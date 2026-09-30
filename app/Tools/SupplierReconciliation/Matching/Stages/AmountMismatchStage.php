<?php

namespace App\Tools\SupplierReconciliation\Matching\Stages;

use App\Tools\SupplierReconciliation\Domain\Side;
use App\Tools\SupplierReconciliation\Domain\Transaction;
use App\Tools\SupplierReconciliation\Matching\AmountRelation;
use App\Tools\SupplierReconciliation\Matching\ItemFactory;
use App\Tools\SupplierReconciliation\Matching\MatchingContext;
use App\Tools\SupplierReconciliation\Matching\PairEvidence;
use App\Tools\SupplierReconciliation\Result\ItemStatus;
use App\Tools\SupplierReconciliation\Result\Reason;
use App\Tools\SupplierReconciliation\Result\ResultItem;

/**
 * Strong reference link but different amounts (spec §23). The engine reports
 * the difference and never decides which amount is right.
 */
final class AmountMismatchStage implements MatchingStage
{
    public function apply(MatchingContext $context): void
    {
        foreach ($context->open(Side::Statement) as $statement) {
            if ($context->isResolved($statement->id) || ! $this->isComparable($statement)
                || $this->hasSameAmountCandidate($context, $statement)) {
                continue;
            }

            $strong = $this->strongEdges($context, $statement);

            if (count($strong) === 1 && $strong[0]->amount === AmountRelation::OppositeSign
                && count($this->strongEdges($context, $strong[0]->ledger)) === 1) {
                $context->resolve($this->oppositeSignItem($strong[0]));

                continue;
            }

            $sameSign = $this->sameSign($strong, $statement);

            if (count($sameSign) === 1) {
                $ledger = $sameSign[0]->ledger;

                if (! $this->hasSameAmountCandidate($context, $ledger)
                    && count($this->sameSign($this->strongEdges($context, $ledger), $ledger)) === 1) {
                    $context->resolve($this->mismatchItem($sameSign[0]));
                }
            }
        }

        foreach ([Side::Statement, Side::Ledger] as $side) {
            foreach ($context->open($side) as $transaction) {
                if ($context->isResolved($transaction->id) || ! $this->isComparable($transaction)
                    || $this->hasSameAmountCandidate($context, $transaction)) {
                    continue;
                }

                $sameSign = $this->sameSign($this->strongEdges($context, $transaction), $transaction);

                if (count($sameSign) >= 2 && $this->exclusive($context, $transaction, $sameSign)) {
                    $context->resolve($this->ambiguousItem($transaction, $sameSign));
                }
            }
        }
    }

    private function isComparable(Transaction $transaction): bool
    {
        return $transaction->hasIdentifyingReference() && $transaction->amount !== null && ! $transaction->amount->isZero();
    }

    /**
     * A same-amount candidate with a related reference is handled as a possible match instead.
     */
    private function hasSameAmountCandidate(MatchingContext $context, Transaction $transaction): bool
    {
        foreach ($context->openEdgesOf($transaction->id) as $edge) {
            if ($edge->amountsEqual() && $edge->reference->isRelated()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<PairEvidence>
     */
    private function strongEdges(MatchingContext $context, Transaction $transaction): array
    {
        return array_values(array_filter(
            $context->openEdgesOf($transaction->id),
            fn (PairEvidence $e): bool => $e->reference->isStrong(),
        ));
    }

    /**
     * @param  list<PairEvidence>  $edges
     * @return list<PairEvidence>
     */
    private function sameSign(array $edges, Transaction $from): array
    {
        return array_values(array_filter($edges, function (PairEvidence $e) use ($from): bool {
            $other = $e->other($from->id)->amount;

            return $other !== null && $from->amount !== null && ! $other->isZero() && $other->hasSameSignAs($from->amount);
        }));
    }

    /**
     * The other lines must not be strongly linked to anything else.
     *
     * @param  list<PairEvidence>  $edges
     */
    private function exclusive(MatchingContext $context, Transaction $transaction, array $edges): bool
    {
        foreach ($edges as $edge) {
            $other = $edge->other($transaction->id);

            if (count($this->strongEdges($context, $other)) !== 1) {
                return false;
            }
        }

        return true;
    }

    private function mismatchItem(PairEvidence $edge): ResultItem
    {
        return ItemFactory::pair(
            status: ItemStatus::AmountMismatch,
            kind: null,
            headline: 'Amount mismatch',
            evidence: $edge,
            extraReasons: [
                Reason::differs('mismatch.amount', 'Same document reference, but the amounts differ by '.$edge->difference()?->abs()->format().'.'),
                Reason::info('mismatch.no_judgement', 'The engine does not decide which amount is correct.'),
            ],
        );
    }

    private function oppositeSignItem(PairEvidence $edge): ResultItem
    {
        return ItemFactory::pair(
            status: ItemStatus::ReviewRequired,
            kind: null,
            headline: 'Same amount with opposite sign',
            evidence: $edge,
            extraReasons: [
                Reason::differs('mismatch.opposite_sign', 'Same reference and amount but opposite signs. The sign was not ignored: check the sign convention, or whether one line is a credit or a reversal.'),
            ],
        );
    }

    /**
     * @param  list<PairEvidence>  $edges
     */
    private function ambiguousItem(Transaction $transaction, array $edges): ResultItem
    {
        $others = array_map(fn (PairEvidence $e): Transaction => $e->other($transaction->id), $edges);
        $otherIds = array_map(fn (Transaction $t): string => $t->id, $others);
        $otherNoun = ItemFactory::sideNoun($transaction->side->other());

        return new ResultItem(
            id: '',
            status: ItemStatus::Ambiguous,
            kind: null,
            headline: count($others)." {$otherNoun} lines share this reference with different amounts",
            statementIds: $transaction->side === Side::Statement ? [$transaction->id] : $otherIds,
            ledgerIds: $transaction->side === Side::Ledger ? [$transaction->id] : $otherIds,
            reasons: [
                Reason::differs('ambiguous.same_reference_different_amounts', count($others)." {$otherNoun} lines (".ItemFactory::rowList($others).') share the reference of '.$transaction->label().', but none has the same amount and they do not add up to it.'),
                Reason::info('ambiguous.no_choice', 'The engine does not pick one of them.'),
            ],
            candidates: array_map(fn (PairEvidence $e) => $e->toCandidate(), $edges),
        );
    }
}

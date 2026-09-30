<?php

namespace App\Tools\SupplierReconciliation\Matching\Stages;

use App\Tools\SupplierReconciliation\Domain\Transaction;
use App\Tools\SupplierReconciliation\Matching\ItemFactory;
use App\Tools\SupplierReconciliation\Matching\MatchingContext;
use App\Tools\SupplierReconciliation\Matching\PairEvidence;
use App\Tools\SupplierReconciliation\Matching\ReferenceRelation;
use App\Tools\SupplierReconciliation\Result\ItemStatus;
use App\Tools\SupplierReconciliation\Result\MatchKind;
use App\Tools\SupplierReconciliation\Result\Reason;

/**
 * Automatic matches (spec §15–17). All conditions are required:
 *
 * - identifying references, identical once formatting (or leading zeros) is ignored;
 * - exactly the same amount;
 * - dates within the tolerance of the reference relation;
 * - mutual uniqueness: neither line has another same-amount candidate sharing
 *   its document number, however it is written (spec §19).
 */
final class CertainMatchStage implements MatchingStage
{
    public function apply(MatchingContext $context): void
    {
        $eligible = array_values(array_filter(
            $context->openEdges(),
            fn (PairEvidence $edge): bool => $this->isCertain($context, $edge),
        ));

        foreach ($eligible as $edge) {
            $exact = $edge->reference === ReferenceRelation::Identical && $edge->dateDifference === 0;

            $context->resolve(ItemFactory::pair(
                status: ItemStatus::Matched,
                kind: $exact ? MatchKind::Exact : MatchKind::Normalized,
                headline: $exact ? 'Matched — Exact' : 'Matched — Normalized',
                evidence: $edge,
                extraReasons: $this->summaryReasons($edge, $exact),
            ));
        }
    }

    private function isCertain(MatchingContext $context, PairEvidence $edge): bool
    {
        if (! $edge->reference->isStrong() || ! $edge->amountsEqual()) {
            return false;
        }

        $datesOk = $edge->reference === ReferenceRelation::LeadingZeros
            ? $edge->datesWithin($context->policy->leadingZerosMaxDateDays)
            : $edge->datesWithin($context->policy->certainMaxDateDays) || $edge->datesAbsent();

        return $datesOk
            && $this->competitors($context, $edge->statement) === 1
            && $this->competitors($context, $edge->ledger) === 1;
    }

    /**
     * Number of open same-amount candidates sharing the document number.
     */
    private function competitors(MatchingContext $context, Transaction $transaction): int
    {
        return count(array_filter(
            $context->openEdgesOf($transaction->id),
            fn (PairEvidence $e): bool => $e->amountsEqual() && $e->reference->isSameNumber(),
        ));
    }

    /**
     * @return list<Reason>
     */
    private function summaryReasons(PairEvidence $edge, bool $exact): array
    {
        if ($exact) {
            return [Reason::agrees('match.exact', 'Same reference, amount and date.')];
        }

        $reasons = [Reason::agrees('match.normalized', match ($edge->reference) {
            ReferenceRelation::LeadingZeros => 'Same reference once leading zeros are removed, same amount and close date. Formatting differences ignored.',
            default => 'Same normalized reference and amount. Formatting differences ignored.',
        })];

        foreach ([$edge->statement, $edge->ledger] as $transaction) {
            $reference = $transaction->reference;
            $compared = $edge->comparedReference($transaction);
            $steps = $reference === null ? [] : $reference->steps;

            if ($reference !== null && $edge->reference === ReferenceRelation::LeadingZeros && $compared !== $reference->typographicKey) {
                $steps[] = 'leading zeros removed';
            }

            if ($reference !== null && $steps !== []) {
                $reasons[] = Reason::info(
                    'reference.transformation',
                    ucfirst($transaction->rowLabel()).': "'.$reference->original.'" compared as '.$compared.' ('.implode('; ', $steps).')',
                );
            }
        }

        if ($edge->datesAbsent()) {
            $reasons[] = Reason::info('match.no_dates', 'Dates were not available: matched on reference and amount only.');
        }

        return $reasons;
    }
}

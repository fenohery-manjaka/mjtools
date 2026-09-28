<?php

namespace App\Tools\SupplierReconciliation\Matching\Stages;

use App\Tools\SupplierReconciliation\Domain\Amount;
use App\Tools\SupplierReconciliation\Domain\Side;
use App\Tools\SupplierReconciliation\Domain\Transaction;
use App\Tools\SupplierReconciliation\Matching\ItemFactory;
use App\Tools\SupplierReconciliation\Matching\MatchingContext;
use App\Tools\SupplierReconciliation\Matching\PairEvidence;
use App\Tools\SupplierReconciliation\Result\ItemStatus;
use App\Tools\SupplierReconciliation\Result\Reason;
use App\Tools\SupplierReconciliation\Result\ResultItem;

/**
 * Detects the same document (same reference and amount) appearing more than
 * once on one side (spec §24). Nothing is removed or corrected.
 *
 * Exception: identical lines that add up exactly to a single line on the
 * other side are proposed as a split (one-to-many) rather than flagged.
 */
final class DuplicateStage implements MatchingStage
{
    public function apply(MatchingContext $context): void
    {
        foreach ([Side::Statement, Side::Ledger] as $side) {
            foreach ($this->groups($context->open($side)) as $group) {
                if ($this->anyResolved($context, $group)) {
                    continue;
                }

                $context->resolve($this->itemFor($context, $group));
            }
        }
    }

    /**
     * @param  list<Transaction>  $transactions
     * @return list<list<Transaction>>
     */
    private function groups(array $transactions): array
    {
        $groups = [];

        foreach ($transactions as $transaction) {
            if (! $transaction->hasIdentifyingReference() || $transaction->amount === null) {
                continue;
            }

            $groups[$transaction->reference?->zeroKey.'#'.$transaction->amount->units][] = $transaction;
        }

        return array_values(array_filter($groups, fn (array $group): bool => count($group) > 1));
    }

    /**
     * @param  list<Transaction>  $group
     */
    private function itemFor(MatchingContext $context, array $group): ResultItem
    {
        $counterparts = $this->strongCounterparts($context, $group);
        $amount = $group[0]->amount ?? Amount::fromUnits(0);
        $total = Amount::sum(array_map(fn (Transaction $t): Amount => $t->amount ?? Amount::fromUnits(0), $group));

        if (count($counterparts) === 1
            && count($group) <= $context->policy->maxGroupSize
            && $counterparts[0]->amount !== null
            && $counterparts[0]->amount->equals($total)) {
            return ItemFactory::grouped($counterparts[0], $group, [
                Reason::partial('group.identical_lines', 'These '.ItemFactory::sideNoun($group[0]->side).' lines are identical ('.$amount->format().' each): this may be a split — or a duplicate.'),
            ]);
        }

        $sameDocument = array_values(array_filter(
            $counterparts,
            fn (Transaction $t): bool => $t->amount !== null && $t->amount->equals($amount),
        ));

        return $this->duplicateItem($context, $group, $sameDocument);
    }

    /**
     * @param  list<Transaction>  $group
     * @param  list<Transaction>  $otherSide
     */
    private function duplicateItem(MatchingContext $context, array $group, array $otherSide): ResultItem
    {
        $side = $group[0]->side;
        $reference = trim((string) $group[0]->reference?->original);
        $amount = $group[0]->amount?->format();
        $otherNoun = ItemFactory::sideNoun($side->other());

        $headline = match (true) {
            count($otherSide) > 1 => 'Repeated on both the statement and the ledger',
            $side === Side::Ledger => 'Potential duplicate in ledger',
            default => 'Potential duplicate on statement',
        };

        $reasons = [
            Reason::differs('duplicate.repeated', ucfirst($side->label()).' contains '.count($group)." lines with reference {$reference} and amount {$amount} (".ItemFactory::rowList($group).').'),
            $otherSide === []
                ? Reason::info('duplicate.not_on_other_side', "No line with this reference and amount on the {$otherNoun}.")
                : Reason::info('duplicate.other_side_count', 'The '.$otherNoun.' shows it '.count($otherSide).' '.(count($otherSide) === 1 ? 'time' : 'times').' ('.ItemFactory::rowList($otherSide).').'),
            Reason::info('duplicate.nothing_changed', 'Nothing was removed or corrected: check whether this document was recorded twice.'),
        ];

        $candidates = [];

        foreach ($otherSide as $other) {
            foreach ($group as $member) {
                [$s, $l] = $side === Side::Ledger ? [$other, $member] : [$member, $other];
                $candidates[] = PairEvidence::between($s, $l, $context->comparator)->toCandidate();
            }
        }

        $statement = $side === Side::Statement ? $group : $otherSide;
        $ledger = $side === Side::Ledger ? $group : $otherSide;

        return new ResultItem(
            id: '',
            status: ItemStatus::DuplicateSuspected,
            kind: null,
            headline: $headline,
            statementIds: $this->ids($statement),
            ledgerIds: $this->ids($ledger),
            reasons: $reasons,
            candidates: $candidates,
        );
    }

    /**
     * @param  list<Transaction>  $transactions
     * @return list<string>
     */
    private function ids(array $transactions): array
    {
        return array_map(fn (Transaction $t): string => $t->id, $transactions);
    }

    /**
     * Open transactions on the other side whose reference is strongly related to the group.
     *
     * @param  list<Transaction>  $group
     * @return list<Transaction>
     */
    private function strongCounterparts(MatchingContext $context, array $group): array
    {
        $found = [];

        foreach ($group as $member) {
            foreach ($context->openEdgesOf($member->id) as $edge) {
                if ($edge->reference->isStrong()) {
                    $other = $edge->other($member->id);
                    $found[$other->id] = $other;
                }
            }
        }

        $found = array_values($found);
        usort($found, fn (Transaction $a, Transaction $b): int => $a->rowNumber <=> $b->rowNumber);

        return $found;
    }

    /**
     * @param  list<Transaction>  $group
     */
    private function anyResolved(MatchingContext $context, array $group): bool
    {
        foreach ($group as $transaction) {
            if ($context->isResolved($transaction->id)) {
                return true;
            }
        }

        return false;
    }
}

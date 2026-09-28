<?php

namespace App\Tools\SupplierReconciliation\Matching\Stages;

use App\Tools\SupplierReconciliation\Domain\Amount;
use App\Tools\SupplierReconciliation\Domain\Side;
use App\Tools\SupplierReconciliation\Domain\Transaction;
use App\Tools\SupplierReconciliation\Matching\ItemFactory;
use App\Tools\SupplierReconciliation\Matching\MatchingContext;
use App\Tools\SupplierReconciliation\Matching\PairEvidence;

/**
 * Simple one-to-many / many-to-one proposals (spec §20): several lines that
 * share one reference and add up exactly to a single line on the other side.
 * Only reference-based combinations are tried; they are always proposals.
 */
final class GroupMatchStage implements MatchingStage
{
    public function apply(MatchingContext $context): void
    {
        foreach ([Side::Statement, Side::Ledger] as $side) {
            foreach ($context->open($side) as $single) {
                if ($context->isResolved($single->id)) {
                    continue;
                }

                $group = $this->groupFor($context, $single);

                if ($group !== null) {
                    $context->resolve(ItemFactory::grouped($single, $group));
                }
            }
        }
    }

    /**
     * @return list<Transaction>|null
     */
    private function groupFor(MatchingContext $context, Transaction $single): ?array
    {
        if (! $single->hasIdentifyingReference() || $single->amount === null || $single->amount->isZero()) {
            return null;
        }

        $group = array_map(
            fn (PairEvidence $e): Transaction => $e->other($single->id),
            array_values(array_filter($context->openEdgesOf($single->id), fn (PairEvidence $e): bool => $e->reference->isStrong())),
        );

        if (count($group) < 2 || count($group) > $context->policy->maxGroupSize) {
            return null;
        }

        foreach ($group as $member) {
            if ($member->amount === null || ! $member->amount->hasSameSignAs($single->amount)) {
                return null;
            }

            // A member also strongly linked to another open line makes the grouping ambiguous.
            $strongLinks = array_filter($context->openEdgesOf($member->id), fn (PairEvidence $e): bool => $e->reference->isStrong());

            if (count($strongLinks) !== 1) {
                return null;
            }
        }

        $total = Amount::sum(array_map(fn (Transaction $t): Amount => $t->amount ?? Amount::fromUnits(0), $group));

        return $total->equals($single->amount) ? $group : null;
    }
}

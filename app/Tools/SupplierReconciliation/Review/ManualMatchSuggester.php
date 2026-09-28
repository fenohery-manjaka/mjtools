<?php

namespace App\Tools\SupplierReconciliation\Review;

use App\Tools\SupplierReconciliation\Domain\Amount;
use App\Tools\SupplierReconciliation\Domain\Side;
use App\Tools\SupplierReconciliation\Domain\Transaction;
use App\Tools\SupplierReconciliation\Matching\ReferenceComparator;

/**
 * Lists lines the user may link manually to an item, most plausible first:
 * same amount, then related reference, then closest date.
 */
final class ManualMatchSuggester
{
    public function __construct(
        private readonly ReferenceComparator $comparator = new ReferenceComparator,
    ) {}

    /**
     * @return list<Transaction>
     */
    public function suggest(ReviewedResult $reviewed, ReviewedItem $item, string $query = '', int $limit = 30): array
    {
        $ownSide = $item->statementIds !== [] ? Side::Statement : Side::Ledger;
        $own = array_map(
            fn (string $id): Transaction => $reviewed->engine->transaction($id),
            $ownSide === Side::Statement ? $item->statementIds : $item->ledgerIds,
        );

        if ($own === []) {
            return [];
        }

        $anchor = $own[0];
        $total = Amount::sum(array_map(fn (Transaction $t): Amount => $t->amount ?? Amount::fromUnits(0), $own));
        $query = mb_strtolower(trim($query));
        $scored = [];

        foreach ($reviewed->unsettledTransactionIds($ownSide->other()) as $id) {
            $candidate = $reviewed->engine->transaction($id);

            if ($query !== '' && ! str_contains($this->searchText($candidate), $query)) {
                continue;
            }

            $score = 0;

            if ($candidate->amount !== null && $candidate->amount->equals($total)) {
                $score += 1000;
            }

            if ($this->comparator->compare($anchor->reference, $candidate->reference)->isRelated()) {
                $score += 500;
            }

            if ($anchor->date !== null && $candidate->date !== null) {
                $score += max(0, 100 - $anchor->date->daysBetween($candidate->date));
            }

            $scored[] = [$score, $candidate];
        }

        usort($scored, fn (array $a, array $b): int => [$b[0], $a[1]->rowNumber] <=> [$a[0], $b[1]->rowNumber]);

        return array_map(fn (array $entry): Transaction => $entry[1], array_slice($scored, 0, $limit));
    }

    private function searchText(Transaction $transaction): string
    {
        return mb_strtolower(implode(' ', [
            ...array_values($transaction->original),
            $transaction->amount?->format() ?? '',
            (string) $transaction->rowNumber,
        ]));
    }
}

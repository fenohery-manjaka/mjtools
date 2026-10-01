<?php

namespace App\Tools\SupplierReconciliation\Mapping;

use App\Tools\SupplierReconciliation\Domain\Transaction;

final readonly class BuiltTransactions
{
    /**
     * @param  list<Transaction>  $transactions
     * @param  array<int, list<string>>  $rowIssues  Spreadsheet row number => problems.
     */
    public function __construct(
        public array $transactions,
        public array $rowIssues,
        public int $filteredOut,
        public int $ignoredTextRows = 0,
        public CurrencyEvidence $currencies = new CurrencyEvidence,
    ) {}

    public function count(): int
    {
        return count($this->transactions);
    }

    public function unreadableAmounts(): int
    {
        return count(array_filter(
            $this->transactions,
            fn (Transaction $t): bool => $t->amount === null && ! $t->isBalanceLine,
        ));
    }

    public function unreadableDates(): int
    {
        return count(array_filter(
            $this->transactions,
            fn (Transaction $t): bool => $t->date === null && $t->original('date') !== null,
        ));
    }

    public function withoutReference(): int
    {
        return count(array_filter(
            $this->transactions,
            fn (Transaction $t): bool => ! $t->hasIdentifyingReference() && ! $t->isBalanceLine,
        ));
    }
}

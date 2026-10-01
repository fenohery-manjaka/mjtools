<?php

namespace App\Tools\SupplierReconciliation\Mapping;

use App\Tools\SupplierReconciliation\Domain\Amount;
use App\Tools\SupplierReconciliation\Domain\Transaction;

/**
 * Optional consistency check of a statement (spec §4, A.12): opening balance
 * plus the lines of the period should give the closing balance.
 *
 * Only balance lines already recognised by their explicit wording are used:
 * the one before the first transaction is the opening balance (zero when
 * there is none), the last one after the last transaction is the closing
 * balance. Without a closing balance, or with unreadable amounts, the check
 * is reported as unavailable rather than guessed. It never blocks the
 * reconciliation and never changes any line.
 */
final class BalanceCheck
{
    public const VERIFIED = 'verified';

    public const INCONSISTENT = 'inconsistent';

    public const UNAVAILABLE = 'unavailable';

    /**
     * @param  list<Transaction>  $transactions  In file order.
     * @return array{status: string, opening: ?string, movements: ?string, expected: ?string, closing: ?string, difference: ?string, message: string}
     */
    public function check(array $transactions): array
    {
        $movements = array_values(array_filter($transactions, fn (Transaction $t): bool => ! $t->isBalanceLine));

        if ($movements === []) {
            return $this->unavailable('The statement has no transaction lines to add up.');
        }

        $firstRow = $movements[0]->rowNumber;
        $lastRow = $movements[count($movements) - 1]->rowNumber;
        $opening = null;
        $closing = null;

        foreach ($transactions as $transaction) {
            if (! $transaction->isBalanceLine) {
                continue;
            }

            if ($transaction->rowNumber < $firstRow && $opening === null) {
                $opening = $transaction;
            } elseif ($transaction->rowNumber > $lastRow) {
                $closing = $transaction;
            }
        }

        if ($closing === null) {
            return $this->unavailable('The statement shows no closing balance after its last line: its total cannot be checked.');
        }

        if ($closing->amount === null || ($opening !== null && $opening->amount === null)) {
            return $this->unavailable('A balance line of the statement could not be read: its total cannot be checked.');
        }

        $unreadable = count(array_filter($movements, fn (Transaction $t): bool => $t->amount === null));

        if ($unreadable > 0) {
            return $this->unavailable("{$unreadable} amount(s) of the statement could not be read: its total cannot be checked.");
        }

        $openingAmount = $opening->amount ?? Amount::fromUnits(0);
        $sum = Amount::sum(array_map(fn (Transaction $t): Amount => $t->amount ?? Amount::fromUnits(0), $movements));
        $expected = $openingAmount->plus($sum);
        $difference = $closing->amount->minus($expected);
        $consistent = $difference->isZero();

        $from = $opening === null ? 'the lines of the statement' : 'the opening balance plus the lines of the statement';

        return [
            'status' => $consistent ? self::VERIFIED : self::INCONSISTENT,
            'opening' => $opening?->amount?->format(),
            'movements' => $sum->format(),
            'expected' => $expected->format(),
            'closing' => $closing->amount->format(),
            'difference' => $consistent ? null : $difference->format(),
            'message' => $consistent
                ? "The closing balance ({$closing->amount->format()}) equals {$from}."
                : "The closing balance ({$closing->amount->format()}) differs from {$from} ({$expected->format()}) by {$difference->format()}. Lines may be missing from the file, or the balance may include other items: this does not change the reconciliation.",
        ];
    }

    /**
     * @return array{status: string, opening: null, movements: null, expected: null, closing: null, difference: null, message: string}
     */
    private function unavailable(string $message): array
    {
        return [
            'status' => self::UNAVAILABLE,
            'opening' => null,
            'movements' => null,
            'expected' => null,
            'closing' => null,
            'difference' => null,
            'message' => $message,
        ];
    }
}

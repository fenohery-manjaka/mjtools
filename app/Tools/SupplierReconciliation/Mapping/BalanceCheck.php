<?php

namespace App\Tools\SupplierReconciliation\Mapping;

use App\Tools\SupplierReconciliation\Domain\Amount;
use App\Tools\SupplierReconciliation\Domain\Transaction;

/**
 * Optional consistency check of a statement (spec §4, A.12): opening balance
 * plus the lines of the period should give the closing balance.
 *
 * Balances come from the statement itself, never from a guess:
 * - balance lines recognised by explicit wording, the one before the first
 *   transaction being the opening balance and the last one after the last
 *   transaction the closing balance;
 * - otherwise, when a running balance column is mapped, the running balance
 *   before the first line and after the last one.
 *
 * Without a closing balance, or with unreadable amounts, the check is
 * reported as unavailable. It never blocks the reconciliation and never
 * changes any line.
 */
final class BalanceCheck
{
    public const VERIFIED = 'verified';

    public const INCONSISTENT = 'inconsistent';

    public const UNAVAILABLE = 'unavailable';

    /**
     * @param  list<Transaction>  $transactions  In file order.
     * @param  array<int, Amount>  $runningBalances  Row number => running balance, statement point of view.
     * @return array{status: string, opening: ?string, movements: ?string, expected: ?string, closing: ?string, difference: ?string, message: string}
     */
    public function check(array $transactions, array $runningBalances = []): array
    {
        $movements = array_values(array_filter($transactions, fn (Transaction $t): bool => ! $t->isBalanceLine));

        if ($movements === []) {
            return $this->unavailable('The statement has no transaction lines to add up.');
        }

        $first = $movements[0];
        $last = $movements[count($movements) - 1];
        $openingLine = null;
        $closingLine = null;

        foreach ($transactions as $transaction) {
            if (! $transaction->isBalanceLine) {
                continue;
            }

            if ($transaction->rowNumber < $first->rowNumber && $openingLine === null) {
                $openingLine = $transaction;
            } elseif ($transaction->rowNumber > $last->rowNumber) {
                $closingLine = $transaction;
            }
        }

        $closing = $closingLine === null
            ? ($runningBalances[$last->rowNumber] ?? null)
            : ($closingLine->amount ?? $runningBalances[$closingLine->rowNumber] ?? null);

        if ($closing === null) {
            return $this->unavailable(match (true) {
                $closingLine === null => 'The statement shows no closing balance after its last line: its total cannot be checked.',
                $closingLine->issues === [] => 'The closing balance line has no amount in the amount columns (map the running balance column to use it): the total cannot be checked.',
                default => 'The closing balance of the statement could not be read: its total cannot be checked.',
            });
        }

        $unreadable = count(array_filter($movements, fn (Transaction $t): bool => $t->amount === null));

        if ($unreadable > 0) {
            return $this->unavailable("{$unreadable} amount(s) of the statement could not be read: its total cannot be checked.");
        }

        $opening = $this->opening($openingLine, $first, $runningBalances);

        if ($opening === false) {
            return $this->unavailable('The opening balance of the statement has no readable amount (map the running balance column if it is only there): its total cannot be checked.');
        }

        $sum = Amount::sum(array_map(fn (Transaction $t): Amount => $t->amount ?? Amount::fromUnits(0), $movements));
        $expected = ($opening ?? Amount::fromUnits(0))->plus($sum);
        $difference = $closing->minus($expected);
        $consistent = $difference->isZero();

        $from = $opening === null ? 'the lines of the statement' : 'the opening balance plus the lines of the statement';

        return [
            'status' => $consistent ? self::VERIFIED : self::INCONSISTENT,
            'opening' => $opening?->format(),
            'movements' => $sum->format(),
            'expected' => $expected->format(),
            'closing' => $closing->format(),
            'difference' => $consistent ? null : $difference->format(),
            'message' => $consistent
                ? "The closing balance ({$closing->format()}) equals {$from}."
                : "The closing balance ({$closing->format()}) differs from {$from} ({$expected->format()}) by {$difference->format()}. Lines may be missing from the file, or the balance may include other items: this does not change the reconciliation.",
        ];
    }

    /**
     * Opening balance: the opening balance line, otherwise the running balance
     * before the first line. Null when the statement shows none (zero is then
     * assumed), false when it is shown without a readable amount.
     *
     * @param  array<int, Amount>  $runningBalances
     */
    private function opening(?Transaction $line, Transaction $first, array $runningBalances): Amount|false|null
    {
        if ($line !== null) {
            return $line->amount ?? $runningBalances[$line->rowNumber] ?? false;
        }

        $afterFirst = $runningBalances[$first->rowNumber] ?? null;

        return $afterFirst === null || $first->amount === null ? null : $afterFirst->minus($first->amount);
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

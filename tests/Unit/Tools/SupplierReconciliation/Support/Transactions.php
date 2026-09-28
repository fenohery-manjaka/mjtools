<?php

namespace Tests\Unit\Tools\SupplierReconciliation\Support;

use App\Tools\SupplierReconciliation\Domain\Side;
use App\Tools\SupplierReconciliation\Domain\Transaction;
use App\Tools\SupplierReconciliation\Normalization\AmountParser;
use App\Tools\SupplierReconciliation\Normalization\BalanceLineDetector;
use App\Tools\SupplierReconciliation\Normalization\DateOrder;
use App\Tools\SupplierReconciliation\Normalization\DateParser;
use App\Tools\SupplierReconciliation\Normalization\DecimalSeparator;
use App\Tools\SupplierReconciliation\Normalization\DocumentTypeClassifier;
use App\Tools\SupplierReconciliation\Normalization\ReferenceNormalizer;

/**
 * Builds canonical transactions from simple rows for engine tests:
 * [reference, date (DD/MM/YYYY), amount, type?].
 */
final class Transactions
{
    /**
     * @param  list<array{0: ?string, 1: ?string, 2: ?string, 3?: ?string}>  $rows
     * @return list<Transaction>
     */
    public static function statement(array $rows, ?DecimalSeparator $separator = null): array
    {
        return self::build(Side::Statement, $rows, $separator);
    }

    /**
     * @param  list<array{0: ?string, 1: ?string, 2: ?string, 3?: ?string}>  $rows
     * @return list<Transaction>
     */
    public static function ledger(array $rows, ?DecimalSeparator $separator = null): array
    {
        return self::build(Side::Ledger, $rows, $separator);
    }

    /**
     * @param  list<array{0: ?string, 1: ?string, 2: ?string, 3?: ?string}>  $rows
     * @return list<Transaction>
     */
    private static function build(Side $side, array $rows, ?DecimalSeparator $separator): array
    {
        $references = new ReferenceNormalizer;
        $amounts = new AmountParser;
        $dates = new DateParser;
        $types = new DocumentTypeClassifier;
        $balance = new BalanceLineDetector;
        $transactions = [];

        foreach ($rows as $index => $row) {
            [$rawReference, $rawDate, $rawAmount] = $row;
            $rawType = $row[3] ?? null;

            $reference = $references->normalize($rawReference);
            $amount = $amounts->parse($rawAmount, $separator);
            $date = $dates->parse($rawDate, DateOrder::DayFirst);

            $issues = [];

            if ($amount->error !== null) {
                $issues[] = "Amount \"{$rawAmount}\": {$amount->error}";
            }

            if ($date->error !== null) {
                $issues[] = "Date \"{$rawDate}\": {$date->error}";
            }

            $transactions[] = new Transaction(
                id: $side->idPrefix().($index + 1),
                side: $side,
                rowNumber: $index + 2,
                original: array_filter([
                    'reference' => $rawReference,
                    'date' => $rawDate,
                    'amount' => $rawAmount,
                    'type' => $rawType,
                ], fn (?string $v): bool => $v !== null),
                reference: $reference,
                date: $date->date,
                amount: $amount->amount,
                documentType: $types->classify($rawType, $reference, $amount->amount),
                issues: $issues,
                isBalanceLine: $balance->isBalanceLine([$rawReference, $rawType]),
            );
        }

        return $transactions;
    }
}

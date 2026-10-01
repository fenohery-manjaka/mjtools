<?php

namespace App\Tools\SupplierReconciliation\Mapping;

use App\Tools\SupplierReconciliation\Domain\Side;
use App\Tools\SupplierReconciliation\Domain\Transaction;
use App\Tools\SupplierReconciliation\Normalization\DateOrder;
use App\Tools\SupplierReconciliation\Normalization\DecimalSeparator;

/**
 * Checks both files before reconciling (spec §37, §38). Blocking problems
 * prevent the reconciliation: it never runs silently on clearly wrong data.
 */
final class PreflightCheck
{
    /** Above this share of unreadable amounts the reconciliation is refused. */
    private const MAX_UNREADABLE_AMOUNTS = 0.2;

    private const MAX_UNREADABLE_DATES = 0.5;

    public function __construct(
        private readonly FormatDetector $formats = new FormatDetector,
        private readonly CurrencyCheck $currencies = new CurrencyCheck,
        private readonly BalanceCheck $balance = new BalanceCheck,
    ) {}

    /**
     * @param  ?string  $currency  Currency confirmed for the reconciliation; null while not confirmed.
     * @return array{ready: bool, blocking: list<string>, warnings: list<string>, sides: array<string, array<string, mixed>>, suggest_inverting_ledger_sign: bool, currency: ?string, balance: array<string, ?string>}
     */
    public function check(PreparedSide $statement, PreparedSide $ledger, ?string $currency): array
    {
        $blocking = [];
        $warnings = [];
        $sides = [];

        if ($currency === null) {
            $blocking[] = 'Confirm the currency of this reconciliation in the Columns step: amounts are only compared within one currency.';
        }

        foreach ([$statement, $ledger] as $prepared) {
            [$sideBlocking, $sideWarnings] = $this->checkSide($prepared);
            $blocking = [...$blocking, ...$sideBlocking, ...$this->currencies->problems($prepared, $currency)];
            $warnings = [...$warnings, ...$sideWarnings];
            $sides[$prepared->side->value] = $this->describeSide($prepared);
        }

        // Optional and informative: an inconsistent balance never blocks the reconciliation.
        $balance = $this->balance->check($statement->built->transactions, $statement->built->runningBalances);

        if ($balance['status'] === BalanceCheck::INCONSISTENT) {
            $warnings[] = $balance['message'];
        }

        $invertLedger = $this->signsLookInverted($statement->built->transactions, $ledger->built->transactions);

        if ($invertLedger) {
            $warnings[] = 'Most lines sharing a reference have opposite signs on the two files: the ledger sign convention is probably inverted. Check "Invoices appear as" for the ledger.';
        }

        return [
            'ready' => $blocking === [],
            'blocking' => $blocking,
            'warnings' => $warnings,
            'sides' => $sides,
            'suggest_inverting_ledger_sign' => $invertLedger,
            'currency' => $currency,
            'balance' => $balance,
        ];
    }

    /**
     * @return array{0: list<string>, 1: list<string>}
     */
    private function checkSide(PreparedSide $prepared): array
    {
        $name = $prepared->side === Side::Statement ? 'supplier statement' : 'ledger';
        $mapping = $prepared->mapping;
        $built = $prepared->built;
        $blocking = [];
        $warnings = [];

        $used = array_count_values(array_map('strval', $this->usedColumns($mapping)));

        foreach ($used as $column => $count) {
            if ($count > 1) {
                $blocking[] = "The same column (\"{$prepared->table->headers[(int) $column]}\") is mapped to several fields in the {$name}.";
            }
        }

        if (! $mapping->has(Field::Reference)) {
            $blocking[] = "Choose the Reference column of the {$name}: references are needed for a reliable reconciliation.";
        }

        if (! $mapping->hasAmount()) {
            $blocking[] = $mapping->amountMode === AmountMode::Signed
                ? "Choose the Amount column of the {$name}."
                : "Choose the Debit and Credit columns of the {$name}.";
        } elseif ($mapping->amountMode === AmountMode::DebitCredit && (! $mapping->has(Field::Debit) || ! $mapping->has(Field::Credit))) {
            $warnings[] = "Only one of the Debit/Credit columns is mapped in the {$name}.";
        }

        if (! $mapping->has(Field::Date)) {
            $warnings[] = "No date column in the {$name}: fewer lines can be matched automatically.";
        }

        if ($mapping->signFromType && ! $mapping->has(Field::Type)) {
            $blocking[] = "The sign is set to come from the Type column, but no Type column is mapped in the {$name}.";
        }

        if ($blocking !== []) {
            return [$blocking, $warnings];
        }

        $count = $built->count();

        if ($count === 0) {
            $blocking[] = $mapping->supplierFilter !== null
                ? "No {$name} line matches the supplier \"{$mapping->supplierFilter}\"."
                : "No transaction lines were found in the {$name}.";

            return [$blocking, $warnings];
        }

        $unreadableAmounts = $built->unreadableAmounts();

        if ($unreadableAmounts / $count > self::MAX_UNREADABLE_AMOUNTS) {
            $blocking[] = "{$unreadableAmounts} of {$count} amounts in the {$name} could not be read: check the amount column and the decimal separator.";
        } elseif ($unreadableAmounts > 0) {
            $warnings[] = "{$unreadableAmounts} amount(s) in the {$name} could not be read: these lines will need review.";
        }

        $unreadableDates = $built->unreadableDates();

        if ($mapping->has(Field::Date) && $unreadableDates / $count > self::MAX_UNREADABLE_DATES) {
            $blocking[] = "{$unreadableDates} of {$count} dates in the {$name} could not be read: check the date column and format.";
        } elseif ($unreadableDates > 0) {
            $warnings[] = "{$unreadableDates} date(s) in the {$name} could not be read.";
        }

        $withoutReference = $built->withoutReference();

        if ($withoutReference / $count > 0.5) {
            $warnings[] = "{$withoutReference} of {$count} lines in the {$name} have no usable reference: most results will need your review.";
        }

        $supplierColumn = $mapping->column(Field::Supplier);

        if ($supplierColumn !== null && $mapping->supplierFilter === null) {
            $suppliers = count(array_unique(array_filter(
                array_map(fn (string $v): string => mb_strtolower(trim($v)), $prepared->table->column($supplierColumn)),
                fn (string $v): bool => $v !== '',
            )));

            if ($suppliers > 1) {
                $warnings[] = "The {$name} contains lines of {$suppliers} different suppliers: choose the supplier of this statement, otherwise other suppliers' lines will be compared too.";
            }
        }

        $dateColumn = $mapping->column(Field::Date);

        if ($dateColumn !== null) {
            $order = $this->formats->dateOrder($prepared->table->column($dateColumn));

            if ($order['conflicting']) {
                $warnings[] = "Dates in the {$name} mix day-first and month-first formats: check the date column.";
            } elseif ($order['ambiguous']) {
                $warnings[] = "All dates in the {$name} could be read day-first or month-first. They are read as ".($mapping->dateOrder === DateOrder::DayFirst ? 'day first (DD/MM/YYYY)' : 'month first (MM/DD/YYYY)').': change it if needed.';
            } elseif ($order['order'] !== $mapping->dateOrder) {
                $warnings[] = "The dates in the {$name} look ".($order['order'] === DateOrder::DayFirst ? 'day-first' : 'month-first').' but are read the other way.';
            }
        }

        $amountValues = [];

        foreach ([Field::Amount, Field::Debit, Field::Credit] as $field) {
            $column = $mapping->column($field);

            if ($column !== null) {
                $amountValues = [...$amountValues, ...$prepared->table->column($column)];
            }
        }

        if ($this->formats->decimalSeparator($amountValues)['conflicting']) {
            $warnings[] = "Amounts in the {$name} mix dot and comma decimal separators: check the amount column.";
        }

        return [$blocking, $warnings];
    }

    /**
     * @return array<string, mixed>
     */
    private function describeSide(PreparedSide $prepared): array
    {
        $mapping = $prepared->mapping;
        $built = $prepared->built;

        return [
            'rows' => count($prepared->table->rows),
            'transactions' => $built->count(),
            'fields' => [
                'reference' => $mapping->has(Field::Reference),
                'date' => $mapping->has(Field::Date),
                'amount' => $mapping->hasAmount(),
            ],
            'unreadable_amounts' => $built->unreadableAmounts(),
            'unreadable_dates' => $built->unreadableDates(),
            'without_reference' => $built->withoutReference(),
            'filtered_out' => $built->filteredOut,
            'ignored_text_rows' => $built->ignoredTextRows,
            'conventions' => $this->conventions($mapping),
            'currencies' => $built->currencies->describe(),
            'row_issues' => array_slice(
                array_map(fn (int $row, array $issues): array => ['row' => $row, 'issues' => $issues], array_keys($built->rowIssues), $built->rowIssues),
                0,
                20,
            ),
        ];
    }

    /**
     * @return list<string>
     */
    public function conventions(ColumnMapping $mapping): array
    {
        $conventions = [];

        if ($mapping->amountMode === AmountMode::Signed) {
            $conventions[] = $mapping->invoiceSign === ColumnMapping::INVOICES_POSITIVE
                ? 'Invoices are positive amounts (used as is)'
                : 'Invoices are negative amounts (signs inverted for comparison)';
        } else {
            $conventions[] = $mapping->invoiceColumn === ColumnMapping::INVOICES_IN_DEBIT
                ? 'Invoices are in the Debit column'
                : 'Invoices are in the Credit column';
        }

        if ($mapping->signFromType) {
            $conventions[] = 'Sign taken from the Type column (credits and payments reduce the balance)';
        }

        $conventions[] = $mapping->decimalSeparator === DecimalSeparator::Comma ? 'Decimal separator: comma' : 'Decimal separator: dot';

        if ($mapping->has(Field::Date)) {
            $conventions[] = $mapping->dateOrder === DateOrder::DayFirst ? 'Dates read day first (DD/MM/YYYY)' : 'Dates read month first (MM/DD/YYYY)';
        }

        if ($mapping->supplierFilter !== null) {
            $conventions[] = "Only lines of supplier \"{$mapping->supplierFilter}\"";
        }

        return $conventions;
    }

    /**
     * @return list<int>
     */
    private function usedColumns(ColumnMapping $mapping): array
    {
        $ignored = $mapping->amountMode === AmountMode::Signed ? [Field::Debit->value, Field::Credit->value] : [Field::Amount->value];

        return array_values(array_diff_key($mapping->columns, array_flip($ignored)));
    }

    /**
     * Compares signs of lines sharing a reference and the same absolute amount.
     *
     * @param  list<Transaction>  $statement
     * @param  list<Transaction>  $ledger
     */
    private function signsLookInverted(array $statement, array $ledger): bool
    {
        $index = [];

        foreach ($statement as $transaction) {
            if ($transaction->hasIdentifyingReference() && $transaction->amount !== null && ! $transaction->amount->isZero()) {
                $index[$transaction->reference?->typographicKey][] = $transaction->amount;
            }
        }

        $same = 0;
        $opposite = 0;

        foreach ($ledger as $transaction) {
            $amount = $transaction->amount;

            if (! $transaction->hasIdentifyingReference() || $amount === null || $amount->isZero()) {
                continue;
            }

            foreach ($index[$transaction->reference?->typographicKey] ?? [] as $other) {
                if ($other->abs()->equals($amount->abs())) {
                    $other->equals($amount) ? $same++ : $opposite++;
                }
            }
        }

        return $opposite >= 3 && $opposite > $same * 2;
    }
}

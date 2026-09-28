<?php

namespace App\Tools\SupplierReconciliation\Mapping;

use App\Tools\SupplierReconciliation\Domain\Amount;
use App\Tools\SupplierReconciliation\Domain\DocumentType;
use App\Tools\SupplierReconciliation\Domain\Side;
use App\Tools\SupplierReconciliation\Domain\Transaction;
use App\Tools\SupplierReconciliation\Import\ImportedTable;
use App\Tools\SupplierReconciliation\Normalization\AmountParser;
use App\Tools\SupplierReconciliation\Normalization\BalanceLineDetector;
use App\Tools\SupplierReconciliation\Normalization\DateParser;
use App\Tools\SupplierReconciliation\Normalization\DocumentTypeClassifier;
use App\Tools\SupplierReconciliation\Normalization\ReferenceNormalizer;

/**
 * Turns imported rows into canonical transactions using a confirmed mapping.
 *
 * Original cell values are kept untouched; normalized values are computed
 * next to them. Amounts are expressed from the supplier statement's point of
 * view (invoices positive) by applying the file-wide sign convention.
 */
final class TransactionBuilder
{
    public function __construct(
        private readonly ReferenceNormalizer $references = new ReferenceNormalizer,
        private readonly AmountParser $amounts = new AmountParser,
        private readonly DateParser $dates = new DateParser,
        private readonly DocumentTypeClassifier $types = new DocumentTypeClassifier,
        private readonly BalanceLineDetector $balanceLines = new BalanceLineDetector,
    ) {}

    public function build(ImportedTable $table, ColumnMapping $mapping, Side $side): BuiltTransactions
    {
        $transactions = [];
        $rowIssues = [];
        $filteredOut = 0;
        $ignored = 0;

        foreach ($table->rows as $row) {
            $original = $this->originalValues($row['cells'], $mapping);

            if ($original === []) {
                continue;
            }

            // Free text lines (notes, messages) carry no reference, date or amount.
            if (array_intersect_key($original, array_flip(['reference', 'date', 'amount', 'debit', 'credit'])) === []) {
                $ignored++;

                continue;
            }

            if ($this->isFilteredOut($original, $mapping)) {
                $filteredOut++;

                continue;
            }

            $transaction = $this->transaction(
                id: $side->idPrefix().(count($transactions) + 1),
                side: $side,
                rowNumber: $row['number'],
                original: $original,
                mapping: $mapping,
            );

            if ($transaction->issues !== []) {
                $rowIssues[$row['number']] = $transaction->issues;
            }

            $transactions[] = $transaction;
        }

        return new BuiltTransactions($transactions, $rowIssues, $filteredOut, $ignored);
    }

    /**
     * @param  array<string, string>  $original
     */
    private function transaction(string $id, Side $side, int $rowNumber, array $original, ColumnMapping $mapping): Transaction
    {
        $issues = [];
        $notes = [];
        $reference = $this->references->normalize($original[Field::Reference->value] ?? null);

        $date = null;

        if (isset($original[Field::Date->value])) {
            $parsed = $this->dates->parse($original[Field::Date->value], $mapping->dateOrder);
            $date = $parsed->date;

            if ($parsed->error !== null) {
                $issues[] = 'Date "'.$original[Field::Date->value].'" could not be read ('.$parsed->error.').';
            }
        }

        $amount = $this->amount($original, $mapping, $issues, $notes);
        $typeText = $original[Field::Type->value] ?? null;

        if ($amount !== null && $mapping->signFromType && $mapping->has(Field::Type)) {
            $type = $this->types->fromTypeText($typeText) ?? $this->types->classify(null, $reference, null);

            if ($type === DocumentType::Unknown) {
                $issues[] = 'Type "'.($typeText ?? '').'" not recognised: the sign of this amount cannot be determined.';
                $amount = null;
            } else {
                $amount = $type->reducesBalance() ? $amount->abs()->negate() : $amount->abs();
                $notes[] = 'sign taken from the Type column';
            }
        }

        return new Transaction(
            id: $id,
            side: $side,
            rowNumber: $rowNumber,
            original: $original,
            reference: $reference,
            date: $date,
            amount: $amount,
            documentType: $this->types->classify($typeText, $reference, $amount),
            issues: $issues,
            amountNotes: $notes,
            isBalanceLine: $this->balanceLines->isBalanceLine([
                $original[Field::Reference->value] ?? null,
                $typeText,
                $original[Field::Description->value] ?? null,
            ]),
        );
    }

    /**
     * @param  array<string, string>  $original
     * @param  list<string>  $issues
     * @param  list<string>  $notes
     */
    private function amount(array $original, ColumnMapping $mapping, array &$issues, array &$notes): ?Amount
    {
        if ($mapping->amountMode === AmountMode::Signed) {
            $raw = $original[Field::Amount->value] ?? null;
            $parsed = $this->amounts->parse($raw, $mapping->decimalSeparator);

            if ($parsed->error !== null) {
                $issues[] = 'Amount "'.$raw.'" could not be read ('.$parsed->error.').';
            }

            if ($parsed->amount === null || $mapping->invoiceSign === ColumnMapping::INVOICES_POSITIVE) {
                return $parsed->amount;
            }

            $notes[] = 'amounts inverted (in this file invoices are negative)';

            return $parsed->amount->negate();
        }

        $debit = $this->amounts->parse($original[Field::Debit->value] ?? null, $mapping->decimalSeparator);
        $credit = $this->amounts->parse($original[Field::Credit->value] ?? null, $mapping->decimalSeparator);

        foreach (['debit' => $debit, 'credit' => $credit] as $name => $parsed) {
            if ($parsed->error !== null) {
                $issues[] = ucfirst($name).' "'.($original[$name] ?? '').'" could not be read ('.$parsed->error.').';
            }
        }

        if ($debit->error !== null || $credit->error !== null || ($debit->amount === null && $credit->amount === null)) {
            return null;
        }

        $net = ($debit->amount ?? Amount::fromUnits(0))->minus($credit->amount ?? Amount::fromUnits(0));

        if ($mapping->invoiceColumn === ColumnMapping::INVOICES_IN_CREDIT) {
            $notes[] = 'debit/credit columns combined (invoices are in the Credit column)';

            return $net->negate();
        }

        return $net;
    }

    /**
     * @param  list<string>  $cells
     * @return array<string, string> Mapped, non-empty original values.
     */
    private function originalValues(array $cells, ColumnMapping $mapping): array
    {
        $values = [];

        foreach (Field::cases() as $field) {
            $column = $mapping->column($field);

            if ($column === null || in_array($field, $this->unusedAmountFields($mapping), true)) {
                continue;
            }

            $value = $cells[$column] ?? '';

            if (trim($value) !== '') {
                $values[$field->value] = $value;
            }
        }

        return $values;
    }

    /**
     * @return list<Field>
     */
    private function unusedAmountFields(ColumnMapping $mapping): array
    {
        return $mapping->amountMode === AmountMode::Signed ? [Field::Debit, Field::Credit] : [Field::Amount];
    }

    /**
     * @param  array<string, string>  $original
     */
    private function isFilteredOut(array $original, ColumnMapping $mapping): bool
    {
        if ($mapping->supplierFilter === null || ! $mapping->has(Field::Supplier)) {
            return false;
        }

        return mb_strtolower(trim($original[Field::Supplier->value] ?? '')) !== mb_strtolower($mapping->supplierFilter);
    }
}

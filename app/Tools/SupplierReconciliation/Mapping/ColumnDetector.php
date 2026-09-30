<?php

namespace App\Tools\SupplierReconciliation\Mapping;

use App\Tools\SupplierReconciliation\Domain\DocumentType;
use App\Tools\SupplierReconciliation\Domain\Side;
use App\Tools\SupplierReconciliation\Import\ImportedTable;
use App\Tools\SupplierReconciliation\Normalization\AmountParser;
use App\Tools\SupplierReconciliation\Normalization\DocumentTypeClassifier;
use App\Tools\SupplierReconciliation\Normalization\ReferenceNormalizer;

/**
 * Proposes a column mapping from header names and column contents. The user
 * always confirms or corrects it (spec §7–9).
 */
final class ColumnDetector
{
    private const SAMPLE_ROWS = 200;

    private const HEADERS = [
        'date' => '/\b(date|dated|posting|posted|doc(ument)? date|invoice date|transaction date|le)\b/iu',
        'amount' => '/\b(amount|montant|total|gross|value|valeur|net|sum|ttc|importe|betrag)\b/iu',
        'debit' => '/^\s*(debit|débit|dr|debit amount|debits)\s*$/iu',
        'credit' => '/^\s*(credit|crédit|cr|credit amount|credits)\s*$/iu',
        'balance' => '/\b(balance|solde|running|cumul|outstanding)\b/iu',
        'reference' => '/\b(ref|reference|référence|invoice|inv|document|doc|no|nº|n°|number|num|numéro|facture|pièce|piece|voucher|external)\b/iu',
        'type' => '/\b(type|nature|kind|doc(ument)? type|transaction type|trans type)\b/iu',
        'description' => '/\b(description|libellé|libelle|details|memo|narrative|text|label|comment|particulars)\b/iu',
        'supplier' => '/\b(supplier|vendor|fournisseur|creditor|tiers|payee|account name)\b/iu',
    ];

    public function __construct(
        private readonly FormatDetector $formats = new FormatDetector,
        private readonly AmountParser $amounts = new AmountParser,
        private readonly ReferenceNormalizer $references = new ReferenceNormalizer,
        private readonly DocumentTypeClassifier $types = new DocumentTypeClassifier,
    ) {}

    public function suggest(ImportedTable $table, Side $side, int $headerIndex): ColumnMapping
    {
        $sample = array_slice($table->rows, 0, self::SAMPLE_ROWS);
        $stats = [];

        foreach ($table->headers as $index => $header) {
            $values = array_map(fn (array $row): string => $row['cells'][$index] ?? '', $sample);
            $stats[$index] = new ColumnProfile(
                header: $header,
                values: $values,
                dateRatio: $this->formats->dateRatio($values),
                amountRatio: $this->formats->amountRatio($values),
                filledRatio: $this->filledRatio($values),
            );
        }

        $taken = [];
        $columns = [];

        $pick = function (string $field, callable $score) use (&$taken, &$columns, $stats): void {
            $best = null;
            $bestScore = 0.0;

            foreach ($stats as $index => $stat) {
                if (isset($taken[$index])) {
                    continue;
                }

                $value = $score($stat);

                if ($value > $bestScore) {
                    $best = $index;
                    $bestScore = $value;
                }
            }

            if ($best !== null) {
                $columns[$field] = $best;
                $taken[$best] = true;
            }
        };

        $matches = fn (string $kind, string $header): bool => preg_match(self::HEADERS[$kind], $header) === 1;

        $pick(Field::Date->value, fn (ColumnProfile $s): float => match (true) {
            $s->dateRatio >= 0.6 && $matches('date', $s->header) => 2 + $s->dateRatio,
            $s->dateRatio >= 0.9 && $s->amountRatio < 0.5 => $s->dateRatio,
            default => 0.0,
        });

        $pick(Field::Debit->value, fn (ColumnProfile $s): float => $matches('debit', $s->header) && $s->amountRatio >= 0.8 ? 1.0 : 0.0);
        $pick(Field::Credit->value, fn (ColumnProfile $s): float => $matches('credit', $s->header) && $s->amountRatio >= 0.8 ? 1.0 : 0.0);

        $pick(Field::Amount->value, fn (ColumnProfile $s): float => match (true) {
            $matches('balance', $s->header) => 0.0,
            $s->amountRatio >= 0.8 && $matches('amount', $s->header) => 2 + $s->amountRatio,
            $s->amountRatio >= 0.95 && $s->dateRatio < 0.5 && ! isset($columns[Field::Debit->value]) => $s->amountRatio,
            default => 0.0,
        });

        $pick(Field::Type->value, fn (ColumnProfile $s): float => $matches('type', $s->header) && ! $matches('date', $s->header) ? 1.0 : 0.0);

        $pick(Field::Reference->value, fn (ColumnProfile $s): float => $this->referenceScore($s, $matches('reference', $s->header), $matches('date', $s->header)));

        $pick(Field::Description->value, fn (ColumnProfile $s): float => $matches('description', $s->header) ? 1.0 : 0.0);

        if ($side === Side::Ledger) {
            $pick(Field::Supplier->value, fn (ColumnProfile $s): float => $matches('supplier', $s->header) ? 1.0 : 0.0);
        }

        $amountMode = isset($columns[Field::Amount->value]) || ! (isset($columns[Field::Debit->value]) || isset($columns[Field::Credit->value]))
            ? AmountMode::Signed
            : AmountMode::DebitCredit;

        if ($amountMode === AmountMode::Signed) {
            unset($columns[Field::Debit->value], $columns[Field::Credit->value]);
        }

        $amountValues = [];

        foreach ([Field::Amount, Field::Debit, Field::Credit] as $field) {
            if (isset($columns[$field->value])) {
                $amountValues = [...$amountValues, ...$table->column($columns[$field->value])];
            }
        }

        $separator = $this->formats->decimalSeparator($amountValues)['separator'];
        $dateOrder = isset($columns[Field::Date->value])
            ? $this->formats->dateOrder($table->column($columns[Field::Date->value]))['order']
            : $this->formats->dateOrder([])['order'];

        $mapping = new ColumnMapping(
            headerIndex: $headerIndex,
            columns: $columns,
            amountMode: $amountMode,
            dateOrder: $dateOrder,
            decimalSeparator: $separator,
        );

        return new ColumnMapping(
            headerIndex: $headerIndex,
            columns: $columns,
            amountMode: $amountMode,
            invoiceSign: $amountMode === AmountMode::Signed ? $this->suggestInvoiceSign($table, $mapping) : ColumnMapping::INVOICES_POSITIVE,
            invoiceColumn: $side === Side::Ledger ? ColumnMapping::INVOICES_IN_CREDIT : ColumnMapping::INVOICES_IN_DEBIT,
            dateOrder: $dateOrder,
            decimalSeparator: $separator,
        );
    }

    private function referenceScore(ColumnProfile $s, bool $headerMatches, bool $isDateHeader): float
    {
        if ($isDateHeader || $s->dateRatio >= 0.8 || $s->filledRatio < 0.3) {
            return 0.0;
        }

        $values = array_values(array_filter($s->values, fn (string $v): bool => trim($v) !== ''));
        $identifying = count(array_filter($values, fn (string $v): bool => $this->references->normalize($v)?->identifying === true));
        $identifyingRatio = $values === [] ? 0 : $identifying / count($values);
        $uniqueness = $values === [] ? 0 : count(array_unique($values)) / count($values);

        // Plain amounts are not references.
        if ($s->amountRatio >= 0.9 && ! $headerMatches) {
            return 0.0;
        }

        return ($headerMatches ? 2.0 : 0.0) + $identifyingRatio + $uniqueness * 0.5;
    }

    /**
     * Invoices are usually the majority of lines: their usual sign tells the
     * file convention. Rows whose type or reference identifies an invoice
     * weigh more.
     */
    private function suggestInvoiceSign(ImportedTable $table, ColumnMapping $mapping): string
    {
        $amountColumn = $mapping->column(Field::Amount);

        if ($amountColumn === null) {
            return ColumnMapping::INVOICES_POSITIVE;
        }

        $positive = 0;
        $negative = 0;

        foreach (array_slice($table->rows, 0, self::SAMPLE_ROWS) as $row) {
            $amount = $this->amounts->parse($row['cells'][$amountColumn] ?? '', $mapping->decimalSeparator)->amount;

            if ($amount === null || $amount->isZero()) {
                continue;
            }

            $typeColumn = $mapping->column(Field::Type);
            $referenceColumn = $mapping->column(Field::Reference);
            $type = $this->types->fromTypeText($typeColumn === null ? null : $row['cells'][$typeColumn] ?? null)
                ?? $this->types->classify(null, $this->references->normalize($referenceColumn === null ? null : $row['cells'][$referenceColumn] ?? null), null);

            $weight = $type === DocumentType::Invoice ? 3 : ($type->reducesBalance() ? 0 : 1);

            if ($amount->isNegative()) {
                $negative += $weight;
            } else {
                $positive += $weight;
            }
        }

        return $negative > $positive ? ColumnMapping::INVOICES_NEGATIVE : ColumnMapping::INVOICES_POSITIVE;
    }

    /**
     * @param  list<string>  $values
     */
    private function filledRatio(array $values): float
    {
        return $values === [] ? 0.0 : count(array_filter($values, fn (string $v): bool => trim($v) !== '')) / count($values);
    }
}

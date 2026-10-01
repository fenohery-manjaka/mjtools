<?php

namespace App\Tools\SupplierReconciliation\Http\Presenters;

use App\Tools\SupplierReconciliation\Domain\Side;
use App\Tools\SupplierReconciliation\Domain\Transaction;
use App\Tools\SupplierReconciliation\Import\ImportedTable;
use App\Tools\SupplierReconciliation\Mapping\AmountMode;
use App\Tools\SupplierReconciliation\Mapping\ColumnMapping;
use App\Tools\SupplierReconciliation\Mapping\CurrencyCheck;
use App\Tools\SupplierReconciliation\Mapping\Field;
use App\Tools\SupplierReconciliation\Normalization\CurrencyDetector;
use App\Tools\SupplierReconciliation\Normalization\DateOrder;
use App\Tools\SupplierReconciliation\Normalization\DecimalSeparator;
use App\Tools\SupplierReconciliation\Result\Candidate;
use App\Tools\SupplierReconciliation\Result\FieldComparison;
use App\Tools\SupplierReconciliation\Result\Reason;
use App\Tools\SupplierReconciliation\Review\ReviewedItem;
use App\Tools\SupplierReconciliation\Review\ReviewedResult;
use App\Tools\SupplierReconciliation\Runs\ReconciliationRun;

/**
 * Shapes domain objects into page props. Presentation only: every status,
 * explanation and allowed action comes from the domain.
 */
final class RunPresenter
{
    private const PREVIEW_ROWS = 5;

    private const HEADER_CHOICES = 15;

    private const CURRENCY_NAMES = [
        'EUR' => 'Euro', 'USD' => 'US dollar', 'GBP' => 'Pound sterling', 'CHF' => 'Swiss franc',
        'CAD' => 'Canadian dollar', 'AUD' => 'Australian dollar', 'NZD' => 'New Zealand dollar',
        'JPY' => 'Japanese yen', 'CNY' => 'Chinese yuan', 'HKD' => 'Hong Kong dollar', 'SGD' => 'Singapore dollar',
        'INR' => 'Indian rupee', 'ZAR' => 'South African rand', 'SEK' => 'Swedish krona', 'NOK' => 'Norwegian krone',
        'DKK' => 'Danish krone', 'PLN' => 'Polish złoty', 'CZK' => 'Czech koruna', 'HUF' => 'Hungarian forint',
        'RON' => 'Romanian leu', 'BRL' => 'Brazilian real', 'MGA' => 'Malagasy ariary', 'MUR' => 'Mauritian rupee',
        'XOF' => 'West African CFA franc', 'XAF' => 'Central African CFA franc', 'MAD' => 'Moroccan dirham',
        'TND' => 'Tunisian dinar', 'AED' => 'UAE dirham',
    ];

    /**
     * @return array<string, mixed>
     */
    public function run(ReconciliationRun $run): array
    {
        return [
            'id' => $run->id,
            'expires_at' => $run->expires_at->toIso8601String(),
            'has_statement' => $run->hasFile(Side::Statement),
            'has_ledger' => $run->hasFile(Side::Ledger),
            'reconciled' => $run->isReconciled(),
            'statement_name' => $run->statement_file['name'] ?? null,
            'ledger_name' => $run->ledger_file['name'] ?? null,
            'currency' => $run->currency,
        ];
    }

    /**
     * The reconciliation currency: confirmed value, or the proposal drawn from the files.
     *
     * @return array<string, mixed>
     */
    public function currency(ReconciliationRun $run): array
    {
        $statement = $run->prepared(Side::Statement);
        $ledger = $run->prepared(Side::Ledger);
        $proposal = $statement === null || $ledger === null
            ? ['code' => null, 'message' => '']
            : (new CurrencyCheck)->propose($statement, $ledger);

        return [
            'value' => $run->currency ?? $proposal['code'],
            'confirmed' => $run->currency !== null,
            'proposed' => $proposal['code'],
            'message' => $proposal['message'],
            'options' => array_map(
                fn (string $code): array => ['value' => $code, 'label' => $code.' — '.(self::CURRENCY_NAMES[$code] ?? $code)],
                CurrencyDetector::codes(),
            ),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function file(ReconciliationRun $run, Side $side): ?array
    {
        $info = $run->fileInfo($side);
        $table = $run->importedTable($side);

        if ($info === null || $table === null) {
            return null;
        }

        return [
            ...$info,
            'rows' => count($table->rows),
            'columns' => count($table->headers),
            'header_row' => $table->headerRowNumber,
            'preview' => [
                'headers' => $table->headers,
                'rows' => array_map(fn (array $row): array => $row['cells'], array_slice($table->rows, 0, self::PREVIEW_ROWS)),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function mapping(ReconciliationRun $run, Side $side): array
    {
        $raw = $run->rawTable($side);
        $mapping = $run->mapping($side);
        $table = $run->importedTable($side);

        if ($raw === null || $mapping === null || $table === null) {
            return [];
        }

        return [
            'side' => $side->value,
            'label' => $side->label(),
            'file_name' => $run->fileInfo($side)['name'] ?? '',
            'header_index' => $mapping->headerIndex,
            'header_choices' => array_map(
                fn (array $row, int $index): array => ['index' => $index, 'row_number' => $index + 1, 'text' => mb_strimwidth(implode(' · ', array_filter($row, fn (string $c): bool => trim($c) !== '')), 0, 90, '…')],
                array_slice($raw->rows, 0, self::HEADER_CHOICES),
                array_keys(array_slice($raw->rows, 0, self::HEADER_CHOICES)),
            ),
            'columns' => array_map(
                fn (string $header, int $index): array => [
                    'index' => $index,
                    'name' => $header,
                    'letter' => ImportedTable::columnLetter($index),
                    'samples' => $table->samples($index),
                ],
                $table->headers,
                array_keys($table->headers),
            ),
            'fields' => array_map(fn (Field $field): array => [
                'key' => $field->value,
                'label' => $field->label(),
                'help' => $field->help(),
            ], Field::cases()),
            'mapping' => $mapping->toArray(),
            'supplier_values' => $this->supplierValues($table, $mapping),
            'options' => [
                'amount_modes' => [
                    ['value' => AmountMode::Signed->value, 'label' => 'One amount column (signed)'],
                    ['value' => AmountMode::DebitCredit->value, 'label' => 'Separate Debit and Credit columns'],
                ],
                'invoice_signs' => [
                    ['value' => ColumnMapping::INVOICES_POSITIVE, 'label' => 'Positive amounts'],
                    ['value' => ColumnMapping::INVOICES_NEGATIVE, 'label' => 'Negative amounts'],
                ],
                'invoice_columns' => [
                    ['value' => ColumnMapping::INVOICES_IN_DEBIT, 'label' => 'Debit column'],
                    ['value' => ColumnMapping::INVOICES_IN_CREDIT, 'label' => 'Credit column'],
                ],
                'date_orders' => array_map(fn (DateOrder $o): array => ['value' => $o->value, 'label' => $o->label()], DateOrder::cases()),
                'decimal_separators' => array_map(fn (DecimalSeparator $s): array => ['value' => $s->value, 'label' => $s->label()], DecimalSeparator::cases()),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function transaction(Transaction $t): array
    {
        return [
            'id' => $t->id,
            'side' => $t->side->value,
            'row' => $t->rowNumber,
            'reference' => $t->original('reference'),
            'reference_normalized' => $t->reference?->typographicKey,
            'date' => $t->original('date'),
            'date_normalized' => $t->date?->format(),
            'amount' => $t->original('amount'),
            'debit' => $t->original('debit'),
            'credit' => $t->original('credit'),
            'amount_normalized' => $t->amount?->format(),
            'type' => $t->documentType->label(),
            'type_original' => $t->original('type'),
            'description' => $t->original('description'),
            'issues' => $t->issues,
            'amount_notes' => $t->amountNotes,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function item(ReviewedItem $item, ReviewedResult $reviewed): array
    {
        $engineItem = $item->engineItemId === null ? null : $reviewed->engine->item($item->engineItemId);

        return [
            'id' => $item->id,
            'status' => $item->status->value,
            'status_label' => $item->status->label(),
            'engine_status' => $item->engineStatus->value,
            'engine_status_label' => $item->engineStatus->label(),
            'kind' => $item->kind?->value,
            'kind_label' => $item->kind?->label(),
            'confidence' => $engineItem?->confidence()?->label(),
            'resolution' => $item->resolution->value,
            'resolution_label' => $item->resolution->label(),
            'needs_attention' => $item->needsAttention(),
            'headline' => $item->headline,
            'statement' => array_map(fn (string $id): array => $this->transaction($reviewed->engine->transaction($id)), $item->statementIds),
            'ledger' => array_map(fn (string $id): array => $this->transaction($reviewed->engine->transaction($id)), $item->ledgerIds),
            'reasons' => array_map(fn (Reason $r): array => $r->toArray(), $item->reasons),
            'candidates' => array_map(fn (Candidate $c): array => [
                'statement_ids' => $c->statementIds,
                'ledger_ids' => $c->ledgerIds,
                'comparisons' => array_map(fn (FieldComparison $f): array => $f->toArray(), $c->comparisons),
                'reasons' => array_map(fn (Reason $r): array => $r->toArray(), $c->reasons),
            ], $item->candidates),
            'difference' => $item->difference?->format(),
            'flags' => $item->flags,
            'actions' => $item->actions,
            'decision' => $item->decision === null ? null : [
                'id' => $item->decision->id,
                'action' => $item->decision->action->value,
                'label' => $item->decision->action->label(),
                'decided_at' => $item->decision->decidedAt,
            ],
        ];
    }

    /**
     * @return list<string>
     */
    private function supplierValues(ImportedTable $table, ColumnMapping $mapping): array
    {
        $column = $mapping->column(Field::Supplier);

        if ($column === null) {
            return [];
        }

        $values = array_values(array_unique(array_filter(
            array_map('trim', $table->column($column)),
            fn (string $v): bool => $v !== '',
        )));
        sort($values);

        return array_slice($values, 0, 100);
    }
}

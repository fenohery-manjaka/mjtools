<?php

namespace App\Tools\SupplierReconciliation\Mapping;

use App\Tools\SupplierReconciliation\Normalization\DateOrder;
use App\Tools\SupplierReconciliation\Normalization\DecimalSeparator;

/**
 * How one file's columns map to business fields, and the conventions used to
 * read its values. Conventions are explicit and file-wide: they are never
 * adjusted row by row to obtain a match.
 */
final readonly class ColumnMapping
{
    public const INVOICES_POSITIVE = 'positive';

    public const INVOICES_NEGATIVE = 'negative';

    public const INVOICES_IN_DEBIT = 'debit';

    public const INVOICES_IN_CREDIT = 'credit';

    /**
     * @param  array<string, int>  $columns  Field value => column index.
     */
    public function __construct(
        public int $headerIndex,
        public array $columns,
        public AmountMode $amountMode = AmountMode::Signed,
        /** Signed mode: invoices appear as positive or negative amounts. */
        public string $invoiceSign = self::INVOICES_POSITIVE,
        /** Debit/credit mode: invoices appear in the debit or credit column. */
        public string $invoiceColumn = self::INVOICES_IN_DEBIT,
        public DateOrder $dateOrder = DateOrder::DayFirst,
        public DecimalSeparator $decimalSeparator = DecimalSeparator::Dot,
        /** Credits/payments listed as positive: take the sign from the Type column. */
        public bool $signFromType = false,
        public ?string $supplierFilter = null,
    ) {}

    public function column(Field $field): ?int
    {
        return $this->columns[$field->value] ?? null;
    }

    public function has(Field $field): bool
    {
        return $this->column($field) !== null;
    }

    public function hasAmount(): bool
    {
        return $this->amountMode === AmountMode::Signed
            ? $this->has(Field::Amount)
            : $this->has(Field::Debit) || $this->has(Field::Credit);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'header_index' => $this->headerIndex,
            'columns' => $this->columns,
            'amount_mode' => $this->amountMode->value,
            'invoice_sign' => $this->invoiceSign,
            'invoice_column' => $this->invoiceColumn,
            'date_order' => $this->dateOrder->value,
            'decimal_separator' => $this->decimalSeparator->value,
            'sign_from_type' => $this->signFromType,
            'supplier_filter' => $this->supplierFilter,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $columns = [];

        foreach ((array) ($data['columns'] ?? []) as $field => $column) {
            if (Field::tryFrom((string) $field) !== null && is_numeric($column) && (int) $column >= 0) {
                $columns[(string) $field] = (int) $column;
            }
        }

        $supplier = isset($data['supplier_filter']) ? trim((string) $data['supplier_filter']) : '';

        return new self(
            headerIndex: max(0, (int) ($data['header_index'] ?? 0)),
            columns: $columns,
            amountMode: AmountMode::tryFrom((string) ($data['amount_mode'] ?? '')) ?? AmountMode::Signed,
            invoiceSign: ($data['invoice_sign'] ?? null) === self::INVOICES_NEGATIVE ? self::INVOICES_NEGATIVE : self::INVOICES_POSITIVE,
            invoiceColumn: ($data['invoice_column'] ?? null) === self::INVOICES_IN_CREDIT ? self::INVOICES_IN_CREDIT : self::INVOICES_IN_DEBIT,
            dateOrder: DateOrder::tryFrom((string) ($data['date_order'] ?? '')) ?? DateOrder::DayFirst,
            decimalSeparator: DecimalSeparator::tryFrom((string) ($data['decimal_separator'] ?? '')) ?? DecimalSeparator::Dot,
            signFromType: (bool) ($data['sign_from_type'] ?? false),
            supplierFilter: $supplier === '' ? null : $supplier,
        );
    }
}

<?php

namespace App\Tools\SupplierReconciliation\Domain;

/**
 * Canonical transaction the matching engine works on, independent of the
 * source format.
 *
 * Original values are kept exactly as imported. Normalized values are only
 * used for comparison. Amounts are expressed from the supplier statement's
 * point of view: invoices are positive, credits and payments negative.
 */
final readonly class Transaction
{
    /**
     * @param  array<string, string>  $original  Imported values keyed by field (reference, date, amount, debit, credit, type, description, supplier).
     * @param  list<string>  $issues  Problems found while reading this row.
     * @param  list<string>  $amountNotes  Conventions applied to obtain the normalized amount.
     */
    public function __construct(
        public string $id,
        public Side $side,
        public int $rowNumber,
        public array $original,
        public ?NormalizedReference $reference,
        public ?CalendarDate $date,
        public ?Amount $amount,
        public DocumentType $documentType,
        public array $issues = [],
        public array $amountNotes = [],
        public bool $isBalanceLine = false,
    ) {}

    public function original(string $field): ?string
    {
        $value = $this->original[$field] ?? null;

        return $value === null || trim($value) === '' ? null : $value;
    }

    public function hasIdentifyingReference(): bool
    {
        return $this->reference !== null && $this->reference->identifying;
    }

    /**
     * "statement row 12"
     */
    public function rowLabel(): string
    {
        return ($this->side === Side::Statement ? 'statement' : 'ledger')." row {$this->rowNumber}";
    }

    /**
     * Human label used in explanations, e.g. "statement row 12 (INV-004583)".
     */
    public function label(): string
    {
        $side = $this->side === Side::Statement ? 'statement' : 'ledger';
        $reference = $this->reference?->original;

        return trim("{$side} row {$this->rowNumber}".($reference !== null ? ' ('.trim($reference).')' : ''));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'side' => $this->side->value,
            'row_number' => $this->rowNumber,
            'original' => $this->original,
            'reference' => $this->reference?->toArray(),
            'date' => $this->date?->toIso(),
            'amount' => $this->amount?->units,
            'document_type' => $this->documentType->value,
            'issues' => $this->issues,
            'amount_notes' => $this->amountNotes,
            'is_balance_line' => $this->isBalanceLine,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        /** @var array<string, mixed>|null $reference */
        $reference = $data['reference'] ?? null;

        return new self(
            id: (string) $data['id'],
            side: Side::from((string) $data['side']),
            rowNumber: (int) $data['row_number'],
            original: array_map('strval', (array) $data['original']),
            reference: $reference === null ? null : NormalizedReference::fromArray($reference),
            date: isset($data['date']) ? CalendarDate::fromIso((string) $data['date']) : null,
            amount: isset($data['amount']) ? Amount::fromUnits((int) $data['amount']) : null,
            documentType: DocumentType::from((string) $data['document_type']),
            issues: array_values(array_map('strval', (array) ($data['issues'] ?? []))),
            amountNotes: array_values(array_map('strval', (array) ($data['amount_notes'] ?? []))),
            isBalanceLine: (bool) ($data['is_balance_line'] ?? false),
        );
    }
}

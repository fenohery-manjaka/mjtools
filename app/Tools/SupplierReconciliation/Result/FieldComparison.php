<?php

namespace App\Tools\SupplierReconciliation\Result;

/**
 * Side-by-side comparison of one field: original values, the normalized
 * values actually compared, and the relation found.
 */
final readonly class FieldComparison
{
    public function __construct(
        public string $field,
        public ?string $statementOriginal,
        public ?string $statementNormalized,
        public ?string $ledgerOriginal,
        public ?string $ledgerNormalized,
        public string $relation,
        public Polarity $polarity,
    ) {}

    /**
     * @return array<string, string|null>
     */
    public function toArray(): array
    {
        return [
            'field' => $this->field,
            'statement_original' => $this->statementOriginal,
            'statement_normalized' => $this->statementNormalized,
            'ledger_original' => $this->ledgerOriginal,
            'ledger_normalized' => $this->ledgerNormalized,
            'relation' => $this->relation,
            'polarity' => $this->polarity->value,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $string = fn (string $key): ?string => isset($data[$key]) ? (string) $data[$key] : null;

        return new self(
            field: (string) $data['field'],
            statementOriginal: $string('statement_original'),
            statementNormalized: $string('statement_normalized'),
            ledgerOriginal: $string('ledger_original'),
            ledgerNormalized: $string('ledger_normalized'),
            relation: (string) $data['relation'],
            polarity: Polarity::from((string) $data['polarity']),
        );
    }
}

<?php

namespace App\Tools\SupplierReconciliation\Result;

/**
 * A link between statement and ledger transactions considered by the engine,
 * with the evidence behind it.
 */
final readonly class Candidate
{
    /**
     * @param  list<string>  $statementIds
     * @param  list<string>  $ledgerIds
     * @param  list<FieldComparison>  $comparisons
     * @param  list<Reason>  $reasons
     */
    public function __construct(
        public array $statementIds,
        public array $ledgerIds,
        public array $comparisons,
        public array $reasons,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'statement_ids' => $this->statementIds,
            'ledger_ids' => $this->ledgerIds,
            'comparisons' => array_map(fn (FieldComparison $c): array => $c->toArray(), $this->comparisons),
            'reasons' => array_map(fn (Reason $r): array => $r->toArray(), $this->reasons),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            statementIds: array_values(array_map('strval', (array) $data['statement_ids'])),
            ledgerIds: array_values(array_map('strval', (array) $data['ledger_ids'])),
            comparisons: array_values(array_map(fn (array $c): FieldComparison => FieldComparison::fromArray($c), (array) $data['comparisons'])),
            reasons: array_values(array_map(fn (array $r): Reason => Reason::fromArray($r), (array) $data['reasons'])),
        );
    }
}

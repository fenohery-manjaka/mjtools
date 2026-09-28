<?php

namespace App\Tools\SupplierReconciliation\Result;

use App\Tools\SupplierReconciliation\Domain\Amount;

/**
 * One outcome of the reconciliation: a match, a proposal, or an exception.
 * Every transaction belongs to exactly one item.
 */
final readonly class ResultItem
{
    public const FLAG_CREDIT = 'credit';

    public const FLAG_PAYMENT = 'payment';

    public const FLAG_TIMING = 'possible_timing_difference';

    public const FLAG_ONE_TO_MANY = 'one_to_many';

    public const FLAG_MANY_TO_ONE = 'many_to_one';

    /**
     * @param  list<string>  $statementIds
     * @param  list<string>  $ledgerIds
     * @param  list<Reason>  $reasons
     * @param  list<Candidate>  $candidates
     * @param  list<string>  $flags
     */
    public function __construct(
        public string $id,
        public ItemStatus $status,
        public ?MatchKind $kind,
        public string $headline,
        public array $statementIds,
        public array $ledgerIds,
        public array $reasons,
        public array $candidates = [],
        public ?Amount $difference = null,
        public array $flags = [],
    ) {}

    public function confidence(): ?Confidence
    {
        return Confidence::forStatus($this->status);
    }

    /**
     * @return list<string>
     */
    public function transactionIds(): array
    {
        return [...$this->statementIds, ...$this->ledgerIds];
    }

    public function hasFlag(string $flag): bool
    {
        return in_array($flag, $this->flags, true);
    }

    public function withId(string $id): self
    {
        return new self($id, $this->status, $this->kind, $this->headline, $this->statementIds, $this->ledgerIds, $this->reasons, $this->candidates, $this->difference, $this->flags);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'kind' => $this->kind?->value,
            'headline' => $this->headline,
            'statement_ids' => $this->statementIds,
            'ledger_ids' => $this->ledgerIds,
            'reasons' => array_map(fn (Reason $r): array => $r->toArray(), $this->reasons),
            'candidates' => array_map(fn (Candidate $c): array => $c->toArray(), $this->candidates),
            'difference' => $this->difference?->units,
            'flags' => $this->flags,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) $data['id'],
            status: ItemStatus::from((string) $data['status']),
            kind: isset($data['kind']) ? MatchKind::from((string) $data['kind']) : null,
            headline: (string) $data['headline'],
            statementIds: array_values(array_map('strval', (array) $data['statement_ids'])),
            ledgerIds: array_values(array_map('strval', (array) $data['ledger_ids'])),
            reasons: array_values(array_map(fn (array $r): Reason => Reason::fromArray($r), (array) $data['reasons'])),
            candidates: array_values(array_map(fn (array $c): Candidate => Candidate::fromArray($c), (array) $data['candidates'])),
            difference: isset($data['difference']) ? Amount::fromUnits((int) $data['difference']) : null,
            flags: array_values(array_map('strval', (array) $data['flags'])),
        );
    }
}

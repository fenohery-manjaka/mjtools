<?php

namespace App\Tools\SupplierReconciliation\Review;

/**
 * A human decision, recorded separately from the engine result (spec §35).
 */
final readonly class Decision
{
    /**
     * @param  list<string>  $statementIds
     * @param  list<string>  $ledgerIds
     */
    public function __construct(
        public string $id,
        public DecisionAction $action,
        public ?string $itemId,
        public array $statementIds = [],
        public array $ledgerIds = [],
        public string $decidedAt = '',
    ) {}

    /**
     * @return list<string>
     */
    public function transactionIds(): array
    {
        return [...$this->statementIds, ...$this->ledgerIds];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'action' => $this->action->value,
            'item_id' => $this->itemId,
            'statement_ids' => $this->statementIds,
            'ledger_ids' => $this->ledgerIds,
            'decided_at' => $this->decidedAt,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) $data['id'],
            action: DecisionAction::from((string) $data['action']),
            itemId: isset($data['item_id']) ? (string) $data['item_id'] : null,
            statementIds: array_values(array_map('strval', (array) ($data['statement_ids'] ?? []))),
            ledgerIds: array_values(array_map('strval', (array) ($data['ledger_ids'] ?? []))),
            decidedAt: (string) ($data['decided_at'] ?? ''),
        );
    }
}

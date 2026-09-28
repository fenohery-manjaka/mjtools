<?php

namespace App\Tools\SupplierReconciliation\Review;

use App\Tools\SupplierReconciliation\Domain\Amount;
use App\Tools\SupplierReconciliation\Result\Candidate;
use App\Tools\SupplierReconciliation\Result\ItemStatus;
use App\Tools\SupplierReconciliation\Result\MatchKind;
use App\Tools\SupplierReconciliation\Result\Reason;
use App\Tools\SupplierReconciliation\Result\ResultItem;

/**
 * Mutable item used while decisions are being applied. Internal to Review.
 */
final class WorkingItem
{
    /**
     * @param  list<string>  $statementIds
     * @param  list<string>  $ledgerIds
     * @param  list<Reason>  $reasons
     * @param  list<Candidate>  $candidates
     * @param  list<string>  $flags
     */
    public function __construct(
        public string $id,
        public ?string $engineItemId,
        public ItemStatus $engineStatus,
        public ItemStatus $status,
        public ?MatchKind $kind,
        public Resolution $resolution,
        public string $headline,
        public array $statementIds,
        public array $ledgerIds,
        public array $reasons,
        public array $candidates = [],
        public ?Amount $difference = null,
        public array $flags = [],
        public ?Decision $decision = null,
    ) {}

    public static function fromEngine(ResultItem $item): self
    {
        return new self(
            id: $item->id,
            engineItemId: $item->id,
            engineStatus: $item->status,
            status: $item->status,
            kind: $item->kind,
            resolution: match ($item->status) {
                ItemStatus::Matched => Resolution::Automatic,
                ItemStatus::Excluded => Resolution::Excluded,
                default => Resolution::Open,
            },
            headline: $item->headline,
            statementIds: $item->statementIds,
            ledgerIds: $item->ledgerIds,
            reasons: $item->reasons,
            candidates: $item->candidates,
            difference: $item->difference,
            flags: $item->flags,
        );
    }

    /**
     * @return list<string>
     */
    public function transactionIds(): array
    {
        return [...$this->statementIds, ...$this->ledgerIds];
    }

    public function isOpen(): bool
    {
        return $this->resolution === Resolution::Open || $this->resolution === Resolution::Deferred;
    }

    /**
     * @param  list<string>  $actions
     */
    public function toReviewed(array $actions): ReviewedItem
    {
        return new ReviewedItem(
            id: $this->id,
            engineItemId: $this->engineItemId,
            engineStatus: $this->engineStatus,
            status: $this->status,
            kind: $this->kind,
            resolution: $this->resolution,
            headline: $this->headline,
            statementIds: $this->statementIds,
            ledgerIds: $this->ledgerIds,
            reasons: $this->reasons,
            candidates: $this->candidates,
            difference: $this->difference,
            flags: $this->flags,
            decision: $this->decision,
            actions: $actions,
        );
    }
}

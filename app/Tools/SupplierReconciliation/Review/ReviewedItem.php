<?php

namespace App\Tools\SupplierReconciliation\Review;

use App\Tools\SupplierReconciliation\Domain\Amount;
use App\Tools\SupplierReconciliation\Result\Candidate;
use App\Tools\SupplierReconciliation\Result\ItemStatus;
use App\Tools\SupplierReconciliation\Result\MatchKind;
use App\Tools\SupplierReconciliation\Result\Reason;

/**
 * An item as the user currently sees it: the engine's outcome plus any
 * human decision applied on top.
 */
final readonly class ReviewedItem
{
    public const ACTION_CONFIRM = 'confirm';

    public const ACTION_REJECT = 'reject';

    public const ACTION_CHOOSE = 'choose';

    public const ACTION_MANUAL_MATCH = 'manual_match';

    public const ACTION_DEFER = 'defer';

    public const ACTION_UNDO = 'undo';

    /**
     * @param  list<string>  $statementIds
     * @param  list<string>  $ledgerIds
     * @param  list<Reason>  $reasons
     * @param  list<Candidate>  $candidates
     * @param  list<string>  $flags
     * @param  list<string>  $actions
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
        public array $candidates,
        public ?Amount $difference,
        public array $flags,
        public ?Decision $decision,
        public array $actions,
    ) {}

    public function needsAttention(): bool
    {
        return ! $this->resolution->isSettled();
    }

    /**
     * @return list<string>
     */
    public function transactionIds(): array
    {
        return [...$this->statementIds, ...$this->ledgerIds];
    }
}

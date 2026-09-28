<?php

namespace App\Tools\SupplierReconciliation\Review;

use App\Tools\SupplierReconciliation\Domain\Amount;
use App\Tools\SupplierReconciliation\Domain\Side;
use App\Tools\SupplierReconciliation\Domain\Transaction;
use App\Tools\SupplierReconciliation\Matching\PairEvidence;
use App\Tools\SupplierReconciliation\Matching\ReferenceComparator;
use App\Tools\SupplierReconciliation\Result\ItemStatus;
use App\Tools\SupplierReconciliation\Result\MatchKind;
use App\Tools\SupplierReconciliation\Result\Reason;
use App\Tools\SupplierReconciliation\Result\ReconciliationResult;

/**
 * Applies human decisions, in order, on top of the immutable engine result.
 * Decisions that no longer apply (e.g. after an undo) are skipped.
 */
final class ReviewApplier
{
    /** @var array<string, WorkingItem> */
    private array $items = [];

    /** @var array<string, string> transaction id => working item id */
    private array $owner = [];

    private ReconciliationResult $result;

    /**
     * @param  list<Decision>  $decisions
     */
    public function apply(ReconciliationResult $result, array $decisions): ReviewedResult
    {
        $this->result = $result;
        $this->items = [];
        $this->owner = [];

        foreach ($result->items as $item) {
            $this->put(WorkingItem::fromEngine($item));
        }

        $applied = [];

        foreach ($decisions as $decision) {
            if ($this->validate($decision) === null) {
                $this->execute($decision);
                $applied[] = $decision;
            }
        }

        return new ReviewedResult($result, $this->finalItems(), $applied);
    }

    /**
     * Why a new decision cannot be applied after the existing ones, or null.
     *
     * @param  list<Decision>  $decisions
     */
    public function rejectionReason(ReconciliationResult $result, array $decisions, Decision $candidate): ?string
    {
        $this->apply($result, $decisions);

        return $this->validate($candidate);
    }

    private function validate(Decision $decision): ?string
    {
        $item = $decision->itemId === null ? null : ($this->items[$decision->itemId] ?? null);

        return match ($decision->action) {
            DecisionAction::Confirm => $item !== null && $item->isOpen() && $item->status === ItemStatus::PossibleMatch
                ? null
                : 'Only an open possible match can be confirmed.',
            DecisionAction::Reject => $item !== null && (
                ($item->isOpen() && in_array($item->status, [ItemStatus::PossibleMatch, ItemStatus::AmountMismatch], true))
                || $item->resolution === Resolution::Automatic
            ) ? null : 'Only a proposed or automatic match can be rejected.',
            DecisionAction::Defer => $item !== null && $item->resolution === Resolution::Open
                ? null
                : 'Only an open item can be left for review.',
            DecisionAction::Match => $this->validateMatch($decision),
        };
    }

    private function validateMatch(Decision $decision): ?string
    {
        if ($decision->statementIds === [] || $decision->ledgerIds === []) {
            return 'Select at least one statement line and one ledger line.';
        }

        $ids = $decision->transactionIds();

        if (count(array_unique($ids)) !== count($ids)) {
            return 'A line was selected twice.';
        }

        foreach ([Side::Statement->value => $decision->statementIds, Side::Ledger->value => $decision->ledgerIds] as $side => $sideIds) {
            foreach ($sideIds as $id) {
                if (($this->result->transactions[$id] ?? null)?->side->value !== $side) {
                    return "Unknown {$side} line.";
                }
            }
        }

        foreach ($ids as $id) {
            $owner = $this->items[$this->owner[$id]];

            if (! $owner->isOpen() && $owner->resolution !== Resolution::Rejected) {
                return 'A selected line is already matched or excluded.';
            }
        }

        return null;
    }

    private function execute(Decision $decision): void
    {
        match ($decision->action) {
            DecisionAction::Confirm => $this->confirm($decision),
            DecisionAction::Reject => $this->reject($decision),
            DecisionAction::Defer => $this->defer($decision),
            DecisionAction::Match => $this->match($decision),
        };
    }

    private function confirm(Decision $decision): void
    {
        $item = $this->items[(string) $decision->itemId];
        $item->status = ItemStatus::Matched;
        $item->resolution = Resolution::Confirmed;
        $item->headline = 'Matched — confirmed by you';
        $item->reasons = [Reason::agrees('decision.confirmed', 'You confirmed this match.'), ...$item->reasons];
        $item->decision = $decision;
    }

    private function defer(Decision $decision): void
    {
        $item = $this->items[(string) $decision->itemId];
        $item->resolution = Resolution::Deferred;
        $item->decision = $decision;
    }

    private function reject(Decision $decision): void
    {
        $item = $this->items[(string) $decision->itemId];
        unset($this->items[$item->id]);

        foreach ($item->transactionIds() as $id) {
            $transaction = $this->result->transaction($id);
            $others = array_map(
                fn (string $otherId): string => $this->result->transaction($otherId)->label(),
                $transaction->side === Side::Statement ? $item->ledgerIds : $item->statementIds,
            );

            $this->put($this->single(
                id: $decision->id.':'.$id,
                source: $item,
                transaction: $transaction,
                resolution: Resolution::Rejected,
                reason: Reason::differs('decision.rejected', 'You rejected the link with '.implode(', ', $others).'.'),
                decision: $decision,
            ));
        }
    }

    private function match(Decision $decision): void
    {
        $ids = $decision->transactionIds();
        $sourceIds = array_values(array_unique(array_map(fn (string $id): string => $this->owner[$id], $ids)));

        foreach ($sourceIds as $sourceId) {
            $source = $this->items[$sourceId];
            unset($this->items[$sourceId]);

            $remainingStatement = array_values(array_diff($source->statementIds, $ids));
            $remainingLedger = array_values(array_diff($source->ledgerIds, $ids));

            if ($remainingStatement === [] && $remainingLedger === []) {
                continue;
            }

            if ($source->status === ItemStatus::DuplicateSuspected) {
                $source->statementIds = $remainingStatement;
                $source->ledgerIds = $remainingLedger;
                $source->candidates = [];
                $source->reasons = [Reason::info('decision.partly_matched', 'Some lines of this group were matched by you; these remain.'), ...$source->reasons];
                $this->put($source);

                continue;
            }

            foreach ([...$remainingStatement, ...$remainingLedger] as $id) {
                $this->put($this->single(
                    id: $decision->id.':'.$id,
                    source: $source,
                    transaction: $this->result->transaction($id),
                    resolution: Resolution::Open,
                    reason: Reason::info('decision.other_lines_matched', 'The line(s) this was grouped with were matched by you.'),
                    decision: null,
                ));
            }
        }

        $this->put($this->manualItem($decision));
    }

    private function manualItem(Decision $decision): WorkingItem
    {
        $statement = array_map(fn (string $id): Transaction => $this->result->transaction($id), $decision->statementIds);
        $ledger = array_map(fn (string $id): Transaction => $this->result->transaction($id), $decision->ledgerIds);
        $reasons = [Reason::agrees('decision.manual', 'You matched these lines manually.')];
        $candidates = [];

        if (count($statement) === 1 && count($ledger) === 1) {
            $evidence = PairEvidence::between($statement[0], $ledger[0], new ReferenceComparator);
            $reasons = [...$reasons, ...$evidence->reasons()];
            $candidates[] = $evidence->toCandidate();
        }

        $difference = $this->total($statement)->minus($this->total($ledger));

        if (! $difference->isZero() && count($statement) + count($ledger) > 2) {
            $reasons[] = Reason::differs('decision.manual_difference', 'The selected lines differ by '.$difference->format().'.');
        }

        return new WorkingItem(
            id: 'M'.$decision->id,
            engineItemId: null,
            engineStatus: ItemStatus::Matched,
            status: ItemStatus::Matched,
            kind: count($statement) + count($ledger) > 2 ? MatchKind::Grouped : null,
            resolution: Resolution::ManualMatch,
            headline: 'Matched manually by you',
            statementIds: $decision->statementIds,
            ledgerIds: $decision->ledgerIds,
            reasons: $reasons,
            candidates: $candidates,
            difference: $difference->isZero() ? null : $difference,
            decision: $decision,
        );
    }

    private function single(string $id, WorkingItem $source, Transaction $transaction, Resolution $resolution, Reason $reason, ?Decision $decision): WorkingItem
    {
        $statement = $transaction->side === Side::Statement;

        return new WorkingItem(
            id: $id,
            engineItemId: $source->engineItemId,
            engineStatus: $source->engineStatus,
            status: $statement ? ItemStatus::MissingInLedger : ItemStatus::LedgerOnly,
            kind: null,
            resolution: $resolution,
            headline: $statement ? 'Missing in ledger' : 'Ledger only',
            statementIds: $statement ? [$transaction->id] : [],
            ledgerIds: $statement ? [] : [$transaction->id],
            reasons: [$reason],
            decision: $decision,
        );
    }

    private function put(WorkingItem $item): void
    {
        $this->items[$item->id] = $item;

        foreach ($item->transactionIds() as $id) {
            $this->owner[$id] = $item->id;
        }
    }

    /**
     * @param  list<Transaction>  $transactions
     */
    private function total(array $transactions): Amount
    {
        return Amount::sum(array_map(fn (Transaction $t): Amount => $t->amount ?? Amount::fromUnits(0), $transactions));
    }

    /**
     * @return list<ReviewedItem>
     */
    private function finalItems(): array
    {
        $position = array_flip(array_keys($this->result->transactions));
        $items = array_values($this->items);

        $first = fn (WorkingItem $item): int => min([PHP_INT_MAX, ...array_map(fn (string $id): int => $position[$id], $item->transactionIds())]);

        usort($items, fn (WorkingItem $a, WorkingItem $b): int => [$first($a), $a->id] <=> [$first($b), $b->id]);

        return array_map(fn (WorkingItem $item): ReviewedItem => $item->toReviewed($this->actionsFor($item)), $items);
    }

    /**
     * @return list<string>
     */
    private function actionsFor(WorkingItem $item): array
    {
        return match ($item->resolution) {
            Resolution::Automatic => [ReviewedItem::ACTION_REJECT],
            Resolution::Confirmed, Resolution::ManualMatch => [ReviewedItem::ACTION_UNDO],
            Resolution::Excluded => [],
            Resolution::Rejected => [ReviewedItem::ACTION_MANUAL_MATCH, ReviewedItem::ACTION_UNDO],
            Resolution::Open, Resolution::Deferred => [
                ...match ($item->status) {
                    ItemStatus::PossibleMatch => [ReviewedItem::ACTION_CONFIRM, ReviewedItem::ACTION_REJECT],
                    ItemStatus::Ambiguous, ItemStatus::DuplicateSuspected => [ReviewedItem::ACTION_CHOOSE],
                    ItemStatus::AmountMismatch => [ReviewedItem::ACTION_REJECT],
                    default => [ReviewedItem::ACTION_MANUAL_MATCH],
                },
                $item->resolution === Resolution::Open ? ReviewedItem::ACTION_DEFER : ReviewedItem::ACTION_UNDO,
            ],
        };
    }
}

<?php

namespace Tests\Unit\Tools\SupplierReconciliation\Review;

use App\Tools\SupplierReconciliation\Matching\ReconciliationEngine;
use App\Tools\SupplierReconciliation\Result\ItemStatus;
use App\Tools\SupplierReconciliation\Result\ReconciliationResult;
use App\Tools\SupplierReconciliation\Review\Decision;
use App\Tools\SupplierReconciliation\Review\DecisionAction;
use App\Tools\SupplierReconciliation\Review\Resolution;
use App\Tools\SupplierReconciliation\Review\ReviewApplier;
use App\Tools\SupplierReconciliation\Review\ReviewedItem;
use App\Tools\SupplierReconciliation\Review\ReviewedResult;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Tools\SupplierReconciliation\Support\Transactions;

class ReviewApplierTest extends TestCase
{
    /**
     * S1 exact ↔ L1, S2 possible ↔ L2, S3 ambiguous with L3/L4, S4 missing, L5 ledger only, S5 mismatch ↔ L6.
     */
    private function engineResult(): ReconciliationResult
    {
        return (new ReconciliationEngine)->reconcile(
            Transactions::statement([
                ['INV-100', '01/08/2026', '100.00'],
                ['INV-00200', '02/08/2026', '200.00'],
                ['PAYMENT', '05/08/2026', '-50.00'],
                ['INV-400', '06/08/2026', '400.00'],
                ['INV-600', '07/08/2026', '600.00'],
            ]),
            Transactions::ledger([
                ['INV-100', '01/08/2026', '100.00'],
                ['200', '02/08/2026', '200.00'],
                ['PMT-1', '05/08/2026', '-50.00'],
                ['PMT-2', '06/08/2026', '-50.00'],
                ['INV-500', '10/08/2026', '500.00'],
                ['INV-600', '07/08/2026', '590.00'],
            ]),
        );
    }

    /**
     * @param  list<Decision>  $decisions
     */
    private function review(array $decisions = []): ReviewedResult
    {
        return (new ReviewApplier)->apply($this->engineResult(), $decisions);
    }

    private function itemWith(ReviewedResult $reviewed, string $transactionId): ReviewedItem
    {
        $item = $reviewed->itemContaining($transactionId);
        $this->assertNotNull($item);

        return $item;
    }

    public function test_without_decisions_engine_outcomes_are_shown_as_is(): void
    {
        $reviewed = $this->review();

        $auto = $this->itemWith($reviewed, 'S1');
        $this->assertSame(Resolution::Automatic, $auto->resolution);
        $this->assertSame([ReviewedItem::ACTION_REJECT], $auto->actions);

        $possible = $this->itemWith($reviewed, 'S2');
        $this->assertSame(ItemStatus::PossibleMatch, $possible->status);
        $this->assertSame(Resolution::Open, $possible->resolution);
        $this->assertSame([ReviewedItem::ACTION_CONFIRM, ReviewedItem::ACTION_REJECT, ReviewedItem::ACTION_DEFER], $possible->actions);

        $this->assertSame([ReviewedItem::ACTION_CHOOSE, ReviewedItem::ACTION_DEFER], $this->itemWith($reviewed, 'S3')->actions);
        $this->assertSame([ReviewedItem::ACTION_MANUAL_MATCH, ReviewedItem::ACTION_DEFER], $this->itemWith($reviewed, 'S4')->actions);
        $this->assertSame([ReviewedItem::ACTION_REJECT, ReviewedItem::ACTION_DEFER], $this->itemWith($reviewed, 'S5')->actions);
    }

    public function test_confirmation_is_traced_as_a_human_decision(): void
    {
        $itemId = $this->itemWith($this->review(), 'S2')->id;
        $reviewed = $this->review([new Decision('1', DecisionAction::Confirm, $itemId, decidedAt: '2026-09-01T10:00:00Z')]);

        $item = $this->itemWith($reviewed, 'S2');
        $this->assertSame(ItemStatus::Matched, $item->status);
        $this->assertSame(ItemStatus::PossibleMatch, $item->engineStatus);
        $this->assertSame(Resolution::Confirmed, $item->resolution);
        $this->assertSame('decision.confirmed', $item->reasons[0]->code);
        $this->assertFalse($item->needsAttention());

        // The engine result itself is never modified.
        $this->assertSame(ItemStatus::PossibleMatch, $reviewed->engine->item($itemId)?->status);
    }

    public function test_rejection_turns_a_proposal_into_open_exceptions(): void
    {
        $itemId = $this->itemWith($this->review(), 'S2')->id;
        $reviewed = $this->review([new Decision('1', DecisionAction::Reject, $itemId)]);

        $statement = $this->itemWith($reviewed, 'S2');
        $ledger = $this->itemWith($reviewed, 'L2');
        $this->assertSame(ItemStatus::MissingInLedger, $statement->status);
        $this->assertSame(ItemStatus::LedgerOnly, $ledger->status);
        $this->assertSame(Resolution::Rejected, $statement->resolution);
        $this->assertTrue($statement->needsAttention());
        $this->assertContains(ReviewedItem::ACTION_MANUAL_MATCH, $statement->actions);
    }

    public function test_an_automatic_match_can_be_rejected(): void
    {
        $itemId = $this->itemWith($this->review(), 'S1')->id;
        $reviewed = $this->review([new Decision('1', DecisionAction::Reject, $itemId)]);

        $this->assertSame(ItemStatus::MissingInLedger, $this->itemWith($reviewed, 'S1')->status);
    }

    public function test_choosing_a_candidate_of_an_ambiguous_item(): void
    {
        $itemId = $this->itemWith($this->review(), 'S3')->id;
        $reviewed = $this->review([new Decision('1', DecisionAction::Match, $itemId, ['S3'], ['L4'])]);

        $chosen = $this->itemWith($reviewed, 'S3');
        $this->assertSame(Resolution::ManualMatch, $chosen->resolution);
        $this->assertSame(['L4'], $chosen->ledgerIds);

        $leftover = $this->itemWith($reviewed, 'L3');
        $this->assertSame(ItemStatus::LedgerOnly, $leftover->status);
        $this->assertSame(Resolution::Open, $leftover->resolution);
    }

    public function test_manual_match_between_two_exceptions(): void
    {
        $reviewed = $this->review([new Decision('1', DecisionAction::Match, null, ['S4'], ['L5'])]);

        $item = $this->itemWith($reviewed, 'S4');
        $this->assertSame(['L5'], $item->ledgerIds);
        $this->assertSame(Resolution::ManualMatch, $item->resolution);
        $this->assertSame('-100.00', $item->difference?->toDecimal());
        $this->assertNotEmpty($item->candidates);
    }

    public function test_already_matched_lines_cannot_be_matched_again(): void
    {
        $applier = new ReviewApplier;
        $reason = $applier->rejectionReason($this->engineResult(), [], new Decision('1', DecisionAction::Match, null, ['S4'], ['L1']));

        $this->assertSame('A selected line is already matched or excluded.', $reason);
        $this->assertNotNull($applier->rejectionReason($this->engineResult(), [], new Decision('1', DecisionAction::Match, null, ['L1'], ['S4'])));
        $this->assertNotNull($applier->rejectionReason($this->engineResult(), [], new Decision('1', DecisionAction::Match, null, ['S4'], [])));
    }

    public function test_invalid_decisions_are_skipped(): void
    {
        $confirm = new Decision('1', DecisionAction::Confirm, 'does-not-exist');
        $reviewed = $this->review([$confirm]);

        $this->assertSame([], $reviewed->appliedDecisions);
    }

    public function test_deferring_keeps_the_item_open(): void
    {
        $itemId = $this->itemWith($this->review(), 'S4')->id;
        $reviewed = $this->review([new Decision('1', DecisionAction::Defer, $itemId)]);

        $item = $this->itemWith($reviewed, 'S4');
        $this->assertSame(Resolution::Deferred, $item->resolution);
        $this->assertTrue($item->needsAttention());
        $this->assertContains(ReviewedItem::ACTION_UNDO, $item->actions);
    }

    public function test_summary_counts_and_amounts(): void
    {
        $summary = $this->review()->summary();

        $this->assertSame(11, $summary['analyzed_lines']);
        $this->assertSame(['items' => 1, 'lines' => 2], $summary['matched_automatically']);
        $this->assertSame(18.2, $summary['cleared_automatically_percent']);
        $this->assertSame(5, $summary['engine_attention_items']);
        $this->assertSame(5, $summary['attention_items']);
        $this->assertSame(['count' => 1, 'total' => '400.00'], $summary['amounts']['missing_invoices']);
        $this->assertSame(['count' => 1, 'total' => '10.00'], $summary['amounts']['amount_differences']);
        $this->assertSame(['count' => 1, 'total' => '500.00'], $summary['amounts']['ledger_only']);

        $itemId = $this->itemWith($this->review(), 'S2')->id;
        $after = $this->review([new Decision('1', DecisionAction::Confirm, $itemId)])->summary();
        $this->assertSame(4, $after['attention_items']);
        $this->assertSame(5, $after['engine_attention_items']);
        $this->assertSame(1, $after['resolutions']['confirmed']);
    }
}

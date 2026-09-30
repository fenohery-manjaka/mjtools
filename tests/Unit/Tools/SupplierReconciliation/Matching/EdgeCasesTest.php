<?php

namespace Tests\Unit\Tools\SupplierReconciliation\Matching;

use App\Tools\SupplierReconciliation\Matching\MatchingPolicy;
use App\Tools\SupplierReconciliation\Result\ItemStatus;
use App\Tools\SupplierReconciliation\Result\MatchKind;

class EdgeCasesTest extends EngineTestCase
{
    public function test_recurring_invoices_with_the_same_amount_match_individually(): void
    {
        $statement = [];
        $ledger = [];

        foreach (range(1, 12) as $month) {
            $date = sprintf('05/%02d/2026', $month);
            $statement[] = ["INV-10{$month}", $date, '500.00'];
            $ledger[] = ["INV-10{$month}", $date, '500.00'];
        }

        $result = $this->reconcile($statement, $ledger);

        foreach (range(1, 12) as $index) {
            $item = $this->assertStatus(ItemStatus::Matched, $result, "S{$index}");
            $this->assertSame(["L{$index}"], $item->ledgerIds);
        }
    }

    public function test_next_invoice_number_with_same_amount_is_not_matched(): void
    {
        $result = $this->reconcile(
            [['INV-1003', '05/03/2026', '500.00']],
            [['INV-1004', '05/04/2026', '500.00']],
        );

        $this->assertStatus(ItemStatus::MissingInLedger, $result, 'S1');
        $this->assertStatus(ItemStatus::LedgerOnly, $result, 'L1');
    }

    public function test_generic_references_are_never_matched_automatically(): void
    {
        $result = $this->reconcile(
            [['PAYMENT', '12/08/2026', '-500.00']],
            [['PAYMENT', '12/08/2026', '-500.00']],
        );

        $item = $this->assertStatus(ItemStatus::PossibleMatch, $result, 'S1');
        $this->assertReason($item, 'possible.no_reference');
    }

    public function test_reversal_in_ledger_does_not_block_the_true_match(): void
    {
        $result = $this->reconcile(
            [['INV-5050', '12/08/2026', '300.00']],
            [
                ['INV-5050', '12/08/2026', '300.00'],
                ['INV-5050', '14/08/2026', '-300.00'],
            ],
        );

        $this->assertSame(['L1'], $this->assertStatus(ItemStatus::Matched, $result, 'S1')->ledgerIds);

        $reversal = $this->assertStatus(ItemStatus::LedgerOnly, $result, 'L2');
        $this->assertReason($reversal, 'unmatched.related_elsewhere');
    }

    public function test_one_ledger_line_with_two_plausible_statement_lines_is_ambiguous(): void
    {
        $result = $this->reconcile(
            [
                ['4583', '12/08/2026', '500.00'],
                ['INV4583', '12/08/2026', '500.00'],
            ],
            [['INV-4583', '12/08/2026', '500.00']],
        );

        $item = $this->assertLinked($result, ['S1', 'S2'], ['L1']);
        $this->assertSame(ItemStatus::Ambiguous, $item->status);
    }

    public function test_date_tolerance_for_automatic_matches(): void
    {
        $result = $this->reconcile(
            [
                ['INV-7001', '01/08/2026', '100.00'],
                ['INV-7002', '01/08/2026', '200.00'],
            ],
            [
                ['INV-7001', '11/08/2026', '100.00'],
                ['INV-7002', '21/08/2026', '200.00'],
            ],
        );

        $this->assertStatus(ItemStatus::Matched, $result, 'S1');
        $this->assertStatus(ItemStatus::PossibleMatch, $result, 'S2');
    }

    public function test_policy_thresholds_are_configurable(): void
    {
        $result = $this->reconcile(
            [['INV-7001', '01/08/2026', '100.00']],
            [['INV-7001', '11/08/2026', '100.00']],
            new MatchingPolicy(certainMaxDateDays: 3),
        );

        $this->assertStatus(ItemStatus::PossibleMatch, $result, 'S1');
    }

    public function test_without_dates_a_strong_reference_and_amount_still_match(): void
    {
        $result = $this->reconcile(
            [['INV-7001', null, '100.00'], ['INV-00072', null, '50.00']],
            [['inv 7001', null, '100.00'], ['INV-72', null, '50.00']],
        );

        $item = $this->assertStatus(ItemStatus::Matched, $result, 'S1');
        $this->assertSame(MatchKind::Normalized, $item->kind);
        $this->assertReason($item, 'match.no_dates');

        // Removing leading zeros requires a date to corroborate.
        $this->assertReason($this->assertStatus(ItemStatus::PossibleMatch, $result, 'S2'), 'possible.no_date');
    }

    public function test_unreadable_date_prevents_an_automatic_match(): void
    {
        $result = $this->reconcile(
            [['INV-7001', '31/02/2026', '100.00']],
            [['INV-7001', '01/03/2026', '100.00']],
        );

        $item = $this->assertStatus(ItemStatus::PossibleMatch, $result, 'S1');
        $this->assertReason($item, 'possible.unreadable_date');
        $this->assertReason($item, 'date.unreadable');
    }

    public function test_identical_lines_adding_up_to_one_line_are_a_split_proposal(): void
    {
        $result = $this->reconcile(
            [['INV-8080', '12/08/2026', '500.00']],
            [
                ['INV-8080', '12/08/2026', '250.00'],
                ['INV-8080', '13/08/2026', '250.00'],
            ],
        );

        $item = $this->assertLinked($result, ['S1'], ['L1', 'L2']);
        $this->assertSame(ItemStatus::PossibleMatch, $item->status);
        $this->assertReason($item, 'group.identical_lines');
    }

    public function test_groups_larger_than_the_limit_are_not_proposed(): void
    {
        // Six lines (100 + 101 + ... + 105 = 615): more than the five-line limit.
        $ledger = array_map(fn (int $i): array => ['INV-9090', '12/08/2026', (string) (100 + $i)], range(0, 5));

        $result = $this->reconcile(
            [['INV-9090', '12/08/2026', '615.00']],
            $ledger,
        );

        $this->assertNotSame(ItemStatus::PossibleMatch, $this->itemOf($result, 'S1')->status);
        $this->assertSame([], $this->autoMatchedPairs($result));
    }

    public function test_duplicate_on_statement(): void
    {
        $result = $this->reconcile(
            [
                ['INV-321', '12/08/2026', '80.00'],
                ['INV 321', '12/08/2026', '80.00'],
            ],
            [['INV-321', '12/08/2026', '80.00']],
        );

        $item = $this->assertLinked($result, ['S1', 'S2'], ['L1']);
        $this->assertSame(ItemStatus::DuplicateSuspected, $item->status);
        $this->assertSame('Potential duplicate on statement', $item->headline);
    }

    public function test_same_reference_with_several_different_amounts_is_ambiguous(): void
    {
        $result = $this->reconcile(
            [['INV-999', '12/08/2026', '500.00']],
            [
                ['INV-999', '12/08/2026', '450.00'],
                ['INV-999', '13/08/2026', '30.00'],
            ],
        );

        $item = $this->assertLinked($result, ['S1'], ['L1', 'L2']);
        $this->assertSame(ItemStatus::Ambiguous, $item->status);
        $this->assertReason($item, 'ambiguous.same_reference_different_amounts');
    }

    public function test_weak_candidates_with_different_references_are_not_multiplied(): void
    {
        $result = $this->reconcile(
            [['INV-1111', '12/08/2026', '100.00']],
            [
                ['INV-7777', '12/08/2026', '100.00'],
                ['INV-8888', '12/08/2026', '100.00'],
            ],
        );

        $item = $this->assertStatus(ItemStatus::MissingInLedger, $result, 'S1');
        $this->assertReason($item, 'unmatched.only_weak_candidates');
        $this->assertStatus(ItemStatus::LedgerOnly, $result, 'L1');
        $this->assertStatus(ItemStatus::LedgerOnly, $result, 'L2');
    }

    public function test_competitor_with_same_number_blocks_automatic_match_even_when_the_other_is_exact(): void
    {
        $result = $this->reconcile(
            [['INV-4583', '12/08/2026', '500.00']],
            [
                ['INV-4583', '12/08/2026', '500.00'],
                ['PO-4583', '12/08/2026', '500.00'],
            ],
        );

        $this->assertSame(ItemStatus::Ambiguous, $this->itemOf($result, 'S1')->status);
    }

    public function test_empty_inputs(): void
    {
        $this->assertSame([], $this->reconcile([], [])->items);

        $onlyLedger = $this->reconcile([], [['INV-1', '12/08/2026', '1.00']]);
        $this->assertStatus(ItemStatus::LedgerOnly, $onlyLedger, 'L1');
    }

    public function test_zero_amount_lines_are_handled(): void
    {
        $result = $this->reconcile(
            [['INV-0001', '12/08/2026', '0.00']],
            [['INV-0001', '12/08/2026', '0.00']],
        );

        $this->assertStatus(ItemStatus::Matched, $result, 'S1');
    }

    public function test_large_volumes_are_reconciled_correctly(): void
    {
        $statement = [];
        $ledger = [];

        for ($i = 1; $i <= 2000; $i++) {
            $amount = number_format(($i * 37) % 900 + 10, 2, '.', '');
            $date = sprintf('%02d/%02d/2026', ($i % 28) + 1, ($i % 12) + 1);
            $statement[] = [sprintf('INV-%06d', $i), $date, $amount];
            $ledger[] = [sprintf('INV%d', $i), $date, $amount];
        }

        $started = microtime(true);
        $result = $this->reconcile($statement, $ledger);
        $elapsed = microtime(true) - $started;

        $this->assertCount(2000, $this->autoMatchedPairs($result));
        $this->assertLessThan(20, $elapsed, 'Reconciliation of 2 x 2000 lines is unexpectedly slow.');

        foreach ($result->items as $item) {
            $this->assertSame(substr($item->statementIds[0], 1), substr($item->ledgerIds[0], 1));
        }
    }
}

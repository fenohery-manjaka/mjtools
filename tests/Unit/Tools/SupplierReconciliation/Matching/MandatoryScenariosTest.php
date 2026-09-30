<?php

namespace Tests\Unit\Tools\SupplierReconciliation\Matching;

use App\Tools\SupplierReconciliation\Result\Confidence;
use App\Tools\SupplierReconciliation\Result\ItemStatus;
use App\Tools\SupplierReconciliation\Result\MatchKind;
use App\Tools\SupplierReconciliation\Result\ResultItem;

/**
 * The functional test cases required by docs/FUNCTIONAL_SPEC.md §47.
 */
class MandatoryScenariosTest extends EngineTestCase
{
    public function test_exact_match(): void
    {
        $result = $this->reconcile(
            [['INV-4583', '12/08/2026', '1240.00']],
            [['INV-4583', '12/08/2026', '1240.00']],
        );

        $item = $this->assertStatus(ItemStatus::Matched, $result, 'S1');
        $this->assertSame(MatchKind::Exact, $item->kind);
        $this->assertSame(Confidence::Certain, $item->confidence());
        $this->assertSame(['L1'], $item->ledgerIds);
        $this->assertReason($item, 'match.exact');
    }

    public function test_reference_format_is_a_normalized_match(): void
    {
        $result = $this->reconcile(
            [['INV-123', '12/08/2026', '500.00']],
            [['INV123', '12/08/2026', '500.00']],
        );

        $item = $this->assertStatus(ItemStatus::Matched, $result, 'S1');
        $this->assertSame(MatchKind::Normalized, $item->kind);
        $this->assertReason($item, 'reference.formatting');
        $this->assertReason($item, 'reference.transformation');
    }

    public function test_letter_case_is_a_normalized_match(): void
    {
        $result = $this->reconcile(
            [['inv-123', '12/08/2026', '500.00']],
            [['INV-123', '12/08/2026', '500.00']],
        );

        $this->assertSame(MatchKind::Normalized, $this->assertStatus(ItemStatus::Matched, $result, 'S1')->kind);
    }

    public function test_spaces_are_a_normalized_match(): void
    {
        $result = $this->reconcile(
            [['INV 123', '12/08/2026', '500.00']],
            [['INV123', '12/08/2026', '500.00']],
        );

        $this->assertSame(MatchKind::Normalized, $this->assertStatus(ItemStatus::Matched, $result, 'S1')->kind);
    }

    public function test_leading_zeros_match_with_same_amount_and_close_date(): void
    {
        $result = $this->reconcile(
            [['INV-000123', '12/08/2026', '500.00']],
            [['INV-123', '13/08/2026', '500.00']],
        );

        $item = $this->assertStatus(ItemStatus::Matched, $result, 'S1');
        $this->assertReason($item, 'reference.leading_zeros');
    }

    public function test_leading_zeros_without_close_date_need_confirmation(): void
    {
        $result = $this->reconcile(
            [['INV-000123', '12/08/2026', '500.00']],
            [['INV-123', '30/08/2026', '500.00']],
        );

        $item = $this->assertStatus(ItemStatus::PossibleMatch, $result, 'S1');
        $this->assertReason($item, 'possible.date_gap');
    }

    public function test_different_prefix_is_only_a_possible_match(): void
    {
        $result = $this->reconcile(
            [['INV-00123', '12/08/2026', '500.00']],
            [['123', '13/08/2026', '500.00']],
        );

        $item = $this->assertStatus(ItemStatus::PossibleMatch, $result, 'S1');
        $this->assertSame(['L1'], $item->ledgerIds);
        $this->assertSame(Confidence::StrongCandidate, $item->confidence());
        $this->assertReason($item, 'reference.prefix_missing');
        $this->assertReason($item, 'amount.equal');
        $this->assertReason($item, 'date.difference');
    }

    public function test_differently_formatted_amounts_match(): void
    {
        $result = $this->reconcile(
            [['INV-004583', '12/08/2026', '1240.00']],
            [['INV004583', '12/08/2026', '1 240,00']],
        );

        $item = $this->assertStatus(ItemStatus::Matched, $result, 'S1');
        $this->assertSame(MatchKind::Normalized, $item->kind);
        $this->assertReason($item, 'amount.equal');
    }

    public function test_one_day_date_difference_still_matches_on_strong_reference(): void
    {
        $result = $this->reconcile(
            [['INV-4583', '12/08/2026', '1240']],
            [['INV-4583', '13/08/2026', '1240']],
        );

        $item = $this->assertStatus(ItemStatus::Matched, $result, 'S1');
        $this->assertSame(MatchKind::Normalized, $item->kind);
        $this->assertReason($item, 'date.difference');
    }

    public function test_same_reference_different_amount_is_an_amount_mismatch(): void
    {
        $result = $this->reconcile(
            [['INV-1248', '12/08/2026', '1200.00']],
            [['INV-1248', '12/08/2026', '1150.00']],
        );

        $item = $this->assertStatus(ItemStatus::AmountMismatch, $result, 'S1');
        $this->assertSame(['L1'], $item->ledgerIds);
        $this->assertSame('50.00', $item->difference?->toDecimal());
        $this->assertReason($item, 'mismatch.no_judgement');
    }

    public function test_same_amount_with_different_reference_is_never_matched_automatically(): void
    {
        $result = $this->reconcile(
            [['INV-1001', '12/08/2026', '500.00']],
            [['INV-2002', '12/08/2026', '500.00']],
        );

        $item = $this->itemOf($result, 'S1');
        $this->assertNotSame(ItemStatus::Matched, $item->status);
        $this->assertSame([], $this->autoMatchedPairs($result));
    }

    public function test_several_candidates_are_ambiguous(): void
    {
        $result = $this->reconcile(
            [['INV-4583', '12/08/2026', '500.00']],
            [
                ['4583', '12/08/2026', '500.00'],
                ['INV4583', '12/08/2026', '500.00'],
            ],
        );

        $item = $this->assertStatus(ItemStatus::Ambiguous, $result, 'S1');
        $this->assertEqualsCanonicalizing(['L1', 'L2'], $item->ledgerIds);
        $this->assertCount(2, $item->candidates);
        $this->assertSame(Confidence::Ambiguous, $item->confidence());
    }

    public function test_invoice_missing_in_ledger(): void
    {
        $result = $this->reconcile(
            [['INV-9001', '12/08/2026', '800.00']],
            [],
        );

        $item = $this->assertStatus(ItemStatus::MissingInLedger, $result, 'S1');
        $this->assertSame('Invoice missing in ledger', $item->headline);
        $this->assertSame(Confidence::NoMatch, $item->confidence());
    }

    public function test_ledger_only_line(): void
    {
        $result = $this->reconcile(
            [['INV-1', '01/08/2026', '10.00']],
            [['INV-9002', '02/07/2026', '300.00']],
        );

        $item = $this->assertStatus(ItemStatus::LedgerOnly, $result, 'L1');
        $this->assertReason($item, 'unmatched.none');
    }

    public function test_credit_sign_and_nature_are_interpreted(): void
    {
        $result = $this->reconcile(
            [
                ['CN-00824', '10/08/2026', '-420.00'],
                ['CN-00825', '11/08/2026', '-100.00'],
            ],
            [['CN-00825', '11/08/2026', '-100.00']],
        );

        $missing = $this->assertStatus(ItemStatus::MissingInLedger, $result, 'S1');
        $this->assertSame('Credit missing in ledger', $missing->headline);
        $this->assertTrue($missing->hasFlag(ResultItem::FLAG_CREDIT));
        $this->assertReason($missing, 'unmatched.credit_risk');

        $this->assertStatus(ItemStatus::Matched, $result, 'S2');
    }

    public function test_credit_never_matches_an_invoice_of_the_same_absolute_amount(): void
    {
        $result = $this->reconcile(
            [['INV-777', '10/08/2026', '420.00']],
            [['INV-777', '10/08/2026', '-420.00']],
        );

        $item = $this->assertStatus(ItemStatus::ReviewRequired, $result, 'S1');
        $this->assertReason($item, 'mismatch.opposite_sign');
        $this->assertSame([], $this->autoMatchedPairs($result));
    }

    public function test_duplicate_in_ledger(): void
    {
        $result = $this->reconcile(
            [['INV-123', '12/08/2026', '500.00']],
            [
                ['INV-123', '12/08/2026', '500.00'],
                ['INV-123', '12/08/2026', '500.00'],
            ],
        );

        $item = $this->assertLinked($result, ['S1'], ['L1', 'L2']);
        $this->assertSame(ItemStatus::DuplicateSuspected, $item->status);
        $this->assertSame('Potential duplicate in ledger', $item->headline);
        $this->assertReason($item, 'duplicate.nothing_changed');
    }

    public function test_simple_one_to_many_is_proposed_not_validated(): void
    {
        $result = $this->reconcile(
            [['INV-500', '12/08/2026', '1000.00']],
            [
                ['INV-500', '12/08/2026', '600.00'],
                ['INV-500', '14/08/2026', '400.00'],
            ],
        );

        $item = $this->assertLinked($result, ['S1'], ['L1', 'L2']);
        $this->assertSame(ItemStatus::PossibleMatch, $item->status);
        $this->assertSame(MatchKind::Grouped, $item->kind);
        $this->assertTrue($item->hasFlag(ResultItem::FLAG_ONE_TO_MANY));
        $this->assertReason($item, 'group.not_automatic');
    }

    public function test_simple_many_to_one_is_proposed_not_validated(): void
    {
        $result = $this->reconcile(
            [
                ['INV-600', '12/08/2026', '250.00'],
                ['INV-600', '12/08/2026', '750.00'],
            ],
            [['INV-600', '12/08/2026', '1000.00']],
        );

        $item = $this->assertLinked($result, ['S1', 'S2'], ['L1']);
        $this->assertSame(ItemStatus::PossibleMatch, $item->status);
        $this->assertTrue($item->hasFlag(ResultItem::FLAG_MANY_TO_ONE));
    }

    public function test_missing_amount_requires_review(): void
    {
        $result = $this->reconcile(
            [['INV-1', '12/08/2026', 'n/a']],
            [['INV-1', '12/08/2026', '100.00']],
        );

        $item = $this->assertStatus(ItemStatus::ReviewRequired, $result, 'S1');
        $this->assertReason($item, 'data.unreadable_amount');
        $this->assertStatus(ItemStatus::LedgerOnly, $result, 'L1');
    }

    public function test_line_without_reference_nor_date_requires_review(): void
    {
        $result = $this->reconcile(
            [[null, null, '100.00']],
            [['INV-1', '12/08/2026', '100.00']],
        );

        $item = $this->assertStatus(ItemStatus::ReviewRequired, $result, 'S1');
        $this->assertReason($item, 'data.no_reference_no_date');
    }
}

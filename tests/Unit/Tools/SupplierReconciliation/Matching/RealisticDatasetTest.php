<?php

namespace Tests\Unit\Tools\SupplierReconciliation\Matching;

use App\Tools\SupplierReconciliation\Result\ItemStatus;
use App\Tools\SupplierReconciliation\Result\ReconciliationResult;
use App\Tools\SupplierReconciliation\Result\ResultItem;
use Tests\Unit\Tools\SupplierReconciliation\Support\Transactions;

/**
 * A realistic supplier statement and AP ledger extract mixing clean data,
 * imperfect formatting and traps that could produce false matches.
 *
 * Each line is labelled with the expected outcome. The key assertion is that
 * every automatic match is a true match.
 */
class RealisticDatasetTest extends EngineTestCase
{
    /**
     * Supplier statement. Comment = expected outcome.
     *
     * @return list<array{0: ?string, 1: ?string, 2: ?string, 3?: ?string}>
     */
    private function statement(): array
    {
        return [
            ['Balance b/f', '01/07/2026', '1 500,00'],               // S1  excluded
            ['INV-1001', '03/07/2026', '500,00', 'Invoice'],         // S2  exact ↔ L1 (recurring amount)
            ['INV-1002', '03/08/2026', '500,00', 'Invoice'],         // S3  exact ↔ L2 (recurring amount)
            ['INV-1003', '03/09/2026', '500,00', 'Invoice'],         // S4  missing in ledger (never matched to 1001/1002)
            ['INV-004583', '12/08/2026', '1 240,00', 'Invoice'],     // S5  normalized ↔ L3 (INV4583, leading zeros)
            ['inv 2201', '05/08/2026', '2 310,50', 'Invoice'],       // S6  normalized ↔ L4 (case, space)
            ['INV-1248', '10/08/2026', '1 200,00', 'Invoice'],       // S7  amount mismatch ↔ L5
            ['INV-3050', '15/08/2026', '75,00', 'Invoice'],          // S8  possible ↔ L6 (prefix missing)
            ['INV-4583A', '16/08/2026', '640,00', 'Invoice'],        // S9  normalized ↔ L7 (typographic)
            ['INV-6120', '18/08/2026', '99,90', 'Invoice'],          // S10 possible ↔ L8 (two characters swapped)
            ['CN-00824', '20/08/2026', '-420,00', 'Credit note'],    // S11 credit missing in ledger
            ['CN-00830', '21/08/2026', '-60,00', 'Credit note'],     // S12 exact-ish ↔ L9
            ['Payment - thank you', '22/08/2026', '-3 000,00', 'Payment'], // S13 possible ↔ L10 (no usable reference)
            ['Payment - thank you', '25/08/2026', '-150,00', 'Payment'],   // S14 ambiguous with L11, L12
            ['INV-7700', '26/08/2026', '1 000,00', 'Invoice'],       // S15 one-to-many ↔ L13, L14
            ['INV-8800', '27/08/2026', '330,00', 'Invoice'],         // S16 duplicate in ledger (L15, L16)
            ['INV-9900', '28/08/2026', 'TBC', 'Invoice'],            // S17 review required (unreadable amount)
            ['INV-9950', '31/08/2026', '410,00', 'Invoice'],         // S18 missing, possible timing difference
            ['INV-100', '12/08/2026', '250,00', 'Invoice'],          // S19 trap: INV-1000 250 in ledger, never auto
            ['INV-5555', '02/07/2026', '880,00', 'Invoice'],         // S20 date 40 days apart ↔ L19 → possible
        ];
    }

    /**
     * @return list<array{0: ?string, 1: ?string, 2: ?string, 3?: ?string}>
     */
    private function ledger(): array
    {
        return [
            ['INV-1001', '03/07/2026', '500.00'],        // L1
            ['INV-1002', '03/08/2026', '500.00'],        // L2
            ['INV4583', '12/08/2026', '1,240.00'],       // L3
            ['INV-2201', '06/08/2026', '2,310.50'],      // L4
            ['INV-1248', '10/08/2026', '1,150.00'],      // L5
            ['3050', '15/08/2026', '75.00'],             // L6
            ['INV 4583-A', '16/08/2026', '640.00'],      // L7
            ['INV-6210', '18/08/2026', '99.90'],         // L8
            ['CN-00830', '21/08/2026', '-60.00'],        // L9
            ['PMT 000441', '23/08/2026', '-3,000.00'],   // L10
            ['PMT 000442', '25/08/2026', '-150.00'],     // L11
            ['PMT 000443', '26/08/2026', '-150.00'],     // L12
            ['INV-7700', '26/08/2026', '600.00'],        // L13
            ['INV-7700', '26/08/2026', '400.00'],        // L14
            ['INV-8800', '27/08/2026', '330.00'],        // L15
            ['INV-8800', '29/08/2026', '330.00'],        // L16
            ['INV-9100', '14/08/2026', '1,999.00'],      // L17 ledger only
            ['INV-1000', '12/08/2026', '250.00'],        // L18 trap for S19
            ['INV-5555', '11/08/2026', '880.00'],        // L19
            ['INV-9800', '30/08/2026', '45.00'],         // L20 ledger only, near statement end → timing
        ];
    }

    /**
     * True links (statement id => ledger ids). Anything else auto-matched is a false match.
     *
     * @var array<string, list<string>>
     */
    private const TRUTH = [
        'S2' => ['L1'],
        'S3' => ['L2'],
        'S5' => ['L3'],
        'S6' => ['L4'],
        'S7' => ['L5'],
        'S8' => ['L6'],
        'S9' => ['L7'],
        'S10' => ['L8'],
        'S12' => ['L9'],
        'S13' => ['L10'],
        'S15' => ['L13', 'L14'],
        'S20' => ['L19'],
    ];

    public function test_no_false_automatic_match(): void
    {
        $result = $this->reconcile($this->statement(), $this->ledger());

        foreach ($result->items as $item) {
            if ($item->status !== ItemStatus::Matched) {
                continue;
            }

            $this->assertCount(1, $item->statementIds);
            $statementId = $item->statementIds[0];

            $this->assertArrayHasKey($statementId, self::TRUTH, "False automatic match:\n".$this->describe($item));
            $this->assertSame(self::TRUTH[$statementId], $item->ledgerIds, "False automatic match:\n".$this->describe($item));
        }
    }

    public function test_safe_lines_are_cleared_automatically(): void
    {
        $result = $this->reconcile($this->statement(), $this->ledger());

        $this->assertSame([
            'S12=L9',
            'S2=L1',
            'S3=L2',
            'S5=L3',
            'S6=L4',
            'S9=L7',
        ], $this->autoMatchedPairs($result));
    }

    public function test_each_line_gets_the_expected_outcome(): void
    {
        $result = $this->reconcile($this->statement(), $this->ledger());

        $this->assertStatus(ItemStatus::Excluded, $result, 'S1');
        $this->assertStatus(ItemStatus::MissingInLedger, $result, 'S4');
        $this->assertStatus(ItemStatus::AmountMismatch, $result, 'S7');
        $this->assertSame(['L6'], $this->assertStatus(ItemStatus::PossibleMatch, $result, 'S8')->ledgerIds);
        $this->assertSame(['L8'], $this->assertStatus(ItemStatus::PossibleMatch, $result, 'S10')->ledgerIds);

        $credit = $this->assertStatus(ItemStatus::MissingInLedger, $result, 'S11');
        $this->assertSame('Credit missing in ledger', $credit->headline);

        $payment = $this->assertStatus(ItemStatus::PossibleMatch, $result, 'S13');
        $this->assertSame(['L10'], $payment->ledgerIds);
        $this->assertReason($payment, 'possible.no_reference');

        $ambiguous = $this->assertStatus(ItemStatus::Ambiguous, $result, 'S14');
        $this->assertEqualsCanonicalizing(['L11', 'L12'], $ambiguous->ledgerIds);

        $this->assertLinked($result, ['S15'], ['L13', 'L14']);
        $this->assertStatus(ItemStatus::PossibleMatch, $result, 'S15');

        $duplicate = $this->assertStatus(ItemStatus::DuplicateSuspected, $result, 'S16');
        $this->assertEqualsCanonicalizing(['L15', 'L16'], $duplicate->ledgerIds);

        $this->assertStatus(ItemStatus::ReviewRequired, $result, 'S17');

        $timing = $this->assertStatus(ItemStatus::MissingInLedger, $result, 'S18');
        $this->assertTrue($timing->hasFlag(ResultItem::FLAG_TIMING));

        $this->assertNotSame(ItemStatus::Matched, $this->itemOf($result, 'S19')->status);

        $late = $this->assertStatus(ItemStatus::PossibleMatch, $result, 'S20');
        $this->assertReason($late, 'possible.date_gap');

        $this->assertStatus(ItemStatus::LedgerOnly, $result, 'L17');
        $this->assertTrue($this->assertStatus(ItemStatus::LedgerOnly, $result, 'L20')->hasFlag(ResultItem::FLAG_TIMING));
    }

    public function test_result_is_deterministic_and_independent_of_input_order(): void
    {
        $first = $this->reconcile($this->statement(), $this->ledger());
        $second = $this->reconcile($this->statement(), $this->ledger());

        $this->assertSame($first->toArray(), $second->toArray());

        // Reversing the ledger order must not change which lines are linked.
        $reversed = $this->reconcileTransactions(
            Transactions::statement($this->statement()),
            array_reverse(Transactions::ledger($this->ledger())),
        );

        $this->assertSame($this->linkSignature($first), $this->linkSignature($reversed));
    }

    public function test_serialization_round_trip(): void
    {
        $result = $this->reconcile($this->statement(), $this->ledger());

        $restored = ReconciliationResult::fromArray(
            json_decode((string) json_encode($result->toArray()), true),
        );

        $this->assertSame($result->toArray(), $restored->toArray());
    }

    /**
     * @return list<string>
     */
    private function linkSignature(ReconciliationResult $result): array
    {
        $signature = [];

        foreach ($result->items as $item) {
            $statement = $item->statementIds;
            $ledger = $item->ledgerIds;
            sort($statement);
            sort($ledger);
            $signature[] = $item->status->value.':'.implode('+', $statement).'='.implode('+', $ledger);
        }

        sort($signature);

        return $signature;
    }
}

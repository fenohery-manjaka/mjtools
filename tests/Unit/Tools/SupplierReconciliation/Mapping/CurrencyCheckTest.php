<?php

namespace Tests\Unit\Tools\SupplierReconciliation\Mapping;

use App\Tools\SupplierReconciliation\Domain\Side;
use App\Tools\SupplierReconciliation\Import\FileImporter;
use App\Tools\SupplierReconciliation\Import\HeaderDetector;
use App\Tools\SupplierReconciliation\Import\ImportedTable;
use App\Tools\SupplierReconciliation\Mapping\ColumnDetector;
use App\Tools\SupplierReconciliation\Mapping\ColumnMapping;
use App\Tools\SupplierReconciliation\Mapping\CurrencyCheck;
use App\Tools\SupplierReconciliation\Mapping\PreflightCheck;
use App\Tools\SupplierReconciliation\Mapping\PreparedSide;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Tools\SupplierReconciliation\Support\Files;

/**
 * A reconciliation compares one currency: proposed from the files, confirmed
 * by the user, and any other currency blocks the check (spec §4, A.11).
 */
class CurrencyCheckTest extends TestCase
{
    protected function tearDown(): void
    {
        Files::cleanup();
    }

    /**
     * @param  array<string, int>  $columns
     */
    private function prepare(Side $side, string $csv, array $columns): PreparedSide
    {
        $raw = (new FileImporter)->import(Files::text($csv));
        $table = ImportedTable::fromRaw($raw, (new HeaderDetector)->detect($raw));

        return PreparedSide::prepare($side, $table, new ColumnMapping(0, $columns));
    }

    private function plainLedger(): PreparedSide
    {
        return $this->prepare(Side::Ledger, "Ref,Amount\nINV-1,10.00\nINV-2,20.00\n", ['reference' => 0, 'amount' => 1]);
    }

    public function test_the_currency_written_in_the_files_is_proposed(): void
    {
        $statement = $this->prepare(Side::Statement, "Ref,Amount\nINV-1,€10.00\nINV-2,€20.00\n", ['reference' => 0, 'amount' => 1]);

        $proposal = (new CurrencyCheck)->propose($statement, $this->plainLedger());

        $this->assertSame('EUR', $proposal['code']);
        $this->assertStringContainsString('€', $proposal['message']);
    }

    public function test_the_amount_header_announces_the_currency(): void
    {
        $statement = $this->prepare(Side::Statement, "Ref,Amount (GBP)\nINV-1,10.00\n", ['reference' => 0, 'amount' => 1]);

        $this->assertSame('GBP', (new CurrencyCheck)->propose($statement, $this->plainLedger())['code']);
    }

    public function test_nothing_is_proposed_without_evidence_or_with_an_ambiguous_symbol(): void
    {
        $plain = $this->prepare(Side::Statement, "Ref,Amount\nINV-1,10.00\n", ['reference' => 0, 'amount' => 1]);
        $dollars = $this->prepare(Side::Statement, "Ref,Amount\nINV-1,$10.00\n", ['reference' => 0, 'amount' => 1]);
        $check = new CurrencyCheck;

        $this->assertNull($check->propose($plain, $this->plainLedger())['code']);
        $this->assertStringContainsString('No currency', $check->propose($plain, $this->plainLedger())['message']);
        $this->assertNull($check->propose($dollars, $this->plainLedger())['code']);
        $this->assertStringContainsString('several currencies', $check->propose($dollars, $this->plainLedger())['message']);
    }

    public function test_two_currencies_are_never_reconciled_together(): void
    {
        $statement = $this->prepare(Side::Statement, "Ref,Amount\nINV-1,10.00 EUR\nINV-2,20.00 EUR\n", ['reference' => 0, 'amount' => 1]);
        $ledger = $this->prepare(Side::Ledger, "Ref,Amount,Currency\nINV-1,10.00,EUR\nINV-2,20.00,USD\nINV-3,5.00,USD\n", ['reference' => 0, 'amount' => 1, 'currency' => 2]);

        $proposal = (new CurrencyCheck)->propose($statement, $ledger);
        $this->assertNull($proposal['code']);

        $report = (new PreflightCheck)->check($statement, $ledger, 'EUR');

        $this->assertFalse($report['ready']);
        $this->assertSame(['2 lines of the ledger are in USD, but this reconciliation is in EUR. Amounts in different currencies are never compared: remove these lines or reconcile them separately.'], $report['blocking']);
        $this->assertSame([['label' => 'EUR', 'lines' => 1], ['label' => 'USD', 'lines' => 2]], $report['sides']['ledger']['currencies']);
    }

    public function test_a_file_labelled_in_another_currency_blocks(): void
    {
        $statement = $this->prepare(Side::Statement, "Ref,Amount (GBP)\nINV-1,10.00\n", ['reference' => 0, 'amount' => 1]);

        $report = (new PreflightCheck)->check($statement, $this->plainLedger(), 'EUR');

        $this->assertFalse($report['ready']);
        $this->assertStringContainsString('labelled GBP', $report['blocking'][0]);
    }

    public function test_an_unknown_currency_in_a_currency_column_blocks(): void
    {
        $ledger = $this->prepare(Side::Ledger, "Ref,Amount,Currency\nINV-1,10.00,Euro\n", ['reference' => 0, 'amount' => 1, 'currency' => 2]);
        $statement = $this->prepare(Side::Statement, "Ref,Amount\nINV-1,10.00\n", ['reference' => 0, 'amount' => 1]);

        $this->assertStringContainsString('not a recognised currency code', (new CurrencyCheck)->propose($statement, $ledger)['message']);
        $this->assertFalse((new PreflightCheck)->check($statement, $ledger, 'EUR')['ready']);
    }

    public function test_the_reconciliation_waits_for_a_confirmed_currency(): void
    {
        $statement = $this->prepare(Side::Statement, "Ref,Amount\nINV-1,10.00\n", ['reference' => 0, 'amount' => 1]);

        $report = (new PreflightCheck)->check($statement, $this->plainLedger(), null);

        $this->assertFalse($report['ready']);
        $this->assertStringContainsString('Confirm the currency', $report['blocking'][0]);
        $this->assertTrue((new PreflightCheck)->check($statement, $this->plainLedger(), 'EUR')['ready']);
    }

    public function test_a_currency_column_is_detected(): void
    {
        $raw = (new FileImporter)->import(Files::text("Doc No.,Date,Amount,Ccy\nINV-1,01/08/2026,10.00,EUR\nINV-2,02/08/2026,20.00,EUR\n"));
        $table = ImportedTable::fromRaw($raw, 0);

        $mapping = (new ColumnDetector)->suggest($table, Side::Ledger, 0);

        $this->assertSame(3, $mapping->columns['currency'] ?? null);
        $this->assertSame(0, $mapping->columns['reference'] ?? null);
    }
}

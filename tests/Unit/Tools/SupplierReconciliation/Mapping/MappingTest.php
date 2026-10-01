<?php

namespace Tests\Unit\Tools\SupplierReconciliation\Mapping;

use App\Tools\SupplierReconciliation\Domain\DocumentType;
use App\Tools\SupplierReconciliation\Domain\Side;
use App\Tools\SupplierReconciliation\Import\FileImporter;
use App\Tools\SupplierReconciliation\Import\HeaderDetector;
use App\Tools\SupplierReconciliation\Import\ImportedTable;
use App\Tools\SupplierReconciliation\Mapping\AmountMode;
use App\Tools\SupplierReconciliation\Mapping\ColumnDetector;
use App\Tools\SupplierReconciliation\Mapping\ColumnMapping;
use App\Tools\SupplierReconciliation\Mapping\Field;
use App\Tools\SupplierReconciliation\Mapping\PreflightCheck;
use App\Tools\SupplierReconciliation\Mapping\PreparedSide;
use App\Tools\SupplierReconciliation\Mapping\TransactionBuilder;
use App\Tools\SupplierReconciliation\Normalization\DateOrder;
use App\Tools\SupplierReconciliation\Normalization\DecimalSeparator;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Tools\SupplierReconciliation\Support\Files;

class MappingTest extends TestCase
{
    protected function tearDown(): void
    {
        Files::cleanup();
    }

    private function table(string $csv): ImportedTable
    {
        $raw = (new FileImporter)->import(Files::text($csv));

        return ImportedTable::fromRaw($raw, (new HeaderDetector)->detect($raw));
    }

    public function test_it_detects_a_typical_statement(): void
    {
        $table = $this->table("Invoice No.,Invoice Date,Description,Gross Amount,Balance\nINV-004581,01/08/2026,Bricks,\"1,240.00\",\"1,240.00\"\nINV-004582,15/08/2026,Sand,310.50,\"1,550.50\"\nCN-00824,20/08/2026,Return,-420.00,\"1,130.50\"\n");

        $mapping = (new ColumnDetector)->suggest($table, Side::Statement, 0);

        $this->assertSame(0, $mapping->column(Field::Reference));
        $this->assertSame(1, $mapping->column(Field::Date));
        $this->assertSame(2, $mapping->column(Field::Description));
        $this->assertSame(3, $mapping->column(Field::Amount));
        $this->assertSame(AmountMode::Signed, $mapping->amountMode);
        $this->assertSame(ColumnMapping::INVOICES_POSITIVE, $mapping->invoiceSign);
        $this->assertSame(DateOrder::DayFirst, $mapping->dateOrder);
        $this->assertSame(DecimalSeparator::Dot, $mapping->decimalSeparator);
    }

    public function test_it_detects_a_ledger_with_debit_and_credit_columns(): void
    {
        $table = $this->table("Posting Date;Document No.;Supplier;Type;Debit;Credit\n08/01/2026;PI-1001;ACME;Invoice;;1 240,00\n08/20/2026;PAY-77;ACME;Payment;500,00;\n");

        $mapping = (new ColumnDetector)->suggest($table, Side::Ledger, 0);

        $this->assertSame(AmountMode::DebitCredit, $mapping->amountMode);
        $this->assertSame(0, $mapping->column(Field::Date));
        $this->assertSame(1, $mapping->column(Field::Reference));
        $this->assertSame(2, $mapping->column(Field::Supplier));
        $this->assertSame(3, $mapping->column(Field::Type));
        $this->assertSame(4, $mapping->column(Field::Debit));
        $this->assertSame(5, $mapping->column(Field::Credit));
        $this->assertSame(ColumnMapping::INVOICES_IN_CREDIT, $mapping->invoiceColumn);
        $this->assertSame(DecimalSeparator::Comma, $mapping->decimalSeparator);
        $this->assertSame(DateOrder::MonthFirst, $mapping->dateOrder);
    }

    public function test_it_suggests_an_inverted_sign_when_invoices_are_negative(): void
    {
        $table = $this->table("Document No.,Date,Amount\nINV-1,01/08/2026,-100.00\nINV-2,02/08/2026,-250.00\nPAY-3,03/08/2026,300.00\n");

        $mapping = (new ColumnDetector)->suggest($table, Side::Ledger, 0);

        $this->assertSame(ColumnMapping::INVOICES_NEGATIVE, $mapping->invoiceSign);
    }

    public function test_builder_keeps_originals_and_applies_the_sign_convention(): void
    {
        $table = $this->table("Doc,Date,Amount\nINV-1,01/08/2026,-1 240.00\nPAY-2,02/08/2026,500.00\n");
        $mapping = new ColumnMapping(0, ['reference' => 0, 'date' => 1, 'amount' => 2], invoiceSign: ColumnMapping::INVOICES_NEGATIVE);

        $built = (new TransactionBuilder)->build($table, $mapping, Side::Ledger);

        [$invoice, $payment] = $built->transactions;
        $this->assertSame('L1', $invoice->id);
        $this->assertSame(2, $invoice->rowNumber);
        $this->assertSame('-1 240.00', $invoice->original('amount'));
        $this->assertSame('1240.00', $invoice->amount?->toDecimal());
        $this->assertSame(DocumentType::Invoice, $invoice->documentType);
        $this->assertSame(['amounts inverted (in this file invoices are negative)'], $invoice->amountNotes);
        $this->assertSame('-500.00', $payment->amount?->toDecimal());
        $this->assertSame(DocumentType::Payment, $payment->documentType);
    }

    public function test_builder_combines_debit_and_credit(): void
    {
        $table = $this->table("Doc;Date;Debit;Credit\nPI-1;01/08/2026;;1 240,00\nPAY-2;02/08/2026;500,00;\nX-3;03/08/2026;;\n");
        $mapping = new ColumnMapping(0, ['reference' => 0, 'date' => 1, 'debit' => 2, 'credit' => 3], AmountMode::DebitCredit, invoiceColumn: ColumnMapping::INVOICES_IN_CREDIT, decimalSeparator: DecimalSeparator::Comma);

        $built = (new TransactionBuilder)->build($table, $mapping, Side::Ledger);

        $this->assertSame('1240.00', $built->transactions[0]->amount?->toDecimal());
        $this->assertSame('-500.00', $built->transactions[1]->amount?->toDecimal());
        $this->assertNull($built->transactions[2]->amount);
    }

    public function test_builder_can_take_the_sign_from_the_type_column(): void
    {
        $table = $this->table("Ref,Type,Amount\nINV-1,Invoice,100.00\nCN-2,Credit note,40.00\nX-3,Other,5.00\n");
        $mapping = new ColumnMapping(0, ['reference' => 0, 'type' => 1, 'amount' => 2], signFromType: true);

        $built = (new TransactionBuilder)->build($table, $mapping, Side::Statement);

        $this->assertSame('100.00', $built->transactions[0]->amount?->toDecimal());
        $this->assertSame('-40.00', $built->transactions[1]->amount?->toDecimal());
        $this->assertSame(DocumentType::Credit, $built->transactions[1]->documentType);
        $this->assertNull($built->transactions[2]->amount);
        $this->assertArrayHasKey(4, $built->rowIssues);
    }

    public function test_builder_filters_by_supplier_and_ignores_text_lines(): void
    {
        $table = $this->table("Ref,Supplier,Amount,Description\nINV-1,ACME,10.00,\nINV-2,Other Co,20.00,\n,,,Thank you for your business\n");
        $mapping = new ColumnMapping(0, ['reference' => 0, 'supplier' => 1, 'amount' => 2, 'description' => 3], supplierFilter: 'acme');

        $built = (new TransactionBuilder)->build($table, $mapping, Side::Ledger);

        $this->assertCount(1, $built->transactions);
        $this->assertSame(1, $built->filteredOut);
        $this->assertSame(1, $built->ignoredTextRows);
    }

    public function test_builder_ignores_free_text_written_in_the_date_column(): void
    {
        $table = $this->table("Date,Ref,Amount\n01/08/2026,INV-1,10.00\nThank you for your business.,,\n31/02/2026,,\n02/08/2026,,\n");
        $mapping = new ColumnMapping(0, ['date' => 0, 'reference' => 1, 'amount' => 2]);

        $built = (new TransactionBuilder)->build($table, $mapping, Side::Statement);

        // A footer sentence and an impossible date alone carry no transaction.
        $this->assertSame(2, $built->ignoredTextRows);
        // A readable date alone may be a real line with a missing amount: it stays visible.
        $this->assertCount(2, $built->transactions);
        $this->assertNull($built->transactions[1]->amount);
    }

    public function test_builder_reports_unreadable_values(): void
    {
        $table = $this->table("Ref,Date,Amount\nINV-1,31/02/2026,abc\n");
        $mapping = new ColumnMapping(0, ['reference' => 0, 'date' => 1, 'amount' => 2]);

        $built = (new TransactionBuilder)->build($table, $mapping, Side::Statement);

        $this->assertCount(2, $built->rowIssues[2]);
        $this->assertSame(1, $built->unreadableAmounts());
        $this->assertSame(1, $built->unreadableDates());
    }

    public function test_mapping_round_trip_rejects_unknown_fields(): void
    {
        $mapping = ColumnMapping::fromArray([
            'header_index' => 2,
            'columns' => ['reference' => 0, 'amount' => '3', 'bogus' => 1, 'date' => -1],
            'amount_mode' => 'signed',
            'invoice_sign' => 'negative',
            'date_order' => 'mdy',
            'decimal_separator' => 'comma',
            'supplier_filter' => '  ',
        ]);

        $this->assertSame(['reference' => 0, 'amount' => 3], $mapping->columns);
        $this->assertNull($mapping->supplierFilter);
        $this->assertEquals($mapping, ColumnMapping::fromArray($mapping->toArray()));
    }

    public function test_preflight_is_ready_for_good_data(): void
    {
        $statement = $this->prepare(Side::Statement, "Ref,Date,Amount\nINV-1,13/08/2026,10.00\nINV-2,14/08/2026,20.00\n", ['reference' => 0, 'date' => 1, 'amount' => 2]);
        $ledger = $this->prepare(Side::Ledger, "Ref,Date,Amount\nINV-1,13/08/2026,10.00\n", ['reference' => 0, 'date' => 1, 'amount' => 2]);

        $report = (new PreflightCheck)->check($statement, $ledger);

        $this->assertTrue($report['ready'], implode("\n", $report['blocking']));
        $this->assertSame(2, $report['sides']['statement']['transactions']);
        $this->assertTrue($report['sides']['ledger']['fields']['reference']);
    }

    public function test_preflight_blocks_missing_fields_and_unreadable_amounts(): void
    {
        $statement = $this->prepare(Side::Statement, "Ref,Date,Amount\nINV-1,13/08/2026,abc\nINV-2,14/08/2026,xyz\n", ['reference' => 0, 'date' => 1, 'amount' => 2]);
        $ledger = $this->prepare(Side::Ledger, "Ref,Date,Amount\nINV-1,13/08/2026,10.00\n", ['date' => 1, 'amount' => 2]);

        $report = (new PreflightCheck)->check($statement, $ledger);

        $this->assertFalse($report['ready']);
        $this->assertCount(2, $report['blocking']);
        $this->assertStringContainsString('amounts in the supplier statement could not be read', $report['blocking'][0]);
        $this->assertStringContainsString('Reference column of the ledger', $report['blocking'][1]);
    }

    public function test_preflight_blocks_a_column_used_twice(): void
    {
        $statement = $this->prepare(Side::Statement, "Ref,Amount\nINV-1,10.00\n", ['reference' => 0, 'amount' => 1, 'description' => 1]);
        $ledger = $this->prepare(Side::Ledger, "Ref,Amount\nINV-1,10.00\n", ['reference' => 0, 'amount' => 1]);

        $this->assertFalse((new PreflightCheck)->check($statement, $ledger)['ready']);
    }

    public function test_preflight_suggests_inverting_the_ledger_sign(): void
    {
        $rows = "Ref,Date,Amount\nINV-1,01/08/2026,%s10.00\nINV-2,02/08/2026,%s20.00\nINV-3,03/08/2026,%s30.00\n";
        $statement = $this->prepare(Side::Statement, sprintf($rows, '', '', ''), ['reference' => 0, 'date' => 1, 'amount' => 2]);
        $ledger = $this->prepare(Side::Ledger, sprintf($rows, '-', '-', '-'), ['reference' => 0, 'date' => 1, 'amount' => 2]);

        $report = (new PreflightCheck)->check($statement, $ledger);

        $this->assertTrue($report['suggest_inverting_ledger_sign']);
        $this->assertTrue($report['ready']);
    }

    public function test_preflight_warns_when_the_ledger_mixes_suppliers(): void
    {
        $csv = "Ref,Supplier,Amount\nINV-1,ACME,10.00\nINV-2,Other Co,20.00\n";
        $statement = $this->prepare(Side::Statement, "Ref,Amount\nINV-1,10.00\n", ['reference' => 0, 'amount' => 1]);
        $ledger = $this->prepare(Side::Ledger, $csv, ['reference' => 0, 'supplier' => 1, 'amount' => 2]);

        $warnings = implode("\n", (new PreflightCheck)->check($statement, $ledger)['warnings']);
        $this->assertStringContainsString('2 different suppliers', $warnings);

        $filtered = PreparedSide::prepare(Side::Ledger, $this->table($csv), new ColumnMapping(0, ['reference' => 0, 'supplier' => 1, 'amount' => 2], supplierFilter: 'ACME'));
        $this->assertStringNotContainsString('different suppliers', implode("\n", (new PreflightCheck)->check($statement, $filtered)['warnings']));
    }

    public function test_preflight_warns_about_ambiguous_dates(): void
    {
        $statement = $this->prepare(Side::Statement, "Ref,Date,Amount\nINV-1,01/02/2026,10.00\n", ['reference' => 0, 'date' => 1, 'amount' => 2]);
        $ledger = $this->prepare(Side::Ledger, "Ref,Date,Amount\nINV-1,01/02/2026,10.00\n", ['reference' => 0, 'date' => 1, 'amount' => 2]);

        $warnings = implode("\n", (new PreflightCheck)->check($statement, $ledger)['warnings']);

        $this->assertStringContainsString('could be read day-first or month-first', $warnings);
    }

    /**
     * @param  array<string, int>  $columns
     */
    private function prepare(Side $side, string $csv, array $columns): PreparedSide
    {
        return PreparedSide::prepare($side, $this->table($csv), new ColumnMapping(0, $columns));
    }
}

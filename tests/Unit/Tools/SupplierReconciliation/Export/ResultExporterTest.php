<?php

namespace Tests\Unit\Tools\SupplierReconciliation\Export;

use App\Tools\SupplierReconciliation\Export\ExportContext;
use App\Tools\SupplierReconciliation\Export\ResultExporter;
use App\Tools\SupplierReconciliation\Import\FileImporter;
use App\Tools\SupplierReconciliation\Matching\ReconciliationEngine;
use App\Tools\SupplierReconciliation\Review\Decision;
use App\Tools\SupplierReconciliation\Review\DecisionAction;
use App\Tools\SupplierReconciliation\Review\ReviewApplier;
use App\Tools\SupplierReconciliation\Review\ReviewedResult;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Tools\SupplierReconciliation\Support\Transactions;

class ResultExporterTest extends TestCase
{
    /**
     * @param  list<Decision>  $decisions
     */
    private function reviewed(array $decisions = []): ReviewedResult
    {
        $result = (new ReconciliationEngine)->reconcile(
            Transactions::statement([
                ['INV-100', '01/08/2026', '100.00'],
                ['=HYPERLINK("x")', '02/08/2026', '200.00'],
                ['INV-700', '03/08/2026', '1000.00'],
            ]),
            Transactions::ledger([
                ['INV-100', '01/08/2026', '100.00'],
                ['INV-700', '03/08/2026', '600.00'],
                ['INV-700', '03/08/2026', '400.00'],
            ]),
        );

        return (new ReviewApplier)->apply($result, $decisions);
    }

    public function test_rows_contain_both_sides_status_and_reasons(): void
    {
        $rows = (new ResultExporter)->rows($this->reviewed());

        $this->assertSame('Matched', $rows[0][1]);
        $this->assertSame('Matched automatically', $rows[0][2]);
        $this->assertSame('Exact', $rows[0][4]);
        $this->assertSame('Certain', $rows[0][5]);
        $this->assertSame(['2', 'INV-100', '01/08/2026', '100.00'], array_slice($rows[0], 7, 4));
        $this->assertSame(['2', 'INV-100', '01/08/2026', '100.00'], array_slice($rows[0], 11, 4));
        $this->assertStringContainsString('Same reference, amount and date.', $rows[0][17]);
    }

    public function test_rows_carry_the_reconciliation_currency(): void
    {
        $rows = (new ResultExporter)->rows($this->reviewed(), 'EUR');

        $this->assertSame('Currency', ResultExporter::HEADERS[16]);
        $this->assertSame('EUR', $rows[0][16]);
        $this->assertSame('', (new ResultExporter)->rows($this->reviewed())[0][16]);
    }

    public function test_grouped_items_span_several_lines_with_the_same_item_id(): void
    {
        $rows = (new ResultExporter)->rows($this->reviewed());
        $group = array_values(array_filter($rows, fn (array $row): bool => $row[8] === 'INV-700' || ($row[8] === '' && $row[12] === 'INV-700')));

        $this->assertCount(2, $group);
        $this->assertSame($group[0][0], $group[1][0]);
        $this->assertSame('Possible match', $group[0][1]);
        $this->assertSame('', $group[1][8]);
        $this->assertSame('INV-700', $group[1][12]);
    }

    public function test_human_decisions_are_exported(): void
    {
        $item = $this->reviewed()->itemContaining('S3');
        $this->assertNotNull($item);

        $rows = (new ResultExporter)->rows($this->reviewed([new Decision('1', DecisionAction::Confirm, $item->id, decidedAt: '2026-09-01 10:00')]));
        $row = array_values(array_filter($rows, fn (array $r): bool => $r[8] === 'INV-700'))[0];

        $this->assertSame('Confirmed by you', $row[2]);
        $this->assertSame('Possible match', $row[3]);
        $this->assertSame('Confirmed by you 2026-09-01 10:00', $row[18]);
    }

    public function test_csv_neutralizes_formulas_but_keeps_negative_numbers(): void
    {
        $exporter = new ResultExporter;
        $csv = $exporter->csv($this->reviewed());

        $this->assertStringStartsWith("\xEF\xBB\xBFItem,Status", $csv);
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertSame('-420.00', $exporter->neutralizeFormula('-420.00'));
        $this->assertSame("'-1+1", $exporter->neutralizeFormula('-1+1'));
        $this->assertSame("'@SUM(A1)", $exporter->neutralizeFormula('@SUM(A1)'));
    }

    public function test_xlsx_has_a_work_list_of_open_items_and_a_summary_with_context(): void
    {
        $path = sys_get_temp_dir().'/mjtools-export-'.bin2hex(random_bytes(4)).'.xlsx';
        $reviewed = $this->reviewed();
        $open = count(array_filter($reviewed->items, fn ($item): bool => $item->needsAttention()));

        try {
            (new ResultExporter)->xlsx($reviewed, $path, new ExportContext(
                currency: 'EUR',
                statementFile: 'statement.csv',
                ledgerFile: 'ledger.xlsx',
                balance: ['status' => 'verified', 'message' => 'The closing balance equals the lines.'],
                generatedAt: '2026-10-02 08:00',
            ));

            $zip = new \ZipArchive;
            $zip->open($path);
            $workbook = (string) $zip->getFromName('xl/workbook.xml');
            $results = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
            $toReview = (string) $zip->getFromName('xl/worksheets/sheet2.xml');
            $summary = (string) $zip->getFromName('xl/worksheets/sheet3.xml');
            $zip->close();

            $this->assertMatchesRegularExpression('/name="Results".*name="To review".*name="Summary"/s', $workbook);
            $this->assertStringContainsString('autoFilter', $results);
            $this->assertStringContainsString('ySplit="1"', $results);
            // Header + one line per open item at least, fewer than all results.
            $this->assertGreaterThanOrEqual($open + 1, substr_count($toReview, '<row '));
            $this->assertLessThan(substr_count($results, '<row '), substr_count($toReview, '<row '));
            $this->assertStringContainsString('ledger.xlsx', $summary);
            $this->assertStringContainsString('Verified', $summary);
        } finally {
            @unlink($path);
        }
    }

    public function test_summary_rows_carry_the_context(): void
    {
        $rows = (new ResultExporter)->summaryRows($this->reviewed(), new ExportContext(currency: 'GBP', balance: ['status' => 'inconsistent', 'message' => 'Differs by 49.00.']));
        $byLabel = array_column($rows, 1, 0);

        $this->assertSame('GBP', $byLabel['Currency (amounts are never converted)']);
        $this->assertSame('Inconsistent', $byLabel['Statement balance check']);
        $this->assertSame('Differs by 49.00.', $byLabel['Statement balance details']);
        $this->assertStringContainsString('not an accuracy score', $byLabel['Note']);
    }

    public function test_xlsx_export_is_readable_and_contains_no_formula(): void
    {
        $path = sys_get_temp_dir().'/mjtools-export-'.bin2hex(random_bytes(4)).'.xlsx';

        try {
            (new ResultExporter)->xlsx($this->reviewed(), $path);
            $table = (new FileImporter)->import($path);

            $this->assertSame('Item', $table->rows[0][0]);
            $this->assertSame('=HYPERLINK("x")', $table->rows[2][8]);

            $zip = new \ZipArchive;
            $zip->open($path);
            $sheet = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
            $zip->close();
            $this->assertStringNotContainsString('<f>', $sheet);
        } finally {
            @unlink($path);
        }
    }
}

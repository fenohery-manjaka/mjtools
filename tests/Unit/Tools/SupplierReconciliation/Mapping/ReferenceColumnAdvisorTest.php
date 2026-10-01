<?php

namespace Tests\Unit\Tools\SupplierReconciliation\Mapping;

use App\Tools\SupplierReconciliation\Domain\Side;
use App\Tools\SupplierReconciliation\Import\FileImporter;
use App\Tools\SupplierReconciliation\Import\ImportedTable;
use App\Tools\SupplierReconciliation\Mapping\ColumnDetector;
use App\Tools\SupplierReconciliation\Mapping\ColumnMapping;
use App\Tools\SupplierReconciliation\Mapping\Field;
use App\Tools\SupplierReconciliation\Mapping\PreflightCheck;
use App\Tools\SupplierReconciliation\Mapping\PreparedSide;
use App\Tools\SupplierReconciliation\Mapping\ReferenceColumnAdvisor;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Tools\SupplierReconciliation\Support\Files;

/**
 * When a file holds an internal number and the supplier's number, the other
 * file tells which one to use as the reference.
 */
class ReferenceColumnAdvisorTest extends TestCase
{
    protected function tearDown(): void
    {
        Files::cleanup();
    }

    private function table(string $csv): ImportedTable
    {
        return ImportedTable::fromRaw((new FileImporter)->import(Files::text($csv)), 0);
    }

    private function statement(): PreparedSide
    {
        $table = $this->table("Invoice No,Date,Amount\nKIS-24581,02/09/2026,100.00\nKIS-24597,05/09/2026,50.00\nKIS-24612,09/09/2026,20.00\n");

        return PreparedSide::prepare(Side::Statement, $table, new ColumnMapping(0, ['reference' => 0, 'date' => 1, 'amount' => 2]));
    }

    private function ledgerTable(): ImportedTable
    {
        return $this->table("Document No.,External Document No.,Posting Date,Amount\nPI-001842,KIS-24581,03/09/2026,100.00\nPI-001851,KIS-24597,06/09/2026,50.00\nPI-001866,KIS24612,10/09/2026,20.00\n");
    }

    public function test_the_column_sharing_references_with_the_other_file_is_preferred(): void
    {
        $table = $this->ledgerTable();
        $detected = (new ColumnDetector)->suggest($table, Side::Ledger, 0);
        $this->assertSame(0, $detected->column(Field::Reference));

        $better = (new ReferenceColumnAdvisor)->better($table, $detected, $this->statement()->built->transactions);
        $this->assertSame(['column' => 1, 'shared' => 3, 'current' => 0], $better);

        $improved = (new ReferenceColumnAdvisor)->improve($table, $detected, $this->statement()->built->transactions);
        $this->assertSame(1, $improved->column(Field::Reference));
        $this->assertSame($detected->column(Field::Amount), $improved->column(Field::Amount));
    }

    public function test_nothing_changes_without_clear_evidence(): void
    {
        $table = $this->table("Doc,Ref,Posting Date,Amount\nKIS-24581,X-1,03/09/2026,100.00\nKIS-24597,KIS-24597,06/09/2026,50.00\n");
        $mapping = new ColumnMapping(0, ['reference' => 0, 'date' => 2, 'amount' => 3]);

        $this->assertNull((new ReferenceColumnAdvisor)->better($table, $mapping, $this->statement()->built->transactions));
        $this->assertSame($mapping, (new ReferenceColumnAdvisor)->improve($table, $mapping, []));
    }

    public function test_the_preflight_check_points_to_a_better_reference_column(): void
    {
        $table = $this->ledgerTable();
        $ledger = PreparedSide::prepare(Side::Ledger, $table, new ColumnMapping(0, ['reference' => 0, 'date' => 2, 'amount' => 3]));

        $warnings = implode("\n", (new PreflightCheck)->check($this->statement(), $ledger, 'EUR')['warnings']);

        $this->assertStringContainsString('"External Document No." shares 3 references with the supplier statement', $warnings);
    }
}

<?php

namespace Tests\Unit\Tools\SupplierReconciliation;

use App\Tools\SupplierReconciliation\Domain\Side;
use App\Tools\SupplierReconciliation\Import\FileImporter;
use App\Tools\SupplierReconciliation\Import\HeaderDetector;
use App\Tools\SupplierReconciliation\Import\ImportedTable;
use App\Tools\SupplierReconciliation\Mapping\ColumnDetector;
use App\Tools\SupplierReconciliation\Mapping\PreflightCheck;
use App\Tools\SupplierReconciliation\Mapping\PreparedSide;
use App\Tools\SupplierReconciliation\Matching\ReconciliationEngine;
use App\Tools\SupplierReconciliation\Result\ItemStatus;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Tools\SupplierReconciliation\Support\Files;

/**
 * Raw files → import → detected mapping → canonical transactions → engine,
 * with no human correction of the proposed mapping.
 */
class PipelineTest extends TestCase
{
    protected function tearDown(): void
    {
        Files::cleanup();
    }

    public function test_french_statement_against_english_debit_credit_ledger(): void
    {
        $statement = Files::text(implode("\n", [
            'BRICOMAT SARL',
            'Relevé de compte au 31/08/2026',
            '',
            'Date;N° pièce;Libellé;Montant',
            '01/08/2026;Solde reporté;;2 500,00',
            '03/08/2026;FA-004581;Parpaings;1 240,00',
            '10/08/2026;FA-004582;Sable;310,50',
            '18/08/2026;AV-00824;Retour palettes;-420,00',
            '25/08/2026;FA-004590;Ciment;880,00',
            '',
        ]));

        $ledger = Files::xlsx([
            ['Posting Date', 'Document No.', 'Vendor', 'Description', 'Debit', 'Credit'],
            [new DateTimeImmutable('2026-08-04'), 'FA004581', 'Bricomat', 'Parpaings', null, 1240],
            [new DateTimeImmutable('2026-08-10'), 'FA-4582', 'Bricomat', 'Sable', null, 310.5],
            [new DateTimeImmutable('2026-08-20'), 'PMT-7781', 'Bricomat', 'Payment', 2500, null],
        ]);

        $prepared = [];
        $prepared[Side::Statement->value] = $this->prepare(Side::Statement, $statement);
        $prepared[Side::Ledger->value] = $this->prepare(Side::Ledger, $ledger);

        $report = (new PreflightCheck)->check($prepared['statement'], $prepared['ledger'], 'EUR');
        $this->assertTrue($report['ready'], implode("\n", $report['blocking']));

        $result = (new ReconciliationEngine)->reconcile(
            $prepared['statement']->built->transactions,
            $prepared['ledger']->built->transactions,
        );

        $statuses = [];

        foreach ($result->items as $item) {
            foreach ($item->statementIds as $id) {
                $statuses[$result->transaction($id)->original('reference') ?? $id] = $item->status;
            }

            foreach ($item->ledgerIds as $id) {
                $statuses['ledger:'.$result->transaction($id)->original('reference')] ??= $item->status;
            }
        }

        $this->assertSame(ItemStatus::Excluded, $statuses['Solde reporté']);
        $this->assertSame(ItemStatus::Matched, $statuses['FA-004581']);
        $this->assertSame(ItemStatus::Matched, $statuses['FA-004582']);
        $this->assertSame(ItemStatus::MissingInLedger, $statuses['AV-00824']);
        $this->assertSame(ItemStatus::MissingInLedger, $statuses['FA-004590']);
        $this->assertSame(ItemStatus::LedgerOnly, $statuses['ledger:PMT-7781']);
    }

    private function prepare(Side $side, string $path): PreparedSide
    {
        $raw = (new FileImporter)->import($path);
        $header = (new HeaderDetector)->detect($raw);
        $table = ImportedTable::fromRaw($raw, $header);

        return PreparedSide::prepare($side, $table, (new ColumnDetector)->suggest($table, $side, $header));
    }
}

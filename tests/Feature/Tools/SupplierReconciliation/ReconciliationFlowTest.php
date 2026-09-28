<?php

namespace Tests\Feature\Tools\SupplierReconciliation;

use App\Tools\SupplierReconciliation\Domain\Side;
use App\Tools\SupplierReconciliation\Runs\ReconciliationRun;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;
use Tests\Unit\Tools\SupplierReconciliation\Support\Files;

class ReconciliationFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Files::cleanup();

        parent::tearDown();
    }

    private function statementCsv(): UploadedFile
    {
        $content = implode("\n", [
            'ACME Building Supplies — Statement',
            '',
            'Invoice No.;Date;Description;Amount',
            'INV-004581;03/08/2026;Bricks;1 240,00',
            'INV-004582;10/08/2026;Sand;310,50',
            'INV-004590;25/08/2026;Cement;880,00',
            'CN-00824;18/08/2026;Returned pallets;-420,00',
            'INV-004600;12/08/2026;Tiles;99,00',
            '',
        ]);

        return new UploadedFile(Files::text($content), 'acme statement.csv', 'text/csv', null, true);
    }

    private function ledgerXlsx(): UploadedFile
    {
        $path = Files::xlsx([
            ['Posting Date', 'Document No.', 'Description', 'Amount'],
            [new DateTimeImmutable('2026-08-04'), 'INV4581', 'Bricks', 1240],
            [new DateTimeImmutable('2026-08-10'), 'INV-004582', 'Sand', 310.5],
            [new DateTimeImmutable('2026-08-12'), '4600', 'Tiles', 99],
            [new DateTimeImmutable('2026-08-20'), 'INV-009999', 'Unknown', 55],
        ]);

        return new UploadedFile($path, 'ledger.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    private function startRun(): ReconciliationRun
    {
        $this->post(route('supplier-reconciliation.runs.store'))->assertRedirect();

        return ReconciliationRun::query()->latest()->firstOrFail();
    }

    /**
     * Uploads both files and saves the proposed mapping.
     */
    private function preparedRun(): ReconciliationRun
    {
        $run = $this->startRun();

        $this->post(route('supplier-reconciliation.files.store', [$run, 'statement']), ['file' => $this->statementCsv()])->assertSessionHasNoErrors();
        $this->post(route('supplier-reconciliation.files.store', [$run, 'ledger']), ['file' => $this->ledgerXlsx()])->assertSessionHasNoErrors();

        $run->refresh();
        $this->put(route('supplier-reconciliation.mapping.update', $run), [
            'statement' => $run->statement_mapping,
            'ledger' => $run->ledger_mapping,
        ])->assertRedirect(route('supplier-reconciliation.check', $run));

        return $run->refresh();
    }

    public function test_the_landing_page_explains_the_checker(): void
    {
        $this->get(route('supplier-reconciliation.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('tools/supplier-reconciliation/Index')
                ->where('retentionHours', 24));
    }

    public function test_complete_reconciliation_flow(): void
    {
        $run = $this->startRun();

        $this->get(route('supplier-reconciliation.files.edit', $run))
            ->assertInertia(fn (Assert $page) => $page
                ->component('tools/supplier-reconciliation/Files')
                ->where('files.statement', null));

        $this->post(route('supplier-reconciliation.files.store', [$run, 'statement']), ['file' => $this->statementCsv()])
            ->assertRedirect(route('supplier-reconciliation.files.edit', $run))
            ->assertSessionHasNoErrors();

        $this->post(route('supplier-reconciliation.files.store', [$run, 'ledger']), ['file' => $this->ledgerXlsx()])
            ->assertSessionHasNoErrors();

        $this->get(route('supplier-reconciliation.files.edit', $run))
            ->assertInertia(fn (Assert $page) => $page
                ->where('files.statement.name', 'acme statement.csv')
                ->where('files.statement.rows', 5)
                ->where('files.statement.header_row', 3)
                ->where('files.ledger.format', 'xlsx')
                ->where('files.ledger.rows', 4)
                ->has('files.ledger.preview.rows', 4));

        $this->get(route('supplier-reconciliation.mapping.edit', $run))
            ->assertInertia(fn (Assert $page) => $page
                ->component('tools/supplier-reconciliation/Mapping')
                ->where('sides.statement.mapping.columns.reference', 0)
                ->where('sides.statement.mapping.columns.amount', 3)
                ->where('sides.statement.mapping.decimal_separator', 'comma')
                ->where('sides.ledger.mapping.columns.reference', 1)
                ->where('sides.ledger.columns.1.samples', ['INV4581', 'INV-004582', '4600']));

        $run->refresh();
        $this->put(route('supplier-reconciliation.mapping.update', $run), [
            'statement' => $run->statement_mapping,
            'ledger' => $run->ledger_mapping,
        ])->assertRedirect(route('supplier-reconciliation.check', $run));

        $this->get(route('supplier-reconciliation.check', $run))
            ->assertInertia(fn (Assert $page) => $page
                ->component('tools/supplier-reconciliation/Check')
                ->where('report.ready', true)
                ->where('report.sides.statement.transactions', 5)
                ->where('report.sides.ledger.transactions', 4));

        $this->post(route('supplier-reconciliation.reconcile', $run))
            ->assertRedirect(route('supplier-reconciliation.summary', $run));

        $this->get(route('supplier-reconciliation.summary', $run))
            ->assertInertia(fn (Assert $page) => $page
                ->component('tools/supplier-reconciliation/Summary')
                ->where('summary.analyzed_lines', 9)
                ->where('summary.matched_automatically.items', 2)
                ->where('summary.engine_counts.possible_match', 1)
                ->where('summary.engine_counts.missing_in_ledger', 2)
                ->where('summary.engine_counts.ledger_only', 1)
                ->where('summary.attention_items', 4)
                ->where('summary.amounts.missing_credits.total', '420.00'));

        $this->get(route('supplier-reconciliation.review', $run))
            ->assertInertia(fn (Assert $page) => $page
                ->component('tools/supplier-reconciliation/Review')
                ->where('filter', 'all')
                ->has('items', 4)
                ->where('attentionCount', 4)
                ->where('items.0.status', 'missing_in_ledger'));

        $possible = $run->refresh()->reviewed()?->itemContaining('S5');
        $this->assertNotNull($possible);
        $this->assertSame('possible_match', $possible->status->value);

        $this->post(route('supplier-reconciliation.decisions.store', $run), [
            'action' => 'confirm',
            'item_id' => $possible->id,
        ])->assertSessionHasNoErrors();

        $this->get(route('supplier-reconciliation.review', [$run, 'filter' => 'matched']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('items', 3)
                ->where('items.2.resolution', 'confirmed')
                ->where('items.2.engine_status', 'possible_match'));

        $csv = $this->get(route('supplier-reconciliation.export', [$run, 'csv']))->assertOk()->streamedContent();
        $this->assertStringContainsString('Confirmed by you', $csv);
        $this->assertStringContainsString('INV-004590', $csv);

        $this->get(route('supplier-reconciliation.export', [$run, 'xlsx']))
            ->assertOk()
            ->assertDownload('supplier-reconciliation-'.now()->format('Y-m-d').'.xlsx');

        $decisionId = $run->refresh()->decisionList()[0]->id;
        $this->delete(route('supplier-reconciliation.decisions.destroy', [$run, $decisionId]))->assertRedirect();
        $this->assertSame([], $run->refresh()->decisionList());
    }

    public function test_manual_match_options_and_decision(): void
    {
        $run = $this->preparedRun();
        $this->post(route('supplier-reconciliation.reconcile', $run));

        $missing = $run->refresh()->reviewed()?->itemContaining('S3');
        $this->assertNotNull($missing);

        $this->get(route('supplier-reconciliation.review', [$run, 'match_for' => $missing->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->missing('matchOptions')
                ->reloadOnly('matchOptions', fn (Assert $reload) => $reload
                    ->where('matchOptions.item_id', $missing->id)
                    ->where('matchOptions.options.0.id', 'L4')));

        $this->post(route('supplier-reconciliation.decisions.store', $run), [
            'action' => 'match',
            'item_id' => $missing->id,
            'statement_ids' => ['S3'],
            'ledger_ids' => ['L4'],
        ])->assertSessionHasNoErrors();

        $item = $run->refresh()->reviewed()?->itemContaining('S3');
        $this->assertSame('manual_match', $item?->resolution->value);
    }

    public function test_invalid_decisions_are_refused(): void
    {
        $run = $this->preparedRun();
        $this->post(route('supplier-reconciliation.reconcile', $run));

        $this->post(route('supplier-reconciliation.decisions.store', $run), [
            'action' => 'match',
            'statement_ids' => ['S1'],
            'ledger_ids' => ['L4'],
        ])->assertSessionHasErrors('decision');

        $this->post(route('supplier-reconciliation.decisions.store', $run), ['action' => 'explode'])
            ->assertSessionHasErrors('action');
    }

    public function test_runs_are_private_to_the_browser_session(): void
    {
        $run = $this->preparedRun();

        $this->flushSession();

        $this->get(route('supplier-reconciliation.files.edit', $run))->assertNotFound();
        $this->get(route('supplier-reconciliation.export', [$run, 'csv']))->assertNotFound();
        $this->post(route('supplier-reconciliation.files.store', [$run, 'statement']), ['file' => $this->statementCsv()])->assertNotFound();
        $this->delete(route('supplier-reconciliation.runs.destroy', $run))->assertNotFound();
    }

    public function test_expired_runs_are_unavailable_and_purged(): void
    {
        $run = $this->preparedRun();
        $run->forceFill(['expires_at' => now()->subMinute()])->save();

        $this->get(route('supplier-reconciliation.check', $run))->assertNotFound();

        $this->artisan('supplier-reconciliation:purge')->assertSuccessful();
        $this->assertModelMissing($run);
    }

    public function test_the_user_can_delete_their_data(): void
    {
        $run = $this->preparedRun();

        $this->delete(route('supplier-reconciliation.runs.destroy', $run))
            ->assertRedirect(route('supplier-reconciliation.index'));

        $this->assertModelMissing($run);
    }

    public function test_unusable_files_are_explained(): void
    {
        $run = $this->startRun();

        $pdf = new UploadedFile(Files::text("%PDF-1.4\n..."), 'statement.csv', 'text/csv', null, true);
        $this->post(route('supplier-reconciliation.files.store', [$run, 'statement']), ['file' => $pdf])
            ->assertSessionHasErrors(['file' => 'PDF files are not supported. Please save the file as XLSX or CSV and try again.']);

        $exe = new UploadedFile(Files::text('MZ...'), 'statement.exe', 'application/octet-stream', null, true);
        $this->post(route('supplier-reconciliation.files.store', [$run, 'statement']), ['file' => $exe])
            ->assertSessionHasErrors('file');

        $this->post(route('supplier-reconciliation.files.store', [$run, 'invoices']), ['file' => $this->statementCsv()])
            ->assertNotFound();

        $this->assertNull($run->refresh()->statement_table);
    }

    public function test_reconciliation_is_refused_when_data_is_insufficient(): void
    {
        $run = $this->preparedRun();
        $mapping = $run->statement_mapping;
        $this->assertIsArray($mapping);
        unset($mapping['columns']['reference']);

        $this->put(route('supplier-reconciliation.mapping.update', $run), [
            'statement' => $mapping,
            'ledger' => $run->ledger_mapping,
        ]);

        $this->get(route('supplier-reconciliation.check', $run))
            ->assertInertia(fn (Assert $page) => $page->where('report.ready', false));

        $this->post(route('supplier-reconciliation.reconcile', $run))
            ->assertRedirect(route('supplier-reconciliation.check', $run))
            ->assertSessionHasErrors('reconcile');

        $this->assertNull($run->refresh()->result);
    }

    public function test_mapping_rejects_unknown_columns(): void
    {
        $run = $this->preparedRun();
        $mapping = $run->statement_mapping;
        $this->assertIsArray($mapping);
        $mapping['columns']['reference'] = 42;

        $this->put(route('supplier-reconciliation.mapping.update', $run), [
            'statement' => $mapping,
            'ledger' => $run->ledger_mapping,
        ])->assertSessionHasErrors('statement.columns.reference');
    }

    public function test_changing_files_discards_previous_results(): void
    {
        $run = $this->preparedRun();
        $this->post(route('supplier-reconciliation.reconcile', $run));
        $this->assertNotNull($run->refresh()->result);

        $this->post(route('supplier-reconciliation.files.store', [$run, 'ledger']), ['file' => $this->ledgerXlsx()]);

        $this->assertNull($run->refresh()->result);
        $this->get(route('supplier-reconciliation.summary', $run))->assertRedirect(route('supplier-reconciliation.check', $run));
    }

    public function test_changing_the_header_row_redetects_columns(): void
    {
        $run = $this->preparedRun();

        $this->put(route('supplier-reconciliation.mapping.header', [$run, 'statement']), ['header_index' => 0])
            ->assertRedirect(route('supplier-reconciliation.mapping.edit', $run));

        $this->assertSame(0, $run->refresh()->mapping(Side::Statement)?->headerIndex);
    }
}

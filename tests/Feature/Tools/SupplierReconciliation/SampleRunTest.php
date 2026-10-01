<?php

namespace Tests\Feature\Tools\SupplierReconciliation;

use App\Tools\SupplierReconciliation\Runs\ReconciliationRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The one-click sample must demonstrate every kind of result with the
 * mapping proposed automatically, without any correction.
 */
class SampleRunTest extends TestCase
{
    use RefreshDatabase;

    public function test_sample_files_produce_every_kind_of_result(): void
    {
        $this->post(route('supplier-reconciliation.runs.sample'))->assertRedirect();
        $run = ReconciliationRun::query()->latest()->firstOrFail();

        $this->get(route('supplier-reconciliation.files.edit', $run))
            ->assertInertia(fn (Assert $page) => $page
                ->component('tools/supplier-reconciliation/Files')
                ->where('files.statement.name', 'sample-supplier-statement.csv')
                ->where('files.statement.sample', true)
                ->where('files.ledger.sample', true));

        $this->get(route('supplier-reconciliation.mapping.edit', $run))
            ->assertInertia(fn (Assert $page) => $page
                ->where('currency.value', 'GBP')
                ->where('currency.confirmed', false));

        $this->put(route('supplier-reconciliation.mapping.update', $run), [
            'statement' => $run->statement_mapping,
            'ledger' => $run->ledger_mapping,
            'currency' => 'GBP',
        ])->assertRedirect(route('supplier-reconciliation.check', $run));

        $this->get(route('supplier-reconciliation.check', $run))
            ->assertInertia(fn (Assert $page) => $page
                ->where('report.ready', true)
                ->where('report.blocking', [])
                ->where('report.sides.statement.ignored_text_rows', 1)
                ->where('report.balance.status', 'verified')
                ->where('report.balance.closing', '6,381.00'));

        $this->post(route('supplier-reconciliation.reconcile', $run))
            ->assertRedirect(route('supplier-reconciliation.summary', $run));

        $this->get(route('supplier-reconciliation.summary', $run))
            ->assertInertia(fn (Assert $page) => $page
                ->where('balance.status', 'verified')
                ->where('summary.excluded_lines', 2)
                ->where('summary.matched_automatically.items', 6)
                ->where('summary.engine_counts.possible_match', 1)
                ->where('summary.engine_counts.ambiguous', 1)
                ->where('summary.engine_counts.missing_in_ledger', 2)
                ->where('summary.engine_counts.ledger_only', 2)
                ->where('summary.engine_counts.amount_mismatch', 1)
                ->where('summary.engine_counts.duplicate_suspected', 1)
                ->where('summary.engine_counts.review_required', 0)
                ->where('summary.amounts.missing_credits.total', '415.00')
                ->where('summary.amounts.amount_differences.total', '18.00'));
    }

    public function test_sample_files_can_be_downloaded(): void
    {
        $response = $this->get(route('supplier-reconciliation.sample.download', 'statement'))
            ->assertOk()
            ->assertDownload('sample-supplier-statement.csv');

        $this->assertStringContainsString('INV-004581', (string) file_get_contents($response->baseResponse->getFile()->getPathname()));

        $this->get(route('supplier-reconciliation.sample.download', 'other'))->assertNotFound();
    }
}

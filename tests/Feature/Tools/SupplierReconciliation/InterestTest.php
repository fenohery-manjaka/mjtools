<?php

namespace Tests\Feature\Tools\SupplierReconciliation;

use App\Tools\SupplierReconciliation\Interest\InterestResponse;
use App\Tools\SupplierReconciliation\Runs\ReconciliationRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * "Do this every month?" measures the intent behind the paid product
 * (spec §43) without building it and without keeping accounting data.
 */
class InterestTest extends TestCase
{
    use RefreshDatabase;

    private function reconciledSample(): ReconciliationRun
    {
        $this->post(route('supplier-reconciliation.runs.sample'));
        $run = ReconciliationRun::query()->latest()->firstOrFail();

        $this->put(route('supplier-reconciliation.mapping.update', $run), [
            'statement' => $run->statement_mapping,
            'ledger' => $run->ledger_mapping,
            'currency' => 'GBP',
        ]);
        $this->post(route('supplier-reconciliation.reconcile', $run));

        return $run->refresh();
    }

    public function test_the_summary_offers_the_question_with_the_configured_price(): void
    {
        config(['supplier-reconciliation.paid_intent.price' => '€19 / month']);
        $run = $this->reconciledSample();

        $this->get(route('supplier-reconciliation.summary', $run))
            ->assertInertia(fn (Assert $page) => $page
                ->where('intent.sent', false)
                ->where('intent.price', '€19 / month')
                ->has('intent.suppliers_per_month', 5)
                ->has('intent.price_answers', 4));
    }

    public function test_answers_are_stored_without_any_link_to_the_run(): void
    {
        Log::spy();
        $run = $this->reconciledSample();

        $this->post(route('supplier-reconciliation.interest.click', $run))->assertRedirect();
        $this->post(route('supplier-reconciliation.interest.store', $run), [
            'suppliers_per_month' => '21-50',
            'accounting_software' => 'other',
            'accounting_software_other' => 'Pennylane',
            'price_answer' => 'maybe',
            'wanted_next' => ['batch', 'pdf', 'batch'],
            'email' => 'bookkeeper@example.com',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $answer = InterestResponse::query()->sole();
        $this->assertSame('21-50', $answer->suppliers_per_month);
        $this->assertSame('Pennylane', $answer->accounting_software_other);
        $this->assertSame('€29 / month', $answer->price_shown);
        $this->assertSame('bookkeeper@example.com', $answer->email);
        $this->assertSame(['batch', 'pdf'], $answer->wanted_next);
        $this->assertArrayNotHasKey('run_id', $answer->getAttributes());

        Log::shouldHaveReceived('info')->with('supplier-reconciliation.save_supplier_clicked', ['run' => $run->id]);
        Log::shouldHaveReceived('info')->withArgs(fn (string $event, array $context): bool => $event === 'supplier-reconciliation.paid_intent'
            && $context['left_email'] === true
            && ! in_array('bookkeeper@example.com', $context, true));

        $this->get(route('supplier-reconciliation.summary', $run))
            ->assertInertia(fn (Assert $page) => $page->where('intent.sent', true));
    }

    public function test_answers_are_validated_and_reserved_to_the_run_owner(): void
    {
        $run = $this->reconciledSample();

        $this->post(route('supplier-reconciliation.interest.store', $run), [
            'suppliers_per_month' => 'lots',
            'accounting_software' => 'xero',
            'price_answer' => 'yes',
            'wanted_next' => ['teleportation'],
            'email' => 'not-an-email',
        ])->assertSessionHasErrors(['suppliers_per_month', 'email', 'wanted_next.0']);

        $this->flushSession();
        $this->post(route('supplier-reconciliation.interest.store', $run), [
            'suppliers_per_month' => '1-5',
            'accounting_software' => 'xero',
            'price_answer' => 'yes',
        ])->assertNotFound();

        $this->assertSame(0, InterestResponse::query()->count());
    }

    public function test_the_report_summarises_answers_without_emails(): void
    {
        InterestResponse::query()->create([
            'suppliers_per_month' => '6-20',
            'accounting_software' => 'xero',
            'price_answer' => 'yes',
            'wanted_next' => ['integration'],
            'price_shown' => '€29 / month',
            'email' => 'someone@example.com',
        ]);

        $this->artisan('supplier-reconciliation:interest')
            ->expectsOutputToContain('Answers in the last 30 day(s): 1')
            ->expectsOutputToContain('With an email: 1')
            ->expectsOutputToContain('Connect my accounting software')
            ->doesntExpectOutputToContain('someone@example.com')
            ->assertSuccessful();
    }
}

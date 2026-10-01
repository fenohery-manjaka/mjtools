<?php

namespace App\Tools\SupplierReconciliation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Tools\SupplierReconciliation\Interest\InterestResponse;
use App\Tools\SupplierReconciliation\Runs\ReconciliationRun;
use App\Tools\SupplierReconciliation\Runs\RunAccess;
use App\Tools\SupplierReconciliation\Runs\UsageLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Measures the intent behind the first paid product (spec §43) before it is
 * built: a click on "save this supplier", then a few optional answers.
 */
class InterestController extends Controller
{
    public const SESSION_KEY = 'supplier_reconciliation.interest_sent';

    public function __construct(private readonly RunAccess $access) {}

    public function click(Request $request, ReconciliationRun $run): RedirectResponse
    {
        $this->access->ensure($request, $run);

        UsageLog::record('save_supplier_clicked', $run);

        return back();
    }

    public function store(Request $request, ReconciliationRun $run): RedirectResponse
    {
        $this->access->ensure($request, $run);

        $validated = $request->validate([
            'suppliers_per_month' => ['required', Rule::in(array_keys(InterestResponse::SUPPLIERS_PER_MONTH))],
            'accounting_software' => ['required', Rule::in(array_keys(InterestResponse::ACCOUNTING_SOFTWARE))],
            'accounting_software_other' => ['nullable', 'string', 'max:100'],
            'price_answer' => ['required', Rule::in(array_keys(InterestResponse::PRICE_ANSWERS))],
            'email' => ['nullable', 'email', 'max:255'],
        ]);

        InterestResponse::query()->create([
            ...$validated,
            'accounting_software_other' => $validated['accounting_software'] === 'other' ? ($validated['accounting_software_other'] ?? null) : null,
            'price_shown' => (string) config('supplier-reconciliation.paid_intent.price'),
        ]);

        $request->session()->put(self::SESSION_KEY, true);

        // Answers only: the email never reaches the logs.
        UsageLog::record('paid_intent', $run, [
            'suppliers_per_month' => $validated['suppliers_per_month'],
            'accounting_software' => $validated['accounting_software'],
            'price_answer' => $validated['price_answer'],
            'left_email' => ($validated['email'] ?? null) !== null,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Thank you — your answers help us decide what to build next.']);

        return back();
    }
}

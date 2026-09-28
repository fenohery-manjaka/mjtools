<?php

namespace App\Tools\SupplierReconciliation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Tools\SupplierReconciliation\Domain\Side;
use App\Tools\SupplierReconciliation\Http\Presenters\RunPresenter;
use App\Tools\SupplierReconciliation\Mapping\PreflightCheck;
use App\Tools\SupplierReconciliation\Mapping\PreparedSide;
use App\Tools\SupplierReconciliation\Matching\ReconciliationEngine;
use App\Tools\SupplierReconciliation\Runs\ReconciliationRun;
use App\Tools\SupplierReconciliation\Runs\RunAccess;
use App\Tools\SupplierReconciliation\Runs\UsageLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReconciliationController extends Controller
{
    public function __construct(
        private readonly RunAccess $access,
        private readonly RunPresenter $presenter,
    ) {}

    public function check(Request $request, ReconciliationRun $run): Response|RedirectResponse
    {
        $this->access->ensure($request, $run);

        $prepared = $this->prepared($run);

        if ($prepared === null) {
            return to_route('supplier-reconciliation.files.edit', $run);
        }

        $check = new PreflightCheck;

        return Inertia::render('tools/supplier-reconciliation/Check', [
            'run' => $this->presenter->run($run),
            'report' => $check->check(...$prepared),
        ]);
    }

    public function store(Request $request, ReconciliationRun $run, ReconciliationEngine $engine): RedirectResponse
    {
        $this->access->ensure($request, $run);

        // Existing results (and the decisions made on them) are kept until files or mappings change.
        if ($run->isReconciled()) {
            return to_route('supplier-reconciliation.summary', $run);
        }

        $prepared = $this->prepared($run);

        if ($prepared === null) {
            return to_route('supplier-reconciliation.files.edit', $run);
        }

        $report = (new PreflightCheck)->check(...$prepared);

        if (! $report['ready']) {
            UsageLog::record('reconciliation_blocked', $run, ['problems' => count($report['blocking'])]);

            return to_route('supplier-reconciliation.check', $run)
                ->withErrors(['reconcile' => 'Review required before reconciliation: '.implode(' ', $report['blocking'])]);
        }

        [$statement, $ledger] = $prepared;
        $result = $engine->reconcile($statement->built->transactions, $ledger->built->transactions);

        $run->storeResult($result);
        $run->save();

        $counts = array_count_values(array_map(fn ($item): string => $item->status->value, $result->items));
        UsageLog::record('reconciled', $run, ['transactions' => count($result->transactions), ...$counts]);

        return to_route('supplier-reconciliation.summary', $run);
    }

    public function summary(Request $request, ReconciliationRun $run): Response|RedirectResponse
    {
        $this->access->ensure($request, $run);

        $reviewed = $run->reviewed();

        if ($reviewed === null) {
            return to_route('supplier-reconciliation.check', $run);
        }

        return Inertia::render('tools/supplier-reconciliation/Summary', [
            'run' => $this->presenter->run($run),
            'summary' => $reviewed->summary(),
        ]);
    }

    /**
     * @return array{0: PreparedSide, 1: PreparedSide}|null
     */
    private function prepared(ReconciliationRun $run): ?array
    {
        $statement = $run->prepared(Side::Statement);
        $ledger = $run->prepared(Side::Ledger);

        return $statement === null || $ledger === null ? null : [$statement, $ledger];
    }
}

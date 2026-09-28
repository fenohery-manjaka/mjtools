<?php

namespace App\Tools\SupplierReconciliation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Tools\SupplierReconciliation\Runs\ReconciliationRun;
use App\Tools\SupplierReconciliation\Runs\RunAccess;
use App\Tools\SupplierReconciliation\Runs\UsageLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CheckerController extends Controller
{
    public function __construct(private readonly RunAccess $access) {}

    public function index(): Response
    {
        return Inertia::render('tools/supplier-reconciliation/Index', [
            'retentionHours' => (int) config('supplier-reconciliation.retention_hours'),
            'limits' => [
                'max_file_mb' => round((int) config('supplier-reconciliation.limits.max_file_kilobytes') / 1024),
                'max_rows' => (int) config('supplier-reconciliation.limits.max_rows'),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $run = $this->access->create($request);
        UsageLog::record('run_created', $run);

        return to_route('supplier-reconciliation.files.edit', $run);
    }

    public function destroy(Request $request, ReconciliationRun $run): RedirectResponse
    {
        $this->access->ensure($request, $run);

        UsageLog::record('deleted_by_user', $run);
        $run->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Your files and results have been deleted.']);

        return to_route('supplier-reconciliation.index');
    }
}

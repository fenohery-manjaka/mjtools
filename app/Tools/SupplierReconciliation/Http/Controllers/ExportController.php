<?php

namespace App\Tools\SupplierReconciliation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Tools\SupplierReconciliation\Export\ResultExporter;
use App\Tools\SupplierReconciliation\Runs\ReconciliationRun;
use App\Tools\SupplierReconciliation\Runs\RunAccess;
use App\Tools\SupplierReconciliation\Runs\UsageLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    public function __construct(private readonly RunAccess $access) {}

    public function show(Request $request, ReconciliationRun $run, string $format, ResultExporter $exporter): StreamedResponse|BinaryFileResponse|RedirectResponse
    {
        $this->access->ensure($request, $run);

        $reviewed = $run->reviewed();

        if ($reviewed === null) {
            return to_route('supplier-reconciliation.check', $run);
        }

        UsageLog::record('exported', $run, ['format' => $format]);

        $name = 'supplier-reconciliation-'.now()->format('Y-m-d');

        if ($format === 'csv') {
            return response()->streamDownload(
                function () use ($exporter, $reviewed): void {
                    echo $exporter->csv($reviewed);
                },
                "{$name}.csv",
                ['Content-Type' => 'text/csv; charset=UTF-8'],
            );
        }

        $path = tempnam(sys_get_temp_dir(), 'mjtools-export-');

        if ($path === false) {
            abort(500);
        }

        $exporter->xlsx($reviewed, $path);

        return response()->download($path, "{$name}.xlsx", [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend();
    }
}

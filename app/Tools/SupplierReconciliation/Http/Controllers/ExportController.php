<?php

namespace App\Tools\SupplierReconciliation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Tools\SupplierReconciliation\Domain\Side;
use App\Tools\SupplierReconciliation\Export\ExportContext;
use App\Tools\SupplierReconciliation\Export\ResultExporter;
use App\Tools\SupplierReconciliation\Mapping\BalanceCheck;
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

        $statement = $run->prepared(Side::Statement);
        $context = new ExportContext(
            currency: $run->currency,
            statementFile: $run->statement_file['name'] ?? null,
            ledgerFile: $run->ledger_file['name'] ?? null,
            balance: $statement === null ? null : (new BalanceCheck)->check($statement->built->transactions, $statement->built->runningBalances),
            generatedAt: now()->format('Y-m-d H:i'),
        );

        $name = 'supplier-reconciliation-'.now()->format('Y-m-d');

        if ($format === 'csv') {
            return response()->streamDownload(
                function () use ($exporter, $reviewed, $context): void {
                    echo $exporter->csv($reviewed, $context);
                },
                "{$name}.csv",
                ['Content-Type' => 'text/csv; charset=UTF-8'],
            );
        }

        $path = tempnam(sys_get_temp_dir(), 'mjtools-export-');

        if ($path === false) {
            abort(500);
        }

        $exporter->xlsx($reviewed, $path, $context);

        return response()->download($path, "{$name}.xlsx", [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend();
    }
}

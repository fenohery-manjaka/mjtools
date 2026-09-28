<?php

namespace App\Tools\SupplierReconciliation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Tools\SupplierReconciliation\Domain\Side;
use App\Tools\SupplierReconciliation\Http\Presenters\RunPresenter;
use App\Tools\SupplierReconciliation\Http\Requests\UploadFileRequest;
use App\Tools\SupplierReconciliation\Import\FileImporter;
use App\Tools\SupplierReconciliation\Import\HeaderDetector;
use App\Tools\SupplierReconciliation\Import\ImportedTable;
use App\Tools\SupplierReconciliation\Import\ImportException;
use App\Tools\SupplierReconciliation\Mapping\ColumnDetector;
use App\Tools\SupplierReconciliation\Runs\ReconciliationRun;
use App\Tools\SupplierReconciliation\Runs\RunAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FileController extends Controller
{
    public function __construct(
        private readonly RunAccess $access,
        private readonly RunPresenter $presenter,
    ) {}

    public function edit(Request $request, ReconciliationRun $run): Response
    {
        $this->access->ensure($request, $run);

        return Inertia::render('tools/supplier-reconciliation/Files', [
            'run' => $this->presenter->run($run),
            'files' => [
                'statement' => $this->presenter->file($run, Side::Statement),
                'ledger' => $this->presenter->file($run, Side::Ledger),
            ],
            'limits' => [
                'max_file_mb' => round((int) config('supplier-reconciliation.limits.max_file_kilobytes') / 1024),
                'max_rows' => (int) config('supplier-reconciliation.limits.max_rows'),
            ],
        ]);
    }

    /**
     * The uploaded file is read from PHP's temporary upload and never stored.
     */
    public function store(UploadFileRequest $request, ReconciliationRun $run, Side $side, FileImporter $importer): RedirectResponse
    {
        $this->access->ensure($request, $run);

        $file = $request->file('file');
        $path = $file?->getRealPath();

        if ($file === null || $path === false || $path === null) {
            return back()->withErrors(['file' => 'The file could not be uploaded.']);
        }

        try {
            $raw = $importer->import($path);
        } catch (ImportException $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        }

        $headerIndex = (new HeaderDetector)->detect($raw);
        $mapping = (new ColumnDetector)->suggest(ImportedTable::fromRaw($raw, $headerIndex), $side, $headerIndex);
        $name = mb_substr(basename(str_replace('\\', '/', $file->getClientOriginalName())), 0, 200);

        $prefix = $side->value;
        $run->{"{$prefix}_file"} = [
            'name' => $name,
            'size' => $file->getSize(),
            'format' => $raw->format->value,
            'format_label' => $raw->format->label(),
            'details' => $raw->details,
        ];
        $run->{"{$prefix}_table"} = $raw->toArray();
        $run->{"{$prefix}_mapping"} = $mapping->toArray();
        $run->discardResult();
        $run->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$side->label()} imported."]);

        return to_route('supplier-reconciliation.files.edit', $run);
    }
}

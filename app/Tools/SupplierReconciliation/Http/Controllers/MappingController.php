<?php

namespace App\Tools\SupplierReconciliation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Tools\SupplierReconciliation\Domain\Side;
use App\Tools\SupplierReconciliation\Http\Presenters\RunPresenter;
use App\Tools\SupplierReconciliation\Http\Requests\UpdateMappingRequest;
use App\Tools\SupplierReconciliation\Import\ImportedTable;
use App\Tools\SupplierReconciliation\Mapping\ColumnDetector;
use App\Tools\SupplierReconciliation\Mapping\ColumnMapping;
use App\Tools\SupplierReconciliation\Runs\ReconciliationRun;
use App\Tools\SupplierReconciliation\Runs\RunAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class MappingController extends Controller
{
    public function __construct(
        private readonly RunAccess $access,
        private readonly RunPresenter $presenter,
    ) {}

    public function edit(Request $request, ReconciliationRun $run): Response|RedirectResponse
    {
        $this->access->ensure($request, $run);

        if (! $run->hasBothFiles()) {
            return to_route('supplier-reconciliation.files.edit', $run);
        }

        return Inertia::render('tools/supplier-reconciliation/Mapping', [
            'run' => $this->presenter->run($run),
            'sides' => [
                'statement' => $this->presenter->mapping($run, Side::Statement),
                'ledger' => $this->presenter->mapping($run, Side::Ledger),
            ],
            'currency' => $this->presenter->currency($run),
        ]);
    }

    public function update(UpdateMappingRequest $request, ReconciliationRun $run): RedirectResponse
    {
        $this->access->ensure($request, $run);

        if (! $run->hasBothFiles()) {
            return to_route('supplier-reconciliation.files.edit', $run);
        }

        $changed = false;

        foreach ([Side::Statement, Side::Ledger] as $side) {
            $current = $run->mapping($side);
            $table = $run->importedTable($side);

            if ($current === null || $table === null) {
                return to_route('supplier-reconciliation.files.edit', $run);
            }

            /** @var array<string, mixed> $input */
            $input = $request->validated($side->value);
            $columns = array_filter((array) ($input['columns'] ?? []), fn ($column): bool => $column !== null);

            foreach ($columns as $field => $column) {
                if ((int) $column >= count($table->headers)) {
                    throw ValidationException::withMessages(["{$side->value}.columns.{$field}" => 'This column does not exist.']);
                }
            }

            $mapping = ColumnMapping::fromArray([...$input, 'columns' => $columns, 'header_index' => $current->headerIndex]);
            $changed = $changed || $mapping->toArray() !== $current->toArray();
            $run->{"{$side->value}_mapping"} = $mapping->toArray();
        }

        $currency = (string) $request->validated('currency');
        $changed = $changed || $currency !== $run->currency;
        $run->currency = $currency;

        if ($changed) {
            $run->discardResult();
        }

        $run->save();

        return to_route('supplier-reconciliation.check', $run);
    }

    /**
     * Changing the header row re-detects the columns of that file.
     */
    public function header(Request $request, ReconciliationRun $run, Side $side): RedirectResponse
    {
        $this->access->ensure($request, $run);

        $raw = $run->rawTable($side);

        if ($raw === null) {
            return to_route('supplier-reconciliation.files.edit', $run);
        }

        $validated = $request->validate([
            'header_index' => ['required', 'integer', 'min:0', 'max:'.max(0, min(count($raw->rows) - 2, 50))],
        ]);

        $headerIndex = (int) $validated['header_index'];
        $mapping = (new ColumnDetector)->suggest(ImportedTable::fromRaw($raw, $headerIndex), $side, $headerIndex);

        $run->{"{$side->value}_mapping"} = $mapping->toArray();
        $run->discardResult();
        $run->save();

        return to_route('supplier-reconciliation.mapping.edit', $run);
    }
}

<?php

namespace App\Tools\SupplierReconciliation\Runs;

use App\Tools\SupplierReconciliation\Domain\Side;
use App\Tools\SupplierReconciliation\Import\FileImporter;
use App\Tools\SupplierReconciliation\Import\HeaderDetector;
use App\Tools\SupplierReconciliation\Import\ImportedTable;
use App\Tools\SupplierReconciliation\Import\ImportException;
use App\Tools\SupplierReconciliation\Import\RawTable;
use App\Tools\SupplierReconciliation\Mapping\ColumnDetector;

/**
 * Attaches an imported file to a run: extracted cells, detected header row
 * and proposed mapping. The file itself is only read, never stored.
 */
final class RunFiles
{
    public function __construct(private readonly FileImporter $importer) {}

    /**
     * @throws ImportException
     */
    public function attach(ReconciliationRun $run, Side $side, string $path, string $name, int $size, bool $sample = false, ?string $sheet = null): RawTable
    {
        $raw = $this->importer->import($path, $sheet);

        $headerIndex = (new HeaderDetector)->detect($raw);
        $mapping = (new ColumnDetector)->suggest(ImportedTable::fromRaw($raw, $headerIndex), $side, $headerIndex);

        $prefix = $side->value;
        $run->{"{$prefix}_file"} = [
            'name' => mb_substr(basename(str_replace('\\', '/', $name)), 0, 200),
            'size' => $size,
            'format' => $raw->format->value,
            'format_label' => $raw->format->label(),
            'details' => $raw->details,
            'sheets' => $raw->sheets,
            'sample' => $sample,
        ];
        $run->{"{$prefix}_table"} = $raw->toArray();
        $run->{"{$prefix}_mapping"} = $mapping->toArray();
        $run->discardResult();
        $run->save();

        return $raw;
    }
}

<?php

namespace App\Tools\SupplierReconciliation\Runs;

use App\Tools\SupplierReconciliation\Domain\Side;
use App\Tools\SupplierReconciliation\Import\FileImporter;
use App\Tools\SupplierReconciliation\Import\HeaderDetector;
use App\Tools\SupplierReconciliation\Import\ImportedTable;
use App\Tools\SupplierReconciliation\Import\ImportException;
use App\Tools\SupplierReconciliation\Import\RawTable;
use App\Tools\SupplierReconciliation\Mapping\ColumnDetector;
use App\Tools\SupplierReconciliation\Mapping\PreparedSide;
use App\Tools\SupplierReconciliation\Mapping\ReferenceColumnAdvisor;

/**
 * Attaches an imported file to a run: extracted cells, detected header row
 * and proposed mapping. The file itself is only read, never stored.
 */
final class RunFiles
{
    public function __construct(
        private readonly FileImporter $importer,
        private readonly ReferenceColumnAdvisor $advisor = new ReferenceColumnAdvisor,
    ) {}

    /**
     * @throws ImportException
     */
    public function attach(ReconciliationRun $run, Side $side, string $path, string $name, int $size, bool $sample = false, ?string $sheet = null): RawTable
    {
        $raw = $this->importer->import($path, $sheet);

        $headerIndex = (new HeaderDetector)->detect($raw);
        $table = ImportedTable::fromRaw($raw, $headerIndex);
        $mapping = (new ColumnDetector)->suggest($table, $side, $headerIndex);
        $otherSide = $side === Side::Statement ? Side::Ledger : Side::Statement;
        $other = $run->prepared($otherSide);

        // The other file tells which column holds the references it uses.
        if ($other !== null) {
            $mapping = $this->advisor->improve($table, $mapping, $other->built->transactions);
        }

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

        if ($other !== null) {
            $this->revisitOther($run, $otherSide, PreparedSide::prepare($side, $table, $mapping));
        }

        $run->discardResult();
        $run->save();

        return $raw;
    }

    /**
     * The file uploaded first had no other file to compare with: its
     * reference column is reconsidered, unless the user already changed its
     * mapping.
     */
    private function revisitOther(ReconciliationRun $run, Side $side, PreparedSide $new): void
    {
        $other = $run->prepared($side);
        $raw = $run->rawTable($side);

        if ($other === null || $raw === null) {
            return;
        }

        $detected = (new ColumnDetector)->suggest($other->table, $side, $other->mapping->headerIndex);

        if ($detected->toArray() !== $other->mapping->toArray()) {
            return;
        }

        $run->{"{$side->value}_mapping"} = $this->advisor->improve($other->table, $other->mapping, $new->built->transactions)->toArray();
    }
}

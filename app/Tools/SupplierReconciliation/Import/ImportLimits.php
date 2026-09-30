<?php

namespace App\Tools\SupplierReconciliation\Import;

final readonly class ImportLimits
{
    public function __construct(
        public int $maxBytes = 10 * 1_048_576,
        public int $maxRows = 5_000,
        public int $maxColumns = 100,
        /** Guard against zip bombs: total uncompressed size of an XLSX. */
        public int $maxUncompressedBytes = 200 * 1_048_576,
    ) {}
}

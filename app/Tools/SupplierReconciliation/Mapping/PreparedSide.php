<?php

namespace App\Tools\SupplierReconciliation\Mapping;

use App\Tools\SupplierReconciliation\Domain\Side;
use App\Tools\SupplierReconciliation\Import\ImportedTable;

/**
 * One file after mapping: its table, the confirmed mapping and the
 * transactions built from them.
 */
final readonly class PreparedSide
{
    public function __construct(
        public Side $side,
        public ImportedTable $table,
        public ColumnMapping $mapping,
        public BuiltTransactions $built,
    ) {}

    public static function prepare(Side $side, ImportedTable $table, ColumnMapping $mapping, TransactionBuilder $builder = new TransactionBuilder): self
    {
        return new self($side, $table, $mapping, $builder->build($table, $mapping, $side));
    }
}

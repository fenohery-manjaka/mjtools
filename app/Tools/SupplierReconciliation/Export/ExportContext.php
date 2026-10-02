<?php

namespace App\Tools\SupplierReconciliation\Export;

/**
 * What the export says about the reconciliation itself, beyond its lines:
 * the files compared, the currency and the statement balance check.
 */
final readonly class ExportContext
{
    /**
     * @param  array{status: string, message: string}|null  $balance  Result of the statement balance check.
     */
    public function __construct(
        public ?string $currency = null,
        public ?string $statementFile = null,
        public ?string $ledgerFile = null,
        public ?array $balance = null,
        public ?string $generatedAt = null,
    ) {}
}

<?php

namespace App\Tools\SupplierReconciliation\Runs;

use Illuminate\Support\Facades\Log;

/**
 * Product signals of the free checker (spec §49): imports, errors,
 * reconciliations, decisions and exports. Only counts and categories are
 * logged — never references, amounts or file contents.
 */
final class UsageLog
{
    /**
     * @param  array<string, int|string|float|bool|null>  $context
     */
    public static function record(string $event, ReconciliationRun $run, array $context = []): void
    {
        Log::info("supplier-reconciliation.{$event}", ['run' => $run->id, ...$context]);
    }
}

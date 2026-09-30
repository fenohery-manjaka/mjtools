<?php

namespace App\Tools\SupplierReconciliation\Runs;

use Illuminate\Console\Command;

class PurgeExpiredRuns extends Command
{
    protected $signature = 'supplier-reconciliation:purge';

    protected $description = 'Delete supplier reconciliation data older than the retention period';

    public function handle(): int
    {
        $deleted = ReconciliationRun::query()->expired()->delete();

        $this->info("Deleted {$deleted} expired reconciliation(s).");

        return self::SUCCESS;
    }
}

<?php

namespace App\Tools\SupplierReconciliation;

use App\Tools\SupplierReconciliation\Corpus\CorpusReport;
use App\Tools\SupplierReconciliation\Import\FileImporter;
use App\Tools\SupplierReconciliation\Import\ImportLimits;
use App\Tools\SupplierReconciliation\Interest\InterestReport;
use App\Tools\SupplierReconciliation\Matching\MatchingPolicy;
use App\Tools\SupplierReconciliation\Matching\ReconciliationEngine;
use App\Tools\SupplierReconciliation\Runs\PurgeExpiredRuns;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Everything the Supplier Statement Reconciliation tool plugs into mjtools:
 * routes, migrations, command and schedule. Its settings live in
 * config/supplier-reconciliation.php.
 */
class SupplierReconciliationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ImportLimits::class, fn (): ImportLimits => new ImportLimits(
            maxBytes: (int) config('supplier-reconciliation.limits.max_file_kilobytes') * 1024,
            maxRows: (int) config('supplier-reconciliation.limits.max_rows'),
            maxColumns: (int) config('supplier-reconciliation.limits.max_columns'),
        ));

        $this->app->bind(FileImporter::class, fn ($app): FileImporter => new FileImporter($app->make(ImportLimits::class)));

        $this->app->bind(ReconciliationEngine::class, fn (): ReconciliationEngine => new ReconciliationEngine(new MatchingPolicy(
            certainMaxDateDays: (int) config('supplier-reconciliation.matching.certain_max_date_days'),
            leadingZerosMaxDateDays: (int) config('supplier-reconciliation.matching.leading_zeros_max_date_days'),
            amountOnlyMaxDateDays: (int) config('supplier-reconciliation.matching.amount_only_max_date_days'),
            differentReferenceMaxDateDays: (int) config('supplier-reconciliation.matching.different_reference_max_date_days'),
            timingWindowDays: (int) config('supplier-reconciliation.matching.timing_window_days'),
            maxGroupSize: (int) config('supplier-reconciliation.matching.max_group_size'),
        )));
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');

        Route::middleware('web')
            ->prefix('tools/supplier-reconciliation')
            ->name('supplier-reconciliation.')
            ->group(__DIR__.'/routes.php');

        if ($this->app->runningInConsole()) {
            $this->commands([PurgeExpiredRuns::class, InterestReport::class, CorpusReport::class]);
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command(PurgeExpiredRuns::class)->hourly();
        });
    }
}

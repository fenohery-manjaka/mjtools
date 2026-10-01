<?php

use App\Tools\SupplierReconciliation\Http\Controllers\CheckerController;
use App\Tools\SupplierReconciliation\Http\Controllers\ExportController;
use App\Tools\SupplierReconciliation\Http\Controllers\FileController;
use App\Tools\SupplierReconciliation\Http\Controllers\MappingController;
use App\Tools\SupplierReconciliation\Http\Controllers\ReconciliationController;
use App\Tools\SupplierReconciliation\Http\Controllers\ReviewController;
use Illuminate\Support\Facades\Route;

/*
| Supplier Statement Reconciliation — loaded by the tool's service provider
| under /tools/supplier-reconciliation with the "supplier-reconciliation." name prefix.
| The free checker needs no account; runs are bound to the browser session.
*/

Route::get('/', [CheckerController::class, 'index'])->name('index');
Route::post('runs', [CheckerController::class, 'store'])->middleware('throttle:20,1')->name('runs.store');
Route::post('runs/sample', [CheckerController::class, 'sample'])->middleware('throttle:20,1')->name('runs.sample');
Route::get('sample/{side}', [CheckerController::class, 'sampleFile'])->name('sample.download');

Route::prefix('runs/{run}')->group(function () {
    Route::delete('/', [CheckerController::class, 'destroy'])->name('runs.destroy');

    Route::get('files', [FileController::class, 'edit'])->name('files.edit');
    Route::post('files/{side}', [FileController::class, 'store'])->middleware('throttle:30,1')->name('files.store');

    Route::get('mapping', [MappingController::class, 'edit'])->name('mapping.edit');
    Route::put('mapping', [MappingController::class, 'update'])->name('mapping.update');
    Route::put('mapping/{side}/header', [MappingController::class, 'header'])->name('mapping.header');

    Route::get('check', [ReconciliationController::class, 'check'])->name('check');
    Route::post('reconcile', [ReconciliationController::class, 'store'])->middleware('throttle:30,1')->name('reconcile');
    Route::get('summary', [ReconciliationController::class, 'summary'])->name('summary');

    Route::get('review', [ReviewController::class, 'index'])->name('review');
    Route::post('decisions', [ReviewController::class, 'store'])->name('decisions.store');
    Route::delete('decisions/{decision}', [ReviewController::class, 'destroy'])->name('decisions.destroy');

    Route::get('export/{format}', [ExportController::class, 'show'])->whereIn('format', ['csv', 'xlsx'])->name('export');
});

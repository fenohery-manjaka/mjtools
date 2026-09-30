<?php

use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Tools\SupplierReconciliation\SupplierReconciliationServiceProvider;

return [
    AppServiceProvider::class,
    FortifyServiceProvider::class,

    // Tools
    SupplierReconciliationServiceProvider::class,
];

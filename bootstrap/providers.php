<?php

use App\Providers\AppServiceProvider;
use App\Providers\AuthorizationServiceProvider;
use App\Providers\Domain\CrmServiceProvider;
use App\Providers\Domain\FinanceServiceProvider;
use App\Providers\Domain\OperationsServiceProvider;
use App\Providers\Domain\SupplyServiceProvider;
use App\Providers\FortifyServiceProvider;

return [
    AppServiceProvider::class,
    AuthorizationServiceProvider::class,
    CrmServiceProvider::class,
    OperationsServiceProvider::class,
    SupplyServiceProvider::class,
    FinanceServiceProvider::class,
    FortifyServiceProvider::class,
];

<?php

namespace App\Providers\Domain;

use App\Models\WorkOrder;
use App\Observers\WorkOrderObserver;
use Illuminate\Support\ServiceProvider;

class OperationsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        WorkOrder::observe(WorkOrderObserver::class);
    }
}

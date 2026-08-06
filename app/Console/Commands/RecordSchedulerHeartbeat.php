<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class RecordSchedulerHeartbeat extends Command
{
    protected $signature = 'system:heartbeat';

    protected $description = 'Record scheduler liveness';

    public function handle(): int
    {
        Cache::put('system.scheduler.heartbeat', now()->toIso8601String(), now()->addDay());

        return self::SUCCESS;
    }
}

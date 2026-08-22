<?php

namespace App\Console\Commands;

use App\Services\Analytics\AnalyticsAutomationService;
use Illuminate\Console\Command;

class DispatchAnalyticsAutomations extends Command
{
    protected $signature = 'analytics:dispatch';

    protected $description = 'Evaluate analytics alerts and queue due report subscriptions';

    public function handle(AnalyticsAutomationService $automation): int
    {
        $result = $automation->dispatch();
        $this->info("{$result['alerts_evaluated']} alerts evaluated, {$result['alerts_triggered']} triggered, {$result['reports_queued']} reports queued.");

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Services\Crm\LeadResponseSlaNotifier;
use Illuminate\Console\Command;

class CheckLeadResponseSla extends Command
{
    protected $signature = 'crm:check-response-sla';

    protected $description = 'Notify opportunity owners when the first-response SLA is breached';

    public function handle(LeadResponseSlaNotifier $notifier): int
    {
        $count = $notifier->notifyBreaches();
        $this->info("{$count} lead response SLA alerts sent.");

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Services\Operations\DailyReminderDispatcher;
use Illuminate\Console\Command;

class SendActionReminders extends Command
{
    protected $signature = 'crm:send-reminders';

    protected $description = 'Send daily CRM action summaries to the responsible users';

    public function handle(DailyReminderDispatcher $dispatcher): int
    {
        $count = $dispatcher->dispatch();
        $this->info("{$count} action reminders sent.");

        return self::SUCCESS;
    }
}

<?php

use App\Models\Expense;
use App\Models\MaintenancePlan;
use App\Models\MonitoringReading;
use App\Models\PurchaseDocument;
use App\Models\SalesDocument;
use App\Models\ServiceTicket;
use App\Services\Accounting\AccountingSynchronizer;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('accounting:sync', function (AccountingSynchronizer $synchronizer) {
    $count = $synchronizer->sync();
    $this->info("{$count} accounting entries generated.");
})->purpose('Generate missing accounting entries from financial records');

Schedule::command('deye:sync')->everyMinute()->withoutOverlapping();
Schedule::command('huawei:sync')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('monitoring:discover deye')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('monitoring:discover huawei')->cron('2 */6 * * *')->withoutOverlapping();
Schedule::command('locations:sync-spain')->monthly()->withoutOverlapping();
Schedule::command('database:backup')->dailyAt('02:30')->withoutOverlapping();
Schedule::command('database:verify-backup')->weeklyOn(1, '03:30')->withoutOverlapping();
Schedule::command('system:heartbeat')->everyMinute();
Schedule::command('system:health-check')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('data:prune-retention')->weekly()->withoutOverlapping();
Schedule::command('accounting:sync')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('purchase-inbox:sync')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('purchase-inbox:extract --limit=3')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('crm:send-reminders')->dailyAt('07:30')->timezone('Europe/Madrid')->withoutOverlapping();
Schedule::call(fn () => MonitoringReading::where('recorded_at', '<', now()->subYears(2))->delete())
    ->weekly()
    ->name('prune-monitoring-readings')
    ->withoutOverlapping();
Schedule::call(fn () => SalesDocument::where('type', 'invoice')->where('status', 'issued')->whereDate('due_date', '<', today())->update(['status' => 'overdue']))
    ->dailyAt('00:10')->name('mark-overdue-invoices')->withoutOverlapping();
Schedule::call(function () {
    Expense::where('status', 'pending')->whereDate('due_date', '<', today())->update(['status' => 'overdue']);
    PurchaseDocument::where('type', 'supplier_invoice')->where('status', 'registered')->whereDate('due_date', '<', today())->update(['status' => 'overdue']);
})
    ->dailyAt('00:15')->name('mark-overdue-payables')->withoutOverlapping();
Schedule::call(function () {
    MaintenancePlan::with('installation')->where('is_active', true)->whereDate('next_due_on', '<=', today())->each(function ($plan) {
        if (! $plan->tickets()->whereNotIn('status', ['resolved', 'closed'])->exists()) {
            ServiceTicket::create(['installation_id' => $plan->installation_id, 'maintenance_plan_id' => $plan->id, 'number' => 'SAT-'.now()->format('Y').'-'.str_pad((string) (ServiceTicket::count() + 1), 5, '0', STR_PAD_LEFT), 'kind' => 'maintenance', 'priority' => 'normal', 'status' => 'open', 'title' => $plan->name, 'description' => $plan->scope]);
        }
    });
})
    ->dailyAt('06:00')->name('create-due-maintenance-tickets')->withoutOverlapping();

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

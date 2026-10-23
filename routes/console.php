<?php

use App\Models\Expense;
use App\Models\MonitoringReading;
use App\Models\PurchaseDocument;
use App\Models\SalesDocument;
use App\Models\WebVitalSample;
use App\Services\Accounting\AccountingHealthReport;
use App\Services\Accounting\AccountingSynchronizer;
use App\Services\Accounting\RecurringEntryProcessor;
use App\Services\Accounting\SupplierMasterRecovery;
use App\Services\AfterSales\ServiceWorkGenerator;
use App\Services\AfterSales\SlaManager;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Artisan::command('accounting:sync', function (AccountingSynchronizer $synchronizer) {
    $count = $synchronizer->sync();
    $this->info("{$count} accounting entries generated.");
})->purpose('Generate missing accounting entries from financial records');

Artisan::command('accounting:health', function (AccountingHealthReport $report) {
    $this->line(json_encode($report->make(), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
})->purpose('Read-only accounting integrity diagnostics, not a compliance certificate');

Artisan::command('accounting:supplier-masters {--apply}', function (SupplierMasterRecovery $recovery) {
    $this->line(json_encode($recovery->run((bool) $this->option('apply')), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
})->purpose('Preview or restore supplier masters from invoice issuers without changing documents or journals');

Artisan::command('accounting:recurring', function (RecurringEntryProcessor $processor) {
    $result = $processor->process();
    $this->info("{$result['posted']} recurring entries posted, {$result['reversed']} reversed, {$result['failed']} failed.");
})->purpose('Post due recurring entries and accrual reversals');

Artisan::command('performance:web-vitals-report {--days=7} {--minimum=5}', function () {
    $days = max(1, min(90, (int) $this->option('days')));
    $minimum = max(1, (int) $this->option('minimum'));
    $percentile = static function ($values, float $percentile): float {
        $sorted = $values->sort()->values();
        $index = max(0, (int) ceil($sorted->count() * $percentile) - 1);

        return (float) $sorted[$index];
    };
    $format = static fn (string $metric, float $value): string => $metric === 'CLS'
        ? number_format($value, 3)
        : number_format($value, 0).' ms';

    $rows = WebVitalSample::query()
        ->where('occurred_at', '>=', now()->subDays($days))
        ->get(['route_name', 'metric', 'device', 'rating', 'value'])
        ->groupBy(fn (WebVitalSample $sample): string => implode('|', [$sample->route_name, $sample->metric, $sample->device]))
        ->filter(fn ($samples): bool => $samples->count() >= $minimum)
        ->map(function ($samples) use ($format, $percentile): array {
            /** @var WebVitalSample $sample */
            $sample = $samples->first();
            $values = $samples->pluck('value');

            return [
                $sample->route_name,
                $sample->metric,
                $sample->device,
                $samples->count(),
                $format($sample->metric, $percentile($values, 0.50)),
                $format($sample->metric, $percentile($values, 0.75)),
                $format($sample->metric, $percentile($values, 0.95)),
                number_format($samples->where('rating', 'good')->count() / $samples->count() * 100, 1).'%',
            ];
        })->values();

    if ($rows->isEmpty()) {
        $this->info("No route has {$minimum} samples in the last {$days} days.");

        return;
    }

    $this->table(['Route', 'Metric', 'Device', 'Samples', 'p50', 'p75', 'p95', 'Good'], $rows);
})->purpose('Summarize privacy-safe Core Web Vitals by route and device');

Schedule::command('deye:sync')->everyMinute()->withoutOverlapping();
Schedule::command('huawei:sync')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('monitoring:discover deye')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('monitoring:discover huawei')->cron('2 */6 * * *')->withoutOverlapping();
Schedule::command('locations:sync-spain')->monthly()->withoutOverlapping();
Schedule::command('database:backup')->dailyAt('02:30')->when(fn () => DB::getDriverName() === 'sqlite')->withoutOverlapping();
Schedule::command('database:verify-backup')->weeklyOn(1, '03:30')->when(fn () => DB::getDriverName() === 'sqlite')->withoutOverlapping();
Schedule::command('database:backup-postgres')
    ->dailyAt('02:30')
    ->when(fn () => DB::getDriverName() === 'pgsql' && config('backup.postgres.enabled'))
    ->onOneServer()
    ->withoutOverlapping(120);
Schedule::command('database:verify-postgres-backup')
    ->weeklyOn(1, '03:30')
    ->when(fn () => DB::getDriverName() === 'pgsql' && config('backup.postgres.restore_enabled'))
    ->onOneServer()
    ->withoutOverlapping(180);
Schedule::command('system:heartbeat')->everyMinute();
Schedule::command('system:health-check')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('data:prune-retention')->weekly()->withoutOverlapping();
Schedule::command('accounting:sync')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('accounting:recurring')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('analytics:dispatch')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('purchase-inbox:sync')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('purchase-inbox:extract --limit=3')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('crm:send-reminders')->dailyAt('07:30')->timezone('Europe/Madrid')->withoutOverlapping();
Schedule::command('crm:check-response-sla')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('ai:reindex --stale')->dailyAt('03:15')->withoutOverlapping();
Schedule::call(fn () => MonitoringReading::where('recorded_at', '<', now()->subYears(2))->delete())
    ->weekly()
    ->name('prune-monitoring-readings')
    ->withoutOverlapping();
Schedule::call(fn () => WebVitalSample::where('occurred_at', '<', now()->subDays(config('performance.web_vitals_retention_days')))->delete())
    ->dailyAt('03:40')
    ->name('prune-web-vital-samples')
    ->withoutOverlapping();
Schedule::call(fn () => SalesDocument::where('type', 'invoice')->where('status', 'issued')->whereDate('due_date', '<', today())->update(['status' => 'overdue']))
    ->dailyAt('00:10')->name('mark-overdue-invoices')->withoutOverlapping();
Schedule::call(function () {
    Expense::where('status', 'pending')->whereDate('due_date', '<', today())->update(['status' => 'overdue']);
    PurchaseDocument::where('type', 'supplier_invoice')->where('status', 'registered')->whereDate('due_date', '<', today())->update(['status' => 'overdue']);
})
    ->dailyAt('00:15')->name('mark-overdue-payables')->withoutOverlapping();
Schedule::call(fn () => app(ServiceWorkGenerator::class)->generateDueMaintenance())
    ->dailyAt('06:00')->name('create-due-maintenance-tickets')->withoutOverlapping();
Schedule::call(fn () => app(SlaManager::class)->escalateOverdue())
    ->everyFifteenMinutes()->name('escalate-overdue-service-tickets')->withoutOverlapping();

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

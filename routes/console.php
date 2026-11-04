<?php

use App\Models\AuditLog;
use App\Models\Expense;
use App\Models\MonitoringReading;
use App\Models\PurchaseDocument;
use App\Models\SalesDocument;
use App\Models\User;
use App\Models\WebsiteContactMail;
use App\Models\WebsiteContactReceipt;
use App\Models\WebVitalSample;
use App\Services\Accounting\AccountingHealthReport;
use App\Services\Accounting\AccountingSynchronizer;
use App\Services\Accounting\RecurringEntryProcessor;
use App\Services\Accounting\SupplierMasterRecovery;
use App\Services\AfterSales\ServiceWorkGenerator;
use App\Services\AfterSales\SlaManager;
use App\Services\Website\WebsiteContactOutbox;
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

// Full legacy automation requires a separate, explicit review.
if (config('automation.scheduler_profile') === 'approved_full') {
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

} elseif (config('automation.scheduler_profile') === 'heartbeat_only') {
    Schedule::command('system:heartbeat')->everyMinute()->onOneServer()->withoutOverlapping();
}

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('website-contact:prune {--apply}', function () {
    $expired = WebsiteContactReceipt::query()->where('expires_at', '<=', now())->whereNotNull('processed_at')->whereDoesntHave('mailItems', fn ($query) => $query->whereNotIn('status', ['sent', 'cancelled']));
    $pending = WebsiteContactReceipt::query()->where('expires_at', '<=', now())->whereNull('processed_at')->count();
    $total = (clone $expired)->count();
    $this->warn("Registros vencidos: {$total}; pendientes de procesamiento: {$pending}.");
    if (! $this->option('apply')) {
        $this->line('Vista previa: no se ha eliminado ningún registro.');

        return;
    }
    $deleted = 0;
    $expired->select('id')->chunkById(100, function ($receipts) use (&$deleted): void {
        foreach ($receipts as $entry) {
            $deleted += \DB::transaction(function () use ($entry): int {
                $receipt = WebsiteContactReceipt::query()->whereKey($entry->id)->lockForUpdate()->first();
                if (! $receipt || ! $receipt->processed_at || $receipt->expires_at->isFuture()) {
                    return 0;
                }
                $mail = $receipt->mailItems()->lockForUpdate()->get();
                if ($mail->contains(fn ($item) => ! in_array($item->status, ['sent', 'cancelled'], true))) {
                    return 0;
                }
                AuditLog::create([
                    'event' => 'contact_retention_prune',
                    'auditable_type' => $receipt->getMorphClass(),
                    'auditable_id' => $receipt->id,
                    'new_values' => ['mail' => $mail->map(fn ($item) => [
                        'id' => $item->id, 'kind' => $item->kind, 'status' => $item->status,
                        'attempts' => $item->attempts, 'sent_at' => $item->sent_at?->toIso8601String(),
                    ])->all()],
                ]);
                $receipt->mailItems()->delete();
                $receipt->delete();

                return 1;
            });
        }
    });
    $this->info("Registros vencidos eliminados: {$deleted}.");
})->purpose('Revisar o eliminar recepciones web vencidas');

Artisan::command('website-contact:mail {id?} {--send} {--operator=}', function (WebsiteContactOutbox $outbox) {
    if (! $this->option('send')) {
        WebsiteContactMail::query()->whereIn('status', ['pending', 'sending', 'uncertain'])->orderBy('id')->limit(100)->get(['id', 'kind', 'status', 'attempts', 'attempted_at', 'error_class'])->each(fn ($item) => $this->line(json_encode($item->toArray())));

        return;
    }
    if (! $this->argument('id') || ! $this->option('operator')) {
        $this->error('Indica el ID y el operador con --operator.');

        return 1;
    }
    $result = $outbox->send((int) $this->argument('id'));
    Log::info('Operación manual de correo web.', ['mail_id' => (int) $this->argument('id'), 'operator' => $this->option('operator'), 'result' => $result]);
    $this->line($result);

    return $result === 'sent' ? 0 : 1;
})->purpose('Consultar o enviar manualmente un correo web pendiente');

Artisan::command('website-contact:mail-resolve {id} {decision} {--operator=} {--reason=}', function () {
    $decision = $this->argument('decision');
    $operator = $this->option('operator');
    $reason = $this->option('reason');
    if (! in_array($decision, ['sent', 'retry', 'cancel'], true) || ! $operator || ! $reason || strlen($reason) > 250) {
        $this->error('Indica sent, retry o cancel, un operador y el motivo de la resolución.');

        return 1;
    }
    $item = \DB::transaction(function () use ($decision, $operator, $reason) {
        $item = WebsiteContactMail::query()->lockForUpdate()->findOrFail($this->argument('id'));
        if ($item->status === 'pending' && $decision === 'cancel') {
            $actor = ctype_digit((string) $operator) ? User::query()->find((int) $operator) : null;
            if (! $actor || ! $actor->can('manage-admin') || $item->attempts !== 0 || $item->attempted_at !== null || $item->sent_at !== null || trim($reason) === '') {
                return null;
            }
            $item->update(['status' => 'cancelled']);
            AuditLog::create([
                'user_id' => $actor->id,
                'event' => 'contact_mail_pending_cancelled',
                'auditable_type' => $item->getMorphClass(),
                'auditable_id' => $item->id,
                'old_values' => ['status' => 'pending', 'attempts' => 0],
                'new_values' => ['status' => 'cancelled', 'reason' => trim($reason)],
            ]);

            return $item;
        }
        if (! in_array($item->status, ['uncertain', 'sending'], true)
            || ($item->status === 'sending' && (! $item->attempted_at || $item->attempted_at->gt(now()->subMinutes(5))))) {
            return null;
        }
        $item->update(['status' => match ($decision) {
            'sent' => 'sent', 'retry' => 'pending', default => 'cancelled'
        }, 'sent_at' => $decision === 'sent' ? now() : null]);

        return $item;
    });
    if (! $item) {
        $this->error('Estado no resoluble o cancelación pendiente no autorizada.');

        return 1;
    }
    Log::warning('Resolución manual de correo web.', ['mail_id' => $item->id, 'operator' => $operator, 'decision' => $decision, 'reason' => $reason]);
    $this->line('Resolución registrada.');
})->purpose('Resolver manualmente el estado incierto de un correo web');

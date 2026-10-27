<?php

namespace App\Console\Commands;

use App\Models\BackupRestoration;
use App\Models\DatabaseBackup;
use App\Models\KnowledgeDocument;
use App\Models\SystemHealthCheck;
use App\Services\Deye\DeyeCloudClient;
use App\Services\Security\SecurityEventRecorder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

class CheckSystemHealth extends Command
{
    protected $signature = 'system:health-check';

    protected $description = 'Check queues, scheduler, mail, storage and external integrations';

    public function handle(SecurityEventRecorder $events, DeyeCloudClient $deye): int
    {
        $checks = [];
        $failed = Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0;
        $pending = Schema::hasTable('jobs') ? DB::table('jobs')->count() : 0;
        $oldestPendingSeconds = $pending ? max(0, now()->timestamp - (int) DB::table('jobs')->min('created_at')) : 0;
        $queueStatus = $failed || $oldestPendingSeconds > 900 ? 'failed' : ($oldestPendingSeconds > 300 ? 'warning' : 'healthy');
        $queueMessage = $failed
            ? "{$failed} failed jobs require attention."
            : ($oldestPendingSeconds > 300 ? "The oldest queued job has waited {$oldestPendingSeconds} seconds." : 'Queue is processing normally.');
        $checks[] = ['queue', $queueStatus, $queueMessage, ['pending' => $pending, 'failed' => $failed, 'oldest_pending_seconds' => $oldestPendingSeconds]];
        $heartbeat = Cache::get('system.scheduler.heartbeat');
        $fresh = $heartbeat && now()->diffInMinutes($heartbeat) <= 5;
        $checks[] = ['scheduler', $fresh ? 'healthy' : 'failed', $fresh ? 'Scheduler heartbeat is current.' : 'Scheduler heartbeat is missing or stale.', ['heartbeat' => $heartbeat]];
        $mailer = config('mail.default');
        $checks[] = ['mail', $mailer && $mailer !== 'log' ? 'healthy' : 'warning', $mailer ? "Mailer {$mailer} is configured." : 'No mailer configured.', ['mailer' => $mailer]];
        $writable = File::isWritable(storage_path());
        $free = @disk_free_space(storage_path());
        $checks[] = ['storage', $writable && ($free === false || $free > 536870912) ? 'healthy' : 'failed', $writable ? 'Private storage is writable.' : 'Private storage is not writable.', ['free_bytes' => $free]];
        $integrations = ['deye' => $deye->configured(), 'huawei' => (bool) config('services.huawei.username'), 'purchase_mailbox' => Schema::hasTable('purchase_mailboxes') && DB::table('purchase_mailboxes')->where('is_active', true)->exists()];
        foreach ($integrations as $name => $configured) {
            $checks[] = ['integration:'.$name, $configured ? 'healthy' : 'warning', $configured ? 'Integration is configured.' : 'Integration is not configured.', []];
        }
        if (DB::getDriverName() === 'pgsql' && Schema::hasTable('database_backups')) {
            $backupEnabled = (bool) config('backup.postgres.enabled');
            $latestBackup = DatabaseBackup::query()->where('engine', 'pgsql')->where('status', 'success')->latest('completed_at')->first();
            $backupFresh = $backupEnabled && $latestBackup?->completed_at?->greaterThanOrEqualTo(now()->subHours((int) config('backup.postgres.max_age_hours', 36)));
            $checks[] = [
                'database-backup',
                $backupFresh ? 'healthy' : 'failed',
                $backupFresh ? 'The latest encrypted PostgreSQL backup is current.' : ($backupEnabled ? 'No current encrypted PostgreSQL backup is available.' : 'PostgreSQL logical backups are disabled.'),
                ['enabled' => $backupEnabled, 'latest_backup_at' => $latestBackup?->completed_at?->toIso8601String(), 'backup_id' => $latestBackup?->id],
            ];

            $restoreEnabled = (bool) config('backup.postgres.restore_enabled');
            $latestRestore = BackupRestoration::query()->where('engine', 'pgsql')->whereNotNull('verified_at')->latest('verified_at')->latest('id')->first();
            $restoreFresh = $restoreEnabled && $latestRestore?->status === 'healthy' && $latestRestore?->verified_at?->greaterThanOrEqualTo(now()->subDays((int) config('backup.postgres.restore_max_age_days', 8)));
            $checks[] = [
                'database-restore',
                $restoreFresh ? 'healthy' : 'failed',
                $restoreFresh ? 'A recent isolated PostgreSQL restoration succeeded.' : ($restoreEnabled ? 'No recent isolated PostgreSQL restoration is verified.' : 'Isolated PostgreSQL restoration verification is disabled.'),
                ['enabled' => $restoreEnabled, 'latest_restore_at' => $latestRestore?->verified_at?->toIso8601String(), 'rpo_seconds' => $latestRestore?->rpo_seconds, 'rto_seconds' => $latestRestore?->rto_seconds],
            ];
        }
        $checks = [...$checks, ...$this->areviaChecks()];

        foreach ($checks as [$component, $status, $message, $metrics]) {
            $previousStatus = SystemHealthCheck::query()->where('component', $component)->latest('checked_at')->value('status');
            SystemHealthCheck::create(compact('component', 'status', 'message', 'metrics') + ['checked_at' => now()]);
            if ($status === 'failed' && $previousStatus !== 'failed') {
                $events->record('system_component_failed', null, compact('component', 'message', 'metrics'), 'critical', true);
            } elseif ($status === 'healthy' && $previousStatus === 'failed') {
                $events->record('system_component_recovered', null, compact('component', 'message', 'metrics'));
            }
        }
        $this->info(count($checks).' system components checked.');

        return collect($checks)->contains(fn ($check) => $check[1] === 'failed') ? self::FAILURE : self::SUCCESS;
    }

    private function areviaChecks(): array
    {
        $checks = [];
        $textProvider = config('ai.default');
        $textConfiguration = config("ai.providers.{$textProvider}", []);
        $textConfigured = ($textConfiguration['driver'] ?? null) === 'ollama'
            ? filled($textConfiguration['url'] ?? null)
            : filled($textConfiguration['key'] ?? null);
        $checks[] = [
            'arevia:text-provider',
            $textConfigured ? 'healthy' : 'warning',
            $textConfigured ? "Text provider {$textProvider} is configured." : "Text provider {$textProvider} is not configured.",
            ['provider' => $textProvider],
        ];

        $embeddingProvider = config('ai.default_for_embeddings');
        $embeddingConfiguration = config("ai.providers.{$embeddingProvider}", []);
        $embeddingStatus = 'warning';
        $embeddingMessage = "Embedding provider {$embeddingProvider} is not configured.";
        $embeddingMetrics = ['provider' => $embeddingProvider];
        if (($embeddingConfiguration['driver'] ?? null) === 'ollama' && filled($embeddingConfiguration['url'] ?? null)) {
            try {
                $started = hrtime(true);
                $response = Http::acceptJson()
                    ->timeout(config('arevia.health.provider_timeout_seconds', 2))
                    ->get(rtrim($embeddingConfiguration['url'], '/').'/api/tags');
                $embeddingMetrics['latency_ms'] = (int) ((hrtime(true) - $started) / 1_000_000);
                $embeddingStatus = $response->successful() ? 'healthy' : 'failed';
                $embeddingMessage = $response->successful()
                    ? "Embedding provider {$embeddingProvider} is reachable."
                    : "Embedding provider {$embeddingProvider} returned HTTP {$response->status()}.";
            } catch (\Throwable $exception) {
                $embeddingStatus = 'failed';
                $embeddingMessage = "Embedding provider {$embeddingProvider} is unreachable.";
                $embeddingMetrics['error'] = mb_substr($exception->getMessage(), 0, 500);
            }
        } elseif (filled($embeddingConfiguration['key'] ?? null)) {
            $embeddingStatus = 'healthy';
            $embeddingMessage = "Embedding provider {$embeddingProvider} is configured.";
        }
        $checks[] = ['arevia:embeddings', $embeddingStatus, $embeddingMessage, $embeddingMetrics];

        if (Schema::hasTable('knowledge_documents')) {
            $knowledge = KnowledgeDocument::query()
                ->selectRaw("sum(case when status = 'pending' then 1 else 0 end) as pending_count")
                ->selectRaw("sum(case when status = 'failed' then 1 else 0 end) as failed_count")
                ->selectRaw("sum(case when status = 'quarantined' then 1 else 0 end) as quarantined_count")
                ->selectRaw('sum(case when stale_at is not null and stale_at <= ? then 1 else 0 end) as stale_count', [now()])
                ->first();
            $metrics = [
                'pending' => (int) $knowledge->pending_count,
                'failed' => (int) $knowledge->failed_count,
                'quarantined' => (int) $knowledge->quarantined_count,
                'stale' => (int) $knowledge->stale_count,
            ];
            $needsAttention = $metrics['pending'] + $metrics['failed'] + $metrics['quarantined'] + $metrics['stale'];
            $checks[] = [
                'arevia:knowledge',
                $needsAttention ? 'warning' : 'healthy',
                $needsAttention ? "{$needsAttention} knowledge documents require attention." : 'Knowledge index is current.',
                $metrics,
            ];
        }

        $stuckBefore = now()->subMinutes(config('arevia.health.stuck_job_minutes', 15));
        $stuckMessages = Schema::hasTable('ai_messages')
            ? DB::table('ai_messages')->whereIn('status', ['queued', 'processing'])->where('created_at', '<=', $stuckBefore)->count()
            : 0;
        $stuckBriefings = Schema::hasTable('ai_generations')
            ? DB::table('ai_generations')->whereIn('status', ['queued', 'processing'])->where('created_at', '<=', $stuckBefore)->count()
            : 0;
        $stuck = $stuckMessages + $stuckBriefings;
        $checks[] = [
            'arevia:jobs',
            $stuck ? 'failed' : 'healthy',
            $stuck ? "{$stuck} Arevia jobs are stuck." : 'Arevia jobs are processing normally.',
            ['stuck_messages' => $stuckMessages, 'stuck_briefings' => $stuckBriefings],
        ];

        return $checks;
    }
}

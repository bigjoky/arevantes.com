<?php

namespace App\Console\Commands;

use App\Models\BackupRestoration;
use App\Models\DatabaseBackup;
use App\Models\SystemHealthCheck;
use App\Services\Deye\DeyeCloudClient;
use App\Services\Security\SecurityEventRecorder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
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
        $checks[] = ['queue', $failed ? 'failed' : 'healthy', $failed ? "{$failed} failed jobs require attention." : 'Queue has no failed jobs.', ['pending' => $pending, 'failed' => $failed]];
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
            $latestRestore = BackupRestoration::query()->where('engine', 'pgsql')->where('status', 'healthy')->latest('verified_at')->first();
            $restoreFresh = $restoreEnabled && $latestRestore?->verified_at?->greaterThanOrEqualTo(now()->subDays((int) config('backup.postgres.restore_max_age_days', 8)));
            $checks[] = [
                'database-restore',
                $restoreFresh ? 'healthy' : 'failed',
                $restoreFresh ? 'A recent isolated PostgreSQL restoration succeeded.' : ($restoreEnabled ? 'No recent isolated PostgreSQL restoration is verified.' : 'Isolated PostgreSQL restoration verification is disabled.'),
                ['enabled' => $restoreEnabled, 'latest_restore_at' => $latestRestore?->verified_at?->toIso8601String(), 'rpo_seconds' => $latestRestore?->rpo_seconds, 'rto_seconds' => $latestRestore?->rto_seconds],
            ];
        }

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
}

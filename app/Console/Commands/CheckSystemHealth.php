<?php

namespace App\Console\Commands;

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

        foreach ($checks as [$component, $status, $message, $metrics]) {
            SystemHealthCheck::create(compact('component', 'status', 'message', 'metrics') + ['checked_at' => now()]);
            if ($status === 'failed') {
                $events->record('system_component_failed', null, compact('component', 'message', 'metrics'), 'critical', true);
            }
        }
        $this->info(count($checks).' system components checked.');

        return collect($checks)->contains(fn ($check) => $check[1] === 'failed') ? self::FAILURE : self::SUCCESS;
    }
}

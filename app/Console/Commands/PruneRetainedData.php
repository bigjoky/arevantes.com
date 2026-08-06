<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\SecurityEvent;
use App\Models\SystemHealthCheck;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PruneRetainedData extends Command
{
    protected $signature = 'data:prune-retention {--dry-run : Report without deleting}';

    protected $description = 'Apply the documented operational-data retention policy';

    public function handle(): int
    {
        $targets = [
            'audit logs' => [AuditLog::where('created_at', '<', now()->subYears((int) config('security.retention.audit_years', 7))), null],
            'security events' => [SecurityEvent::where('created_at', '<', now()->subYears((int) config('security.retention.security_event_years', 2))), null],
            'health checks' => [SystemHealthCheck::where('checked_at', '<', now()->subDays((int) config('security.retention.health_days', 90))), null],
        ];
        foreach ($targets as $label => [$query]) {
            $count = (clone $query)->count();
            $this->line("{$label}: {$count}");
            if (! $this->option('dry-run')) {
                $query->delete();
            }
        }
        if (! $this->option('dry-run') && DB::getDriverName() === 'sqlite') {
            DB::statement('PRAGMA optimize');
        }

        return self::SUCCESS;
    }
}

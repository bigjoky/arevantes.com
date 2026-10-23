<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\MobileOperation;
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
            'mobile operations' => [MobileOperation::where('created_at', '<', now()->subDays((int) config('security.retention.mobile_operation_days', 30))), null],
        ];
        foreach ($targets as $label => [$query]) {
            $count = (clone $query)->count();
            $this->line("{$label}: {$count}");
            if (! $this->option('dry-run')) {
                $query->delete();
            }
        }
        $locations = DB::table('commercial_visits')->whereNotNull('prospect_type')
            ->where('visited_at', '<', now()->subDays(max(1, (int) config('commercial_field.location_retention_days'))));
        $this->line('commercial field locations: '.(clone $locations)->whereNotNull('latitude')->count());
        $visits = DB::table('commercial_visits')->whereNotNull('prospect_type')
            ->where('visited_at', '<', now()->subDays(max(1, (int) config('commercial_field.activity_retention_days'))));
        $sessions = DB::table('commercial_field_sessions')->where('started_at', '<', now()->utc()->subDays(max(1, (int) config('commercial_field.activity_retention_days'))));
        $this->line('commercial field visits: '.(clone $visits)->count());
        $this->line('commercial field sessions: '.(clone $sessions)->count());
        if (! $this->option('dry-run')) {
            $locations->update(['latitude' => null, 'longitude' => null, 'accuracy_meters' => null]);
            $visits->delete();
            $sessions->delete();
        }
        if (! $this->option('dry-run') && DB::getDriverName() === 'sqlite') {
            DB::statement('PRAGMA optimize');
        }

        return self::SUCCESS;
    }
}

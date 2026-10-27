<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $fields = [
        'work_orders' => ['scheduled_start', 'scheduled_end'],
        'time_entries' => ['started_at'],
        'opportunities' => ['created_at', 'first_response_due_at'],
        'crm_activities' => ['starts_at', 'due_at'],
        'portfolio_projects' => ['published_at'],
        'work_order_attachments' => ['published_at'],
    ];

    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }
        // Deploy in the documented maintenance window. Do not acquire table locks in a live rolling release.
        foreach ($this->fields as $table => $fields) {
            foreach ($fields as $field) {
                $wall = '"'.$field.'"';
                $utc = '"'.$field.'_utc"';
                $base = "($wall AT TIME ZONE 'Europe/Madrid')";
                $alternative = "((($base AT TIME ZONE 'UTC') - interval '1 hour') AT TIME ZONE 'UTC')";
                $latest = in_array($field, ['scheduled_end', 'first_response_due_at', 'due_at', 'published_at'], true);
                $legacy = $latest ? $base : "CASE WHEN ($alternative AT TIME ZONE 'Europe/Madrid') = $wall THEN $alternative ELSE $base END";
                $legacy = "CASE WHEN ($base AT TIME ZONE 'Europe/Madrid') = $wall THEN ($legacy) ELSE NULL END";
                $expression = "EXTRACT(EPOCH FROM (COALESCE($utc, ($legacy)) AT TIME ZONE 'UTC'))";
                DB::statement("CREATE INDEX {$table}_{$field}_instant_idx ON $table (($expression))");
            }
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }
        foreach ($this->fields as $table => $fields) {
            foreach ($fields as $field) {
                DB::statement("DROP INDEX IF EXISTS {$table}_{$field}_instant_idx");
            }
        }
    }
};

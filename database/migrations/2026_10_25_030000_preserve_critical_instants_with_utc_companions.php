<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Additive: no conversion/backfill of historical local timestamps.
    private array $fields = [
        'document_deliveries' => ['expires_at', 'sent_at', 'first_viewed_at', 'last_downloaded_at', 'responded_at', 'revoked_at'],
        'customer_portal_accesses' => ['expires_at', 'sent_at', 'first_accessed_at', 'last_accessed_at', 'revoked_at'],
        'work_order_attachments' => ['published_at', 'reviewed_at'],
        'portfolio_projects' => ['published_at'],
        'planning_scenarios' => ['reference_valid_until', 'approved_at', 'closed_at'],
        'opportunities' => ['created_at', 'first_response_due_at', 'first_responded_at', 'first_response_sla_alerted_at', 'first_response_sla_escalated_at'],
        'work_orders' => ['scheduled_start', 'scheduled_end', 'signed_at', 'completed_at'],
        'time_entries' => ['started_at', 'ended_at'],
        'crm_activities' => ['starts_at', 'due_at', 'completed_at'],
    ];

    public function up(): void
    {
        foreach ($this->fields as $table => $fields) {
            Schema::table($table, function (Blueprint $blueprint) use ($fields) {
                foreach ($fields as $field) {
                    $blueprint->timestampTz($field.'_utc', 6)->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            if (DB::transactionLevel() === 0) {
                throw new RuntimeException('UTC rollback inspection requires a transaction and exclusive table locks.');
            }
            foreach (array_keys($this->fields) as $table) {
                DB::statement("LOCK TABLE $table IN ACCESS EXCLUSIVE MODE");
            }
        }
        // Never discard newly captured temporal evidence through a rollback.
        foreach ($this->fields as $table => $fields) {
            foreach ($fields as $field) {
                if (DB::table($table)->whereNotNull($field.'_utc')->exists()) {
                    throw new RuntimeException('UTC evidence exists; rollback must preserve companion columns.');
                }
            }
        }
        foreach ($this->fields as $table => $fields) {
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropColumn(array_map(fn ($field) => $field.'_utc', $fields)));
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // Frozen mapping, independent of future model casts. No backfill or historical update.
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
        'work_order_reschedules' => ['old_scheduled_start', 'old_scheduled_end', 'new_scheduled_start', 'new_scheduled_end'],
    ];

    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }
        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION arevantes_guard_instants() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE
    field text;
    wall text;
    canonical text;
    previous jsonb;
    current_row jsonb := to_jsonb(NEW);
BEGIN
    IF TG_OP = 'UPDATE' THEN previous := to_jsonb(OLD); END IF;
    FOREACH field IN ARRAY TG_ARGV LOOP
        wall := current_row ->> field;
        canonical := current_row ->> (field || '_utc');
        -- Inspect changed pairs only: do not reinterpret unrelated historical data.
        IF TG_OP = 'UPDATE' AND current_row -> field IS NOT DISTINCT FROM previous -> field
            AND current_row -> (field || '_utc') IS NOT DISTINCT FROM previous -> (field || '_utc') THEN
            CONTINUE;
        END IF;
        IF canonical IS NULL THEN
            IF TG_OP = 'UPDATE' AND previous ->> (field || '_utc') IS NOT NULL AND wall IS NOT NULL THEN
                RAISE EXCEPTION 'Unsynchronized temporal write: %.%', TG_TABLE_NAME, field USING ERRCODE = '23514';
            END IF;
        ELSIF wall IS NULL THEN
            RAISE EXCEPTION 'Missing original temporal value: %.%', TG_TABLE_NAME, field USING ERRCODE = '23514';
        ELSIF TG_TABLE_NAME = 'planning_scenarios' AND field IN ('reference_valid_until', 'closed_at') THEN
            IF wall::timestamptz IS DISTINCT FROM canonical::timestamptz THEN
                RAISE EXCEPTION 'Temporal pair mismatch: %.%', TG_TABLE_NAME, field USING ERRCODE = '23514';
            END IF;
        ELSIF wall::timestamp IS DISTINCT FROM (canonical::timestamptz AT TIME ZONE 'Europe/Madrid') THEN
            RAISE EXCEPTION 'Temporal pair mismatch: %.%', TG_TABLE_NAME, field USING ERRCODE = '23514';
        END IF;
    END LOOP;
    RETURN NEW;
END $$;
SQL);
        foreach ($this->fields as $table => $fields) {
            $args = implode(', ', array_map(fn ($field) => "'$field'", $fields));
            DB::unprepared("CREATE TRIGGER precise_instants_guard BEFORE INSERT OR UPDATE ON $table FOR EACH ROW EXECUTE FUNCTION arevantes_guard_instants($args)");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }
        // Guard removal never removes evidence columns or converts data.
        foreach (array_keys($this->fields) as $table) {
            DB::unprepared("DROP TRIGGER IF EXISTS precise_instants_guard ON $table");
        }
        DB::unprepared('DROP FUNCTION IF EXISTS arevantes_guard_instants()');
    }
};

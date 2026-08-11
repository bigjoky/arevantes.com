<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_orders', function (Blueprint $table): void {
            $table->index('installation_id', 'work_orders_installation_idx');
        });
        Schema::table('time_entries', function (Blueprint $table): void {
            $table->index('work_order_id', 'time_entries_work_order_idx');
        });
        Schema::table('work_order_checklist_items', function (Blueprint $table): void {
            $table->index('work_order_id', 'work_order_checklist_order_idx');
        });
        Schema::table('work_order_attachments', function (Blueprint $table): void {
            $table->index('work_order_id', 'work_order_attachments_order_idx');
        });
        Schema::table('work_order_reschedules', function (Blueprint $table): void {
            $table->index('work_order_id', 'work_order_reschedules_order_idx');
        });
        Schema::table('installation_assets', function (Blueprint $table): void {
            $table->index('product_id', 'installation_assets_product_idx');
            $table->index('source_purchase_document_id', 'installation_assets_purchase_idx');
            $table->index('replaced_by_asset_id', 'installation_assets_replacement_idx');
        });
        Schema::table('asset_events', function (Blueprint $table): void {
            $table->index('work_order_id', 'asset_events_work_order_idx');
            $table->index('service_ticket_id', 'asset_events_service_ticket_idx');
        });
        Schema::table('service_tickets', function (Blueprint $table): void {
            $table->index('maintenance_plan_id', 'service_tickets_maintenance_plan_idx');
            $table->index('assigned_user_id', 'service_tickets_assignee_idx');
            $table->index('work_order_id', 'service_tickets_work_order_idx');
            $table->index('sla_policy_id', 'service_tickets_sla_policy_idx');
        });
        Schema::table('maintenance_plans', function (Blueprint $table): void {
            $table->index('work_order_template_id', 'maintenance_plans_template_idx');
            $table->index('assigned_user_id', 'maintenance_plans_assignee_idx');
            $table->index(['is_active', 'next_due_on'], 'maintenance_plans_active_due_idx');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("CREATE INDEX service_tickets_open_resolution_due_idx ON service_tickets (resolution_due_at) WHERE status NOT IN ('resolved', 'closed')");
            DB::statement("CREATE INDEX service_tickets_open_response_due_idx ON service_tickets (first_response_due_at) WHERE status NOT IN ('resolved', 'closed') AND first_responded_at IS NULL");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS service_tickets_open_resolution_due_idx');
            DB::statement('DROP INDEX IF EXISTS service_tickets_open_response_due_idx');
        }

        Schema::table('maintenance_plans', function (Blueprint $table): void {
            $table->dropIndex('maintenance_plans_active_due_idx');
            $table->dropIndex('maintenance_plans_assignee_idx');
            $table->dropIndex('maintenance_plans_template_idx');
        });
        Schema::table('service_tickets', function (Blueprint $table): void {
            $table->dropIndex('service_tickets_sla_policy_idx');
            $table->dropIndex('service_tickets_work_order_idx');
            $table->dropIndex('service_tickets_assignee_idx');
            $table->dropIndex('service_tickets_maintenance_plan_idx');
        });
        Schema::table('asset_events', function (Blueprint $table): void {
            $table->dropIndex('asset_events_service_ticket_idx');
            $table->dropIndex('asset_events_work_order_idx');
        });
        Schema::table('installation_assets', function (Blueprint $table): void {
            $table->dropIndex('installation_assets_replacement_idx');
            $table->dropIndex('installation_assets_purchase_idx');
            $table->dropIndex('installation_assets_product_idx');
        });
        Schema::table('work_order_reschedules', fn (Blueprint $table) => $table->dropIndex('work_order_reschedules_order_idx'));
        Schema::table('work_order_attachments', fn (Blueprint $table) => $table->dropIndex('work_order_attachments_order_idx'));
        Schema::table('work_order_checklist_items', fn (Blueprint $table) => $table->dropIndex('work_order_checklist_order_idx'));
        Schema::table('time_entries', fn (Blueprint $table) => $table->dropIndex('time_entries_work_order_idx'));
        Schema::table('work_orders', fn (Blueprint $table) => $table->dropIndex('work_orders_installation_idx'));
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasIndex('financial_forecast_items', 'financial_forecast_items_maintenance_plan_id_index')) {
            Schema::table('financial_forecast_items', function (Blueprint $table): void {
                $table->index('maintenance_plan_id');
            });
        }

        if (Schema::hasIndex('financial_forecast_items', 'financial_forecast_items_maintenance_plan_id_unique')) {
            Schema::table('financial_forecast_items', function (Blueprint $table): void {
                $table->dropUnique(['maintenance_plan_id']);
            });
        }

        if (! Schema::hasColumn('financial_forecast_items', 'maintenance_plan_version')) {
            Schema::table('financial_forecast_items', function (Blueprint $table): void {
                $table->unsignedInteger('maintenance_plan_version')->nullable()->after('maintenance_plan_id');
            });
        }

        if (! Schema::hasIndex('financial_forecast_items', 'financial_forecast_plan_version_due_unique')) {
            Schema::table('financial_forecast_items', function (Blueprint $table): void {
                $table->unique(
                    ['maintenance_plan_id', 'maintenance_plan_version', 'due_on'],
                    'financial_forecast_plan_version_due_unique'
                );
            });
        }

        DB::table('financial_forecast_items')
            ->whereNotNull('maintenance_plan_id')
            ->update([
                'maintenance_plan_version' => DB::raw('(select version from maintenance_plans where maintenance_plans.id = financial_forecast_items.maintenance_plan_id)'),
            ]);

        if (! Schema::hasTable('service_cycle_commitments')) {
            Schema::create('service_cycle_commitments', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('maintenance_plan_id')->constrained()->cascadeOnDelete();
                $table->unsignedInteger('maintenance_plan_version');
                $table->date('period_starts_on');
                $table->date('period_ends_on');
                $table->string('status', 20)->default('approved');
                $table->unsignedSmallInteger('planned_visits')->default(1);
                $table->unsignedInteger('planned_minutes');
                $table->decimal('planned_travel_km', 10, 2)->default(0);
                $table->decimal('planned_revenue', 14, 2)->default(0);
                $table->decimal('planned_labor_cost', 14, 2)->default(0);
                $table->decimal('planned_material_cost', 14, 2)->default(0);
                $table->decimal('planned_travel_cost', 14, 2)->default(0);
                $table->json('capacity_snapshot');
                $table->text('approval_note');
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('approved_at');
                $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('cancelled_at')->nullable();
                $table->text('cancellation_reason')->nullable();
                $table->timestamps();
                $table->unique(['maintenance_plan_id', 'maintenance_plan_version', 'period_starts_on'], 'service_cycle_plan_version_period_unique');
                $table->index(['status', 'period_starts_on', 'period_ends_on'], 'service_cycle_status_period_idx');
            });
        }

        if (! Schema::hasTable('replenishment_targets')) {
            Schema::create('replenishment_targets', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('product_id')->constrained()->restrictOnDelete();
                $table->date('period_starts_on');
                $table->date('period_ends_on');
                $table->string('status', 20)->default('approved');
                $table->decimal('target_quantity', 14, 3);
                $table->decimal('expected_quantity', 14, 3);
                $table->decimal('minimum_stock', 14, 3)->default(0);
                $table->decimal('free_stock', 14, 3)->default(0);
                $table->decimal('inbound_quantity', 14, 3)->default(0);
                $table->decimal('forecast_shortage', 14, 3)->default(0);
                $table->json('source_snapshot');
                $table->text('approval_note');
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('approved_at');
                $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('cancelled_at')->nullable();
                $table->text('cancellation_reason')->nullable();
                $table->timestamps();
                $table->unique(['product_id', 'period_starts_on', 'period_ends_on'], 'replenishment_target_product_period_unique');
                $table->index(['status', 'period_ends_on'], 'replenishment_target_status_period_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('replenishment_targets');
        Schema::dropIfExists('service_cycle_commitments');
        Schema::table('financial_forecast_items', function (Blueprint $table): void {
            $table->dropUnique('financial_forecast_plan_version_due_unique');
            $table->dropColumn('maintenance_plan_version');
            $table->unique('maintenance_plan_id');
        });
    }
};

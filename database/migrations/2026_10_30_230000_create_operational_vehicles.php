<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operational_vehicles', function (Blueprint $table) {
            $table->id();
            $table->string('registration', 40);
            $table->string('registration_key', 40)->unique();
            $table->string('name', 120);
            $table->string('make', 100)->nullable();
            $table->string('model', 100)->nullable();
            $table->string('type', 100)->nullable();
            $table->string('status', 30)->default('available');
            $table->string('base_location', 180)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
        Schema::create('operational_vehicle_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained('operational_vehicles')->restrictOnDelete();
            $table->timestampTz('starts_at_utc');
            $table->timestampTz('ends_at_utc');
            $table->string('reason', 1000);
            $table->timestampTz('cancelled_at_utc')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('cancellation_reason', 1000)->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['vehicle_id', 'starts_at_utc', 'ends_at_utc'], 'ops_vehicle_blocks_interval_idx');
        });
        Schema::table('work_orders', function (Blueprint $table) {
            $table->foreignId('vehicle_id')->nullable()->constrained('operational_vehicles')->restrictOnDelete();
            $table->boolean('vehicle_required')->default(false);
            $table->index(['vehicle_id', 'status'], 'ops_vehicle_order_status_idx');
        });
        Schema::table('crm_activities', function (Blueprint $table) {
            $table->foreignId('vehicle_id')->nullable()->constrained('operational_vehicles')->restrictOnDelete();
            $table->boolean('vehicle_required')->default(false);
            $table->index(['vehicle_id', 'status'], 'ops_vehicle_activity_status_idx');
        });
        Schema::create('operational_vehicle_assignment_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_id')->nullable()->constrained('work_orders')->restrictOnDelete();
            $table->foreignId('crm_activity_id')->nullable()->constrained('crm_activities')->restrictOnDelete();
            $table->foreignId('old_vehicle_id')->nullable()->constrained('operational_vehicles')->restrictOnDelete();
            $table->foreignId('new_vehicle_id')->nullable()->constrained('operational_vehicles')->restrictOnDelete();
            $table->foreignId('old_assignee_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('new_assignee_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('old_start_utc')->nullable();
            $table->timestampTz('old_end_utc')->nullable();
            $table->timestampTz('new_start_utc')->nullable();
            $table->timestampTz('new_end_utc')->nullable();
            $table->boolean('old_required');
            $table->boolean('new_required');
            $table->text('reason');
            $table->foreignId('changed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['work_order_id', 'id'], 'ops_vehicle_history_order_idx');
        });
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE operational_vehicle_assignment_history ADD CONSTRAINT ops_vehicle_history_single_parent CHECK ((work_order_id IS NULL) <> (crm_activity_id IS NULL))');
            foreach (['operational_vehicles', 'operational_vehicle_blocks', 'operational_vehicle_assignment_history'] as $table) {
                DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
                DB::statement("REVOKE ALL ON TABLE {$table} FROM PUBLIC");
                foreach (['anon', 'authenticated'] as $role) {
                    if (DB::selectOne('select 1 from pg_roles where rolname = ?', [$role])) {
                        DB::statement("REVOKE ALL ON TABLE {$table} FROM {$role}");
                    }
                }
            }
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Preserve operational vehicle and assignment history; use a reviewed forward migration.');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_cycle_commitments', function (Blueprint $table): void {
            $table->json('technical_snapshot')->nullable()->after('capacity_snapshot');
            $table->foreignId('service_ticket_id')->nullable()->unique()->after('technical_snapshot')->constrained('service_tickets')->nullOnDelete();
            $table->foreignId('work_order_id')->nullable()->unique()->after('service_ticket_id')->constrained('work_orders')->nullOnDelete();
            $table->string('schedule_status', 30)->default('unplanned')->after('work_order_id');
            $table->foreignId('materialized_by')->nullable()->after('schedule_status')->constrained('users')->nullOnDelete();
            $table->timestamp('materialized_at')->nullable()->after('materialized_by');
            $table->foreignId('schedule_confirmed_by')->nullable()->after('materialized_at')->constrained('users')->nullOnDelete();
            $table->timestamp('schedule_confirmed_at')->nullable()->after('schedule_confirmed_by');
            $table->index(['schedule_status', 'period_starts_on'], 'service_cycle_schedule_status_period_idx');
        });

        Schema::create('service_cycle_schedule_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('service_cycle_commitment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('work_order_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('version');
            $table->string('status', 30)->default('pending_confirmation');
            $table->timestampTz('scheduled_start');
            $table->timestampTz('scheduled_end');
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('superseded_at')->nullable();
            $table->timestamps();
            $table->unique(['service_cycle_commitment_id', 'version'], 'service_cycle_schedule_revision_unique');
            $table->index(['service_cycle_commitment_id', 'status'], 'service_cycle_schedule_revision_status_idx');
        });

        Schema::table('service_cycle_communications', function (Blueprint $table): void {
            $table->dropUnique('service_cycle_communication_unique');
            $table->unsignedInteger('revision')->default(1)->after('type');
            $table->foreignId('supersedes_communication_id')->nullable()->after('revision')->constrained('service_cycle_communications')->nullOnDelete();
            $table->timestamp('superseded_at')->nullable()->after('delivery_note');
            $table->unique(['service_cycle_commitment_id', 'type', 'revision'], 'service_cycle_communication_revision_unique');
            $table->index(['service_cycle_commitment_id', 'type', 'superseded_at'], 'service_cycle_communication_current_idx');
        });
    }

    public function down(): void
    {
        Schema::table('service_cycle_communications', function (Blueprint $table): void {
            $table->dropIndex('service_cycle_communication_current_idx');
            $table->dropUnique('service_cycle_communication_revision_unique');
            $table->dropForeign(['supersedes_communication_id']);
            $table->dropColumn(['revision', 'supersedes_communication_id', 'superseded_at']);
            $table->unique(['service_cycle_commitment_id', 'type'], 'service_cycle_communication_unique');
        });
        Schema::dropIfExists('service_cycle_schedule_revisions');
        Schema::table('service_cycle_commitments', function (Blueprint $table): void {
            $table->dropIndex('service_cycle_schedule_status_period_idx');
            $table->dropConstrainedForeignId('schedule_confirmed_by');
            $table->dropConstrainedForeignId('materialized_by');
            $table->dropConstrainedForeignId('work_order_id');
            $table->dropConstrainedForeignId('service_ticket_id');
            $table->dropColumn(['technical_snapshot', 'schedule_status', 'materialized_at', 'schedule_confirmed_at']);
        });
    }
};

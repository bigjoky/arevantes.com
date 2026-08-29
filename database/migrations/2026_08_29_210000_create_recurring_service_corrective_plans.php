<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_corrective_plans', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 30)->nullable()->unique();
            $table->foreignId('maintenance_plan_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('root_cause_category', 40);
            $table->text('root_cause_analysis');
            $table->string('status', 24)->default('draft');
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('target_date');
            $table->unsignedTinyInteger('verification_cycles')->default(2);
            $table->json('baseline_snapshot');
            $table->json('effectiveness_snapshot')->nullable();
            $table->string('effectiveness_result', 20)->nullable();
            $table->text('effectiveness_note')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('verification_started_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['status', 'target_date'], 'service_corrective_plan_status_due_idx');
            $table->index(['maintenance_plan_id', 'status'], 'service_corrective_plan_maintenance_idx');
        });

        Schema::create('service_corrective_plan_deviation', function (Blueprint $table): void {
            $table->foreignId('service_corrective_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_cycle_deviation_id')->constrained()->cascadeOnDelete();
            $table->primary(['service_corrective_plan_id', 'service_cycle_deviation_id'], 'service_corrective_plan_deviation_primary');
        });

        Schema::create('service_corrective_actions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('service_corrective_plan_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->string('title');
            $table->text('details')->nullable();
            $table->string('status', 20)->default('open');
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('due_on');
            $table->text('completion_note')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['service_corrective_plan_id', 'status', 'due_on'], 'service_corrective_action_status_due_idx');
        });

        Schema::create('service_corrective_proposals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('service_corrective_plan_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30);
            $table->string('title');
            $table->text('rationale');
            $table->json('payload')->nullable();
            $table->string('status', 20)->default('prepared');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['type', 'status'], 'service_corrective_proposal_type_status_idx');
        });

        Schema::create('service_corrective_plan_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('service_corrective_plan_id')->constrained()->cascadeOnDelete();
            $table->string('event', 30);
            $table->string('from_status', 24)->nullable();
            $table->string('to_status', 24)->nullable();
            $table->text('note')->nullable();
            $table->json('changes')->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at');
            $table->index(['service_corrective_plan_id', 'created_at'], 'service_corrective_plan_event_history_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_corrective_plan_events');
        Schema::dropIfExists('service_corrective_proposals');
        Schema::dropIfExists('service_corrective_actions');
        Schema::dropIfExists('service_corrective_plan_deviation');
        Schema::dropIfExists('service_corrective_plans');
    }
};

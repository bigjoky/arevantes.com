<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planning_calibration_evaluations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('planning_calibration_proposal_id')->constrained()->restrictOnDelete();
            $table->foreignId('planning_scenario_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->string('status', 20)->default('pending');
            $table->string('recommendation', 20);
            $table->decimal('origin_delta_percent', 10, 3);
            $table->decimal('observed_delta_percent', 10, 3);
            $table->decimal('improvement_points', 10, 3);
            $table->json('evidence_snapshot');
            $table->char('fingerprint', 64)->unique();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('decision', 20)->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('decided_at')->nullable();
            $table->text('decision_note')->nullable();
            $table->timestampsTz();

            $table->unique('planning_scenario_id', 'planning_calibration_evaluations_scenario_unique');
            $table->unique(['planning_calibration_proposal_id', 'version'], 'planning_calibration_evaluations_version_unique');
            $table->index(['status', 'recommendation'], 'planning_calibration_evaluations_queue_index');
            $table->index('decided_by', 'planning_calibration_evaluations_decider_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planning_calibration_evaluations');
    }
};

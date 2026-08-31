<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planning_calibration_proposals', function (Blueprint $table): void {
            $table->id();
            $table->string('business_line', 40);
            $table->string('area', 30);
            $table->string('metric_label', 120);
            $table->string('metric_key', 64);
            $table->string('unit', 30);
            $table->unsignedInteger('version');
            $table->string('status', 20)->default('pending');
            $table->string('direction', 20);
            $table->unsignedSmallInteger('sample_size');
            $table->json('evidence_snapshot');
            $table->char('source_fingerprint', 64)->unique();
            $table->text('recommendation');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('decided_at')->nullable();
            $table->text('decision_note')->nullable();
            $table->timestampsTz();

            $table->unique(['business_line', 'metric_key', 'version'], 'planning_calibrations_version_unique');
            $table->index(['status', 'business_line'], 'planning_calibrations_queue_index');
            $table->index('decided_by', 'planning_calibrations_decider_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planning_calibration_proposals');
    }
};

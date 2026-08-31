<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('planning_calibration_proposals', function (Blueprint $table): void {
            $table->string('governance_state', 20)->default('available')->after('status');
            $table->foreignId('superseded_by_id')->nullable()->after('decision_note')->constrained('planning_calibration_proposals')->nullOnDelete();
            $table->index(['business_line', 'metric_key', 'governance_state'], 'planning_calibrations_governance_index');
        });

        Schema::create('planning_calibration_governance_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('planning_calibration_proposal_id')->constrained(indexName: 'pcg_reviews_proposal_fk')->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->string('status', 20)->default('pending');
            $table->string('recommendation', 20);
            $table->unsignedSmallInteger('sample_size');
            $table->unsignedSmallInteger('retain_count');
            $table->unsignedSmallInteger('revise_count');
            $table->unsignedSmallInteger('retire_count');
            $table->decimal('consistency', 5, 3);
            $table->json('evidence_snapshot');
            $table->char('fingerprint', 64)->unique();
            $table->foreignId('created_by')->constrained('users', indexName: 'pcg_reviews_creator_fk')->restrictOnDelete();
            $table->string('decision', 20)->nullable();
            $table->foreignId('replacement_proposal_id')->nullable()->constrained('planning_calibration_proposals', indexName: 'pcg_reviews_replacement_fk')->nullOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users', indexName: 'pcg_reviews_decider_fk')->nullOnDelete();
            $table->timestampTz('decided_at')->nullable();
            $table->text('decision_note')->nullable();
            $table->timestampsTz();

            $table->unique(['planning_calibration_proposal_id', 'version'], 'planning_calibration_governance_version_unique');
            $table->index(['status', 'recommendation'], 'planning_calibration_governance_queue_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planning_calibration_governance_reviews');

        Schema::table('planning_calibration_proposals', function (Blueprint $table): void {
            $table->dropIndex('planning_calibrations_governance_index');
            $table->dropConstrainedForeignId('superseded_by_id');
            $table->dropColumn('governance_state');
        });
    }
};

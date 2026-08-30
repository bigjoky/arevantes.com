<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planning_scenario_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('planning_scenario_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('sequence');
            $table->json('outcome_snapshot');
            $table->char('fingerprint', 64);
            $table->text('explanation');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestampTz('reviewed_at');
            $table->timestampsTz();

            $table->unique(['planning_scenario_id', 'sequence'], 'planning_scenario_reviews_sequence_unique');
            $table->index(['planning_scenario_id', 'reviewed_at'], 'planning_scenario_reviews_timeline_index');
            $table->index('created_by', 'planning_scenario_reviews_created_by_index');
        });

        Schema::table('planning_scenarios', function (Blueprint $table): void {
            $table->json('closure_snapshot')->nullable()->after('approval_note');
            $table->char('closure_fingerprint', 64)->nullable()->after('closure_snapshot');
            $table->text('closure_note')->nullable()->after('closure_fingerprint');
            $table->foreignId('closed_by')->nullable()->after('closure_note')->constrained('users')->nullOnDelete();
            $table->timestampTz('closed_at')->nullable()->after('closed_by');

            $table->index(['status', 'closed_at'], 'planning_scenarios_closure_queue_index');
            $table->index('closed_by', 'planning_scenarios_closed_by_index');
        });
    }

    public function down(): void
    {
        Schema::table('planning_scenarios', function (Blueprint $table): void {
            $table->dropIndex('planning_scenarios_closure_queue_index');
            $table->dropIndex('planning_scenarios_closed_by_index');
            $table->dropConstrainedForeignId('closed_by');
            $table->dropColumn(['closure_snapshot', 'closure_fingerprint', 'closure_note', 'closed_at']);
        });

        Schema::dropIfExists('planning_scenario_reviews');
    }
};

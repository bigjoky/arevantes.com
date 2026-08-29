<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('planning_scenarios', function (Blueprint $table): void {
            $table->string('scenario_kind')->default('base')->after('status')->index();
            $table->foreignId('baseline_scenario_id')->nullable()->after('scenario_kind')->constrained('planning_scenarios')->restrictOnDelete();
            $table->json('reference_snapshot')->nullable()->after('result_snapshot');
            $table->json('comparison_snapshot')->nullable()->after('reference_snapshot');

            $table->index('baseline_scenario_id', 'planning_scenarios_baseline_index');
            $table->index('created_by', 'planning_scenarios_created_by_index');
            $table->index('approved_by', 'planning_scenarios_approved_by_index');
        });
    }

    public function down(): void
    {
        Schema::table('planning_scenarios', function (Blueprint $table): void {
            $table->dropForeign(['baseline_scenario_id']);
            $table->dropIndex('planning_scenarios_baseline_index');
            $table->dropIndex('planning_scenarios_created_by_index');
            $table->dropIndex('planning_scenarios_approved_by_index');
            $table->dropIndex(['scenario_kind']);
            $table->dropColumn(['scenario_kind', 'baseline_scenario_id', 'reference_snapshot', 'comparison_snapshot']);
        });
    }
};

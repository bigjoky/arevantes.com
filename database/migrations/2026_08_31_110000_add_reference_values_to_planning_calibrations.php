<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('planning_calibration_proposals', function (Blueprint $table): void {
            $table->string('assumption_key', 80)->nullable()->after('recommendation');
            $table->decimal('accepted_value', 12, 3)->nullable()->after('assumption_key');
            $table->index(['status', 'assumption_key'], 'planning_calibrations_reference_index');
        });

        DB::table('planning_calibration_proposals')->where('area', 'operations')->update(['assumption_key' => 'capacity_hours_per_opportunity']);
        DB::table('planning_calibration_proposals')->whereIn('area', ['inventory', 'purchases'])->update(['assumption_key' => 'material_cost_percent_without_quote']);
        DB::table('planning_calibration_proposals')->where('area', 'finance')->update(['assumption_key' => 'collection_delay_days']);
    }

    public function down(): void
    {
        Schema::table('planning_calibration_proposals', function (Blueprint $table): void {
            $table->dropIndex('planning_calibrations_reference_index');
            $table->dropColumn(['assumption_key', 'accepted_value']);
        });
    }
};

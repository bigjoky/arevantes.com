<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('planning_scenario_requests', function (Blueprint $table): void {
            $table->enum('execution_status', ['pending', 'executed'])->default('pending')->after('authorization_note');
            $table->json('execution_result')->nullable()->after('execution_status');
            $table->char('execution_fingerprint', 64)->nullable()->after('execution_result');
            $table->foreignId('executed_by')->nullable()->after('execution_fingerprint')->constrained('users')->nullOnDelete();
            $table->timestampTz('executed_at')->nullable()->after('executed_by');

            $table->index(['area', 'execution_status'], 'planning_scenario_requests_execution_queue_index');
            $table->index('executed_by', 'planning_scenario_requests_executed_by_index');
        });
    }

    public function down(): void
    {
        Schema::table('planning_scenario_requests', function (Blueprint $table): void {
            $table->dropForeign(['executed_by']);
            $table->dropIndex('planning_scenario_requests_execution_queue_index');
            $table->dropIndex('planning_scenario_requests_executed_by_index');
            $table->dropColumn(['execution_status', 'execution_result', 'execution_fingerprint', 'executed_by', 'executed_at']);
        });
    }
};

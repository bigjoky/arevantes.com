<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('planning_scenario_requests', function (Blueprint $table): void {
            $table->enum('promotion_status', ['pending', 'promoted'])->default('pending')->after('executed_at');
            $table->json('promotion_result')->nullable()->after('promotion_status');
            $table->char('promotion_fingerprint', 64)->nullable()->after('promotion_result');
            $table->foreignId('promoted_by')->nullable()->after('promotion_fingerprint')->constrained('users')->nullOnDelete();
            $table->timestampTz('promoted_at')->nullable()->after('promoted_by');
            $table->index(['area', 'promotion_status'], 'planning_scenario_requests_promotion_queue_index');
            $table->index('promoted_by', 'planning_scenario_requests_promoted_by_index');
        });

        foreach (['crm_activities', 'stock_reservations', 'purchase_documents', 'financial_forecast_items'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->foreignId('planning_scenario_request_id')->nullable()->index()->constrained('planning_scenario_requests')->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (['financial_forecast_items', 'purchase_documents', 'stock_reservations', 'crm_activities'] as $tableName) {
            Schema::table($tableName, fn (Blueprint $table) => $table->dropConstrainedForeignId('planning_scenario_request_id'));
        }

        Schema::table('planning_scenario_requests', function (Blueprint $table): void {
            $table->dropIndex('planning_scenario_requests_promotion_queue_index');
            $table->dropIndex('planning_scenario_requests_promoted_by_index');
            $table->dropConstrainedForeignId('promoted_by');
            $table->dropColumn(['promotion_status', 'promotion_result', 'promotion_fingerprint', 'promoted_at']);
        });
    }
};

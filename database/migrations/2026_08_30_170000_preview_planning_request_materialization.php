<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('planning_scenario_requests', function (Blueprint $table): void {
            $table->enum('materialization_status', ['pending', 'prepared', 'authorized'])->default('pending')->after('confirmation_note');
            $table->json('materialization_preview')->nullable()->after('materialization_status');
            $table->char('materialization_fingerprint', 64)->nullable()->after('materialization_preview');
            $table->uuid('idempotency_key')->nullable()->unique()->after('materialization_fingerprint');
            $table->foreignId('prepared_by')->nullable()->after('idempotency_key')->constrained('users')->nullOnDelete();
            $table->timestampTz('prepared_at')->nullable()->after('prepared_by');
            $table->foreignId('authorized_by')->nullable()->after('prepared_at')->constrained('users')->nullOnDelete();
            $table->timestampTz('authorized_at')->nullable()->after('authorized_by');
            $table->text('authorization_note')->nullable()->after('authorized_at');

            $table->index(['area', 'materialization_status'], 'planning_scenario_requests_materialization_queue_index');
            $table->index('prepared_by', 'planning_scenario_requests_prepared_by_index');
            $table->index('authorized_by', 'planning_scenario_requests_authorized_by_index');
        });
    }

    public function down(): void
    {
        Schema::table('planning_scenario_requests', function (Blueprint $table): void {
            $table->dropForeign(['prepared_by']);
            $table->dropForeign(['authorized_by']);
            $table->dropIndex('planning_scenario_requests_materialization_queue_index');
            $table->dropIndex('planning_scenario_requests_prepared_by_index');
            $table->dropIndex('planning_scenario_requests_authorized_by_index');
            $table->dropUnique(['idempotency_key']);
            $table->dropColumn([
                'materialization_status', 'materialization_preview', 'materialization_fingerprint', 'idempotency_key',
                'prepared_by', 'prepared_at', 'authorized_by', 'authorized_at', 'authorization_note',
            ]);
        });
    }
};

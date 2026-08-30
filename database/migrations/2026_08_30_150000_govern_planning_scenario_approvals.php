<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('planning_scenarios', function (Blueprint $table): void {
            $table->timestampTz('reference_valid_until')->nullable()->after('reference_snapshot');
            $table->char('approved_fingerprint', 64)->nullable()->after('approved_at');
            $table->text('approval_note')->nullable()->after('approved_fingerprint');

            $table->index(['status', 'reference_valid_until'], 'planning_scenarios_approval_queue_index');
        });
    }

    public function down(): void
    {
        Schema::table('planning_scenarios', function (Blueprint $table): void {
            $table->dropIndex('planning_scenarios_approval_queue_index');
            $table->dropColumn(['reference_valid_until', 'approved_fingerprint', 'approval_note']);
        });
    }
};

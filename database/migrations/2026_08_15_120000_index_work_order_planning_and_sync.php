<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_orders', function (Blueprint $table): void {
            $table->index(['status', 'scheduled_start', 'id'], 'work_orders_planning_idx');
            $table->index(['updated_at', 'id'], 'work_orders_mobile_sync_idx');
        });
    }

    public function down(): void
    {
        Schema::table('work_orders', function (Blueprint $table): void {
            $table->dropIndex('work_orders_planning_idx');
            $table->dropIndex('work_orders_mobile_sync_idx');
        });
    }
};

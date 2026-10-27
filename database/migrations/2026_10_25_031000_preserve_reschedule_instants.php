<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_order_reschedules', function (Blueprint $table) {
            foreach (['old_scheduled_start', 'old_scheduled_end', 'new_scheduled_start', 'new_scheduled_end'] as $field) {
                $table->timestampTz($field.'_utc', 6)->nullable();
            }
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            if (DB::transactionLevel() === 0) {
                throw new RuntimeException('UTC rollback inspection requires a transaction and exclusive table locks.');
            }
            DB::statement('LOCK TABLE work_order_reschedules IN ACCESS EXCLUSIVE MODE');
        }
        // Never discard newly captured temporal evidence through a rollback.
        foreach (['old_scheduled_start', 'old_scheduled_end', 'new_scheduled_start', 'new_scheduled_end'] as $field) {
            if (DB::table('work_order_reschedules')->whereNotNull($field.'_utc')->exists()) {
                throw new RuntimeException('UTC evidence exists; rollback must preserve companion columns.');
            }
        }
        Schema::table('work_order_reschedules', fn (Blueprint $table) => $table->dropColumn(['old_scheduled_start_utc', 'old_scheduled_end_utc', 'new_scheduled_start_utc', 'new_scheduled_end_utc']));
    }
};

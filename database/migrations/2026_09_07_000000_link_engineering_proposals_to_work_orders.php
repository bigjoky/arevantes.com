<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('engineering_schedule_proposals', function (Blueprint $table) {
            $table->foreignId('work_order_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->string('execution_start_time', 5)->nullable();
            $table->foreignId('executed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('executed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('engineering_schedule_proposals', function (Blueprint $table) {
            $table->dropConstrainedForeignId('work_order_id');
            $table->dropConstrainedForeignId('executed_by');
            $table->dropColumn(['execution_start_time', 'executed_at']);
        });
    }
};

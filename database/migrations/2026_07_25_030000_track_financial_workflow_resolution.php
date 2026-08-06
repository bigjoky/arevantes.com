<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('collection_actions', function (Blueprint $table) {
            $table->string('resolution_reason', 40)->nullable()->after('status');
            $table->timestamp('resolved_at')->nullable()->after('next_action_on');
        });

        Schema::table('payment_batches', function (Blueprint $table) {
            $table->timestamp('reconciled_at')->nullable()->after('executed_at');
        });
    }

    public function down(): void
    {
        Schema::table('collection_actions', fn (Blueprint $table) => $table->dropColumn(['resolution_reason', 'resolved_at']));
        Schema::table('payment_batches', fn (Blueprint $table) => $table->dropColumn('reconciled_at'));
    }
};

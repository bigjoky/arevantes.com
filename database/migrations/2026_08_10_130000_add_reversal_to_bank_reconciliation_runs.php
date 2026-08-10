<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bank_reconciliation_runs', function (Blueprint $table) {
            $table->foreignId('reversed_by')->nullable()->after('triggered_by')->constrained('users')->nullOnDelete();
            $table->timestamp('reversed_at')->nullable()->after('completed_at');
            $table->string('reversal_reason', 500)->nullable()->after('reversed_at');
        });
    }

    public function down(): void
    {
        Schema::table('bank_reconciliation_runs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reversed_by');
            $table->dropColumn(['reversed_at', 'reversal_reason']);
        });
    }
};

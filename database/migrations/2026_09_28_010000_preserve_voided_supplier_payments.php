<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_payments', function (Blueprint $table) {
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('void_reason')->nullable();
            $table->foreignId('reversal_entry_id')->nullable()->constrained('journal_entries')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::table('supplier_payments')->whereNotNull('voided_at')->exists()) {
            throw new RuntimeException('Cannot remove cancellation history while voided payments exist.');
        }
        Schema::table('supplier_payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('voided_by');
            $table->dropConstrainedForeignId('reversal_entry_id');
            $table->dropColumn(['voided_at', 'void_reason']);
        });
    }
};

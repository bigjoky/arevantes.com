<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_document_payments', function (Blueprint $table) {
            $table->timestampTz('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('void_reason')->nullable();
            $table->uuid('void_operation_key')->nullable()->unique();
            $table->foreignId('reversal_entry_id')->nullable()->constrained('journal_entries')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::table('sales_document_payments')->whereNotNull('voided_at')->exists()) {
            throw new RuntimeException('Cannot remove customer receipt cancellation history.');
        }
        Schema::table('sales_document_payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('voided_by');
            $table->dropConstrainedForeignId('reversal_entry_id');
            $table->dropUnique(['void_operation_key']);
            $table->dropColumn(['voided_at', 'void_reason', 'void_operation_key']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('historical_invoice_rows', function (Blueprint $table) {
            $table->foreignId('expense_id')->nullable()->unique()->constrained('expenses');
            $table->foreignId('confirmed_by')->nullable()->constrained('users');
            $table->timestampTz('confirmed_at')->nullable();
            $table->date('accounting_date')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('historical_invoice_rows', function (Blueprint $table) {
            $table->dropConstrainedForeignId('expense_id');
            $table->dropConstrainedForeignId('confirmed_by');
            $table->dropColumn(['confirmed_at', 'accounting_date']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_document_payments', function (Blueprint $table) {
            $table->foreignId('origin_bank_transaction_id')->nullable()->unique()->after('created_by')->constrained('bank_transactions')->nullOnDelete();
        });

        Schema::table('supplier_payments', function (Blueprint $table) {
            $table->foreignId('origin_bank_transaction_id')->nullable()->unique()->after('created_by')->constrained('bank_transactions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sales_document_payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('origin_bank_transaction_id');
        });

        Schema::table('supplier_payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('origin_bank_transaction_id');
        });
    }
};

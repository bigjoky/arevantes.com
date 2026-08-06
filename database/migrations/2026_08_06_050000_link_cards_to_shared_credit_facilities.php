<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bank_financial_products', function (Blueprint $table) {
            $table->foreignId('shared_credit_facility_id')
                ->nullable()
                ->after('bank_account_id')
                ->constrained('bank_financial_products')
                ->nullOnDelete();
            $table->index(['shared_credit_facility_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::table('bank_financial_products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('shared_credit_facility_id');
        });
    }
};

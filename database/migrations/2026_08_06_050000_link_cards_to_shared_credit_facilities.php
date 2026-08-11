<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL does not roll back every ALTER TABLE statement when a later
        // statement fails. Keep this migration safe to rerun after a partial
        // deployment and use an explicit name below the 64-character limit.
        if (! Schema::hasColumn('bank_financial_products', 'shared_credit_facility_id')) {
            Schema::table('bank_financial_products', function (Blueprint $table) {
                $table->foreignId('shared_credit_facility_id')
                    ->nullable()
                    ->after('bank_account_id')
                    ->constrained('bank_financial_products')
                    ->nullOnDelete();
            });
        }

        Schema::table('bank_financial_products', function (Blueprint $table) {
            $table->index(['shared_credit_facility_id', 'is_active'], 'bank_products_shared_credit_active_idx');
        });
    }

    public function down(): void
    {
        Schema::table('bank_financial_products', function (Blueprint $table) {
            $table->dropIndex('bank_products_shared_credit_active_idx');
            $table->dropConstrainedForeignId('shared_credit_facility_id');
        });
    }
};

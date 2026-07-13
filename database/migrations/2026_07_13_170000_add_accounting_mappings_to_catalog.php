<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('sale_account_code', 20)->nullable();
            $table->string('purchase_account_code', 20)->nullable();
        });
        Schema::table('expense_categories', fn (Blueprint $table) => $table->string('expense_account_code', 20)->nullable());
        Schema::table('tax_rates', function (Blueprint $table) {
            $table->string('output_account_code', 20)->nullable();
            $table->string('input_account_code', 20)->nullable();
        });
        DB::table('products')->where('type', 'service')->update(['sale_account_code' => '705', 'purchase_account_code' => '600']);
        DB::table('products')->where('type', 'product')->update(['sale_account_code' => '700', 'purchase_account_code' => '600']);
        DB::table('expense_categories')->update(['expense_account_code' => '629']);
        DB::table('tax_rates')->update(['output_account_code' => '477', 'input_account_code' => '472']);
    }

    public function down(): void
    {
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn(['sale_account_code', 'purchase_account_code']));
        Schema::table('expense_categories', fn (Blueprint $table) => $table->dropColumn('expense_account_code'));
        Schema::table('tax_rates', fn (Blueprint $table) => $table->dropColumn(['output_account_code', 'input_account_code']));
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tax_returns', function (Blueprint $table) {
            $table->string('result_option', 20)->nullable()->after('result');
            $table->string('receipt_original_name')->nullable()->after('receipt_path');
            $table->string('receipt_sha256', 64)->nullable()->after('receipt_original_name');
        });
        DB::table('tax_returns')->where('result', '>', 0)->update(['result_option' => 'payable']);
        DB::table('tax_returns')->where('result', '<', 0)->update(['result_option' => 'compensate']);
        DB::table('tax_returns')->where('result', '=', 0)->update(['result_option' => 'none']);

        Schema::table('corporate_tax_returns', function (Blueprint $table) {
            $table->string('receipt_original_name')->nullable()->after('receipt_path');
            $table->string('receipt_sha256', 64)->nullable()->after('receipt_original_name');
        });
    }

    public function down(): void
    {
        Schema::table('corporate_tax_returns', function (Blueprint $table) {
            $table->dropColumn(['receipt_original_name', 'receipt_sha256']);
        });
        Schema::table('tax_returns', function (Blueprint $table) {
            $table->dropColumn(['result_option', 'receipt_original_name', 'receipt_sha256']);
        });
    }
};

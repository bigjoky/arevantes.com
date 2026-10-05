<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_fiscal_classifications', function (Blueprint $table) {
            $table->date('registered_on')->nullable();
            $table->unsignedSmallInteger('deduction_year')->nullable();
            $table->unsignedTinyInteger('deduction_quarter')->nullable();
            $table->index(['deduction_year', 'deduction_quarter'], 'purchase_fiscal_deduction_period_idx');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_fiscal_classifications', function (Blueprint $table) {
            $table->dropIndex('purchase_fiscal_deduction_period_idx');
            $table->dropColumn(['registered_on', 'deduction_year', 'deduction_quarter']);
        });
    }
};

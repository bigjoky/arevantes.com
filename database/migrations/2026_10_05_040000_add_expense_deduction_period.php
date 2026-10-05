<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expense_fiscal_classifications', function (Blueprint $table) {
            $table->date('registered_on')->nullable();
            $table->unsignedSmallInteger('deduction_year')->nullable();
            $table->unsignedTinyInteger('deduction_quarter')->nullable();
            $table->string('source_reference', 255)->nullable();
            $table->index(['deduction_year', 'deduction_quarter'], 'expense_fiscal_deduction_period_idx');
        });
    }

    public function down(): void
    {
        Schema::table('expense_fiscal_classifications', function (Blueprint $table) {
            $table->dropIndex('expense_fiscal_deduction_period_idx');
            $table->dropColumn(['registered_on', 'deduction_year', 'deduction_quarter', 'source_reference']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->string('issuer_legal_name')->nullable()->after('merchant_name');
            $table->string('issuer_tax_id', 32)->nullable()->after('issuer_legal_name');
            $table->string('invoice_series', 64)->nullable()->after('document_number');
            $table->date('operation_date')->nullable()->after('expense_date');
            $table->char('currency_code', 3)->default('EUR')->after('operation_date');
            $table->string('tax_regime', 32)->default('standard')->after('currency_code');
            $table->decimal('withholding_rate', 7, 3)->default(0)->after('tax_amount');
            $table->decimal('withholding_amount', 14, 2)->default(0)->after('withholding_rate');
            $table->decimal('deductible_expense_percent', 5, 2)->default(100)->after('withholding_amount');
        });

        Schema::create('expense_tax_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('kind', 32)->default('vat');
            $table->decimal('taxable_base', 14, 2);
            $table->decimal('tax_rate', 7, 3)->default(0);
            $table->decimal('tax_amount', 14, 2)->default(0);
            $table->decimal('deductible_percent', 5, 2)->default(100);
            $table->decimal('deductible_amount', 14, 2)->default(0);
            $table->string('exemption_reason')->nullable();
            $table->timestamps();
            $table->index(['expense_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_tax_lines');
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropColumn([
                'issuer_legal_name', 'issuer_tax_id', 'invoice_series', 'operation_date',
                'currency_code', 'tax_regime', 'withholding_rate', 'withholding_amount',
                'deductible_expense_percent',
            ]);
        });
    }
};

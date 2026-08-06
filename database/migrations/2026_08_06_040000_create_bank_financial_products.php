<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_financial_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_account_id')->constrained()->restrictOnDelete();
            $table->foreignId('ledger_account_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('type', 30);
            $table->string('name');
            $table->string('provider')->nullable();
            $table->string('currency', 3)->default('EUR');
            $table->string('identifier_last_four', 4)->nullable();
            $table->string('contract_reference')->nullable();
            $table->decimal('credit_limit', 14, 2);
            $table->decimal('used_amount', 14, 2)->default(0);
            $table->decimal('annual_interest_rate', 7, 4)->nullable();
            $table->decimal('annual_fee', 14, 2)->default(0);
            $table->unsignedTinyInteger('billing_day')->nullable();
            $table->unsignedTinyInteger('payment_day')->nullable();
            $table->date('starts_on')->nullable();
            $table->date('expires_on')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['type', 'is_active']);
            $table->index(['expires_on', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_financial_products');
    }
};

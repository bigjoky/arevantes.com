<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('bank_name')->nullable();
            $table->string('iban', 34)->nullable()->unique();
            $table->string('currency', 3)->default('EUR');
            $table->decimal('opening_balance', 14, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('bank_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_account_id')->constrained()->cascadeOnDelete();
            $table->date('booked_on')->index();
            $table->date('value_on')->nullable();
            $table->decimal('amount', 14, 2);
            $table->string('currency', 3)->default('EUR');
            $table->string('reference')->nullable();
            $table->text('description');
            $table->string('import_hash', 64);
            $table->string('status', 20)->default('pending');
            $table->json('raw_data')->nullable();
            $table->timestamps();
            $table->unique(['bank_account_id', 'import_hash']);
            $table->index(['bank_account_id', 'status', 'booked_on']);
        });

        Schema::create('bank_reconciliations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_transaction_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('sales_document_payment_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_payment_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('reconciled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('method', 20)->default('manual');
            $table->timestamp('reconciled_at');
            $table->timestamps();
            $table->unique('sales_document_payment_id');
            $table->unique('supplier_payment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_reconciliations');
        Schema::dropIfExists('bank_transactions');
        Schema::dropIfExists('bank_accounts');
    }
};

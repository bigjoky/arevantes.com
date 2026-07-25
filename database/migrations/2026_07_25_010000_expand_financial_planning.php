<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->foreignId('installation_id')->nullable()->after('supplier_id')->constrained()->nullOnDelete();
            $table->index(['installation_id', 'expense_date']);
        });

        Schema::table('suppliers', function (Blueprint $table) {
            $table->string('iban', 34)->nullable()->after('country');
            $table->string('bic', 11)->nullable()->after('iban');
        });

        Schema::table('sales_document_payments', fn (Blueprint $table) => $table->index('paid_at'));
        Schema::table('supplier_payments', fn (Blueprint $table) => $table->index('paid_at'));

        Schema::create('financial_forecast_items', function (Blueprint $table) {
            $table->id();
            $table->string('direction', 10);
            $table->string('category', 40);
            $table->string('description');
            $table->decimal('amount', 14, 2);
            $table->date('due_on');
            $table->unsignedTinyInteger('probability')->default(100);
            $table->string('recurrence', 20)->nullable();
            $table->string('status', 20)->default('planned');
            $table->foreignId('installation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['status', 'due_on', 'direction']);
        });

        Schema::create('financial_budgets', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->string('category', 30);
            $table->decimal('amount', 14, 2);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['year', 'month', 'category']);
        });

        Schema::create('collection_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_document_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('open');
            $table->date('contacted_on')->nullable();
            $table->date('promised_on')->nullable();
            $table->decimal('promised_amount', 14, 2)->nullable();
            $table->date('next_action_on')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['status', 'next_action_on']);
        });

        Schema::create('payment_batches', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->unique();
            $table->foreignId('bank_account_id')->nullable()->constrained()->nullOnDelete();
            $table->date('planned_on');
            $table->string('status', 20)->default('draft');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('exported_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'planned_on']);
        });

        Schema::create('payment_batch_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_batch_id')->constrained()->cascadeOnDelete();
            $table->string('payable_type', 30);
            $table->unsignedBigInteger('payable_id');
            $table->decimal('amount', 14, 2);
            $table->timestamps();
            $table->unique(['payment_batch_id', 'payable_type', 'payable_id'], 'payment_batch_payable_unique');
            $table->index(['payable_type', 'payable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_batch_items');
        Schema::dropIfExists('payment_batches');
        Schema::dropIfExists('collection_actions');
        Schema::dropIfExists('financial_budgets');
        Schema::dropIfExists('financial_forecast_items');

        Schema::table('supplier_payments', fn (Blueprint $table) => $table->dropIndex(['paid_at']));
        Schema::table('sales_document_payments', fn (Blueprint $table) => $table->dropIndex(['paid_at']));
        Schema::table('suppliers', fn (Blueprint $table) => $table->dropColumn(['iban', 'bic']));
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropIndex(['installation_id', 'expense_date']);
            $table->dropConstrainedForeignId('installation_id');
        });
    }
};

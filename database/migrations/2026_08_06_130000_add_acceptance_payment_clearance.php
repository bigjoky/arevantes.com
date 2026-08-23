<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->decimal('acceptance_payment_percentage', 5, 2)->default(30)->after('approval_notes');
            $table->unsignedSmallInteger('acceptance_payment_due_days')->default(7)->after('acceptance_payment_percentage');
            $table->string('financial_status', 20)->default('not_required')->after('acceptance_payment_due_days')->index();
            $table->timestamp('financially_cleared_at')->nullable()->after('financial_status');
        });

        Schema::table('sales_documents', function (Blueprint $table) {
            $table->boolean('is_acceptance_invoice')->default(false)->after('billing_label');
            $table->index(['quote_id', 'is_acceptance_invoice'], 'sales_quote_acceptance_invoice_index');
        });

        Schema::table('installations', function (Blueprint $table) {
            $table->string('financial_status', 20)->default('not_required')->after('status')->index();
            $table->timestamp('financially_cleared_at')->nullable()->after('financial_status');
        });
    }

    public function down(): void
    {
        Schema::table('installations', function (Blueprint $table) {
            $table->dropIndex(['financial_status']);
            $table->dropColumn(['financial_status', 'financially_cleared_at']);
        });

        Schema::table('sales_documents', function (Blueprint $table) {
            $table->dropIndex('sales_quote_acceptance_invoice_index');
            $table->dropColumn('is_acceptance_invoice');
        });

        Schema::table('quotes', function (Blueprint $table) {
            $table->dropIndex(['financial_status']);
            $table->dropColumn(['acceptance_payment_percentage', 'acceptance_payment_due_days', 'financial_status', 'financially_cleared_at']);
        });
    }
};

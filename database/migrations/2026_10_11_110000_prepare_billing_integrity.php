<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_documents', function (Blueprint $table) {
            $table->string('archived_pdf_path')->nullable();
            $table->string('archived_pdf_hash', 64)->nullable();
            $table->index(['source_document_id', 'type', 'status'], 'billing_sales_credit_idx');
            $table->index('due_date', 'billing_sales_due_idx');
        });
        foreach (['sales_document_payments', 'supplier_payments'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->uuid('idempotency_key')->nullable()->unique());
        }
    }

    public function down(): void
    {
        foreach (['sales_document_payments', 'supplier_payments'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropUnique(['idempotency_key']);
                $table->dropColumn('idempotency_key');
            });
        }
        Schema::table('sales_documents', function (Blueprint $table) {
            $table->dropIndex('billing_sales_credit_idx');
            $table->dropIndex('billing_sales_due_idx');
            $table->dropColumn(['archived_pdf_path', 'archived_pdf_hash']);
        });
    }
};

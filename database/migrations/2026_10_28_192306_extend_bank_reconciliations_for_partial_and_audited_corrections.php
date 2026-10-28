<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bank_reconciliations', function (Blueprint $table) {
            $table->decimal('allocated_amount', 12, 2)->nullable();
            $table->uuid('operation_key')->nullable()->unique();
            $table->text('reason')->nullable();
            $table->timestampTz('confirmed_at_utc')->nullable();
            $table->timestampTz('reversed_at')->nullable();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('reversal_reason')->nullable();
            $table->uuid('reversal_operation_key')->nullable()->unique();
            $table->foreignId('reversal_entry_id')->nullable()->constrained('journal_entries')->restrictOnDelete();
            $table->dropUnique(['bank_transaction_id']);
            $table->dropUnique(['sales_document_payment_id']);
            $table->dropUnique(['supplier_payment_id']);
            $table->dropUnique(['tax_return_payment_id']);
            $table->index('bank_transaction_id');
            $table->index('sales_document_payment_id');
            $table->index('supplier_payment_id');
            $table->index('tax_return_payment_id');
        });
        // Old rows remain byte-for-byte intact. Their allocation is inferred from the bank amount.
        foreach (['sales_document_payment_id', 'supplier_payment_id', 'tax_return_payment_id'] as $column) {
            $name = ['sales_document_payment_id' => 'bank_active_customer_pair', 'supplier_payment_id' => 'bank_active_supplier_pair', 'tax_return_payment_id' => 'bank_active_tax_pair'][$column];
            DB::statement("CREATE UNIQUE INDEX $name ON bank_reconciliations (bank_transaction_id, $column) WHERE reversed_at IS NULL AND $column IS NOT NULL");
        }
    }

    public function down(): void
    {
        // Restoring one-to-one constraints could destroy or invalidate allocation/history.
        throw new RuntimeException('Audited bank allocations require a reviewed forward migration; automatic rollback is disabled.');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('serial_numbers', function (Blueprint $table) {
            $table->foreignId('origin_purchase_document_id')
                ->nullable()
                ->after('product_id')
                ->constrained('purchase_documents')
                ->restrictOnDelete();
            $table->index(['product_id', 'warehouse_id', 'status'], 'serial_product_warehouse_status_idx');
        });

        DB::statement(<<<'SQL'
            update serial_numbers
            set origin_purchase_document_id = (
                select stock_movements.purchase_document_id
                from stock_movement_lines
                join stock_movements on stock_movements.id = stock_movement_lines.stock_movement_id
                where stock_movement_lines.id = serial_numbers.received_line_id
            )
            where origin_purchase_document_id is null
              and received_line_id is not null
        SQL);

        Schema::table('purchase_document_lines', function (Blueprint $table) {
            $table->index('purchase_document_id', 'purchase_lines_document_idx');
            $table->index('product_id', 'purchase_lines_product_idx');
            $table->index('source_line_id', 'purchase_lines_source_idx');
        });
        Schema::table('stock_movement_lines', fn (Blueprint $table) => $table->index('stock_movement_id', 'stock_lines_movement_idx'));
        Schema::table('goods_receipts', function (Blueprint $table) {
            $table->index('purchase_document_id', 'goods_receipts_purchase_idx');
            $table->index('stock_movement_id', 'goods_receipts_movement_idx');
        });
        Schema::table('goods_returns', function (Blueprint $table) {
            $table->index('purchase_document_id', 'goods_returns_purchase_idx');
            $table->index('stock_movement_id', 'goods_returns_movement_idx');
        });
        Schema::table('supplier_payments', function (Blueprint $table) {
            $table->index('expense_id', 'supplier_payments_expense_idx');
            $table->index('purchase_document_id', 'supplier_payments_purchase_idx');
        });
        Schema::table('serial_number_events', function (Blueprint $table) {
            $table->index('stock_movement_id', 'serial_events_movement_idx');
            $table->index('installation_id', 'serial_events_installation_idx');
        });
        Schema::table('supplier_quality_incidents', fn (Blueprint $table) => $table->index('goods_receipt_id', 'supplier_quality_receipt_idx'));
    }

    public function down(): void
    {
        Schema::table('supplier_quality_incidents', fn (Blueprint $table) => $table->dropIndex('supplier_quality_receipt_idx'));
        Schema::table('serial_number_events', function (Blueprint $table) {
            $table->dropIndex('serial_events_movement_idx');
            $table->dropIndex('serial_events_installation_idx');
        });
        Schema::table('supplier_payments', function (Blueprint $table) {
            $table->dropIndex('supplier_payments_expense_idx');
            $table->dropIndex('supplier_payments_purchase_idx');
        });
        Schema::table('goods_returns', function (Blueprint $table) {
            $table->dropIndex('goods_returns_purchase_idx');
            $table->dropIndex('goods_returns_movement_idx');
        });
        Schema::table('goods_receipts', function (Blueprint $table) {
            $table->dropIndex('goods_receipts_purchase_idx');
            $table->dropIndex('goods_receipts_movement_idx');
        });
        Schema::table('stock_movement_lines', fn (Blueprint $table) => $table->dropIndex('stock_lines_movement_idx'));
        Schema::table('purchase_document_lines', function (Blueprint $table) {
            $table->dropIndex('purchase_lines_document_idx');
            $table->dropIndex('purchase_lines_product_idx');
            $table->dropIndex('purchase_lines_source_idx');
        });
        Schema::table('serial_numbers', function (Blueprint $table) {
            $table->dropIndex('serial_product_warehouse_status_idx');
            $table->dropConstrainedForeignId('origin_purchase_document_id');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        DB::table('document_series')->insert(['document_type' => 'purchase_rfq', 'name' => 'RFQ '.now()->year, 'prefix' => 'RFQ', 'next_number' => 1, 'fiscal_year' => now()->year, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('document_series')->insert(['document_type' => 'goods_receipt', 'name' => 'REC '.now()->year, 'prefix' => 'EM', 'next_number' => 1, 'fiscal_year' => now()->year, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);

        Schema::create('purchase_documents', function (Blueprint $table) {
            $table->id();
            $table->string('type', 30);
            $table->foreignId('source_document_id')->nullable()->constrained('purchase_documents')->nullOnDelete();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('document_series_id')->nullable()->constrained('document_series')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('number')->unique();
            $table->string('supplier_number')->nullable();
            $table->string('status', 20)->default('draft');
            $table->date('issue_date');
            $table->date('due_date')->nullable();
            $table->decimal('subtotal', 12, 2);
            $table->decimal('tax_amount', 12, 2);
            $table->decimal('amount', 12, 2);
            $table->text('notes')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->timestamps();
            $table->index(['type', 'status', 'issue_date']);
        });

        Schema::create('purchase_document_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('position');
            $table->text('description');
            $table->decimal('quantity', 12, 3);
            $table->decimal('received_quantity', 12, 3)->default(0);
            $table->decimal('unit_price', 12, 2);
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->decimal('tax_percent', 5, 2)->default(0);
            $table->decimal('subtotal', 12, 2);
            $table->decimal('tax_amount', 12, 2);
            $table->decimal('total', 12, 2);
            $table->timestamps();
        });

        Schema::create('goods_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_document_id')->constrained()->restrictOnDelete();
            $table->foreignId('stock_movement_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('number')->unique();
            $table->string('supplier_delivery_note')->nullable();
            $table->timestamp('received_at');
            $table->timestamps();
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->foreignId('purchase_document_id')->nullable()->after('sales_document_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', fn (Blueprint $table) => $table->dropConstrainedForeignId('purchase_document_id'));
        Schema::dropIfExists('goods_receipts');
        Schema::dropIfExists('purchase_document_lines');
        Schema::dropIfExists('purchase_documents');
        DB::table('document_series')->whereIn('document_type', ['purchase_rfq', 'goods_receipt'])->delete();
    }
};

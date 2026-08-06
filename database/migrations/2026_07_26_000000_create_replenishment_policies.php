<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_documents', function (Blueprint $table) {
            $table->foreignId('destination_warehouse_id')->nullable()->after('supplier_id')->constrained('warehouses')->nullOnDelete();
            $table->index(['destination_warehouse_id', 'type', 'status']);
        });

        Schema::create('replenishment_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->decimal('reorder_point', 12, 3)->default(0);
            $table->decimal('target_stock', 12, 3);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['warehouse_id', 'product_id']);
            $table->index(['is_active', 'warehouse_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('replenishment_policies');

        Schema::table('purchase_documents', function (Blueprint $table) {
            $table->dropIndex(['destination_warehouse_id', 'type', 'status']);
            $table->dropConstrainedForeignId('destination_warehouse_id');
        });
    }
};

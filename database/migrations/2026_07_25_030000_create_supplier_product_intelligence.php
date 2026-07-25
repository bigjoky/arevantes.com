<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('supplier_sku')->nullable();
            $table->string('supplier_description')->nullable();
            $table->decimal('minimum_order_quantity', 12, 3)->default(1);
            $table->decimal('order_multiple', 12, 3)->default(1);
            $table->unsignedSmallInteger('lead_time_days')->nullable();
            $table->decimal('last_unit_price', 12, 2)->nullable();
            $table->string('currency', 3)->default('EUR');
            $table->boolean('is_preferred')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['supplier_id', 'product_id']);
            $table->index(['product_id', 'is_preferred']);
        });

        Schema::create('supplier_product_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_document_line_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->decimal('unit_price', 12, 2);
            $table->string('currency', 3)->default('EUR');
            $table->timestamp('recorded_at');
            $table->timestamps();
            $table->index(['supplier_product_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_product_prices');
        Schema::dropIfExists('supplier_products');
    }
};

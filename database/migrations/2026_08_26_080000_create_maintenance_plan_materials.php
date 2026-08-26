<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_plan_materials', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('maintenance_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity_per_visit', 12, 3)->default(1);
            $table->decimal('expected_usage_percent', 5, 2)->default(100);
            $table->decimal('minimum_stock', 12, 3)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['maintenance_plan_id', 'product_id']);
            $table->index(['product_id', 'maintenance_plan_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_plan_materials');
    }
};

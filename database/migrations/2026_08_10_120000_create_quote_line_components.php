<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quote_line_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quote_line_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('position')->default(1);
            $table->string('kind', 20)->default('material');
            $table->string('fulfillment_kind', 20)->default('stock');
            $table->string('visibility', 20)->default('detailed');
            $table->text('description');
            $table->decimal('quantity', 12, 3)->default(1);
            $table->decimal('planned_unit_cost', 12, 2)->default(0);
            $table->unsignedSmallInteger('interval_months')->nullable();
            $table->timestamps();

            $table->index(['quote_line_id', 'position']);
            $table->index(['fulfillment_kind', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quote_line_components');
    }
};

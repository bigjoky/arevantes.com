<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('installation_material_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('installation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quote_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source', 30)->default('quote');
            $table->string('sku')->nullable();
            $table->string('description');
            $table->decimal('planned_quantity', 12, 3);
            $table->decimal('planned_unit_cost', 12, 2)->default(0);
            $table->timestamps();

            $table->index(['installation_id', 'source']);
            $table->unique(['installation_id', 'source', 'product_id'], 'installation_material_source_product_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('installation_material_requirements');
    }
};

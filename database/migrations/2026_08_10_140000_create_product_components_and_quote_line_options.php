<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('component_product_id')->constrained('products')->restrictOnDelete();
            $table->unsignedSmallInteger('position')->default(1);
            $table->decimal('quantity', 12, 3)->default(1);
            $table->string('fulfillment_kind', 20)->default('stock');
            $table->string('visibility', 20)->default('detailed');
            $table->unsignedSmallInteger('interval_months')->nullable();
            $table->timestamps();

            $table->unique(['parent_product_id', 'component_product_id']);
        });

        Schema::table('quote_lines', function (Blueprint $table) {
            $table->string('selection_mode', 20)->default('included')->after('position');
            $table->string('option_group')->nullable()->after('selection_mode');
            $table->boolean('is_selected')->default(true)->after('option_group');
            $table->index(['quote_id', 'selection_mode', 'is_selected']);
        });
    }

    public function down(): void
    {
        Schema::table('quote_lines', function (Blueprint $table) {
            $table->dropIndex(['quote_id', 'selection_mode', 'is_selected']);
            $table->dropColumn(['selection_mode', 'option_group', 'is_selected']);
        });
        Schema::dropIfExists('product_components');
    }
};

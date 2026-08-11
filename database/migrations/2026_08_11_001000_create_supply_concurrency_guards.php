<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 15, 3)->default(0);
            $table->timestamps();
            $table->unique(['warehouse_id', 'product_id']);
        });

        DB::statement(<<<'SQL'
            insert into stock_balances (warehouse_id, product_id, quantity, created_at, updated_at)
            select warehouse_id, product_id, sum(quantity), current_timestamp, current_timestamp
            from stock_movement_lines
            group by warehouse_id, product_id
        SQL);

        Schema::create('supply_number_sequences', function (Blueprint $table) {
            $table->string('scope', 80)->primary();
            $table->unsignedBigInteger('next_number')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supply_number_sequences');
        Schema::dropIfExists('stock_balances');
    }
};

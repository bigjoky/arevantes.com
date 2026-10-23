<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('warehouses', fn (Blueprint $table) => $table->boolean('is_usable')->default(true));
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->uuid('operation_key')->nullable()->unique();
            $table->char('operation_hash', 64)->nullable();
            $table->foreignId('source_movement_id')->nullable()->constrained('stock_movements')->restrictOnDelete();
            $table->string('material_condition', 20)->nullable();
            $table->foreignId('recipient_id')->nullable()->constrained('users')->restrictOnDelete();
        });
        Schema::create('purchase_receipt_requests', function (Blueprint $table) {
            $table->uuid('operation_key')->primary();
            $table->foreignId('purchase_document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->char('operation_hash', 64);
            $table->foreignId('goods_receipt_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedInteger('incident_count')->default(0);
            $table->timestamps();
        });
        Schema::table('goods_receipts', function (Blueprint $table) {
            $table->uuid('operation_key')->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_receipt_requests');
        Schema::table('warehouses', fn (Blueprint $table) => $table->dropColumn('is_usable'));
        Schema::table('goods_receipts', fn (Blueprint $table) => $table->dropColumn('operation_key'));
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('recipient_id');
            $table->dropConstrainedForeignId('source_movement_id');
            $table->dropColumn(['operation_key', 'operation_hash', 'material_condition']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->foreignId('sales_document_id')->nullable()->after('created_by')->constrained()->nullOnDelete();
            $table->foreignId('installation_id')->nullable()->after('sales_document_id')->constrained()->nullOnDelete();
            $table->unique('sales_document_id');
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropUnique(['sales_document_id']);
            $table->dropConstrainedForeignId('installation_id');
            $table->dropConstrainedForeignId('sales_document_id');
        });
    }
};

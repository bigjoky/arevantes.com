<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_documents', function (Blueprint $table) {
            $table->foreignId('sales_document_id')->nullable()->after('work_order_id')->constrained('sales_documents')->nullOnDelete();
            $table->index(['sales_document_id', 'type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('purchase_documents', function (Blueprint $table) {
            $table->dropIndex(['sales_document_id', 'type', 'status']);
            $table->dropConstrainedForeignId('sales_document_id');
        });
    }
};

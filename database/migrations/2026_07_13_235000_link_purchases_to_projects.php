<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_documents', function (Blueprint $table) {
            $table->foreignId('installation_id')->nullable()->after('source_document_id')->constrained()->nullOnDelete();
            $table->foreignId('work_order_id')->nullable()->after('installation_id')->constrained()->nullOnDelete();
            $table->index(['installation_id', 'status']);
            $table->index(['work_order_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('purchase_documents', function (Blueprint $table) {
            $table->dropIndex(['installation_id', 'status']);
            $table->dropIndex(['work_order_id', 'status']);
            $table->dropConstrainedForeignId('work_order_id');
            $table->dropConstrainedForeignId('installation_id');
        });
    }
};

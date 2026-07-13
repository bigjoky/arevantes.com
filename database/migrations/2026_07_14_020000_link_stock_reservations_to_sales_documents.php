<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_reservations', function (Blueprint $table) {
            $table->foreignId('sales_document_id')->nullable()->after('work_order_id')->constrained('sales_documents')->nullOnDelete();
            $table->index(['sales_document_id', 'status']);
        });

        Schema::table('stock_reservations', function (Blueprint $table) {
            $table->foreignId('installation_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('stock_reservations')->whereNull('installation_id')->delete();

        Schema::table('stock_reservations', function (Blueprint $table) {
            $table->foreignId('installation_id')->nullable(false)->change();
            $table->dropIndex(['sales_document_id', 'status']);
            $table->dropConstrainedForeignId('sales_document_id');
        });
    }
};

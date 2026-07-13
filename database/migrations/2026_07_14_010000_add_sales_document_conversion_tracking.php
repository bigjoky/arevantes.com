<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_documents', function (Blueprint $table) {
            $table->string('fulfillment_status', 20)->nullable()->after('status');
            $table->string('billing_status', 20)->nullable()->after('fulfillment_status');
        });
        Schema::table('sales_document_lines', function (Blueprint $table) {
            $table->foreignId('source_line_id')->nullable()->after('sales_document_id')->constrained('sales_document_lines')->nullOnDelete();
            $table->index('source_line_id');
        });

        DB::table('sales_documents')->where('type', 'order')->update(['fulfillment_status' => 'pending', 'billing_status' => 'pending']);
        DB::table('sales_documents')->where('type', 'delivery_note')->update(['fulfillment_status' => DB::raw("case when status = 'delivered' then 'complete' else 'pending' end"), 'billing_status' => 'pending']);
    }

    public function down(): void
    {
        Schema::table('sales_document_lines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('source_line_id');
        });
        Schema::table('sales_documents', function (Blueprint $table) {
            $table->dropColumn(['fulfillment_status', 'billing_status']);
        });
    }
};

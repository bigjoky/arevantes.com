<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_documents', fn (Blueprint $table) => $table->boolean('collect_before_invoice')->default(false));
        DB::table('sales_documents')->where('type', 'invoice')->where('status', 'draft')->whereNotNull('quote_id')->whereNull('locked_at')->update(['collect_before_invoice' => true]);
    }

    public function down(): void
    {
        Schema::table('sales_documents', fn (Blueprint $table) => $table->dropColumn('collect_before_invoice'));
    }
};

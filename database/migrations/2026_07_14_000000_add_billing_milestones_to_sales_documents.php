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
            $table->dropUnique(['quote_id', 'type']);
            $table->decimal('billing_percentage', 5, 2)->nullable()->after('due_date');
            $table->unsignedSmallInteger('billing_sequence')->nullable()->after('billing_percentage');
            $table->string('billing_label')->nullable()->after('billing_sequence');
            $table->index(['quote_id', 'type']);
        });

        DB::table('sales_documents')
            ->where('type', 'invoice')
            ->whereNotNull('quote_id')
            ->update(['billing_percentage' => 100, 'billing_sequence' => 1, 'billing_label' => 'Final invoice']);
    }

    public function down(): void
    {
        Schema::table('sales_documents', function (Blueprint $table) {
            $table->dropIndex(['quote_id', 'type']);
            $table->dropColumn(['billing_percentage', 'billing_sequence', 'billing_label']);
            $table->unique(['quote_id', 'type']);
        });
    }
};

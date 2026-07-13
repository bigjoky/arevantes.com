<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_documents', function (Blueprint $table) {
            $table->uuid('procurement_batch_id')->nullable()->after('sales_document_id')->index();
            $table->timestamp('quoted_at')->nullable()->after('issue_date');
            $table->date('valid_until')->nullable()->after('quoted_at');
            $table->unsignedSmallInteger('delivery_days')->nullable()->after('valid_until');
            $table->text('quote_notes')->nullable()->after('notes');
            $table->boolean('is_selected')->default(false)->after('quote_notes');
        });
        Schema::table('purchase_document_lines', function (Blueprint $table) {
            $table->decimal('estimated_unit_price', 12, 2)->nullable()->after('unit_price');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_document_lines', fn (Blueprint $table) => $table->dropColumn('estimated_unit_price'));
        Schema::table('purchase_documents', function (Blueprint $table) {
            $table->dropIndex(['procurement_batch_id']);
            $table->dropColumn(['procurement_batch_id', 'quoted_at', 'valid_until', 'delivery_days', 'quote_notes', 'is_selected']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_documents', function (Blueprint $table) {
            $table->string('invoicing_status', 30)->nullable()->after('matching_status')->index();
        });

        Schema::table('purchase_document_lines', function (Blueprint $table) {
            $table->decimal('returned_quantity', 12, 3)->default(0)->after('received_quantity');
        });

        Schema::create('goods_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_document_id')->constrained()->restrictOnDelete();
            $table->foreignId('stock_movement_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('number')->unique();
            $table->string('supplier_return_number')->nullable();
            $table->timestamp('returned_at');
            $table->timestamps();
        });

        $now = now();
        foreach (DB::table('fiscal_years')->get(['id', 'year']) as $year) {
            foreach (['supplier_credit_note' => 'AR', 'goods_return' => 'DEV'] as $type => $prefix) {
                DB::table('document_series')->insertOrIgnore([
                    'document_type' => $type,
                    'name' => $prefix.' '.$year->year,
                    'prefix' => $prefix,
                    'next_number' => 1,
                    'fiscal_year' => $year->year,
                    'fiscal_year_id' => $year->id,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('document_series')->whereIn('document_type', ['supplier_credit_note', 'goods_return'])->delete();
        Schema::dropIfExists('goods_returns');
        Schema::table('purchase_document_lines', fn (Blueprint $table) => $table->dropColumn('returned_quantity'));
        Schema::table('purchase_documents', function (Blueprint $table) {
            $table->dropIndex(['invoicing_status']);
            $table->dropColumn('invoicing_status');
        });
    }
};

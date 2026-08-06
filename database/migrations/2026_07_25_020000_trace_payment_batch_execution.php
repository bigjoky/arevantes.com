<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_batches', function (Blueprint $table) {
            $table->foreignId('executed_by')->nullable()->after('approved_by')->constrained('users')->nullOnDelete();
            $table->timestamp('executed_at')->nullable()->after('exported_at');
        });

        Schema::table('payment_batch_items', function (Blueprint $table) {
            $table->foreignId('supplier_payment_id')->nullable()->after('payable_id')->unique()->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payment_batch_items', fn (Blueprint $table) => $table->dropConstrainedForeignId('supplier_payment_id'));
        Schema::table('payment_batches', function (Blueprint $table) {
            $table->dropConstrainedForeignId('executed_by');
            $table->dropColumn('executed_at');
        });
    }
};

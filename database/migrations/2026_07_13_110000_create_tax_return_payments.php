<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tax_returns', function (Blueprint $table) {
            $table->decimal('paid_amount', 14, 2)->default(0)->after('result');
            $table->string('settlement_status', 20)->default('pending')->after('status');
        });

        Schema::create('tax_return_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tax_return_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('journal_entry_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->date('paid_on');
            $table->decimal('amount', 14, 2);
            $table->string('method', 30)->default('bank_transfer');
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::table('bank_reconciliations', function (Blueprint $table) {
            $table->foreignId('tax_return_payment_id')->nullable()->unique()->after('supplier_payment_id')->constrained()->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bank_reconciliations', fn (Blueprint $table) => $table->dropConstrainedForeignId('tax_return_payment_id'));
        Schema::dropIfExists('tax_return_payments');
        Schema::table('tax_returns', fn (Blueprint $table) => $table->dropColumn(['paid_amount', 'settlement_status']));
    }
};

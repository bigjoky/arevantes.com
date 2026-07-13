<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_returns', function (Blueprint $table) {
            $table->id();
            $table->string('tax_type', 20)->default('vat');
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('quarter');
            $table->date('period_from');
            $table->date('period_to');
            $table->string('status', 20)->default('prepared');
            $table->decimal('output_vat', 14, 2);
            $table->decimal('input_vat', 14, 2);
            $table->decimal('result', 14, 2);
            $table->json('snapshot');
            $table->foreignId('journal_entry_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->foreignId('prepared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('prepared_at');
            $table->string('filing_reference')->nullable();
            $table->date('filed_on')->nullable();
            $table->foreignId('filed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('receipt_path')->nullable();
            $table->timestamps();
            $table->unique(['tax_type', 'year', 'quarter']);
        });

        $now = now();
        DB::table('ledger_accounts')->insert([
            ['code' => '4700', 'name' => 'Tax authority, VAT receivable', 'type' => 'asset', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => '4750', 'name' => 'Tax authority, VAT payable', 'type' => 'liability', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_returns');
        DB::table('ledger_accounts')->whereIn('code', ['4700', '4750'])->delete();
    }
};

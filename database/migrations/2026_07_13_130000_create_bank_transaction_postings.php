<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bank_accounts', function (Blueprint $table) {
            $table->foreignId('ledger_account_id')->nullable()->after('currency')->constrained()->nullOnDelete();
        });
        DB::table('bank_accounts')->update(['ledger_account_id' => DB::table('ledger_accounts')->where('code', '572')->value('id')]);

        Schema::create('bank_transaction_postings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_transaction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('counterpart_account_id')->constrained('ledger_accounts')->restrictOnDelete();
            $table->foreignId('journal_entry_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->foreignId('reversal_entry_id')->nullable()->unique()->constrained('journal_entries')->nullOnDelete();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reversed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_transaction_postings');
        Schema::table('bank_accounts', fn (Blueprint $table) => $table->dropConstrainedForeignId('ledger_account_id'));
    }
};

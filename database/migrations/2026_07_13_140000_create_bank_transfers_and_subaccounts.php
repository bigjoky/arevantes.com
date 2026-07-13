<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        DB::table('bank_accounts')->orderBy('id')->each(function ($account) use ($now) {
            $code = '572'.str_pad((string) $account->id, 4, '0', STR_PAD_LEFT);
            $ledgerId = DB::table('ledger_accounts')->insertGetId(['code' => $code, 'name' => $account->name, 'type' => 'asset', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
            DB::table('bank_accounts')->where('id', $account->id)->update(['ledger_account_id' => $ledgerId]);
        });

        Schema::create('bank_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outgoing_transaction_id')->constrained('bank_transactions')->cascadeOnDelete();
            $table->foreignId('incoming_transaction_id')->constrained('bank_transactions')->cascadeOnDelete();
            $table->foreignId('journal_entry_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->foreignId('reversal_entry_id')->nullable()->unique()->constrained('journal_entries')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reversed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_transfers');
        $ids = DB::table('bank_accounts')->pluck('ledger_account_id')->filter();
        DB::table('bank_accounts')->update(['ledger_account_id' => DB::table('ledger_accounts')->where('code', '572')->value('id')]);
        DB::table('ledger_accounts')->whereIn('id', $ids)->delete();
    }
};

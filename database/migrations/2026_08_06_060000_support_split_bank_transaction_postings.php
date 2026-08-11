<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bank_transaction_postings', function (Blueprint $table) {
            $table->foreignId('counterpart_account_id')->nullable()->change();
            $table->json('allocation_data')->nullable()->after('counterpart_account_id');
        });
    }

    public function down(): void
    {
        DB::table('bank_transaction_postings')->whereNull('counterpart_account_id')->orderBy('id')->each(function ($posting) {
            $first = json_decode((string) $posting->allocation_data, true)[0] ?? null;
            if ($first && DB::table('ledger_accounts')->where('id', $first['account_id'] ?? null)->exists()) {
                DB::table('bank_transaction_postings')->where('id', $posting->id)->update(['counterpart_account_id' => $first['account_id']]);
            }
        });
        DB::table('bank_transaction_postings')->whereNull('counterpart_account_id')->delete();
        Schema::table('bank_transaction_postings', function (Blueprint $table) {
            $table->dropColumn('allocation_data');
            $table->foreignId('counterpart_account_id')->nullable(false)->change();
        });
    }
};

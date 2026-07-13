<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('people', fn (Blueprint $table) => $table->string('receivable_account_code', 20)->nullable()->index());
        Schema::table('suppliers', fn (Blueprint $table) => $table->string('payable_account_code', 20)->nullable()->index());
        $now = now();
        DB::table('people')->where('category', 'customer')->orderBy('id')->each(function ($person) use ($now) {
            $code = '430'.str_pad((string) $person->id, 5, '0', STR_PAD_LEFT);
            DB::table('ledger_accounts')->upsert([['code' => $code, 'name' => $person->company_name ?: trim($person->first_name.' '.$person->last_name), 'type' => 'asset', 'parent_code' => '430', 'level' => strlen($code), 'is_postable' => true, 'is_active' => true, 'source' => 'custom', 'source_version' => null, 'created_at' => $now, 'updated_at' => $now]], ['code'], ['name', 'updated_at']);
            DB::table('people')->where('id', $person->id)->update(['receivable_account_code' => $code]);
        });
        DB::table('suppliers')->orderBy('id')->each(function ($supplier) use ($now) {
            $code = '400'.str_pad((string) $supplier->id, 5, '0', STR_PAD_LEFT);
            DB::table('ledger_accounts')->upsert([['code' => $code, 'name' => $supplier->legal_name, 'type' => 'liability', 'parent_code' => '400', 'level' => strlen($code), 'is_postable' => true, 'is_active' => true, 'source' => 'custom', 'source_version' => null, 'created_at' => $now, 'updated_at' => $now]], ['code'], ['name', 'updated_at']);
            DB::table('suppliers')->where('id', $supplier->id)->update(['payable_account_code' => $code]);
        });
    }

    public function down(): void
    {
        Schema::table('people', fn (Blueprint $table) => $table->dropColumn('receivable_account_code'));
        Schema::table('suppliers', fn (Blueprint $table) => $table->dropColumn('payable_account_code'));
    }
};

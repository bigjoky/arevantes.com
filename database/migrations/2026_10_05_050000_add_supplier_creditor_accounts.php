<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->string('service_payable_account_code', 20)->nullable()->index();
        });
        Schema::table('expenses', function (Blueprint $table) {
            $table->string('creditor_account_code', 20)->nullable()->index();
        });
        // SQLite cannot rebuild these legacy tables without losing expression indexes.
        // Production/PostgreSQL enforces references; application guards also cover SQLite.
        if (DB::getDriverName() !== 'sqlite') {
            Schema::table('suppliers', fn (Blueprint $table) => $table->foreign('service_payable_account_code')->references('code')->on('ledger_accounts')->restrictOnDelete());
            Schema::table('expenses', fn (Blueprint $table) => $table->foreign('creditor_account_code')->references('code')->on('ledger_accounts')->restrictOnDelete());
        }
    }

    public function down(): void
    {
        if (DB::table('expenses')->whereNotNull('creditor_account_code')->exists()
            || DB::table('suppliers')->whereNotNull('service_payable_account_code')->exists()) {
            throw new RuntimeException('Cannot remove persisted creditor account assignments.');
        }
        Schema::table('expenses', function (Blueprint $table) {
            if (DB::getDriverName() !== 'sqlite') {
                $table->dropForeign(['creditor_account_code']);
            }
            $table->dropColumn('creditor_account_code');
        });
        Schema::table('suppliers', function (Blueprint $table) {
            if (DB::getDriverName() !== 'sqlite') {
                $table->dropForeign(['service_payable_account_code']);
            }
            $table->dropColumn('service_payable_account_code');
        });
    }
};

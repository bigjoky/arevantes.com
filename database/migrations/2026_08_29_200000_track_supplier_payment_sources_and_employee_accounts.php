<?php

use App\Services\Accounting\EmployeeLedgerAccountManager;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('people', function (Blueprint $table) {
            $table->string('employee_advance_account_code', 20)->nullable()->after('receivable_account_code');
            $table->string('employee_payable_account_code', 20)->nullable()->after('employee_advance_account_code');
            $table->foreign('employee_advance_account_code')->references('code')->on('ledger_accounts')->nullOnDelete();
            $table->foreign('employee_payable_account_code')->references('code')->on('ledger_accounts')->nullOnDelete();
        });

        Schema::table('supplier_payments', function (Blueprint $table) {
            $table->string('funding_source_type', 30)->nullable()->after('method');
            $table->unsignedBigInteger('funding_source_id')->nullable()->after('funding_source_type');
            $table->foreignId('ledger_account_id')->nullable()->after('funding_source_id')->constrained()->restrictOnDelete();
            $table->index(['funding_source_type', 'funding_source_id'], 'supplier_payments_funding_source_index');
        });

        $cash = DB::table('ledger_accounts')->where('code', '570')->value('id');
        $bank = DB::table('ledger_accounts')->where('code', '572')->value('id');
        DB::table('supplier_payments')->orderBy('id')->each(function ($payment) use ($cash, $bank): void {
            DB::table('supplier_payments')->where('id', $payment->id)->update([
                'funding_source_type' => 'legacy',
                'ledger_account_id' => $payment->method === 'cash' ? $cash : $bank,
            ]);
        });

        app(EmployeeLedgerAccountManager::class)->syncAll();
    }

    public function down(): void
    {
        Schema::table('supplier_payments', function (Blueprint $table) {
            $table->dropIndex('supplier_payments_funding_source_index');
            $table->dropConstrainedForeignId('ledger_account_id');
            $table->dropColumn(['funding_source_type', 'funding_source_id']);
        });
        Schema::table('people', function (Blueprint $table) {
            $table->dropForeign(['employee_advance_account_code']);
            $table->dropForeign(['employee_payable_account_code']);
            $table->dropColumn(['employee_advance_account_code', 'employee_payable_account_code']);
        });
    }
};

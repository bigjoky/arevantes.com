<?php

namespace Tests\Support;

use App\Models\Expense;
use App\Models\LedgerAccount;
use App\Models\Person;
use App\Models\PurchaseDocument;
use App\Models\Supplier;
use Illuminate\Support\Facades\DB;

/** Complete synthetic identities for tests whose subject is not missing fiscal data. */
class EconomicFixture
{
    public static function supplier(array $data): Supplier
    {
        $supplier = Supplier::create([...$data, 'tax_id' => $data['tax_id'] ?? 'B'.sprintf('%08d', hexdec(substr(hash('sha256', $data['legal_name'] ?? 'Synthetic supplier'), 0, 6)))]);
        self::supplierAccounts($supplier);

        return $supplier;
    }

    public static function supplierAccounts(Supplier $supplier): void
    {
        // Explicit master-data choices in synthetic fixtures; production no longer provisions accounts.
        foreach (['payable_account_code' => ['400', 'liability'], 'service_payable_account_code' => ['410', 'liability']] as $field => [$prefix,$type]) {
            if (! $supplier->$field) {
                $code = $prefix.str_pad((string) $supplier->id, 4, '0', STR_PAD_LEFT);
                LedgerAccount::firstOrCreate(['code' => $code], ['name' => $supplier->legal_name, 'type' => $type, 'parent_code' => $prefix, 'is_active' => true, 'is_postable' => true, 'source' => 'custom']);
                $supplier->update([$field => $code]);
            }
        }
    }

    public static function personAccounts(Person $person): void
    {
        $fields = $person->category === 'staff' ? ['employee_advance_account_code' => ['460', 'asset'], 'employee_payable_account_code' => ['465', 'liability']] : ['receivable_account_code' => ['430', 'asset']];
        foreach ($fields as $field => [$prefix,$type]) {
            $code = $prefix.str_pad((string) $person->id, 4, '0', STR_PAD_LEFT);
            LedgerAccount::firstOrCreate(['code' => $code], ['name' => 'Synthetic chosen account', 'type' => $type, 'parent_code' => $prefix, 'is_active' => true, 'is_postable' => true, 'source' => 'custom']);
            $person->update([$field => $code]);
        }
    }

    public static function expense(array $data): Expense
    {
        if (in_array($data['type'] ?? '', ['invoice', 'simplified_invoice'], true)) {
            $data += ['issuer_tax_id' => 'B12345678', 'issuer_country' => 'ES', 'document_number' => 'SYNTHETIC-'.($data['number'] ?? bin2hex(random_bytes(8)))];
        }

        return Expense::create($data);
    }

    public static function purchase(array $data): PurchaseDocument
    {
        if (in_array($data['type'] ?? '', ['supplier_invoice', 'supplier_credit_note'], true)) {
            $data += ['supplier_number' => 'SYNTHETIC-'.($data['number'] ?? bin2hex(random_bytes(8)))];
        }

        return PurchaseDocument::create($data);
    }

    /** Simulate pre-migration history explicitly, only inside a disposable test database. */
    public static function legacy(\Closure $create): mixed
    {
        if (! app()->environment('testing') || (DB::getDriverName() === 'pgsql' && ! str_ends_with(DB::connection()->getDatabaseName(), '_test')) || (DB::getDriverName() === 'sqlite' && DB::connection()->getDatabaseName() !== ':memory:')) {
            throw new \LogicException('Disposable test database required for historical fixtures.');
        }
        $triggers = ['purchase_received_identity' => 'purchase_documents', 'expense_received_identity' => 'expenses', 'purchase_received_identity_update' => 'purchase_documents', 'expense_received_identity_update' => 'expenses', 'supplier_payment_received_identity' => 'supplier_payments', 'journal_received_identity' => 'journal_entries', 'reconciliation_received_identity' => 'bank_reconciliations'];
        $restore = [];
        if (DB::getDriverName() === 'pgsql') {
            foreach ($triggers as $trigger => $table) {
                if (! DB::table('pg_trigger')->where('tgname', $trigger)->exists()) {
                    continue;
                }
                DB::statement("ALTER TABLE $table DISABLE TRIGGER $trigger");
                $restore[] = "ALTER TABLE $table ENABLE TRIGGER $trigger";
            }
        } else {
            foreach (DB::table('sqlite_master')->where('type', 'trigger')->whereIn('name', array_keys($triggers))->get(['name', 'sql']) as $trigger) {
                DB::statement('DROP TRIGGER '.$trigger->name);
                $restore[] = $trigger->sql;
            }
        }
        try {
            return $create();
        } finally {
            foreach ($restore as $sql) {
                DB::unprepared($sql);
            }
        }
    }
}

<?php

namespace Tests\Support;

use App\Models\Expense;
use App\Models\PurchaseDocument;
use App\Models\Supplier;
use Illuminate\Support\Facades\DB;

/** Complete synthetic identities for tests whose subject is not missing fiscal data. */
class EconomicFixture
{
    public static function supplier(array $data): Supplier
    {
        return Supplier::create([...$data, 'tax_id' => $data['tax_id'] ?? 'B'.sprintf('%08d', hexdec(substr(hash('sha256', $data['legal_name'] ?? 'Synthetic supplier'), 0, 6)))]);
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

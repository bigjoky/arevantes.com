<?php

namespace Tests\Feature;

use Dompdf\Dompdf;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class DeploymentIncrementalTest extends TestCase
{
    public function test_incremental_upgrade_preserves_synthetic_pre_time_history(): void
    {
        if (getenv('DEPLOY01_LOCAL_TEST') !== '1' || DB::getDriverName() !== 'pgsql' || DB::connection()->getDatabaseName() !== 'arevantes_deploy01_incremental_test') {
            $this->markTestSkipped('Dedicated marked local deployment database required.');
        }
        $cluster = new Process(['container', 'exec', 'apple-dev-postgres', 'psql', '-U', 'developer', '-d', 'postgres', '-Atc', 'SELECT system_identifier::text FROM pg_control_system()']);
        $cluster->mustRun();
        $this->assertSame(trim($cluster->getOutput()), DB::selectOne('SELECT system_identifier::text AS id FROM pg_control_system()')->id);
        $this->assertSame('arevantes:deploy01:synthetic:v1', DB::selectOne("SELECT shobj_description(oid,'pg_database') AS marker FROM pg_database WHERE datname=current_database()")->marker);
        // Build a genuinely older schema, not the current schema followed by destructive downs.
        $directory = sys_get_temp_dir().'/deploy01-old-schema-'.bin2hex(random_bytes(8));
        mkdir($directory);
        foreach (glob(database_path('migrations/*.php')) as $file) {
            if (basename($file) < '2026_10_25_030000') {
                copy($file, $directory.'/'.basename($file));
            }
        }
        $this->artisan('migrate:fresh', ['--path' => $directory, '--realpath' => true, '--force' => true])->assertSuccessful();
        $this->artisan('db:seed', ['--force' => true])->assertSuccessful();
        $id = DB::table('document_deliveries')->insertGetId(['recipient_email' => 'synthetic@example.test', 'token_hash' => hash('sha256', 'deploy01'), 'status' => 'sent', 'expires_at' => '2026-10-25 02:30:00', 'created_at' => '2026-10-24 10:00:00', 'updated_at' => '2026-10-24 10:00:00']);
        $stamp = ['created_at' => '2026-10-24 10:00:00', 'updated_at' => '2026-10-24 10:00:00'];
        $supplier = DB::table('suppliers')->insertGetId(['legal_name' => 'DEPLOY01 synthetic supplier', 'country' => 'ES', 'tax_id' => 'B12345678', ...$stamp]);
        $purchase = DB::table('purchase_documents')->insertGetId(['type' => 'supplier_invoice', 'supplier_id' => $supplier, 'number' => 'SYN-DEPLOY-PURCHASE', 'supplier_number' => 'EXT-DEPLOY-PURCHASE', 'status' => 'registered', 'issue_date' => '2026-10-24', 'subtotal' => '100.00', 'tax_amount' => '21.00', 'amount' => '121.00', 'paid_amount' => '20.10', ...$stamp]);
        DB::table('expenses')->insert(['expense_category_id' => DB::table('expense_categories')->value('id'), 'number' => 'SYN-DEPLOY-EXPENSE', 'type' => 'invoice', 'document_number' => 'EXT-DEPLOY-EXPENSE', 'issuer_country' => 'ES', 'issuer_tax_id' => 'B87654321', 'expense_date' => '2026-10-24', 'subtotal' => '35.00', 'tax_amount' => '7.35', 'amount' => '42.35', ...$stamp]);
        $invoiceId = DB::table('sales_documents')->insertGetId(['type' => 'invoice', 'number' => 'SYN-DEPLOY-SALE', 'status' => 'issued', 'issue_date' => '2026-10-24', 'subtotal' => '1.00', 'tax_amount' => '.21', 'amount' => '1.21', 'customer_snapshot' => json_encode(['name' => 'Synthetic client']), ...$stamp]);
        $paymentId = DB::table('supplier_payments')->insertGetId(['purchase_document_id' => $purchase, 'paid_at' => '2026-10-24', 'amount' => '20.10', 'method' => 'bank_transfer', ...$stamp]);
        $bank = DB::table('bank_accounts')->insertGetId(['name' => 'Synthetic incremental bank', 'currency' => 'EUR', ...$stamp]);
        $movement = DB::table('bank_transactions')->insertGetId(['bank_account_id' => $bank, 'booked_on' => '2026-10-24', 'amount' => '-20.10', 'description' => 'Synthetic partial payment', 'import_hash' => hash('sha256', 'incremental-bank'), ...$stamp]);
        DB::table('bank_reconciliations')->insert(['bank_transaction_id' => $movement, 'supplier_payment_id' => $paymentId, 'reconciled_at' => '2026-10-24 10:00:00', ...$stamp]);
        DB::table('document_deliveries')->where('id', $id)->update(['sales_document_id' => $invoiceId]);
        $fixturePdf = new Dompdf;
        $fixturePdf->loadHtml('<p>Synthetic pre-migration QA archive. Not a fiscal document.</p>');
        $fixturePdf->render();
        $bytes = $fixturePdf->output();
        $archivePath = 'synthetic-incremental/'.hash('sha256', $bytes).'.pdf';
        Storage::disk(config('filesystems.private'))->put($archivePath, $bytes);
        DB::table('sales_documents')->where('id', $invoiceId)->update(['archived_pdf_path' => $archivePath, 'archived_pdf_hash' => hash('sha256', $bytes)]);
        $entry = DB::table('journal_entries')->insertGetId(['number' => 'SYN-DEPLOY-JOURNAL', 'description' => 'Synthetic historical posted entry', 'entry_date' => '2026-10-24', 'status' => 'posted', 'source_type' => 'App\\Models\\PurchaseDocument', 'source_id' => $purchase, ...$stamp]);
        $accounts = DB::table('ledger_accounts')->orderBy('id')->limit(2)->pluck('id');
        DB::table('journal_lines')->insert([['journal_entry_id' => $entry, 'ledger_account_id' => $accounts[0], 'debit' => '121.00', 'credit' => '0.00', ...$stamp], ['journal_entry_id' => $entry, 'ledger_account_id' => $accounts[1], 'debit' => '0.00', 'credit' => '121.00', ...$stamp]]);
        DB::table('audit_logs')->insert(['event' => 'synthetic_historical_fixture', 'auditable_type' => 'App\\Models\\PurchaseDocument', 'auditable_id' => $purchase, 'new_values' => json_encode(['amount' => '121.00']), ...$stamp]);
        $volume = getenv('DEPLOY02_VOLUME') === '1';
        if ($volume) {
            for ($batch = 0; $batch < 20; $batch++) {
                $rows = [];
                for ($n = 0; $n < 500; $n++) {
                    $rows[] = ['recipient_email' => 'bulk@qa.invalid', 'token_hash' => hash('sha256', 'deploy02-'.$batch.'-'.$n), 'status' => 'sent', 'expires_at' => '2026-10-24 10:00:00', ...$stamp];
                }
                DB::table('document_deliveries')->insert($rows);
            }
            for ($batch = 0; $batch < 4; $batch++) {
                $rows = [];
                for ($n = 0; $n < 500; $n++) {
                    $rows[] = ['type' => 'supplier_invoice', 'supplier_id' => $supplier, 'number' => 'SYN-BULK-'.$batch.'-'.$n, 'supplier_number' => 'EXT-BULK-'.$batch.'-'.$n, 'status' => 'registered', 'issue_date' => '2026-10-24', 'subtotal' => '100.00', 'tax_amount' => '21.00', 'amount' => '121.00', ...$stamp];
                }
                DB::table('purchase_documents')->insert($rows);
            }
        }
        $tables = ['document_deliveries', 'document_series', 'fiscal_years', 'ledger_accounts', 'audit_logs', 'purchase_documents', 'expenses', 'sales_documents', 'journal_entries', 'journal_lines', 'supplier_payments', 'bank_accounts', 'bank_transactions', 'bank_reconciliations', 'company_profiles', 'suppliers'];
        $before = [];
        foreach ($tables as $table) {
            $before[$table] = DB::table($table)->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();
        }
        DB::statement("SET lock_timeout='2s'");
        DB::statement("SET statement_timeout='60s'");
        $blocking = null;
        if ($volume) {
            $worker = new Process([PHP_BINARY, base_path('tests/Support/deployment-lock-worker.php')], base_path(), timeout: 15);
            $worker->start();
            $ready = $worker->waitUntil(fn ($type, $output) => str_contains($output, 'ready'));
            $this->assertTrue($ready, $worker->getErrorOutput());
            $blockedAt = hrtime(true);
            try {
                Artisan::call('migrate', ['--force' => true]);
                $this->fail('DDL must time out rather than waiting indefinitely.');
            } catch (QueryException $exception) {
                $this->assertSame('55P03', $exception->errorInfo[0]);
                $blocking = ['sqlstate' => '55P03', 'wait_ms' => intdiv(hrtime(true) - $blockedAt, 1000000), 'retained_rows' => DB::table('document_deliveries')->count()];
                $this->assertSame(10001, $blocking['retained_rows']);
            } finally {
                $worker->wait();
                $this->assertTrue($worker->isSuccessful(), $worker->getErrorOutput());
            }
        }
        $start = hrtime(true);
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
        $milliseconds = intdiv(hrtime(true) - $start, 1000000);
        foreach ($before as $table => $rows) {
            foreach ($rows as $row) {
                $restored = (array) DB::table($table)->where('id', $row['id'])->first();
                $this->assertSame($row, array_intersect_key($restored, $row), $table);
            }
            $this->assertSame(count($rows), DB::table($table)->count(), $table);
        }
        $restoredInvoice = DB::table('sales_documents')->where('id', $invoiceId)->first();
        $this->assertSame($bytes, Storage::disk(config('filesystems.private'))->get($restoredInvoice->archived_pdf_path));
        $this->assertSame(hash('sha256', $bytes), $restoredInvoice->archived_pdf_hash);
        $this->assertSame($invoiceId, DB::table('document_deliveries')->where('id', $id)->value('sales_document_id'));
        $this->assertNull(DB::table('document_deliveries')->where('id', $id)->value('expires_at_utc'));
        $this->assertSame(0, DB::table('received_invoice_identities')->count());
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
        $this->assertSame(1, Artisan::call('time:audit-instants', ['--json' => true, '--strict' => true]));
        $temporal = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(1, $temporal['fields']['document_deliveries.expires_at']['ambiguous']);
        file_put_contents(sys_get_temp_dir().'/deploy01-incremental-proof.json', json_encode(['migration_ms' => $milliseconds, 'volume_profile' => $volume ? '10000 additional deliveries and 2000 additional supplier invoices' : 'small fixture', 'ddl_blocking_probe' => $blocking, 'lock_timeout' => '2s', 'statement_timeout' => '60s', 'history_tables' => $tables, 'history_unchanged' => true, 'reconciliation_preserved' => true, 'document_file_reference_and_sha256_preserved' => true, 'ambiguous_legacy_not_backfilled' => true, 'repeat_migrate_success' => true, 'temporal_report' => $temporal], JSON_PRETTY_PRINT));
    }
}

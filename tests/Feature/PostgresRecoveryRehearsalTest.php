<?php

namespace Tests\Feature;

use App\Models\BackupRestoration;
use App\Models\BankAccount;
use App\Models\BankReconciliation;
use App\Models\BankTransaction;
use App\Models\DatabaseBackup;
use App\Models\DocumentDelivery;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Person;
use App\Models\PurchaseDocument;
use App\Models\SalesDocument;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Accounting\AccountingSynchronizer;
use App\Services\Accounting\IssuedInvoiceCorrection;
use App\Services\Backup\BackupCipher;
use App\Services\Backup\PostgresBackupService;
use App\Services\Backup\PostgresConnection;
use App\Services\Backup\PostgresRestoreVerifier;
use App\Services\Documents\DocumentPdf;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class PostgresRecoveryRehearsalTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        if (getenv('STAB03_RECOVERY_REHEARSAL') !== '1' || DB::getDriverName() !== 'pgsql'
            || DB::connection()->getDatabaseName() !== 'arevantes_stab03_recovery_test'
            || ! in_array(config('database.connections.'.DB::getDefaultConnection().'.host'), ['127.0.0.1', 'localhost', '::1'], true)) {
            $this->markTestSkipped('Requires the explicitly provisioned synthetic local recovery source/target.');
        }
        $cluster = new Process(['container', 'exec', 'apple-dev-postgres', 'psql', '-U', 'developer', '-d', 'postgres', '-Atc', 'SELECT system_identifier::text FROM pg_control_system()']);
        $cluster->mustRun();
        $this->assertSame(trim($cluster->getOutput()), DB::selectOne('SELECT system_identifier::text AS id FROM pg_control_system()')->id);
        $this->assertSame('arevantes:synthetic-recovery-source:v1', DB::selectOne("SELECT shobj_description(oid, 'pg_database') AS marker FROM pg_database WHERE datname = current_database()")->marker);
        $this->artisan('migrate:fresh', ['--seed' => true, '--force' => true])->assertSuccessful();
        $this->directory = sys_get_temp_dir().'/arevantes-stab03-recovery-'.bin2hex(random_bytes(8));
        File::makeDirectory($this->directory);
        foreach (['pg_dump', 'pg_restore', 'psql'] as $tool) {
            symlink(base_path('tests/Support/recovery-pg-client.py'), $this->directory.'/'.$tool);
        }
        $connection = config('database.connections.'.DB::getDefaultConnection());
        $target = [...$connection, 'url' => null, 'database' => 'arevantes_stab03_restore_verify_test'];
        config(['database.connections.rehearsal_target' => $target,
            'backup.postgres.enabled' => true, 'backup.postgres.restore_enabled' => true, 'backup.postgres.allow_same_host_restore' => true,
            'backup.postgres.source_connection' => DB::getDefaultConnection(), 'backup.postgres.restore_connection' => 'rehearsal_target',
            'backup.postgres.disk' => 'rehearsal-backups', 'backup.postgres.encryption_key' => 'base64:'.base64_encode(random_bytes(32)),
            'backup.postgres.pg_dump_binary' => $this->directory.'/pg_dump', 'backup.postgres.pg_restore_binary' => $this->directory.'/pg_restore', 'backup.postgres.psql_binary' => $this->directory.'/psql',
            'filesystems.disks.rehearsal-backups' => ['driver' => 'local', 'root' => $this->directory.'/backups', 'throw' => true],
            'filesystems.disks.rehearsal-documents' => ['driver' => 'local', 'root' => $this->directory.'/documents', 'throw' => true],
            'filesystems.private' => 'rehearsal-documents']);
        $this->assertSame('arevantes:disposable-restore-verification:v1', DB::connection('rehearsal_target')->selectOne("SELECT shobj_description(oid, 'pg_database') AS marker FROM pg_database WHERE datname = current_database()")->marker);
    }

    protected function tearDown(): void
    {
        if (isset($this->directory)) {
            File::deleteDirectory($this->directory);
        }
        DB::purge('rehearsal_target');
        parent::tearDown();
    }

    public function test_real_encrypted_backup_restore_preserves_database_guards_and_independently_recovers_original_files(): void
    {
        $actor = User::factory()->create(['role' => 'admin']);
        $this->actingAs($actor);
        $customer = Person::create(['category' => 'customer', 'first_name' => 'Synthetic recovery customer']);
        $invoice = SalesDocument::create(['type' => 'invoice', 'person_id' => $customer->id, 'number' => 'SYN-RECOVERY-DRAFT', 'status' => 'draft', 'issue_date' => today(), 'subtotal' => 1, 'tax_amount' => '.21', 'amount' => '1.21', 'paid_amount' => 0, 'customer_snapshot' => $customer->customerSnapshot()]);
        $invoice->lines()->create(['position' => 1, 'description' => 'Synthetic three-decimal line', 'quantity' => 3, 'unit_price' => '.333', 'subtotal' => 1, 'discount_percent' => 0, 'tax_percent' => 21, 'tax_amount' => '.21', 'total' => '1.21']);
        $this->post(route('sales-documents.issue', $invoice), ['issue_date' => today()->toDateString()])->assertSessionHasNoErrors();
        $invoice->refresh();
        $this->post(route('sales-documents.payments.store', $invoice), ['paid_at' => today()->toDateString(), 'amount' => '.50', 'method' => 'cash'])->assertSessionHasNoErrors();
        $supplier = Supplier::create(['legal_name' => 'Synthetic recovery supplier', 'tax_id' => 'B87654321', 'country' => 'ES']);
        $purchase = PurchaseDocument::create(['type' => 'supplier_invoice', 'supplier_id' => $supplier->id, 'number' => 'SYN-RECOVERY-PURCHASE', 'supplier_number' => 'EXT-RECOVERY', 'status' => 'registered', 'issue_date' => today(), 'subtotal' => 100, 'tax_amount' => 21, 'amount' => 121, 'paid_amount' => 20]);
        $expense = Expense::create(['expense_category_id' => ExpenseCategory::firstOrFail()->id, 'type' => 'invoice', 'number' => 'SYN-RECOVERY-EXPENSE', 'issuer_country' => 'ES', 'issuer_tax_id' => 'B11111111', 'issuer_legal_name' => 'Synthetic expense issuer', 'document_number' => 'EXP-RECOVERY', 'expense_date' => today(), 'status' => 'pending', 'subtotal' => 35, 'tax_amount' => '7.35', 'amount' => '42.35', 'paid_amount' => 0]);
        $accounting = app(AccountingSynchronizer::class);
        $accounting->syncPurchaseDocument($purchase, $actor->id);
        $accounting->syncExpense($expense, $actor->id);
        $payment = $purchase->payments()->create(['amount' => 20, 'paid_at' => today(), 'method' => 'bank_transfer']);
        $accounting->syncSupplierPayment($payment, $actor->id);
        $bank = BankAccount::create(['name' => 'Synthetic recovery bank', 'currency' => 'EUR']);
        $transaction = BankTransaction::create(['bank_account_id' => $bank->id, 'booked_on' => today(), 'amount' => -20, 'description' => 'Synthetic bank payment', 'import_hash' => hash('sha256', 'recovery-transaction')]);
        BankReconciliation::create(['bank_transaction_id' => $transaction->id, 'supplier_payment_id' => $payment->id, 'reconciled_at' => now()]);
        foreach (['2026-10-25T00:30:00Z', '2026-10-25T01:30:00Z'] as $index => $instant) {
            DocumentDelivery::create(['sales_document_id' => $invoice->id, 'sent_by' => $actor->id, 'recipient_email' => 'synthetic'.$index.'@example.test', 'token_hash' => hash('sha256', 'synthetic-token-'.$index), 'status' => 'sent', 'expires_at' => CarbonImmutable::parse($instant)]);
        }
        $invoice->refresh();
        $previous = ['path' => $invoice->archived_pdf_path, 'hash' => $invoice->archived_pdf_hash, 'bytes' => Storage::disk('rehearsal-documents')->get($invoice->archived_pdf_path)];
        $this->put(route('sales-documents.update-issued', $invoice), [...$invoice->only(['type', 'person_id', 'installation_id']), 'issue_date' => $invoice->issue_date->toDateString(), 'revision' => IssuedInvoiceCorrection::revision($invoice), 'correction_reason' => 'Synthetic recovery version test', 'notes' => 'Synthetic second archived version', 'lines' => $invoice->lines()->get()->map->only(['id', 'product_id', 'unit_id', 'description', 'quantity', 'unit_price', 'discount_percent', 'tax_percent'])->all()])->assertRedirect()->assertSessionHasNoErrors();
        $invoice->refresh();
        $this->assertNotSame($previous['path'], $invoice->archived_pdf_path);
        $tables = ['document_series', 'fiscal_years', 'ledger_accounts', 'suppliers', 'people', 'company_profiles', 'migrations', 'sales_documents', 'sales_document_lines', 'sales_document_payments', 'purchase_documents', 'expenses', 'supplier_payments', 'bank_transactions', 'bank_reconciliations', 'journal_entries', 'journal_lines', 'received_invoice_identities', 'audit_logs', 'document_deliveries'];
        $expected = [];
        foreach ($tables as $table) {
            $expected[$table] = hash('sha256', DB::table($table)->orderBy('id')->get()->toJson());
        }
        $inventory = function ($database): array {
            $constraints = $database->select("SELECT t.relname AS table_name, c.conname AS name, c.contype AS kind, c.convalidated AS validated, pg_get_constraintdef(c.oid, true) AS definition FROM pg_constraint c JOIN pg_class t ON t.oid=c.conrelid JOIN pg_namespace n ON n.oid=t.relnamespace WHERE n.nspname='public' ORDER BY t.relname,c.conname");
            $indexes = $database->select("SELECT tablename,indexname,indexdef FROM pg_indexes WHERE schemaname='public' ORDER BY tablename,indexname");
            $triggers = $database->select("SELECT c.relname,t.tgname,t.tgenabled,pg_get_triggerdef(t.oid,true) AS definition FROM pg_trigger t JOIN pg_class c ON c.oid=t.tgrelid JOIN pg_namespace n ON n.oid=c.relnamespace WHERE n.nspname='public' AND NOT t.tgisinternal ORDER BY c.relname,t.tgname");

            $columns = $database->select("SELECT table_name,column_name,data_type,udt_name,numeric_precision,numeric_scale,is_nullable,datetime_precision,column_default FROM information_schema.columns WHERE table_schema='public' ORDER BY table_name,column_name");
            File::put('/tmp/stab03-schema-'.$database->getDatabaseName().'.json', json_encode(compact('constraints', 'indexes', 'triggers', 'columns'), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
            // pg_dump reparses varchar literal arrays as individual text coercions.
            // Canonicalize only unbounded string-literal coercions, never numeric casts,
            // column types, predicates, constraint names or enabled/validated flags.
            $normalize = function (?string $sql): ?string {
                if ($sql === null) {
                    return null;
                }
                $literal = "'(?:[^']|'')*'";
                $sql = preg_replace('/('.$literal.')::character varying(?!\\s*\\()/', '$1', $sql);
                $sql = preg_replace('/\\(('.$literal.')\\)::text/', '$1', $sql);
                $sql = preg_replace('/('.$literal.')::text/', '$1', $sql);
                $array = 'ARRAY\\[\\s*'.$literal.'(?:\\s*,\\s*'.$literal.')*\\s*\\]';
                $sql = preg_replace('/\\(('.$array.')\\)::text\\[\\]/', '$1', $sql);

                return preg_replace('/('.$array.')::text\\[\\]/', '$1', $sql);
            };
            foreach ($constraints as $row) {
                $row->definition = $normalize($row->definition);
            }
            foreach ($indexes as $row) {
                $row->indexdef = $normalize($row->indexdef);
            }
            foreach ($columns as $row) {
                $row->column_default = $normalize($row->column_default);
            }

            return array_map(fn ($rows) => hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR)), compact('constraints', 'indexes', 'triggers', 'columns'));
        };
        $expectedSchema = $inventory(DB::connection());
        $original = app(DocumentPdf::class)->render($invoice, 'invoice');
        $archive = $invoice->archived_pdf_path;
        $bundle = $this->directory.'/independent-file-backup';
        File::copyDirectory($this->directory.'/documents', $bundle);
        $backup = app(PostgresBackupService::class)->create();
        // Only synthetic files in this test-owned directory are removed.
        Storage::disk('rehearsal-documents')->delete($archive);
        $restoredFiles = $this->directory.'/recovered-files';
        File::copyDirectory($bundle, $restoredFiles);
        $test = $this;
        $verifier = new class(app(BackupCipher::class), app(PostgresConnection::class), $test, $expected, $expectedSchema, $inventory, $invoice->id, $original, $restoredFiles, $previous) extends PostgresRestoreVerifier
        {
            public function __construct($cipher, $connections, private $test, private array $expected, private array $expectedSchema, private $inventory, private int $invoiceId, private string $original, private string $files, private array $previous)
            {
                parent::__construct($cipher, $connections);
            }

            protected function validateRestoredDatabase(string $connection): array
            {
                $metrics = parent::validateRestoredDatabase($connection);
                $database = DB::connection($connection);
                foreach ($this->expected as $table => $hash) {
                    $this->test->assertSame($hash, hash('sha256', $database->table($table)->orderBy('id')->get()->toJson()), $table.' differs after restore');
                }
                $this->test->assertSame($this->expectedSchema, ($this->inventory)($database));
                foreach (['purchase_received_identity', 'expense_received_identity', 'supplier_payment_received_identity', 'journal_received_identity', 'reconciliation_received_identity', 'received_identity_preserve'] as $trigger) {
                    $this->test->assertContains($trigger, $metrics['enabled_triggers']);
                }
                $this->test->assertContains('purchase_received_identity', $metrics['enabled_triggers']);
                $this->test->assertContains('received_identity_preserve', $metrics['enabled_triggers']);
                $precise = $database->selectOne("SELECT count(*) AS total FROM pg_trigger WHERE tgname='precise_instants_guard' AND tgenabled <> 'D'");
                $this->test->assertSame(10, (int) $precise->total);
                $purchase = $database->table('purchase_documents')->first();
                try {
                    $database->transaction(fn () => $database->table('expenses')->insert(['expense_category_id' => 1, 'number' => 'SYN-DUP-AFTER-RESTORE', 'type' => 'invoice', 'issuer_country' => 'ES', 'issuer_tax_id' => 'B87654321', 'document_number' => 'EXT-RECOVERY', 'expense_date' => '2026-10-26', 'subtotal' => 100, 'tax_amount' => 21, 'amount' => 121]));
                    $this->test->fail('Restored identity guard must reject a second obligation.');
                } catch (QueryException $exception) {
                    $this->test->assertSame('23505', $exception->errorInfo[0]);
                }
                try {
                    $database->transaction(fn () => $database->table('journal_lines')->insert(['journal_entry_id' => -999, 'ledger_account_id' => 1, 'debit' => 1, 'credit' => 0]));
                    $this->test->fail('Restored foreign keys must reject orphan journal lines.');
                } catch (QueryException $exception) {
                    $this->test->assertSame('23503', $exception->errorInfo[0]);
                }
                $delivery = $database->table('document_deliveries')->first();
                try {
                    $database->transaction(fn () => $database->table('document_deliveries')->where('id', $delivery->id)->update(['expires_at_utc' => null]));
                    $this->test->fail('Restored UTC evidence must not be cleared.');
                } catch (QueryException $exception) {
                    $this->test->assertSame('23514', $exception->errorInfo[0]);
                }
                $document = SalesDocument::on($connection)->findOrFail($this->invoiceId);
                $this->test->assertSame('historical_original_missing', app(DocumentPdf::class)->custody($document));
                Storage::set('rehearsal-documents', Storage::build(['driver' => 'local', 'root' => $this->files, 'throw' => true]));
                $this->test->assertSame($this->original, app(DocumentPdf::class)->render($document, 'invoice'));
                $audit = $database->table('audit_logs')->where('event', 'issued_invoice_corrected')->first();
                $this->test->assertNotNull($audit);
                $history = json_decode($audit->old_values, true, flags: JSON_THROW_ON_ERROR);
                $this->test->assertSame($this->previous['path'], $history['archived_pdf_path']);
                $previousBytes = Storage::disk('rehearsal-documents')->get($history['archived_pdf_path']);
                $this->test->assertSame($this->previous['bytes'], $previousBytes);
                $this->test->assertSame($history['archived_pdf_hash'], hash('sha256', $previousBytes));
                Storage::disk('rehearsal-documents')->put($document->archived_pdf_path, 'synthetic altered file');
                $this->test->assertSame('integrity_failed', app(DocumentPdf::class)->custody($document));
                Storage::disk('rehearsal-documents')->delete($document->archived_pdf_path);
                $this->test->assertSame('historical_original_missing', app(DocumentPdf::class)->custody($document));

                return $metrics + ['schema_fingerprints_verified' => $this->expectedSchema, 'foreign_key_probe_rejected' => true, 'synthetic_domain_tables_verified' => count($this->expected), 'independent_pdf_rehearsal' => ['original_bytes_recovered' => true, 'previous_version_and_audit_reference_verified' => true, 'database_file_snapshot_consistent' => true, 'altered_rejected' => true, 'missing_detected' => true]];
            }
        };
        $result = $verifier->verify($backup);
        $this->assertSame('healthy', $result->status);
        $this->assertFalse($result->metrics['pdf_files_recovered']);
        $this->assertTrue($result->metrics['independent_pdf_rehearsal']['original_bytes_recovered']);
        $this->assertIsInt($result->rpo_seconds);
        $this->assertIsInt($result->rto_seconds);
        $this->assertIsInt($result->metrics['rto_microseconds']);
        $this->assertSame(0, (int) DB::connection('rehearsal_target')->selectOne("SELECT count(*) AS total FROM pg_tables WHERE schemaname='public'")->total);
        File::put('/tmp/stab03-recovery-proof.json', json_encode(['status' => $result->status, 'metrics' => $result->metrics, 'rpo_seconds' => $result->rpo_seconds, 'rto_seconds' => $result->rto_seconds, 'target_wiped' => true], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
    }

    public function test_an_unmarked_target_is_not_touched_even_when_its_name_looks_disposable(): void
    {
        $target = DB::connection('rehearsal_target');
        $target->statement('CREATE TABLE IF NOT EXISTS public.synthetic_sentinel(id integer)');
        $target->table('synthetic_sentinel')->insert(['id' => 42]);
        $target->statement('COMMENT ON DATABASE arevantes_stab03_restore_verify_test IS NULL');
        try {
            $backup = DatabaseBackup::create(['engine' => 'pgsql', 'status' => 'success', 'disk' => 'rehearsal-backups', 'path' => 'not-read', 'started_at' => now(), 'completed_at' => now()]);
            try {
                app(PostgresRestoreVerifier::class)->verify($backup);
                $this->fail('An unmarked database must never be reset.');
            } catch (\RuntimeException $exception) {
                $this->assertStringContainsString('not an authorized disposable', $exception->getMessage());
            }
            $this->assertSame(1, $target->table('synthetic_sentinel')->where('id', 42)->count());
        } finally {
            $target->statement("COMMENT ON DATABASE arevantes_stab03_restore_verify_test IS 'arevantes:disposable-restore-verification:v1'");
        }
    }

    public function test_corrupted_encrypted_backup_fails_before_destroying_target_data(): void
    {
        $target = DB::connection('rehearsal_target');
        $target->statement('CREATE TABLE IF NOT EXISTS public.synthetic_sentinel(id integer)');
        $target->table('synthetic_sentinel')->insert(['id' => 43]);
        $backup = app(PostgresBackupService::class)->create();
        $bytes = Storage::disk('rehearsal-backups')->get($backup->path);
        $bytes[strlen($bytes) - 2] = chr(ord($bytes[strlen($bytes) - 2]) ^ 255);
        Storage::disk('rehearsal-backups')->put($backup->path, $bytes);
        try {
            app(PostgresRestoreVerifier::class)->verify($backup);
            $this->fail('Corrupt backup must not reset target.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('checksum', $exception->getMessage());
        }
        $this->assertSame(1, $target->table('synthetic_sentinel')->where('id', 43)->count());
        $this->assertFalse(BackupRestoration::latest('id')->firstOrFail()->metrics['target_cleanup_failed']);
    }

    public static function partialFailures(): array
    {
        return ['authorized cleanup' => [false], 'authorization lost; preserve for review' => [true]];
    }

    #[DataProvider('partialFailures')]
    public function test_partial_restore_failure_is_recorded_and_cleanup_requires_unchanged_authorization(bool $loseMarker): void
    {
        $backup = app(PostgresBackupService::class)->create();
        $script = $this->directory.'/synthetic-failing-restore';
        $sql = 'CREATE TABLE public.synthetic_partial_restore(id integer);'.($loseMarker ? ' COMMENT ON DATABASE arevantes_stab03_restore_verify_test IS NULL;' : '');
        File::put($script, "#!/bin/sh\ncontainer exec apple-dev-postgres psql -U developer -d arevantes_stab03_restore_verify_test -v ON_ERROR_STOP=1 -c ".escapeshellarg($sql)." >/dev/null\necho 'synthetic partial restore failure' >&2\nexit 73\n");
        chmod($script, 0700);
        config(['backup.postgres.pg_restore_binary' => $script]);
        try {
            try {
                app(PostgresRestoreVerifier::class)->verify($backup);
                $this->fail('Injected restore failure must not succeed.');
            } catch (\RuntimeException $exception) {
                $this->assertStringContainsString('pg_restore failed with exit code 73', $exception->getMessage());
            }
            $result = BackupRestoration::latest('id')->firstOrFail();
            $this->assertSame('failed', $result->status);
            $this->assertSame($loseMarker, $result->metrics['target_cleanup_failed']);
            $tables = (int) DB::connection('rehearsal_target')->selectOne("SELECT count(*) AS total FROM pg_tables WHERE schemaname='public'")->total;
            $this->assertSame($loseMarker ? 1 : 0, $tables);
            $this->assertGreaterThan(0, DB::table('migrations')->count());
        } finally {
            $target = DB::connection('rehearsal_target');
            $target->statement("COMMENT ON DATABASE arevantes_stab03_restore_verify_test IS 'arevantes:disposable-restore-verification:v1'");
            if ($loseMarker) {
                $target->statement('DROP TABLE IF EXISTS public.synthetic_partial_restore');
            }
        }
    }

    public function test_a_second_verifier_cannot_reset_a_target_locked_by_another_session(): void
    {
        $target = DB::connection('rehearsal_target');
        $target->select("SELECT pg_advisory_lock(hashtextextended('arevantes:restore-target:arevantes_stab03_restore_verify_test', 0))");
        try {
            $backup = DatabaseBackup::create(['engine' => 'pgsql', 'status' => 'success', 'disk' => 'rehearsal-backups', 'path' => 'not-read', 'started_at' => now(), 'completed_at' => now()]);
            try {
                app(PostgresRestoreVerifier::class)->verify($backup);
                $this->fail('A competing verifier must not touch the target.');
            } catch (\RuntimeException $exception) {
                $this->assertStringContainsString('Another restore verification owns', $exception->getMessage());
            }
            $this->assertSame('failed', BackupRestoration::latest('id')->value('status'));
        } finally {
            $target->select("SELECT pg_advisory_unlock(hashtextextended('arevantes:restore-target:arevantes_stab03_restore_verify_test', 0))");
        }
    }

    public function test_restore_is_not_healthy_when_claimed_economic_guards_are_missing(): void
    {
        DB::statement('DROP TRIGGER purchase_received_identity ON purchase_documents');
        $backup = app(PostgresBackupService::class)->create();
        try {
            app(PostgresRestoreVerifier::class)->verify($backup);
            $this->fail('Migration rows cannot substitute for actual active guards.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('missing or disabled', $exception->getMessage());
        }
        $this->assertSame('failed', BackupRestoration::latest('id')->value('status'));
        $this->assertSame(0, (int) DB::connection('rehearsal_target')->selectOne("SELECT count(*) AS total FROM pg_tables WHERE schemaname='public'")->total);
    }
}

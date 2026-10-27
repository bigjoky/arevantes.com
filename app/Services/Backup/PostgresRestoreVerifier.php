<?php

namespace App\Services\Backup;

use App\Models\BackupRestoration;
use App\Models\DatabaseBackup;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

class PostgresRestoreVerifier
{
    public function __construct(
        private readonly BackupCipher $cipher,
        private readonly PostgresConnection $connections,
    ) {}

    public function verify(DatabaseBackup $backup): BackupRestoration
    {
        $targetName = (string) config('backup.postgres.restore_connection', 'backup_restore');
        if ($backup->engine !== 'pgsql' || $backup->status !== 'success') {
            throw new RuntimeException('Only successful PostgreSQL backups can be restored.');
        }

        $started = now();
        $monotonicStart = hrtime(true);
        $rpoMicros = $backup->completed_at && $backup->completed_at->lessThanOrEqualTo($started) ? RecoveryDuration::microseconds($backup->completed_at, $started) : null;
        $probeName = 'restore_probe_'.Str::lower(Str::random(16));
        $restoration = BackupRestoration::create([
            'database_backup_id' => $backup->id,
            'backup_name' => basename($backup->path),
            'engine' => 'pgsql',
            'status' => 'running',
            'environment' => 'isolated',
            'rpo_seconds' => $rpoMicros === null ? null : RecoveryDuration::seconds($rpoMicros),
            'metrics' => ['rpo_microseconds' => $rpoMicros, 'duration_precision' => 'microseconds', 'seconds_policy' => 'ceiling'],
        ]);
        $encrypted = null;
        $dump = null;
        $target = null;
        $targetTouched = false;
        $targetLockHeld = false;
        $targetBackend = null;

        $source = null;
        try {
            if ($rpoMicros === null) {
                throw new RuntimeException('Backup completion timestamp is missing or in the future.');
            }
            $source = $this->connections->parameters((string) config('backup.postgres.source_connection'));
            $target = $this->connections->parameters($targetName);
            $this->assertSafeTarget($source, $target);
            config()->set('database.connections.'.$probeName, ['driver' => 'pgsql', ...$target, 'url' => null, 'charset' => 'utf8', 'prefix' => '', 'search_path' => 'public']);
            $this->assertDisposableTarget($probeName, $target);
            $lock = DB::connection($probeName)->selectOne('SELECT pg_try_advisory_lock(hashtextextended(?, 0)) AS acquired, pg_backend_pid() AS backend', ['arevantes:restore-target:'.$target['database']]);
            if (! in_array($lock->acquired, [true, 't', 1, '1'], true)) {
                throw new RuntimeException('Another restore verification owns this disposable target.');
            }
            $targetLockHeld = true;
            $targetBackend = (int) $lock->backend;
            $encrypted = tempnam(sys_get_temp_dir(), 'arevantes-restore-encrypted-');
            $dump = tempnam(sys_get_temp_dir(), 'arevantes-restore-dump-');
            if ($encrypted === false || $dump === false) {
                throw new RuntimeException('Unable to allocate temporary restore files.');
            }
            $manifest = $this->downloadAndValidate($backup, $encrypted);
            $this->cipher->decrypt($encrypted, $dump);
            $this->assertDisposableTarget($probeName, $target, $targetBackend);
            $targetTouched = true;
            $this->resetTarget($target);
            $this->restore($target, $dump);
            $metrics = $this->validateRestoredDatabase($probeName);
            $rtoMicros = intdiv(hrtime(true) - $monotonicStart + 999, 1000);
            $this->assertDisposableTarget($probeName, $target, $targetBackend);
            $this->resetTarget($target);
            $targetTouched = false;

            $restoration->update([
                'status' => 'healthy',
                'details' => "PostgreSQL restore completed; {$metrics['tables']} tables and {$metrics['migrations']} migrations detected; isolated target wiped.",
                'rto_seconds' => RecoveryDuration::seconds($rtoMicros),
                'metrics' => $metrics + ['manifest_version' => $manifest['payload']['version'], 'rpo_microseconds' => $rpoMicros, 'rto_microseconds' => $rtoMicros, 'seconds_policy' => 'ceiling', 'pdf_files_recovered' => false],
                'verified_at' => now(),
            ]);

            return $restoration->refresh();
        } catch (Throwable $exception) {
            $cleanupFailed = false;
            if ($targetTouched && is_array($target)) {
                try {
                    $this->assertDisposableTarget($probeName, $target, $targetBackend);
                    $this->resetTarget($target);
                } catch (Throwable) {
                    $cleanupFailed = true;
                }
            }
            $message = Str::limit($this->safeMessage($exception, array_filter([$source['password'] ?? null, $target['password'] ?? null])).($cleanupFailed ? ' Isolated target cleanup also failed.' : ''), 1500, '');
            $rtoMicros = intdiv(hrtime(true) - $monotonicStart + 999, 1000);
            try {
                $restoration->update([
                    'status' => 'failed',
                    'details' => $message,
                    'rto_seconds' => RecoveryDuration::seconds($rtoMicros),
                    'metrics' => ['target_cleanup_failed' => $cleanupFailed, 'rpo_microseconds' => $rpoMicros, 'rto_microseconds' => $rtoMicros, 'seconds_policy' => 'ceiling'],
                    'verified_at' => now(),
                ]);
            } catch (Throwable $recordFailure) {
                $message .= ' Recovery result could not be persisted.';
                try {
                    logger()->error('Recovery result persistence failed', ['restoration_id' => $restoration->id, 'error_class' => $recordFailure::class, 'target_cleanup_failed' => $cleanupFailed]);
                } catch (Throwable) { /* Preserve the original failure even if both persistence channels fail. */
                }
            }
            throw new RuntimeException($message, previous: $exception);
        } finally {
            if ($targetLockHeld) {
                try {
                    DB::connection($probeName)->select('SELECT pg_advisory_unlock(hashtextextended(?, 0))', ['arevantes:restore-target:'.$target['database']]);
                } catch (Throwable) { /* Closing the session releases any surviving session lock. */
                }
            }
            DB::purge($probeName);
            config()->set('database.connections.'.$probeName, null);
            File::delete(array_filter([$encrypted, $dump], 'is_string'));
        }
    }

    /** @return array{payload: array<string, mixed>, signature: string} */
    private function downloadAndValidate(DatabaseBackup $backup, string $destination): array
    {
        $disk = Storage::disk($backup->disk);
        $manifestJson = $disk->get($backup->path.'.json');
        $manifest = json_decode($manifestJson, true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($manifest) || ! is_array($manifest['payload'] ?? null) || ! is_string($manifest['signature'] ?? null)) {
            throw new RuntimeException('The backup manifest is invalid.');
        }
        $payloadJson = json_encode($manifest['payload'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        if (! hash_equals($this->cipher->sign($payloadJson), $manifest['signature'])) {
            throw new RuntimeException('The backup manifest signature is invalid.');
        }
        if (($manifest['payload']['backup_id'] ?? null) !== $backup->id || ($manifest['payload']['sha256'] ?? null) !== $backup->checksum) {
            throw new RuntimeException('The backup manifest does not match its database record.');
        }

        if (($manifest['payload']['version'] ?? null) !== 1 || ($manifest['payload']['engine'] ?? null) !== 'pgsql'
            || ($manifest['payload']['format'] ?? null) !== 'pg_dump_custom_secretstream_xchacha20poly1305') {
            throw new RuntimeException('The backup format or manifest version is unsupported.');
        }
        $source = $this->connections->parameters((string) config('backup.postgres.source_connection'));
        if (! hash_equals(hash('sha256', strtolower($source['host']).'/'.$source['database']), (string) ($manifest['payload']['source_fingerprint'] ?? ''))) {
            throw new RuntimeException('The backup source fingerprint does not match the configured source.');
        }
        $input = $disk->readStream($backup->path);
        $output = fopen($destination, 'wb');
        if ($input === false || $output === false) {
            if (is_resource($input)) {
                fclose($input);
            }
            if (is_resource($output)) {
                fclose($output);
            }
            throw new RuntimeException('The encrypted backup could not be downloaded.');
        }
        try {
            if (stream_copy_to_stream($input, $output) === false) {
                throw new RuntimeException('The encrypted backup download was interrupted.');
            }
        } finally {
            fclose($input);
            fclose($output);
        }
        if (filesize($destination) !== ($manifest['payload']['bytes'] ?? null)) {
            throw new RuntimeException('The backup size does not match its manifest.');
        }
        if (! hash_equals($backup->checksum, hash_file('sha256', $destination) ?: '')) {
            throw new RuntimeException('The encrypted backup checksum is invalid.');
        }

        return $manifest;
    }

    /**
     * @param  array{host: string, port: string, database: string, username: string, password: string, sslmode: string}  $source
     * @param  array{host: string, port: string, database: string, username: string, password: string, sslmode: string}  $target
     */
    private function assertSafeTarget(array $source, array $target): void
    {
        if (! config('backup.postgres.restore_enabled')) {
            throw new RuntimeException('PostgreSQL restore verification is disabled.');
        }
        if (strcasecmp($source['database'], $target['database']) === 0 || strcasecmp((string) DB::connection()->getDatabaseName(), $target['database']) === 0) {
            throw new RuntimeException('The restore target resolves to the production database.');
        }
        if (strcasecmp($source['host'], $target['host']) === 0 && ! config('backup.postgres.allow_same_host_restore')) {
            throw new RuntimeException('The restore target must use an isolated PostgreSQL host.');
        }
        if (! preg_match('/^[a-z][a-z0-9_]*_restore_verify_test$/D', $target['database'])
            || preg_match('/(^|_)(prod|production|live|demo|staging|business)(_|$)/i', $target['database'])) {
            throw new RuntimeException('The restore target must be an explicitly disposable *_restore_verify_test database, never a business or demonstration database.');
        }
        $pattern = (string) config('backup.postgres.restore_database_pattern');
        if (@preg_match($pattern, $target['database']) !== 1) {
            throw new RuntimeException('The restore database name does not satisfy the configured isolation pattern.');
        }
    }

    /** Read-only authorization check; never creates the marker or changes target data. */
    private function assertDisposableTarget(string $connection, array $target, ?int $backend = null): void
    {
        $database = DB::connection($connection);
        $row = $database->selectOne("SELECT current_database() AS database, shobj_description(oid, 'pg_database') AS marker, pg_is_in_recovery() AS recovery, pg_backend_pid() AS backend FROM pg_database WHERE datname = current_database()");
        if (! $row || $row->database !== $target['database'] || $row->marker !== 'arevantes:disposable-restore-verification:v1' || ! in_array($row->recovery, [false, 'f', 0, '0'], true) || ($backend !== null && (int) $row->backend !== $backend)) {
            throw new RuntimeException('The actual restore destination is not an authorized disposable verification database.');
        }
    }

    /** @param array{host: string, port: string, database: string, username: string, password: string, sslmode: string} $target */
    private function resetTarget(array $target): void
    {
        $this->run([
            (string) config('backup.postgres.psql_binary', 'psql'),
            ...$this->connections->processArguments($target),
            '--set=ON_ERROR_STOP=1',
            '--command=DROP SCHEMA IF EXISTS public CASCADE; CREATE SCHEMA public;',
        ], $target, 'psql target reset');
    }

    /** @param array{host: string, port: string, database: string, username: string, password: string, sslmode: string} $target */
    private function restore(array $target, string $dump): void
    {
        $this->run([
            (string) config('backup.postgres.pg_restore_binary', 'pg_restore'),
            '--exit-on-error',
            '--no-owner',
            '--no-privileges',
            ...$this->connections->processArguments($target),
            $dump,
        ], $target, 'pg_restore');
    }

    /** @return array{tables: int, migrations: int} */
    protected function validateRestoredDatabase(string $connection): array
    {
        $database = DB::connection($connection);
        $migrationTable = $database->selectOne("select to_regclass('public.migrations') as relation");
        if (! $migrationTable || $migrationTable->relation === null) {
            throw new RuntimeException('The restored database does not contain the migrations table.');
        }
        $migrations = $database->table('migrations')->count();
        $tables = (int) ($database->selectOne("select count(*) as aggregate from pg_catalog.pg_tables where schemaname = 'public'")->aggregate ?? 0);
        if ($migrations < 1 || $tables < 2) {
            throw new RuntimeException('The restored PostgreSQL schema is incomplete.');
        }

        $constraints = $database->table('pg_constraint as c')->join('pg_namespace as n', 'n.oid', '=', 'c.connamespace')->where('n.nspname', 'public')->count();
        $indexes = $database->table('pg_indexes')->where('schemaname', 'public')->count();
        $triggers = $database->select("SELECT t.tgname AS name, c.relname AS table_name FROM pg_trigger t JOIN pg_class c ON c.oid=t.tgrelid JOIN pg_namespace n ON n.oid=c.relnamespace WHERE n.nspname='public' AND NOT t.tgisinternal AND t.tgenabled IN ('O','A') ORDER BY t.tgname");

        $applied = $database->table('migrations')->pluck('migration')->all();
        $required = [];
        if (in_array('2026_10_25_040000_guard_precise_instant_writes', $applied, true)) {
            foreach (['document_deliveries', 'customer_portal_accesses', 'work_order_attachments', 'portfolio_projects', 'planning_scenarios', 'opportunities', 'work_orders', 'time_entries', 'crm_activities', 'work_order_reschedules'] as $table) {
                $required[] = $table.':precise_instants_guard';
            }
        }
        if (in_array('2026_10_26_101000_guard_received_invoice_identity', $applied, true)) {
            $required = [...$required, 'purchase_documents:purchase_received_identity', 'expenses:expense_received_identity'];
        }
        if (in_array('2026_10_26_103000_guard_received_invoice_economic_effects', $applied, true)) {
            $required = [...$required, 'supplier_payments:supplier_payment_received_identity', 'journal_entries:journal_received_identity', 'bank_reconciliations:reconciliation_received_identity', 'received_invoice_identities:received_identity_preserve'];
        }
        $active = array_map(fn ($trigger) => $trigger->table_name.':'.$trigger->name, $triggers);
        if (array_diff($required, $active)) {
            throw new RuntimeException('The restored migrations claim temporal/economic guards which are missing or disabled.');
        }
        if (in_array('2026_10_26_100000_share_received_invoice_identity', $applied, true)) {
            $unique = $database->selectOne("SELECT i.indisunique AND i.indisvalid AND i.indisready AS valid FROM pg_index i JOIN pg_class c ON c.oid=i.indexrelid JOIN pg_namespace n ON n.oid=c.relnamespace WHERE n.nspname='public' AND c.relname='received_invoice_fiscal_identity_unique'");
            if (! $unique || ! in_array($unique->valid, [true, 't', 1, '1'], true)) {
                throw new RuntimeException('The restored fiscal identity unique index is missing or invalid.');
            }
        }

        return compact('tables', 'migrations', 'constraints', 'indexes') + ['enabled_triggers' => array_column($triggers, 'name'), 'validation_scope' => 'database_schema_inventory; domain and file recovery require the dedicated rehearsal'];
    }

    /**
     * @param  list<string>  $arguments
     * @param  array{password: string, sslmode: string}  $connection
     */
    private function run(array $arguments, array $connection, string $operation): void
    {
        $process = new Process(
            $arguments,
            null,
            $this->connections->processEnvironment($connection),
            null,
            max(60, (int) config('backup.postgres.timeout_seconds', 1800)),
        );
        $process->run();
        if (! $process->isSuccessful()) {
            $error = Str::limit(trim(preg_replace('/\s+/', ' ', $process->getErrorOutput()) ?? ''), 500, '');
            throw new RuntimeException("{$operation} failed with exit code {$process->getExitCode()}. {$error}");
        }
    }

    private function safeMessage(Throwable $exception, array $secrets = []): string
    {
        return preg_replace('/(password|pass|pwd)=([^\s;]+)/i', '$1=[redacted]', str_replace($secrets, '[redacted]', $exception->getMessage())) ?? 'Restore verification failed.';
    }
}

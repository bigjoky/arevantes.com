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
        $restoration = BackupRestoration::create([
            'database_backup_id' => $backup->id,
            'backup_name' => basename($backup->path),
            'engine' => 'pgsql',
            'status' => 'running',
            'environment' => 'isolated',
            'rpo_seconds' => $backup->completed_at?->diffInSeconds($started),
        ]);
        $encrypted = null;
        $dump = null;
        $target = null;
        $targetTouched = false;

        try {
            $source = $this->connections->parameters((string) config('backup.postgres.source_connection'));
            $target = $this->connections->parameters($targetName);
            $this->assertSafeTarget($source, $target);
            $encrypted = tempnam(sys_get_temp_dir(), 'arevantes-restore-encrypted-');
            $dump = tempnam(sys_get_temp_dir(), 'arevantes-restore-dump-');
            if ($encrypted === false || $dump === false) {
                throw new RuntimeException('Unable to allocate temporary restore files.');
            }
            $manifest = $this->downloadAndValidate($backup, $encrypted);
            $this->cipher->decrypt($encrypted, $dump);
            $targetTouched = true;
            $this->resetTarget($target);
            $this->restore($target, $dump);
            $metrics = $this->validateRestoredDatabase($targetName);
            $rto = $started->diffInSeconds(now());
            $this->resetTarget($target);
            $targetTouched = false;

            $restoration->update([
                'status' => 'healthy',
                'details' => "PostgreSQL restore completed; {$metrics['tables']} tables and {$metrics['migrations']} migrations detected; isolated target wiped.",
                'rto_seconds' => $rto,
                'metrics' => $metrics + ['manifest_version' => $manifest['payload']['version']],
                'verified_at' => now(),
            ]);

            return $restoration->refresh();
        } catch (Throwable $exception) {
            $cleanupFailed = false;
            if ($targetTouched && is_array($target)) {
                try {
                    $this->resetTarget($target);
                } catch (Throwable) {
                    $cleanupFailed = true;
                }
            }
            $message = Str::limit($this->safeMessage($exception).($cleanupFailed ? ' Isolated target cleanup also failed.' : ''), 1500, '');
            $restoration->update([
                'status' => 'failed',
                'details' => $message,
                'rto_seconds' => $started->diffInSeconds(now()),
                'metrics' => ['target_cleanup_failed' => $cleanupFailed],
                'verified_at' => now(),
            ]);
            throw new RuntimeException($message, previous: $exception);
        } finally {
            DB::purge($targetName);
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
        if (strcasecmp($source['host'], $target['host']) === 0 && strcasecmp($source['database'], $target['database']) === 0) {
            throw new RuntimeException('The restore target resolves to the production database.');
        }
        if (strcasecmp($source['host'], $target['host']) === 0 && ! config('backup.postgres.allow_same_host_restore')) {
            throw new RuntimeException('The restore target must use an isolated PostgreSQL host.');
        }
        $pattern = (string) config('backup.postgres.restore_database_pattern');
        if (@preg_match($pattern, $target['database']) !== 1) {
            throw new RuntimeException('The restore database name does not satisfy the configured isolation pattern.');
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
    private function validateRestoredDatabase(string $connection): array
    {
        DB::purge($connection);
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

        return compact('tables', 'migrations');
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

    private function safeMessage(Throwable $exception): string
    {
        return preg_replace('/(password|pass|pwd)=([^\s;]+)/i', '$1=[redacted]', $exception->getMessage()) ?? 'Restore verification failed.';
    }
}

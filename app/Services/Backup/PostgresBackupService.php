<?php

namespace App\Services\Backup;

use App\Models\DatabaseBackup;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

class PostgresBackupService
{
    public function __construct(
        private readonly BackupCipher $cipher,
        private readonly PostgresConnection $connections,
    ) {}

    public function create(): DatabaseBackup
    {
        $this->assertEnabled();
        $disk = (string) config('backup.postgres.disk');
        $prefix = (string) config('backup.postgres.prefix');
        $id = (string) Str::uuid();
        $filename = 'postgres-'.now()->utc()->format('Ymd-His').'-'.$id.'.dump.enc';
        $path = $prefix.'/'.$filename;
        $backup = DatabaseBackup::create([
            'id' => $id,
            'engine' => 'pgsql',
            'status' => 'running',
            'disk' => $disk,
            'path' => $path,
            'started_at' => now(),
            'expires_at' => now()->addDays(max(1, (int) config('backup.retention', 30))),
        ]);
        $dump = tempnam(sys_get_temp_dir(), 'arevantes-pgdump-');
        if ($dump === false) {
            $backup->update(['status' => 'failed', 'completed_at' => now(), 'error' => 'Unable to allocate a temporary backup file.']);
            throw new RuntimeException('Unable to allocate a temporary backup file.');
        }
        $encrypted = $dump.'.enc';

        try {
            $source = $this->connections->parameters((string) config('backup.postgres.source_connection'));
            $this->dump($source, $dump);
            $this->cipher->encrypt($dump, $encrypted);
            $checksum = hash_file('sha256', $encrypted);
            if ($checksum === false) {
                throw new RuntimeException('Unable to checksum the encrypted backup.');
            }

            $payload = [
                'version' => 1,
                'backup_id' => $id,
                'engine' => 'pgsql',
                'format' => 'pg_dump_custom_secretstream_xchacha20poly1305',
                'artifact' => $filename,
                'created_at' => now()->utc()->toIso8601String(),
                'bytes' => File::size($encrypted),
                'sha256' => $checksum,
                'source_fingerprint' => hash('sha256', strtolower($source['host']).'/'.$source['database']),
            ];
            $manifestJson = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
            $manifest = ['payload' => $payload, 'signature' => $this->cipher->sign($manifestJson)];

            $stream = fopen($encrypted, 'rb');
            if ($stream === false) {
                throw new RuntimeException('Unable to read the encrypted backup for upload.');
            }
            try {
                if (! Storage::disk($disk)->put($path, $stream)) {
                    throw new RuntimeException('The encrypted backup could not be written to private storage.');
                }
            } finally {
                fclose($stream);
            }
            if (! Storage::disk($disk)->put($path.'.json', json_encode($manifest, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT))) {
                Storage::disk($disk)->delete($path);
                throw new RuntimeException('The signed backup manifest could not be written to private storage.');
            }

            $backup->update([
                'status' => 'success',
                'bytes' => $payload['bytes'],
                'checksum' => $checksum,
                'manifest' => $manifest,
                'completed_at' => now(),
                'error' => null,
            ]);
            $this->pruneExpired();

            return $backup->refresh();
        } catch (Throwable $exception) {
            $backup->update([
                'status' => 'failed',
                'completed_at' => now(),
                'error' => Str::limit($this->safeMessage($exception), 1000, ''),
            ]);
            throw $exception;
        } finally {
            File::delete([$dump, $encrypted]);
        }
    }

    public function pruneExpired(): int
    {
        $pruned = 0;
        DatabaseBackup::query()
            ->where('status', 'success')
            ->where('expires_at', '<=', now())
            ->orderBy('expires_at')
            ->eachById(function (DatabaseBackup $backup) use (&$pruned) {
                $disk = Storage::disk($backup->disk);
                if ($disk->delete([$backup->path, $backup->path.'.json'])) {
                    $backup->update(['status' => 'expired']);
                    $pruned++;
                }
            });

        return $pruned;
    }

    private function assertEnabled(): void
    {
        $sourceConnection = (string) config('backup.postgres.source_connection');
        if (config("database.connections.{$sourceConnection}.driver") !== 'pgsql') {
            throw new RuntimeException('PostgreSQL logical backups require the pgsql database driver.');
        }
        if (! config('backup.postgres.enabled')) {
            throw new RuntimeException('PostgreSQL logical backups are disabled.');
        }
        $disk = (string) config('backup.postgres.disk');
        if ($disk === '' || ! config("filesystems.disks.{$disk}")) {
            throw new RuntimeException('POSTGRES_BACKUP_DISK must name a configured private filesystem disk.');
        }
        if (app()->environment('production') && config("filesystems.disks.{$disk}.driver") === 'local') {
            throw new RuntimeException('Production PostgreSQL backups cannot use ephemeral local storage.');
        }

        // Validate the dedicated key before pg_dump starts.
        $this->cipher->sign('backup-preflight');
    }

    /** @param array{host: string, port: string, database: string, username: string, password: string, sslmode: string} $source */
    private function dump(array $source, string $destination): void
    {
        $arguments = [
            (string) config('backup.postgres.pg_dump_binary', 'pg_dump'),
            '--format=custom',
            '--compress=6',
            '--no-owner',
            '--no-privileges',
            '--file='.$destination,
            ...$this->connections->processArguments($source),
        ];
        $process = new Process(
            $arguments,
            null,
            $this->connections->processEnvironment($source),
            null,
            max(60, (int) config('backup.postgres.timeout_seconds', 1800)),
        );
        $process->run();
        if (! $process->isSuccessful()) {
            throw new RuntimeException('pg_dump failed with exit code '.$process->getExitCode().'. '.$this->safeProcessError($process));
        }
        if (! is_file($destination) || File::size($destination) === 0) {
            throw new RuntimeException('pg_dump did not create a usable backup artifact.');
        }
    }

    private function safeProcessError(Process $process): string
    {
        return Str::limit(trim(preg_replace('/\s+/', ' ', $process->getErrorOutput()) ?? ''), 500, '');
    }

    private function safeMessage(Throwable $exception): string
    {
        return preg_replace('/(password|pass|pwd)=([^\s;]+)/i', '$1=[redacted]', $exception->getMessage()) ?? 'Backup failed.';
    }
}

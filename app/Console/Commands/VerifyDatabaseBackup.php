<?php

namespace App\Console\Commands;

use App\Models\BackupRestoration;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\File;
use PDO;
use Throwable;

class VerifyDatabaseBackup extends Command
{
    protected $signature = 'database:verify-backup {backup? : Backup filename; latest by default}';

    protected $description = 'Restore an encrypted backup into an isolated temporary database and run integrity checks';

    public function handle(): int
    {
        $directory = storage_path('app/private/backups');
        $name = $this->argument('backup');
        $path = $name ? $directory.'/'.basename($name) : collect(File::glob($directory.'/*.enc'))->sortByDesc(fn ($file) => File::lastModified($file))->first();
        if (! $path || ! is_file($path)) {
            $this->error('No backup is available to verify.');

            return self::FAILURE;
        }
        $temporary = tempnam(sys_get_temp_dir(), 'arevantes-restore-');
        try {
            File::put($temporary, Crypt::decryptString(File::get($path)));
            $pdo = new PDO('sqlite:'.$temporary, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $integrity = $pdo->query('PRAGMA integrity_check')->fetchColumn();
            $migrations = (int) $pdo->query("SELECT count(*) FROM sqlite_master WHERE type='table' AND name='migrations'")->fetchColumn();
            if ($integrity !== 'ok' || $migrations !== 1) {
                throw new \RuntimeException('Integrity or schema validation failed.');
            }
            BackupRestoration::create(['backup_name' => basename($path), 'status' => 'healthy', 'details' => 'SQLite integrity_check: ok; application schema detected.', 'verified_at' => now()]);
            $this->info('Backup restored and checked successfully in an isolated temporary database.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            BackupRestoration::create(['backup_name' => basename($path), 'status' => 'failed', 'details' => $exception->getMessage(), 'verified_at' => now()]);
            $this->error('Backup verification failed: '.$exception->getMessage());

            return self::FAILURE;
        } finally {
            if ($temporary) {
                File::delete($temporary);
            }
        }
    }
}

<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class BackupDatabase extends Command
{
    protected $signature = 'database:backup {--keep=30 : Number of daily backups to retain}';

    protected $description = 'Create an encrypted backup of the SQLite database';

    public function handle(): int
    {
        if (DB::getDriverName() !== 'sqlite') {
            $this->error('Encrypted application backups currently support SQLite only.');

            return self::FAILURE;
        }

        $database = DB::getDatabaseName();

        if (! is_file($database)) {
            $this->error('SQLite database file not found.');

            return self::FAILURE;
        }

        DB::statement('PRAGMA wal_checkpoint(FULL)');
        $directory = storage_path('app/private/backups');
        File::ensureDirectoryExists($directory);
        $path = $directory.'/database-'.now()->format('Y-m-d-His').'.sqlite.enc';
        File::put($path, Crypt::encryptString(File::get($database)));

        if ($disk = config('backup.mirror_disk')) {
            Storage::disk($disk)->put('crm-backups/'.basename($path), File::get($path));
        }

        collect(File::files($directory))
            ->sortByDesc(fn ($file) => $file->getMTime())
            ->slice(max(1, (int) ($this->option('keep') ?: config('backup.retention'))))
            ->each(fn ($file) => File::delete($file->getPathname()));

        $this->info("Encrypted backup created: {$path}");

        return self::SUCCESS;
    }
}

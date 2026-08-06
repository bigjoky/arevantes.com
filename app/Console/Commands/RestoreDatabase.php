<?php

namespace App\Console\Commands;

use App\Models\SecurityEvent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Throwable;

class RestoreDatabase extends Command
{
    protected $signature = 'database:restore {backup} {--force : Restore without interactive confirmation}';

    protected $description = 'Restore an encrypted SQLite backup after creating a safety backup';

    public function handle(): int
    {
        if (DB::getDriverName() !== 'sqlite') {
            $this->error('Restore currently supports SQLite only.');

            return self::FAILURE;
        }

        $path = storage_path('app/private/backups/'.basename($this->argument('backup')));
        if (! is_file($path)) {
            $this->error('Backup not found.');

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm('This replaces the current database. Continue?')) {
            return self::SUCCESS;
        }

        try {
            $contents = Crypt::decryptString(File::get($path));
        } catch (Throwable $exception) {
            $this->error('Backup decryption failed: '.$exception->getMessage());

            return self::FAILURE;
        }

        if (! str_starts_with($contents, 'SQLite format 3')) {
            $this->error('The decrypted file is not a valid SQLite database.');

            return self::FAILURE;
        }

        Artisan::call('database:backup');
        DB::disconnect();
        File::put(DB::getDatabaseName(), $contents);
        DB::reconnect();
        if (Schema::hasTable('security_events')) {
            SecurityEvent::create(['type' => 'backup_restored', 'severity' => 'critical', 'context' => ['backup' => basename($path), 'environment' => app()->environment()]]);
        }
        $this->info('Database restored. Restart queue and scheduler workers before continuing.');

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Services\Backup\PostgresBackupService;
use App\Services\Security\SecurityEventRecorder;
use Illuminate\Console\Command;
use Throwable;

class BackupPostgresDatabase extends Command
{
    protected $signature = 'database:backup-postgres';

    protected $description = 'Create an encrypted PostgreSQL logical backup in private object storage';

    public function handle(PostgresBackupService $backups, SecurityEventRecorder $events): int
    {
        try {
            $backup = $backups->create();
            $events->record('backup_created', null, [
                'backup_id' => $backup->id,
                'engine' => 'pgsql',
                'disk' => $backup->disk,
                'bytes' => $backup->bytes,
            ], 'warning');
            $this->info("Encrypted PostgreSQL backup created: {$backup->id}");

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $events->record('backup_creation_failed', null, [
                'engine' => 'pgsql',
                'error_class' => $exception::class,
            ], 'critical', true);
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}

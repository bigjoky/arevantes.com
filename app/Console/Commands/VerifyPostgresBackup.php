<?php

namespace App\Console\Commands;

use App\Models\DatabaseBackup;
use App\Services\Backup\PostgresRestoreVerifier;
use App\Services\Security\SecurityEventRecorder;
use Illuminate\Console\Command;
use Throwable;

class VerifyPostgresBackup extends Command
{
    protected $signature = 'database:verify-postgres-backup {backup? : Database backup UUID; latest successful by default}';

    protected $description = 'Restore a PostgreSQL backup to a disposable isolated target and validate it';

    public function handle(PostgresRestoreVerifier $verifier, SecurityEventRecorder $events): int
    {
        $backup = DatabaseBackup::query()
            ->where('engine', 'pgsql')
            ->where('status', 'success')
            ->when($this->argument('backup'), fn ($query, $id) => $query->whereKey($id))
            ->latest('completed_at')
            ->first();
        if (! $backup) {
            $this->error('No successful PostgreSQL backup is available to verify.');

            return self::FAILURE;
        }

        try {
            $restoration = $verifier->verify($backup);
            $events->record('backup_restore_verified', null, [
                'backup_id' => $backup->id,
                'restoration_id' => $restoration->id,
                'rpo_seconds' => $restoration->rpo_seconds,
                'rto_seconds' => $restoration->rto_seconds,
            ], 'warning');
            $this->info("Backup restored and verified in isolation. RPO {$restoration->rpo_seconds}s; RTO {$restoration->rto_seconds}s.");

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $events->record('backup_restore_failed', null, [
                'backup_id' => $backup->id,
                'error_class' => $exception::class,
            ], 'critical', true);
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}

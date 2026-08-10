<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

class MigratePrivateStorage extends Command
{
    protected $signature = 'storage:migrate-private
        {from=local : Source filesystem disk}
        {to=s3 : Destination filesystem disk}
        {--dry-run : Report the files without copying them}';

    protected $description = 'Copy private application files between filesystem disks without deleting the source';

    public function handle(): int
    {
        $fromName = (string) $this->argument('from');
        $toName = (string) $this->argument('to');
        if ($fromName === $toName) {
            $this->error('Source and destination disks must be different.');

            return self::FAILURE;
        }

        $source = Storage::disk($fromName);
        $destination = Storage::disk($toName);
        $files = $source->allFiles();
        $copied = 0;
        $skipped = 0;

        foreach ($files as $path) {
            try {
                $contents = $source->get($path);
                $hash = hash('sha256', $contents);
                if ($destination->exists($path) && hash_equals($hash, hash('sha256', $destination->get($path)))) {
                    $skipped++;

                    continue;
                }

                if (! $this->option('dry-run')) {
                    $destination->put($path, $contents);
                    if (! $destination->exists($path) || ! hash_equals($hash, hash('sha256', $destination->get($path)))) {
                        throw new \RuntimeException("Integrity verification failed for {$path}.");
                    }
                }
                $copied++;
            } catch (Throwable $exception) {
                $this->error("Failed {$path}: {$exception->getMessage()}");

                return self::FAILURE;
            }
        }

        $verb = $this->option('dry-run') ? 'would be copied' : 'copied';
        $this->info("{$copied} files {$verb}; {$skipped} unchanged files skipped.");

        return self::SUCCESS;
    }
}

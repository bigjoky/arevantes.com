<?php

namespace App\Console\Commands;

use App\Models\Installation;
use App\Services\Deye\DeyeCloudClient;
use Illuminate\Console\Command;
use Throwable;

class SyncDeyeInstallations extends Command
{
    protected $signature = 'deye:sync {installation?}';

    protected $description = 'Synchronize monitored installations with Deye Cloud';

    public function handle(DeyeCloudClient $deye): int
    {
        Installation::query()->whereNotNull('deye_station_id')
            ->when($this->argument('installation'), fn ($query, $id) => $query->whereKey($id))
            ->each(function (Installation $installation) use ($deye): void {
                try {
                    $deye->sync($installation);
                    $this->info("Synced installation {$installation->id}");
                } catch (Throwable $exception) {
                    report($exception);
                    $this->error("Installation {$installation->id}: {$exception->getMessage()}");
                }
            });

        return self::SUCCESS;
    }
}

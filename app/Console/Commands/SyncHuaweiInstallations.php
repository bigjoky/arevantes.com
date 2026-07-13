<?php

namespace App\Console\Commands;

use App\Models\Installation;
use App\Services\Huawei\FusionSolarClient;
use Illuminate\Console\Command;
use Throwable;

class SyncHuaweiInstallations extends Command
{
    protected $signature = 'huawei:sync {installation?}';

    protected $description = 'Synchronize installations with Huawei FusionSolar';

    public function handle(FusionSolarClient $client): int
    {
        $installations = Installation::whereNotNull('huawei_station_code')
            ->when($this->argument('installation'), fn ($query, $id) => $query->whereKey($id))
            ->get();

        try {
            $client->syncMany($installations);
            $this->info("Synced {$installations->count()} Huawei installations in one batch.");
        } catch (Throwable $exception) {
            report($exception);
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}

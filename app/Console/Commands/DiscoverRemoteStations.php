<?php

namespace App\Console\Commands;

use App\Services\Monitoring\RemoteStationCatalog;
use Illuminate\Console\Command;
use Throwable;

class DiscoverRemoteStations extends Command
{
    protected $signature = 'monitoring:discover {provider? : deye or huawei}';

    protected $description = 'Store the stations available from monitoring providers';

    public function handle(RemoteStationCatalog $catalog): int
    {
        $providers = $this->argument('provider') ? [$this->argument('provider')] : ['deye', 'huawei'];

        foreach ($providers as $provider) {
            if (! in_array($provider, ['deye', 'huawei'], true)) {
                $this->error("Unknown provider: {$provider}");

                return self::FAILURE;
            }

            try {
                $count = $catalog->refresh($provider);
                $this->info("Stored {$count} {$provider} stations.");
            } catch (Throwable $exception) {
                report($exception);
                $this->error(ucfirst($provider).": {$exception->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}

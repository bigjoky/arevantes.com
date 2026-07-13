<?php

namespace App\Services\Monitoring;

use App\Models\RemoteStation;
use App\Services\Deye\DeyeCloudClient;
use App\Services\Huawei\FusionSolarClient;

class RemoteStationCatalog
{
    public function __construct(
        private readonly DeyeCloudClient $deye,
        private readonly FusionSolarClient $huawei,
    ) {}

    public function refresh(string $provider): int
    {
        $stations = match ($provider) {
            'deye' => $this->deye->stations(true),
            'huawei' => $this->huawei->stations(true),
        };
        $seenAt = now();

        foreach ($stations as $station) {
            $normalized = $this->normalize($provider, $station);

            RemoteStation::updateOrCreate(
                [
                    'provider' => $provider,
                    'remote_identifier' => $normalized['remote_identifier'],
                ],
                [
                    ...$normalized,
                    'provider_data' => $station,
                    'last_seen_at' => $seenAt,
                    'discovered_at' => RemoteStation::query()
                        ->where('provider', $provider)
                        ->where('remote_identifier', $normalized['remote_identifier'])
                        ->value('discovered_at') ?? $seenAt,
                ],
            );
        }

        return count($stations);
    }

    /** @return array{remote_identifier: string, name: ?string, address: ?string, connection_status: ?string} */
    private function normalize(string $provider, array $station): array
    {
        return match ($provider) {
            'deye' => [
                'remote_identifier' => (string) $station['id'],
                'name' => $station['name'] ?? null,
                'address' => $station['address'] ?? null,
                'connection_status' => $station['connectionStatus'] ?? null,
            ],
            'huawei' => [
                'remote_identifier' => (string) $station['stationCode'],
                'name' => $station['stationName'] ?? null,
                'address' => $station['stationAddr'] ?? null,
                'connection_status' => isset($station['stationStatus']) ? (string) $station['stationStatus'] : null,
            ],
        };
    }
}

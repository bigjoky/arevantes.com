<?php

namespace App\Services\Deye;

use App\Models\Installation;
use App\Models\MonitoringReading;
use App\Services\Monitoring\MonitoringAlertManager;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class DeyeCloudClient
{
    public function configured(): bool
    {
        return collect(['app_id', 'app_secret', 'email', 'password'])
            ->every(fn (string $key) => filled(config("services.deye.{$key}")));
    }

    public function sync(Installation $installation): void
    {
        if (! $this->configured()) {
            throw new RuntimeException('Deye Cloud credentials are not configured.');
        }

        $stationId = $installation->deye_station_id;
        $stations = $this->stations();
        $station = collect($stations)->first(fn (array $item) => (string) ($item['id'] ?? '') === (string) $stationId);

        if (! $station) {
            throw new RuntimeException('The Deye station ID was not found in this account.');
        }

        $latest = $this->post('/v1.0/station/latest', ['stationId' => (int) $stationId]);
        $today = now(config('app.timezone'))->toDateString();
        $history = $this->post('/v1.0/station/history', [
            'stationId' => (int) $stationId,
            'granularity' => 2,
            'startAt' => $today,
            'endAt' => now(config('app.timezone'))->addDay()->toDateString(),
        ]);
        $alerts = $this->post('/v1.0/station/alertList', [
            'stationId' => (int) $stationId,
            'startTimestamp' => now()->subDays(7)->timestamp,
            'endTimestamp' => now()->timestamp,
            'page' => 1,
            'size' => 100,
        ]);

        $openAlerts = collect($alerts['stationAlertItems'] ?? [])->where('status', 1);
        $connection = $station['connectionStatus'] ?? 'NO_DEVICE';
        $status = match (true) {
            in_array($connection, ['NO_DEVICE', 'ALL_OFFLINE'], true) => 'disconnected',
            $connection === 'PARTIAL_OFFLINE' || $openAlerts->isNotEmpty() => 'fault',
            default => 'healthy',
        };
        $message = match ($status) {
            'disconnected' => 'Installation disconnected from Deye Cloud.',
            'fault' => $openAlerts->first()['alertName'] ?? 'A device is offline or reporting a fault.',
            default => 'Installation operating normally.',
        };

        $installation->update([
            'deye_station_name' => $station['name'] ?? null,
            'deye_status' => $status,
            'deye_current_power_kw' => ((float) ($latest['generationPower'] ?? $station['generationPower'] ?? 0)) / 1000,
            'deye_today_energy_kwh' => data_get($history, 'stationDataItems.0.generationValue', 0),
            'deye_status_message' => $message,
            'deye_data' => ['station' => $station, 'latest' => $latest, 'open_alerts' => $openAlerts->values()->all()],
            'deye_synced_at' => now(),
        ]);

        $reading = MonitoringReading::create([
            'installation_id' => $installation->id,
            'provider' => 'deye',
            'status' => $status,
            'current_power_kw' => ((float) ($latest['generationPower'] ?? $station['generationPower'] ?? 0)) / 1000,
            'today_energy_kwh' => data_get($history, 'stationDataItems.0.generationValue', 0),
            'status_message' => $message,
            'provider_data' => ['station' => $station, 'latest' => $latest, 'open_alerts' => $openAlerts->values()->all()],
            'recorded_at' => now(),
        ]);
        app(MonitoringAlertManager::class)->record($reading);
    }

    public function stations(bool $fresh = false): array
    {
        if (! $this->configured()) {
            throw new RuntimeException('Deye Cloud credentials are not configured.');
        }

        if ($fresh) {
            Cache::forget('deye:stations');
        }

        return Cache::remember(
            'deye:stations',
            now()->addMinutes(2),
            fn () => $this->post('/v1.0/station/list', ['page' => 1, 'size' => 200])['stationList'] ?? [],
        );
    }

    private function post(string $path, array $body): array
    {
        $response = Http::baseUrl(config('services.deye.url'))
            ->acceptJson()
            ->withToken($this->token())
            ->timeout(15)
            ->post($path, $body)
            ->throw()
            ->json();

        if (($response['success'] ?? false) !== true) {
            throw new RuntimeException($response['msg'] ?? 'Deye Cloud request failed.');
        }

        return $response;
    }

    private function token(): string
    {
        return Cache::remember('deye:access_token', now()->addMinutes(50), function (): string {
            $password = config('services.deye.password');
            $response = Http::baseUrl(config('services.deye.url'))
                ->acceptJson()
                ->timeout(15)
                ->post('/v1.0/account/token?appId='.urlencode(config('services.deye.app_id')), [
                    'appSecret' => config('services.deye.app_secret'),
                    'email' => config('services.deye.email'),
                    'password' => preg_match('/^[a-f0-9]{64}$/i', $password) ? $password : hash('sha256', $password),
                    'companyId' => filled(config('services.deye.company_id'))
                        ? (int) config('services.deye.company_id')
                        : null,
                ])->throw()->json();

            $token = $response['accessToken'] ?? null;
            if (! $token) {
                throw new RuntimeException($response['msg'] ?? 'Deye Cloud authentication failed.');
            }

            return $token;
        });
    }
}

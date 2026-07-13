<?php

namespace App\Services\Huawei;

use App\Models\Installation;
use App\Models\MonitoringReading;
use App\Services\Monitoring\MonitoringAlertManager;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class FusionSolarClient
{
    public function configured(): bool
    {
        return collect(['url', 'username', 'system_code'])->every(fn ($key) => filled(config("services.huawei.{$key}")));
    }

    public function sync(Installation $installation): void
    {
        $this->syncMany([$installation]);
    }

    /** @param iterable<Installation> $installations */
    public function syncMany(iterable $installations): void
    {
        $installations = collect($installations)->filter(fn (Installation $installation) => filled($installation->huawei_station_code));

        if ($installations->isEmpty()) {
            return;
        }

        $codes = $installations->pluck('huawei_station_code')->implode(',');
        $stations = $this->stations();
        $kpis = collect($this->post('/thirdData/getStationRealKpi', ['stationCodes' => $codes])['data'] ?? [])->keyBy('stationCode');
        $inverterKpis = $this->inverterKpis($codes);
        $alarms = Cache::remember('huawei:alarms:'.sha1($codes), now()->addMinutes(30), fn () => $this->post('/thirdData/getAlarmList', [
            'stationCodes' => $codes,
            'beginTime' => now()->subDay()->getTimestampMs(),
            'endTime' => now()->getTimestampMs(),
            'language' => 'es_ES',
            'levels' => '1,2,3,4',
        ])['data'] ?? []);

        foreach ($installations as $installation) {
            $code = $installation->huawei_station_code;
            $station = collect($stations)->firstWhere('stationCode', $code);
            $item = $kpis->get($code, []);
            $data = $item['dataItemMap'] ?? [];
            $inverters = $inverterKpis->get($code, collect());
            $currentPower = $inverters->isNotEmpty()
                ? $inverters->sum(fn (array $inverter) => (float) data_get($inverter, 'dataItemMap.active_power', 0))
                : ($data['inverter_power'] ?? $data['active_power'] ?? 0);
            $stationAlarms = collect($alarms)->filter(fn (array $alarm) => ($alarm['stationCode'] ?? $alarm['plantCode'] ?? null) === $code)->values()->all();
            $status = $stationAlarms ? 'fault' : ($item ? 'healthy' : 'disconnected');
            $message = match ($status) {
                'fault' => $stationAlarms[0]['alarmName'] ?? 'FusionSolar reports an active alarm.',
                'disconnected' => 'Installation disconnected from FusionSolar.',
                default => 'Installation operating normally.',
            };
            $payload = ['station' => $station, 'kpi' => $data, 'inverters' => $inverters->values()->all(), 'alarms' => $stationAlarms];

            $installation->update([
                'huawei_station_name' => data_get($station, 'stationName'),
                'huawei_status' => $status,
                'huawei_current_power_kw' => $currentPower,
                'huawei_today_energy_kwh' => $data['day_power'] ?? 0,
                'huawei_status_message' => $message,
                'huawei_data' => $payload,
                'huawei_synced_at' => now(),
            ]);

            $reading = MonitoringReading::create([
                'installation_id' => $installation->id,
                'provider' => 'huawei',
                'status' => $status,
                'current_power_kw' => $currentPower,
                'today_energy_kwh' => $data['day_power'] ?? 0,
                'status_message' => $message,
                'provider_data' => $payload,
                'recorded_at' => now(),
            ]);
            app(MonitoringAlertManager::class)->record($reading);
        }
    }

    private function inverterKpis(string $stationCodes): Collection
    {
        $devices = collect(Cache::remember(
            'huawei:devices:'.sha1($stationCodes),
            now()->addDay(),
            fn () => $this->post('/thirdData/getDevList', ['stationCodes' => $stationCodes])['data'] ?? [],
        ));
        $inverters = $devices->filter(fn (array $device) => filled($device['invType'] ?? null)
            || in_array((int) ($device['devTypeId'] ?? 0), [1, 38], true));

        $deviceStations = $inverters->mapWithKeys(fn (array $device) => [(string) $device['id'] => $device['stationCode'] ?? null]);

        return $inverters
            ->groupBy('devTypeId')
            ->flatMap(function ($devices, $devTypeId) {
                return collect($this->post('/thirdData/getDevRealKpi', [
                    'devIds' => $devices->pluck('id')->implode(','),
                    'devTypeId' => (int) $devTypeId,
                ])['data'] ?? []);
            })
            ->groupBy(fn (array $kpi) => $deviceStations->get((string) ($kpi['devId'] ?? '')));
    }

    public function stations(bool $fresh = false): array
    {
        if ($fresh) {
            Cache::forget('huawei:stations');
        }

        try {
            return Cache::remember('huawei:stations', now()->addHours(6), function (): array {
                $data = $this->post('/thirdData/getStationList', [
                    'pageNo' => 1,
                    'pageSize' => 100,
                ])['data'] ?? [];
                $stations = collect($data['list'] ?? $data)
                    ->map(fn (array $station) => [
                        ...$station,
                        'stationCode' => $station['stationCode'] ?? $station['plantCode'] ?? null,
                        'stationName' => $station['stationName'] ?? $station['plantName'] ?? null,
                        'stationAddr' => $station['stationAddr'] ?? $station['plantAddress'] ?? null,
                    ])
                    ->filter(fn (array $station) => filled($station['stationCode']))
                    ->values()
                    ->all();

                Cache::forever('huawei:stations:last_successful', $stations);

                return $stations;
            });
        } catch (RuntimeException $exception) {
            $lastSuccessful = Cache::get('huawei:stations:last_successful');

            if (! $fresh && str_contains($exception->getMessage(), 'error 407') && is_array($lastSuccessful)) {
                return $lastSuccessful;
            }

            throw $exception;
        }
    }

    private function post(string $path, array $body): array
    {
        $response = $this->send($path, $body);

        if ((string) ($response['failCode'] ?? '') === '305') {
            Cache::forget('huawei:session');
            $response = $this->send($path, $body);
        }

        if (($response['success'] ?? false) !== true) {
            $code = $response['failCode'] ?? 'unknown';
            $message = (string) $code === '407'
                ? 'Huawei API request limit reached. Please try refreshing later.'
                : ($response['message'] ?? 'request failed');

            throw new RuntimeException("FusionSolar error {$code}: {$message}");
        }

        return $response;
    }

    private function send(string $path, array $body): array
    {
        $session = $this->session();

        return Http::baseUrl(config('services.huawei.url'))->acceptJson()
            ->withHeaders(['XSRF-TOKEN' => $session['token'], 'Cookie' => $session['cookie']])
            ->timeout(20)->post($path, $body === [] ? (object) [] : $body)->throw()->json();
    }

    private function session(): array
    {
        return Cache::remember('huawei:session', now()->addMinutes(25), function (): array {
            $response = Http::baseUrl(config('services.huawei.url'))->acceptJson()->timeout(20)->post('/thirdData/login', [
                'userName' => config('services.huawei.username'),
                'systemCode' => config('services.huawei.system_code'),
            ])->throw();
            $token = $response->header('XSRF-TOKEN');
            $cookies = collect($response->cookies()->toArray())->map(fn ($cookie) => $cookie['Name'].'='.$cookie['Value'])->implode('; ');

            if (! $token || ($response->json('success') !== true)) {
                throw new RuntimeException('FusionSolar authentication failed.');
            }

            return ['token' => $token, 'cookie' => $cookies];
        });
    }
}

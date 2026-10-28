<?php

namespace App\Jobs;

use App\Exceptions\AutomationBlocked;
use App\Models\Installation;
use App\Services\Automation\ControlledJob;
use App\Services\Automation\JobSnapshot;
use App\Services\Geocoding\NominatimGeocoder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class GeocodeInstallation implements ShouldQueue
{
    use Queueable;

    public ?int $queuedAtEpoch = null;

    public ?string $expectedSnapshot = null;

    public int $tries = 1;

    public int $timeout = 90;

    public function __construct(public int $installationId)
    {
        $this->queuedAtEpoch = now()->getTimestamp();
        $this->onQueue('ops-geocode');
        $subject = Installation::find($this->installationId);
        $this->expectedSnapshot = $subject ? JobSnapshot::geocode($subject) : null;
    }

    public function handle(NominatimGeocoder $geocoder): void
    {
        app(ControlledJob::class)->run('geocode', Installation::class, $this->installationId, $this->queuedAtEpoch, $this->expectedSnapshot,
            fn (Installation $installation) => JobSnapshot::geocode($installation),
            fn () => $this->geocode($geocoder));
    }

    private function geocode(NominatimGeocoder $geocoder): void
    {
        $installation = Installation::find($this->installationId);
        if (! $installation || ($installation->site_latitude !== null && $installation->site_longitude !== null)) {
            return;
        }

        $components = [
            'street' => $installation->site_address_line_1,
            'street_number' => $installation->site_street_number,
            'locality' => $installation->site_locality,
            'municipality' => $installation->site_city,
            'province' => $installation->site_province_state,
            'postal_code' => $installation->site_postal_code,
            'country' => $installation->site_country,
        ];
        $query = collect([
            trim($installation->site_address_line_1.' '.$installation->site_street_number),
            $installation->site_locality ?: $installation->site_city,
            $installation->site_province_state,
            $installation->site_postal_code,
            $installation->site_country,
        ])->filter()->implode(', ');
        $results = collect($geocoder->search($query, 'es', $components));
        $place = $results->firstWhere('house_number_match', true) ?: $results->first();

        if (! $place) {
            return;
        }

        DB::transaction(function () use ($place): void {
            $installation = Installation::whereKey($this->installationId)->lockForUpdate()->first();
            if (! $installation || JobSnapshot::geocode($installation) !== $this->expectedSnapshot) {
                throw new AutomationBlocked('Address or coordinates changed during geocoding.');
            }
            $installation->update([
                'site_latitude' => $place['latitude'],
                'site_longitude' => $place['longitude'],
                'site_geocoding_provider' => $place['provider'],
                'site_geocoding_place_id' => $place['place_id'],
                'site_geocoding_osm_type' => $place['osm_type'],
                'site_geocoding_osm_id' => $place['osm_id'],
                'site_geocoding_display_name' => $place['display_name'],
                'site_geocoding_data' => $place,
                'site_geocoded_at' => now(),
            ]);
        });
    }
}

<?php

namespace App\Jobs;

use App\Models\Installation;
use App\Services\Geocoding\NominatimGeocoder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GeocodeInstallation implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $installationId) {}

    public function handle(NominatimGeocoder $geocoder): void
    {
        $installation = Installation::find($this->installationId);
        if (! $installation || ($installation->site_latitude && $installation->site_longitude)) {
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
    }
}

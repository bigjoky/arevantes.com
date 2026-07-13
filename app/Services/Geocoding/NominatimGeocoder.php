<?php

namespace App\Services\Geocoding;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class NominatimGeocoder
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function search(string $query, string $language = 'es', array $components = []): array
    {
        $normalizedQuery = Str::of($query)->squish()->lower()->toString();
        $normalizedComponents = collect($components)
            ->map(fn ($value) => is_string($value) ? Str::of($value)->squish()->lower()->toString() : $value)
            ->filter()
            ->sortKeys()
            ->all();
        $cacheKey = 'geocoding:nominatim:v2:'.hash('sha256', json_encode([
            $normalizedQuery,
            $language,
            $normalizedComponents,
        ]));

        return Cache::remember($cacheKey, now()->addDays(30), function () use ($query, $language, $components): array {
            return Cache::lock('geocoding:nominatim:request', 5)->block(6, function () use ($query, $language, $components): array {
                $params = $this->structuredParams($components);
                $places = $params
                    ? $this->request($params, $language)
                    : $this->request(['q' => $query], $language);

                if ($places === [] && $params) {
                    $places = $this->request(['q' => $query], $language);
                }

                return collect($places)
                    ->map(fn (array $place) => $this->normalize($place, $components['street_number'] ?? null))
                    ->unique(fn (array $place) => implode('|', [
                        $place['display_name'],
                        round((float) $place['latitude'], 6),
                        round((float) $place['longitude'], 6),
                    ]))
                    ->values()
                    ->all();
            });
        });
    }

    /**
     * @param  array<string, mixed>  $components
     * @return array<string, string>
     */
    private function structuredParams(array $components): array
    {
        $street = trim(implode(' ', array_filter([
            $components['street_number'] ?? null,
            $components['street'] ?? null,
        ])));

        return array_filter([
            'street' => $street ?: null,
            'city' => $components['locality'] ?? $components['municipality'] ?? null,
            'county' => $components['municipality'] ?? null,
            'state' => $components['province'] ?? null,
            'postalcode' => $components['postal_code'] ?? null,
            'country' => $components['country'] ?? null,
        ]);
    }

    /**
     * @param  array<string, string>  $params
     * @return array<int, array<string, mixed>>
     */
    private function request(array $params, string $language): array
    {
        $lastRequestAt = (float) Cache::get('geocoding:nominatim:last_request_at', 0);
        $remainingDelay = 1 - (microtime(true) - $lastRequestAt);

        if ($remainingDelay > 0) {
            usleep((int) ceil($remainingDelay * 1_000_000));
        }

        $response = Http::baseUrl(rtrim(config('services.nominatim.url'), '/'))
            ->withHeaders([
                'Accept-Language' => $language,
                'User-Agent' => config('services.nominatim.user_agent'),
            ])
            ->acceptJson()
            ->timeout(10)
            ->retry(2, 250)
            ->get('/search', [
                ...$params,
                'format' => 'jsonv2',
                'addressdetails' => 1,
                'extratags' => 1,
                'namedetails' => 1,
                'countrycodes' => config('services.nominatim.country_codes'),
                'limit' => 5,
            ]);

        Cache::put('geocoding:nominatim:last_request_at', microtime(true), now()->addMinute());
        $response->throw();

        return $response->json();
    }

    /**
     * @param  array<string, mixed>  $place
     * @return array<string, mixed>
     */
    private function normalize(array $place, ?string $requestedHouseNumber = null): array
    {
        $address = $place['address'] ?? [];

        return [
            'provider' => 'nominatim',
            'place_id' => (string) ($place['place_id'] ?? ''),
            'osm_type' => $place['osm_type'] ?? null,
            'osm_id' => isset($place['osm_id']) ? (string) $place['osm_id'] : null,
            'latitude' => (string) $place['lat'],
            'longitude' => (string) $place['lon'],
            'display_name' => $place['display_name'] ?? null,
            'category' => $place['category'] ?? null,
            'type' => $place['type'] ?? null,
            'importance' => $place['importance'] ?? null,
            'bounding_box' => $place['boundingbox'] ?? null,
            'requested_house_number' => $requestedHouseNumber,
            'house_number_match' => $requestedHouseNumber === null
                || strcasecmp(trim((string) ($address['house_number'] ?? '')), trim($requestedHouseNumber)) === 0,
            'address' => [
                'road' => $address['road'] ?? $address['pedestrian'] ?? null,
                'house_number' => $address['house_number'] ?? null,
                'locality' => $address['village'] ?? $address['hamlet'] ?? $address['suburb'] ?? null,
                'municipality' => $address['city'] ?? $address['town'] ?? $address['municipality'] ?? null,
                'province' => $address['state'] ?? $address['province'] ?? null,
                'postal_code' => $address['postcode'] ?? null,
                'country' => $address['country'] ?? null,
                'country_code' => isset($address['country_code']) ? strtoupper($address['country_code']) : null,
            ],
        ];
    }
}

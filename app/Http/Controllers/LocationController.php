<?php

namespace App\Http\Controllers;

use App\Models\Country;
use App\Models\Locality;
use App\Models\Municipality;
use App\Models\PostalCode;
use App\Models\Province;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class LocationController extends Controller
{
    public function countries(): JsonResponse
    {
        return response()->json(Cache::remember('locations:countries:v1', now()->addDay(), fn () => Country::query()
            ->orderBy('name')
            ->get(['id', 'code', 'name'])
            ->toArray()));
    }

    public function provinces(Request $request): JsonResponse
    {
        $countryId = $request->integer('country_id');

        return response()->json(Cache::remember("locations:provinces:{$countryId}:v1", now()->addDay(), fn () => Province::query()
            ->when($countryId, fn ($query) => $query->where('country_id', $countryId))
            ->orderBy('name')
            ->get(['id', 'country_id', 'code', 'name'])
            ->toArray()));
    }

    public function municipalities(Request $request): JsonResponse
    {
        $provinceId = $request->integer('province_id');

        return response()->json(Cache::remember("locations:municipalities:{$provinceId}:v1", now()->addDay(), fn () => Municipality::query()
            ->when($provinceId, fn ($query) => $query->where('province_id', $provinceId))
            ->orderBy('name')
            ->get(['id', 'province_id', 'code', 'name'])
            ->toArray()));
    }

    public function localities(Request $request): JsonResponse
    {
        $municipalityId = $request->integer('municipality_id');

        return response()->json(Cache::remember("locations:localities:{$municipalityId}:v1", now()->addDay(), fn () => Locality::query()
            ->when($municipalityId, fn ($query) => $query->where('municipality_id', $municipalityId))
            ->orderBy('name')
            ->get(['id', 'municipality_id', 'name'])
            ->toArray()));
    }

    public function postalCode(string $code): JsonResponse
    {
        $postalCode = PostalCode::query()
            ->where('code', $code)
            ->with('localities.municipality.province.country')
            ->first();

        if (! $postalCode) {
            return response()->json(['localities' => []]);
        }

        return response()->json([
            'code' => $postalCode->code,
            'localities' => $postalCode->localities
                ->map(fn (Locality $locality) => [
                    'id' => $locality->id,
                    'name' => $locality->name,
                    'municipality' => [
                        'id' => $locality->municipality->id,
                        'name' => $locality->municipality->name,
                    ],
                    'province' => [
                        'id' => $locality->municipality->province->id,
                        'name' => $locality->municipality->province->name,
                    ],
                    'country' => [
                        'id' => $locality->municipality->province->country->id,
                        'code' => $locality->municipality->province->country->code,
                        'name' => $locality->municipality->province->country->name,
                    ],
                ])
                ->values(),
        ]);
    }
}

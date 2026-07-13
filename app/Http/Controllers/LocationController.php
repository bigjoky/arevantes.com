<?php

namespace App\Http\Controllers;

use App\Models\Country;
use App\Models\Locality;
use App\Models\Municipality;
use App\Models\PostalCode;
use App\Models\Province;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    public function countries(): JsonResponse
    {
        return response()->json(
            Country::query()->orderBy('name')->get(['id', 'code', 'name'])
        );
    }

    public function provinces(Request $request): JsonResponse
    {
        return response()->json(
            Province::query()
                ->when($request->integer('country_id'), fn ($query, $countryId) => $query->where('country_id', $countryId))
                ->orderBy('name')
                ->get(['id', 'country_id', 'code', 'name'])
        );
    }

    public function municipalities(Request $request): JsonResponse
    {
        return response()->json(
            Municipality::query()
                ->when($request->integer('province_id'), fn ($query, $provinceId) => $query->where('province_id', $provinceId))
                ->orderBy('name')
                ->get(['id', 'province_id', 'code', 'name'])
        );
    }

    public function localities(Request $request): JsonResponse
    {
        return response()->json(
            Locality::query()
                ->when($request->integer('municipality_id'), fn ($query, $municipalityId) => $query->where('municipality_id', $municipalityId))
                ->orderBy('name')
                ->get(['id', 'municipality_id', 'name'])
        );
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

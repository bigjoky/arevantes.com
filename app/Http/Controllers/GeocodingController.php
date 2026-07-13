<?php

namespace App\Http\Controllers;

use App\Services\Geocoding\NominatimGeocoder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class GeocodingController extends Controller
{
    public function search(Request $request, NominatimGeocoder $geocoder): JsonResponse
    {
        $validated = $request->validate([
            'query' => ['required', 'string', 'min:5', 'max:500'],
            'language' => ['nullable', 'string', 'in:es,en'],
            'street' => ['nullable', 'string', 'max:255'],
            'street_number' => ['nullable', 'string', 'max:30'],
            'locality' => ['nullable', 'string', 'max:255'],
            'municipality' => ['nullable', 'string', 'max:255'],
            'province' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'country' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            return response()->json([
                'results' => $geocoder->search(
                    $validated['query'],
                    $validated['language'] ?? 'es',
                    collect($validated)->only([
                        'street',
                        'street_number',
                        'locality',
                        'municipality',
                        'province',
                        'postal_code',
                        'country',
                    ])->all(),
                ),
                'attribution' => 'Data © OpenStreetMap contributors, ODbL 1.0',
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => __('The address could not be located right now.'),
            ], 502);
        }
    }
}

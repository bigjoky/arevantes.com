<?php

namespace App\Http\Controllers;

use App\Models\Installation;
use App\Models\RemoteStation;
use App\Services\Monitoring\RemoteStationCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

class RemoteStationController extends Controller
{
    public function refresh(Request $request, RemoteStationCatalog $catalog): JsonResponse
    {
        $data = $request->validate([
            'provider' => ['required', Rule::in(['huawei', 'deye'])],
            'installation_id' => ['nullable', 'integer', 'exists:installations,id'],
        ]);
        try {
            $catalog->refresh($data['provider']);
        } catch (RuntimeException $exception) {
            report($exception);

            return response()->json([
                'message' => $exception->getMessage(),
            ], str_contains($exception->getMessage(), 'error 407') ? 429 : 502);
        }
        $installation = isset($data['installation_id']) ? Installation::find($data['installation_id']) : null;
        $stations = RemoteStation::query()
            ->where('provider', $data['provider'])
            ->where(fn ($query) => $query
                ->whereNull('installation_id')
                ->when($installation, fn ($query) => $query->orWhere('installation_id', $installation->id)))
            ->orderBy('name')
            ->get()
            ->map(fn (RemoteStation $station) => [
                'id' => $station->id,
                'provider' => $station->provider,
                'remote_identifier' => $station->remote_identifier,
                'name' => $station->name,
                'address' => $station->address,
                'installation_id' => $station->installation_id,
            ]);

        return response()->json([
            'stations' => $stations,
            'refreshed_at' => now()->toIso8601String(),
        ]);
    }
}

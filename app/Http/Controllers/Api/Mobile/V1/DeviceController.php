<?php

namespace App\Http\Controllers\Api\Mobile\V1;

use App\Http\Controllers\Controller;
use App\Models\MobileDevice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DeviceController extends Controller
{
    public function update(Request $request, string $deviceId): JsonResponse
    {
        $data = $request->validate([
            'push_token' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/i'],
            'environment' => ['required', Rule::in(['development', 'production'])],
            'app_version' => ['nullable', 'string', 'max:40'],
        ]);
        MobileDevice::where('push_token', strtolower($data['push_token']))
            ->where(fn ($query) => $query->where('user_id', '!=', $request->user()->id)->orWhere('device_id', '!=', $deviceId))
            ->delete();
        $device = MobileDevice::updateOrCreate(
            ['user_id' => $request->user()->id, 'device_id' => $deviceId],
            [...$data, 'push_token' => strtolower($data['push_token']), 'platform' => 'ios', 'enabled' => true, 'last_seen_at' => now()],
        );

        return response()->json(['data' => ['device_id' => $device->device_id, 'enabled' => $device->enabled]]);
    }

    public function destroy(Request $request, string $deviceId): JsonResponse
    {
        MobileDevice::where('user_id', $request->user()->id)->where('device_id', $deviceId)->delete();

        return response()->json(status: 204);
    }
}

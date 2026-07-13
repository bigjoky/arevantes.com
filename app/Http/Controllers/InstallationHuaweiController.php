<?php

namespace App\Http\Controllers;

use App\Models\Installation;
use App\Services\Huawei\FusionSolarClient;
use Illuminate\Http\RedirectResponse;
use Throwable;

class InstallationHuaweiController extends Controller
{
    public function sync(Installation $installation, FusionSolarClient $client): RedirectResponse
    {
        abort_unless($installation->huawei_station_code, 422);

        try {
            $client->sync($installation);

            return back()->with('toast', ['type' => 'success', 'message' => __('FusionSolar status synchronized.')]);
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors(['huawei' => $exception->getMessage()]);
        }
    }
}

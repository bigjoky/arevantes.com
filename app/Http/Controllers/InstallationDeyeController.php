<?php

namespace App\Http\Controllers;

use App\Models\Installation;
use App\Services\Deye\DeyeCloudClient;
use Illuminate\Http\RedirectResponse;
use Throwable;

class InstallationDeyeController extends Controller
{
    public function sync(Installation $installation, DeyeCloudClient $deye): RedirectResponse
    {
        abort_unless($installation->deye_station_id, 422);

        try {
            $deye->sync($installation);

            return back()->with('toast', ['type' => 'success', 'message' => __('Deye status synchronized.')]);
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors(['deye' => $exception->getMessage()]);
        }
    }
}

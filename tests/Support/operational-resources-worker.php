<?php

use App\Http\Controllers\OperationalVehicleController;
use App\Http\Controllers\OperationsController;
use App\Models\OperationalVehicle;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\Crm\LeadResponseSla;
use App\Services\Crm\OpportunitySalesCycle;
use App\Services\Work\VehicleAvailability;
use App\Services\Work\WorkOrderRescheduler;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! app()->environment('testing') || DB::getDriverName() !== 'pgsql' || config('database.connections.pgsql.host') !== '127.0.0.1' || ! str_ends_with(DB::connection()->getDatabaseName(), '_ops01_test')) {
    exit(90);
}
if (DB::selectOne("SELECT shobj_description(oid,'pg_database') AS marker FROM pg_database WHERE datname=current_database()")->marker !== 'arevantes:ops01:synthetic-disposable:v1') {
    exit(91);
}
$args = json_decode($argv[1], true, flags: JSON_THROW_ON_ERROR);
if (! str_starts_with($args['directory'], sys_get_temp_dir().'/ops03-race-')) {
    exit(92);
}
$user = User::findOrFail($args['user_id']);
Auth::guard('web')->setUser($user);
file_put_contents($args['directory'].'/ready-'.getmypid(), 'ready');
$deadline = microtime(true) + 15;
while (! file_exists($args['directory'].'/go') && microtime(true) < $deadline) {
    usleep(10000);
}
try {
    $start = '2026-11-02T11:00:00+01:00';
    $end = '2026-11-02T12:00:00+01:00';
    if ($args['mode'] === 'order') {
        app(WorkOrderRescheduler::class)->reschedule(WorkOrder::findOrFail($args['order_id']), ['vehicle_id' => $args['vehicle_id'], 'scheduled_start' => $start, 'scheduled_end' => $end], 'Synthetic race', $user->id);
    } elseif ($args['mode'] === 'activity') {
        $request = Request::create('/operations/activities', 'POST', ['vehicle_id' => $args['vehicle_id'], 'type' => 'visit', 'title' => 'Synthetic race visit', 'priority' => 'normal', 'status' => 'pending', 'starts_at' => $start, 'due_at' => $end]);
        $request->setUserResolver(fn () => $user);
        app(OperationsController::class)->store($request, app(OpportunitySalesCycle::class), app(LeadResponseSla::class));
    } else {
        $request = Request::create('/operations/vehicles', 'POST', ['starts_at' => $start, 'ends_at' => $end, 'reason' => 'Synthetic maintenance']);
        $request->setUserResolver(fn () => $user);
        app(OperationalVehicleController::class)->block($request, OperationalVehicle::findOrFail($args['vehicle_id']), app(VehicleAvailability::class));
    }
    $result = 'assigned';
} catch (ValidationException) {
    $result = 'blocked';
}
file_put_contents($args['directory'].'/result-'.getmypid(), $result);

<?php

use App\Models\CrmActivity;
use App\Models\WorkOrder;
use App\Services\Work\TechnicianSchedule;
use App\Services\Work\WorkOrderRescheduler;
use Illuminate\Contracts\Console\Kernel;
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
if (! str_starts_with($args['directory'], sys_get_temp_dir().'/ops02-race-')) {
    exit(92);
}
file_put_contents($args['directory'].'/ready-'.getmypid(), 'ready');
$deadline = microtime(true) + 15;
while (! file_exists($args['directory'].'/go') && microtime(true) < $deadline) {
    usleep(10000);
}
try {
    if (($args['mode'] ?? 'order') === 'activity') {
        DB::transaction(function () use ($args) {
            $schedule = app(TechnicianSchedule::class);
            $schedule->lockTechnicians([$args['user_id']]);
            $schedule->assertAvailable($args['user_id'], '2026-11-02T11:00:00+01:00', '2026-11-02T12:00:00+01:00');
            CrmActivity::create(['title' => 'Synthetic concurrent visit', 'type' => 'visit', 'status' => 'pending', 'assigned_user_id' => $args['user_id'], 'starts_at' => '2026-11-02T11:00:00+01:00', 'due_at' => '2026-11-02T12:00:00+01:00']);
        });
    } else {
        app(WorkOrderRescheduler::class)->reschedule(WorkOrder::findOrFail($args['order_id']), ['assigned_user_id' => $args['user_id'], 'scheduled_start' => '2026-11-02T11:00:00+01:00', 'scheduled_end' => '2026-11-02T12:00:00+01:00'], 'Synthetic concurrent assignment', $args['user_id']);
    }
    $result = 'assigned';
} catch (ValidationException) {
    $result = 'blocked';
}
file_put_contents($args['directory'].'/result-'.getmypid(), $result);

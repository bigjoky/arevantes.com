<?php

use App\Models\WorkOrder;
use App\Services\Automation\ControlledJob;
use App\Services\Automation\JobSnapshot;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('testing') || config('database.connections.pgsql.host') !== '127.0.0.1' || ! str_ends_with(DB::connection()->getDatabaseName(), '_ops01_test')) {
    throw new RuntimeException('Disposable local OPS-01 test target required.');
}
$x = json_decode($argv[1], true, flags: JSON_THROW_ON_ERROR);
$directory = realpath($x['directory']);
if (! $directory || ! str_starts_with($directory, realpath(sys_get_temp_dir()).DIRECTORY_SEPARATOR)) {
    throw new RuntimeException('Temporary synthetic effect directory required.');
}
config(['automation.approved.push' => true]);
file_put_contents($directory.'/ready-'.getmypid(), 'ready');
$deadline = microtime(true) + 10;
while (! file_exists($directory.'/go')) {
    if (microtime(true) > $deadline) {
        throw new RuntimeException('Synthetic barrier timed out.');
    }
    usleep(10000);
}
app(ControlledJob::class)->run('push', WorkOrder::class, $x['order_id'], $x['queued_at'], $x['snapshot'],
    fn ($row) => JobSnapshot::push($row, $row->assigned_user_id, 'Synthetic'),
    function () use ($directory): void {
        file_put_contents($directory.'/effects', "synthetic attempt\n", FILE_APPEND | LOCK_EX);
        usleep(200000);
    });
echo json_encode(['pid' => getmypid(), 'completed' => true], JSON_THROW_ON_ERROR);

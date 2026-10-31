<?php

namespace Tests\Feature;

use App\Models\CrmActivity;
use App\Models\Installation;
use App\Models\OperationalVehicle;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class OperationalResourcesConcurrencyTest extends TestCase
{
    private bool $ownsDisposableDatabase = false;

    protected function tearDown(): void
    {
        try {
            if ($this->ownsDisposableDatabase) {
                $this->artisan('migrate:fresh', ['--force' => true])->assertSuccessful();
            }
        } finally {
            parent::tearDown();
        }
    }

    public function test_vehicle_lock_serializes_orders_visits_and_maintenance_blocks(): void
    {
        if (DB::getDriverName() !== 'pgsql' || config('database.connections.pgsql.host') !== '127.0.0.1' || ! str_ends_with(DB::connection()->getDatabaseName(), '_ops01_test')) {
            $this->markTestSkipped('Requires marked disposable local PostgreSQL.');
        }
        $this->assertSame('arevantes:ops01:synthetic-disposable:v1', DB::selectOne("SELECT shobj_description(oid,'pg_database') AS marker FROM pg_database WHERE datname=current_database()")->marker);
        $this->ownsDisposableDatabase = true;
        $this->artisan('migrate:fresh', ['--force' => true])->assertSuccessful();
        $user = User::factory()->create(['role' => 'admin']);
        $vehicle = OperationalVehicle::create(['registration' => 'SYN-OPS03', 'registration_key' => 'SYNOPS03', 'name' => 'Synthetic', 'status' => 'available', 'created_by' => $user->id]);
        foreach (['order', 'activity', 'block'] as $mode) {
            WorkOrder::query()->update(['status' => 'cancelled']);
            CrmActivity::query()->update(['status' => 'cancelled']);
            $vehicle->blocks()->delete();
            $orders = collect([0, 1])->map(function () {
                $installation = Installation::create(['service_type' => 'electrical', 'status' => 'approved', 'site_address_line_1' => 'Synthetic']);

                return WorkOrder::create(['installation_id' => $installation->id, 'number' => 'SYN-'.str()->uuid(), 'title' => 'Synthetic', 'kind' => 'maintenance', 'status' => 'planned']);
            });
            $directory = sys_get_temp_dir().'/ops03-race-'.str()->uuid();
            File::makeDirectory($directory);
            $workers = [];
            try {
                foreach ([0, 1] as $index) {
                    $args = ['directory' => $directory, 'mode' => $index ? $mode : 'order', 'order_id' => $orders[$index]->id, 'vehicle_id' => $vehicle->id, 'user_id' => $user->id];
                    $worker = new Process([PHP_BINARY, base_path('tests/Support/operational-resources-worker.php'), json_encode($args, JSON_THROW_ON_ERROR)], base_path(), timeout: 30);
                    $worker->start();
                    $workers[] = $worker;
                }
                $deadline = microtime(true) + 10;
                while (count(glob($directory.'/ready-*')) < 2 && microtime(true) < $deadline) {
                    usleep(10000);
                }
                $this->assertCount(2, glob($directory.'/ready-*'));
                file_put_contents($directory.'/go', 'go');
                foreach ($workers as $worker) {
                    $worker->wait();
                    $this->assertTrue($worker->isSuccessful(), $worker->getErrorOutput());
                }
                $results = array_map(fn ($path) => file_get_contents($path), glob($directory.'/result-*'));
                $this->assertSame(1, count(array_filter($results, fn ($result) => $result === 'assigned')));
                $this->assertSame(1, count(array_filter($results, fn ($result) => $result === 'blocked')));
                $active = WorkOrder::whereKey($orders->pluck('id'))->where('vehicle_id', $vehicle->id)->count()
                    + CrmActivity::where('vehicle_id', $vehicle->id)->where('status', 'pending')->count()
                    + $vehicle->blocks()->whereNull('cancelled_at_utc')->count();
                $this->assertSame(1, $active);
            } finally {
                foreach ($workers as $worker) {
                    if ($worker->isRunning()) {
                        $worker->stop();
                    }
                }
                File::deleteDirectory($directory);
            }
        }
    }
}

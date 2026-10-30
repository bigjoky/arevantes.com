<?php

namespace Tests\Feature;

use App\Models\CrmActivity;
use App\Models\FieldServiceProfile;
use App\Models\Installation;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class OperationalPlanningConcurrencyTest extends TestCase
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

    public function test_distinct_orders_cannot_book_the_same_technician_and_repeated_confirmation_has_one_history(): void
    {
        if (DB::getDriverName() !== 'pgsql' || config('database.connections.pgsql.host') !== '127.0.0.1' || ! str_ends_with(DB::connection()->getDatabaseName(), '_ops01_test')) {
            $this->markTestSkipped('Requires marked disposable local PostgreSQL.');
        }
        $this->assertSame('arevantes:ops01:synthetic-disposable:v1', DB::selectOne("SELECT shobj_description(oid,'pg_database') AS marker FROM pg_database WHERE datname=current_database()")->marker);
        $this->ownsDisposableDatabase = true;
        $this->artisan('migrate:fresh', ['--force' => true])->assertSuccessful();
        $user = User::factory()->create(['role' => 'technician']);
        FieldServiceProfile::create(['user_id' => $user->id, 'daily_capacity_minutes' => 480, 'is_available' => true, 'weekly_schedule' => ['1' => [['start' => '08:00', 'end' => '18:00']]]]);
        foreach (['different', 'same', 'activity'] as $scenario) {
            $sameOrder = $scenario === 'same';
            CrmActivity::query()->update(['status' => 'cancelled']);
            WorkOrder::query()->update(['status' => 'cancelled']);
            $orders = collect([0, 1])->map(function () {
                $installation = Installation::create(['service_type' => 'electrical', 'status' => 'survey_booked', 'site_address_line_1' => 'Synthetic']);

                return WorkOrder::create(['installation_id' => $installation->id, 'number' => 'SYN-'.str()->uuid(), 'title' => 'Synthetic', 'kind' => 'survey', 'status' => 'planned']);
            });
            $directory = sys_get_temp_dir().'/ops02-race-'.str()->uuid();
            File::makeDirectory($directory);
            $workers = [];
            try {
                foreach ([0, 1] as $index) {
                    $args = ['mode' => $scenario === 'activity' && $index === 1 ? 'activity' : 'order', 'directory' => $directory, 'order_id' => $orders[$sameOrder ? 0 : $index]->id, 'user_id' => $user->id];
                    $worker = new Process([PHP_BINARY, base_path('tests/Support/operational-planning-worker.php'), json_encode($args, JSON_THROW_ON_ERROR)], base_path(), timeout: 30);
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
                $this->assertSame($sameOrder ? 2 : 1, count(array_filter($results, fn ($result) => $result === 'assigned')));
                $bookedOrders = WorkOrder::whereKey($orders->pluck('id'))->where('assigned_user_id', $user->id)->count();
                $bookedActivities = CrmActivity::where('status', 'pending')->count();
                $this->assertSame(1, $bookedOrders + $bookedActivities);
                $this->assertSame($bookedOrders, DB::table('work_order_reschedules')->whereIn('work_order_id', $orders->pluck('id'))->count());
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

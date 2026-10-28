<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Installation;
use App\Models\MobileDevice;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\Automation\JobSnapshot;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ControlledJobConcurrencyTest extends TestCase
{
    public function test_two_postgres_workers_claim_only_one_external_attempt(): void
    {
        if (DB::getDriverName() !== 'pgsql' || config('database.connections.pgsql.host') !== '127.0.0.1' || ! str_ends_with(DB::connection()->getDatabaseName(), '_ops01_test')) {
            $this->markTestSkipped('Requires the marked disposable local OPS-01 PostgreSQL database.');
        }
        $this->assertSame('arevantes:ops01:synthetic-disposable:v1', DB::selectOne("SELECT shobj_description(oid,'pg_database') AS marker FROM pg_database WHERE datname=current_database()")->marker);
        $this->artisan('migrate:fresh', ['--force' => true])->assertSuccessful();
        Queue::fake();
        $user = User::factory()->create(['role' => 'admin']);
        $installation = Installation::create(['service_type' => 'solar', 'status' => 'scheduled', 'site_address_line_1' => 'Synthetic']);
        $order = WorkOrder::create(['installation_id' => $installation->id, 'assigned_user_id' => $user->id, 'number' => 'SYN-OPS', 'title' => 'Synthetic', 'status' => 'planned']);
        MobileDevice::create(['user_id' => $user->id, 'device_id' => (string) Str::uuid(), 'push_token' => str_repeat('a', 64), 'environment' => 'development', 'enabled' => true]);
        $directory = sys_get_temp_dir().'/arevantes-ops01-'.Str::uuid();
        File::makeDirectory($directory);
        $arguments = json_encode(['order_id' => $order->id, 'queued_at' => now()->getTimestamp(), 'snapshot' => JobSnapshot::push($order->fresh(), $user->id, 'Synthetic'), 'directory' => $directory], JSON_THROW_ON_ERROR);
        $workers = [];
        try {
            for ($i = 0; $i < 2; $i++) {
                $worker = new Process([PHP_BINARY, base_path('tests/Support/controlled-job-worker.php'), $arguments], base_path(), timeout: 20);
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
                $this->assertTrue($worker->isSuccessful(), $worker->getErrorOutput().$worker->getOutput());
            }
            $this->assertSame("synthetic attempt\n", file_get_contents($directory.'/effects'));
            $this->assertSame(1, AuditLog::where('event', 'automation_effect_claimed')->count());
            $this->assertSame(1, AuditLog::where('event', 'automation_effect_completed')->count());
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

<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WebsiteContactMail;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class WebsiteContactMailConcurrencyTest extends TestCase
{
    public function test_sender_waiting_on_a_cancelled_item_does_not_send(): void
    {
        if (DB::getDriverName() !== 'pgsql' || config('database.connections.pgsql.host') !== '127.0.0.1' || ! str_ends_with(DB::connection()->getDatabaseName(), '_contact10_test')) {
            $this->markTestSkipped('Requires an isolated contact10 PostgreSQL database.');
        }
        $admin = User::factory()->create(['role' => 'admin']);
        $this->post(route('website.contact'), ['name' => 'Prueba', 'email' => 'race@example.test', 'service' => 'solar', 'message' => 'Prueba aislada de concurrencia del formulario.', 'privacy' => '1']);
        $item = WebsiteContactMail::firstOrFail();
        DB::beginTransaction();
        try {
            WebsiteContactMail::whereKey($item->id)->lockForUpdate()->firstOrFail();
            $worker = new Process([PHP_BINARY, base_path('tests/Support/contact-mail-send-worker.php'), (string) $item->id], base_path(), [
                'APP_ENV' => 'testing', 'APP_KEY' => 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=',
                'DB_CONNECTION' => 'pgsql', 'DB_URL' => '', 'DB_HOST' => '127.0.0.1',
                'DB_PORT' => (string) config('database.connections.pgsql.port'), 'DB_DATABASE' => DB::connection()->getDatabaseName(),
                'DB_USERNAME' => config('database.connections.pgsql.username'), 'DB_PASSWORD' => config('database.connections.pgsql.password') ?? '',
            ]);
            $worker->start();
            $deadline = microtime(true) + 10;
            while (! str_contains($worker->getOutput(), 'ready') && $worker->isRunning() && microtime(true) < $deadline) {
                usleep(10000);
            }
            $this->assertStringContainsString('ready', $worker->getOutput());
            preg_match('/ready:(\d+)/', $worker->getOutput(), $matches);
            $this->assertNotEmpty($matches[1] ?? null);
            $blocked = false;
            while (microtime(true) < $deadline && $worker->isRunning()) {
                $blocked = DB::selectOne('select wait_event_type = ? as blocked from pg_stat_activity where pid = ?', ['Lock', (int) $matches[1]])->blocked ?? false;
                if ($blocked) {
                    break;
                }
                usleep(10000);
            }
            $this->assertTrue($blocked, 'Sender must be waiting for the same row lock.');
            $this->artisan('website-contact:mail-resolve', ['id' => $item->id, 'decision' => 'cancel', '--operator' => (string) $admin->id, '--reason' => 'Prueba concurrente'])->assertSuccessful();
            DB::commit();
            $worker->wait();
            $this->assertTrue($worker->isSuccessful(), $worker->getErrorOutput());
            $this->assertStringContainsString('not_pending', $worker->getOutput());
            $this->assertSame('cancelled', $item->fresh()->status);
            $this->assertSame(0, $item->fresh()->attempts);
        } finally {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            if (isset($worker) && $worker->isRunning()) {
                $worker->stop();
            }
        }
    }
}

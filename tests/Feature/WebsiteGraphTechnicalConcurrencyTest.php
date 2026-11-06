<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WebsiteGraphTestAuthorization;
use App\Services\Website\Graph\GraphTechnicalTest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class WebsiteGraphTechnicalConcurrencyTest extends TestCase
{
    public function test_two_executors_cannot_send_the_same_authorization(): void
    {
        if (DB::getDriverName() !== 'pgsql' || config('database.connections.pgsql.host') !== '127.0.0.1' || ! str_ends_with(DB::connection()->getDatabaseName(), '_contact10_test')) {
            $this->markTestSkipped('Requires isolated PostgreSQL.');
        }
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $csr = openssl_csr_new(['commonName' => 'Synthetic Test'], $key, ['digest_alg' => 'sha256']);
        $cert = openssl_csr_sign($csr, null, $key, 1, ['digest_alg' => 'sha256']);
        openssl_x509_export($cert, $certPem);
        openssl_pkey_export($key, $keyPem, 'synthetic-passphrase');
        $fingerprint = openssl_x509_fingerprint($cert, 'sha1');
        $admin = User::factory()->create(['role' => 'admin']);
        $id = (string) Str::uuid();
        $otherId = (string) Str::uuid();
        $otherAdmin = User::factory()->create(['role' => 'admin']);
        config(['contact-mail.graph_test_enabled' => true, 'contact-mail.enabled' => false, 'contact-mail.transport' => 'graph', 'contact-mail.from' => 'info@arevantes.com', 'contact-mail.reply_to' => 'info@arevantes.com',
            'contact-mail.graph.tenant_id' => '7c831860-91be-46c8-9e16-afea4d7b12fa', 'contact-mail.graph.client_id' => '7dbadeb2-e9ec-49dc-ae83-68a9563c236d', 'contact-mail.graph.sha1_thumbprint' => $fingerprint, 'cache.default' => 'array']);
        putenv('MS_GRAPH_CERTIFICATE_PEM_B64='.base64_encode($certPem));
        putenv('MS_GRAPH_PRIVATE_KEY_PEM_B64='.base64_encode($keyPem));
        putenv('MS_GRAPH_PRIVATE_KEY_PASSPHRASE=synthetic-passphrase');
        Http::fake(['login.microsoftonline.com/*' => Http::response(['access_token' => 'synthetic', 'expires_in' => 3600]), 'graph.microsoft.com/*' => Http::response('', 202)]);
        app(GraphTechnicalTest::class)->authorize($admin, $id);
        app(GraphTechnicalTest::class)->authorize($otherAdmin, $otherId);
        DB::beginTransaction();
        try {
            DB::select("SELECT pg_advisory_xact_lock(hashtextextended('graph-technical-test:initial-campaign', 0))");
            $worker = new Process([PHP_BINARY, base_path('tests/Support/graph-technical-test-worker.php'), $otherId, (string) $otherAdmin->id], base_path(), [
                'APP_ENV' => 'testing', 'APP_KEY' => 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=',
                'DB_CONNECTION' => 'pgsql', 'DB_URL' => '', 'DB_HOST' => '127.0.0.1', 'DB_PORT' => (string) config('database.connections.pgsql.port'),
                'DB_DATABASE' => DB::connection()->getDatabaseName(), 'DB_USERNAME' => config('database.connections.pgsql.username'), 'DB_PASSWORD' => config('database.connections.pgsql.password') ?? '',
                'MS_GRAPH_CERTIFICATE_PEM_B64' => base64_encode($certPem), 'MS_GRAPH_PRIVATE_KEY_PEM_B64' => base64_encode($keyPem),
                'MS_GRAPH_PRIVATE_KEY_PASSPHRASE' => 'synthetic-passphrase', 'TEST_GRAPH_FINGERPRINT' => $fingerprint,
            ]);
            $worker->start();
            $deadline = microtime(true) + 10;
            while (! preg_match('/ready:(\d+)/', $worker->getOutput(), $matches) && $worker->isRunning() && microtime(true) < $deadline) { usleep(10000); }
            $this->assertNotEmpty($matches[1] ?? null);
            $blocked = false;
            while (microtime(true) < $deadline && $worker->isRunning()) {
                $blocked = DB::selectOne('select wait_event_type = ? as blocked from pg_stat_activity where pid = ?', ['Lock', (int) $matches[1]])->blocked ?? false;
                if ($blocked) { break; }
                usleep(10000);
            }
            $this->assertTrue($blocked);
            $this->assertSame('accepted', app(GraphTechnicalTest::class)->execute($admin, $id));
            DB::commit();
            $worker->wait();
            $this->assertTrue($worker->isSuccessful(), $worker->getErrorOutput());
            $this->assertStringContainsString('blocked', $worker->getOutput());
            $this->assertStringNotContainsString('unexpected-send', $worker->getOutput());
            Http::assertSentCount(2);
        } finally {
            if (DB::transactionLevel() > 0) { DB::rollBack(); }
            if (isset($worker) && $worker->isRunning()) { $worker->stop(); }
            putenv('MS_GRAPH_CERTIFICATE_PEM_B64');
            putenv('MS_GRAPH_PRIVATE_KEY_PEM_B64');
            putenv('MS_GRAPH_PRIVATE_KEY_PASSPHRASE');
        }
    }
}

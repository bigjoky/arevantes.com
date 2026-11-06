<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WebsiteContactMail;
use App\Services\Website\Graph\GraphAuthentication;
use App\Services\Website\Graph\GraphException;
use App\Services\Website\WebsiteContactOutbox;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Crypt\RSA;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class WebsiteGraphMailTest extends TestCase
{
    use RefreshDatabase;

    private string $certPath;

    private string $keyPath;

    private string $publicKey;

    public function test_technical_test_requires_authorized_session_and_exact_recipient(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $other = User::factory()->create(['role' => 'commercial']);
        $id = (string) \Illuminate\Support\Str::uuid();
        $url = route('contact-incidents.graph-test.authorize');
        $this->post($url, ['id' => $id, 'recipient' => 'info@arevantes.com'])->assertRedirect(route('login'));
        $this->post(route('contact-incidents.graph-test.send', $id), ['recipient' => 'info@arevantes.com', 'confirmation' => 'ENVIAR UNA VEZ'])->assertRedirect(route('login'));
        $this->actingAs($other)->post($url, ['id' => $id, 'recipient' => 'info@arevantes.com'])->assertForbidden();
        $this->post(route('contact-incidents.graph-test.send', $id), ['recipient' => 'info@arevantes.com', 'confirmation' => 'ENVIAR UNA VEZ'])->assertForbidden();
        $this->actingAs($admin)->post($url, ['id' => $id, 'recipient' => 'other@example.test'])->assertSessionHasErrors('recipient');
        $this->assertDatabaseCount('website_graph_test_authorizations', 0);
        $this->actingAs($admin)->post($url, ['id' => $id, 'recipient' => 'info@arevantes.com'])->assertSessionHasNoErrors();
        $this->post($url, ['id' => $id, 'recipient' => 'info@arevantes.com'])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('website_graph_test_authorizations', 1);
        $this->assertDatabaseCount('audit_logs', 1);
        $this->assertDatabaseHas('audit_logs', ['event' => 'graph_test_authorized', 'user_id' => $admin->id]);
    }

    public function test_technical_test_routes_use_csrf_middleware(): void
    {
        foreach (['contact-incidents.graph-test.authorize', 'contact-incidents.graph-test.send', 'contact-incidents.graph-test.receipt'] as $name) {
            $this->assertContains('web', app('router')->getRoutes()->getByName($name)->gatherMiddleware());
        }
        $this->assertContains(\Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class, app('router')->getMiddlewareGroups()['web']);
        $request = \Illuminate\Http\Request::create('/contact-incidents/graph-test', 'POST');
        $request->setLaravelSession(app('session')->driver());
        $middleware = new class(app(), app('encrypter')) extends \Illuminate\Foundation\Http\Middleware\PreventRequestForgery {
            protected function runningUnitTests() { return false; }
        };
        $this->expectException(\Illuminate\Session\TokenMismatchException::class);
        $middleware->handle($request, fn () => response('unexpected'));
    }

    public function test_technical_test_acceptance_is_single_and_does_not_enable_general_mail(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $id = (string) \Illuminate\Support\Str::uuid();
        config(['contact-mail.enabled' => false]);
        Http::fake(['login.microsoftonline.com/*' => Http::response(['access_token' => 'test-token', 'expires_in' => 3600]), 'graph.microsoft.com/*' => Http::response('', 202)]);
        $this->actingAs($admin)->post(route('contact-incidents.graph-test.authorize'), ['id' => $id, 'recipient' => 'info@arevantes.com'])->assertSessionHasNoErrors();
        $url = route('contact-incidents.graph-test.send', $id);
        $this->post($url, ['recipient' => 'other@example.test', 'confirmation' => 'ENVIAR UNA VEZ'])->assertSessionHasErrors('recipient');
        $this->post($url, ['recipient' => 'info@arevantes.com', 'confirmation' => 'ENVIAR UNA VEZ'])->assertSessionHasNoErrors();
        $this->post($url, ['recipient' => 'info@arevantes.com', 'confirmation' => 'ENVIAR UNA VEZ'])->assertSessionHasErrors('test');
        $this->assertDatabaseHas('website_graph_test_authorizations', ['id' => $id, 'status' => 'accepted', 'authorized_by' => $admin->id, 'executed_by' => $admin->id]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'graph_test_started', 'user_id' => $admin->id]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'graph_test_accepted', 'user_id' => $admin->id]);
        $this->assertDatabaseHas('website_graph_test_authorizations', ['id' => $id, 'receipt_confirmed_at' => null]);
        $this->post(route('contact-incidents.graph-test.receipt', $id), ['confirmation' => 'RECIBIDO'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('website_graph_test_authorizations', ['id' => $id, 'receipt_confirmed_by' => $admin->id]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'graph_test_receipt_confirmed', 'user_id' => $admin->id]);
        $this->post(route('contact-incidents.graph-test.receipt', $id), ['confirmation' => 'RECIBIDO'])->assertSessionHasErrors('test');
        $this->assertFalse(config('contact-mail.enabled'));
        Http::assertSentCount(2);
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'graph.microsoft.com') && $r['message']['toRecipients'][0]['emailAddress']['address'] === 'info@arevantes.com' && str_contains($r['message']['subject'], 'Prueba técnica'));
    }

    public function test_technical_test_failure_is_uncertain_and_cannot_be_replayed(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        foreach (['auth', 'forbidden', 'timeout'] as $failure) {
            Cache::flush();
            $id = (string) \Illuminate\Support\Str::uuid();
            Http::fake(function (Request $r) use ($failure) {
                if (str_contains($r->url(), 'login.microsoftonline.com')) {
                    return $failure === 'auth' ? Http::response([], 401) : Http::response(['access_token' => 'test-token', 'expires_in' => 3600]);
                }
                if ($failure === 'timeout') { throw new ConnectionException('timeout'); }
                return Http::response('', 403);
            });
            $this->actingAs($admin)->post(route('contact-incidents.graph-test.authorize'), ['id' => $id, 'recipient' => 'info@arevantes.com'])->assertSessionHasNoErrors();
            $args = ['recipient' => 'info@arevantes.com', 'confirmation' => 'ENVIAR UNA VEZ'];
            $this->post(route('contact-incidents.graph-test.send', $id), $args)->assertSessionHasNoErrors();
            $this->assertDatabaseHas('website_graph_test_authorizations', ['id' => $id, 'status' => 'uncertain']);
            $this->post(route('contact-incidents.graph-test.send', $id), $args)->assertSessionHasErrors('test');
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $csr = openssl_csr_new(['commonName' => 'Local Test'], $key, ['digest_alg' => 'sha256']);
        $cert = openssl_csr_sign($csr, null, $key, 1, ['digest_alg' => 'sha256']);
        openssl_x509_export($cert, $certPem);
        openssl_pkey_export($key, $keyPem, 'test-passphrase');
        $this->publicKey = openssl_pkey_get_details($key)['key'];
        $this->certPath = tempnam(sys_get_temp_dir(), 'contact-cert-');
        $this->keyPath = tempnam(sys_get_temp_dir(), 'contact-key-');
        file_put_contents($this->certPath, $certPem);
        file_put_contents($this->keyPath, $keyPem);
        config(['contact-mail.enabled' => true, 'contact-mail.transport' => 'graph', 'contact-mail.from' => 'info@arevantes.com', 'contact-mail.reply_to' => 'info@arevantes.com',
            'contact-mail.graph.tenant_id' => '7c831860-91be-46c8-9e16-afea4d7b12fa', 'contact-mail.graph.client_id' => '7dbadeb2-e9ec-49dc-ae83-68a9563c236d',
            'contact-mail.graph.certificate_path' => $this->certPath, 'contact-mail.graph.private_key_path' => $this->keyPath,
            'contact-mail.graph.private_key_passphrase' => 'test-passphrase', 'contact-mail.graph.sha1_thumbprint' => openssl_x509_fingerprint($cert, 'sha1')]);
        Cache::flush();
    }

    protected function tearDown(): void
    {
        @unlink($this->certPath);
        @unlink($this->keyPath);
        parent::tearDown();
    }

    private function item(): WebsiteContactMail
    {
        User::factory()->create(['role' => 'admin']);
        $this->post(route('website.contact'), ['name' => 'Prueba', 'email' => 'visitor@example.test', 'service' => 'solar', 'message' => 'Necesito información sobre una instalación solar de prueba.', 'privacy' => '1'])->assertSessionHas('contact_submitted', true);

        return WebsiteContactMail::where('kind', 'visitor')->firstOrFail();
    }

    public function test_assertion_uses_ps256_and_token_is_cached_then_renewed(): void
    {
        Http::fake(['login.microsoftonline.com/*' => Http::response(['access_token' => 'test-token', 'expires_in' => 3600])]);
        $auth = app(GraphAuthentication::class);
        $this->assertSame('test-token', $auth->token());
        $this->assertSame('test-token', $auth->token());
        Http::assertSentCount(1);
        Http::assertSent(function (Request $request): bool {
            $parts = explode('.', $request['client_assertion']);
            $decode = fn ($value) => base64_decode(strtr($value, '-_', '+/'));
            $header = json_decode($decode($parts[0]), true);
            $claims = json_decode($decode($parts[1]), true);
            $expected = rtrim(strtr(base64_encode(hash('sha256', base64_decode(preg_replace('/-----BEGIN CERTIFICATE-----|-----END CERTIFICATE-----|\s+/', '', file_get_contents($this->certPath))), true)), '+/', '-_'), '=');
            $signature = $decode($parts[2]);
            $valid = PublicKeyLoader::loadPublicKey($this->publicKey)->withPadding(RSA::SIGNATURE_PSS)->withHash('sha256')->withMGFHash('sha256')->withSaltLength(32)->verify($parts[0].'.'.$parts[1], $signature);

            return $valid && $header['alg'] === 'PS256' && $header['x5t#S256'] === $expected && $claims['sub'] === config('contact-mail.graph.client_id') && $claims['aud'] === $request->url() && $request['scope'] === 'https://graph.microsoft.com/.default';
        });
        $this->travel(3500)->seconds();
        $this->assertSame('test-token', $auth->token());
        Http::assertSentCount(2);
    }

    public function test_graph_202_is_recorded_as_accepted_once(): void
    {
        $item = $this->item();
        Http::fake(['login.microsoftonline.com/*' => Http::response(['access_token' => 'test-token', 'expires_in' => 3600]), 'graph.microsoft.com/*' => Http::response('', 202)]);
        $this->assertSame('sent', app(WebsiteContactOutbox::class)->send($item->id));
        $this->assertSame('not_pending', app(WebsiteContactOutbox::class)->send($item->id));
        Http::assertSent(fn (Request $request) => str_contains($request->url(), '/users/info%40arevantes.com/sendMail') && $request['message']['toRecipients'][0]['emailAddress']['address'] === 'visitor@example.test');
        $this->assertDatabaseCount('opportunities', 1);
    }

    public function test_injected_secrets_are_used_without_writing_credential_files_or_config(): void
    {
        $certificate = file_get_contents($this->certPath);
        $privateKey = file_get_contents($this->keyPath);
        putenv('MS_GRAPH_CERTIFICATE_PEM_B64='.base64_encode($certificate));
        putenv('MS_GRAPH_PRIVATE_KEY_PEM_B64='.base64_encode($privateKey));
        putenv('MS_GRAPH_PRIVATE_KEY_PASSPHRASE=test-passphrase');
        try {
            config(['contact-mail.graph.certificate_path' => null, 'contact-mail.graph.private_key_path' => null, 'contact-mail.graph.private_key_passphrase' => null]);
            Http::fake(['login.microsoftonline.com/*' => Http::response(['access_token' => 'test-token', 'expires_in' => 3600])]);
            $this->assertSame('test-token', app(GraphAuthentication::class)->token());
            $this->assertStringNotContainsString($privateKey, serialize(config('contact-mail')));
            Http::assertSentCount(1);
        } finally {
            putenv('MS_GRAPH_CERTIFICATE_PEM_B64');
            putenv('MS_GRAPH_PRIVATE_KEY_PEM_B64');
            putenv('MS_GRAPH_PRIVATE_KEY_PASSPHRASE');
        }
    }

    public function test_missing_or_malformed_fingerprint_rejects_authentication(): void
    {
        $item = $this->item();
        Http::fake();
        foreach ([null, '', '1234'] as $fingerprint) {
            $item->refresh()->update(['status' => 'pending']);
            config(['contact-mail.graph.sha1_thumbprint' => $fingerprint]);
            $this->assertSame('uncertain', app(WebsiteContactOutbox::class)->send($item->id));
            $this->assertSame('certificate_fingerprint_required', $item->fresh()->error_class);
        }
        Http::assertNothingSent();
    }

    public function test_cloud_style_secrets_work_with_cached_configuration(): void
    {
        $cacheFile = tempnam(sys_get_temp_dir(), 'contact-config-');
        unlink($cacheFile);
        $scriptFile = tempnam(sys_get_temp_dir(), 'contact-script-');
        $environment = [
            'APP_ENV' => 'production', 'APP_KEY' => 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=',
            'APP_CONFIG_CACHE' => $cacheFile, 'CACHE_STORE' => 'array',
            'MS_GRAPH_TENANT_ID' => '7c831860-91be-46c8-9e16-afea4d7b12fa',
            'MS_GRAPH_CLIENT_ID' => '7dbadeb2-e9ec-49dc-ae83-68a9563c236d',
            'MS_GRAPH_CERTIFICATE_SHA1' => openssl_x509_fingerprint(file_get_contents($this->certPath), 'sha1'),
            'MS_GRAPH_CERTIFICATE_PEM_B64' => base64_encode(file_get_contents($this->certPath)),
            'MS_GRAPH_PRIVATE_KEY_PEM_B64' => base64_encode(file_get_contents($this->keyPath)),
            'MS_GRAPH_PRIVATE_KEY_PASSPHRASE' => 'test-passphrase',
        ];
        file_put_contents($scriptFile, <<<'PHP'
<?php
require getcwd().'/vendor/autoload.php';
$app = require getcwd().'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (! $app->configurationIsCached()) { exit(2); }
Illuminate\Support\Facades\Http::fake(['login.microsoftonline.com/*' => Illuminate\Support\Facades\Http::response(['access_token' => 'simulated', 'expires_in' => 3600])]);
if (app(App\Services\Website\Graph\GraphAuthentication::class)->token() !== 'simulated') { exit(3); }
echo 'ok';
PHP);
        try {
            $cache = new Process([PHP_BINARY, 'artisan', 'config:cache'], base_path(), $environment);
            $cache->run();
            $this->assertTrue($cache->isSuccessful(), 'No se pudo generar la caché de configuración aislada.');
            $cached = file_get_contents($cacheFile);
            $this->assertStringNotContainsString($environment['MS_GRAPH_PRIVATE_KEY_PEM_B64'], $cached);
            $this->assertStringNotContainsString($environment['MS_GRAPH_PRIVATE_KEY_PASSPHRASE'], $cached);
            $process = new Process([PHP_BINARY, $scriptFile], base_path(), $environment);
            $process->run();
            $this->assertTrue($process->isSuccessful(), 'No se pudieron leer los secretos simulados con configuración cacheada.');
            $this->assertSame('ok', $process->getOutput());
        } finally {
            @unlink($cacheFile);
            @unlink($scriptFile);
        }
    }

    public function test_cloud_dotenv_secrets_are_read_with_cached_configuration(): void
    {
        $directory = sys_get_temp_dir().'/contact-cloud-'.bin2hex(random_bytes(8));
        mkdir($directory, 0700);
        $cacheFile = $directory.'/config.php';
        $scriptFile = $directory.'/check.php';
        $certificate = base64_encode(file_get_contents($this->certPath));
        $privateKey = base64_encode(file_get_contents($this->keyPath));
        file_put_contents($directory.'/.env', "MS_GRAPH_CERTIFICATE_PEM_B64={$certificate}\nMS_GRAPH_PRIVATE_KEY_PEM_B64={$privateKey}\nMS_GRAPH_PRIVATE_KEY_PASSPHRASE=\"test-passphrase\"\n");
        chmod($directory.'/.env', 0600);
        $environment = [
            'APP_ENV' => 'production', 'APP_KEY' => 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=',
            'APP_CONFIG_CACHE' => $cacheFile, 'CACHE_STORE' => 'array',
            'MS_GRAPH_TENANT_ID' => '7c831860-91be-46c8-9e16-afea4d7b12fa',
            'MS_GRAPH_CLIENT_ID' => '7dbadeb2-e9ec-49dc-ae83-68a9563c236d',
            'MS_GRAPH_CERTIFICATE_SHA1' => openssl_x509_fingerprint(file_get_contents($this->certPath), 'sha1'),
            'MS_GRAPH_CERTIFICATE_PEM_B64' => false, 'MS_GRAPH_PRIVATE_KEY_PEM_B64' => false,
            'MS_GRAPH_PRIVATE_KEY_PASSPHRASE' => false,
        ];
        file_put_contents($scriptFile, <<<'PHP'
<?php
require getcwd().'/vendor/autoload.php';
$app = require getcwd().'/bootstrap/app.php';
$app->useEnvironmentPath($argv[1]);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (! $app->configurationIsCached() || getenv('MS_GRAPH_PRIVATE_KEY_PEM_B64') !== false) { exit(2); }
Illuminate\Support\Facades\Http::fake(['login.microsoftonline.com/*' => Illuminate\Support\Facades\Http::response(['access_token' => 'simulated', 'expires_in' => 3600])]);
if (app(App\Services\Website\Graph\GraphAuthentication::class)->token() !== 'simulated') { exit(3); }
putenv('MS_GRAPH_CERTIFICATE_PEM_B64=invalid-process-value');
try {
    (new App\Services\Website\Graph\GraphAuthentication)->token();
    exit(4);
} catch (App\Services\Website\Graph\GraphException $e) {
    if ($e->category !== 'credentials_unavailable') { exit(5); }
}
putenv('MS_GRAPH_CERTIFICATE_PEM_B64');
unlink($app->environmentFilePath());
try {
    (new App\Services\Website\Graph\GraphAuthentication)->token();
    exit(6);
} catch (App\Services\Website\Graph\GraphException $e) {
    if ($e->category !== 'credentials_path_disabled') { exit(7); }
}
echo 'ok';
PHP);
        try {
            $cache = new Process([PHP_BINARY, 'artisan', 'config:cache'], base_path(), $environment);
            $cache->run();
            $this->assertTrue($cache->isSuccessful(), 'No se pudo crear la configuración cacheada aislada.');
            $this->assertStringNotContainsString($privateKey, file_get_contents($cacheFile));
            $process = new Process([PHP_BINARY, $scriptFile, $directory], base_path(), $environment);
            $process->run();
            $this->assertTrue($process->isSuccessful(), 'No se pudieron leer los secretos simulados desde el entorno protegido.');
            $this->assertSame('ok', $process->getOutput());
        } finally {
            @unlink($directory.'/.env');
            @unlink($scriptFile);
            @unlink($cacheFile);
            @rmdir($directory);
        }
    }

    public function test_production_rejects_private_key_paths_without_injected_secrets(): void
    {
        $previous = $this->app['env'];
        $this->app['env'] = 'production';
        try {
            Http::fake();
            $this->expectException(GraphException::class);
            app(GraphAuthentication::class)->token();
        } finally {
            $this->app['env'] = $previous;
        }
    }

    public function test_wrong_certificate_or_passphrase_is_rejected_before_http(): void
    {
        $item = $this->item();
        config(['contact-mail.graph.private_key_passphrase' => 'incorrect']);
        Http::fake();
        $this->assertSame('uncertain', app(WebsiteContactOutbox::class)->send($item->id));
        $this->assertSame('credentials_invalid', $item->fresh()->error_class);
        Http::assertNothingSent();
        $item->refresh()->update(['status' => 'pending']);
        config(['contact-mail.graph.private_key_passphrase' => 'test-passphrase', 'contact-mail.graph.sha1_thumbprint' => str_repeat('a', 40)]);
        $this->assertSame('uncertain', app(WebsiteContactOutbox::class)->send($item->id));
        $this->assertSame('certificate_mismatch', $item->fresh()->error_class);
        Http::assertNothingSent();
    }

    public function test_certificate_and_unrelated_private_key_are_rejected(): void
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $pem, 'test-passphrase');
        file_put_contents($this->keyPath, $pem);
        Http::fake();
        try {
            app(GraphAuthentication::class)->token();
            $this->fail('La clave privada no corresponde al certificado.');
        } catch (GraphException $e) {
            $this->assertSame('credentials_invalid', $e->category);
        }
        Http::assertNothingSent();
    }

    public function test_authentication_failure_does_not_send_or_log_credentials(): void
    {
        $item = $this->item();
        Http::fake(['login.microsoftonline.com/*' => Http::response(['error_description' => 'secret-token-material'], 401)]);
        $this->assertSame('uncertain', app(WebsiteContactOutbox::class)->send($item->id));
        $this->assertSame('authentication_http_401', $item->fresh()->error_class);
        Http::assertSentCount(1);
        $this->assertDatabaseCount('opportunities', 1);
    }

    public function test_graph_timeout_is_uncertain_without_resending(): void
    {
        $item = $this->item();
        Http::fake(function (Request $request) {
            if (str_contains($request->url(), 'login.microsoftonline.com')) {
                return Http::response(['access_token' => 'test-token', 'expires_in' => 3600]);
            }
            throw new ConnectionException('timeout');
        });
        $this->assertSame('uncertain', app(WebsiteContactOutbox::class)->send($item->id));
        $this->assertSame('send_uncertain', $item->fresh()->error_class);
        $this->assertSame('not_pending', app(WebsiteContactOutbox::class)->send($item->id));
        $this->assertDatabaseCount('opportunities', 1);
    }

    public function test_graph_errors_remain_uncertain_without_automatic_retry(): void
    {
        $item = $this->item();
        $status = 401;
        Http::fake(function (Request $request) use (&$status) {
            return str_contains($request->url(), 'login.microsoftonline.com')
                ? Http::response(['access_token' => 'test-token', 'expires_in' => 3600]) : Http::response('', $status);
        });
        foreach ([401, 403, 429, 500] as $status) {
            $item->refresh()->update(['status' => 'pending']);
            $this->assertSame('uncertain', app(WebsiteContactOutbox::class)->send($item->id));
            $this->assertSame('send_http_'.$status, $item->fresh()->error_class);
            $this->assertSame('not_pending', app(WebsiteContactOutbox::class)->send($item->id));
        }
    }
}

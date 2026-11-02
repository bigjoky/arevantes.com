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
use Tests\TestCase;

class WebsiteGraphMailTest extends TestCase
{
    use RefreshDatabase;

    private string $certPath;

    private string $keyPath;

    private string $publicKey;

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
            'contact-mail.graph.private_key_passphrase' => 'test-passphrase', 'contact-mail.graph.sha1_thumbprint' => '']);
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

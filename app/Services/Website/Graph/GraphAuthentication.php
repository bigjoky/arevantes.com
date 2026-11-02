<?php

namespace App\Services\Website\Graph;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Crypt\RSA;
use Throwable;

final class GraphAuthentication
{
    public function token(): string
    {
        $tenant = (string) config('contact-mail.graph.tenant_id');
        $client = (string) config('contact-mail.graph.client_id');
        if (! preg_match('/^[a-f0-9-]{36}$/i', $tenant) || ! preg_match('/^[a-f0-9-]{36}$/i', $client)) {
            throw new GraphException('configuration');
        }
        [$certificate, $key, $fingerprint] = $this->credentials();
        $cacheKey = $this->cacheKey($tenant, $client, $fingerprint);
        $cached = Cache::get($cacheKey);
        if (is_string($cached)) {
            try {
                return Crypt::decryptString($cached);
            } catch (Throwable) {
                Cache::forget($cacheKey);
            }
        }
        $audience = "https://login.microsoftonline.com/{$tenant}/oauth2/v2.0/token";
        $now = time();
        $header = ['alg' => 'PS256', 'typ' => 'JWT', 'x5t#S256' => $this->base64url(hash('sha256', $this->der($certificate), true))];
        $claims = ['aud' => $audience, 'iss' => $client, 'sub' => $client, 'jti' => (string) Str::uuid(), 'nbf' => $now - 30, 'exp' => $now + 300];
        $input = $this->base64url(json_encode($header, JSON_THROW_ON_ERROR)).'.'.$this->base64url(json_encode($claims, JSON_THROW_ON_ERROR));
        try {
            $signer = PublicKeyLoader::loadPrivateKey($key, $this->passphrase())
                ->withPadding(RSA::SIGNATURE_PSS)->withHash('sha256')->withMGFHash('sha256')->withSaltLength(32);
            $assertion = $input.'.'.$this->base64url($signer->sign($input));
            $response = Http::asForm()->timeout(10)->connectTimeout(5)->post($audience, [
                'client_id' => $client, 'scope' => 'https://graph.microsoft.com/.default', 'grant_type' => 'client_credentials',
                'client_assertion_type' => 'urn:ietf:params:oauth:client-assertion-type:jwt-bearer', 'client_assertion' => $assertion,
            ]);
        } catch (Throwable) {
            throw new GraphException('authentication_connection');
        }
        if (! $response->successful()) {
            throw new GraphException('authentication_http_'.$response->status());
        }
        $token = $response->json('access_token');
        $ttl = (int) $response->json('expires_in') - 120;
        if (! is_string($token) || $token === '') {
            throw new GraphException('authentication_response');
        }
        if ($ttl > 0) {
            Cache::put($cacheKey, Crypt::encryptString($token), $ttl);
        }

        return $token;
    }

    public function forgetToken(): void
    {
        try {
            [, , $fingerprint] = $this->credentials();
            Cache::forget($this->cacheKey((string) config('contact-mail.graph.tenant_id'), (string) config('contact-mail.graph.client_id'), $fingerprint));
        } catch (Throwable) {
        }
    }

    private function credentials(): array
    {
        $certificateSecret = getenv('MS_GRAPH_CERTIFICATE_PEM_B64');
        $keySecret = getenv('MS_GRAPH_PRIVATE_KEY_PEM_B64');
        if ($certificateSecret !== false || $keySecret !== false) {
            if (! is_string($certificateSecret) || ! is_string($keySecret) || strlen($certificateSecret) > 32768 || strlen($keySecret) > 32768) {
                throw new GraphException('credentials_invalid');
            }
            $certificate = base64_decode($certificateSecret, true);
            $key = base64_decode($keySecret, true);
        } else {
            if (app()->environment('production')) {
                throw new GraphException('credentials_path_disabled');
            }
            $certificatePath = $this->securePath(config('contact-mail.graph.certificate_path'));
            $privatePath = $this->securePath(config('contact-mail.graph.private_key_path'));
            $certificate = @file_get_contents($certificatePath);
            $key = @file_get_contents($privatePath);
        }
        if (! is_string($certificate) || ! is_string($key)) {
            throw new GraphException('credentials_unavailable');
        }
        $parsed = @openssl_x509_read($certificate);
        $private = @openssl_pkey_get_private($key, $this->passphrase());
        if (! $parsed || ! $private || ! @openssl_x509_check_private_key($parsed, $private)) {
            throw new GraphException('credentials_invalid');
        }
        $details = openssl_x509_parse($parsed);
        if (! $details || ($details['validFrom_time_t'] ?? PHP_INT_MAX) > time() || ($details['validTo_time_t'] ?? 0) <= time()) {
            throw new GraphException('certificate_expired');
        }
        $fingerprint = strtolower(str_replace(':', '', (string) openssl_x509_fingerprint($parsed, 'sha1')));
        $expected = strtolower(str_replace(':', '', (string) config('contact-mail.graph.sha1_thumbprint')));
        if ($expected !== '' && ! hash_equals($expected, $fingerprint)) {
            throw new GraphException('certificate_mismatch');
        }

        return [$certificate, $key, $fingerprint];
    }

    private function passphrase(): string
    {
        $value = getenv('MS_GRAPH_PRIVATE_KEY_PASSPHRASE');

        return $value !== false ? $value : (string) config('contact-mail.graph.private_key_passphrase');
    }

    private function securePath(mixed $path): string
    {
        $resolved = is_string($path) ? realpath($path) : false;
        $root = realpath(base_path());
        if (! $resolved || ! is_file($resolved) || ! is_readable($resolved) || ($root && str_starts_with($resolved, $root.DIRECTORY_SEPARATOR))) {
            throw new GraphException('credentials_path');
        }

        return $resolved;
    }

    private function der(string $pem): string
    {
        $body = preg_replace('/-----BEGIN CERTIFICATE-----|-----END CERTIFICATE-----|\s+/', '', $pem);
        $der = base64_decode((string) $body, true);
        if ($der === false) {
            throw new GraphException('certificate_invalid');
        }

        return $der;
    }

    private function cacheKey(string $tenant, string $client, string $fingerprint): string
    {
        return 'contact-graph-token:'.hash('sha256', $tenant.$client.$fingerprint);
    }

    private function base64url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}

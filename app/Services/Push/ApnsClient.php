<?php

namespace App\Services\Push;

use App\Models\MobileDevice;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class ApnsClient
{
    /** @param array<string, mixed> $payload */
    public function send(MobileDevice $device, array $payload): void
    {
        if (! config('services.apns.key_id') || ! config('services.apns.team_id') || ! config('services.apns.bundle_id') || ! config('services.apns.private_key')) {
            return;
        }
        $host = $device->environment === 'production' ? 'https://api.push.apple.com' : 'https://api.sandbox.push.apple.com';
        $response = Http::withToken($this->jwt())
            ->withHeaders([
                'apns-topic' => config('services.apns.bundle_id'),
                'apns-push-type' => 'alert',
                'apns-priority' => '10',
            ])->post("{$host}/3/device/{$device->push_token}", $payload);

        $reason = $response->json('reason');
        if ($response->status() === 410 || in_array($reason, ['BadDeviceToken', 'DeviceTokenNotForTopic', 'Unregistered'], true)) {
            $device->update(['enabled' => false]);
        }
        if ($response->status() === 429 || $response->serverError()) {
            $response->throw();
        }
    }

    private function jwt(): string
    {
        return Cache::remember('apns:provider-jwt', now()->addMinutes(50), function (): string {
            $encodedHeader = $this->base64Url(json_encode(['alg' => 'ES256', 'kid' => config('services.apns.key_id')], JSON_THROW_ON_ERROR));
            $encodedClaims = $this->base64Url(json_encode(['iss' => config('services.apns.team_id'), 'iat' => time()], JSON_THROW_ON_ERROR));
            $input = "{$encodedHeader}.{$encodedClaims}";
            $privateKey = config('services.apns.private_key');
            if (is_string($privateKey) && ! str_contains($privateKey, 'BEGIN PRIVATE KEY') && is_file($privateKey)) {
                $privateKey = file_get_contents($privateKey);
            }
            abort_unless(openssl_sign($input, $derSignature, $privateKey, OPENSSL_ALGO_SHA256), 500, 'Unable to sign APNs token.');

            return $input.'.'.$this->base64Url($this->derToJose($derSignature));
        });
    }

    private function derToJose(string $der): string
    {
        $offset = 2;
        abort_unless(ord($der[0]) === 0x30 && ord($der[$offset]) === 0x02, 500, 'Invalid APNs signature.');
        $rLength = ord($der[++$offset]);
        $r = substr($der, ++$offset, $rLength);
        $offset += $rLength;
        abort_unless(ord($der[$offset]) === 0x02, 500, 'Invalid APNs signature.');
        $sLength = ord($der[++$offset]);
        $s = substr($der, ++$offset, $sLength);

        return str_pad(ltrim($r, "\0"), 32, "\0", STR_PAD_LEFT).str_pad(ltrim($s, "\0"), 32, "\0", STR_PAD_LEFT);
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}

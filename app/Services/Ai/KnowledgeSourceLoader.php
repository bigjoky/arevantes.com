<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;

class KnowledgeSourceLoader
{
    public function fromUrl(string $url): string
    {
        $currentUrl = $url;
        $response = null;
        for ($redirects = 0; $redirects <= 3; $redirects++) {
            $this->assertPublicUrl($currentUrl);
            $response = Http::timeout(20)
                ->connectTimeout(5)
                ->withOptions(['allow_redirects' => false])
                ->withUserAgent('Arevantes-Arevia-Knowledge/1.0')
                ->get($currentUrl);
            if (! $response->redirect()) {
                break;
            }
            $location = $response->header('Location');
            if (! is_string($location) || $location === '' || $redirects === 3) {
                abort(422, __('The source has too many or invalid redirects.'));
            }
            $currentUrl = $this->redirectUrl($currentUrl, $location);
        }
        if (! $response->successful()) {
            abort(422, __('The source could not be downloaded.'));
        }
        if (strlen($response->body()) > 5_000_000) {
            abort(422, __('The web source is too large.'));
        }

        $contentType = strtolower($response->header('Content-Type'));
        if (! str_contains($contentType, 'text/html') && ! str_contains($contentType, 'text/plain')) {
            abort(422, __('The URL must contain an HTML or plain-text page.'));
        }
        $text = str_contains($contentType, 'text/html')
            ? html_entity_decode(strip_tags(preg_replace('/<(script|style)\b[^>]*>.*?<\/\1>/is', ' ', $response->body())), ENT_QUOTES | ENT_HTML5, 'UTF-8')
            : $response->body();

        return trim(preg_replace('/[\t ]+/', ' ', preg_replace('/\R{3,}/', "\n\n", $text)));
    }

    private function assertPublicUrl(string $url): void
    {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $host = parse_url($url, PHP_URL_HOST);
        if (! in_array($scheme, ['http', 'https'], true) || ! is_string($host) || ! $this->isPublicHost($host)) {
            abort(422, __('Only public web sources can be indexed.'));
        }
    }

    private function redirectUrl(string $currentUrl, string $location): string
    {
        if (preg_match('#^https?://#i', $location)) {
            return $location;
        }
        $scheme = (string) parse_url($currentUrl, PHP_URL_SCHEME);
        if (str_starts_with($location, '//')) {
            return $scheme.':'.$location;
        }
        $authority = $scheme.'://'.parse_url($currentUrl, PHP_URL_HOST).
            (($port = parse_url($currentUrl, PHP_URL_PORT)) ? ':'.$port : '');

        return str_starts_with($location, '/')
            ? $authority.$location
            : $authority.rtrim(dirname((string) parse_url($currentUrl, PHP_URL_PATH)), '/').'/'.$location;
    }

    private function isPublicHost(string $host): bool
    {
        if (in_array(strtolower($host), ['localhost', 'localhost.localdomain'], true)) {
            return false;
        }
        $addresses = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : (gethostbynamel($host) ?: []);

        return $addresses !== [] && collect($addresses)->every(fn (string $address) => filter_var(
            $address,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        ) !== false);
    }
}

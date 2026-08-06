<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;

class KnowledgeSourceLoader
{
    public function fromUrl(string $url): string
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (! is_string($host) || ! $this->isPublicHost($host)) {
            abort(422, __('Only public web sources can be indexed.'));
        }

        $response = Http::timeout(20)
            ->connectTimeout(5)
            ->withOptions(['allow_redirects' => false])
            ->withUserAgent('Arevantes-Arevia-Knowledge/1.0')
            ->get($url);
        if (! $response->successful()) {
            abort(422, __('The source could not be downloaded. Use its final public URL if it redirects.'));
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

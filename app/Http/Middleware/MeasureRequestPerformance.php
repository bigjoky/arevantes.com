<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MeasureRequestPerformance
{
    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = hrtime(true);
        $databaseStartedAt = DB::connection()->totalQueryDuration();
        $response = $next($request);
        $durationMs = round((hrtime(true) - $startedAt) / 1_000_000, 1);
        $databaseMs = round(max(0, DB::connection()->totalQueryDuration() - $databaseStartedAt), 1);
        $responseBytes = $this->responseBytes($response);

        $timings = "app;dur={$durationMs}, db;dur={$databaseMs}";
        $existingTimings = $response->headers->get('Server-Timing');
        $response->headers->set('Server-Timing', $existingTimings ? "{$existingTimings}, {$timings}" : $timings);

        if ($durationMs >= config('performance.slow_request_ms') || ($responseBytes !== null && $responseBytes >= config('performance.large_response_bytes'))) {
            Log::warning('Web request exceeded its performance budget.', [
                'correlation_id' => $request->attributes->get('correlation_id'),
                'route' => $request->route()?->getName(),
                'method' => $request->method(),
                'status' => $response->getStatusCode(),
                'duration_ms' => $durationMs,
                'database_ms' => $databaseMs,
                'response_bytes' => $responseBytes,
            ]);
        }

        return $response;
    }

    private function responseBytes(Response $response): ?int
    {
        if ($response instanceof BinaryFileResponse || $response instanceof StreamedResponse) {
            return null;
        }

        $content = $response->getContent();

        return is_string($content) ? strlen($content) : null;
    }
}

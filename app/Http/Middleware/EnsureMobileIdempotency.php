<?php

namespace App\Http\Middleware;

use App\Models\MobileOperation;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class EnsureMobileIdempotency
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header('Idempotency-Key');
        abort_unless(is_string($key) && Str::isUuid($key), 422, __('A valid Idempotency-Key UUID header is required.'));

        $existing = MobileOperation::where('user_id', $request->user()->id)->where('key', $key)->first();
        if ($existing) {
            abort_unless($existing->method === $request->method() && $existing->path === $request->path(), 409, __('Idempotency key was already used for another operation.'));

            return new JsonResponse($existing->response_body, $existing->response_status, ['Idempotency-Replayed' => 'true']);
        }

        $response = $next($request);
        if ($response instanceof JsonResponse && $response->isSuccessful()) {
            MobileOperation::create([
                'user_id' => $request->user()->id,
                'key' => $key,
                'method' => $request->method(),
                'path' => $request->path(),
                'response_status' => $response->getStatusCode(),
                'response_body' => $response->getData(true),
            ]);
        }

        return $response;
    }
}

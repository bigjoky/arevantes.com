<?php

namespace App\Http\Middleware;

use App\Models\MobileOperation;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class EnsureMobileIdempotency
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header('Idempotency-Key');
        abort_unless(is_string($key) && Str::isUuid($key), 422, __('A valid Idempotency-Key UUID header is required.'));

        $operation = MobileOperation::firstOrCreate([
            'user_id' => $request->user()->id,
            'key' => $key,
        ], [
            'method' => $request->method(),
            'path' => $request->path(),
            'response_status' => 102,
            'response_body' => null,
        ]);
        if (! $operation->wasRecentlyCreated) {
            abort_unless($operation->method === $request->method() && $operation->path === $request->path(), 409, __('Idempotency key was already used for another operation.'));
            if ($operation->response_status === 102) {
                return new JsonResponse(['message' => __('This operation is already being processed.')], 409, [
                    'Idempotency-In-Progress' => 'true',
                    'Retry-After' => '2',
                ]);
            }

            return new JsonResponse($operation->response_body, $operation->response_status, ['Idempotency-Replayed' => 'true']);
        }

        try {
            $response = $next($request);
            if ($response instanceof JsonResponse && $response->isSuccessful()) {
                $operation->update([
                    'response_status' => $response->getStatusCode(),
                    'response_body' => $response->getData(true),
                ]);
            } else {
                $operation->delete();
            }
        } catch (Throwable $exception) {
            $operation->delete();

            throw $exception;
        }

        return $response;
    }
}

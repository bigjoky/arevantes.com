<?php

use App\Http\Middleware\AddSecurityHeaders;
use App\Http\Middleware\CorrelateRequests;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\MeasureRequestPerformance;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            MeasureRequestPerformance::class,
            CorrelateRequests::class,
            AddSecurityHeaders::class,
            HandleAppearance::class,
            SetLocale::class,
            HandleInertiaRequests::class,
        ]);
        $middleware->api(append: [
            CorrelateRequests::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->routeIs('engineering.availability'),
        );
        $exceptions->report(function (Throwable $exception) {
            if (app()->bound('request')) {
                logger()->error('Unhandled application error', [
                    'correlation_id' => request()->attributes->get('correlation_id'),
                    'user_id' => request()->user()?->id,
                    'operation' => request()->route()?->getName(),
                    'exception' => $exception::class,
                ]);
            }
        });
    })->create();

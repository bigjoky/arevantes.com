<?php

use App\Http\Middleware\AddSecurityHeaders;
use App\Http\Middleware\CorrelateRequests;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\MeasureRequestPerformance;
use App\Http\Middleware\SetLocale;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

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
        $exceptions->map(UniqueConstraintViolationException::class, function ($exception) {
            $message = $exception->getMessage();
            if (str_contains($message, 'received_invoice_fiscal_identity_unique') || str_contains($message, 'received_invoice_identities.receiver_company_id')) {
                return ValidationException::withMessages(['document_number' => 'Esta factura ya está registrada en Compras o Gastos. Utiliza el documento existente.', 'supplier_number' => 'Esta factura ya está registrada en Compras o Gastos. Utiliza el documento existente.']);
            }
            if (str_contains($message, 'purchase_supplier_document_identity_unique') || str_contains($message, 'purchase_documents.supplier_id, purchase_documents.type, purchase_documents.supplier_number')) {
                return ValidationException::withMessages(['supplier_number' => __('This supplier document number is already registered.')]);
            }
            if (str_contains($message, 'expense_issuer_document_identity_unique')) {
                return ValidationException::withMessages(['document_number' => __('This issuer invoice is already registered.')]);
            }

            return $exception;
        });
        $exceptions->map(QueryException::class, function ($exception) {
            foreach (['received_invoice_fiscal_identity_unique', 'received_invoice_identity_incomplete', 'received_invoice_identity_immutable'] as $constraint) {
                if (str_contains($exception->getMessage(), $constraint)) {
                    return ValidationException::withMessages(['document_number' => 'Revisa la identidad fiscal y el documento original antes de continuar. Una identidad ya registrada no puede sustituirse silenciosamente.', 'supplier_number' => 'Revisa la identidad fiscal y el documento original antes de continuar.']);
                }
            }

            return $exception;
        });
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->expectsJson() || $request->is('api/*') || $request->routeIs('engineering.availability'),
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

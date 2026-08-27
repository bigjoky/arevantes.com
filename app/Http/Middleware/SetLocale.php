<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        $supported = array_keys(config('localization.supported', []));
        $locale = $request->session()->get('locale', config('app.locale'));
        App::setLocale(in_array($locale, $supported, true) ? $locale : config('app.locale'));

        return $next($request);
    }
}

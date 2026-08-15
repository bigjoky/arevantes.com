<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMobileAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless($user && $user->tokenCan('mobile'), 403, __('This token cannot access the mobile API.'));
        abort_unless($user->hasRole('admin', 'commercial', 'technician'), 403, __('Your current role cannot access the mobile API.'));

        return $next($request);
    }
}

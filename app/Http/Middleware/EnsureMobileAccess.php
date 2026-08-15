<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class EnsureMobileAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless($user && $user->tokenCan('mobile'), 403, __('This token cannot access the mobile API.'));
        abort_unless(Gate::forUser($user)->allows('manage-operations'), 403, __('Your current permissions cannot access the mobile API.'));

        return $next($request);
    }
}

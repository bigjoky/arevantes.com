<?php

namespace App\Http\Middleware;

use App\Models\CompanyProfile;
use Closure;
use Illuminate\Http\Request;

class CompanyWorkspaceContext
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless($request->user()?->can('manage-staff') || $request->user()?->can('manage-admin') || $request->user()?->can('manage-tax'), 403);
        abort_if(CompanyProfile::count() > 1, 409, __('Company workspace separation is not configured.'));

        return $next($request);
    }
}

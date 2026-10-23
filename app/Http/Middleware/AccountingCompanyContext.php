<?php

namespace App\Http\Middleware;

use App\Models\CompanyProfile;
use Closure;
use Illuminate\Http\Request;

/** Until company ownership exists throughout the ledger, refuse ambiguous company context. */
class AccountingCompanyContext
{
    public function handle(Request $request, Closure $next)
    {
        abort_if(CompanyProfile::count() > 1, 409, __('Accounting company separation is not configured.'));

        return $next($request);
    }
}

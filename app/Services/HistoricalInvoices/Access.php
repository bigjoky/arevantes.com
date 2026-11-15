<?php

namespace App\Services\HistoricalInvoices;

use App\Models\User;
use App\Services\Security\PermissionResolver;

final class Access
{
    public function allows(?User $user): bool
    {
        return (bool) ($user && config('fiscal.historical_invoice_2026') && $user->hasRole('admin')
            && $user->permission_profile_id && $user->can('manage-accounting') && $user->can('view-finance')
            && app(PermissionResolver::class)->allows($user, 'manage-historical-invoices-2026'));
    }
}

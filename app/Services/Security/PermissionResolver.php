<?php

namespace App\Services\Security;

use App\Models\User;
use Illuminate\Support\Facades\Schema;

class PermissionResolver
{
    public function allows(User $user, string $permission): bool
    {
        if (! Schema::hasTable('permissions') || ! $user->permission_profile_id) {
            return $this->legacyAllows($user, $permission);
        }

        $override = $user->permissionOverrides()->where('key', $permission)->first()?->pivot?->allowed;
        if ($override !== null) {
            return (bool) $override;
        }

        return $user->permissionProfile?->permissions()->where('key', $permission)->exists() ?? false;
    }

    private function legacyAllows(User $user, string $permission): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }
        $technician = ['view-installations', 'sync-installations', 'use-ai', 'manage-operations', 'view-purchases', 'receive-purchases', 'view-analytics', 'view-analytics-operations'];
        $commercial = ['manage-customers', 'manage-quotes', 'view-installations', 'manage-installations', 'use-ai', 'manage-operations', 'manage-catalog', 'view-finance', 'manage-treasury', 'manage-accounting', 'view-purchases', 'manage-purchases', 'receive-purchases', 'approve-purchase-invoices', 'view-analytics', 'view-analytics-sales'];

        return in_array($permission, $user->hasRole('technician') ? $technician : $commercial, true);
    }
}

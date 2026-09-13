<?php

namespace App\Services\Security;

use App\Models\User;
use Illuminate\Support\Facades\Schema;

class PermissionResolver
{
    private ?bool $permissionsTableExists = null;

    public function allows(User $user, string $permission): bool
    {
        if (! $this->permissionsTableExists() || ! $user->permission_profile_id) {
            return $this->legacyAllows($user, $permission);
        }

        $user->loadMissing(['permissionOverrides', 'permissionProfile.permissions']);

        $override = $user->permissionOverrides
            ->firstWhere('key', $permission)
            ?->pivot
            ?->allowed;

        if ($override !== null) {
            return (bool) $override;
        }

        return $user->permissionProfile?->permissions->contains('key', $permission) ?? false;
    }

    private function permissionsTableExists(): bool
    {
        return $this->permissionsTableExists ??= Schema::hasTable('permissions');
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

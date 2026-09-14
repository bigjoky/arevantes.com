<?php

namespace App\Services\Security;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class PermissionResolver
{
    private ?bool $permissionsTableExists = null;

    private ?Request $request = null;

    /** @var array<int|string, array<string, bool>> */
    private array $overridesByUser = [];

    /** @var array<int|string, array<string, true>> */
    private array $profilePermissionsByUser = [];

    public function allows(User $user, string $permission): bool
    {
        if (! $this->permissionsTableExists() || ! $user->permission_profile_id) {
            return $this->legacyAllows($user, $permission);
        }

        $this->resetForNewRequest();
        $userKey = $user->getKey();

        if (! array_key_exists($userKey, $this->overridesByUser)) {
            $user->load(['permissionOverrides', 'permissionProfile.permissions']);

            $this->overridesByUser[$userKey] = $user->permissionOverrides
                ->mapWithKeys(fn ($override) => [$override->key => (bool) $override->pivot->allowed])
                ->all();
            $this->profilePermissionsByUser[$userKey] = $user->permissionProfile?->permissions
                ->mapWithKeys(fn ($profilePermission) => [$profilePermission->key => true])
                ->all() ?? [];
        }

        if (array_key_exists($permission, $this->overridesByUser[$userKey])) {
            return $this->overridesByUser[$userKey][$permission];
        }

        return isset($this->profilePermissionsByUser[$userKey][$permission]);
    }

    private function permissionsTableExists(): bool
    {
        return $this->permissionsTableExists ??= Schema::hasTable('permissions');
    }

    private function resetForNewRequest(): void
    {
        if (! app()->bound('request')) {
            return;
        }

        $request = app('request');
        if ($this->request === $request) {
            return;
        }

        $this->request = $request;
        $this->overridesByUser = [];
        $this->profilePermissionsByUser = [];
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

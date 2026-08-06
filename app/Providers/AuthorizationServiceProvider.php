<?php

namespace App\Providers;

use App\Models\CrmActivity;
use App\Models\User;
use App\Policies\CrmActivityPolicy;
use App\Support\Authorization\PlatformAbility;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AuthorizationServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(CrmActivity::class, CrmActivityPolicy::class);
        Gate::before(fn (User $user) => $user->hasRole('admin') ? true : null);

        foreach (PlatformAbility::cases() as $ability) {
            Gate::define($ability->value, fn (User $user) => $ability->allows($user));
        }
    }
}

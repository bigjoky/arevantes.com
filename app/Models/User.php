<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Schema;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['person_id', 'name', 'email', 'password', 'role', 'permission_profile_id', 'access_reviewed_at', 'access_review_due_at'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    protected static function booted(): void
    {
        static::creating(function (User $user): void {
            if (! $user->permission_profile_id && Schema::hasTable('permission_profiles')) {
                $slug = $user->role === 'admin' ? 'administrator' : ($user->role === 'technician' ? 'technician' : 'commercial');
                $user->permission_profile_id = PermissionProfile::where('slug', $slug)->value('id');
                $user->access_review_due_at ??= now()->addMonths((int) config('security.access_review_months', 3));
            }
        });
    }

    public function hasRole(string ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    public function permissionProfile(): BelongsTo
    {
        return $this->belongsTo(PermissionProfile::class);
    }

    public function permissionOverrides(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'user_permission_overrides')
            ->withPivot(['allowed', 'granted_by'])->withTimestamps();
    }

    public function staffProfile(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'person_id');
    }

    public function ownedOpportunities(): HasMany
    {
        return $this->hasMany(Opportunity::class, 'owner_user_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'access_reviewed_at' => 'datetime',
            'access_review_due_at' => 'datetime',
        ];
    }
}

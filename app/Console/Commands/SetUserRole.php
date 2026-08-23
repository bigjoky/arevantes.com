<?php

namespace App\Console\Commands;

use App\Models\PermissionProfile;
use App\Models\User;
use Illuminate\Console\Command;

class SetUserRole extends Command
{
    protected $signature = 'user:role {email} {role : admin, commercial or technician}';

    protected $description = 'Assign a CRM authorization role to a user';

    public function handle(): int
    {
        $role = $this->argument('role');

        if (! in_array($role, ['admin', 'commercial', 'technician'], true)) {
            $this->error('Role must be admin, commercial or technician.');

            return self::FAILURE;
        }

        $user = User::where('email', $this->argument('email'))->first();

        if (! $user) {
            $this->error('User not found.');

            return self::FAILURE;
        }

        $slug = $role === 'admin' ? 'administrator' : $role;
        $profile = PermissionProfile::where('slug', $slug)->firstOrFail();
        $administrator = PermissionProfile::where('slug', 'administrator')->firstOrFail();
        if ($user->permission_profile_id === $administrator->id && $profile->id !== $administrator->id && User::where('permission_profile_id', $administrator->id)->count() === 1) {
            $this->error('The last administrator cannot be demoted.');

            return self::FAILURE;
        }

        $user->update([
            'role' => $role,
            'permission_profile_id' => $profile->id,
            'access_reviewed_at' => now(),
            'access_review_due_at' => now()->addMonths((int) config('security.access_review_months', 3)),
        ]);
        $this->info("{$user->email} is now {$role}.");

        return self::SUCCESS;
    }
}

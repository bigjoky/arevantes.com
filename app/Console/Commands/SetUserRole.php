<?php

namespace App\Console\Commands;

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

        $user->update(['role' => $role]);
        $this->info("{$user->email} is now {$role}.");

        return self::SUCCESS;
    }
}

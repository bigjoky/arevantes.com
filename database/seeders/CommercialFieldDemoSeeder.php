<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\User;
use Illuminate\Database\Seeder;

class CommercialFieldDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('Field demo accounts may only be created in local or testing environments.');
        }
        $commercial = User::firstOrCreate(['email' => 'campo.demo@example.test'], [
            'name' => 'Comercial demo', 'role' => 'commercial', 'password' => 'FieldDemo-2026!',
        ]);
        $supervisor = User::firstOrCreate(['email' => 'supervisor.demo@example.test'], [
            'name' => 'Supervisor demo', 'role' => 'commercial', 'password' => 'FieldDemo-2026!',
        ]);
        $commercial->forceFill(['email_verified_at' => now()])->save();
        $supervisor->forceFill(['email_verified_at' => now()])->save();
        $supervisor->permissionOverrides()->syncWithoutDetaching([
            Permission::where('key', 'supervise-commercial-field')->value('id') => ['allowed' => true],
        ]);
    }
}

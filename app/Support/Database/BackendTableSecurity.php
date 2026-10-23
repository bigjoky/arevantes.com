<?php

namespace App\Support\Database;

use Illuminate\Support\Facades\DB;

final class BackendTableSecurity
{
    /** Laravel owns these tables; public Supabase API roles must never bypass its gates. */
    public static function restrict(string $table): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }
        if (! preg_match('/^[a-z_]+$/D', $table)) {
            throw new \InvalidArgumentException('Invalid backend table identifier.');
        }
        DB::statement('ALTER TABLE "'.$table.'" ENABLE ROW LEVEL SECURITY');
        DB::statement('REVOKE ALL PRIVILEGES ON TABLE "'.$table.'" FROM PUBLIC');
        foreach (['anon', 'authenticated'] as $role) {
            if (DB::selectOne('SELECT 1 FROM pg_roles WHERE rolname = ?', [$role])) {
                DB::statement('REVOKE ALL PRIVILEGES ON TABLE "'.$table.'" FROM "'.$role.'"');
            }
        }
    }
}

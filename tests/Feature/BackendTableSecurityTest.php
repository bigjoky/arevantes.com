<?php

namespace Tests\Feature;

use App\Support\Database\BackendTableSecurity;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BackendTableSecurityTest extends TestCase
{
    public function test_new_backend_tables_remain_private_while_laravel_owner_can_use_them(): void
    {
        if (DB::getDriverName() !== 'pgsql' || ! str_ends_with(DB::connection()->getDatabaseName(), '_concurrency_test')) {
            $this->markTestSkipped('Requires an isolated PostgreSQL database ending in _concurrency_test.');
        }
        $this->artisan('migrate:fresh', ['--seed' => true, '--force' => true])->assertExitCode(0);
        DB::beginTransaction();
        try {
            foreach (['anon', 'authenticated'] as $role) {
                if (! DB::selectOne('SELECT 1 FROM pg_roles WHERE rolname = ?', [$role])) {
                    DB::statement('CREATE ROLE "'.$role.'" NOLOGIN');
                }
            }
            foreach (['fiscal_entities', 'expense_fiscal_classifications', 'company_census_versions', 'purchase_fiscal_classifications', 'purchase_receipt_requests', 'company_documents', 'company_document_versions'] as $table) {
                // Simulate Supabase's default API grants before applying the guard.
                DB::statement('GRANT ALL PRIVILEGES ON TABLE "'.$table.'" TO anon, authenticated, PUBLIC');
                BackendTableSecurity::restrict($table);
                $this->assertTrue(DB::selectOne('SELECT relrowsecurity FROM pg_class WHERE oid = ?::regclass', [$table])->relrowsecurity);
                foreach (['anon', 'authenticated'] as $role) {
                    foreach (['SELECT', 'INSERT', 'UPDATE', 'DELETE'] as $privilege) {
                        $this->assertFalse(DB::selectOne('SELECT has_table_privilege(?, ?, ?) AS allowed', [$role, $table, $privilege])->allowed);
                    }
                }
                // Owner/bypass connection used by Laravel can still query its table.
                $this->assertIsInt(DB::table($table)->count());
            }
        } finally {
            DB::rollBack();
        }
    }
}

<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WebsiteContactPostgresSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_campaign_unique_index_rejects_a_second_consumed_uuid(): void
    {
        if (DB::getDriverName() !== 'pgsql') { $this->markTestSkipped('PostgreSQL is required.'); }
        $admin = \App\Models\User::factory()->create(['role' => 'admin']);
        $first = (string) \Illuminate\Support\Str::uuid();
        $second = (string) \Illuminate\Support\Str::uuid();
        foreach ([$first, $second] as $id) {
            \App\Models\WebsiteGraphTestAuthorization::create(['id' => $id, 'authorized_by' => $admin->id, 'status' => 'authorized']);
        }
        \App\Models\WebsiteGraphTestAuthorization::whereKey($first)->update(['status' => 'sending', 'attempted_at' => now()]);
        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);
        \App\Models\WebsiteGraphTestAuthorization::whereKey($second)->update(['status' => 'sending', 'attempted_at' => now()]);
    }

    public function test_graph_test_authorizations_are_not_exposed_to_api_roles(): void
    {
        if (DB::getDriverName() !== 'pgsql') { $this->markTestSkipped('PostgreSQL is required.'); }
        $table = 'website_graph_test_authorizations';
        $this->assertTrue(DB::selectOne('select relrowsecurity from pg_class where oid = ?::regclass', [$table])->relrowsecurity);
        foreach (['anon', 'authenticated', 'service_role'] as $role) {
            if (! DB::selectOne('select exists(select 1 from pg_roles where rolname = ?) as present', [$role])->present) { $this->markTestSkipped('Supabase API roles are required.'); }
            foreach (['SELECT', 'INSERT', 'UPDATE', 'DELETE'] as $privilege) {
                $this->assertFalse(DB::selectOne('select has_table_privilege(?, ?, ?) as allowed', [$role, $table, $privilege])->allowed);
            }
        }
    }

    public function test_contact_tables_are_private_from_api_roles(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('PostgreSQL is required.');
        }

        foreach (['website_contact_receipts', 'website_contact_mail'] as $table) {
            $metadata = DB::selectOne('select c.relrowsecurity, pg_get_serial_sequence(?, ?) as seq from pg_class c where c.oid = ?::regclass', [$table, 'id', $table]);
            $this->assertTrue($metadata->relrowsecurity, $table.' must enable RLS');
            $this->assertNotNull($metadata->seq);

            foreach ([$table, $metadata->seq] as $object) {
                $publicGrants = DB::selectOne('select count(*) as count from pg_class c cross join lateral aclexplode(coalesce(c.relacl, acldefault(c.relkind, c.relowner))) a where c.oid = ?::regclass and a.grantee = 0', [$object]);
                $this->assertSame(0, $publicGrants->count, "PUBLIC grants on $object");
            }

            foreach (['anon', 'authenticated', 'service_role'] as $role) {
                if (! DB::selectOne('select exists(select 1 from pg_roles where rolname = ?) as present', [$role])->present) {
                    $this->markTestSkipped('Supabase API roles are required for this test.');
                }
                foreach (['SELECT', 'INSERT', 'UPDATE', 'DELETE', 'TRUNCATE', 'REFERENCES', 'TRIGGER'] as $privilege) {
                    $this->assertFalse(DB::selectOne('select has_table_privilege(?, ?, ?) as allowed', [$role, $table, $privilege])->allowed, "$role $privilege $table");
                }
                foreach (['USAGE', 'SELECT', 'UPDATE'] as $privilege) {
                    $this->assertFalse(DB::selectOne('select has_sequence_privilege(?, ?, ?) as allowed', [$role, $metadata->seq, $privilege])->allowed, "$role $privilege $metadata->seq");
                }
            }

            $this->assertSame(0, DB::selectOne('select count(*) as count from pg_policies where schemaname = current_schema() and tablename = ?', [$table])->count);
        }
    }
}

<?php

namespace Tests\Feature;

use App\Models\PurchaseDocument;
use App\Models\Supplier;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FiscalViewSecurityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (DB::getDriverName() !== 'pgsql' || config('database.connections.pgsql.host') !== '127.0.0.1' || ! str_ends_with(DB::connection()->getDatabaseName(), '_sec01_test')) {
            $this->markTestSkipped('Requires the explicitly marked disposable local SEC-01 PostgreSQL database.');
        }
        $marker = DB::selectOne("SELECT shobj_description(oid, 'pg_database') AS marker FROM pg_database WHERE datname = current_database()")->marker;
        $this->assertSame('arevantes:sec01:synthetic-disposable:v1', $marker);
        foreach (['anon', 'authenticated', 'sec01_api_reader'] as $role) {
            $this->assertTrue(DB::selectOne('SELECT EXISTS(SELECT 1 FROM pg_roles WHERE rolname = ?) AS present', [$role])->present, 'Provision test-only NOLOGIN roles before running this suite.');
        }
        $this->artisan('migrate:fresh', ['--force' => true])->assertSuccessful();
        foreach (['purchase_documents', 'expenses', 'suppliers', 'company_profiles'] as $table) {
            DB::statement('ALTER TABLE public.'.$table.' ENABLE ROW LEVEL SECURITY');
            DB::statement('GRANT SELECT ON public.'.$table.' TO PUBLIC');
        }
        $supplier = Supplier::create(['legal_name' => 'Synthetic SEC-01 issuer', 'country' => 'ES', 'tax_id' => 'B12345678']);
        PurchaseDocument::create(['type' => 'supplier_invoice', 'supplier_id' => $supplier->id, 'supplier_number' => 'SYN-SEC-01', 'number' => 'SYN-PURCHASE', 'issue_date' => today(), 'status' => 'registered', 'subtotal' => 100, 'tax_amount' => 0, 'amount' => 100]);
    }

    private function queryAs(string $role, string $query): array
    {
        DB::beginTransaction();
        try {
            DB::statement('SET TRANSACTION READ ONLY');
            DB::statement('SET LOCAL ROLE "'.$role.'"');

            return DB::select($query);
        } finally {
            DB::rollBack();
        }
    }

    public function test_api_roles_have_neither_table_nor_column_access(): void
    {
        foreach (['anon', 'authenticated'] as $role) {
            $this->assertFalse(DB::selectOne("SELECT has_any_column_privilege(?, 'public.received_invoice_candidates', 'SELECT') AS allowed", [$role])->allowed);
            foreach (['SELECT count(*) FROM public.received_invoice_candidates', 'SELECT issuer_tax_id FROM public.received_invoice_candidates LIMIT 0'] as $query) {
                try {
                    $this->queryAs($role, $query);
                    $this->fail('An API role unexpectedly accessed the fiscal view.');
                } catch (QueryException $exception) {
                    $this->assertSame('42501', $exception->getCode());
                }
            }
        }
        $this->assertSame(1, DB::table('received_invoice_candidates')->count());
    }

    public function test_public_and_inherited_role_regrants_still_respect_base_rls(): void
    {
        foreach (['PUBLIC', 'sec01_api_reader'] as $grantee) {
            DB::statement('GRANT SELECT ON public.received_invoice_candidates TO '.$grantee);
            foreach (['anon', 'authenticated'] as $role) {
                $this->assertSame(0, $this->queryAs($role, 'SELECT count(*) AS count FROM public.received_invoice_candidates')[0]->count);
            }
            DB::statement('REVOKE SELECT ON public.received_invoice_candidates FROM '.$grantee);
        }
        $this->assertSame(1, DB::table('received_invoice_candidates')->count());
    }

    public function test_reapplication_preserves_definition_oid_rows_and_economic_triggers(): void
    {
        $before = $this->snapshot();
        $migration = require database_path('migrations/2026_10_28_121228_restrict_public_received_invoice_view.php');
        $migration->up();
        $migration->up();
        $this->assertSame($before, $this->snapshot());
        $this->assertStringContainsString('security_invoker=true', DB::selectOne("SELECT reloptions::text AS options FROM pg_class WHERE oid='public.received_invoice_candidates'::regclass")->options);
        $this->assertSame(16, DB::table('pg_trigger')->where('tgisinternal', false)->whereIn('tgenabled', ['O', 'A'])->count());
        $this->artisan('time:audit-instants', ['--strict' => true])->assertSuccessful();
        $this->artisan('received-invoices:audit')->assertSuccessful();
    }

    public function test_rollback_refuses_to_reopen_public_access(): void
    {
        $migration = require database_path('migrations/2026_10_28_121228_restrict_public_received_invoice_view.php');
        $this->expectException(\RuntimeException::class);
        $migration->down();
    }

    private function snapshot(): array
    {
        return [
            (array) DB::selectOne("SELECT oid,pg_get_viewdef(oid,true) AS definition FROM pg_class WHERE oid='public.received_invoice_candidates'::regclass"),
            DB::table('received_invoice_candidates')->orderBy('source_kind')->orderBy('source_id')->get()->toJson(),
            DB::table('purchase_documents')->orderBy('id')->get()->toJson(),
            DB::table('expenses')->orderBy('id')->get()->toJson(),
            DB::table('sales_documents')->orderBy('id')->get()->toJson(),
            DB::table('journal_entries')->orderBy('id')->get()->toJson(),
            DB::table('received_invoice_identities')->orderBy('id')->get()->toJson(),
        ];
    }
}

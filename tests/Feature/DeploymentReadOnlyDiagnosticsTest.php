<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class DeploymentReadOnlyDiagnosticsTest extends TestCase
{
    public function test_read_only_queries_detect_reconciliation_and_counter_errors_without_repairing_history(): void
    {
        if (getenv('DEPLOY02_DIAGNOSTICS') !== '1' || DB::getDriverName() !== 'pgsql' || DB::connection()->getDatabaseName() !== 'arevantes_deploy01_incremental_test') {
            $this->markTestSkipped('Requires the marked synthetic incremental dataset.');
        }
        $cluster = new Process(['container', 'exec', 'apple-dev-postgres', 'psql', '-U', 'developer', '-d', 'postgres', '-Atc', 'SELECT system_identifier::text FROM pg_control_system()']);
        $cluster->mustRun();
        $this->assertSame(trim($cluster->getOutput()), DB::selectOne('SELECT system_identifier::text AS id FROM pg_control_system()')->id);
        $this->assertSame('arevantes:deploy01:synthetic:v1', DB::selectOne("SELECT shobj_description(oid,'pg_database') AS marker FROM pg_database WHERE datname=current_database()")->marker);
        $sql = file_get_contents(base_path('scripts/deployment/read-only-diagnostics.sql'));
        preg_match('/SELECT r.id AS reconciliation_id.*?;/s', $sql, $bankQuery);
        preg_match('/WITH numbered AS.*?;/s', $sql, $numberQuery);
        $beforeBank = (array) DB::table('bank_transactions')->first();
        $this->assertCount(1, DB::select($bankQuery[0])); // stale legacy pending status
        DB::beginTransaction();
        try {
            DB::table('bank_transactions')->where('id', $beforeBank['id'])->update(['status' => 'reconciled']);
            $this->assertSame([], DB::select($bankQuery[0]));
            DB::table('bank_transactions')->where('id', $beforeBank['id'])->update(['amount' => '-99.99']);
            $this->assertCount(1, DB::select($bankQuery[0]));
            $series = DB::table('document_series')->where('document_type', 'invoice')->whereNotNull('fiscal_year_id')->first();
            $year = DB::table('fiscal_years')->where('id', $series->fiscal_year_id)->value('year');
            DB::table('sales_documents')->insert(['type' => 'invoice', 'number' => $series->prefix.'-'.$year.'-00099', 'document_series_id' => $series->id, 'status' => 'issued', 'issue_date' => '2026-10-24', 'subtotal' => '1.00', 'tax_amount' => '.21', 'amount' => '1.21', 'customer_snapshot' => '{"name":"Synthetic counter fixture"}']);
            $this->assertCount(1, DB::select($numberQuery[0]));
            DB::table('document_series')->where('id', $series->id)->update(['next_number' => 100]);
            $this->assertSame([], DB::select($numberQuery[0]));
        } finally {
            DB::rollBack();
        }
        $this->assertSame($beforeBank, (array) DB::table('bank_transactions')->first());
    }
}

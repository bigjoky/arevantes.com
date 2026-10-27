<?php

namespace Tests\Feature;

use App\Models\DocumentDelivery;
use App\Models\WorkOrder;
use App\Support\Time\BusinessTime;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TemporalIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private function delivery(string $key, string $at): DocumentDelivery
    {
        return DocumentDelivery::create(['recipient_email' => 'synthetic@example.test', 'token_hash' => hash('sha256', $key), 'status' => 'sent', 'expires_at' => $at]);
    }

    public function test_filters_ordering_distinct_grouping_and_union_keep_the_real_instant(): void
    {
        $later = $this->delivery('later', '2026-10-25T02:10:00+01:00');
        $earlier = $this->delivery('earlier', '2026-10-25T02:50:00+02:00');
        $this->assertSame([$earlier->id, $later->id], DocumentDelivery::orderBy('expires_at')->pluck('id')->all());
        $this->assertSame([$later->id, $earlier->id], DocumentDelivery::orderByDesc('expires_at')->pluck('id')->all());
        $this->assertSame([$later->id], DocumentDelivery::where('expires_at', '>', '2026-10-25T01:00:00Z')->pluck('id')->all());
        $this->assertSame('2026-10-25T01:10:00.000000Z', DocumentDelivery::whereKey($later->id)->get(['id', 'expires_at'])->first()->expires_at->toISOString());
        $this->assertSame('2026-10-25T01:10:00.000000Z', DocumentDelivery::whereKey($later->id)->distinct()->get(['expires_at', 'expires_at_utc'])->first()->expires_at->toISOString());
        $this->assertSame(1, DocumentDelivery::whereBetween('expires_at', ['2026-10-25T01:00:00Z', '2026-10-25T01:20:00Z'])->count());
        $this->assertSame(1, DocumentDelivery::where('expires_at', '<', '2026-10-25T01:00:00Z')->distinct()->count('id'));
        $this->assertSame('2026-10-25T01:10:00.000000Z', DocumentDelivery::whereKey($later->id)->pluck('expires_at')->first()->toISOString());
        $this->assertSame(1, DocumentDelivery::where('expires_at', '<', '2026-10-25T01:00:00Z')->select('status')->groupBy('status')->get()->count());
        $one = DocumentDelivery::where('expires_at', '<', '2026-10-25T01:00:00Z')->select('id');
        $two = DocumentDelivery::where('expires_at', '>', '2026-10-25T01:00:00Z')->select('id');
        $this->assertSame(2, $one->union($two)->get()->count());
    }

    public function test_advanced_projections_require_the_companion_instead_of_losing_the_offset(): void
    {
        $this->delivery('projection', '2026-10-25T02:10:00+01:00');
        $this->expectException(\LogicException::class);
        DocumentDelivery::distinct()->get(['expires_at']);
    }

    public function test_bulk_insert_upsert_and_relationship_updates_keep_pairs(): void
    {
        DocumentDelivery::insert(['recipient_email' => 'synthetic@example.test', 'token_hash' => hash('sha256', 'bulk'), 'status' => 'sent', 'expires_at' => '2026-10-25T02:10:00+01:00']);
        $delivery = DocumentDelivery::firstOrFail();
        $this->assertSame('2026-10-25T01:10:00.000000Z', $delivery->expires_at->toISOString());
        DocumentDelivery::upsert([['id' => $delivery->id, 'recipient_email' => 'synthetic@example.test', 'token_hash' => $delivery->token_hash, 'status' => 'sent', 'expires_at' => '2026-10-25T02:50:00+02:00']], ['id'], ['expires_at']);
        $this->assertSame('2026-10-25T00:50:00.000000Z', $delivery->fresh()->expires_at->toISOString());
        $this->assertSame('precise', BusinessTime::classify($delivery->fresh()->getRawOriginal('expires_at'), $delivery->fresh()->getRawOriginal('expires_at_utc')));
    }

    public function test_audit_classifies_empty_invalid_ambiguous_and_divergent_pairs_without_values_or_writes(): void
    {
        $this->assertSame('empty', BusinessTime::classify(null, null));
        $this->assertSame('ambiguous', BusinessTime::classify('2026-10-25 02:30:00', null));
        $this->assertSame('invalid_or_nonexistent', BusinessTime::classify('2026-03-29 02:30:00', null));
        $this->assertSame('invalid_or_nonexistent', BusinessTime::classify('invalid', null));
        $this->assertSame('divergent', BusinessTime::classify('2026-10-25 04:30:00', '2026-10-25T00:30:00Z'));
        $this->assertSame('divergent', BusinessTime::classify(null, '2026-10-25T00:30:00Z'));
        DB::table('document_deliveries')->insert(['recipient_email' => 'private-fixture@example.test', 'token_hash' => hash('sha256', 'audit'), 'status' => 'sent', 'expires_at' => '2026-10-25 02:30:00']);
        $before = DB::table('document_deliveries')->get()->toJson();
        $code = Artisan::call('time:audit-instants', ['--json' => true, '--strict' => true]);
        $this->assertSame(1, $code);
        $output = Artisan::output();
        $this->assertStringNotContainsString('private-fixture@example.test', $output);
        $this->assertStringNotContainsString(hash('sha256', 'audit'), $output);
        $report = json_decode($output, true, flags: JSON_THROW_ON_ERROR);
        $this->assertTrue($report['read_only']);
        $this->assertSame(1, $report['fields']['document_deliveries.expires_at']['ambiguous']);
        $this->assertSame($before, DB::table('document_deliveries')->get()->toJson());
    }

    public function test_rollback_refuses_to_drop_captured_evidence(): void
    {
        $delivery = $this->delivery('rollback', '2026-10-25T02:30:00+01:00');
        $migration = require database_path('migrations/2026_10_25_030000_preserve_critical_instants_with_utc_companions.php');
        try {
            $migration->down();
            $this->fail('Rollback must preserve evidence.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('UTC evidence exists', $e->getMessage());
        }
        $this->assertSame('2026-10-25T01:30:00.000000Z', $delivery->fresh()->expires_at->toISOString());
    }

    public function test_postgres_all_companions_are_nullable_microsecond_timestamptz_and_queries_have_indexes(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('PostgreSQL migration/index verification.');
        }
        $count = 0;
        foreach (['DocumentDelivery', 'CustomerPortalAccess', 'WorkOrderAttachment', 'PortfolioProject', 'PlanningScenario', 'Opportunity', 'WorkOrder', 'TimeEntry', 'CrmActivity', 'WorkOrderReschedule'] as $name) {
            $class = 'App\\Models\\'.$name;
            $model = new $class;
            foreach ($model->preciseInstantFields() as $field) {
                $column = DB::table('information_schema.columns')->where('table_schema', 'public')->where('table_name', $model->getTable())->where('column_name', $field.'_utc')->first();
                $this->assertSame('timestamp with time zone', $column->data_type);
                $this->assertSame('YES', $column->is_nullable);
                $this->assertSame(6, (int) $column->datetime_precision);
                $count++;
            }
        }
        $this->assertSame(35, $count);
        $this->assertSame(9, DB::table('pg_indexes')->where('schemaname', 'public')->where('indexname', 'like', '%_instant_idx')->count());
        DB::statement('SET LOCAL enable_seqscan = off');
        $query = WorkOrder::where('scheduled_start', '>', '2026-10-25T00:00:00Z');
        $plan = DB::select('EXPLAIN '.$query->toSql(), $query->getBindings());
        $this->assertStringContainsString('work_orders_scheduled_start_instant_idx', json_encode($plan));
    }

    public function test_postgres_rejects_old_or_direct_writers_that_would_leave_a_stale_companion(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('PostgreSQL trigger test.');
        }
        $delivery = $this->delivery('guard', '2026-10-25T02:30:00+01:00');
        foreach ([['expires_at' => '2026-10-25 04:00:00'], ['expires_at_utc' => null], ['expires_at' => null], ['expires_at_utc' => '2026-10-25T03:00:00Z']] as $changes) {
            try {
                DB::transaction(fn () => DB::table('document_deliveries')->where('id', $delivery->id)->update($changes));
                $this->fail('Unsafe write was accepted.');
            } catch (QueryException $e) {
                $this->assertSame('23514', $e->errorInfo[0]);
            }
            $this->assertSame('2026-10-25T01:30:00.000000Z', $delivery->fresh()->expires_at->toISOString());
        }
        DB::table('document_deliveries')->where('id', $delivery->id)->update(BusinessTime::storage('expires_at', '2026-10-25T04:00:00+01:00'));
        $this->assertSame('2026-10-25T03:00:00.000000Z', $delivery->fresh()->expires_at->toISOString());
    }
}

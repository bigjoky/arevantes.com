<?php

namespace Tests\Feature;

use App\Models\DocumentDelivery;
use App\Models\FieldServiceProfile;
use App\Models\Installation;
use App\Models\Opportunity;
use App\Models\Person;
use App\Models\PlanningScenario;
use App\Models\PortfolioProject;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\Work\TechnicianSchedule;
use App\Support\Time\BusinessTime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TemporalInstantsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.timezone' => 'Europe/Madrid']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public static function folds(): array
    {
        return [['2026-10-25T00:30:00Z'], ['2026-10-25T01:30:00Z']];
    }

    #[DataProvider('folds')]
    public function test_expiry_boundary_sla_publication_and_scenario_expiry_in_both_occurrences(string $now): void
    {
        config(['app.timezone' => 'Europe/Madrid']);
        Carbon::setTestNow(Carbon::parse($now)->utc());
        $this->assertSame(Carbon::parse($now)->toISOString(), BusinessTime::now()->toISOString());
        Notification::fake();
        $delivery = DocumentDelivery::create(['recipient_email' => 'synthetic@example.test', 'token_hash' => hash('sha256', 'dst-expiry-test'), 'status' => 'sent', 'expires_at' => BusinessTime::now()]);
        $this->assertTrue($delivery->fresh()->expires_at->equalTo(BusinessTime::now()));
        $this->get(route('documents.shared.show', 'dst-expiry-test'))->assertGone();
        $delivery->update(['expires_at' => BusinessTime::now()->addMinute()]);
        $this->assertTrue($delivery->fresh()->expires_at->gt(BusinessTime::now()));

        $owner = User::factory()->create(['role' => 'commercial']);
        $customer = Person::create(['category' => 'customer', 'first_name' => 'Synthetic temporal test']);
        $opportunity = Opportunity::create(['person_id' => $customer->id, 'owner_user_id' => $owner->id, 'code' => 'OPP-DST', 'title' => 'Synthetic test', 'stage' => 'new', 'first_response_due_at' => BusinessTime::now()->subMinute()]);
        $this->artisan('crm:check-response-sla')->expectsOutput('1 lead response SLA alerts sent.')->assertSuccessful();
        $this->artisan('crm:check-response-sla')->expectsOutput('0 lead response SLA alerts sent.')->assertSuccessful();
        $this->assertTrue($opportunity->fresh()->created_at->equalTo(BusinessTime::now()));

        $project = PortfolioProject::create(['title' => 'Synthetic publication', 'slug' => 'synthetic-dst', 'summary' => 'Synthetic fixture', 'service_slug' => 'solar', 'published_at' => BusinessTime::now()]);
        $this->assertTrue($project->fresh()->published_at->equalTo(BusinessTime::now()));
        $this->assertSame(1, PortfolioProject::published()->count());
        $project->update(['published_at' => BusinessTime::now()->addHour()]);
        $this->assertSame(0, PortfolioProject::published()->count());
        $scenario = PlanningScenario::create(['name' => 'Synthetic scenario', 'created_by' => $owner->id, 'version' => 1, 'status' => 'draft', 'horizon_starts_on' => '2026-10-25', 'horizon_ends_on' => '2026-10-26', 'assumptions' => [], 'opportunity_snapshot' => [], 'result_snapshot' => [], 'fingerprint' => str_repeat('a', 64), 'reference_valid_until' => BusinessTime::now()->subMinute()]);
        $this->assertTrue($scenario->fresh()->reference_valid_until->lt(BusinessTime::now()));
        $this->assertSame('2026-10-25', $scenario->fresh()->horizon_starts_on->toDateString());
    }

    public function test_round_trip_projection_bulk_updates_and_json_preserve_each_distinct_instant(): void
    {
        $first = DocumentDelivery::create(['recipient_email' => 'synthetic@example.test', 'token_hash' => str_repeat('a', 64), 'status' => 'sent', 'expires_at' => '2026-10-25T02:30:00.123456+02:00']);
        $second = DocumentDelivery::create(['recipient_email' => 'synthetic@example.test', 'token_hash' => str_repeat('b', 64), 'status' => 'sent', 'expires_at' => '2026-10-25T02:30:00.123456+01:00']);
        $this->assertSame($first->getRawOriginal('expires_at'), $second->getRawOriginal('expires_at'));
        $this->assertSame(60.0, $first->fresh()->expires_at->diffInMinutes($second->fresh()->expires_at));
        $projected = DocumentDelivery::select(['id', 'expires_at'])->findOrFail($second->id);
        $this->assertSame('2026-10-25T01:30:00.000000Z', $projected->toArray()['expires_at']);
        $this->assertArrayNotHasKey('expires_at_utc', $projected->toArray());
        DocumentDelivery::whereKey($second->id)->update(['expires_at' => '2026-10-25T02:45:00+02:00']);
        $this->assertSame('2026-10-25T00:45:00.000000Z', $second->fresh()->expires_at->toISOString());
        DocumentDelivery::whereKey($second->id)->update(['responded_at' => null]);
        $this->assertNull($second->fresh()->getRawOriginal('responded_at_utc'));
    }

    public static function invalidWalls(): array
    {
        return [['2026-10-25T02:30'], ['2026-03-29T02:30'], ['2026-02-30T12:00'], ['2026-02-30T12:00:00Z'], ['2026-10-25T02:30:00+25:00']];
    }

    #[DataProvider('invalidWalls')]
    public function test_ambiguous_nonexistent_and_invalid_new_wall_times_are_rejected(string $value): void
    {
        $this->expectException(ValidationException::class);
        BusinessTime::parse($value, 'scheduled_start');
    }

    public function test_spring_and_autumn_durations_use_elapsed_minutes_and_distinct_folds_do_not_conflict(): void
    {
        $this->assertSame(60.0, BusinessTime::parse('2026-03-29T01:30:00+01:00')->diffInMinutes(BusinessTime::parse('2026-03-29T03:30:00+02:00')));
        $user = User::factory()->create(['role' => 'technician']);
        FieldServiceProfile::create(['weekly_schedule' => array_fill(1, 7, [['start' => '00:00', 'end' => '24:00']]), 'user_id' => $user->id, 'skills' => ['solar'], 'daily_capacity_minutes' => 480]);
        $installation = Installation::create(['service_type' => 'solar', 'status' => 'approved', 'site_address_line_1' => 'Synthetic local test']);
        $order = WorkOrder::create(['installation_id' => $installation->id, 'assigned_user_id' => $user->id, 'number' => 'WO-DST', 'title' => 'Synthetic test', 'kind' => 'survey', 'scheduled_start' => '2026-10-25T02:10:00+02:00', 'scheduled_end' => '2026-10-25T02:40:00+02:00']);
        app(TechnicianSchedule::class)->assertAvailable($user->id, '2026-10-25T02:10:00+01:00', '2026-10-25T02:40:00+01:00');
        $history = $order->reschedules()->create(['old_scheduled_start' => $order->scheduled_start, 'old_scheduled_end' => $order->scheduled_end, 'new_scheduled_start' => '2026-10-25T02:10:00+01:00', 'new_scheduled_end' => '2026-10-25T02:40:00+01:00', 'reason' => 'Synthetic offset change']);
        $this->assertSame(60.0, $history->fresh()->old_scheduled_start->diffInMinutes($history->fresh()->new_scheduled_start));
        $entry = TimeEntry::create(['work_order_id' => $order->id, 'user_id' => $user->id, 'started_at' => '2026-10-25T02:15:00+02:00', 'ended_at' => '2026-10-25T02:45:00+01:00']);
        $this->assertSame(90.0, $entry->fresh()->started_at->diffInMinutes($entry->fresh()->ended_at));
        try {
            app(TechnicianSchedule::class)->assertAvailable($user->id, '2026-10-25T02:20:00+02:00', '2026-10-25T02:30:00+02:00');
            $this->fail('A real overlap must still be rejected.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('scheduled_start', $e->errors());
        }
    }

    public function test_legacy_ambiguity_is_reported_without_rewriting_and_conservative_bounds_are_explicit(): void
    {
        $id = DB::table('document_deliveries')->insertGetId(['recipient_email' => 'synthetic@example.test', 'token_hash' => str_repeat('c', 64), 'status' => 'sent', 'expires_at' => '2026-10-25 02:30:00']);
        $delivery = DocumentDelivery::findOrFail($id);
        $before = DB::table('document_deliveries')->where('id', $delivery->id)->first();
        $this->assertSame('2026-10-25T00:30:00.000000Z', $delivery->fresh()->expires_at->toISOString());
        $this->artisan('time:audit-instants')->assertSuccessful();
        $this->assertEquals($before, DB::table('document_deliveries')->where('id', $delivery->id)->first());
        $this->assertSame(2, count(BusinessTime::candidates('2026-10-25 02:30:00')));
        $this->assertSame('2026-10-25T01:30:00.000000Z', BusinessTime::legacy('2026-10-25 02:30:00', 'latest')->toISOString());
    }

    public function test_additive_migration_keeps_ambiguous_legacy_values_and_does_not_backfill_an_offset(): void
    {
        $migration = require database_path('migrations/2026_10_25_030000_preserve_critical_instants_with_utc_companions.php');
        $migration->down();
        $id = DB::table('document_deliveries')->insertGetId(['recipient_email' => 'synthetic@example.test', 'token_hash' => str_repeat('e', 64), 'status' => 'sent', 'expires_at' => '2026-10-25 02:30:00']);
        $columns = ['id', 'recipient_email', 'token_hash', 'status', 'expires_at'];
        $before = DB::table('document_deliveries')->where('id', $id)->first($columns);
        $migration->up();
        $this->assertEquals($before, DB::table('document_deliveries')->where('id', $id)->first($columns));
        $this->assertNull(DB::table('document_deliveries')->where('id', $id)->value('expires_at_utc'));
    }

    public function test_http_schedule_rejects_a_nonexistent_wall_time_before_creating_an_order(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $installation = Installation::create(['service_type' => 'solar', 'status' => 'approved', 'site_address_line_1' => 'Synthetic local test']);
        $this->actingAs($user)->post(route('work-orders.store'), ['installation_id' => $installation->id, 'title' => 'Synthetic time test', 'kind' => 'survey', 'scheduled_start' => '2026-03-29T02:30', 'scheduled_end' => '2026-03-29T04:00'])->assertSessionHasErrors('scheduled_start');
        $this->assertSame(0, WorkOrder::count());
    }

    public function test_postgres_companion_type_and_session_timezone_do_not_change_the_instant(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('PostgreSQL-specific type/session verification.');
        }
        $type = DB::table('information_schema.columns')->where('table_name', 'document_deliveries')->where('column_name', 'expires_at_utc')->value('data_type');
        $this->assertSame('timestamp with time zone', $type);
        DB::statement("SET LOCAL TIME ZONE 'Pacific/Honolulu'");
        $delivery = DocumentDelivery::create(['recipient_email' => 'synthetic@example.test', 'token_hash' => str_repeat('d', 64), 'status' => 'sent', 'expires_at' => '2026-10-25T02:30:00+01:00']);
        $this->assertSame('2026-10-25T01:30:00.000000Z', $delivery->fresh()->expires_at->toISOString());
    }
}

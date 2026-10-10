<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\CommercialFieldSession;
use App\Models\CommercialRoutePlan;
use App\Models\CommercialVisit;
use App\Models\CrmActivity;
use App\Models\Permission;
use App\Models\Person;
use App\Models\User;
use App\Services\Commercial\FieldActivity;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CommercialFieldTest extends TestCase
{
    use RefreshDatabase;

    private function commercial(): User
    {
        return User::factory()->create(['role' => 'commercial']);
    }

    private function payload(array $changes = []): array
    {
        return [...[
            'client_uuid' => (string) Str::uuid(), 'prospect_type' => 'business',
            'outcome' => 'no_answer', 'visited_at' => now()->subMinute()->toISOString(),
        ], ...$changes];
    }

    public function test_cold_visit_has_no_fake_customer_or_opportunity_and_retries_do_not_duplicate(): void
    {
        $user = $this->commercial();
        $this->actingAs($user);
        $data = $this->payload();
        $people = Person::count();
        $this->post('/commercial-field/visits', $data)->assertRedirect();
        $this->post('/commercial-field/visits', $data)->assertRedirect();
        $this->assertDatabaseCount('commercial_visits', 1);
        $this->assertDatabaseCount('opportunities', 0);
        $this->assertEquals($people, Person::count());
        $this->assertDatabaseHas('commercial_visits', ['person_id' => null, 'opportunity_id' => null]);
    }

    public function test_validation_and_route_ownership_and_date(): void
    {
        $user = $this->commercial();
        $other = $this->commercial();
        $route = CommercialRoutePlan::create(['user_id' => $other->id, 'route_date' => today(), 'status' => 'planned']);
        $this->actingAs($user)->postJson('/commercial-field/visits', $this->payload(['commercial_route_plan_id' => $route->id]))->assertUnprocessable();
        $route->update(['user_id' => $user->id, 'route_date' => today()->addDay()]);
        $this->postJson('/commercial-field/visits', $this->payload(['commercial_route_plan_id' => $route->id]))->assertUnprocessable();
        $route->update(['route_date' => today()]);
        $this->post('/commercial-field/visits', $this->payload(['commercial_route_plan_id' => $route->id]))->assertRedirect();
        $this->postJson('/commercial-field/visits', $this->payload(['outcome' => 'made_up', 'prospect_type' => 'made_up', 'latitude' => 91]))->assertUnprocessable()->assertJsonValidationErrors(['outcome', 'prospect_type', 'latitude', 'longitude']);
    }

    public function test_session_lifecycle_ownership_and_corrected_end_validation(): void
    {
        $user = $this->commercial();
        $this->actingAs($user)->post('/commercial-field/sessions')->assertRedirect();
        $session = CommercialFieldSession::firstOrFail();
        $this->postJson('/commercial-field/sessions')->assertUnprocessable();
        $this->actingAs($this->commercial())->patchJson("/commercial-field/sessions/{$session->id}/stop")->assertNotFound();
        $this->actingAs($user)->patchJson("/commercial-field/sessions/{$session->id}/stop", ['ended_at' => $session->started_at->subMinute()->toISOString()])->assertUnprocessable();
        $this->travel(2)->minutes();
        $this->post('/commercial-field/visits', $this->payload())->assertRedirect();
        $this->patchJson("/commercial-field/sessions/{$session->id}/stop", ['ended_at' => $session->started_at->addSeconds(30)->toISOString()])->assertUnprocessable();
        $this->patch("/commercial-field/sessions/{$session->id}/stop")->assertRedirect();
        $this->patch("/commercial-field/sessions/{$session->id}/stop")->assertRedirect();
        $this->assertNotNull($session->fresh()->ended_at);
        $this->post('/commercial-field/sessions')->assertRedirect();
        $this->assertDatabaseCount('commercial_field_sessions', 2);
    }

    public function test_kpis_use_closed_actual_time_clip_period_and_exclude_unmeasured_visits(): void
    {
        $user = $this->commercial();
        $from = CarbonImmutable::parse('2026-10-10T00:00:00');
        $to = $from->addDay();
        $session = CommercialFieldSession::create(['user_id' => $user->id, 'started_at' => $from->subHour(), 'ended_at' => $from->addHour()]);
        foreach ([null, $session->id] as $id) {
            CommercialVisit::create([...$this->payload(['visited_at' => $from->addMinutes(30), 'outcome' => 'interested']), 'user_id' => $user->id, 'commercial_field_session_id' => $id]);
        }
        CommercialFieldSession::create(['user_id' => $user->id, 'started_at' => $from->addHours(5)]);
        $report = app(FieldActivity::class)->report($from, $to, $user->id)[0];
        $this->assertEquals(1, $report['hours']);
        $this->assertEquals(1, $report['visits_per_hour']);
        $this->assertEquals(1, $report['unmeasured_visits']);
        $this->assertEquals(2, $report['visits']);
    }

    public function test_commercial_can_only_see_own_data_and_supervisor_can_see_team(): void
    {
        $user = $this->commercial();
        $other = $this->commercial();
        CommercialVisit::create([...$this->payload(), 'user_id' => $other->id]);
        Sanctum::actingAs($user, ['mobile']);
        $this->getJson('/api/mobile/v1/commercial/field?user_id='.$other->id)->assertOk()->assertJsonCount(0, 'data.visits.data');
        $user->permissionOverrides()->attach(Permission::where('key', 'supervise-commercial-field')->value('id'), ['allowed' => true]);
        $this->getJson('/api/mobile/v1/commercial/field')->assertOk()->assertJsonCount(1, 'data.visits.data');
        $this->actingAs(User::factory()->create(['role' => 'technician']))->get('/commercial-field')->assertForbidden();
    }

    public function test_mobile_requires_ability_permission_idempotency_and_replays_response(): void
    {
        $user = $this->commercial();
        Sanctum::actingAs($user, ['unrelated']);
        $this->getJson('/api/mobile/v1/commercial/field')->assertForbidden();
        Sanctum::actingAs($user, ['mobile']);
        $data = $this->payload();
        $this->postJson('/api/mobile/v1/commercial/field/visits', $data)->assertUnprocessable();
        $key = (string) Str::uuid();
        $this->withHeader('Idempotency-Key', $key)->postJson('/api/mobile/v1/commercial/field/visits', $data)->assertCreated();
        $this->withHeader('Idempotency-Key', $key)->postJson('/api/mobile/v1/commercial/field/visits', $data)->assertCreated()->assertHeader('Idempotency-Replayed', 'true');
        $this->assertDatabaseCount('commercial_visits', 1);
        $user->permissionOverrides()->attach(Permission::where('key', 'record-commercial-field')->value('id'), ['allowed' => false]);
        $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/mobile/v1/commercial/field/visits', $data)->assertForbidden();
    }

    public function test_conversion_reuses_visit_creates_real_crm_and_is_idempotent(): void
    {
        $user = $this->commercial();
        $visit = CommercialVisit::create([...$this->payload(['outcome' => 'interested']), 'user_id' => $user->id]);
        $data = ['first_name' => 'Contacto de prueba', 'phone' => '612345678', 'preferred_contact_channel' => 'phone', 'consent_status' => 'denied', 'service_interest' => 'solar_photovoltaic', 'next_action_at' => now()->addDay()->toISOString()];
        $this->actingAs($this->commercial())->postJson("/commercial-field/visits/{$visit->id}/convert", $data)->assertNotFound();
        $this->actingAs($user)->post("/commercial-field/visits/{$visit->id}/convert", $data)->assertRedirect();
        $this->post("/commercial-field/visits/{$visit->id}/convert", $data)->assertRedirect();
        $this->assertDatabaseCount('commercial_visits', 1);
        $this->assertDatabaseCount('opportunities', 1);
        $visit->refresh();
        $this->assertNotNull($visit->opportunity_id);
        $this->assertTrue($visit->customer->do_not_contact);
        $this->assertDatabaseHas('opportunities', ['id' => $visit->opportunity_id, 'owner_user_id' => $user->id, 'source' => 'outbound']);
        $this->assertDatabaseHas('crm_activities', ['opportunity_id' => $visit->opportunity_id, 'type' => 'task', 'status' => 'pending']);
        $completed = CrmActivity::where('opportunity_id', $visit->opportunity_id)->where('status', 'completed')->firstOrFail();
        $next = CrmActivity::where('opportunity_id', $visit->opportunity_id)->where('status', 'pending')->firstOrFail();
        $this->assertTrue($completed->completed_at->equalTo($visit->visited_at));
        $this->assertTrue($next->due_at->equalTo(CarbonImmutable::parse($data['next_action_at'])->startOfSecond()));
    }

    public function test_non_actionable_conversion_and_invalid_contact_do_not_create_crm_records(): void
    {
        $user = $this->commercial();
        $visit = CommercialVisit::create([...$this->payload(), 'user_id' => $user->id]);
        $this->actingAs($user)->postJson("/commercial-field/visits/{$visit->id}/convert", [])->assertUnprocessable();
        $visit->update(['outcome' => 'interested']);
        $this->postJson("/commercial-field/visits/{$visit->id}/convert", [])->assertUnprocessable()->assertJsonValidationErrors(['first_name', 'phone', 'service_interest']);
        $this->assertDatabaseCount('opportunities', 0);
        $this->assertDatabaseCount('people', 0);
    }

    public function test_supervisor_assigns_real_sector_without_replacing_existing_route(): void
    {
        $user = $this->commercial();
        $data = ['user_id' => $user->id, 'route_date' => today()->toDateString(), 'sector_label' => 'Centro', 'instructions' => 'Visitas por la mañana'];
        $this->actingAs($user)->postJson('/commercial-field/routes', $data)->assertForbidden();
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->post('/commercial-field/routes', $data)->assertRedirect();
        $route = CommercialRoutePlan::firstOrFail();
        $route->update(['status' => 'active']);
        $stop = $route->stops()->create(['position' => 1, 'status' => 'planned', 'title' => 'Parada CRM existente']);
        $this->post('/commercial-field/routes', [...$data, 'sector_label' => 'Norte'])->assertRedirect();
        $this->assertDatabaseCount('commercial_route_plans', 1);
        $this->assertDatabaseHas('commercial_route_stops', ['id' => $stop->id, 'title' => 'Parada CRM existente']);
        $this->assertDatabaseCount('people', 0);
        $this->assertDatabaseHas('commercial_route_plans', ['id' => $route->id, 'status' => 'active', 'sector_label' => 'Norte']);
    }

    public function test_retention_dry_run_then_prunes_field_data_without_deleting_crm(): void
    {
        $user = $this->commercial();
        $old = CommercialVisit::create([...$this->payload(['visited_at' => now()->subDays(40), 'latitude' => 40, 'longitude' => -3, 'address' => 'Referencia']), 'user_id' => $user->id]);
        $ancient = CommercialVisit::create([...$this->payload(['visited_at' => now()->subDays(400)]), 'user_id' => $user->id]);
        $this->artisan('data:prune-retention', ['--dry-run' => true])->assertSuccessful();
        $this->assertNotNull($old->fresh()->latitude);
        $this->artisan('data:prune-retention')->assertSuccessful();
        $this->assertNull($old->fresh()->latitude);
        $this->assertDatabaseMissing('commercial_visits', ['id' => $ancient->id]);
        $this->assertDatabaseHas('commercial_visits', ['id' => $old->id, 'address' => 'Referencia']);
    }

    public function test_visit_audit_redacts_location_and_notes(): void
    {
        $this->actingAs($this->commercial())->post('/commercial-field/visits', $this->payload(['latitude' => 40, 'longitude' => -3, 'accuracy_meters' => 5, 'notes' => 'Private notes']))->assertRedirect();
        $audit = AuditLog::where('auditable_type', CommercialVisit::class)->firstOrFail();
        $this->assertEquals('[REDACTED]', $audit->new_values['latitude']);
        $this->assertEquals('[REDACTED]', $audit->new_values['notes']);
    }

    public function test_actual_session_hours_survive_the_madrid_autumn_clock_change(): void
    {
        $user = $this->commercial();
        $start = CarbonImmutable::parse('2026-10-25T01:30:00+02:00');
        $end = CarbonImmutable::parse('2026-10-25T02:30:00+01:00');
        $session = CommercialFieldSession::create(['user_id' => $user->id, 'started_at' => $start, 'ended_at' => $end]);
        $this->assertTrue($session->fresh()->started_at->equalTo($start));
        $this->assertTrue($session->fresh()->ended_at->equalTo($end));
        $from = CarbonImmutable::parse('2026-10-25', 'Europe/Madrid')->startOfDay();
        $report = app(FieldActivity::class)->report($from, $from->endOfDay(), $user->id)[0];
        $this->assertEquals(2, $report['hours']);
    }
}

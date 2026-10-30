<?php

namespace Tests\Feature\Api;

use App\Models\CommercialVisit;
use App\Models\CrmActivity;
use App\Models\FieldServiceProfile;
use App\Models\Installation;
use App\Models\InstallationAsset;
use App\Models\MaintenancePlan;
use App\Models\MobileOperation;
use App\Models\Opportunity;
use App\Models\PermissionProfile;
use App\Models\Person;
use App\Models\Product;
use App\Models\ServiceTicket;
use App\Models\StockReservation;
use App\Models\TaxRate;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WorkOrder;
use App\Services\Geocoding\NominatimGeocoder;
use App\Services\Inventory\StockLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class MobileV1Test extends TestCase
{
    use RefreshDatabase;

    private function mobileAs(User $user): self
    {
        Sanctum::actingAs($user, ['mobile']);

        return $this;
    }

    public function test_device_can_login_read_profile_and_revoke_its_token(): void
    {
        $user = User::factory()->create(['role' => 'technician', 'password' => 'secret-password']);
        FieldServiceProfile::create(['user_id' => $user->id, 'weekly_schedule' => array_fill(1, 7, [['start' => '00:00', 'end' => '24:00']]), 'daily_capacity_minutes' => 1440, 'is_available' => true]);

        $login = $this->postJson('/api/mobile/v1/auth/login', [
            'email' => $user->email,
            'password' => 'secret-password',
            'device_name' => 'iPhone test',
        ])->assertOk()
            ->assertJsonPath('data.user.role', 'technician')
            ->assertJsonPath('data.expires_at', fn ($value) => is_string($value));

        $token = $login->json('data.token');
        $this->assertNotNull($user->tokens()->firstOrFail()->expires_at);
        $this->withToken($token)->getJson('/api/mobile/v1/me')->assertOk()->assertJsonPath('data.email', $user->email);
        $this->withToken($token)->deleteJson('/api/mobile/v1/auth/token')->assertNoContent();
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/mobile/v1/me')->assertUnauthorized();
    }

    public function test_login_rejects_invalid_credentials(): void
    {
        $user = User::factory()->create(['password' => 'correct-password']);

        $this->postJson('/api/mobile/v1/auth/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
            'device_name' => 'iPhone test',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_mobile_routes_require_the_mobile_token_ability(): void
    {
        $user = User::factory()->create(['role' => 'technician']);
        FieldServiceProfile::create(['user_id' => $user->id, 'weekly_schedule' => array_fill(1, 7, [['start' => '00:00', 'end' => '24:00']]), 'daily_capacity_minutes' => 1440, 'is_available' => true]);

        Sanctum::actingAs($user, ['unrelated']);

        $this->getJson('/api/mobile/v1/me')->assertForbidden();
    }

    public function test_mobile_routes_recheck_the_current_permission_profile_on_every_request(): void
    {
        $finance = PermissionProfile::where('slug', 'finance')->firstOrFail();
        $user = User::factory()->create(['role' => 'commercial', 'permission_profile_id' => $finance->id]);

        Sanctum::actingAs($user, ['mobile']);

        $this->getJson('/api/mobile/v1/work-orders')->assertForbidden();
    }

    public function test_finance_profile_cannot_login_to_mobile_even_with_legacy_commercial_role(): void
    {
        $finance = PermissionProfile::where('slug', 'finance')->firstOrFail();
        $user = User::factory()->create([
            'role' => 'commercial',
            'permission_profile_id' => $finance->id,
            'password' => 'secret-password',
        ]);

        $this->postJson('/api/mobile/v1/auth/login', [
            'email' => $user->email,
            'password' => 'secret-password',
            'device_name' => 'Finance phone',
        ])->assertForbidden();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_mobile_api_responses_include_a_correlation_identifier(): void
    {
        $technician = User::factory()->create(['role' => 'technician']);
        FieldServiceProfile::create(['user_id' => $technician->id, 'weekly_schedule' => array_fill(1, 7, [['start' => '00:00', 'end' => '24:00']]), 'daily_capacity_minutes' => 1440, 'is_available' => true]);

        $this->mobileAs($technician)
            ->getJson('/api/mobile/v1/me')
            ->assertOk()
            ->assertHeader('X-Correlation-ID');
    }

    public function test_mobile_api_accepts_capacitor_cors_preflight(): void
    {
        $this->call('OPTIONS', '/api/mobile/v1/work-orders', [], [], [], [
            'HTTP_ORIGIN' => 'capacitor://localhost',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
            'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'authorization,idempotency-key',
        ])->assertNoContent()
            ->assertHeader('Access-Control-Allow-Origin', 'capacitor://localhost')
            ->assertHeader('Access-Control-Allow-Methods');
    }

    public function test_commercial_can_resolve_a_gps_position_to_an_address(): void
    {
        $commercial = User::factory()->create(['role' => 'commercial']);
        $geocoder = $this->mock(NominatimGeocoder::class);
        $geocoder->shouldReceive('reverse')
            ->once()
            ->with(37.3891, -5.9845)
            ->andReturn([
                'display_name' => 'Calle Sierpes 10, Sevilla, España',
                'address' => [
                    'road' => 'Calle Sierpes',
                    'house_number' => '10',
                    'locality' => null,
                    'municipality' => 'Sevilla',
                    'postal_code' => '41004',
                ],
            ]);

        $this->mobileAs($commercial)
            ->getJson('/api/mobile/v1/commercial/location/reverse?latitude=37.3891&longitude=-5.9845')
            ->assertOk()
            ->assertJsonPath('data.address', 'Calle Sierpes 10')
            ->assertJsonPath('data.city', 'Sevilla')
            ->assertJsonPath('data.postal_code', '41004');
    }

    public function test_reverse_geocoding_rejects_invalid_coordinates(): void
    {
        $commercial = User::factory()->create(['role' => 'commercial']);

        $this->mobileAs($commercial)
            ->getJson('/api/mobile/v1/commercial/location/reverse?latitude=100&longitude=-5.9845')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('latitude');
    }

    public function test_commercial_can_capture_a_visit_as_a_lead_offline_idempotently(): void
    {
        $commercial = User::factory()->create(['role' => 'commercial']);
        $key = (string) Str::uuid();
        $clientUuid = (string) Str::uuid();
        $payload = [
            'client_uuid' => $clientUuid,
            'first_name' => 'María',
            'last_name' => 'Solar',
            'phone' => '612 345 678',
            'email' => 'maria@example.test',
            'preferred_contact_channel' => 'whatsapp',
            'consent_status' => 'granted',
            'consent_notes' => 'Aceptado durante la visita.',
            'service_interest' => 'solar_photovoltaic',
            'source' => 'outbound',
            'estimated_value' => 8500,
            'address' => 'Calle Sol 10',
            'city' => 'Sevilla',
            'postal_code' => '41001',
            'notes' => 'Quiere reducir su factura eléctrica.',
            'visited_at' => now()->subMinute()->toISOString(),
            'latitude' => 37.3891,
            'longitude' => -5.9845,
            'accuracy_meters' => 8.5,
            'next_action_at' => now()->addDay()->toISOString(),
        ];

        $this->mobileAs($commercial)->withHeader('Idempotency-Key', $key)
            ->postJson('/api/mobile/v1/commercial/leads', $payload)
            ->assertCreated()
            ->assertJsonPath('data.client_uuid', $clientUuid)
            ->assertJsonPath('data.opportunity.owner_user_id', $commercial->id)
            ->assertJsonPath('data.opportunity.stage', 'new');
        $this->withHeader('Idempotency-Key', $key)
            ->postJson('/api/mobile/v1/commercial/leads', $payload)
            ->assertCreated()
            ->assertHeader('Idempotency-Replayed', 'true');

        $visit = CommercialVisit::firstOrFail();
        $customer = Person::findOrFail($visit->person_id);
        $this->assertDatabaseCount('commercial_visits', 1);
        $this->assertDatabaseCount('opportunities', 1);
        $this->assertDatabaseHas('customer_consents', ['person_id' => $customer->id, 'status' => 'granted', 'source' => 'mobile_commercial_visit']);
        $this->assertDatabaseHas('crm_activities', ['opportunity_id' => $visit->opportunity_id, 'type' => 'visit', 'status' => 'completed']);
        $this->assertDatabaseHas('crm_activities', ['opportunity_id' => $visit->opportunity_id, 'type' => 'call', 'status' => 'pending']);
        $this->assertSame(['solar_photovoltaic'], $customer->service_interests);
    }

    public function test_commercial_dashboard_contains_only_its_open_portfolio_and_agenda(): void
    {
        $commercial = User::factory()->create(['role' => 'commercial']);
        $other = User::factory()->create(['role' => 'commercial']);
        $customer = Person::create(['category' => 'customer', 'first_name' => 'Lead propio', 'phone' => '612345678', 'country' => 'Spain']);
        $own = Opportunity::create([
            'person_id' => $customer->id,
            'owner_user_id' => $commercial->id,
            'created_by' => $commercial->id,
            'code' => 'OPP-MOBILE-1',
            'title' => 'Autoconsumo',
            'stage' => 'qualified',
            'probability' => 35,
            'commercial_score' => 70,
            'estimated_value' => 12000,
            'source' => 'outbound',
        ]);
        $action = CrmActivity::create([
            'opportunity_id' => $own->id,
            'person_id' => $customer->id,
            'assigned_user_id' => $commercial->id,
            'created_by' => $commercial->id,
            'type' => 'call',
            'title' => 'Llamar al lead',
            'priority' => 'high',
            'status' => 'pending',
            'due_at' => now()->subHour(),
        ]);
        CommercialVisit::create([
            'client_uuid' => (string) Str::uuid(),
            'user_id' => $commercial->id,
            'person_id' => $customer->id,
            'opportunity_id' => $own->id,
            'visited_at' => now()->subDay(),
            'latitude' => 37.3891,
            'longitude' => -5.9845,
        ]);
        Opportunity::create([
            'person_id' => $customer->id,
            'owner_user_id' => $other->id,
            'created_by' => $other->id,
            'code' => 'OPP-OTHER',
            'title' => 'No visible',
            'stage' => 'new',
            'probability' => 10,
            'estimated_value' => 99999,
        ]);
        Opportunity::create([
            'person_id' => $customer->id,
            'owner_user_id' => $commercial->id,
            'created_by' => $commercial->id,
            'code' => 'OPP-CLOSED',
            'title' => 'Cerrada',
            'stage' => 'won',
            'probability' => 100,
            'estimated_value' => 5000,
        ]);

        $this->mobileAs($commercial)->getJson('/api/mobile/v1/commercial/dashboard')
            ->assertOk()
            ->assertJsonPath('data.summary.open', 1)
            ->assertJsonPath('data.summary.overdue', 1)
            ->assertJsonPath('data.summary.pipeline_value', 12000)
            ->assertJsonCount(1, 'data.leads')
            ->assertJsonPath('data.leads.0.id', $own->id)
            ->assertJsonPath('data.leads.0.customer.name', 'Lead propio')
            ->assertJsonPath('data.leads.0.last_visit.latitude', 37.3891)
            ->assertJsonCount(1, 'data.agenda')
            ->assertJsonPath('data.agenda.0.id', $action->id)
            ->assertJsonPath('data.conversions.summary.closed', 1)
            ->assertJsonPath('data.conversions.summary.won', 1)
            ->assertJsonPath('data.conversions.summary.win_rate', 100)
            ->assertJsonPath('data.conversions.summary.attribution_rate', 0)
            ->assertJsonCount(0, 'data.conversions.by_commercial');

        $technician = User::factory()->create(['role' => 'technician']);
        FieldServiceProfile::create(['user_id' => $technician->id, 'weekly_schedule' => array_fill(1, 7, [['start' => '00:00', 'end' => '24:00']]), 'daily_capacity_minutes' => 1440, 'is_available' => true]);
        $this->mobileAs($technician)->getJson('/api/mobile/v1/commercial/dashboard')->assertForbidden();
    }

    public function test_mobile_commercial_capture_accepts_electrical_and_security_interests(): void
    {
        $commercial = User::factory()->create(['role' => 'commercial']);

        foreach (['electrical_installation', 'security_systems'] as $index => $interest) {
            $phone = '61234567'.$index;
            $this->mobileAs($commercial)
                ->withHeader('Idempotency-Key', (string) Str::uuid())
                ->postJson('/api/mobile/v1/commercial/leads', [
                    'client_uuid' => (string) Str::uuid(),
                    'first_name' => 'Nueva línea '.$index,
                    'phone' => $phone,
                    'preferred_contact_channel' => 'phone',
                    'consent_status' => 'not_recorded',
                    'service_interest' => $interest,
                    'source' => 'outbound',
                    'visited_at' => now()->subMinute()->toISOString(),
                    'next_action_at' => now()->addDay()->toISOString(),
                ])
                ->assertCreated();

            $this->assertSame(
                [$interest],
                Person::where('phone', $phone)->firstOrFail()->service_interests,
            );
        }
    }

    public function test_commercial_can_plan_and_execute_its_daily_route(): void
    {
        $commercial = User::factory()->create(['role' => 'commercial']);
        $other = User::factory()->create(['role' => 'commercial']);
        $nearCustomer = Person::create(['category' => 'customer', 'first_name' => 'Cliente cercano', 'phone' => '611111111', 'address_line_1' => 'Calle Cerca 1', 'city' => 'Sevilla', 'country' => 'Spain']);
        $farCustomer = Person::create(['category' => 'customer', 'first_name' => 'Cliente lejano', 'phone' => '622222222', 'address_line_1' => 'Calle Lejos 2', 'city' => 'Sevilla', 'country' => 'Spain']);
        $nearOpportunity = Opportunity::create(['person_id' => $nearCustomer->id, 'owner_user_id' => $commercial->id, 'created_by' => $commercial->id, 'code' => 'OPP-ROUTE-1', 'title' => 'Oportunidad cercana', 'stage' => 'qualified', 'probability' => 30, 'estimated_value' => 1000]);
        $farOpportunity = Opportunity::create(['person_id' => $farCustomer->id, 'owner_user_id' => $commercial->id, 'created_by' => $commercial->id, 'code' => 'OPP-ROUTE-2', 'title' => 'Oportunidad lejana', 'stage' => 'qualified', 'probability' => 30, 'estimated_value' => 1000]);
        $nearActivity = CrmActivity::create([
            'opportunity_id' => $nearOpportunity->id,
            'person_id' => $nearCustomer->id,
            'assigned_user_id' => $commercial->id,
            'created_by' => $commercial->id,
            'type' => 'visit',
            'title' => 'Visita cercana',
            'priority' => 'normal',
            'status' => 'pending',
            'starts_at' => today()->setHour(12),
        ]);
        $farActivity = CrmActivity::create([
            'opportunity_id' => $farOpportunity->id,
            'person_id' => $farCustomer->id,
            'assigned_user_id' => $commercial->id,
            'created_by' => $commercial->id,
            'type' => 'meeting',
            'title' => 'Reunión lejana',
            'priority' => 'normal',
            'status' => 'pending',
            'starts_at' => today()->setHour(9),
        ]);
        CrmActivity::create([
            'person_id' => $nearCustomer->id,
            'assigned_user_id' => $other->id,
            'created_by' => $other->id,
            'type' => 'visit',
            'title' => 'Visita ajena',
            'priority' => 'normal',
            'status' => 'pending',
            'starts_at' => today()->setHour(10),
        ]);
        CommercialVisit::create(['client_uuid' => (string) Str::uuid(), 'user_id' => $commercial->id, 'person_id' => $nearCustomer->id, 'opportunity_id' => $nearOpportunity->id, 'visited_at' => now()->subDay(), 'latitude' => 37.3892, 'longitude' => -5.9846]);
        CommercialVisit::create(['client_uuid' => (string) Str::uuid(), 'user_id' => $commercial->id, 'person_id' => $farCustomer->id, 'opportunity_id' => $farOpportunity->id, 'visited_at' => now()->subDay(), 'latitude' => 37.45, 'longitude' => -6.05]);

        $response = $this->mobileAs($commercial)->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/mobile/v1/commercial/routes', [
                'route_date' => today()->toDateString(),
                'origin_latitude' => 37.3891,
                'origin_longitude' => -5.9845,
            ])
            ->assertCreated()
            ->assertJsonPath('data.summary.total', 2)
            ->assertJsonPath('data.stops.0.activity_id', $nearActivity->id)
            ->assertJsonPath('data.stops.1.activity_id', $farActivity->id);
        $planId = $response->json('data.id');
        $nearStopId = $response->json('data.stops.0.id');
        $farStopId = $response->json('data.stops.1.id');

        $this->app['auth']->forgetGuards();
        $this->mobileAs($other)->getJson('/api/mobile/v1/commercial/routes/current')->assertOk()->assertJsonPath('data', null);
        $this->mobileAs($other)->withHeader('Idempotency-Key', (string) Str::uuid())
            ->patchJson("/api/mobile/v1/commercial/routes/{$planId}/stops/{$nearStopId}/complete", ['completed_at' => now()->toISOString()])
            ->assertNotFound();

        $arrival = now()->subMinute();
        $this->app['auth']->forgetGuards();
        $this->mobileAs($commercial)->withHeader('Idempotency-Key', (string) Str::uuid())
            ->patchJson("/api/mobile/v1/commercial/routes/{$planId}/stops/{$nearStopId}/arrive", [
                'arrived_at' => $arrival->toISOString(),
                'latitude' => 37.3892,
                'longitude' => -5.9846,
                'accuracy_meters' => 7,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.stops.0.status', 'arrived');
        $this->assertDatabaseHas('crm_activities', ['id' => $nearActivity->id, 'status' => 'in_progress']);

        $this->mobileAs($commercial)->withHeader('Idempotency-Key', (string) Str::uuid())
            ->patchJson("/api/mobile/v1/commercial/routes/{$planId}/stops/{$farStopId}/arrive", [
                'arrived_at' => $arrival->toISOString(),
                'latitude' => 37.45,
                'longitude' => -6.05,
            ])->assertOk();
        foreach ([$nearStopId, $farStopId] as $stopId) {
            $this->mobileAs($commercial)->withHeader('Idempotency-Key', (string) Str::uuid())
                ->patchJson("/api/mobile/v1/commercial/routes/{$planId}/stops/{$stopId}/complete", [
                    'completed_at' => now()->toISOString(),
                    'notes' => 'Visita realizada',
                ])->assertOk();
        }
        $this->assertDatabaseHas('commercial_route_plans', ['id' => $planId, 'status' => 'completed']);
        $this->assertDatabaseHas('crm_activities', ['id' => $nearActivity->id, 'status' => 'completed']);
        $this->assertDatabaseHas('crm_activities', ['id' => $farActivity->id, 'status' => 'completed']);

        $this->mobileAs($commercial)->getJson('/api/mobile/v1/commercial/dashboard')
            ->assertOk()
            ->assertJsonPath('data.route.id', $planId)
            ->assertJsonPath('data.route.summary.remaining', 0);
    }

    public function test_commercial_lead_capture_reuses_customer_and_rejects_technician(): void
    {
        $existing = Person::create([
            'category' => 'customer',
            'customer_type' => 'residential',
            'lifecycle_status' => 'lead',
            'first_name' => 'Cliente',
            'phone' => '612345678',
            'country' => 'Spain',
        ]);
        $payload = [
            'client_uuid' => (string) Str::uuid(),
            'first_name' => 'Cliente actualizado',
            'phone' => '+34 612 345 678',
            'preferred_contact_channel' => 'phone',
            'consent_status' => 'denied',
            'service_interest' => 'maintenance',
            'source' => 'referral',
            'visited_at' => now()->subMinute()->toISOString(),
            'next_action_at' => now()->addDay()->toISOString(),
        ];
        $commercial = User::factory()->create(['role' => 'commercial']);

        $this->mobileAs($commercial)->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/mobile/v1/commercial/leads', $payload)
            ->assertCreated();
        $this->assertDatabaseCount('people', 1);
        $this->assertSame($existing->id, CommercialVisit::firstOrFail()->person_id);
        $this->assertTrue($existing->refresh()->do_not_contact);
        $this->assertDatabaseHas('crm_activities', ['opportunity_id' => CommercialVisit::firstOrFail()->opportunity_id, 'type' => 'task', 'status' => 'pending']);
        $this->assertDatabaseMissing('crm_activities', ['opportunity_id' => CommercialVisit::firstOrFail()->opportunity_id, 'type' => 'call']);

        $technician = User::factory()->create(['role' => 'technician']);
        FieldServiceProfile::create(['user_id' => $technician->id, 'weekly_schedule' => array_fill(1, 7, [['start' => '00:00', 'end' => '24:00']]), 'daily_capacity_minutes' => 1440, 'is_available' => true]);
        $this->mobileAs($technician)->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/mobile/v1/commercial/leads', [...$payload, 'client_uuid' => (string) Str::uuid()])
            ->assertForbidden();
        $this->assertDatabaseCount('commercial_visits', 1);
    }

    public function test_mobile_agenda_lists_only_current_users_active_unlinked_activities(): void
    {
        $technician = User::factory()->create(['role' => 'technician']);
        FieldServiceProfile::create(['user_id' => $technician->id, 'weekly_schedule' => array_fill(1, 7, [['start' => '00:00', 'end' => '24:00']]), 'daily_capacity_minutes' => 1440, 'is_available' => true]);
        $other = User::factory()->create(['role' => 'technician']);
        FieldServiceProfile::create(['user_id' => $other->id, 'weekly_schedule' => array_fill(1, 7, [['start' => '00:00', 'end' => '24:00']]), 'daily_capacity_minutes' => 1440, 'is_available' => true]);
        $installation = Installation::create(['service_type' => 'solar', 'status' => 'in_progress', 'project_reference' => 'FV-MOBILE', 'site_address_line_1' => 'Calle Sol 1']);
        $visible = CrmActivity::create(['installation_id' => $installation->id, 'assigned_user_id' => $technician->id, 'type' => 'visit', 'title' => 'Long maintenance', 'priority' => 'normal', 'status' => 'pending', 'starts_at' => now()->subDays(2), 'due_at' => now()->addDays(5)]);
        CrmActivity::create(['assigned_user_id' => $other->id, 'type' => 'task', 'title' => 'Other user', 'priority' => 'normal', 'status' => 'pending']);
        CrmActivity::create(['assigned_user_id' => $technician->id, 'type' => 'task', 'title' => 'Non operational task', 'priority' => 'normal', 'status' => 'pending']);
        CrmActivity::create(['assigned_user_id' => $technician->id, 'type' => 'task', 'title' => 'Completed', 'priority' => 'normal', 'status' => 'completed', 'completed_at' => now()]);
        $linkedOrder = WorkOrder::create(['installation_id' => $installation->id, 'assigned_user_id' => $technician->id, 'number' => 'WO-LINKED', 'title' => 'Linked', 'status' => 'planned']);
        CrmActivity::create(['work_order_id' => $linkedOrder->id, 'assigned_user_id' => $technician->id, 'type' => 'visit', 'title' => 'Linked duplicate', 'priority' => 'normal', 'status' => 'pending']);

        $this->mobileAs($technician)->getJson('/api/mobile/v1/activities')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $visible->id)
            ->assertJsonPath('data.0.installation.project_reference', 'FV-MOBILE');
    }

    public function test_mobile_user_can_read_and_change_an_assigned_activity_idempotently(): void
    {
        $technician = User::factory()->create(['role' => 'technician']);
        FieldServiceProfile::create(['user_id' => $technician->id, 'weekly_schedule' => array_fill(1, 7, [['start' => '00:00', 'end' => '24:00']]), 'daily_capacity_minutes' => 1440, 'is_available' => true]);
        $installation = Installation::create(['service_type' => 'solar', 'status' => 'in_progress', 'site_address_line_1' => 'Calle Sol 1']);
        $activity = CrmActivity::create(['installation_id' => $installation->id, 'assigned_user_id' => $technician->id, 'type' => 'task', 'title' => 'Assigned task', 'notes' => 'Detailed instructions', 'priority' => 'high', 'status' => 'pending']);
        $key = (string) Str::uuid();

        $this->mobileAs($technician)->getJson("/api/mobile/v1/activities/{$activity->id}")
            ->assertOk()->assertJsonPath('data.notes', 'Detailed instructions');
        $this->withHeader('Idempotency-Key', $key)->patchJson("/api/mobile/v1/activities/{$activity->id}/status", [
            'status' => 'blocked',
            'blocked_reason' => 'Waiting for site access.',
        ])->assertOk()->assertJsonPath('data.blocked_reason', 'Waiting for site access.');
        $this->withHeader('Idempotency-Key', $key)->patchJson("/api/mobile/v1/activities/{$activity->id}/status", [
            'status' => 'completed',
        ])->assertOk()->assertHeader('Idempotency-Replayed', 'true')->assertJsonPath('data.status', 'blocked');
        $this->withHeader('Idempotency-Key', (string) Str::uuid())->patchJson("/api/mobile/v1/activities/{$activity->id}/status", [
            'status' => 'completed',
        ])->assertOk()->assertJsonPath('data.status', 'completed');

        $this->assertNotNull($activity->refresh()->completed_at);
    }

    public function test_mobile_user_cannot_access_another_users_activity(): void
    {
        $technician = User::factory()->create(['role' => 'technician']);
        FieldServiceProfile::create(['user_id' => $technician->id, 'weekly_schedule' => array_fill(1, 7, [['start' => '00:00', 'end' => '24:00']]), 'daily_capacity_minutes' => 1440, 'is_available' => true]);
        $installation = Installation::create(['service_type' => 'solar', 'status' => 'in_progress', 'site_address_line_1' => 'Calle Sol 1']);
        $activity = CrmActivity::create(['installation_id' => $installation->id, 'assigned_user_id' => User::factory()->create()->id, 'type' => 'task', 'title' => 'Private task', 'priority' => 'normal', 'status' => 'pending']);

        $this->mobileAs($technician)->getJson("/api/mobile/v1/activities/{$activity->id}")->assertForbidden();
        $this->withHeader('Idempotency-Key', (string) Str::uuid())->patchJson("/api/mobile/v1/activities/{$activity->id}/status", ['status' => 'completed'])->assertForbidden();
    }

    public function test_retention_prunes_only_expired_mobile_idempotency_responses(): void
    {
        $user = User::factory()->create();
        $operation = fn (string $key) => MobileOperation::create([
            'user_id' => $user->id,
            'key' => $key,
            'method' => 'PATCH',
            'path' => '/api/mobile/v1/work-orders/1/complete',
            'response_status' => 200,
            'response_body' => ['data' => ['status' => 'completed']],
        ]);
        $expired = $operation((string) Str::uuid());
        $expired->forceFill(['created_at' => now()->subDays(31), 'updated_at' => now()->subDays(31)])->save();
        $current = $operation((string) Str::uuid());

        $this->artisan('data:prune-retention')->assertSuccessful();

        $this->assertDatabaseMissing('mobile_operations', ['id' => $expired->id]);
        $this->assertDatabaseHas('mobile_operations', ['id' => $current->id]);
    }

    public function test_mobile_login_requires_valid_two_factor_code_when_enabled(): void
    {
        $user = User::factory()->withTwoFactor()->create(['password' => 'correct-password']);
        $payload = ['email' => $user->email, 'password' => 'correct-password', 'device_name' => 'iPhone test'];

        $this->postJson('/api/mobile/v1/auth/login', $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('two_factor_code');
        $provider = Mockery::mock(TwoFactorAuthenticationProvider::class);
        $provider->shouldReceive('verify')->once()->with('secret', '123456')->andReturnTrue();
        $this->app->instance(TwoFactorAuthenticationProvider::class, $provider);
        $this->postJson('/api/mobile/v1/auth/login', [...$payload, 'two_factor_code' => '123456'])
            ->assertOk()->assertJsonPath('data.user.id', $user->id);
    }

    public function test_technician_only_receives_assigned_work_orders(): void
    {
        $technician = User::factory()->create(['role' => 'technician']);
        FieldServiceProfile::create(['user_id' => $technician->id, 'weekly_schedule' => array_fill(1, 7, [['start' => '00:00', 'end' => '24:00']]), 'daily_capacity_minutes' => 1440, 'is_available' => true]);
        $other = User::factory()->create(['role' => 'technician']);
        FieldServiceProfile::create(['user_id' => $other->id, 'weekly_schedule' => array_fill(1, 7, [['start' => '00:00', 'end' => '24:00']]), 'daily_capacity_minutes' => 1440, 'is_available' => true]);
        $installation = Installation::create(['service_type' => 'solar', 'status' => 'scheduled', 'site_address_line_1' => 'Calle Sol 1']);
        $assigned = WorkOrder::create(['installation_id' => $installation->id, 'assigned_user_id' => $technician->id, 'number' => 'WO-1', 'title' => 'Assigned']);
        $hidden = WorkOrder::create(['installation_id' => $installation->id, 'assigned_user_id' => $other->id, 'number' => 'WO-2', 'title' => 'Hidden']);

        $this->mobileAs($technician)
            ->getJson('/api/mobile/v1/work-orders')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $assigned->id);
        $this->mobileAs($technician)->getJson("/api/mobile/v1/work-orders/{$hidden->id}")->assertForbidden();
    }

    public function test_mobile_sync_uses_a_stable_cursor_and_exposes_a_snapshot_checkpoint(): void
    {
        $technician = User::factory()->create(['role' => 'technician']);
        FieldServiceProfile::create(['user_id' => $technician->id, 'weekly_schedule' => array_fill(1, 7, [['start' => '00:00', 'end' => '24:00']]), 'daily_capacity_minutes' => 1440, 'is_available' => true]);
        $installation = Installation::create(['service_type' => 'solar', 'status' => 'scheduled', 'site_address_line_1' => 'Calle Sol 1']);
        $orders = collect(range(1, 3))->map(fn (int $index) => WorkOrder::create([
            'installation_id' => $installation->id,
            'assigned_user_id' => $technician->id,
            'number' => "WO-SYNC-{$index}",
            'title' => "Sync {$index}",
        ]));
        $orders->each(fn (WorkOrder $order, int $index) => $order->updateQuietly([
            'updated_at' => now()->subMinutes(10)->addSeconds($index),
        ]));

        $first = $this->mobileAs($technician)
            ->getJson('/api/mobile/v1/work-orders?sync=1&per_page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $orders[0]->id)
            ->assertJsonPath('data.1.id', $orders[1]->id)
            ->assertJsonPath('meta.snapshot_at', fn ($value) => is_string($value));

        $cursor = $first->json('meta.next_cursor');
        $snapshotAt = $first->json('meta.snapshot_at');
        $this->assertIsString($cursor);
        $this->travel(2)->minutes();
        $newer = WorkOrder::create([
            'installation_id' => $installation->id,
            'assigned_user_id' => $technician->id,
            'number' => 'WO-SYNC-NEW',
            'title' => 'Created during sync',
        ]);

        $this->getJson('/api/mobile/v1/work-orders?'.http_build_query([
            'sync' => 1,
            'per_page' => 2,
            'cursor' => $cursor,
            'snapshot_at' => $snapshotAt,
        ]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $orders[2]->id)
            ->assertJsonPath('meta.next_cursor', null);
        $this->getJson('/api/mobile/v1/work-orders?'.http_build_query([
            'sync' => 1,
            'updated_after' => $snapshotAt,
        ]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $newer->id);
    }

    public function test_mobile_status_changes_detect_a_stale_work_order_version(): void
    {
        $technician = User::factory()->create(['role' => 'technician', 'name' => 'Initial Technician']);
        FieldServiceProfile::create(['user_id' => $technician->id, 'weekly_schedule' => array_fill(1, 7, [['start' => '00:00', 'end' => '24:00']]), 'daily_capacity_minutes' => 1440, 'is_available' => true]);
        $admin = User::factory()->create(['role' => 'admin']);
        $installation = Installation::create(['service_type' => 'solar', 'status' => 'scheduled', 'site_address_line_1' => 'Calle Sol 1']);
        $order = WorkOrder::create([
            'installation_id' => $installation->id,
            'assigned_user_id' => $technician->id,
            'number' => 'WO-CONFLICT',
            'title' => 'Original',
            'scheduled_start' => now()->addDay(),
            'scheduled_end' => now()->addDay()->addHour(),
        ]);
        $order->activities()->create([
            'installation_id' => $installation->id,
            'assigned_user_id' => $technician->id,
            'created_by' => $admin->id,
            'type' => 'task',
            'title' => $order->title,
            'priority' => 'normal',
            'status' => 'pending',
        ]);
        $expected = $order->fresh()->updated_at->toISOString();
        $order->activities()->update(['priority' => 'high']);
        WorkOrder::query()->whereKey($order)->update([
            'title' => 'Changed on server',
            'scheduled_start' => now()->addDays(2),
            'scheduled_end' => now()->addDays(2)->addHour(),
            'updated_at' => now()->addMinute(),
        ]);

        $this->mobileAs($admin)
            ->withHeader('Idempotency-Key', (string) Str::uuid())
            ->patchJson("/api/mobile/v1/work-orders/{$order->id}/status", [
                'status' => 'in_progress',
                'expected_updated_at' => $expected,
            ])
            ->assertConflict()
            ->assertJsonPath('conflict.work_order_id', $order->id)
            ->assertJsonPath('conflict.expected_updated_at', $expected)
            ->assertJsonPath('conflict.current.title', 'Changed on server')
            ->assertJsonPath('conflict.current.priority', 'high')
            ->assertJsonPath('conflict.current.material_ready', true)
            ->assertJsonPath('conflict.current.assigned_user.name', 'Initial Technician');

        $this->assertSame('planned', $order->fresh()->status);
    }

    public function test_nested_execution_changes_advance_the_work_order_sync_version(): void
    {
        $technician = User::factory()->create(['role' => 'technician']);
        FieldServiceProfile::create(['user_id' => $technician->id, 'weekly_schedule' => array_fill(1, 7, [['start' => '00:00', 'end' => '24:00']]), 'daily_capacity_minutes' => 1440, 'is_available' => true]);
        $installation = Installation::create(['service_type' => 'solar', 'status' => 'scheduled', 'site_address_line_1' => 'Calle Sol 1']);
        $order = WorkOrder::create(['installation_id' => $installation->id, 'assigned_user_id' => $technician->id, 'number' => 'WO-TOUCH', 'title' => 'Touch parent']);
        WorkOrder::query()->whereKey($order)->update(['updated_at' => now()->subDay()]);
        $previousVersion = $order->fresh()->updated_at;

        $order->checklistItems()->create(['position' => 1, 'label' => 'Evidence', 'is_required' => true]);

        $this->assertTrue($order->fresh()->updated_at->isAfter($previousVersion));
        WorkOrder::query()->whereKey($order)->update(['updated_at' => now()->subDay()]);
        $activityVersion = $order->fresh()->updated_at;
        $order->activities()->create([
            'installation_id' => $installation->id,
            'assigned_user_id' => $technician->id,
            'created_by' => $technician->id,
            'type' => 'task',
            'title' => 'Priority changed',
            'priority' => 'high',
            'status' => 'pending',
        ]);
        $this->assertTrue($order->fresh()->updated_at->isAfter($activityVersion));
        $this->mobileAs($technician)
            ->getJson('/api/mobile/v1/work-orders?'.http_build_query(['sync' => 1, 'updated_after' => $previousVersion->toISOString()]))
            ->assertOk()
            ->assertJsonPath('data.0.id', $order->id)
            ->assertJsonPath('data.0.checklist.0.label', 'Evidence')
            ->assertJsonPath('data.0.priority', 'high');
    }

    public function test_technician_can_execute_assigned_work_order(): void
    {
        $technician = User::factory()->create(['role' => 'technician']);
        FieldServiceProfile::create(['user_id' => $technician->id, 'weekly_schedule' => array_fill(1, 7, [['start' => '00:00', 'end' => '24:00']]), 'daily_capacity_minutes' => 1440, 'is_available' => true]);
        $installation = Installation::create(['service_type' => 'solar', 'status' => 'scheduled', 'site_address_line_1' => 'Calle Sol 1']);
        $order = WorkOrder::create(['installation_id' => $installation->id, 'assigned_user_id' => $technician->id, 'number' => 'WO-1', 'title' => 'Assigned']);
        $item = $order->checklistItems()->create(['position' => 1, 'label' => 'Test item', 'is_required' => true]);

        $entryId = $this->mobileAs($technician)
            ->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson("/api/mobile/v1/work-orders/{$order->id}/timer")
            ->assertCreated()->json('data.id');
        $this->getJson('/api/mobile/v1/work-orders')->assertOk()->assertJsonPath('running_time_entry.id', $entryId);
        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->patchJson("/api/mobile/v1/work-orders/{$order->id}/checklist/{$item->id}", ['completed' => true])
            ->assertOk()->assertJsonPath('data.id', $item->id);
        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->patchJson("/api/mobile/v1/time-entries/{$entryId}/stop", ['travel_km' => 12.5])
            ->assertOk()->assertJsonPath('data.id', $entryId);
        $this->getJson("/api/mobile/v1/work-orders/{$order->id}")
            ->assertOk()->assertJsonPath('data.status', 'in_progress')->assertJsonPath('data.checklist.0.id', $item->id);
    }

    public function test_mobile_work_order_exposes_the_contract_version_and_covered_assets_offline(): void
    {
        $technician = User::factory()->create(['role' => 'technician']);
        FieldServiceProfile::create(['user_id' => $technician->id, 'weekly_schedule' => array_fill(1, 7, [['start' => '00:00', 'end' => '24:00']]), 'daily_capacity_minutes' => 1440, 'is_available' => true]);
        $installation = Installation::create(['service_type' => 'security', 'status' => 'completed', 'site_address_line_1' => 'Calle Alarma 1']);
        $asset = InstallationAsset::create(['installation_id' => $installation->id, 'asset_tag' => 'SEC-001', 'name' => 'Alarm panel', 'category' => 'alarm_panel', 'status' => 'active']);
        $plan = MaintenancePlan::create(['installation_id' => $installation->id, 'name' => 'Critical security care', 'version' => 4, 'service_level' => 'critical', 'coverage_type' => 'full', 'response_minutes' => 30, 'resolution_minutes' => 240, 'interval_months' => 3, 'starts_on' => today(), 'next_due_on' => today(), 'is_active' => true, 'scope' => 'Validate panel, signals and communications.']);
        $plan->coveredAssets()->attach($asset);
        $order = WorkOrder::create(['installation_id' => $installation->id, 'assigned_user_id' => $technician->id, 'number' => 'WO-SERVICE-1', 'title' => 'Security service']);
        ServiceTicket::create(['installation_id' => $installation->id, 'maintenance_plan_id' => $plan->id, 'maintenance_plan_version' => 3, 'work_order_id' => $order->id, 'number' => 'SAT-SERVICE-1', 'kind' => 'maintenance', 'priority' => 'high', 'status' => 'assigned', 'title' => 'Security service']);

        $this->mobileAs($technician)->getJson("/api/mobile/v1/work-orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('data.service_plan.name', 'Critical security care')
            ->assertJsonPath('data.service_plan.version', 3)
            ->assertJsonPath('data.service_plan.service_level', 'critical')
            ->assertJsonPath('data.service_plan.covered_assets.0.asset_tag', 'SEC-001');
    }

    public function test_technician_can_start_and_stop_a_timer_created_offline(): void
    {
        $technician = User::factory()->create(['role' => 'technician']);
        FieldServiceProfile::create(['user_id' => $technician->id, 'weekly_schedule' => array_fill(1, 7, [['start' => '00:00', 'end' => '24:00']]), 'daily_capacity_minutes' => 1440, 'is_available' => true]);
        $installation = Installation::create(['service_type' => 'solar', 'status' => 'scheduled', 'site_address_line_1' => 'Calle Sol 1']);
        $order = WorkOrder::create(['installation_id' => $installation->id, 'assigned_user_id' => $technician->id, 'number' => 'WO-OFFLINE', 'title' => 'Offline timer']);
        $clientUuid = (string) Str::uuid();
        $startedAt = now()->subMinutes(42)->toISOString();
        $endedAt = now()->subMinute()->toISOString();

        $this->mobileAs($technician)->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson("/api/mobile/v1/work-orders/{$order->id}/timer", [
                'client_uuid' => $clientUuid,
                'started_at' => $startedAt,
            ])
            ->assertCreated()
            ->assertJsonPath('data.client_uuid', $clientUuid);

        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->patchJson("/api/mobile/v1/time-entries/client/{$clientUuid}/stop", [
                'ended_at' => $endedAt,
                'travel_km' => 18.5,
            ])
            ->assertOk()
            ->assertJsonPath('data.client_uuid', $clientUuid)
            ->assertJsonPath('data.duration_minutes', 41);

        $this->assertDatabaseHas('time_entries', [
            'client_uuid' => $clientUuid,
            'user_id' => $technician->id,
            'travel_km' => 18.5,
        ]);
    }

    public function test_technician_can_block_and_resume_an_assigned_order_idempotently(): void
    {
        $technician = User::factory()->create(['role' => 'technician']);
        FieldServiceProfile::create(['user_id' => $technician->id, 'weekly_schedule' => array_fill(1, 7, [['start' => '00:00', 'end' => '24:00']]), 'daily_capacity_minutes' => 1440, 'is_available' => true]);
        $installation = Installation::create(['service_type' => 'solar', 'status' => 'in_progress', 'site_address_line_1' => 'Calle Sol 1']);
        $order = WorkOrder::create(['installation_id' => $installation->id, 'assigned_user_id' => $technician->id, 'number' => 'WO-BLOCK', 'title' => 'Blocked order', 'status' => 'in_progress']);
        $activity = $order->activities()->create(['assigned_user_id' => $technician->id, 'type' => 'task', 'title' => $order->title, 'status' => 'in_progress']);
        $blockKey = (string) Str::uuid();

        $this->mobileAs($technician)->withHeader('Idempotency-Key', $blockKey)
            ->patchJson("/api/mobile/v1/work-orders/{$order->id}/status", [
                'status' => 'blocked',
                'blocked_reason' => 'No hay acceso al cuarto técnico.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'blocked')
            ->assertJsonPath('data.blocked_reason', 'No hay acceso al cuarto técnico.');
        $this->app['auth']->forgetGuards();
        $this->mobileAs($technician)->withHeader('Idempotency-Key', $blockKey)
            ->patchJson("/api/mobile/v1/work-orders/{$order->id}/status", ['status' => 'blocked', 'blocked_reason' => 'Different'])
            ->assertOk()->assertHeader('Idempotency-Replayed', 'true')
            ->assertJsonPath('data.blocked_reason', 'No hay acceso al cuarto técnico.');

        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->patchJson("/api/mobile/v1/work-orders/{$order->id}/status", ['status' => 'in_progress'])
            ->assertOk()->assertJsonPath('data.status', 'in_progress')->assertJsonPath('data.blocked_reason', null);
        $this->assertNull($activity->refresh()->blocked_reason);
    }

    public function test_technician_must_stop_the_timer_before_blocking_an_order(): void
    {
        $technician = User::factory()->create(['role' => 'technician']);
        FieldServiceProfile::create(['user_id' => $technician->id, 'weekly_schedule' => array_fill(1, 7, [['start' => '00:00', 'end' => '24:00']]), 'daily_capacity_minutes' => 1440, 'is_available' => true]);
        $installation = Installation::create(['service_type' => 'solar', 'status' => 'in_progress', 'site_address_line_1' => 'Calle Sol 1']);
        $order = WorkOrder::create(['installation_id' => $installation->id, 'assigned_user_id' => $technician->id, 'number' => 'WO-BLOCK-TIMER', 'title' => 'Running order', 'status' => 'in_progress']);
        $order->timeEntries()->create(['user_id' => $technician->id, 'created_by' => $technician->id, 'started_at' => now()]);

        $this->mobileAs($technician)->withHeader('Idempotency-Key', (string) Str::uuid())
            ->patchJson("/api/mobile/v1/work-orders/{$order->id}/status", ['status' => 'blocked', 'blocked_reason' => 'Sin material.'])
            ->assertUnprocessable();

        $this->assertSame('in_progress', $order->refresh()->status);
        $this->assertDatabaseCount('mobile_operations', 0);
    }

    public function test_technician_can_complete_a_ready_work_order_idempotently(): void
    {
        $technician = User::factory()->create(['role' => 'technician']);
        FieldServiceProfile::create(['user_id' => $technician->id, 'weekly_schedule' => array_fill(1, 7, [['start' => '00:00', 'end' => '24:00']]), 'daily_capacity_minutes' => 1440, 'is_available' => true]);
        $installation = Installation::create(['service_type' => 'solar', 'status' => 'in_progress', 'site_address_line_1' => 'Calle Sol 1']);
        $order = WorkOrder::create([
            'installation_id' => $installation->id,
            'assigned_user_id' => $technician->id,
            'number' => 'WO-MOBILE-COMPLETE',
            'title' => 'Complete installation',
            'kind' => 'installation',
            'status' => 'in_progress',
            'signed_by_name' => 'Ana Cliente',
            'signed_at' => now(),
        ]);
        $order->checklistItems()->create([
            'position' => 1,
            'label' => 'Commissioning complete',
            'is_required' => true,
            'completed_by' => $technician->id,
            'completed_at' => now(),
        ]);
        $order->attachments()->create([
            'uploaded_by' => $technician->id,
            'kind' => 'photo',
            'original_name' => 'completed.jpg',
            'path' => 'work-orders/completed.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 100,
        ]);
        $key = (string) Str::uuid();

        $this->mobileAs($technician)->withHeader('Idempotency-Key', $key)
            ->patchJson("/api/mobile/v1/work-orders/{$order->id}/complete", ['completion_notes' => 'Installation tested and accepted.'])
            ->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.completion_notes', 'Installation tested and accepted.');
        $this->app['auth']->forgetGuards();
        $this->mobileAs($technician)->withHeader('Idempotency-Key', $key)
            ->patchJson("/api/mobile/v1/work-orders/{$order->id}/complete", ['completion_notes' => 'Must not run twice.'])
            ->assertOk()
            ->assertHeader('Idempotency-Replayed', 'true')
            ->assertJsonPath('data.completion_notes', 'Installation tested and accepted.');

        $this->assertSame('completed', $order->refresh()->status);
        $this->assertSame('completed', $installation->refresh()->status);
        $this->assertDatabaseCount('mobile_operations', 1);
    }

    public function test_mobile_completion_rejects_an_incomplete_work_order_without_reserving_the_key(): void
    {
        $technician = User::factory()->create(['role' => 'technician']);
        FieldServiceProfile::create(['user_id' => $technician->id, 'weekly_schedule' => array_fill(1, 7, [['start' => '00:00', 'end' => '24:00']]), 'daily_capacity_minutes' => 1440, 'is_available' => true]);
        $installation = Installation::create(['service_type' => 'solar', 'status' => 'in_progress', 'site_address_line_1' => 'Calle Sol 1']);
        $order = WorkOrder::create([
            'installation_id' => $installation->id,
            'assigned_user_id' => $technician->id,
            'number' => 'WO-MOBILE-INCOMPLETE',
            'title' => 'Incomplete installation',
            'kind' => 'installation',
            'status' => 'in_progress',
        ]);

        $this->mobileAs($technician)->withHeader('Idempotency-Key', (string) Str::uuid())
            ->patchJson("/api/mobile/v1/work-orders/{$order->id}/complete", ['completion_notes' => 'Attempted completion.'])
            ->assertUnprocessable();

        $this->assertSame('in_progress', $order->refresh()->status);
        $this->assertDatabaseCount('mobile_operations', 0);
    }

    public function test_technician_can_consume_reserved_material_from_mobile_idempotently(): void
    {
        $technician = User::factory()->create(['role' => 'technician']);
        FieldServiceProfile::create(['user_id' => $technician->id, 'weekly_schedule' => array_fill(1, 7, [['start' => '00:00', 'end' => '24:00']]), 'daily_capacity_minutes' => 1440, 'is_available' => true]);
        $installation = Installation::create(['service_type' => 'solar', 'status' => 'in_progress', 'site_address_line_1' => 'Calle Sol 1']);
        $order = WorkOrder::create([
            'installation_id' => $installation->id,
            'assigned_user_id' => $technician->id,
            'number' => 'WO-MOBILE-MATERIAL',
            'title' => 'Install inverter',
            'kind' => 'installation',
            'status' => 'in_progress',
        ]);
        $warehouse = Warehouse::create(['code' => 'VAN-1', 'name' => 'Service van', 'is_active' => true]);
        $product = Product::create([
            'type' => 'product',
            'name' => 'Inverter',
            'unit_id' => Unit::firstOrFail()->id,
            'tax_rate_id' => TaxRate::firstOrFail()->id,
            'sale_price' => 100,
            'cost_price' => 50,
            'track_stock' => true,
            'track_serials' => false,
            'is_active' => true,
        ]);
        app(StockLedger::class)->post([
            'type' => 'receipt',
            'warehouse_id' => $warehouse->id,
            'lines' => [['product_id' => $product->id, 'quantity' => 2, 'unit_cost' => 50]],
        ], $technician->id);
        $reservation = StockReservation::create([
            'installation_id' => $installation->id,
            'work_order_id' => $order->id,
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'consumed_quantity' => 0,
            'status' => 'active',
        ]);
        $key = (string) Str::uuid();

        $this->mobileAs($technician)->getJson("/api/mobile/v1/work-orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('data.materials.0.remaining_quantity', 2);
        $this->withHeader('Idempotency-Key', $key)
            ->postJson("/api/mobile/v1/work-orders/{$order->id}/materials/{$reservation->id}/consume", ['quantity' => 1])
            ->assertOk()
            ->assertJsonPath('data.remaining_quantity', 1);
        $this->app['auth']->forgetGuards();
        $this->mobileAs($technician)->withHeader('Idempotency-Key', $key)
            ->postJson("/api/mobile/v1/work-orders/{$order->id}/materials/{$reservation->id}/consume", ['quantity' => 1])
            ->assertOk()
            ->assertHeader('Idempotency-Replayed', 'true')
            ->assertJsonPath('data.remaining_quantity', 1);

        $this->assertSame('1.000', $reservation->refresh()->consumed_quantity);
        $this->assertDatabaseCount('mobile_operations', 1);
    }

    public function test_mutation_is_replayed_without_running_twice(): void
    {
        $technician = User::factory()->create(['role' => 'technician']);
        FieldServiceProfile::create(['user_id' => $technician->id, 'weekly_schedule' => array_fill(1, 7, [['start' => '00:00', 'end' => '24:00']]), 'daily_capacity_minutes' => 1440, 'is_available' => true]);
        $installation = Installation::create(['service_type' => 'solar', 'status' => 'scheduled', 'site_address_line_1' => 'Calle Sol 1']);
        $order = WorkOrder::create(['installation_id' => $installation->id, 'assigned_user_id' => $technician->id, 'number' => 'WO-1', 'title' => 'Assigned']);
        $key = (string) Str::uuid();

        $this->mobileAs($technician)->withHeader('Idempotency-Key', $key)
            ->postJson("/api/mobile/v1/work-orders/{$order->id}/timer")->assertCreated();
        $this->app['auth']->forgetGuards();
        $this->mobileAs($technician)->withHeader('Idempotency-Key', $key)
            ->postJson("/api/mobile/v1/work-orders/{$order->id}/timer")
            ->assertCreated()->assertHeader('Idempotency-Replayed', 'true');

        $this->assertDatabaseCount('time_entries', 1);
        $this->assertDatabaseCount('mobile_operations', 1);
    }

    public function test_an_in_flight_idempotent_mutation_is_not_executed_twice(): void
    {
        $technician = User::factory()->create(['role' => 'technician']);
        FieldServiceProfile::create(['user_id' => $technician->id, 'weekly_schedule' => array_fill(1, 7, [['start' => '00:00', 'end' => '24:00']]), 'daily_capacity_minutes' => 1440, 'is_available' => true]);
        $installation = Installation::create(['service_type' => 'solar', 'status' => 'scheduled', 'site_address_line_1' => 'Calle Sol 1']);
        $order = WorkOrder::create(['installation_id' => $installation->id, 'assigned_user_id' => $technician->id, 'number' => 'WO-1', 'title' => 'Assigned']);
        $key = (string) Str::uuid();
        MobileOperation::create([
            'user_id' => $technician->id,
            'key' => $key,
            'method' => 'POST',
            'path' => "api/mobile/v1/work-orders/{$order->id}/timer",
            'response_status' => 102,
        ]);

        $this->mobileAs($technician)->withHeader('Idempotency-Key', $key)
            ->postJson("/api/mobile/v1/work-orders/{$order->id}/timer")
            ->assertConflict()
            ->assertHeader('Idempotency-In-Progress', 'true')
            ->assertHeader('Retry-After', '2');

        $this->assertDatabaseCount('time_entries', 0);
    }

    public function test_technician_can_queue_an_idempotent_photo_upload(): void
    {
        Storage::fake('local');
        $technician = User::factory()->create(['role' => 'technician']);
        FieldServiceProfile::create(['user_id' => $technician->id, 'weekly_schedule' => array_fill(1, 7, [['start' => '00:00', 'end' => '24:00']]), 'daily_capacity_minutes' => 1440, 'is_available' => true]);
        $other = User::factory()->create(['role' => 'technician']);
        FieldServiceProfile::create(['user_id' => $other->id, 'weekly_schedule' => array_fill(1, 7, [['start' => '00:00', 'end' => '24:00']]), 'daily_capacity_minutes' => 1440, 'is_available' => true]);
        $installation = Installation::create(['service_type' => 'solar', 'status' => 'scheduled', 'site_address_line_1' => 'Calle Sol 1']);
        $order = WorkOrder::create(['installation_id' => $installation->id, 'assigned_user_id' => $technician->id, 'number' => 'WO-1', 'title' => 'Assigned']);
        $key = (string) Str::uuid();

        $this->mobileAs($technician)->withHeader('Idempotency-Key', $key)
            ->post("/api/mobile/v1/work-orders/{$order->id}/attachments", [
                'file' => UploadedFile::fake()->image('inverter.jpg', 1200, 900),
                'caption' => 'Instalación finalizada',
            ], ['Accept' => 'application/json'])
            ->assertCreated()->assertJsonPath('data.kind', 'photo');
        $attachment = $order->attachments()->firstOrFail();
        Storage::disk('local')->assertExists($attachment->path);

        $this->app['auth']->forgetGuards();
        $this->mobileAs($technician)->withHeader('Idempotency-Key', $key)
            ->post("/api/mobile/v1/work-orders/{$order->id}/attachments", ['file' => UploadedFile::fake()->image('duplicate.jpg')], ['Accept' => 'application/json'])
            ->assertCreated()->assertHeader('Idempotency-Replayed', 'true');
        $this->assertDatabaseCount('work_order_attachments', 1);

        $this->mobileAs($other)->withHeader('Idempotency-Key', (string) Str::uuid())
            ->post("/api/mobile/v1/work-orders/{$order->id}/attachments", ['file' => UploadedFile::fake()->image('forbidden.jpg')], ['Accept' => 'application/json'])
            ->assertForbidden();
    }

    public function test_technician_can_capture_an_idempotent_signature(): void
    {
        Storage::fake('local');
        $technician = User::factory()->create(['role' => 'technician']);
        FieldServiceProfile::create(['user_id' => $technician->id, 'weekly_schedule' => array_fill(1, 7, [['start' => '00:00', 'end' => '24:00']]), 'daily_capacity_minutes' => 1440, 'is_available' => true]);
        $other = User::factory()->create(['role' => 'technician']);
        FieldServiceProfile::create(['user_id' => $other->id, 'weekly_schedule' => array_fill(1, 7, [['start' => '00:00', 'end' => '24:00']]), 'daily_capacity_minutes' => 1440, 'is_available' => true]);
        $installation = Installation::create(['service_type' => 'solar', 'status' => 'scheduled', 'site_address_line_1' => 'Calle Sol 1']);
        $order = WorkOrder::create(['installation_id' => $installation->id, 'assigned_user_id' => $technician->id, 'number' => 'WO-1', 'title' => 'Assigned']);
        $key = (string) Str::uuid();

        $this->mobileAs($technician)->withHeader('Idempotency-Key', $key)
            ->post("/api/mobile/v1/work-orders/{$order->id}/signature", [
                'signed_by_name' => 'Ana Cliente',
                'signature' => UploadedFile::fake()->image('signature.png', 600, 240),
            ], ['Accept' => 'application/json'])
            ->assertCreated()->assertJsonPath('data.signed_by_name', 'Ana Cliente');
        $order->refresh();
        Storage::disk('local')->assertExists($order->signature_path);

        $this->app['auth']->forgetGuards();
        $this->mobileAs($technician)->withHeader('Idempotency-Key', $key)
            ->post("/api/mobile/v1/work-orders/{$order->id}/signature", [
                'signed_by_name' => 'Firma duplicada',
                'signature' => UploadedFile::fake()->image('duplicate.png'),
            ], ['Accept' => 'application/json'])
            ->assertCreated()->assertHeader('Idempotency-Replayed', 'true')->assertJsonPath('data.signed_by_name', 'Ana Cliente');
        $this->assertDatabaseCount('mobile_operations', 1);

        $this->mobileAs($other)->withHeader('Idempotency-Key', (string) Str::uuid())
            ->post("/api/mobile/v1/work-orders/{$order->id}/signature", [
                'signed_by_name' => 'Intruso',
                'signature' => UploadedFile::fake()->image('forbidden.png'),
            ], ['Accept' => 'application/json'])
            ->assertForbidden();
    }

    public function test_technician_can_record_an_idempotent_arrival(): void
    {
        $technician = User::factory()->create(['role' => 'technician']);
        FieldServiceProfile::create(['user_id' => $technician->id, 'weekly_schedule' => array_fill(1, 7, [['start' => '00:00', 'end' => '24:00']]), 'daily_capacity_minutes' => 1440, 'is_available' => true]);
        $other = User::factory()->create(['role' => 'technician']);
        FieldServiceProfile::create(['user_id' => $other->id, 'weekly_schedule' => array_fill(1, 7, [['start' => '00:00', 'end' => '24:00']]), 'daily_capacity_minutes' => 1440, 'is_available' => true]);
        $installation = Installation::create(['service_type' => 'solar', 'status' => 'scheduled', 'site_address_line_1' => 'Calle Sol 1']);
        $order = WorkOrder::create(['installation_id' => $installation->id, 'assigned_user_id' => $technician->id, 'number' => 'WO-1', 'title' => 'Assigned']);
        $key = (string) Str::uuid();
        $payload = ['latitude' => 40.4167754, 'longitude' => -3.7037902, 'accuracy_meters' => 8.5, 'arrived_at' => now()->subMinute()->toISOString()];

        $this->mobileAs($technician)->withHeader('Idempotency-Key', $key)
            ->postJson("/api/mobile/v1/work-orders/{$order->id}/arrivals", $payload)
            ->assertCreated()->assertJsonPath('data.latitude', 40.4167754);
        $this->app['auth']->forgetGuards();
        $this->mobileAs($technician)->withHeader('Idempotency-Key', $key)
            ->postJson("/api/mobile/v1/work-orders/{$order->id}/arrivals", $payload)
            ->assertCreated()->assertHeader('Idempotency-Replayed', 'true');
        $this->assertDatabaseCount('work_order_visits', 1);

        $this->mobileAs($other)->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson("/api/mobile/v1/work-orders/{$order->id}/arrivals", $payload)
            ->assertForbidden();
        $this->mobileAs($technician)->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson("/api/mobile/v1/work-orders/{$order->id}/arrivals", [...$payload, 'latitude' => 91])
            ->assertUnprocessable()->assertJsonValidationErrors('latitude');
    }
}

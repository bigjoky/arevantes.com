<?php

namespace Tests\Feature;

use App\Ai\Agents\AreviaChat;
use App\Ai\Agents\InstallationAdvisor;
use App\Ai\Agents\InstallationSnapshot;
use App\Models\AuditLog;
use App\Models\Country;
use App\Models\Installation;
use App\Models\KnowledgeDocument;
use App\Models\Person;
use App\Models\RemoteStation;
use App\Models\User;
use App\Services\Ai\AiActionProposalService;
use App\Services\Deye\DeyeCloudClient;
use App\Services\Huawei\FusionSolarClient;
use App\Services\Monitoring\RemoteStationCatalog;
use Database\Seeders\LocationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_dashboard()
    {
        $user = User::factory()->create(['role' => 'commercial']);
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertOk();
        $this->assertMatchesRegularExpression(
            '/^app;dur=\d+(?:\.\d+)?, db;dur=\d+(?:\.\d+)?$/',
            (string) $response->headers->get('Server-Timing'),
        );
    }

    public function test_dashboard_keeps_map_data_out_of_the_initial_response_and_loads_it_on_demand()
    {
        $mapCacheKey = 'dashboard:installation-map:v1:'.app()->getLocale();
        Cache::forget($mapCacheKey);
        $user = User::factory()->create();
        $this->actingAs($user);

        Person::create([
            'category' => 'staff',
            'first_name' => 'Luis',
            'email' => 'luis@example.com',
        ]);

        $customer = Person::create([
            'category' => 'customer',
            'customer_type' => 'residential',
            'first_name' => 'Ana',
            'last_name' => 'Garcia',
            'service_interests' => ['Solar installation', 'EV charger'],
            'document_type' => 'nif',
            'document_number' => '12345678Z',
            'email' => 'ana@example.com',
            'city' => 'Madrid',
            'country' => 'Spain',
        ]);

        Installation::create([
            'person_id' => $customer->id,
            'service_type' => 'solar',
            'status' => 'scheduled',
            'project_reference' => 'FV-2026-002',
            'site_address_line_1' => 'Calle Sol 12',
            'site_city' => 'Cordoba',
            'site_country' => 'Spain',
            'site_latitude' => '37.8882000',
            'site_longitude' => '-4.7794000',
        ]);

        $response = $this->get(route('dashboard'));

        $response
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard')
                ->where('peopleSummary.total', 2)
                ->where('peopleSummary.customers', 1)
                ->where('peopleSummary.staff', 1)
                ->where('installationSummary.total', 1)
                ->where('installationSummary.active', 1)
                ->missing('installations')
            );

        $this->assertFalse(Cache::has($mapCacheKey));

        $this->getJson(route('dashboard.installation-map'))
            ->assertOk()
            ->assertJsonPath('installations.0.project_reference', 'FV-2026-002')
            ->assertJsonPath('installations.0.site_latitude', '37.8882000')
            ->assertJsonPath('installations.0.site_longitude', '-4.7794000');

        $this->assertTrue(Cache::has($mapCacheKey));
    }

    public function test_dashboard_normalizes_deye_and_huawei_monitoring_for_the_map()
    {
        Cache::forget('dashboard:installation-map:v1:'.app()->getLocale());
        $this->actingAs(User::factory()->create());
        $deye = Installation::create([
            'service_type' => 'solar', 'status' => 'completed', 'site_address_line_1' => 'Deye site',
            'site_latitude' => 37.1, 'site_longitude' => -4.1, 'deye_station_id' => 'D-1',
            'deye_station_name' => 'Deye station', 'deye_status' => 'healthy',
            'deye_current_power_kw' => 4.25, 'deye_today_energy_kwh' => 18.7, 'deye_synced_at' => now(),
        ]);
        $deye->forceFill(['created_at' => now()->subMinute()])->saveQuietly();
        Installation::create([
            'service_type' => 'solar', 'status' => 'completed', 'site_address_line_1' => 'Huawei site',
            'site_latitude' => 37.2, 'site_longitude' => -4.2, 'huawei_station_code' => 'H-1',
            'huawei_station_name' => 'Huawei station', 'huawei_status' => 'fault',
            'huawei_current_power_kw' => 3.1, 'huawei_today_energy_kwh' => 12.4,
            'huawei_status_message' => 'Inverter alarm', 'huawei_synced_at' => now(),
        ]);

        $this->getJson(route('dashboard.installation-map'))
            ->assertOk()
            ->assertJsonPath('installations.0.monitoring.provider', 'huawei')
            ->assertJsonPath('installations.0.monitoring.status', 'fault')
            ->assertJsonPath('installations.0.monitoring.current_power_kw', '3.100')
            ->assertJsonPath('installations.0.monitoring.message', 'Inverter alarm')
            ->assertJsonPath('installations.1.monitoring.provider', 'deye')
            ->assertJsonPath('installations.1.monitoring.status', 'healthy')
            ->assertJsonPath('installations.1.monitoring.today_energy_kwh', '18.700')
            ->assertJsonPath('installations.1.monitoring.message', 'Operating normally.');
    }

    public function test_deye_cloud_status_is_synchronized_for_an_installation()
    {
        Cache::clear();
        config([
            'services.deye.url' => 'https://eu1-developer.deyecloud.com',
            'services.deye.app_id' => 'app-id',
            'services.deye.app_secret' => 'app-secret',
            'services.deye.email' => 'solar@example.com',
            'services.deye.password' => 'password',
        ]);

        Http::fake(function ($request) {
            return match (true) {
                str_contains($request->url(), '/account/token') => Http::response(['success' => true, 'accessToken' => 'token']),
                str_contains($request->url(), '/station/list') => Http::response(['success' => true, 'stationList' => [[
                    'id' => 322,
                    'name' => 'Solar Baena',
                    'connectionStatus' => 'NORMAL',
                    'generationPower' => 4250,
                ]]]),
                str_contains($request->url(), '/station/latest') => Http::response(['success' => true, 'generationPower' => 4250]),
                str_contains($request->url(), '/station/history') => Http::response(['success' => true, 'stationDataItems' => [['generationValue' => 18.7]]]),
                str_contains($request->url(), '/station/alertList') => Http::response(['success' => true, 'stationAlertItems' => []]),
                default => Http::response([], 404),
            };
        });

        $customer = Person::create(['category' => 'customer', 'first_name' => 'Ana']);
        $installation = Installation::create([
            'person_id' => $customer->id,
            'service_type' => 'solar',
            'status' => 'completed',
            'site_address_line_1' => 'Calle Sol',
            'deye_station_id' => '322',
        ]);

        app(DeyeCloudClient::class)->sync($installation);
        $installation->refresh();

        $this->assertSame('healthy', $installation->deye_status);
        $this->assertSame('4.250', $installation->deye_current_power_kw);
        $this->assertSame('18.700', $installation->deye_today_energy_kwh);
        $this->assertSame('Solar Baena', $installation->deye_station_name);
        $this->assertDatabaseHas('monitoring_readings', [
            'installation_id' => $installation->id,
            'provider' => 'deye',
            'status' => 'healthy',
        ]);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/account/token')
            && $request['password'] === hash('sha256', 'password'));
    }

    public function test_huawei_scheduled_sync_batches_installations_into_one_client_call()
    {
        Installation::create([
            'service_type' => 'solar',
            'status' => 'completed',
            'site_address_line_1' => 'Site one',
            'huawei_station_code' => 'HW-1',
        ]);
        Installation::create([
            'service_type' => 'solar',
            'status' => 'completed',
            'site_address_line_1' => 'Site two',
            'huawei_station_code' => 'HW-2',
        ]);
        $client = Mockery::mock(FusionSolarClient::class);
        $client->shouldReceive('syncMany')
            ->once()
            ->withArgs(fn ($installations) => $installations->count() === 2);
        $this->app->instance(FusionSolarClient::class, $client);

        $this->artisan('huawei:sync')->assertSuccessful();
    }

    public function test_huawei_sync_gets_current_power_from_inverter_kpis()
    {
        Cache::clear();
        config([
            'services.huawei.url' => 'https://eu5.fusionsolar.huawei.com',
            'services.huawei.username' => 'installer',
            'services.huawei.system_code' => 'secret',
        ]);

        Http::fake(function ($request) {
            return match (true) {
                str_contains($request->url(), '/thirdData/login') => Http::response(
                    ['success' => true],
                    200,
                    ['XSRF-TOKEN' => 'token'],
                ),
                str_contains($request->url(), '/thirdData/getStationList') => Http::response([
                    'success' => true,
                    'data' => ['list' => [[
                        'stationCode' => 'HW-1',
                        'stationName' => 'Huawei station',
                    ]]],
                ]),
                str_contains($request->url(), '/thirdData/getStationRealKpi') => Http::response([
                    'success' => true,
                    'data' => [[
                        'stationCode' => 'HW-1',
                        'dataItemMap' => ['day_power' => 12.58, 'total_power' => 23202.61],
                    ]],
                ]),
                str_contains($request->url(), '/thirdData/getDevList') => Http::response([
                    'success' => true,
                    'data' => [
                        ['id' => 101, 'stationCode' => 'HW-1', 'devTypeId' => 38, 'invType' => 'SUN2000-6KTL-L1'],
                        ['id' => 102, 'stationCode' => 'HW-1', 'devTypeId' => 38, 'invType' => 'SUN2000-3KTL-L1'],
                        ['id' => 103, 'stationCode' => 'HW-1', 'devTypeId' => 47, 'invType' => null],
                    ],
                ]),
                str_contains($request->url(), '/thirdData/getDevRealKpi') => Http::response([
                    'success' => true,
                    'data' => [
                        ['devId' => 101, 'dataItemMap' => ['active_power' => 2.1]],
                        ['devId' => 102, 'dataItemMap' => ['active_power' => 1.35]],
                    ],
                ]),
                str_contains($request->url(), '/thirdData/getAlarmList') => Http::response([
                    'success' => true,
                    'data' => [],
                ]),
                default => Http::response([], 404),
            };
        });

        $installation = Installation::create([
            'service_type' => 'solar',
            'status' => 'completed',
            'site_address_line_1' => 'Huawei site',
            'huawei_station_code' => 'HW-1',
        ]);

        app(FusionSolarClient::class)->sync($installation);
        $installation->refresh();

        $this->assertSame('3.450', $installation->huawei_current_power_kw);
        $this->assertSame('12.580', $installation->huawei_today_energy_kwh);
        $this->assertCount(2, $installation->huawei_data['inverters']);
        $this->assertDatabaseHas('monitoring_readings', [
            'installation_id' => $installation->id,
            'provider' => 'huawei',
            'current_power_kw' => 3.450,
        ]);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/thirdData/getDevRealKpi')
            && $request['devIds'] === '101,102'
            && $request['devTypeId'] === 38);
    }

    public function test_huawei_refresh_reauthenticates_when_the_cached_session_is_invalid()
    {
        Cache::clear();
        Cache::put('huawei:session', ['token' => 'expired-token', 'cookie' => 'old=session'], now()->addMinutes(25));
        config([
            'services.huawei.url' => 'https://eu5.fusionsolar.huawei.com',
            'services.huawei.username' => 'installer',
            'services.huawei.system_code' => 'secret',
        ]);

        Http::fake(function ($request) {
            if (str_contains($request->url(), '/thirdData/login')) {
                return Http::response(['success' => true], 200, ['XSRF-TOKEN' => 'fresh-token']);
            }

            if (str_contains($request->url(), '/thirdData/getStationList')
                && $request->hasHeader('XSRF-TOKEN', 'expired-token')) {
                return Http::response(['success' => false, 'failCode' => 305]);
            }

            if (str_contains($request->url(), '/thirdData/getStationList')
                && $request->hasHeader('XSRF-TOKEN', 'fresh-token')) {
                return Http::response([
                    'success' => true,
                    'data' => ['list' => [[
                        'stationCode' => 'HW-NEW',
                        'stationName' => 'New Huawei plant',
                    ]]],
                ]);
            }

            return Http::response([], 404);
        });

        $stations = app(FusionSolarClient::class)->stations(true);

        $this->assertSame('HW-NEW', $stations[0]['stationCode']);
        Http::assertSentCount(3);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/thirdData/login'));
    }

    public function test_huawei_rate_limit_does_not_trigger_an_unnecessary_login()
    {
        Cache::clear();
        Cache::put('huawei:session', ['token' => 'valid-token', 'cookie' => 'current=session'], now()->addMinutes(25));
        config(['services.huawei.url' => 'https://eu5.fusionsolar.huawei.com']);

        Http::fake(fn () => Http::response(['success' => false, 'failCode' => 407]));

        $this->expectExceptionMessage('Huawei API request limit reached');

        try {
            app(FusionSolarClient::class)->stations(true);
        } finally {
            Http::assertSentCount(1);
        }
    }

    public function test_huawei_sync_can_use_the_last_successful_station_list_when_rate_limited()
    {
        Cache::clear();
        Cache::put('huawei:session', ['token' => 'valid-token', 'cookie' => 'current=session'], now()->addMinutes(25));
        Cache::forever('huawei:stations:last_successful', [[
            'stationCode' => 'HW-CACHED',
            'stationName' => 'Cached Huawei plant',
        ]]);
        config(['services.huawei.url' => 'https://eu5.fusionsolar.huawei.com']);

        Http::fake(fn () => Http::response(['success' => false, 'failCode' => 407]));

        $stations = app(FusionSolarClient::class)->stations();

        $this->assertSame('HW-CACHED', $stations[0]['stationCode']);
        Http::assertSentCount(1);
    }

    public function test_authenticated_users_can_create_customers_from_people()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->post(route('people.store'), [
            'category' => 'staff',
            'customer_type' => 'residential',
            'first_name' => 'Luis',
            'last_name' => 'Santos',
            'staff_role' => 'Installer',
            'document_type' => 'nie',
            'document_number' => 'X1234567L',
            'phone' => '+34 600 000 000',
            'email' => 'luis@example.com',
            'address_line_1' => 'Calle Mayor 1',
            'city' => 'Valencia',
            'country' => 'Spain',
            'service_interests' => "Solar\nElectrical",
            'extra_details' => "Department: Operations\nLocker: A12",
        ]);

        $person = Person::firstWhere('email', 'luis@example.com');

        $response->assertRedirect(route('people.show', $person));

        $this->assertDatabaseHas('people', [
            'category' => 'customer',
            'first_name' => 'Luis',
            'document_number' => 'X1234567L',
            'email' => 'luis@example.com',
        ]);

        $this->assertSame(
            ['Department' => 'Operations', 'Locker' => 'A12'],
            $person->extra_details,
        );

        $this->assertSame(
            ['Solar', 'Electrical'],
            $person->service_interests,
        );

        $this->assertNull($person->staff_role);
    }

    public function test_people_section_only_lists_and_opens_customers()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $person = Person::create([
            'category' => 'customer',
            'first_name' => 'Ana',
            'email' => 'ana@example.com',
            'city' => 'Lucena',
            'locality' => 'Lucena',
            'province_state' => 'Córdoba',
            'postal_code' => '14900',
            'country' => 'Spain',
        ]);

        $staff = Person::create([
            'category' => 'staff',
            'first_name' => 'Luis',
            'email' => 'luis@example.com',
        ]);

        $this->get(route('people.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('people/index')
                ->where('people.data.0.id', $person->id)
                ->has('people.data', 1)
            );
        $this->get(route('people.show', $person))->assertOk();
        $this->get(route('people.edit', $person))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('person.city', 'Lucena')
                ->where('person.locality', 'Lucena')
                ->where('person.province_state', 'Córdoba')
                ->where('person.country', 'Spain'));
        $this->get(route('people.show', $staff))->assertNotFound();
    }

    public function test_authenticated_users_can_visit_edit_update_and_delete_customers()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $person = Person::create([
            'category' => 'customer',
            'first_name' => 'Ana',
            'email' => 'ana@example.com',
        ]);

        $this->get(route('people.create'))->assertOk();
        $this->get(route('people.show', $person))->assertOk();
        $this->get(route('people.edit', $person))->assertOk();

        $response = $this->patch(route('people.update', $person), [
            'category' => 'staff',
            'customer_type' => 'residential',
            'first_name' => 'Ana Maria',
            'staff_role' => 'Installer',
            'email' => 'ana.maria@example.com',
            'service_interests' => 'Solar, EV charger',
        ]);

        $response->assertRedirect(route('people.show', $person));
        $this->assertDatabaseHas('people', [
            'id' => $person->id,
            'category' => 'customer',
            'first_name' => 'Ana Maria',
            'email' => 'ana.maria@example.com',
            'staff_role' => null,
        ]);

        $this->delete(route('people.destroy', $person))
            ->assertRedirect(route('people.index'));

        $this->assertSoftDeleted('people', ['id' => $person->id]);
    }

    public function test_authenticated_users_can_manage_staff_profiles()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $customer = Person::create([
            'category' => 'customer',
            'first_name' => 'Ana',
            'email' => 'ana@example.com',
        ]);

        $response = $this->post(route('staff.store'), [
            'category' => 'customer',
            'first_name' => 'Luis',
            'last_name' => 'Santos',
            'staff_role' => 'Installer',
            'document_type' => 'nie',
            'document_number' => 'X1234567L',
            'phone' => '+34 600 000 000',
            'email' => 'luis@example.com',
            'service_interests' => 'Solar',
        ]);

        $staff = Person::firstWhere('email', 'luis@example.com');

        $response->assertRedirect(route('staff.show', $staff));
        $this->assertDatabaseHas('people', [
            'id' => $staff->id,
            'category' => 'staff',
            'first_name' => 'Luis',
            'staff_role' => 'Installer',
        ]);

        $this->get(route('staff.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('people/index')
                ->where('people.0.id', $staff->id)
                ->has('people', 1)
            );
        $this->get(route('staff.create'))->assertOk();
        $this->get(route('staff.show', $staff))->assertOk();
        $this->get(route('staff.edit', $staff))->assertOk();
        $this->get(route('staff.show', $customer))->assertNotFound();

        $this->patch(route('staff.update', $staff), [
            'category' => 'customer',
            'first_name' => 'Luis Miguel',
            'staff_role' => 'Technical lead',
            'email' => 'luis.miguel@example.com',
            'service_interests' => 'Electrical',
        ])->assertRedirect(route('staff.show', $staff));

        $this->assertDatabaseHas('people', [
            'id' => $staff->id,
            'category' => 'staff',
            'first_name' => 'Luis Miguel',
            'staff_role' => 'Technical lead',
            'service_interests' => null,
        ]);

        $this->delete(route('staff.destroy', $staff))
            ->assertRedirect(route('staff.index'));

        $this->assertSoftDeleted('people', ['id' => $staff->id]);
    }

    public function test_admin_can_create_and_manage_crm_access_from_a_staff_profile()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $staff = Person::create([
            'category' => 'staff',
            'first_name' => 'Lucia',
            'last_name' => 'Solar',
            'email' => 'lucia.staff@example.com',
            'staff_role' => 'Installer',
        ]);
        $this->actingAs($admin);

        $this->post(route('staff.access.store', $staff), [
            'email' => 'lucia.access@example.com',
            'role' => 'technician',
            'password' => 'Access-Password-2026!',
            'password_confirmation' => 'Access-Password-2026!',
        ])->assertRedirect();

        $account = User::where('person_id', $staff->id)->firstOrFail();
        $this->assertSame('technician', $account->role);
        $this->get(route('staff.show', $staff))
            ->assertInertia(fn (Assert $page) => $page
                ->where('person.access.email', 'lucia.access@example.com')
                ->where('person.access.role', 'technician'));

        $this->patch(route('staff.access.update', $staff), [
            'email' => 'lucia.access@example.com',
            'role' => 'commercial',
            'password' => '',
            'password_confirmation' => '',
        ])->assertRedirect();
        $this->assertDatabaseHas('users', ['person_id' => $staff->id, 'role' => 'commercial']);

        $this->delete(route('staff.access.destroy', $staff))->assertRedirect();
        $this->assertDatabaseMissing('users', ['person_id' => $staff->id]);
        $this->assertDatabaseHas('people', ['id' => $staff->id, 'category' => 'staff']);
    }

    public function test_authenticated_users_can_create_installations_for_customers()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $customer = Person::create([
            'category' => 'customer',
            'customer_type' => 'business',
            'first_name' => 'Maria',
            'last_name' => 'Lopez',
            'company_name' => 'Lopez Logistics',
            'email' => 'maria@example.com',
        ]);

        $response = $this->post(route('installations.store'), [
            'person_id' => $customer->id,
            'service_type' => 'solar',
            'status' => 'survey_booked',
            'project_reference' => 'FV-2026-001',
            'site_address_line_1' => 'Poligono Industrial',
            'site_street_number' => '4',
            'site_city' => 'Malaga',
            'site_country' => 'Spain',
            'site_latitude' => '36.7213028',
            'site_longitude' => '-4.4216366',
            'survey_date' => '2026-07-01',
            'system_power_kw' => '25.50',
            'panel_count' => 46,
            'inverter_brand' => 'Huawei',
            'technical_notes' => 'Three-phase supply available.',
            'extra_details' => "Roof type: flat\nAccess: lift",
        ]);

        $installation = Installation::firstWhere('project_reference', 'FV-2026-001');

        $response->assertRedirect(route('installations.show', $installation));

        $this->assertDatabaseHas('installations', [
            'person_id' => $customer->id,
            'service_type' => 'solar',
            'status' => 'survey_booked',
            'project_reference' => 'FV-2026-001',
            'site_city' => 'Malaga',
            'site_street_number' => '4',
            'site_latitude' => '36.7213028',
            'site_longitude' => '-4.4216366',
        ]);

        $this->assertSame('Maria', $installation->customer->first_name);
        $this->assertSame(
            ['Roof type' => 'flat', 'Access' => 'lift'],
            $installation->extra_details,
        );
    }

    public function test_authenticated_users_can_create_installations_without_a_customer()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->post(route('installations.store'), [
            'person_id' => '',
            'service_type' => 'solar',
            'status' => 'lead',
            'project_reference' => 'FV-UNASSIGNED-1',
            'site_address_line_1' => 'Calle Solar',
        ]);

        $installation = Installation::firstWhere('project_reference', 'FV-UNASSIGNED-1');

        $response->assertRedirect(route('installations.show', $installation));
        $this->assertNull($installation->person_id);
    }

    public function test_remote_monitoring_stations_can_be_bound_and_reassigned_by_provider()
    {
        $this->actingAs(User::factory()->create());
        $deye = RemoteStation::create([
            'provider' => 'deye',
            'remote_identifier' => 'DEYE-322',
            'name' => 'Solar Baena',
            'discovered_at' => now(),
            'last_seen_at' => now(),
        ]);
        $huawei = RemoteStation::create([
            'provider' => 'huawei',
            'remote_identifier' => 'HW-123',
            'name' => 'Solar Cordoba',
            'discovered_at' => now(),
            'last_seen_at' => now(),
        ]);

        $this->post(route('installations.store'), [
            'service_type' => 'solar',
            'status' => 'lead',
            'site_address_line_1' => 'Calle Solar',
            'monitoring_provider' => 'deye',
            'remote_station_id' => $deye->id,
        ])->assertRedirect();

        $installation = Installation::first();
        $this->assertSame('DEYE-322', $installation->deye_station_id);
        $this->assertSame($installation->id, $deye->refresh()->installation_id);

        $this->patch(route('installations.update', $installation), [
            'service_type' => 'solar',
            'status' => 'lead',
            'site_address_line_1' => 'Calle Solar',
            'monitoring_provider' => 'huawei',
            'remote_station_id' => $huawei->id,
        ])->assertRedirect(route('installations.show', $installation));

        $installation->refresh();
        $this->assertNull($installation->deye_station_id);
        $this->assertSame('HW-123', $installation->huawei_station_code);
        $this->assertNull($deye->refresh()->installation_id);
        $this->assertSame($installation->id, $huawei->refresh()->installation_id);
    }

    public function test_commercial_users_can_refresh_available_huawei_stations()
    {
        $user = User::factory()->create(['role' => 'commercial']);
        $catalog = Mockery::mock(RemoteStationCatalog::class);
        $catalog->shouldReceive('refresh')->once()->with('huawei')->andReturnUsing(function () {
            RemoteStation::create([
                'provider' => 'huawei',
                'remote_identifier' => 'HW-NEW',
                'name' => 'New Huawei plant',
                'discovered_at' => now(),
                'last_seen_at' => now(),
            ]);

            return 1;
        });
        $this->app->instance(RemoteStationCatalog::class, $catalog);

        $this->actingAs($user)
            ->postJson(route('remote-stations.refresh'), ['provider' => 'huawei'])
            ->assertOk()
            ->assertJsonPath('stations.0.remote_identifier', 'HW-NEW')
            ->assertJsonPath('stations.0.name', 'New Huawei plant');
    }

    public function test_a_remote_station_cannot_be_bound_to_two_installations()
    {
        $this->actingAs(User::factory()->create());
        $existing = Installation::create([
            'service_type' => 'solar',
            'status' => 'lead',
            'site_address_line_1' => 'Site one',
        ]);
        $station = RemoteStation::create([
            'installation_id' => $existing->id,
            'provider' => 'huawei',
            'remote_identifier' => 'HW-LOCKED',
            'discovered_at' => now(),
            'last_seen_at' => now(),
        ]);

        $this->post(route('installations.store'), [
            'service_type' => 'solar',
            'status' => 'lead',
            'site_address_line_1' => 'Site two',
            'monitoring_provider' => 'huawei',
            'remote_station_id' => $station->id,
        ])->assertSessionHasErrors('remote_station_id');

        $this->assertDatabaseCount('installations', 1);
    }

    public function test_archiving_a_customer_preserves_their_installation_history()
    {
        $customer = Person::create([
            'category' => 'customer',
            'first_name' => 'Maria',
        ]);
        $installation = Installation::create([
            'person_id' => $customer->id,
            'service_type' => 'solar',
            'status' => 'lead',
            'site_address_line_1' => 'Calle Solar',
        ]);

        $customer->delete();

        $this->assertDatabaseHas('installations', [
            'id' => $installation->id,
            'person_id' => $customer->id,
        ]);
        $this->assertSoftDeleted('people', ['id' => $customer->id]);
        $this->assertSame($customer->id, $installation->fresh()->customer->id);
    }

    public function test_authenticated_users_can_open_the_installation_workflow()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $customer = Person::create([
            'category' => 'customer',
            'first_name' => 'Maria',
            'address_line_1' => 'Calle Solar 10',
            'city' => 'Córdoba',
            'province_state' => 'Córdoba',
            'postal_code' => '14001',
            'country' => 'Spain',
        ]);

        $installation = Installation::create([
            'person_id' => $customer->id,
            'service_type' => 'solar',
            'status' => 'lead',
            'project_reference' => 'FV-WORKFLOW-1',
            'site_address_line_1' => 'Calle Solar 10',
        ]);

        $this->get(route('installations.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('installations/index')
                ->where('installations.0.id', $installation->id)
                ->where('installations.0.customer_name', 'Maria')
            );

        $this->get(route('installations.create', ['customer' => $customer->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('installations/create')
                ->where('installation.person_id', $customer->id)
                ->where('installation.site_address_line_1', 'Calle Solar')
                ->where('installation.site_street_number', '10')
                ->where('installation.site_city', 'Córdoba')
                ->where('installation.site_postal_code', '14001')
                ->has('customers', 1)
            );
    }

    public function test_installation_create_rejects_staff_as_preselected_customer()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $staff = Person::create([
            'category' => 'staff',
            'first_name' => 'Luis',
        ]);

        $this->get(route('installations.create', ['customer' => $staff->id]))
            ->assertNotFound();
    }

    public function test_authenticated_users_can_geocode_an_installation_address()
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        Cache::clear();

        Http::fake([
            'https://nominatim.openstreetmap.org/search*' => Http::response([
                [
                    'place_id' => 12345,
                    'osm_type' => 'way',
                    'osm_id' => 98765,
                    'lat' => '37.8882000',
                    'lon' => '-4.7794000',
                    'display_name' => 'Calle Claudio Marcelo, Córdoba, España',
                    'category' => 'place',
                    'type' => 'house',
                    'importance' => 0.42,
                    'boundingbox' => ['37.88', '37.89', '-4.78', '-4.77'],
                    'address' => [
                        'road' => 'Calle Claudio Marcelo',
                        'house_number' => '1',
                        'city' => 'Córdoba',
                        'state' => 'Andalucía',
                        'postcode' => '14002',
                        'country' => 'España',
                        'country_code' => 'es',
                    ],
                ],
            ]),
        ]);

        $response = $this->postJson(route('geocoding.search'), [
            'query' => 'Calle Claudio Marcelo 1, 14002 Córdoba, España',
            'language' => 'es',
            'street' => 'Calle Claudio Marcelo',
            'street_number' => '1',
            'municipality' => 'Córdoba',
            'province' => 'Córdoba',
            'postal_code' => '14002',
            'country' => 'España',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('results.0.latitude', '37.8882000')
            ->assertJsonPath('results.0.longitude', '-4.7794000')
            ->assertJsonPath('results.0.address.municipality', 'Córdoba')
            ->assertJsonPath('results.0.address.country_code', 'ES')
            ->assertJsonPath('results.0.requested_house_number', '1')
            ->assertJsonPath('results.0.house_number_match', true);

        Http::assertSent(fn ($request) => $request->hasHeader('User-Agent')
            && $request['street'] === '1 Calle Claudio Marcelo'
            && $request['postalcode'] === '14002'
            && $request['city'] === 'Córdoba'
            && $request['addressdetails'] === 1
            && $request['countrycodes'] === 'es'
        );
    }

    public function test_geocoding_requires_authentication()
    {
        $this->postJson(route('geocoding.search'), [
            'query' => 'Calle Claudio Marcelo 1, Córdoba',
        ])->assertUnauthorized();
    }

    public function test_authenticated_users_can_visit_edit_update_and_delete_installations()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $customer = Person::create([
            'category' => 'customer',
            'first_name' => 'Maria',
        ]);

        $installation = Installation::create([
            'person_id' => $customer->id,
            'service_type' => 'ev_charger',
            'status' => 'lead',
            'site_address_line_1' => 'Garage 1',
        ]);

        $this->get(route('installations.show', $installation))->assertOk();
        $this->get(route('installations.edit', $installation))->assertOk();

        $response = $this->patch(route('installations.update', $installation), [
            'person_id' => $customer->id,
            'service_type' => 'ev_charger',
            'status' => 'approved',
            'site_address_line_1' => 'Garage 1',
            'site_latitude' => '37.8882000',
            'site_longitude' => '-4.7794000',
            'charger_power_kw' => '7.40',
            'charger_connector_type' => 'Type 2',
        ]);

        $response->assertRedirect(route('installations.show', $installation));
        $this->assertDatabaseHas('installations', [
            'id' => $installation->id,
            'status' => 'approved',
            'charger_connector_type' => 'Type 2',
            'site_latitude' => '37.8882000',
            'site_longitude' => '-4.7794000',
        ]);

        $this->delete(route('installations.destroy', $installation))
            ->assertRedirect(route('people.show', $customer));

        $this->assertDatabaseMissing('installations', [
            'id' => $installation->id,
        ]);
    }

    public function test_installations_cannot_be_attached_to_staff()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $staff = Person::create([
            'category' => 'staff',
            'first_name' => 'Luis',
        ]);

        $response = $this->post(route('installations.store'), [
            'person_id' => $staff->id,
            'service_type' => 'ev_charger',
            'status' => 'lead',
            'site_address_line_1' => 'Garage 1',
        ]);

        $response->assertSessionHasErrors('person_id');
        $this->assertDatabaseCount('installations', 0);
    }

    public function test_authenticated_users_can_generate_ai_installation_briefings()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        InstallationAdvisor::fake([
            [
                'summary' => 'Briefing listo: faltan fotos de cubierta y confirmacion de acometida.',
                'health_assessment' => 'La instalacion no tiene incidencias de monitorizacion en los datos aportados.',
                'commercial_angle' => 'Conviene completar datos tecnicos antes de cerrar la propuesta.',
                'missing_information' => ['Fotos de cubierta'],
                'risks' => [],
                'next_actions' => ['Confirmar acometida'],
                'action_items' => [
                    [
                        'title' => 'Confirmar acometida',
                        'type' => 'task',
                        'priority' => 'high',
                        'reason' => 'Dato necesario antes de presupuestar.',
                        'due_in_days' => 2,
                    ],
                ],
                'confidence' => 0.9,
            ],
        ]);

        $customer = Person::create([
            'category' => 'customer',
            'customer_type' => 'business',
            'first_name' => 'Maria',
            'last_name' => 'Lopez',
            'company_name' => 'Lopez Logistics',
            'phone' => '+34 600 000 000',
            'email' => 'maria@example.com',
            'city' => 'Cordoba',
            'country' => 'Spain',
            'service_interests' => ['Solar installation'],
        ]);

        $installation = Installation::create([
            'person_id' => $customer->id,
            'service_type' => 'solar',
            'status' => 'survey_booked',
            'project_reference' => 'FV-2026-009',
            'site_address_line_1' => 'Poligono Industrial 8',
            'site_city' => 'Cordoba',
            'site_country' => 'Spain',
            'system_power_kw' => '32.00',
            'panel_count' => 58,
            'technical_notes' => 'Three-phase supply available.',
        ]);

        $this->postJson(route('installations.ai.briefing', $installation))
            ->assertStatus(202)
            ->assertJsonPath(
                'generation.result.summary',
                'Briefing listo: faltan fotos de cubierta y confirmacion de acometida.',
            );

        $this->assertDatabaseHas('ai_generations', [
            'installation_id' => $installation->id,
            'status' => 'completed',
            'prompt_version' => 'installation-operational-v1',
            'mode' => 'operational',
        ]);

        InstallationAdvisor::assertPrompted(
            fn ($prompt) => $prompt->contains('FV-2026-009')
                && $prompt->contains('solar')
                && $prompt->contains('monitoring')
                && $prompt->contains('operations')
                && ! $prompt->contains('Lopez Logistics')
                && ! $prompt->contains('Three-phase supply available.'),
        );
    }

    public function test_admins_can_visit_the_private_knowledge_library()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        KnowledgeDocument::create([
            'user_id' => $admin->id,
            'title' => 'Solar protocol',
            'source' => storage_path('app/private/knowledge/test.md'),
            'content_hash' => hash('sha256', 'Solar protocol'),
            'indexed_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('knowledge.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('knowledge/index')
                ->where('documents.0.title', 'Solar protocol')
                ->where('documents.0.user_name', $admin->name));
    }

    public function test_private_network_urls_cannot_be_added_as_arevia_sources()
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->post(route('knowledge.store'), [
                'title' => 'Internal service',
                'source_url' => 'http://127.0.0.1:11434/api/tags',
                'area' => 'general',
                'confidentiality' => 'internal',
            ])
            ->assertUnprocessable();

        $this->assertDatabaseMissing('knowledge_documents', ['title' => 'Internal service']);
    }

    public function test_quick_ai_summary_uses_the_short_snapshot_mode()
    {
        $user = User::factory()->create(['role' => 'commercial']);
        $this->actingAs($user);
        InstallationSnapshot::fake([[
            'summary' => 'Instalacion pendiente de visita.',
            'health_assessment' => 'Sin monitorizacion conectada.',
            'missing_information' => ['Fecha de visita'],
            'risks' => [],
            'next_actions' => ['Programar visita'],
            'confidence' => 0.8,
        ]]);
        $installation = Installation::create([
            'service_type' => 'solar',
            'status' => 'lead',
            'site_address_line_1' => 'Calle Sol 1',
        ]);

        $this->postJson(route('installations.ai.briefing', $installation), ['mode' => 'quick'])
            ->assertAccepted()
            ->assertJsonPath('generation.mode', 'quick')
            ->assertJsonPath('generation.result.summary', 'Instalacion pendiente de visita.');

        $this->assertDatabaseHas('ai_generations', [
            'installation_id' => $installation->id,
            'mode' => 'quick',
            'prompt_version' => 'installation-snapshot-v1',
        ]);
    }

    public function test_users_can_correct_an_ai_response_for_admin_review()
    {
        $user = User::factory()->create(['role' => 'commercial']);
        $installation = Installation::create([
            'service_type' => 'solar',
            'status' => 'lead',
            'site_address_line_1' => 'Calle Sol 2',
        ]);
        $generation = $installation->aiGenerations()->create([
            'user_id' => $user->id,
            'status' => 'completed',
            'mode' => 'operational',
            'structured_response' => [
                'summary' => 'Original summary',
                'health_assessment' => 'Unknown',
                'commercial_angle' => 'Review',
                'missing_information' => [],
                'risks' => ['Original risk'],
                'next_actions' => ['Original action'],
                'action_items' => [],
                'confidence' => 0.5,
            ],
        ]);

        $this->actingAs($user)
            ->postJson(route('installations.ai.briefing.feedback', [$installation, $generation->uuid]), [
                'rating' => 'needs_correction',
                'summary' => 'Validated summary',
                'risks' => "Roof access\nMissing permit",
                'next_actions' => 'Schedule site visit',
                'notes' => 'Reviewed with the technician.',
            ])
            ->assertCreated()
            ->assertJsonPath('feedback.rating', 'needs_correction');

        $this->assertDatabaseHas('ai_feedback', [
            'ai_generation_id' => $generation->id,
            'user_id' => $user->id,
            'rating' => 'needs_correction',
            'approved_at' => null,
        ]);

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)
            ->get(route('knowledge.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('pendingFeedback.0.corrected_response.summary', 'Validated summary')
                ->where('pendingFeedback.0.reviewer', $user->name));
    }

    public function test_arevia_chat_persists_conversation_and_feedback()
    {
        $user = User::factory()->create(['role' => 'commercial']);
        $this->actingAs($user);
        AreviaChat::fake(['La visita tecnica es el siguiente paso recomendado.']);

        $response = $this->postJson(route('arevia.messages.store'), [
            'content' => 'Cual es el siguiente paso?',
            'page_url' => '/installations/1',
        ])->assertAccepted()
            ->assertJsonPath('conversation.messages.0.role', 'user')
            ->assertJsonPath('conversation.messages.1.content', 'La visita tecnica es el siguiente paso recomendado.');

        $messageId = $response->json('conversation.messages.1.id');
        $this->postJson(route('arevia.messages.feedback', $messageId), ['rating' => 'helpful'])
            ->assertOk()
            ->assertJsonPath('message.rating', 'helpful');

        $this->getJson(route('arevia.conversation'))
            ->assertOk()
            ->assertJsonCount(2, 'conversation.messages');
        $this->assertDatabaseHas('ai_messages', ['id' => $messageId, 'rating' => 'helpful']);
    }

    public function test_ai_action_items_can_be_converted_to_crm_activities()
    {
        $user = User::factory()->create(['role' => 'commercial']);
        $this->actingAs($user);

        $installation = Installation::create([
            'service_type' => 'solar',
            'status' => 'lead',
            'project_reference' => 'FV-2026-010',
            'site_address_line_1' => 'Calle Solar 10',
        ]);

        $generation = $installation->aiGenerations()->create([
            'user_id' => $user->id,
            'status' => 'completed',
            'structured_response' => [
                'summary' => 'Briefing listo.',
                'health_assessment' => 'Sin monitorizacion conectada.',
                'commercial_angle' => 'Completar visita tecnica.',
                'missing_information' => [],
                'risks' => [],
                'next_actions' => [],
                'action_items' => [
                    [
                        'title' => 'Programar visita tecnica',
                        'type' => 'visit',
                        'priority' => 'normal',
                        'reason' => 'Hace falta revisar el emplazamiento.',
                        'due_in_days' => 4,
                    ],
                ],
                'confidence' => 0.8,
            ],
        ]);
        $proposal = app(AiActionProposalService::class)->proposeFor($generation)->firstOrFail();

        $this->postJson(route('installations.ai.briefing.activities', [$installation, $generation->uuid]), [
            'confirmed' => true,
            'proposal_uuids' => [$proposal->uuid],
        ])->assertCreated()
            ->assertJsonPath('created.0.title', 'Programar visita tecnica');

        $this->assertDatabaseHas('crm_activities', [
            'installation_id' => $installation->id,
            'assigned_user_id' => $user->id,
            'created_by' => $user->id,
            'type' => 'visit',
            'title' => 'Programar visita tecnica',
            'priority' => 'normal',
        ]);

        $this->postJson(route('installations.ai.briefing.activities', [$installation, $generation->uuid]), [
            'confirmed' => true,
            'proposal_uuids' => [$proposal->uuid],
        ])->assertCreated();
        $this->assertDatabaseCount('crm_activities', 1);
    }

    public function test_guest_users_cannot_create_people()
    {
        $response = $this->post(route('people.store'), [
            'category' => 'customer',
            'first_name' => 'Guest',
        ]);

        $response->assertRedirect(route('login'));
        $this->assertDatabaseCount('people', 0);
    }

    public function test_technicians_can_view_installations_but_cannot_manage_customers()
    {
        $technician = User::factory()->create(['role' => 'technician']);
        $this->actingAs($technician);

        $this->get(route('installations.index'))->assertOk();
        $this->get(route('people.index'))->assertForbidden();
        $this->get(route('installations.create'))->assertForbidden();
    }

    public function test_customer_changes_are_audited_with_the_authenticated_user()
    {
        $user = User::factory()->create();
        $customer = Person::create(['category' => 'customer', 'first_name' => 'Ana']);
        $this->actingAs($user);

        $this->patch(route('people.update', $customer), [
            'category' => 'customer',
            'first_name' => 'Ana Maria',
        ])->assertRedirect(route('people.show', $customer));

        $audit = AuditLog::where('auditable_type', Person::class)
            ->where('auditable_id', $customer->id)
            ->where('event', 'updated')
            ->firstOrFail();

        $this->assertSame($user->id, $audit->user_id);
        $this->assertSame('Ana', $audit->old_values['first_name']);
        $this->assertSame('Ana Maria', $audit->new_values['first_name']);
    }

    public function test_postal_code_lookup_returns_location_hierarchy()
    {
        $this->seed(LocationSeeder::class);

        $response = $this->getJson('/api/locations/postal-codes/14900');

        $response
            ->assertOk()
            ->assertJsonPath('code', '14900')
            ->assertJsonPath('localities.0.name', 'Lucena')
            ->assertJsonPath('localities.0.municipality.name', 'Lucena')
            ->assertJsonPath('localities.0.province.name', 'Córdoba')
            ->assertJsonPath('localities.0.country.code', 'ES');
    }

    public function test_location_catalogs_are_scoped_and_cached_for_edit_forms()
    {
        Cache::clear();
        $this->seed(LocationSeeder::class);
        $country = Country::where('code', 'ES')->firstOrFail();

        $countries = $this->getJson('/api/locations/countries')
            ->assertOk()
            ->assertJsonFragment(['code' => 'ES', 'name' => 'Spain']);
        $provinceId = $this->getJson("/api/locations/provinces?country_id={$country->id}")
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.name', 'Córdoba')
            ->json('0.id');
        $municipalityId = $this->getJson("/api/locations/municipalities?province_id={$provinceId}")
            ->assertOk()
            ->assertJsonFragment(['name' => 'Lucena'])
            ->json('0.id');
        $this->getJson("/api/locations/localities?municipality_id={$municipalityId}")
            ->assertOk()
            ->assertJsonStructure([['id', 'municipality_id', 'name']]);

        $countries->assertJsonPath('0.name', 'Spain');
        $this->assertTrue(Cache::has('locations:countries:v1'));
        $this->assertTrue(Cache::has("locations:provinces:{$country->id}:v1"));
    }
}

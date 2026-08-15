<?php

namespace Tests\Feature\Api;

use App\Models\Installation;
use App\Models\MobileOperation;
use App\Models\PermissionProfile;
use App\Models\Product;
use App\Models\StockReservation;
use App\Models\TaxRate;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WorkOrder;
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
        $other = User::factory()->create(['role' => 'technician']);
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

    public function test_technician_can_execute_assigned_work_order(): void
    {
        $technician = User::factory()->create(['role' => 'technician']);
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

    public function test_technician_can_start_and_stop_a_timer_created_offline(): void
    {
        $technician = User::factory()->create(['role' => 'technician']);
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
        $other = User::factory()->create(['role' => 'technician']);
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
        $other = User::factory()->create(['role' => 'technician']);
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
        $other = User::factory()->create(['role' => 'technician']);
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

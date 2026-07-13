<?php

namespace Tests\Feature\Api;

use App\Models\Installation;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Mockery;
use Tests\TestCase;

class MobileV1Test extends TestCase
{
    use RefreshDatabase;

    public function test_device_can_login_read_profile_and_revoke_its_token(): void
    {
        $user = User::factory()->create(['role' => 'technician', 'password' => 'secret-password']);

        $login = $this->postJson('/api/mobile/v1/auth/login', [
            'email' => $user->email,
            'password' => 'secret-password',
            'device_name' => 'iPhone test',
        ])->assertOk()->assertJsonPath('data.user.role', 'technician');

        $token = $login->json('data.token');
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

        $this->actingAs($technician, 'sanctum')
            ->getJson('/api/mobile/v1/work-orders')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $assigned->id);
        $this->actingAs($technician, 'sanctum')->getJson("/api/mobile/v1/work-orders/{$hidden->id}")->assertForbidden();
    }

    public function test_technician_can_execute_assigned_work_order(): void
    {
        $technician = User::factory()->create(['role' => 'technician']);
        $installation = Installation::create(['service_type' => 'solar', 'status' => 'scheduled', 'site_address_line_1' => 'Calle Sol 1']);
        $order = WorkOrder::create(['installation_id' => $installation->id, 'assigned_user_id' => $technician->id, 'number' => 'WO-1', 'title' => 'Assigned']);
        $item = $order->checklistItems()->create(['position' => 1, 'label' => 'Test item', 'is_required' => true]);

        $entryId = $this->actingAs($technician, 'sanctum')
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

    public function test_mutation_is_replayed_without_running_twice(): void
    {
        $technician = User::factory()->create(['role' => 'technician']);
        $installation = Installation::create(['service_type' => 'solar', 'status' => 'scheduled', 'site_address_line_1' => 'Calle Sol 1']);
        $order = WorkOrder::create(['installation_id' => $installation->id, 'assigned_user_id' => $technician->id, 'number' => 'WO-1', 'title' => 'Assigned']);
        $key = (string) Str::uuid();

        $this->actingAs($technician, 'sanctum')->withHeader('Idempotency-Key', $key)
            ->postJson("/api/mobile/v1/work-orders/{$order->id}/timer")->assertCreated();
        $this->app['auth']->forgetGuards();
        $this->actingAs($technician, 'sanctum')->withHeader('Idempotency-Key', $key)
            ->postJson("/api/mobile/v1/work-orders/{$order->id}/timer")
            ->assertCreated()->assertHeader('Idempotency-Replayed', 'true');

        $this->assertDatabaseCount('time_entries', 1);
        $this->assertDatabaseCount('mobile_operations', 1);
    }

    public function test_technician_can_queue_an_idempotent_photo_upload(): void
    {
        Storage::fake('local');
        $technician = User::factory()->create(['role' => 'technician']);
        $other = User::factory()->create(['role' => 'technician']);
        $installation = Installation::create(['service_type' => 'solar', 'status' => 'scheduled', 'site_address_line_1' => 'Calle Sol 1']);
        $order = WorkOrder::create(['installation_id' => $installation->id, 'assigned_user_id' => $technician->id, 'number' => 'WO-1', 'title' => 'Assigned']);
        $key = (string) Str::uuid();

        $this->actingAs($technician, 'sanctum')->withHeader('Idempotency-Key', $key)
            ->post("/api/mobile/v1/work-orders/{$order->id}/attachments", [
                'file' => UploadedFile::fake()->image('inverter.jpg', 1200, 900),
                'caption' => 'Instalación finalizada',
            ], ['Accept' => 'application/json'])
            ->assertCreated()->assertJsonPath('data.kind', 'photo');
        $attachment = $order->attachments()->firstOrFail();
        Storage::disk('local')->assertExists($attachment->path);

        $this->app['auth']->forgetGuards();
        $this->actingAs($technician, 'sanctum')->withHeader('Idempotency-Key', $key)
            ->post("/api/mobile/v1/work-orders/{$order->id}/attachments", ['file' => UploadedFile::fake()->image('duplicate.jpg')], ['Accept' => 'application/json'])
            ->assertCreated()->assertHeader('Idempotency-Replayed', 'true');
        $this->assertDatabaseCount('work_order_attachments', 1);

        $this->actingAs($other, 'sanctum')->withHeader('Idempotency-Key', (string) Str::uuid())
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

        $this->actingAs($technician, 'sanctum')->withHeader('Idempotency-Key', $key)
            ->post("/api/mobile/v1/work-orders/{$order->id}/signature", [
                'signed_by_name' => 'Ana Cliente',
                'signature' => UploadedFile::fake()->image('signature.png', 600, 240),
            ], ['Accept' => 'application/json'])
            ->assertCreated()->assertJsonPath('data.signed_by_name', 'Ana Cliente');
        $order->refresh();
        Storage::disk('local')->assertExists($order->signature_path);

        $this->app['auth']->forgetGuards();
        $this->actingAs($technician, 'sanctum')->withHeader('Idempotency-Key', $key)
            ->post("/api/mobile/v1/work-orders/{$order->id}/signature", [
                'signed_by_name' => 'Firma duplicada',
                'signature' => UploadedFile::fake()->image('duplicate.png'),
            ], ['Accept' => 'application/json'])
            ->assertCreated()->assertHeader('Idempotency-Replayed', 'true')->assertJsonPath('data.signed_by_name', 'Ana Cliente');
        $this->assertDatabaseCount('mobile_operations', 1);

        $this->actingAs($other, 'sanctum')->withHeader('Idempotency-Key', (string) Str::uuid())
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

        $this->actingAs($technician, 'sanctum')->withHeader('Idempotency-Key', $key)
            ->postJson("/api/mobile/v1/work-orders/{$order->id}/arrivals", $payload)
            ->assertCreated()->assertJsonPath('data.latitude', 40.4167754);
        $this->app['auth']->forgetGuards();
        $this->actingAs($technician, 'sanctum')->withHeader('Idempotency-Key', $key)
            ->postJson("/api/mobile/v1/work-orders/{$order->id}/arrivals", $payload)
            ->assertCreated()->assertHeader('Idempotency-Replayed', 'true');
        $this->assertDatabaseCount('work_order_visits', 1);

        $this->actingAs($other, 'sanctum')->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson("/api/mobile/v1/work-orders/{$order->id}/arrivals", $payload)
            ->assertForbidden();
        $this->actingAs($technician, 'sanctum')->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson("/api/mobile/v1/work-orders/{$order->id}/arrivals", [...$payload, 'latitude' => 91])
            ->assertUnprocessable()->assertJsonValidationErrors('latitude');
    }
}

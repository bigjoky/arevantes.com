<?php

namespace Tests\Feature\Api;

use App\Jobs\SendWorkOrderPush;
use App\Models\Installation;
use App\Models\MobileDevice;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\Push\ApnsClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MobilePushTest extends TestCase
{
    use RefreshDatabase;

    private function mobileAs(User $user): self
    {
        Sanctum::actingAs($user, ['mobile']);

        return $this;
    }

    public function test_user_can_register_update_and_revoke_their_ios_device(): void
    {
        $user = User::factory()->create(['role' => 'technician']);
        $deviceId = (string) Str::uuid();
        $token = str_repeat('ab', 32);

        $this->mobileAs($user)->putJson("/api/mobile/v1/devices/{$deviceId}", [
            'push_token' => strtoupper($token),
            'environment' => 'development',
            'app_version' => '1.0',
        ])->assertOk()->assertJsonPath('data.device_id', $deviceId);
        $this->assertDatabaseHas('mobile_devices', ['user_id' => $user->id, 'push_token' => $token, 'enabled' => true]);

        $this->deleteJson("/api/mobile/v1/devices/{$deviceId}")->assertNoContent();
        $this->assertDatabaseCount('mobile_devices', 0);
    }

    public function test_work_order_assignment_and_relevant_changes_dispatch_push_jobs(): void
    {
        config(['automation.approved.push' => true]);
        Queue::fake();
        $technician = User::factory()->create(['role' => 'technician']);
        $installation = Installation::create(['service_type' => 'solar', 'status' => 'scheduled', 'site_address_line_1' => 'Calle Sol 1']);
        $order = WorkOrder::create(['installation_id' => $installation->id, 'assigned_user_id' => $technician->id, 'number' => 'WO-1', 'title' => 'Assigned']);

        Queue::assertPushed(SendWorkOrderPush::class, fn ($job) => $job->userId === $technician->id && $job->title === 'Nueva orden asignada');
        $order->update(['status' => 'in_progress']);
        Queue::assertPushed(SendWorkOrderPush::class, 2);
        $order->update(['completion_notes' => 'No push for this field']);
        Queue::assertPushed(SendWorkOrderPush::class, 2);
    }

    public function test_apns_invalid_token_disables_device(): void
    {
        $user = User::factory()->create();
        $device = MobileDevice::create(['user_id' => $user->id, 'device_id' => (string) Str::uuid(), 'push_token' => str_repeat('cd', 32), 'environment' => 'development']);
        $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        openssl_pkey_export($key, $privateKey);
        config()->set('services.apns', ['key_id' => 'KEY123', 'team_id' => 'TEAM123', 'bundle_id' => 'com.arevantes.mobile', 'private_key' => $privateKey]);
        Http::fake(['api.sandbox.push.apple.com/*' => Http::response(['reason' => 'Unregistered'], 410)]);

        app(ApnsClient::class)->send($device, ['aps' => ['alert' => ['title' => 'Test', 'body' => 'Body']]]);

        $this->assertFalse($device->refresh()->enabled);
        Http::assertSent(fn ($request) => str_contains($request->url(), $device->push_token) && str_starts_with($request->header('Authorization')[0], 'Bearer '));
    }
}

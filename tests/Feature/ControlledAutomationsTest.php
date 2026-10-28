<?php

namespace Tests\Feature;

use App\Ai\Agents\AreviaChat;
use App\Exceptions\AutomationBlocked;
use App\Jobs\GenerateAreviaReply;
use App\Jobs\GeocodeInstallation;
use App\Jobs\SendWorkOrderPush;
use App\Models\AiAuditLog;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\AuditLog;
use App\Models\Installation;
use App\Models\MobileDevice;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\Ai\BusinessContextBuilder;
use App\Services\Ai\KnowledgeRetriever;
use App\Services\Ai\RelevantBusinessContext;
use App\Services\Geocoding\NominatimGeocoder;
use App\Services\Push\ApnsClient;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class ControlledAutomationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Http::preventStrayRequests();
        config(['automation.approved.push' => true, 'automation.approved.arevia' => true, 'automation.approved.geocode' => true, 'services.apns' => ['key_id' => 'SYN', 'team_id' => 'SYN', 'bundle_id' => 'SYN', 'private_key' => 'SYN']]);
    }

    private function order(): WorkOrder
    {
        $user = User::factory()->create(['role' => 'admin']);
        $installation = Installation::create(['service_type' => 'solar', 'status' => 'scheduled', 'site_address_line_1' => 'Synthetic address']);
        $order = WorkOrder::create(['installation_id' => $installation->id, 'assigned_user_id' => $user->id, 'number' => 'SYN-'.Str::random(5), 'title' => 'Synthetic task', 'status' => 'planned']);
        MobileDevice::create(['user_id' => $user->id, 'device_id' => (string) Str::uuid(), 'push_token' => str_repeat('a', 64), 'environment' => 'development', 'enabled' => true]);

        return $order;
    }

    private function push(WorkOrder $order): SendWorkOrderPush
    {
        return new SendWorkOrderPush($order->assigned_user_id, $order->id, 'Synthetic', 'Synthetic');
    }

    private function blocked(callable $run): void
    {
        try {
            $run();
            $this->fail('Unsafe execution was permitted.');
        } catch (AutomationBlocked) {
            $this->assertTrue(true);
        }
    }

    public function test_duplicate_delivery_and_retry_do_not_repeat_an_external_attempt(): void
    {
        $job = $this->push($this->order());
        $apns = $this->mock(ApnsClient::class);
        $apns->shouldReceive('send')->once();
        $job->handle($apns);
        (clone $job)->handle($apns);
        $this->assertSame(1, AuditLog::where('event', 'automation_effect_claimed')->count());
        $this->assertSame(1, AuditLog::where('event', 'automation_effect_completed')->count());
        $this->assertSame('ops-push', $job->queue);
    }

    public function test_ambiguous_provider_failure_keeps_the_claim_and_blocks_replay(): void
    {
        $job = $this->push($this->order());
        $apns = $this->mock(ApnsClient::class);
        $apns->shouldReceive('send')->once()->andThrow(new RuntimeException('Synthetic unknown acceptance'));
        $this->blocked(fn () => $job->handle($apns));
        $job->handle($apns);
        $this->assertSame(1, AuditLog::where('event', 'automation_effect_uncertain')->count());
        $this->assertSame(0, AuditLog::where('event', 'automation_effect_completed')->count());
    }

    public function test_closed_order_and_changed_assignee_cannot_send_old_notifications(): void
    {
        $order = $this->order();
        $job = $this->push($order);
        $order->update(['status' => 'completed']);
        $apns = $this->mock(ApnsClient::class);
        $apns->shouldNotReceive('send');
        $this->blocked(fn () => $job->handle($apns));
        $order->update(['status' => 'planned']);
        $job = $this->push($order);
        $order->update(['assigned_user_id' => User::factory()->create(['role' => 'admin'])->id]);
        $this->blocked(fn () => $job->handle($apns));
        $this->assertSame(0, AuditLog::where('event', 'automation_effect_claimed')->count());
    }

    public function test_fresh_completion_update_does_not_reopen_the_order(): void
    {
        $order = $this->order();
        $order->update(['status' => 'completed', 'completed_at' => now()]);
        $job = new SendWorkOrderPush($order->assigned_user_id, $order->id, 'Orden actualizada', 'Synthetic completed update');
        $apns = $this->mock(ApnsClient::class);
        $apns->shouldReceive('send')->once();
        $job->handle($apns);
        $this->assertSame('completed', $order->fresh()->status);
        $this->assertNotNull($order->fresh()->completed_at);
    }

    public function test_revoked_permission_and_missing_approval_prevent_external_calls(): void
    {
        $job = $this->push($this->order());
        $apns = $this->mock(ApnsClient::class);
        $apns->shouldNotReceive('send');
        config(['automation.approved.push' => false]);
        $this->blocked(fn () => $job->handle($apns));
        config(['automation.approved.push' => true]);
        Gate::define('manage-operations', fn () => false);
        $this->blocked(fn () => $job->handle($apns));
    }

    public function test_short_queue_reservation_cannot_start_a_long_external_attempt(): void
    {
        $job = $this->push($this->order());
        $apns = $this->mock(ApnsClient::class);
        $apns->shouldNotReceive('send');
        config(['queue.connections.database.retry_after' => 90]);
        $this->blocked(fn () => $job->handle($apns));
        $this->assertSame(0, AuditLog::where('event', 'automation_effect_claimed')->count());
    }

    public function test_expired_jobs_and_legacy_serialized_payloads_are_not_replayed(): void
    {
        $order = $this->order();
        $fresh = $this->push($order);
        $apns = $this->mock(ApnsClient::class);
        $apns->shouldNotReceive('send');
        $this->travel(11)->minutes();
        $this->blocked(fn () => $fresh->handle($apns));
        $fields = ['userId' => $order->assigned_user_id, 'workOrderId' => $order->id, 'title' => 'Synthetic', 'body' => 'Synthetic'];
        $serialized = 'O:'.strlen(SendWorkOrderPush::class).':"'.SendWorkOrderPush::class.'":4:{';
        foreach ($fields as $key => $value) {
            $serialized .= serialize($key).serialize($value);
        }
        $legacy = unserialize($serialized.'}', ['allowed_classes' => [SendWorkOrderPush::class]]);
        $this->assertNull($legacy->queuedAtEpoch);
        $this->assertNull($legacy->expectedSnapshot);
        $this->blocked(fn () => $legacy->handle($apns));
    }

    public function test_legacy_arevia_and_geocode_payloads_cannot_run_or_change_business_state(): void
    {
        AreviaChat::fake();
        $message = $this->reply();
        $installation = Installation::create(['service_type' => 'solar', 'status' => 'scheduled', 'site_address_line_1' => 'Synthetic']);
        $legacy = function (string $class, string $property, int $id) {
            $body = serialize($property).serialize($id);

            return unserialize('O:'.strlen($class).':"'.$class.'":1:{'.$body.'}', ['allowed_classes' => [$class]]);
        };
        $arevia = $legacy(GenerateAreviaReply::class, 'messageId', $message->id);
        $geocode = $legacy(GeocodeInstallation::class, 'installationId', $installation->id);
        $service = $this->mock(NominatimGeocoder::class);
        $service->shouldNotReceive('search');
        $this->blocked(fn () => app()->call([$arevia, 'handle']));
        $this->blocked(fn () => $geocode->handle($service));
        $this->assertSame('queued', $message->fresh()->status);
        $this->assertNull($installation->fresh()->site_latitude);
        AreviaChat::assertNeverPrompted();
    }

    private function reply(): AiMessage
    {
        $user = User::factory()->create(['role' => 'admin']);
        $conversation = AiConversation::create(['user_id' => $user->id]);
        $conversation->messages()->create(['role' => 'user', 'status' => 'completed', 'content' => 'Synthetic question']);

        return $conversation->messages()->create(['role' => 'assistant', 'status' => 'queued']);
    }

    public function test_arevia_runs_once_and_does_not_answer_a_later_question(): void
    {
        AreviaChat::fake(['Synthetic answer without sources.']);
        $this->mock(KnowledgeRetriever::class)->shouldReceive('search')->once()->andReturn([]);
        $this->mock(BusinessContextBuilder::class)->shouldReceive('build')->once()->andReturn('Synthetic authorized context');
        $this->mock(RelevantBusinessContext::class)->shouldReceive('search')->once()->andReturn('Synthetic authorized records');
        $message = $this->reply();
        $job = new GenerateAreviaReply($message->id);
        app()->call([$job, 'handle']);
        app()->call([$job, 'handle']);
        $this->assertSame('completed', $message->fresh()->status);
        $this->assertSame(1, AiAuditLog::where('event', 'response_generated')->count());
        $next = $this->reply();
        $old = new GenerateAreviaReply($next->id);
        $next->conversation->messages()->create(['role' => 'user', 'status' => 'completed', 'content' => 'Another synthetic question']);
        $this->blocked(fn () => app()->call([$old, 'handle']));
        $this->assertSame('queued', $next->fresh()->status);
        $this->assertSame(1, AiAuditLog::where('event', 'response_generated')->count());
    }

    public function test_interrupted_arevia_attempt_does_not_overwrite_completed_output(): void
    {
        $message = $this->reply();
        $job = new GenerateAreviaReply($message->id);
        $key = hash('sha256', 'arevia|'.AiMessage::class.'|'.$message->id.'|'.$job->expectedSnapshot);
        AuditLog::create(['event' => 'automation_effect_claimed', 'auditable_type' => AiMessage::class, 'auditable_id' => $message->id, 'new_values' => ['key' => $key]]);
        $message->update(['status' => 'processing']);
        $job->failed(new RuntimeException('Synthetic timeout'));
        $this->assertSame('failed', $message->fresh()->status);
        $message->update(['status' => 'completed', 'content' => 'Synthetic completed answer']);
        $job->failed(new RuntimeException('Synthetic late timeout'));
        $this->assertSame('completed', $message->fresh()->status);
    }

    public function test_geocoding_does_not_overwrite_a_changed_address_or_existing_zero_coordinates(): void
    {
        $installation = Installation::create(['service_type' => 'solar', 'status' => 'scheduled', 'site_address_line_1' => 'Synthetic original address']);
        $job = new GeocodeInstallation($installation->id);
        $geocoder = $this->mock(NominatimGeocoder::class);
        $geocoder->shouldReceive('search')->once()->andReturnUsing(function () use ($installation) {
            DB::table('installations')->where('id', $installation->id)->update(['site_address_line_1' => 'Synthetic changed address']);

            return [['latitude' => '40.0', 'longitude' => '-3.0', 'provider' => 'synthetic', 'place_id' => 'SYN', 'osm_type' => null, 'osm_id' => null, 'display_name' => 'Synthetic']];
        });
        $this->blocked(fn () => $job->handle($geocoder));
        $this->assertNull($installation->fresh()->site_latitude);
        $installation->update(['site_latitude' => 0, 'site_longitude' => 0]);
        $alreadyLocated = new GeocodeInstallation($installation->id);
        $this->assertNull($alreadyLocated->expectedSnapshot);
        $this->blocked(fn () => $alreadyLocated->handle($geocoder));
    }

    public function test_scheduler_defaults_to_no_automatic_business_events(): void
    {
        $this->assertSame('disabled', config('automation.scheduler_profile'));
        $this->assertCount(0, app(Schedule::class)->events());
    }
}

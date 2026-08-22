<?php

namespace Tests\Feature;

use App\Models\Opportunity;
use App\Models\Person;
use App\Models\User;
use App\Notifications\LeadResponseSlaBreachedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class LeadResponseSlaNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_is_notified_once_when_first_response_sla_is_breached(): void
    {
        Notification::fake();
        $owner = User::factory()->create(['role' => 'commercial']);
        $opportunity = $this->opportunity($owner, [
            'first_response_due_at' => now()->subMinute(),
        ]);

        $this->artisan('crm:check-response-sla')
            ->expectsOutput('1 lead response SLA alerts sent.')
            ->assertSuccessful();

        Notification::assertSentTo($owner, LeadResponseSlaBreachedNotification::class, function ($notification) use ($owner, $opportunity): bool {
            $data = $notification->toArray($owner);

            return $data['opportunity_id'] === $opportunity->id
                && str_contains($data['url'], 'attention=response_sla');
        });
        $this->assertNotNull($opportunity->refresh()->first_response_sla_alerted_at);

        $this->artisan('crm:check-response-sla')
            ->expectsOutput('0 lead response SLA alerts sent.')
            ->assertSuccessful();
        Notification::assertSentToTimes($owner, LeadResponseSlaBreachedNotification::class, 1);
    }

    public function test_future_answered_closed_and_unowned_opportunities_are_not_notified(): void
    {
        Notification::fake();
        $owner = User::factory()->create(['role' => 'commercial']);
        $this->opportunity($owner, ['first_response_due_at' => now()->addMinute()]);
        $this->opportunity($owner, [
            'first_response_due_at' => now()->subMinute(),
            'first_responded_at' => now(),
        ]);
        $this->opportunity($owner, [
            'first_response_due_at' => now()->subMinute(),
            'stage' => 'lost',
        ]);
        $this->opportunity(null, ['first_response_due_at' => now()->subMinute()]);

        $this->artisan('crm:check-response-sla')
            ->expectsOutput('0 lead response SLA alerts sent.')
            ->assertSuccessful();

        Notification::assertNothingSent();
    }

    private function opportunity(?User $owner, array $attributes = []): Opportunity
    {
        $customer = Person::create([
            'category' => 'customer',
            'first_name' => fake()->firstName(),
        ]);

        return Opportunity::create([
            'person_id' => $customer->id,
            'owner_user_id' => $owner?->id,
            'created_by' => $owner?->id,
            'title' => 'Lead SLA',
            'stage' => 'new',
            'probability' => 10,
            'estimated_value' => 1000,
            ...$attributes,
        ]);
    }
}

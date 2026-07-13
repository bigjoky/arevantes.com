<?php

namespace Tests\Feature;

use App\Models\CrmActivity;
use App\Models\Installation;
use App\Models\MonitoringAlert;
use App\Models\MonitoringReading;
use App\Models\Person;
use App\Models\Quote;
use App\Models\User;
use App\Services\Monitoring\MonitoringAlertManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationsAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_commercial_users_can_manage_activities_and_quotes(): void
    {
        $user = User::factory()->create(['role' => 'commercial']);
        $customer = Person::create(['category' => 'customer', 'first_name' => 'Ana']);
        $installation = Installation::create([
            'person_id' => $customer->id,
            'service_type' => 'solar',
            'status' => 'lead',
            'site_address_line_1' => 'Calle Solar',
        ]);
        $this->actingAs($user);

        $this->post(route('activities.store'), [
            'installation_id' => $installation->id,
            'type' => 'visit',
            'title' => 'Technical survey',
            'priority' => 'high',
            'starts_at' => now()->addDay()->format('Y-m-d H:i:s'),
        ])->assertRedirect();

        $activity = CrmActivity::firstOrFail();
        $this->patch(route('activities.update', $activity), ['status' => 'completed'])->assertRedirect();
        $this->assertNotNull($activity->refresh()->completed_at);

        $this->post(route('quotes.store'), [
            'person_id' => $installation->person_id,
            'installation_id' => $installation->id,
            'issue_date' => now()->toDateString(),
            'lines' => [[
                'description' => 'Solar installation',
                'quantity' => 1,
                'unit_price' => 12500,
                'discount_percent' => 0,
                'tax_percent' => 0,
            ]],
        ])->assertRedirect();
        $quote = Quote::firstOrFail();
        $this->patch(route('quotes.status.update', $quote), ['status' => 'accepted'])->assertRedirect();

        $this->assertSame('approved', $installation->refresh()->status);
        $this->assertSame('12500.00', $installation->estimated_budget);
    }

    public function test_only_administrators_can_manage_roles(): void
    {
        $technician = User::factory()->create(['role' => 'technician']);
        $commercial = User::factory()->create(['role' => 'commercial']);

        $this->actingAs($technician)->get(route('admin.index'))->assertForbidden();

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)
            ->patch(route('admin.users.update', $commercial), ['role' => 'technician'])
            ->assertRedirect();
        $this->assertSame('technician', $commercial->refresh()->role);
    }

    public function test_repeated_monitoring_failures_create_one_notification_and_recover(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $installation = Installation::create([
            'service_type' => 'solar',
            'status' => 'completed',
            'site_address_line_1' => 'Calle Solar',
        ]);
        $manager = app(MonitoringAlertManager::class);

        foreach ([1, 2] as $minute) {
            $reading = MonitoringReading::create([
                'installation_id' => $installation->id,
                'provider' => 'deye',
                'status' => 'disconnected',
                'status_message' => 'Station offline.',
                'recorded_at' => now()->addMinutes($minute),
            ]);
            $manager->record($reading);
        }

        $alert = MonitoringAlert::firstOrFail();
        $this->assertSame(2, $alert->occurrences);
        $this->assertNotNull($alert->notified_at);
        $this->assertCount(1, $admin->refresh()->notifications);

        $healthy = MonitoringReading::create([
            'installation_id' => $installation->id,
            'provider' => 'deye',
            'status' => 'healthy',
            'recorded_at' => now()->addMinutes(3),
        ]);
        $manager->record($healthy);
        $this->assertNotNull($alert->refresh()->resolved_at);
    }
}

<?php

namespace Tests\Feature;

use App\Models\CrmActivity;
use App\Models\Installation;
use App\Models\MaintenancePlan;
use App\Models\MonitoringAlert;
use App\Models\MonitoringReading;
use App\Models\Opportunity;
use App\Models\Person;
use App\Models\Quote;
use App\Models\ServiceTicket;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\Monitoring\MonitoringAlertManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
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

    public function test_operations_preserves_the_next_action_for_active_opportunities(): void
    {
        $commercial = User::factory()->create(['role' => 'commercial']);
        $customer = Person::create(['category' => 'customer', 'first_name' => 'Ana']);
        $opportunity = Opportunity::create([
            'person_id' => $customer->id,
            'owner_user_id' => $commercial->id,
            'created_by' => $commercial->id,
            'code' => 'OPP-CONTINUITY-1',
            'title' => 'Seguimiento continuo',
            'stage' => 'qualified',
            'probability' => 25,
            'estimated_value' => 5000,
            'expected_close_on' => today()->addMonth(),
        ]);
        $activity = CrmActivity::create([
            'opportunity_id' => $opportunity->id,
            'person_id' => $customer->id,
            'assigned_user_id' => $commercial->id,
            'created_by' => $commercial->id,
            'type' => 'call',
            'title' => 'Llamar al cliente',
            'priority' => 'normal',
            'status' => 'pending',
            'due_at' => now()->addDay(),
        ]);

        $this->actingAs($commercial)
            ->patch(route('activities.update', $activity), ['status' => 'completed'])
            ->assertRedirect();

        $replacement = $opportunity->activities()->whereIn('status', ['pending', 'in_progress'])->firstOrFail();
        $this->assertNotSame($activity->id, $replacement->id);
        $this->assertSame('completed', $activity->fresh()->status);
        $this->assertSame('Review opportunity and define next step', $replacement->title);

        $this->delete(route('activities.destroy', $replacement))->assertRedirect();
        $this->assertSame(1, $opportunity->activities()->whereIn('status', ['pending', 'in_progress'])->count());

        $closed = Opportunity::create([
            'person_id' => $customer->id,
            'owner_user_id' => $commercial->id,
            'created_by' => $commercial->id,
            'code' => 'OPP-CLOSED-1',
            'title' => 'Oportunidad cerrada',
            'stage' => 'won',
            'probability' => 100,
            'estimated_value' => 5000,
        ]);
        $this->post(route('activities.store'), [
            'opportunity_id' => $closed->id,
            'type' => 'call',
            'title' => 'No debe crearse',
            'priority' => 'normal',
        ])->assertUnprocessable();
        $this->assertDatabaseMissing('crm_activities', ['title' => 'No debe crearse']);
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

    public function test_technicians_cannot_change_or_delete_another_users_activity(): void
    {
        $technician = User::factory()->create(['role' => 'technician']);
        $other = User::factory()->create(['role' => 'technician']);
        $activity = CrmActivity::create([
            'assigned_user_id' => $other->id,
            'created_by' => $other->id,
            'type' => 'task',
            'title' => 'Private assignment',
            'priority' => 'normal',
            'status' => 'pending',
        ]);

        $this->actingAs($technician)
            ->patch(route('activities.update', $activity), ['status' => 'completed'])
            ->assertForbidden();
        $this->delete(route('activities.destroy', $activity))->assertForbidden();
        $this->assertSame('pending', $activity->refresh()->status);
    }

    public function test_technician_created_activities_are_always_assigned_to_them(): void
    {
        $technician = User::factory()->create(['role' => 'technician']);
        $other = User::factory()->create(['role' => 'technician']);

        $this->actingAs($technician)->post(route('activities.store'), [
            'assigned_user_id' => $other->id,
            'type' => 'task',
            'title' => 'My follow-up',
            'priority' => 'high',
            'status' => 'completed',
        ])->assertRedirect();

        $activity = CrmActivity::firstOrFail();
        $this->assertSame($technician->id, $activity->assigned_user_id);
        $this->assertSame('pending', $activity->status);
    }

    public function test_operations_supports_filters_metrics_and_blocked_work(): void
    {
        $commercial = User::factory()->create(['role' => 'commercial']);
        CrmActivity::create(['title' => 'Urgent inverter review', 'type' => 'task', 'priority' => 'high', 'status' => 'blocked', 'blocked_reason' => 'Waiting for access', 'due_at' => now()->subHour()]);
        CrmActivity::create(['title' => 'Routine call', 'type' => 'call', 'priority' => 'normal', 'status' => 'pending']);

        $this->actingAs($commercial)->get(route('operations.index', ['q' => 'inverter', 'status' => 'blocked', 'view' => 'board']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('operations/index')
                ->where('filters.view', 'board')
                ->where('metrics.blocked', 1)
                ->where('metrics.overdue', 1)
                ->has('activities.data', 1)
                ->where('activities.data.0.blocked_reason', 'Waiting for access'));
    }

    public function test_planning_reports_when_the_unscheduled_queue_is_truncated(): void
    {
        $commercial = User::factory()->create(['role' => 'commercial']);
        $installation = Installation::create(['service_type' => 'solar', 'status' => 'scheduled', 'site_address_line_1' => 'Calle Solar 8']);
        foreach (range(1, 51) as $index) {
            WorkOrder::create([
                'installation_id' => $installation->id,
                'number' => sprintf('WO-QUEUE-%03d', $index),
                'title' => "Queued work {$index}",
            ]);
        }

        $this->actingAs($commercial)
            ->get(route('operations.index', ['view' => 'week', 'week' => '2026-08-10']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('planningOrders', 50)
                ->where('planningMeta.unscheduled_total', 51)
                ->where('planningMeta.unscheduled_loaded', 50)
                ->where('planningMeta.truncated', true));
    }

    public function test_commercial_user_can_convert_an_activity_to_a_work_order(): void
    {
        $commercial = User::factory()->create(['role' => 'commercial']);
        $technician = User::factory()->create(['role' => 'technician']);
        $installation = Installation::create(['service_type' => 'solar', 'status' => 'approved', 'site_address_line_1' => 'Calle Solar 8']);
        $activity = CrmActivity::create([
            'installation_id' => $installation->id,
            'assigned_user_id' => $technician->id,
            'created_by' => $commercial->id,
            'title' => 'Install solar array',
            'notes' => 'Roof access at the rear.',
            'type' => 'visit',
            'priority' => 'high',
            'status' => 'pending',
            'starts_at' => now()->addDay()->setTime(9, 0),
            'due_at' => now()->addDay()->setTime(13, 0),
        ]);

        $this->actingAs($commercial)->post(route('activities.work-order.store', $activity), [
            'kind' => 'installation',
            'scheduled_end' => now()->addDay()->setTime(13, 0)->format('Y-m-d H:i:s'),
        ])->assertRedirect();

        $order = WorkOrder::firstOrFail();
        $this->assertSame($order->id, $activity->refresh()->work_order_id);
        $this->assertSame($technician->id, $order->assigned_user_id);
        $this->assertSame('Roof access at the rear.', $order->instructions);
        $this->assertGreaterThan(0, $order->checklistItems()->count());
        $this->assertSame('scheduled', $installation->refresh()->status);
    }

    public function test_operations_surfaces_after_sales_and_maintenance_exceptions(): void
    {
        $commercial = User::factory()->create(['role' => 'commercial']);
        $installation = Installation::create(['service_type' => 'solar', 'status' => 'completed', 'site_address_line_1' => 'Calle Solar 10']);
        ServiceTicket::create(['installation_id' => $installation->id, 'created_by' => $commercial->id, 'number' => 'SAT-2026-00999', 'kind' => 'incident', 'priority' => 'high', 'status' => 'open', 'title' => 'Inverter offline']);
        MaintenancePlan::create(['installation_id' => $installation->id, 'name' => 'Annual inspection', 'interval_months' => 12, 'starts_on' => today(), 'next_due_on' => today()->addDays(10), 'is_active' => true]);

        $this->actingAs($commercial)->get(route('operations.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('exceptions', 2)
                ->where('exceptions.0.kind', 'incident')
                ->where('exceptions.1.kind', 'maintenance'));
    }
}

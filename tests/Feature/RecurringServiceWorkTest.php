<?php

namespace Tests\Feature;

use App\Models\CustomerPortalAccess;
use App\Models\CustomerPortalPreference;
use App\Models\Installation;
use App\Models\Person;
use App\Models\ServiceCycleCommunication;
use App\Models\ServiceCycleScheduleRevision;
use App\Models\ServiceTicket;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderTemplate;
use App\Services\AfterSales\MaintenancePlanLifecycle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RecurringServiceWorkTest extends TestCase
{
    use RefreshDatabase;

    public function test_approved_cycle_materializes_once_and_only_confirmed_schedule_reaches_mobile_and_portal(): void
    {
        $this->travelTo('2026-08-28 08:00:00');
        $admin = User::factory()->create(['role' => 'admin']);
        $technician = User::factory()->create(['role' => 'technician']);
        $customer = Person::create([
            'category' => 'customer', 'first_name' => 'Lucía', 'email' => 'lucia@example.test',
            'phone' => '600111222', 'preferred_contact_channel' => 'email',
        ]);
        CustomerPortalPreference::create(['person_id' => $customer->id, 'email_documents' => true, 'email_tickets' => true, 'email_maintenance' => true]);
        $installation = Installation::create([
            'person_id' => $customer->id, 'technician_user_id' => $technician->id,
            'service_type' => 'security', 'status' => 'completed', 'project_reference' => 'SEG-MAT-1',
            'site_address_line_1' => 'Calle Planificada 1', 'site_postal_code' => '41001',
        ]);
        $template = WorkOrderTemplate::create([
            'name' => 'Frozen security visit', 'service_type' => 'security', 'kind' => 'maintenance',
            'title' => 'Frozen maintenance', 'estimated_duration_minutes' => 75,
            'required_skills' => [], 'checklist' => [['label' => 'Frozen checklist item', 'is_required' => true]],
            'required_evidence' => [['key' => 'panel', 'label' => 'Panel photo', 'kind' => 'photo', 'required' => true]],
            'handover_documents' => [], 'allowed_transitions' => [], 'is_active' => true, 'is_system' => false,
        ]);
        $plan = app(MaintenancePlanLifecycle::class)->create([
            'installation_id' => $installation->id, 'work_order_template_id' => $template->id,
            'assigned_user_id' => $technician->id, 'name' => 'Security care', 'service_level' => 'standard',
            'coverage_type' => 'preventive', 'interval_months' => 3, 'starts_on' => today(),
            'next_due_on' => today()->addWeek(), 'is_active' => true, 'scope' => 'Inspect and verify the alarm system.',
        ], [], $admin);

        $this->actingAs($admin)->post(route('after-sales.plans.commitments.store', $plan), [
            'plan_version' => 1, 'planned_travel_km' => 5, 'approval_note' => 'Capacity and frozen technical scope reviewed.',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $commitment = $plan->cycleCommitments()->sole();
        $this->assertSame('Frozen checklist item', $commitment->technical_snapshot['template']['checklist'][0]['label']);
        $template->update(['title' => 'Changed later', 'checklist' => ['Changed later']]);

        $payload = [
            'scheduled_start' => today()->addWeek()->setTime(9, 0)->toDateTimeString(),
            'planning_note' => 'Technician and approved visit window reviewed.',
        ];
        $this->post(route('after-sales.commitments.materialize', $commitment), $payload)->assertRedirect()->assertSessionHasNoErrors();
        $this->post(route('after-sales.commitments.materialize', $commitment), $payload)->assertRedirect()->assertSessionHasNoErrors();

        $order = WorkOrder::with('checklistItems')->sole();
        $this->assertSame('Frozen maintenance', $order->title);
        $this->assertSame('Frozen checklist item', $order->checklistItems->sole()->label);
        $this->assertSame(1, ServiceTicket::count());
        $this->assertSame(1, ServiceCycleScheduleRevision::count());
        $this->assertSame('pending_confirmation', $commitment->refresh()->schedule_status);

        Sanctum::actingAs($technician, ['mobile']);
        $this->getJson('/api/mobile/v1/work-orders')->assertOk()->assertJsonCount(0, 'data');
        $this->actingAs($admin)->post(route('after-sales.commitments.schedule.confirm', $commitment))
            ->assertRedirect()->assertSessionHasNoErrors();

        Sanctum::actingAs($technician, ['mobile']);
        $this->getJson('/api/mobile/v1/work-orders')->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.service_cycle.plan_version', 1)
            ->assertJsonPath('data.0.service_cycle.schedule_status', 'confirmed')
            ->assertJsonMissingPath('data.0.service_cycle.capacity_snapshot')
            ->assertJsonMissingPath('data.0.service_cycle.planned_revenue');

        $token = 'confirmed-service-schedule';
        CustomerPortalAccess::create(['person_id' => $customer->id, 'recipient_email' => $customer->email, 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addMonth()]);
        $this->get(route('customer-portal.show', $token))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('serviceSchedules', 1)
                ->where('serviceSchedules.0.status', 'confirmed')
                ->where('serviceSchedules.0.plan', 'Security care'));

        $notice = [
            'type' => 'maintenance_notice', 'subject' => 'Confirmed security visit',
            'message' => 'The service date has been reviewed.', 'scheduled_for' => $order->scheduled_start->toDateTimeString(),
        ];
        $this->actingAs($admin)->post(route('after-sales.commitments.communications.store', $commitment), $notice)->assertRedirect();
        $this->post(route('after-sales.commitments.communications.confirm', [$commitment, ServiceCycleCommunication::sole()]))->assertRedirect();

        $newStart = today()->addDays(9)->setTime(11, 0);
        $this->patch(route('work-orders.schedule.update', $order), [
            'scheduled_start' => $newStart->toDateTimeString(),
            'scheduled_end' => $newStart->copy()->addMinutes(75)->toDateTimeString(),
            'reschedule_reason' => 'Customer requested a different confirmed window.',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('revision_required', $commitment->refresh()->schedule_status);
        $this->assertSame(2, ServiceCycleScheduleRevision::count());
        $this->assertSame(2, ServiceCycleCommunication::count());
        $this->assertSame('draft', ServiceCycleCommunication::latest('revision')->firstOrFail()->status);
        Sanctum::actingAs($technician, ['mobile']);
        $this->getJson('/api/mobile/v1/work-orders')->assertOk()->assertJsonCount(0, 'data');

        $this->actingAs($admin)->post(route('after-sales.commitments.schedule.confirm', $commitment))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('superseded', ServiceCycleScheduleRevision::where('version', 1)->sole()->status);
        $this->assertSame('confirmed', ServiceCycleScheduleRevision::where('version', 2)->sole()->status);
        Sanctum::actingAs($technician, ['mobile']);
        $this->getJson('/api/mobile/v1/work-orders')->assertOk()->assertJsonCount(1, 'data');
    }
}

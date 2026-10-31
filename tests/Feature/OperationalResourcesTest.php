<?php

namespace Tests\Feature;

use App\Models\CrmActivity;
use App\Models\FieldServiceProfile;
use App\Models\HrCalendarHoliday;
use App\Models\HrLeaveRequest;
use App\Models\Installation;
use App\Models\OperationalVehicle;
use App\Models\Person;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\Work\VehicleAvailability;
use App\Services\Work\WorkOrderRescheduler;
use App\Services\Work\WorkOrderStateManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class OperationalResourcesTest extends TestCase
{
    use RefreshDatabase;

    private function vehicle(User $actor): OperationalVehicle
    {
        return OperationalVehicle::create(['registration' => 'SYN-001', 'registration_key' => 'SYN001', 'name' => 'Synthetic vehicle', 'status' => 'available', 'created_by' => $actor->id]);
    }

    private function order(?User $technician = null, array $values = []): WorkOrder
    {
        $installation = Installation::create(['service_type' => 'electrical', 'status' => 'approved', 'site_address_line_1' => 'Synthetic']);

        return WorkOrder::create([...['installation_id' => $installation->id, 'number' => 'SYN-'.str()->uuid(), 'title' => 'Synthetic work', 'kind' => 'maintenance', 'status' => 'planned', 'assigned_user_id' => $technician?->id], ...$values]);
    }

    public function test_readiness_does_not_mark_a_required_or_unavailable_vehicle_ready(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $vehicle = $this->vehicle($admin);
        $order = $this->order(null, ['vehicle_required' => true]);
        $readiness = app(\App\Services\Work\WorkOrderPlanningReadiness::class);
        $this->assertFalse($readiness->for($order)['vehicle_ready']);
        $this->assertSame('warning', $readiness->for($order)['status']);
        $vehicle->update(['status' => 'maintenance']);
        $order->update(['vehicle_id' => $vehicle->id, 'scheduled_start' => '2026-11-02 09:00', 'scheduled_end' => '2026-11-02 10:00']);
        $result = $readiness->for($order->fresh());
        $this->assertSame('unavailable', $result['vehicle_status']);
        $this->assertSame('blocked', $result['status']);
    }

    public function test_vehicle_assignment_conflicts_replanning_and_history(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $vehicle = $this->vehicle($admin);
        $a = $this->order();
        $b = $this->order();
        $service = app(WorkOrderRescheduler::class);
        $change = ['vehicle_id' => $vehicle->id, 'vehicle_required' => true, 'scheduled_start' => '2026-11-02 09:00', 'scheduled_end' => '2026-11-02 10:00'];
        $service->reschedule($a, $change, 'Synthetic', $admin->id);
        $this->assertSame($vehicle->id, $a->fresh()->vehicle_id);
        $this->assertSame(1, $a->vehicleHistory()->count());
        $service->reschedule($a, $change, 'Repeated', $admin->id);
        $this->assertSame(1, $a->vehicleHistory()->count());
        try {
            $service->reschedule($b, $change, 'Conflict', $admin->id);
            $this->fail('Overlap must be rejected.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('vehicle_id', $e->errors());
        }
        $this->assertNull($b->fresh()->vehicle_id);
        $service->reschedule($a, [...$change, 'scheduled_start' => '2026-11-02 11:00', 'scheduled_end' => '2026-11-02 12:00'], 'Replanned', $admin->id);
        $this->assertSame(2, $a->vehicleHistory()->count());
        $this->assertSame('Replanned', $a->vehicleHistory()->first()->reason);
        $service->reschedule($b, $change, 'Now available', $admin->id);
        $this->assertSame($vehicle->id, $b->fresh()->vehicle_id);
    }

    public function test_required_missing_incomplete_maintenance_and_blocks_are_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $vehicle = $this->vehicle($admin);
        $service = app(VehicleAvailability::class);
        $this->assertSame('unavailable', $service->decision(null, null, null, required: true)['status']);
        $this->assertSame('unknown', $service->decision($vehicle->id, null, null)['status']);
        $vehicle->update(['status' => 'maintenance']);
        $this->assertSame('unavailable', $service->decision($vehicle->id, '2026-11-02 09:00', '2026-11-02 10:00')['status']);
        $vehicle->update(['status' => 'out_of_service']);
        $this->assertSame('unavailable', $service->decision($vehicle->id, '2026-11-02 09:00', '2026-11-02 10:00')['status']);
        $vehicle->update(['status' => 'available']);
        $this->actingAs($admin)->post(route('operations.vehicles.blocks', $vehicle), ['starts_at' => '2026-11-02 09:00', 'ends_at' => '2026-11-02 10:00', 'reason' => 'Synthetic maintenance'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('unavailable', $service->decision($vehicle->id, '2026-11-02 09:30', '2026-11-02 10:30')['status']);
        $block = $vehicle->blocks()->firstOrFail();
        $this->patch(route('operations.vehicles.blocks.cancel', [$vehicle, $block]), ['reason' => 'Synthetic correction'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertNotNull($block->fresh()->cancelled_at_utc);
        $this->assertSame('available', $service->decision($vehicle->id, '2026-11-02 09:30', '2026-11-02 10:30')['status']);
    }

    public function test_normalized_registration_is_unique_and_permissions_are_enforced(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $technician = User::factory()->create(['role' => 'technician']);
        $this->actingAs($admin)->post(route('operations.vehicles.store'), ['registration' => '1234 ABC', 'name' => 'Synthetic', 'status' => 'available'])->assertRedirect()->assertSessionHasNoErrors();
        $this->post(route('operations.vehicles.store'), ['registration' => '1234-ABC', 'name' => 'Duplicate', 'status' => 'available'])->assertSessionHasErrors('registration_key');
        $this->actingAs($technician)->post(route('operations.vehicles.store'), ['registration' => 'OTHER', 'name' => 'Forbidden', 'status' => 'available'])->assertForbidden();
        $this->get(route('operations.calendar', $admin))->assertForbidden();
    }

    public function test_individual_calendar_shares_events_and_masks_leave_details(): void
    {
        $person = Person::create(['category' => 'staff', 'first_name' => 'Synthetic']);
        $tech = User::factory()->create(['role' => 'technician', 'person_id' => $person->id]);
        FieldServiceProfile::create(['user_id' => $tech->id, 'daily_capacity_minutes' => 480, 'is_available' => true, 'weekly_schedule' => ['1' => [['start' => '08:00', 'end' => '16:00']]]]);
        $order = $this->order($tech, ['scheduled_start' => '2026-11-02 09:00', 'scheduled_end' => '2026-11-02 10:00']);
        CrmActivity::create(['title' => 'Linked synthetic', 'type' => 'visit', 'status' => 'pending', 'work_order_id' => $order->id, 'assigned_user_id' => $tech->id, 'starts_at' => $order->scheduled_start, 'due_at' => $order->scheduled_end]);
        HrLeaveRequest::create(['person_id' => $person->id, 'type' => 'vacation', 'start_date' => '2026-11-02', 'end_date' => '2026-11-02', 'requested_days' => 1, 'status' => 'approved', 'notes' => 'PRIVATE SYNTHETIC REASON']);
        $this->actingAs($tech)->get(route('operations.calendar', [$tech, 'week' => '2026-11-02', 'view' => 'day']))->assertOk()->assertInertia(fn (Assert $p) => $p->has('orders', 1)->has('activities', 0)->where('calendarDays.0.available_minutes', 0)->where('calendarDays.0.occupied_minutes', 60)->where('calendarDays.0.conflict', true)->missing('calendarDays.0.notes'));
        $this->get(route('operations.calendar', [$tech, 'week' => '2026-11-02', 'view' => 'week']))->assertInertia(fn (Assert $p) => $p->has('calendarDays', 7));
        HrCalendarHoliday::create(['holiday_date' => '2026-11-02', 'name' => 'Synthetic holiday']);
        $this->get(route('operations.calendar', [$tech, 'week' => '2026-11-02', 'view' => 'day']))->assertInertia(fn (Assert $p) => $p->where('calendarDays.0.holiday', 'Synthetic holiday'));
    }

    public function test_standalone_visit_reserves_vehicle_and_conversion_reuses_the_same_intervention(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $vehicle = $this->vehicle($admin);
        $installation = Installation::create(['service_type' => 'electrical', 'status' => 'approved', 'site_address_line_1' => 'Synthetic']);
        $payload = ['installation_id' => $installation->id, 'vehicle_id' => $vehicle->id, 'vehicle_required' => true,
            'type' => 'visit', 'title' => 'Synthetic visit', 'priority' => 'normal', 'status' => 'pending',
            'starts_at' => '2026-11-02T09:00:00+01:00', 'due_at' => '2026-11-02T10:00:00+01:00'];
        $this->actingAs($admin)->post(route('activities.store'), $payload)->assertRedirect()->assertSessionHasNoErrors();
        $activity = CrmActivity::where('title', 'Synthetic visit')->firstOrFail();
        $this->assertSame(1, $activity->vehicleHistory()->count());
        $this->getJson(route('operations.vehicles.availability', ['starts_at' => '2026-11-02 09:00', 'ends_at' => '2026-11-02 10:00']))->assertOk()->assertJsonPath('vehicles.0.status', 'unavailable');
        $this->getJson(route('operations.vehicles.availability', ['starts_at' => '2026-11-02 09:00', 'ends_at' => '2026-11-02 10:00', 'except_activity_id' => $activity->id]))->assertOk()->assertJsonPath('vehicles.0.status', 'available');
        $this->patch(route('activities.update', $activity), ['vehicle_id' => $vehicle->id, 'starts_at' => '2026-11-02 11:00', 'due_at' => '2026-11-02 12:00'])->assertSessionHasErrors('vehicle_change_reason');
        $this->patch(route('activities.update', $activity), ['vehicle_id' => $vehicle->id, 'starts_at' => '2026-11-02 11:00', 'due_at' => '2026-11-02 12:00', 'vehicle_change_reason' => 'Synthetic reschedule'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(2, $activity->vehicleHistory()->count());
        $this->getJson(route('operations.activities.vehicle-history', $activity))->assertOk()->assertJsonCount(2, 'history');
        $this->post(route('activities.work-order.store', $activity), ['kind' => 'maintenance'])->assertRedirect()->assertSessionHasNoErrors();
        $order = $activity->fresh()->workOrder;
        $this->assertNotNull($order);
        $this->assertSame($vehicle->id, $order->vehicle_id);
        $this->assertSame('unavailable', app(VehicleAvailability::class)->decision($vehicle->id, '2026-11-02 11:00', '2026-11-02 12:00')['status']);
        $this->assertSame('available', app(VehicleAvailability::class)->decision($vehicle->id, '2026-11-02 11:00', '2026-11-02 12:00', $order->id)['status']);
    }

    public function test_vehicle_with_unlinked_technician_is_unknown_but_legacy_no_vehicle_remains_available(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $technician = User::factory()->create(['role' => 'technician']);
        FieldServiceProfile::create(['user_id' => $technician->id, 'daily_capacity_minutes' => 480, 'is_available' => true, 'weekly_schedule' => ['1' => [['start' => '08:00', 'end' => '16:00']]]]);
        $vehicle = $this->vehicle($admin);
        $order = $this->order($technician);
        $legacy = $this->actingAs($admin)->getJson(route('operations.planning.availability', [$order, 'scheduled_start' => '2026-11-02 09:00', 'scheduled_end' => '2026-11-02 10:00']))->assertOk();
        $this->assertSame('available', collect($legacy->json('technicians'))->firstWhere('id', $technician->id)['status']);
        $response = $this->getJson(route('operations.planning.availability', [$order, 'scheduled_start' => '2026-11-02 09:00', 'scheduled_end' => '2026-11-02 10:00', 'vehicle_id' => $vehicle->id]))->assertOk();
        $tech = collect($response->json('technicians'))->firstWhere('id', $technician->id);
        $this->assertSame('unknown', $tech['status']);
        try {
            app(WorkOrderRescheduler::class)->reschedule($order, ['vehicle_id' => $vehicle->id, 'scheduled_start' => '2026-11-02 09:00', 'scheduled_end' => '2026-11-02 10:00'], 'Synthetic', $admin->id);
            $this->fail('Staff record is required for a confirmed vehicle assignment.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('assigned_user_id', $e->errors());
        }
        $this->assertNull($order->fresh()->vehicle_id);
        $this->assertSame(0, $order->vehicleHistory()->count());
    }

    public function test_reopening_a_cancelled_order_cannot_reuse_a_occupied_vehicle(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $vehicle = $this->vehicle($admin);
        $data = ['vehicle_id' => $vehicle->id, 'scheduled_start' => '2026-11-02 09:00', 'scheduled_end' => '2026-11-02 10:00'];
        $this->order(values: $data);
        $cancelled = $this->order(values: [...$data, 'status' => 'cancelled']);
        try {
            app(WorkOrderStateManager::class)->transition($cancelled, 'planned', actorId: $admin->id);
            $this->fail('Reopening must recheck the vehicle.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('vehicle_id', $e->errors());
        }
        $this->assertSame('cancelled', $cancelled->fresh()->status);
    }

    public function test_vehicle_distinguishes_the_two_autumn_clock_folds(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $vehicle = $this->vehicle($admin);
        $this->order(values: ['vehicle_id' => $vehicle->id, 'scheduled_start' => '2026-10-25T02:10:00+02:00', 'scheduled_end' => '2026-10-25T02:50:00+02:00']);
        $decision = app(VehicleAvailability::class)->decision($vehicle->id, '2026-10-25T02:10:00+01:00', '2026-10-25T02:50:00+01:00');
        $this->assertSame('available', $decision['status']);
    }

    public function test_vehicle_status_change_requires_explicit_confirmation_of_scheduled_work(): void
    {
        $this->travelTo(Carbon::parse('2026-11-01 12:00:00', 'Europe/Madrid'));
        $admin = User::factory()->create(['role' => 'admin']);
        $vehicle = $this->vehicle($admin);
        $this->order(values: ['vehicle_id' => $vehicle->id, 'scheduled_start' => '2026-11-02 09:00', 'scheduled_end' => '2026-11-02 10:00']);
        $payload = ['registration' => $vehicle->registration, 'name' => $vehicle->name, 'status' => 'maintenance'];
        $this->actingAs($admin)->put(route('operations.vehicles.update', $vehicle), $payload)->assertSessionHasErrors('confirm_conflicts');
        $this->assertSame('available', $vehicle->fresh()->status);
        $this->put(route('operations.vehicles.update', $vehicle), [...$payload, 'confirm_conflicts' => true])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('maintenance', $vehicle->fresh()->status);
    }

    public function test_own_calendar_does_not_claim_free_time_without_a_staff_record(): void
    {
        $technician = User::factory()->create(['role' => 'technician']);
        FieldServiceProfile::create(['user_id' => $technician->id, 'daily_capacity_minutes' => 480, 'is_available' => true, 'weekly_schedule' => ['1' => [['start' => '08:00', 'end' => '16:00']]]]);
        $this->actingAs($technician)->get(route('operations.calendar', [$technician, 'week' => '2026-11-02', 'view' => 'day']))->assertInertia(fn (Assert $p) => $p->where('calendarDays.0.available_minutes', null));
    }

    public function test_dst_ambiguity_rejected_and_closed_orders_not_modified(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $vehicle = $this->vehicle($admin);
        $this->actingAs($admin)->post(route('operations.vehicles.blocks', $vehicle), ['starts_at' => '2026-10-25 02:15', 'ends_at' => '2026-10-25 03:15', 'reason' => 'Synthetic'])->assertSessionHasErrors('starts_at');
        $this->post(route('operations.vehicles.blocks', $vehicle), ['starts_at' => '2026-03-29 02:15', 'ends_at' => '2026-03-29 03:15', 'reason' => 'Synthetic'])->assertSessionHasErrors('starts_at');
        $closed = $this->order(values: ['status' => 'completed']);
        $this->expectException(HttpException::class);
        app(WorkOrderRescheduler::class)->reschedule($closed, ['vehicle_id' => $vehicle->id, 'scheduled_start' => '2026-11-02 09:00', 'scheduled_end' => '2026-11-02 10:00'], 'Synthetic', $admin->id);
    }
}

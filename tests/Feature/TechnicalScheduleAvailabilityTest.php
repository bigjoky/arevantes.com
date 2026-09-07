<?php

namespace Tests\Feature;

use App\Models\EngineeringScheduleProposal;
use App\Models\FieldServiceProfile;
use App\Models\HrLeaveRequest;
use App\Models\Installation;
use App\Models\Person;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\Engineering\TechnicalScheduleAvailability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TechnicalScheduleAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_absences_are_checked_without_disclosing_private_reasons_or_reserving_work(): void
    {
        $person = Person::create(['category' => 'staff', 'first_name' => 'Planner']);
        $user = User::factory()->create(['person_id' => $person->id, 'role' => 'technician']);
        $proposal = new EngineeringScheduleProposal(['assignee_id' => $user->id, 'proposed_on' => today()->addDays(2), 'estimated_hours' => 4]);
        $service = app(TechnicalScheduleAvailability::class);
        $this->assertSame('blocked', $service->check($proposal, '09:00')['status']);
        FieldServiceProfile::create(['user_id' => $user->id, 'daily_capacity_minutes' => 480, 'is_available' => true]);
        $this->assertSame('clear', $service->check($proposal, '09:00')['status']);
        $leave = HrLeaveRequest::create(['person_id' => $person->id, 'type' => 'vacation', 'start_date' => $proposal->proposed_on, 'end_date' => $proposal->proposed_on, 'requested_days' => 1, 'status' => 'pending', 'notes' => 'PRIVATE MEDICAL REASON']);
        $this->assertSame('warning', $service->check($proposal, '09:00')['status']);
        $leave->update(['status' => 'approved']);
        $result = $service->check($proposal, '09:00');
        $this->assertSame('blocked', $result['status']);
        $this->assertStringNotContainsString('PRIVATE MEDICAL REASON', json_encode($result));
        $leave->update(['status' => 'rejected']);
        $this->assertSame('clear', $service->check($proposal, '09:00')['status']);
        $this->assertSame('blocked', $service->check($proposal, '23:00')['status']);
        $proposal->estimated_hours = 2;
        $leave->update(['status' => 'approved', 'start_date' => $proposal->proposed_on->copy()->addDay(), 'end_date' => $proposal->proposed_on->copy()->addDay()]);
        $this->assertSame('clear', $service->check($proposal, '22:00')['status']);
        $this->assertSame(0, WorkOrder::count());
    }

    public function test_orders_overlap_and_overnight_work_counts_towards_daily_capacity(): void
    {
        $user = User::factory()->create(['role' => 'technician']);
        FieldServiceProfile::create(['user_id' => $user->id, 'daily_capacity_minutes' => 480, 'is_available' => true]);
        $installation = Installation::create(['service_type' => 'electrical', 'status' => 'survey_booked', 'site_address_line_1' => 'Work site']);
        $day = today()->addDays(2);
        $proposal = new EngineeringScheduleProposal(['assignee_id' => $user->id, 'proposed_on' => $day, 'estimated_hours' => 7]);
        $order = WorkOrder::create(['installation_id' => $installation->id, 'assigned_user_id' => $user->id, 'created_by' => $user->id, 'number' => 'WO-AVAIL-1', 'title' => 'Existing work', 'kind' => 'installation', 'status' => 'scheduled', 'scheduled_start' => $day->copy()->subHour(), 'scheduled_end' => $day->copy()->addHours(2)]);
        $service = app(TechnicalScheduleAvailability::class);
        $this->assertSame('blocked', $service->check($proposal, '09:00')['status']);
        $proposal->estimated_hours = 2;
        $this->assertSame('clear', $service->check($proposal, '09:00')['status']);
        $this->assertSame('blocked', $service->check($proposal, '01:00')['status']);
        $order->update(['status' => 'cancelled']);
        $this->assertSame('clear', $service->check($proposal, '01:00')['status']);
        $this->assertSame(1, WorkOrder::count());
    }
}

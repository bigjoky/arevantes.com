<?php

namespace Tests\Feature;

use App\Models\CrmActivity;
use App\Models\FieldServiceProfile;
use App\Models\HrCalendarHoliday;
use App\Models\HrLeaveRequest;
use App\Models\Installation;
use App\Models\Person;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\Work\TechnicianSchedule;
use App\Services\Work\WorkOrderRescheduler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class OperationalAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    private function technician(): User
    {
        $user = User::factory()->create(['role' => 'technician']);
        FieldServiceProfile::create(['user_id' => $user->id, 'daily_capacity_minutes' => 480, 'is_available' => true, 'skills' => ['solar'], 'weekly_schedule' => array_fill(1, 7, [['start' => '08:00', 'end' => '14:00'], ['start' => '15:00', 'end' => '18:00']])]);

        return $user;
    }

    private function decision(User $user, string $start = '2026-11-02T09:00:00+01:00', string $end = '2026-11-02T10:00:00+01:00'): array
    {
        return app(TechnicianSchedule::class)->availability($user->id, $start, $end, requiredSkills: ['solar']);
    }

    private function order(User $user, array $changes = []): WorkOrder
    {
        $installation = Installation::create(['service_type' => 'electrical', 'status' => 'survey_booked', 'site_address_line_1' => 'Synthetic site']);

        return WorkOrder::create([...['installation_id' => $installation->id, 'number' => 'OPS-'.str()->uuid(), 'title' => 'Synthetic visit', 'kind' => 'survey', 'status' => 'planned', 'assigned_user_id' => $user->id, 'scheduled_start' => '2026-11-02T09:00:00+01:00', 'scheduled_end' => '2026-11-02T10:00:00+01:00'], ...$changes]);
    }

    public function test_available_unknown_and_non_working_schedules_are_distinct(): void
    {
        $user = $this->technician();
        $this->assertSame('available', $this->decision($user)['status']);
        $user->fieldServiceProfile->update(['weekly_schedule' => null]);
        $this->assertSame('unknown', $this->decision($user)['status']);
        $user->fieldServiceProfile->update(['weekly_schedule' => []]);
        $this->assertSame('unavailable', $this->decision($user)['status']);
    }

    public function test_breaks_holidays_skills_and_approved_leave_block_without_private_details(): void
    {
        $user = $this->technician();
        $this->assertSame('unavailable', $this->decision($user, '2026-11-02 13:30', '2026-11-02 15:30')['status']);
        HrCalendarHoliday::create(['holiday_date' => '2026-11-02', 'name' => 'Synthetic holiday']);
        $this->assertSame('unavailable', $this->decision($user)['status']);
        HrCalendarHoliday::query()->delete();
        $user->fieldServiceProfile->update(['skills' => []]);
        $this->assertSame('unavailable', $this->decision($user)['status']);
        $user->fieldServiceProfile->update(['skills' => ['solar']]);
        $person = Person::create(['category' => 'staff', 'first_name' => 'Synthetic']);
        $user->update(['person_id' => $person->id]);
        HrLeaveRequest::create(['person_id' => $person->id, 'type' => 'vacation', 'start_date' => '2026-11-02', 'end_date' => '2026-11-02', 'requested_days' => 1, 'status' => 'approved', 'notes' => 'PRIVATE REASON']);
        $decision = $this->decision($user);
        $this->assertSame('unavailable', $decision['status']);
        $this->assertStringNotContainsString('PRIVATE REASON', json_encode($decision));
    }

    public function test_orders_and_unlinked_visits_conflict_but_linked_activity_is_not_counted_twice(): void
    {
        $user = $this->technician();
        $order = $this->order($user);
        $this->assertSame('unavailable', $this->decision($user)['status']);
        $order->update(['status' => 'cancelled']);
        $activity = CrmActivity::create(['title' => 'Synthetic', 'type' => 'visit', 'status' => 'pending', 'assigned_user_id' => $user->id, 'starts_at' => '2026-11-02T09:00:00+01:00', 'due_at' => '2026-11-02T10:00:00+01:00']);
        $this->assertSame('unavailable', $this->decision($user)['status']);
        $activity->update(['work_order_id' => $order->id]);
        $this->assertSame('available', $this->decision($user)['status']);
        $this->assertSame('available', $this->decision($user, '2026-11-02 10:00', '2026-11-02 11:00')['status']);
    }

    public function test_incomplete_assignment_is_unknown_and_daily_capacity_counts_crm_visits(): void
    {
        $user = $this->technician();
        $order = $this->order($user, ['scheduled_end' => null]);
        $this->assertSame('unknown', $this->decision($user, '2026-11-02 11:00', '2026-11-02 12:00')['status']);
        $order->update(['status' => 'cancelled']);
        $user->fieldServiceProfile->update(['daily_capacity_minutes' => 60]);
        CrmActivity::create(['title' => 'Synthetic', 'type' => 'visit', 'status' => 'pending', 'assigned_user_id' => $user->id, 'starts_at' => '2026-11-02 11:00', 'due_at' => '2026-11-02 12:00']);
        $this->assertSame('unavailable', $this->decision($user)['status']);
        CrmActivity::query()->delete();
        CrmActivity::create(['title' => 'Incomplete synthetic visit', 'type' => 'visit', 'status' => 'pending', 'assigned_user_id' => $user->id, 'due_at' => '2026-11-02 12:00']);
        $this->assertSame('unknown', $this->decision($user)['status']);
    }

    public function test_dst_nonexistent_and_repeated_wall_times_require_explicit_instants(): void
    {
        $user = $this->technician();
        $user->fieldServiceProfile->update(['weekly_schedule' => ['7' => [['start' => '00:00', 'end' => '06:00']]]]);
        foreach ([['2026-03-29 02:15', '2026-03-29 03:15'], ['2026-10-25 02:15', '2026-10-25 03:15']] as [$start, $end]) {
            $this->assertSame('unavailable', $this->decision($user, $start, $end)['status']);
        }
        $this->assertSame('available', $this->decision($user, '2026-10-25T02:15:00+02:00', '2026-10-25T02:45:00+01:00')['status']);
    }

    public function test_rescheduling_preserves_history_syncs_activity_and_repeated_confirmation_is_noop(): void
    {
        $user = $this->technician();
        $order = $this->order($user);
        $activity = CrmActivity::create(['title' => 'Synthetic', 'type' => 'visit', 'status' => 'pending', 'work_order_id' => $order->id, 'assigned_user_id' => $user->id]);
        $changes = ['scheduled_start' => '2026-11-02T11:00:00+01:00', 'scheduled_end' => '2026-11-02T12:00:00+01:00'];
        $service = app(WorkOrderRescheduler::class);
        $service->reschedule($order, $changes, 'Synthetic reschedule', $user->id);
        $service->reschedule($order, $changes, 'Repeated confirmation', $user->id);
        $this->assertSame(1, $order->reschedules()->count());
        $this->assertTrue($activity->refresh()->starts_at->equalTo($order->refresh()->scheduled_start));
        $this->assertSame($user->id, $order->reschedules()->first()->changed_by);
        $this->assertSame(0, DB::table('jobs')->count());
    }

    public function test_native_calendar_views_filters_and_schedule_history_share_the_same_events(): void
    {
        $user = $this->technician();
        $order = $this->order($user);
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        CrmActivity::create(['title' => 'Linked synthetic', 'type' => 'visit', 'status' => 'pending', 'work_order_id' => $order->id, 'assigned_user_id' => $user->id, 'starts_at' => '2026-11-02 09:00', 'due_at' => '2026-11-02 10:00']);
        CrmActivity::create(['title' => 'Independent synthetic', 'type' => 'visit', 'status' => 'pending', 'assigned_user_id' => $user->id, 'starts_at' => '2026-11-02 12:00', 'due_at' => '2026-11-02 13:00']);
        foreach (['day' => 1, 'week' => 7, 'month' => 42] as $view => $days) {
            $this->get('/operations/planning?week=2026-11-02&view='.$view)->assertInertia(fn (AssertableInertia $page) => $page->has('days', $days)->has('orders', 1)->has('activities', 1));
        }
        $this->get('/operations/planning?week=2026-11-02&kind=repair')->assertInertia(fn (AssertableInertia $page) => $page->has('orders', 0));
        app(WorkOrderRescheduler::class)->reschedule($order, ['scheduled_start' => '2026-11-02 10:00', 'scheduled_end' => '2026-11-02 11:00'], 'Synthetic revision', auth()->id());
        $this->get('/work-orders/'.$order->id.'?tab=activity')->assertInertia(fn (AssertableInertia $page) => $page->has('scheduleHistory', 1)->where('scheduleHistory.0.reason', 'Synthetic revision')->where('scheduleHistory.0.next_technician.id', $user->id));
    }

    public function test_calendar_excludes_interventions_ending_exactly_at_the_day_boundary(): void
    {
        $user = $this->technician();
        $this->order($user, ['scheduled_start' => '2026-11-01T23:00:00+01:00', 'scheduled_end' => '2026-11-02T00:00:00+01:00']);
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->get('/operations/planning?week=2026-11-02&view=day')->assertInertia(fn (AssertableInertia $page) => $page->has('orders', 0));
        $this->get('/operations/agenda?week=2026-11-02&view=day')->assertInertia(fn (AssertableInertia $page) => $page->has('orders', 0));
    }

    public function test_profile_configuration_rejects_partial_overlapping_and_reversed_periods(): void
    {
        $user = $this->technician();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $base = ['daily_capacity_minutes' => 480, 'max_travel_km' => 100, 'is_available' => true];
        foreach ([[['start' => '09:00', 'end' => '']], [['start' => '11:00', 'end' => '10:00']], [['start' => '09:00', 'end' => '12:00'], ['start' => '11:00', 'end' => '13:00']]] as $slots) {
            $this->putJson(route('field-service.profiles.update', $user), [...$base, 'weekly_schedule' => ['1' => $slots]])->assertUnprocessable();
        }
        $this->putJson(route('field-service.profiles.update', $user), [...$base, 'weekly_schedule' => ['1' => [['start' => '09:00', 'end' => '12:00'], ['start' => '', 'end' => '']]]])->assertRedirect();
        $this->assertSame([['start' => '09:00', 'end' => '12:00']], $user->fieldServiceProfile->refresh()->weekly_schedule['1']);
    }

    public function test_closed_order_cannot_be_rescheduled_and_endpoint_requires_permission(): void
    {
        $user = $this->technician();
        $order = $this->order($user, ['status' => 'completed']);
        $this->actingAs($user)->getJson(route('operations.planning.availability', [$order, 'scheduled_start' => '2026-11-02 11:00', 'scheduled_end' => '2026-11-02 12:00']))->assertForbidden();
        $this->expectException(HttpException::class);
        app(WorkOrderRescheduler::class)->reschedule($order, ['scheduled_start' => '2026-11-02 11:00', 'scheduled_end' => '2026-11-02 12:00'], 'Synthetic', $user->id);
    }
}

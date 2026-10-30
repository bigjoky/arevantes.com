<?php

namespace Tests\Feature;

use App\Models\FieldServiceProfile;
use App\Models\HrCalendarHoliday;
use App\Models\HrLeaveAttachment;
use App\Models\HrLeavePolicy;
use App\Models\HrLeaveRequest;
use App\Models\Installation;
use App\Models\Person;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\HumanResources\LeaveBalanceCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class HumanResourcesLeavePolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_policy_requires_protected_evidence_and_employee_can_only_download_their_own(): void
    {
        Storage::fake('local');
        $person = $this->staff('Julia', 'Nieto');
        $user = User::factory()->create(['person_id' => $person->id, 'role' => 'technician']);
        FieldServiceProfile::create(['user_id' => $user->id, 'weekly_schedule' => array_fill(1, 7, [['start' => '00:00', 'end' => '24:00']]), 'daily_capacity_minutes' => 1440, 'is_available' => true]);
        $other = $this->staff('Pedro', 'Ríos');
        $otherUser = User::factory()->create(['person_id' => $other->id, 'role' => 'technician']);
        FieldServiceProfile::create(['user_id' => $otherUser->id, 'weekly_schedule' => array_fill(1, 7, [['start' => '00:00', 'end' => '24:00']]), 'daily_capacity_minutes' => 1440, 'is_available' => true]);

        $this->actingAs($user)->post(route('my-human-resources.leave.store'), [
            'type' => 'sick',
            'start_date' => '2026-08-10',
            'end_date' => '2026-08-11',
        ])->assertSessionHasErrors('attachment');

        $this->post(route('my-human-resources.leave.store'), [
            'type' => 'sick',
            'start_date' => '2026-08-10',
            'end_date' => '2026-08-11',
            'attachment' => UploadedFile::fake()->create('parte-medico.pdf', 80, 'application/pdf'),
        ])->assertRedirect();

        $leave = HrLeaveRequest::firstOrFail();
        $attachment = HrLeaveAttachment::firstOrFail();
        Storage::disk('local')->assertExists($attachment->path);
        $this->get(route('my-human-resources.leave.attachments.download', [$leave, $attachment]))->assertOk();
        $this->actingAs($otherUser)
            ->get(route('my-human-resources.leave.attachments.download', [$leave, $attachment]))
            ->assertNotFound();
    }

    public function test_absence_with_scheduled_work_requires_a_traceable_substitute(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $person = $this->staff('Iván', 'Campo');
        $technician = User::factory()->create(['person_id' => $person->id, 'role' => 'technician']);
        FieldServiceProfile::create(['user_id' => $technician->id, 'weekly_schedule' => array_fill(1, 7, [['start' => '00:00', 'end' => '24:00']]), 'daily_capacity_minutes' => 1440, 'is_available' => true]);
        $substitute = $this->staff('Sara', 'Luz');
        $substituteUser = User::factory()->create(['person_id' => $substitute->id, 'role' => 'technician']);
        FieldServiceProfile::create(['user_id' => $substituteUser->id, 'weekly_schedule' => array_fill(1, 7, [['start' => '00:00', 'end' => '24:00']]), 'daily_capacity_minutes' => 1440, 'is_available' => true]);
        $installation = Installation::create([
            'service_type' => 'solar',
            'status' => 'scheduled',
            'site_address_line_1' => 'Calle Sol 8',
        ]);
        WorkOrder::create([
            'installation_id' => $installation->id,
            'assigned_user_id' => $technician->id,
            'created_by' => $admin->id,
            'number' => 'WO-COVERAGE-1',
            'title' => 'Puesta en marcha',
            'status' => 'planned',
            'scheduled_start' => '2026-08-12 09:00:00',
            'scheduled_end' => '2026-08-12 13:00:00',
        ]);
        $leave = HrLeaveRequest::create([
            'person_id' => $person->id,
            'type' => 'vacation',
            'start_date' => '2026-08-12',
            'end_date' => '2026-08-13',
            'requested_days' => 2,
            'status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->get(route('human-resources.leave.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('requests.0.coverageConflicts', 1)
                ->where('requests.0.coverageConflicts.0.number', 'WO-COVERAGE-1'));

        $this->put(route('human-resources.leave.update', $leave), [
            'status' => 'approved',
        ])->assertSessionHasErrors('substitute_person_id');

        $this->put(route('human-resources.leave.update', $leave), [
            'status' => 'approved',
            'substitute_person_id' => $substitute->id,
        ])->assertRedirect();
        $this->assertDatabaseHas('hr_leave_requests', [
            'id' => $leave->id,
            'status' => 'approved',
            'substitute_person_id' => $substitute->id,
        ]);
        $this->assertDatabaseHas('work_orders', [
            'number' => 'WO-COVERAGE-1',
            'assigned_user_id' => $substituteUser->id,
        ]);
    }

    public function test_monthly_accrual_used_days_and_policy_changes_are_exposed(): void
    {
        $this->travelTo('2026-08-06 12:00:00');
        $admin = User::factory()->create(['role' => 'admin']);
        $person = $this->staff('Noa', 'Rey');
        HrLeaveRequest::create([
            'person_id' => $person->id,
            'type' => 'vacation',
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-05',
            'requested_days' => 5,
            'status' => 'approved',
        ]);

        $this->actingAs($admin)
            ->get(route('human-resources.leave.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('staff.0.balances.3.name', 'Vacaciones')
                ->where('staff.0.balances.3.accrued', 14.67)
                ->where('staff.0.balances.3.used', 3)
                ->where('staff.0.balances.3.available', 11.67));

        $policy = HrLeavePolicy::where('type', 'vacation')->firstOrFail();
        $this->put(route('human-resources.leave.policies.update', $policy), [
            'name' => 'Vacaciones laborables',
            'annual_allowance_days' => 24,
            'accrual_method' => 'monthly',
            'carryover_limit_days' => 6,
            'requires_attachment' => false,
            'is_active' => true,
        ])->assertRedirect();
        $this->assertDatabaseHas('hr_leave_policies', [
            'id' => $policy->id,
            'name' => 'Vacaciones laborables',
            'annual_allowance_days' => 24,
        ]);
    }

    public function test_working_day_requests_exclude_weekends_and_configured_holidays(): void
    {
        $this->travelTo('2026-08-10 09:00:00');
        $person = $this->staff('Lara', 'Díaz');
        $user = User::factory()->create(['person_id' => $person->id, 'role' => 'technician']);
        FieldServiceProfile::create(['user_id' => $user->id, 'weekly_schedule' => array_fill(1, 7, [['start' => '00:00', 'end' => '24:00']]), 'daily_capacity_minutes' => 1440, 'is_available' => true]);
        HrCalendarHoliday::create(['holiday_date' => '2026-08-17', 'name' => 'Festivo local']);

        $this->actingAs($user)->post(route('my-human-resources.leave.store'), [
            'type' => 'vacation',
            'start_date' => '2026-08-14',
            'end_date' => '2026-08-17',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('hr_leave_requests', [
            'person_id' => $person->id,
            'requested_days' => 1,
            'status' => 'pending',
        ]);
    }

    public function test_cross_year_leave_is_allocated_to_the_correct_balance_year(): void
    {
        $this->travelTo('2026-08-10 09:00:00');
        $person = $this->staff('Álex', 'Sanz');
        HrCalendarHoliday::create(['holiday_date' => '2027-01-01', 'name' => 'Año Nuevo']);
        HrLeaveRequest::create([
            'person_id' => $person->id,
            'type' => 'vacation',
            'start_date' => '2026-12-31',
            'end_date' => '2027-01-04',
            'requested_days' => 2,
            'status' => 'approved',
        ]);

        $policy = HrLeavePolicy::where('type', 'vacation')->firstOrFail();
        $calculator = app(LeaveBalanceCalculator::class);
        $this->assertSame(1, $calculator->calculate($person, $policy, 2026)['used']);
        $this->assertSame(1, $calculator->calculate($person, $policy, 2027)['used']);
    }

    public function test_employee_cannot_request_more_days_than_the_available_balance(): void
    {
        $this->travelTo('2026-08-10 09:00:00');
        $person = $this->staff('Eva', 'Paz');
        $user = User::factory()->create(['person_id' => $person->id, 'role' => 'technician']);
        FieldServiceProfile::create(['user_id' => $user->id, 'weekly_schedule' => array_fill(1, 7, [['start' => '00:00', 'end' => '24:00']]), 'daily_capacity_minutes' => 1440, 'is_available' => true]);

        $this->actingAs($user)->post(route('my-human-resources.leave.store'), [
            'type' => 'personal',
            'start_date' => '2026-08-17',
            'end_date' => '2026-08-21',
        ])->assertSessionHasErrors('start_date');

        $this->assertDatabaseMissing('hr_leave_requests', ['person_id' => $person->id]);
    }

    public function test_reviewed_leave_cannot_be_decided_twice(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $person = $this->staff('Leo', 'Serra');
        $leave = HrLeaveRequest::create([
            'person_id' => $person->id,
            'type' => 'sick',
            'start_date' => '2026-08-18',
            'end_date' => '2026-08-18',
            'requested_days' => 1,
            'status' => 'approved',
        ]);

        $this->actingAs($admin)->put(route('human-resources.leave.update', $leave), [
            'status' => 'rejected',
        ])->assertSessionHasErrors('status');

        $this->assertDatabaseHas('hr_leave_requests', ['id' => $leave->id, 'status' => 'approved']);
    }

    private function staff(string $firstName, string $lastName): Person
    {
        return Person::create([
            'category' => 'staff',
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => strtolower($firstName.'.'.$lastName).'@example.com',
        ]);
    }
}

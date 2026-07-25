<?php

namespace Tests\Feature;

use App\Models\HrDepartment;
use App\Models\HrLeaveRequest;
use App\Models\Person;
use App\Models\User;
use App\Notifications\LeaveRequestDecisionNotification;
use App\Notifications\LeaveRequestSubmittedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class HumanResourcesSelfServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_can_view_only_their_own_human_resources_data(): void
    {
        $department = HrDepartment::create([
            'name' => 'Operaciones',
            'is_active' => true,
        ]);
        $manager = $this->staff('Marta', 'Manager');
        $employee = $this->staff('Luis', 'Employee');
        $other = $this->staff('Nora', 'Other');
        $employee->employmentProfile()->create([
            'department_id' => $department->id,
            'manager_person_id' => $manager->id,
            'employee_number' => 'EMP-010',
            'employment_type' => 'permanent',
            'status' => 'active',
            'weekly_hours' => 40,
            'start_date' => '2026-01-15',
        ]);
        $ownLeave = $this->leave($employee, '2026-08-03', '2026-08-07');
        $this->leave($other, '2026-09-01', '2026-09-02');
        $user = User::factory()->create([
            'person_id' => $employee->id,
            'role' => 'technician',
        ]);

        $this->actingAs($user)
            ->get(route('my-human-resources.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('human-resources/my-space')
                ->where('employee.id', $employee->id)
                ->where('employee.employment.departmentName', 'Operaciones')
                ->where('employee.employment.managerName', 'Marta Manager')
                ->has('requests', 1)
                ->where('requests.0.id', $ownLeave->id)
            );
    }

    public function test_employee_can_submit_leave_and_admin_is_notified(): void
    {
        Notification::fake();
        $employee = $this->staff('Luis', 'Employee');
        $user = User::factory()->create([
            'person_id' => $employee->id,
            'role' => 'commercial',
        ]);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($user)
            ->post(route('my-human-resources.leave.store'), [
                'type' => 'vacation',
                'start_date' => '2026-08-03',
                'end_date' => '2026-08-07',
                'notes' => 'Vacaciones',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('hr_leave_requests', [
            'person_id' => $employee->id,
            'requested_days' => 5,
            'status' => 'pending',
        ]);
        Notification::assertSentTo($admin, LeaveRequestSubmittedNotification::class);
    }

    public function test_overlapping_pending_or_approved_leave_is_rejected(): void
    {
        $employee = $this->staff('Luis', 'Employee');
        $user = User::factory()->create([
            'person_id' => $employee->id,
            'role' => 'commercial',
        ]);
        $this->leave($employee, '2026-08-03', '2026-08-07');

        $this->actingAs($user)
            ->post(route('my-human-resources.leave.store'), [
                'type' => 'personal',
                'start_date' => '2026-08-07',
                'end_date' => '2026-08-08',
                'notes' => null,
            ])
            ->assertSessionHasErrors('start_date');

        $this->assertSame(1, HrLeaveRequest::count());
    }

    public function test_employee_can_cancel_only_their_own_pending_request(): void
    {
        $employee = $this->staff('Luis', 'Employee');
        $other = $this->staff('Nora', 'Other');
        $user = User::factory()->create([
            'person_id' => $employee->id,
            'role' => 'technician',
        ]);
        $ownLeave = $this->leave($employee, '2026-08-03', '2026-08-07');
        $otherLeave = $this->leave($other, '2026-09-01', '2026-09-02');

        $this->actingAs($user)
            ->patch(route('my-human-resources.leave.cancel', $otherLeave))
            ->assertNotFound();

        $this->patch(route('my-human-resources.leave.cancel', $ownLeave))
            ->assertRedirect();
        $this->assertDatabaseHas('hr_leave_requests', [
            'id' => $ownLeave->id,
            'status' => 'cancelled',
        ]);

        $ownLeave->update(['status' => 'approved']);
        $this->patch(route('my-human-resources.leave.cancel', $ownLeave))
            ->assertUnprocessable();
    }

    public function test_employee_is_notified_when_admin_reviews_leave(): void
    {
        Notification::fake();
        $employee = $this->staff('Luis', 'Employee');
        $employeeUser = User::factory()->create([
            'person_id' => $employee->id,
            'role' => 'technician',
        ]);
        $admin = User::factory()->create(['role' => 'admin']);
        $leave = $this->leave($employee, '2026-08-03', '2026-08-07');

        $this->actingAs($admin)
            ->put(route('human-resources.leave.update', $leave), [
                'status' => 'approved',
                'decision_notes' => 'Aprobada',
            ])
            ->assertRedirect();

        Notification::assertSentTo($employeeUser, LeaveRequestDecisionNotification::class);
    }

    public function test_user_without_staff_profile_sees_linking_message_and_cannot_submit(): void
    {
        $user = User::factory()->create([
            'person_id' => null,
            'role' => 'commercial',
        ]);

        $this->actingAs($user)
            ->get(route('my-human-resources.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('employee', null)
                ->has('requests', 0)
            );

        $this->post(route('my-human-resources.leave.store'), [
            'type' => 'vacation',
            'start_date' => '2026-08-03',
            'end_date' => '2026-08-07',
        ])->assertForbidden();
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

    private function leave(Person $person, string $startDate, string $endDate): HrLeaveRequest
    {
        return HrLeaveRequest::create([
            'person_id' => $person->id,
            'type' => 'vacation',
            'start_date' => $startDate,
            'end_date' => $endDate,
            'requested_days' => 5,
            'status' => 'pending',
        ]);
    }
}

<?php

namespace Tests\Feature;

use App\Models\HrDepartment;
use App\Models\HrEmploymentProfile;
use App\Models\HrLeaveRequest;
use App\Models\Person;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HumanResourcesManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_manage_departments(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $this->post(route('human-resources.departments.store'), [
            'name' => 'Operaciones',
            'code' => 'ops',
            'description' => 'Equipo de campo',
            'is_active' => true,
        ])->assertRedirect();

        $department = HrDepartment::firstOrFail();
        $this->assertSame('OPS', $department->code);

        $this->put(route('human-resources.departments.update', $department), [
            'name' => 'Operaciones técnicas',
            'code' => 'tec',
            'description' => null,
            'is_active' => false,
        ])->assertRedirect();

        $this->assertDatabaseHas('hr_departments', [
            'id' => $department->id,
            'name' => 'Operaciones técnicas',
            'code' => 'TEC',
            'is_active' => false,
        ]);

        $this->delete(route('human-resources.departments.destroy', $department))
            ->assertRedirect();
        $this->assertDatabaseMissing('hr_departments', ['id' => $department->id]);
    }

    public function test_admin_can_create_and_update_an_employment_profile(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $department = HrDepartment::create([
            'name' => 'Comercial',
            'code' => 'COM',
            'is_active' => true,
        ]);
        $manager = $this->staff('Marta', 'Manager');
        $employee = $this->staff('Luis', 'Employee');

        $this->put(route('human-resources.employees.update', $employee), [
            'employee_number' => 'EMP-001',
            'department_id' => $department->id,
            'manager_person_id' => $manager->id,
            'employment_type' => 'permanent',
            'status' => 'active',
            'work_location' => 'Madrid',
            'weekly_hours' => 40,
            'start_date' => '2026-07-01',
            'probation_end_date' => '2026-10-01',
            'end_date' => null,
        ])->assertRedirect();

        $this->assertDatabaseHas('hr_employment_profiles', [
            'person_id' => $employee->id,
            'employee_number' => 'EMP-001',
            'department_id' => $department->id,
            'manager_person_id' => $manager->id,
            'status' => 'active',
        ]);

        $this->put(route('human-resources.employees.update', $employee), [
            'employee_number' => 'EMP-001',
            'department_id' => $department->id,
            'manager_person_id' => null,
            'employment_type' => 'temporary',
            'status' => 'ended',
            'work_location' => 'Córdoba',
            'weekly_hours' => 20,
            'start_date' => '2026-07-01',
            'probation_end_date' => null,
            'end_date' => '2026-12-31',
        ])->assertRedirect();

        $this->assertSame(1, HrEmploymentProfile::count());
        $this->assertDatabaseHas('hr_employment_profiles', [
            'person_id' => $employee->id,
            'employment_type' => 'temporary',
            'status' => 'ended',
            'work_location' => 'Córdoba',
        ]);
    }

    public function test_employment_profile_rejects_customers_and_self_management(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $employee = $this->staff('Ana', 'Employee');
        $customer = Person::create([
            'category' => 'customer',
            'first_name' => 'Cliente',
        ]);

        $payload = [
            'employee_number' => null,
            'department_id' => null,
            'manager_person_id' => $employee->id,
            'employment_type' => 'permanent',
            'status' => 'active',
            'work_location' => null,
            'weekly_hours' => 40,
            'start_date' => '2026-07-01',
            'probation_end_date' => null,
            'end_date' => null,
        ];

        $this->put(route('human-resources.employees.update', $employee), $payload)
            ->assertSessionHasErrors('manager_person_id');

        $this->put(route('human-resources.employees.update', $customer), [
            ...$payload,
            'manager_person_id' => null,
        ])->assertNotFound();
    }

    public function test_admin_can_create_and_review_leave_requests(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $employee = $this->staff('Nora', 'Employee');
        $this->actingAs($admin);

        $this->post(route('human-resources.leave.store'), [
            'person_id' => $employee->id,
            'type' => 'vacation',
            'start_date' => '2026-08-03',
            'end_date' => '2026-08-07',
            'notes' => 'Vacaciones de verano',
        ])->assertRedirect();

        $leave = HrLeaveRequest::firstOrFail();
        $this->assertSame(5, $leave->requested_days);
        $this->assertSame('pending', $leave->status);

        $this->put(route('human-resources.leave.update', $leave), [
            'status' => 'approved',
            'decision_notes' => 'Aprobado por planificación',
        ])->assertRedirect();

        $leave->refresh();
        $this->assertSame('approved', $leave->status);
        $this->assertSame($admin->id, $leave->reviewed_by);
        $this->assertNotNull($leave->reviewed_at);

        $this->delete(route('human-resources.leave.destroy', $leave))
            ->assertUnprocessable();
    }

    public function test_non_admin_cannot_manage_human_resources_data(): void
    {
        $employee = $this->staff('Pablo', 'Employee');
        $this->actingAs(User::factory()->create(['role' => 'commercial']));

        $this->post(route('human-resources.departments.store'), [
            'name' => 'Unauthorized',
            'is_active' => true,
        ])->assertForbidden();

        $this->post(route('human-resources.leave.store'), [
            'person_id' => $employee->id,
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
}

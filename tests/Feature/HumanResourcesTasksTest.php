<?php

namespace Tests\Feature;

use App\Models\HrEmployeeTask;
use App\Models\Person;
use App\Models\User;
use App\Notifications\HrTaskAssignedNotification;
use App\Notifications\HrTaskCompletedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class HumanResourcesTasksTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_and_manage_employee_tasks(): void
    {
        Notification::fake();
        $employee = $this->staff('Luis', 'Employee');
        $employeeUser = User::factory()->create([
            'person_id' => $employee->id,
            'role' => 'technician',
        ]);
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);

        $this->post(route('human-resources.tasks.store'), [
            'person_id' => $employee->id,
            'title' => 'Completar formación PRL',
            'category' => 'training',
            'priority' => 'high',
            'due_date' => '2026-08-15',
            'employee_visible' => true,
            'employee_actionable' => true,
            'notes' => 'Curso inicial obligatorio',
        ])->assertRedirect();

        $task = HrEmployeeTask::firstOrFail();
        $this->assertDatabaseHas('hr_employee_tasks', [
            'id' => $task->id,
            'person_id' => $employee->id,
            'created_by' => $admin->id,
            'status' => 'pending',
            'employee_actionable' => true,
            'employee_visible' => true,
        ]);
        Notification::assertSentTo($employeeUser, HrTaskAssignedNotification::class);

        $this->put(route('human-resources.tasks.update', $task), [
            'status' => 'completed',
        ])->assertRedirect();

        $task->refresh();
        $this->assertSame('completed', $task->status);
        $this->assertSame($admin->id, $task->completed_by);
        $this->assertNotNull($task->completed_at);

        $this->put(route('human-resources.tasks.update', $task), [
            'status' => 'pending',
        ])->assertRedirect();
        $this->assertNull($task->fresh()->completed_at);

        $this->delete(route('human-resources.tasks.destroy', $task))
            ->assertRedirect();
        $this->assertSoftDeleted('hr_employee_tasks', ['id' => $task->id]);
    }

    public function test_task_overview_reports_open_overdue_and_completed_work(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $employee = $this->staff('Luis', 'Employee');
        $overdue = $this->task($employee, [
            'title' => 'Vencida',
            'due_date' => today()->subDay(),
        ]);
        $completed = $this->task($employee, [
            'title' => 'Completada',
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('human-resources.tasks.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('human-resources/tasks')
                ->where('summary.open', 1)
                ->where('summary.overdue', 1)
                ->where('summary.completed', 1)
                ->has('tasks', 2)
                ->where('tasks.0.id', $overdue->id)
                ->where('tasks.1.id', $completed->id)
            );
    }

    public function test_employee_can_complete_only_their_actionable_tasks(): void
    {
        Notification::fake();
        $employee = $this->staff('Luis', 'Employee');
        $other = $this->staff('Nora', 'Other');
        $employeeUser = User::factory()->create([
            'person_id' => $employee->id,
            'role' => 'technician',
        ]);
        $admin = User::factory()->create(['role' => 'admin']);
        $actionable = $this->task($employee, [
            'title' => 'Confirmar lectura',
            'employee_actionable' => true,
        ]);
        $adminOnly = $this->task($employee, [
            'title' => 'Validación interna',
            'employee_visible' => true,
            'employee_actionable' => false,
        ]);
        $otherTask = $this->task($other, [
            'title' => 'Tarea ajena',
            'employee_actionable' => true,
        ]);

        $this->actingAs($employeeUser)
            ->patch(route('my-human-resources.tasks.complete', $otherTask))
            ->assertNotFound();
        $this->patch(route('my-human-resources.tasks.complete', $adminOnly))
            ->assertForbidden();
        $this->patch(route('my-human-resources.tasks.complete', $actionable))
            ->assertRedirect();

        $this->assertDatabaseHas('hr_employee_tasks', [
            'id' => $actionable->id,
            'status' => 'completed',
            'completed_by' => $employeeUser->id,
        ]);
        Notification::assertSentTo($admin, HrTaskCompletedNotification::class);
    }

    public function test_employee_self_service_lists_only_their_tasks(): void
    {
        $employee = $this->staff('Luis', 'Employee');
        $other = $this->staff('Nora', 'Other');
        $employeeUser = User::factory()->create([
            'person_id' => $employee->id,
            'role' => 'commercial',
        ]);
        $ownTask = $this->task($employee, [
            'title' => 'Mi tarea',
            'employee_actionable' => true,
        ]);
        $this->task($other, ['title' => 'Tarea ajena']);
        $this->task($employee, [
            'title' => 'Tarea interna',
            'employee_visible' => false,
        ]);

        $this->actingAs($employeeUser)
            ->get(route('my-human-resources.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('tasks', 1)
                ->where('tasks.0.id', $ownTask->id)
                ->where('tasks.0.title', 'Mi tarea')
                ->where('tasks.0.employeeActionable', true)
            );
    }

    public function test_non_admin_cannot_manage_hr_tasks(): void
    {
        $employee = $this->staff('Luis', 'Employee');
        $user = User::factory()->create([
            'person_id' => $employee->id,
            'role' => 'commercial',
        ]);

        $this->actingAs($user)
            ->post(route('human-resources.tasks.store'), [
                'person_id' => $employee->id,
                'title' => 'No autorizada',
                'category' => 'other',
                'priority' => 'normal',
                'due_date' => null,
                'employee_visible' => true,
                'employee_actionable' => true,
                'notes' => null,
            ])
            ->assertForbidden();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function task(Person $person, array $overrides = []): HrEmployeeTask
    {
        return HrEmployeeTask::create([
            'person_id' => $person->id,
            'title' => 'Onboarding',
            'category' => 'onboarding',
            'priority' => 'normal',
            'status' => 'pending',
            'employee_visible' => true,
            'employee_actionable' => false,
            ...$overrides,
        ]);
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

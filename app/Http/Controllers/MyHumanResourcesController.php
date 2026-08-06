<?php

namespace App\Http\Controllers;

use App\Models\HrEmployeeTask;
use App\Models\HrLeaveRequest;
use App\Models\Person;
use App\Models\User;
use App\Notifications\HrTaskCompletedNotification;
use App\Notifications\LeaveRequestSubmittedNotification;
use App\Services\HumanResources\LeaveRequestCreator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class MyHumanResourcesController extends Controller
{
    public function index(Request $request): Response
    {
        $person = $request->user()
            ->staffProfile()
            ->with(['employmentProfile.department', 'employmentProfile.manager'])
            ->first();

        return Inertia::render('human-resources/my-space', [
            'employee' => $person ? $this->employeeData($person) : null,
            'requests' => $person
                ? $person->leaveRequests()
                    ->with('reviewer:id,name')
                    ->latest('start_date')
                    ->get()
                    ->map(fn (HrLeaveRequest $leave) => [
                        'id' => $leave->id,
                        'type' => $leave->type,
                        'startDate' => $leave->start_date->toDateString(),
                        'endDate' => $leave->end_date->toDateString(),
                        'requestedDays' => $leave->requested_days,
                        'status' => $leave->status,
                        'notes' => $leave->notes,
                        'decisionNotes' => $leave->decision_notes,
                        'reviewerName' => $leave->reviewer?->name,
                    ])
                : [],
            'tasks' => $person
                ? $person->hrTasks()
                    ->where('employee_visible', true)
                    ->orderByRaw("case when status = 'completed' then 1 else 0 end")
                    ->orderByRaw('case when due_date is null then 1 else 0 end')
                    ->orderBy('due_date')
                    ->get()
                    ->map(fn (HrEmployeeTask $task) => [
                        'id' => $task->id,
                        'title' => $task->title,
                        'category' => $task->category,
                        'priority' => $task->priority,
                        'dueDate' => $task->due_date?->toDateString(),
                        'status' => $task->status,
                        'employeeActionable' => $task->employee_actionable,
                        'notes' => $task->notes,
                        'completedAt' => $task->completed_at?->toIso8601String(),
                    ])
                : [],
        ]);
    }

    public function store(Request $request, LeaveRequestCreator $creator): RedirectResponse
    {
        $person = $this->staffProfile($request);
        $validated = $request->validate([
            'type' => ['required', Rule::in(['vacation', 'sick', 'personal', 'other'])],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $leave = $creator->create($person, $validated)->load('person');
        Notification::send(
            User::query()->where('role', 'admin')->whereKeyNot($request->user()->id)->get(),
            new LeaveRequestSubmittedNotification($leave),
        );

        return back()->with('success', __('Leave request created.'));
    }

    public function cancel(Request $request, HrLeaveRequest $leave): RedirectResponse
    {
        $person = $this->staffProfile($request);
        abort_unless($leave->person_id === $person->id, 404);
        abort_unless($leave->status === 'pending', 422);

        $leave->update([
            'status' => 'cancelled',
            'reviewed_by' => null,
            'reviewed_at' => null,
        ]);

        return back()->with('success', __('Leave request cancelled.'));
    }

    public function completeTask(Request $request, HrEmployeeTask $task): RedirectResponse
    {
        $person = $this->staffProfile($request);
        abort_unless($task->person_id === $person->id, 404);
        abort_unless($task->employee_visible, 404);
        abort_unless($task->employee_actionable, 403);
        abort_if($task->status === 'completed', 422);

        $task->update([
            'status' => 'completed',
            'completed_by' => $request->user()->id,
            'completed_at' => now(),
        ]);
        $task->load('person');

        Notification::send(
            User::query()->where('role', 'admin')->whereKeyNot($request->user()->id)->get(),
            new HrTaskCompletedNotification($task),
        );

        return back()->with('success', __('HR task completed.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function employeeData(Person $person): array
    {
        $employment = $person->employmentProfile;

        return [
            'id' => $person->id,
            'name' => $person->company_name ?: trim($person->first_name.' '.($person->last_name ?? '')),
            'email' => $person->email,
            'staffRole' => $person->staff_role,
            'employment' => $employment ? [
                'employeeNumber' => $employment->employee_number,
                'departmentName' => $employment->department?->name,
                'managerName' => $employment->manager
                    ? ($employment->manager->company_name
                        ?: trim($employment->manager->first_name.' '.($employment->manager->last_name ?? '')))
                    : null,
                'employmentType' => $employment->employment_type,
                'status' => $employment->status,
                'workLocation' => $employment->work_location,
                'weeklyHours' => $employment->weekly_hours,
                'startDate' => $employment->start_date?->toDateString(),
            ] : null,
        ];
    }

    private function staffProfile(Request $request): Person
    {
        $person = $request->user()->staffProfile;
        abort_unless($person?->category === 'staff', 403);

        return $person;
    }
}

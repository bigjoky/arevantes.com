<?php

namespace App\Http\Controllers;

use App\Models\HrEmployeeTask;
use App\Models\Person;
use App\Notifications\HrTaskAssignedNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class HrTaskController extends Controller
{
    public function index(): Response
    {
        $tasks = HrEmployeeTask::query()
            ->with(['person', 'creator:id,name', 'completedBy:id,name'])
            ->orderByRaw("case when status = 'completed' then 1 else 0 end")
            ->orderByRaw('case when due_date is null then 1 else 0 end')
            ->orderBy('due_date')
            ->latest('id')
            ->get();

        return Inertia::render('human-resources/tasks', [
            'staff' => Person::query()
                ->where('category', 'staff')
                ->orderBy('first_name')
                ->orderBy('last_name')
                ->get()
                ->map(fn (Person $person) => [
                    'id' => $person->id,
                    'name' => $this->displayName($person),
                ]),
            'summary' => [
                'open' => $tasks->whereNotIn('status', ['completed'])->count(),
                'overdue' => $tasks
                    ->whereNotIn('status', ['completed'])
                    ->filter(fn (HrEmployeeTask $task) => $task->due_date?->isPast())
                    ->count(),
                'completed' => $tasks->where('status', 'completed')->count(),
            ],
            'tasks' => $tasks->map(fn (HrEmployeeTask $task) => [
                'id' => $task->id,
                'personName' => $this->displayName($task->person),
                'title' => $task->title,
                'category' => $task->category,
                'priority' => $task->priority,
                'dueDate' => $task->due_date?->toDateString(),
                'status' => $task->status,
                'employeeVisible' => $task->employee_visible,
                'employeeActionable' => $task->employee_actionable,
                'notes' => $task->notes,
                'createdByName' => $task->creator?->name,
                'completedByName' => $task->completedBy?->name,
                'completedAt' => $task->completed_at?->toIso8601String(),
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'person_id' => ['required', Rule::exists('people', 'id')->where('category', 'staff')],
            'title' => ['required', 'string', 'max:255'],
            'category' => ['required', Rule::in(['onboarding', 'offboarding', 'training', 'compliance', 'other'])],
            'priority' => ['required', Rule::in(['low', 'normal', 'high'])],
            'due_date' => ['nullable', 'date'],
            'employee_visible' => ['required', 'boolean'],
            'employee_actionable' => ['required', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $validated['employee_visible'] = $validated['employee_visible']
            || $validated['employee_actionable'];

        $task = HrEmployeeTask::create([
            ...$validated,
            'created_by' => $request->user()->id,
            'status' => 'pending',
        ]);

        if ($task->employee_visible && $task->person->user) {
            $task->person->user->notify(new HrTaskAssignedNotification($task));
        }

        return back()->with('success', __('HR task created.'));
    }

    public function update(Request $request, HrEmployeeTask $task): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['pending', 'in_progress', 'completed'])],
        ]);
        $completed = $validated['status'] === 'completed';

        $task->update([
            'status' => $validated['status'],
            'completed_by' => $completed ? $request->user()->id : null,
            'completed_at' => $completed ? now() : null,
        ]);

        return back()->with('success', __('HR task updated.'));
    }

    public function destroy(HrEmployeeTask $task): RedirectResponse
    {
        $task->delete();

        return back()->with('success', __('HR task deleted.'));
    }

    private function displayName(Person $person): string
    {
        return $person->company_name
            ?: trim($person->first_name.' '.($person->last_name ?? ''));
    }
}

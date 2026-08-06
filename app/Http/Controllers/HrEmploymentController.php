<?php

namespace App\Http\Controllers;

use App\Models\HrDepartment;
use App\Models\HrEmploymentProfile;
use App\Models\Person;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class HrEmploymentController extends Controller
{
    public function index(): Response
    {
        $staff = Person::query()
            ->where('category', 'staff')
            ->with(['employmentProfile.department', 'employmentProfile.manager'])
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();

        return Inertia::render('human-resources/employees', [
            'departments' => HrDepartment::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
            'staffOptions' => $staff->map(fn (Person $person) => [
                'id' => $person->id,
                'name' => $this->displayName($person),
            ]),
            'employees' => $staff->map(fn (Person $person) => [
                'id' => $person->id,
                'name' => $this->displayName($person),
                'email' => $person->email,
                'staffRole' => $person->staff_role,
                'employment' => $person->employmentProfile ? [
                    'employeeNumber' => $person->employmentProfile->employee_number,
                    'jobTitle' => $person->employmentProfile->job_title,
                    'professionalCategory' => $person->employmentProfile->professional_category,
                    'departmentId' => $person->employmentProfile->department_id,
                    'departmentName' => $person->employmentProfile->department?->name,
                    'managerPersonId' => $person->employmentProfile->manager_person_id,
                    'managerName' => $person->employmentProfile->manager
                        ? $this->displayName($person->employmentProfile->manager)
                        : null,
                    'employmentType' => $person->employmentProfile->employment_type,
                    'status' => $person->employmentProfile->status,
                    'workLocation' => $person->employmentProfile->work_location,
                    'scheduleType' => $person->employmentProfile->schedule_type,
                    'weeklyHours' => $person->employmentProfile->weekly_hours,
                    'startDate' => $person->employmentProfile->start_date?->toDateString(),
                    'probationEndDate' => $person->employmentProfile->probation_end_date?->toDateString(),
                    'endDate' => $person->employmentProfile->end_date?->toDateString(),
                ] : null,
            ]),
        ]);
    }

    public function update(Request $request, Person $staff): RedirectResponse
    {
        abort_unless($staff->category === 'staff', 404);

        $validated = $request->validate([
            'employee_number' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('hr_employment_profiles')->ignore($staff->employmentProfile?->id),
            ],
            'job_title' => ['nullable', 'string', 'max:255'],
            'professional_category' => ['nullable', 'string', 'max:255'],
            'department_id' => ['nullable', Rule::exists('hr_departments', 'id')->where('is_active', true)],
            'manager_person_id' => [
                'nullable',
                Rule::exists('people', 'id')->where('category', 'staff'),
                Rule::notIn([$staff->id]),
            ],
            'employment_type' => ['required', Rule::in(['permanent', 'temporary', 'internship', 'contractor'])],
            'status' => ['required', Rule::in(['active', 'on_leave', 'ended'])],
            'work_location' => ['nullable', 'string', 'max:255'],
            'schedule_type' => ['nullable', Rule::in(['full_time', 'part_time', 'shift', 'flexible', 'other'])],
            'weekly_hours' => ['nullable', 'numeric', 'min:0', 'max:168'],
            'start_date' => ['nullable', 'date'],
            'probation_end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'change_effective_date' => ['nullable', 'date'],
            'change_reason' => ['nullable', 'string', 'max:255'],
        ]);

        $effectiveDate = $validated['change_effective_date'] ?? now()->toDateString();
        $reason = $validated['change_reason'] ?? __('Employment dossier updated');
        unset($validated['change_effective_date'], $validated['change_reason']);

        $existing = $staff->employmentProfile;
        $previous = $existing?->only(array_keys($validated));
        $profile = $staff->employmentProfile()->updateOrCreate(
            ['person_id' => $staff->id],
            $validated,
        );

        $changes = $this->changedValues($profile, $previous, array_keys($validated));
        if ($existing === null || $changes !== []) {
            $staff->contractChanges()->create([
                'changed_by' => $request->user()->id,
                'effective_date' => $effectiveDate,
                'reason' => $reason,
                'previous_values' => $previous,
                'new_values' => $existing === null ? $profile->only(array_keys($validated)) : $changes,
            ]);
        }

        return back()->with('success', __('Employment profile updated.'));
    }

    public function updateSensitive(Request $request, Person $staff): RedirectResponse
    {
        abort_unless($staff->category === 'staff', 404);

        $validated = $request->validate([
            'annual_reference_salary' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'collective_agreement' => ['nullable', 'string', 'max:255'],
            'cost_center' => ['nullable', 'string', 'max:100'],
            'payroll_code' => ['nullable', 'string', 'max:100'],
            'change_effective_date' => ['required', 'date'],
            'change_reason' => ['required', 'string', 'max:255'],
        ]);

        $effectiveDate = $validated['change_effective_date'];
        $reason = $validated['change_reason'];
        unset($validated['change_effective_date'], $validated['change_reason']);

        $profile = $staff->employmentProfile()->firstOrCreate(
            ['person_id' => $staff->id],
            ['employment_type' => 'permanent', 'status' => 'active'],
        );
        $previous = $profile->only(array_keys($validated));
        $profile->update($validated);
        $changes = $this->changedValues($profile, $previous, array_keys($validated));

        if ($changes !== []) {
            $staff->contractChanges()->create([
                'changed_by' => $request->user()->id,
                'effective_date' => $effectiveDate,
                'reason' => $reason,
                'previous_values' => $previous,
                'new_values' => $changes,
            ]);
        }

        return back()->with('success', __('Sensitive employment data updated.'));
    }

    /**
     * @param  array<string, mixed>|null  $previous
     * @param  list<string>  $keys
     * @return array<string, mixed>
     */
    private function changedValues(HrEmploymentProfile $profile, ?array $previous, array $keys): array
    {
        if ($previous === null) {
            return $profile->only($keys);
        }

        return collect($profile->only($keys))
            ->filter(fn (mixed $value, string $key) => (string) ($previous[$key] ?? '') !== (string) ($value ?? ''))
            ->all();
    }

    private function displayName(Person $person): string
    {
        return $person->company_name
            ?: trim($person->first_name.' '.($person->last_name ?? ''));
    }
}

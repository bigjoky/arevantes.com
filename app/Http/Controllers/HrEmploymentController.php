<?php

namespace App\Http\Controllers;

use App\Models\HrDepartment;
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
                    'departmentId' => $person->employmentProfile->department_id,
                    'departmentName' => $person->employmentProfile->department?->name,
                    'managerPersonId' => $person->employmentProfile->manager_person_id,
                    'managerName' => $person->employmentProfile->manager
                        ? $this->displayName($person->employmentProfile->manager)
                        : null,
                    'employmentType' => $person->employmentProfile->employment_type,
                    'status' => $person->employmentProfile->status,
                    'workLocation' => $person->employmentProfile->work_location,
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
            'department_id' => ['nullable', Rule::exists('hr_departments', 'id')->where('is_active', true)],
            'manager_person_id' => [
                'nullable',
                Rule::exists('people', 'id')->where('category', 'staff'),
                Rule::notIn([$staff->id]),
            ],
            'employment_type' => ['required', Rule::in(['permanent', 'temporary', 'internship', 'contractor'])],
            'status' => ['required', Rule::in(['active', 'on_leave', 'ended'])],
            'work_location' => ['nullable', 'string', 'max:255'],
            'weekly_hours' => ['nullable', 'numeric', 'min:0', 'max:168'],
            'start_date' => ['nullable', 'date'],
            'probation_end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        $staff->employmentProfile()->updateOrCreate(
            ['person_id' => $staff->id],
            $validated,
        );

        return back()->with('success', __('Employment profile updated.'));
    }

    private function displayName(Person $person): string
    {
        return $person->company_name
            ?: trim($person->first_name.' '.($person->last_name ?? ''));
    }
}

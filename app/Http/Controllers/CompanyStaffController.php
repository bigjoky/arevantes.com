<?php

namespace App\Http\Controllers;

use App\Models\HrEmploymentProfile;
use App\Models\Person;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CompanyStaffController extends Controller
{
    public function store(Request $request)
    {
        Gate::authorize('manage-staff');
        $names = $request->validate(['first_name' => 'required|string|max:100', 'last_name' => 'nullable|string|max:100', 'employee_number' => 'required|string|max:50|unique:hr_employment_profiles,employee_number']);
        try {
            $person = DB::transaction(function () use ($request, $names) {
                $person = Person::create(['first_name' => $names['first_name'], 'last_name' => $names['last_name'] ?? null, 'category' => 'staff']);
                app(HrEmploymentController::class)->update($request, $person);

                return $person;
            });

        } catch (UniqueConstraintViolationException $exception) {
            if (HrEmploymentProfile::where('employee_number', $names['employee_number'])->exists()) {
                throw ValidationException::withMessages(['employee_number' => __('The employee number is already in use.')]);
            }
            throw $exception;
        }

        return to_route('company.staff.show', $person);
    }

    public function update(Request $request, Person $staff)
    {
        Gate::authorize('manage-staff');
        abort_unless($staff->category === 'staff', 404);
        $request->validate(['expected_fingerprint' => 'required|string|size:64']);
        DB::transaction(function () use ($request, $staff) {
            $person = Person::whereKey($staff->id)->lockForUpdate()->firstOrFail();
            abort_unless(hash_equals(self::fingerprint($person), $request->input('expected_fingerprint')), 409, __('The employee record changed. Reload it before saving.'));
            app(HrEmploymentController::class)->update($request, $person);
        });

        return back();
    }

    public static function fingerprint(Person $staff): string
    {
        $profile = $staff->employmentProfile;

        return hash('sha256', json_encode([$profile?->only(['employee_number', 'job_title', 'department_id', 'employment_type', 'status', 'start_date', 'end_date', 'work_location']), $staff->contractChanges()->max('id')], JSON_THROW_ON_ERROR));
    }
}

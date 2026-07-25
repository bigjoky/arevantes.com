<?php

namespace App\Http\Controllers;

use App\Models\HrDepartment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class HrDepartmentController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('human-resources/departments', [
            'departments' => HrDepartment::query()
                ->withCount('employmentProfiles')
                ->orderByDesc('is_active')
                ->orderBy('name')
                ->get()
                ->map(fn (HrDepartment $department) => [
                    'id' => $department->id,
                    'name' => $department->name,
                    'code' => $department->code,
                    'description' => $department->description,
                    'isActive' => $department->is_active,
                    'peopleCount' => $department->employment_profiles_count,
                ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        HrDepartment::create($this->validatedData($request));

        return back()->with('success', __('Department created.'));
    }

    public function update(Request $request, HrDepartment $department): RedirectResponse
    {
        $department->update($this->validatedData($request, $department));

        return back()->with('success', __('Department updated.'));
    }

    public function destroy(HrDepartment $department): RedirectResponse
    {
        $department->delete();

        return back()->with('success', __('Department deleted.'));
    }

    /**
     * @return array{name: string, code: ?string, description: ?string, is_active: bool}
     */
    private function validatedData(Request $request, ?HrDepartment $department = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('hr_departments')->ignore($department)],
            'code' => ['nullable', 'string', 'max:30', Rule::unique('hr_departments')->ignore($department)],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['required', 'boolean'],
        ]);

        $validated['code'] = filled($validated['code'] ?? null)
            ? strtoupper($validated['code'])
            : null;

        return $validated;
    }
}

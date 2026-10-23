<?php

namespace App\Http\Controllers;

use App\Models\FieldServiceProfile;
use App\Models\User;
use App\Models\WorkOrderTemplate;
use App\Support\BusinessLineCatalog;
use App\Support\TechnicianSkills;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class FieldServiceSettingsController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('operations/field-service', [
            'users' => User::with('fieldServiceProfile')->whereIn('role', ['technician', 'admin'])->orderBy('name')->get(['id', 'name', 'role']),
            'skillOptions' => TechnicianSkills::options(),
            'templates' => WorkOrderTemplate::orderBy('kind')->orderBy('name')->get(),
        ]);
    }

    public function updateProfile(Request $request, User $user): RedirectResponse
    {
        abort_unless($user->hasRole('technician', 'admin'), 422);
        $data = $request->validate([
            'daily_capacity_minutes' => ['required', 'integer', 'between:60,1440'],
            'max_travel_km' => ['required', 'integer', 'between:1,1000'],
            'home_postal_code' => ['nullable', 'string', 'max:20'],
            'service_zones' => ['nullable', 'array', 'max:50'],
            'service_zones.*' => ['string', 'max:100'],
            'skills' => ['nullable', 'array', 'max:50'],
            'skills.*' => ['string', 'max:100'],
            'is_available' => ['required', 'boolean'],
        ]);
        $data['skills'] = array_values(array_unique($data['skills'] ?? []));
        FieldServiceProfile::updateOrCreate(['user_id' => $user->id], $data);

        return back()->with('toast', ['type' => 'success', 'message' => __('Field-service profile updated.')]);
    }

    public function storeTemplate(Request $request): RedirectResponse
    {
        $data = $this->validateTemplate($request);
        WorkOrderTemplate::create([...$data, 'required_skills' => $data['required_skills'] ?? [], 'is_active' => true]);

        return back()->with('toast', ['type' => 'success', 'message' => __('Work-order template created.')]);
    }

    public function updateTemplate(Request $request, WorkOrderTemplate $template): RedirectResponse
    {
        $template->update($this->validateTemplate($request, true));

        return back()->with('toast', ['type' => 'success', 'message' => __('Work-order template updated.')]);
    }

    /** @return array<string, mixed> */
    private function validateTemplate(Request $request, bool $updating = false): array
    {
        return $request->validate([
            'name' => [$updating ? 'sometimes' : 'required', 'string', 'max:255', Rule::unique('work_order_templates')->ignore($request->route('template'))],
            'service_type' => ['nullable', Rule::in(BusinessLineCatalog::INSTALLATION_TYPES)],
            'kind' => [$updating ? 'sometimes' : 'required', Rule::in(['survey', 'installation', 'commissioning', 'maintenance', 'repair'])],
            'title' => [$updating ? 'sometimes' : 'required', 'string', 'max:255'],
            'estimated_duration_minutes' => [$updating ? 'sometimes' : 'required', 'integer', 'between:1,1440'],
            'required_skills' => ['nullable', 'array', 'max:20'],
            'required_skills.*' => ['string', 'max:100'],
            'checklist' => [$updating ? 'sometimes' : 'required', 'array', 'min:1', 'max:100'],
            'checklist.*.label' => ['required', 'string', 'max:255'],
            'checklist.*.is_required' => ['required', 'boolean'],
            'required_evidence' => ['nullable', 'array', 'max:30'],
            'required_evidence.*.key' => ['required', 'alpha_dash', 'max:100'],
            'required_evidence.*.label' => ['required', 'string', 'max:255'],
            'required_evidence.*.kind' => ['required', Rule::in(['photo', 'document'])],
            'required_evidence.*.min_count' => ['required', 'integer', 'between:1,20'],
            'handover_documents' => ['nullable', 'array', 'max:30'],
            'handover_documents.*.key' => ['required', 'alpha_dash', 'max:100'],
            'handover_documents.*.label' => ['required', 'string', 'max:255'],
            'allowed_transitions' => ['nullable', 'array'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }
}

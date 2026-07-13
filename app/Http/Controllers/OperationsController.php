<?php

namespace App\Http\Controllers;

use App\Models\CrmActivity;
use App\Models\Installation;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class OperationsController extends Controller
{
    public function index(Request $request): Response
    {
        $canManageQuotes = $request->user()->can('manage-installations');
        $activities = CrmActivity::with(['installation:id,project_reference,site_name', 'person:id,first_name,last_name', 'assignedUser:id,name'])
            ->when(! $request->user()->hasRole('admin'), fn ($query) => $query->where(fn ($query) => $query->whereNull('assigned_user_id')->orWhere('assigned_user_id', $request->user()->id)))
            ->orderByRaw('completed_at is not null')
            ->orderBy('due_at')
            ->get();

        return Inertia::render('operations/index', [
            'activities' => $activities,
            'quotes' => $canManageQuotes
                ? Quote::with(['installation:id,project_reference,site_name', 'customer:id,first_name,last_name,company_name'])->latest()->get()
                : [],
            'installations' => Installation::orderBy('project_reference')->get(['id', 'project_reference', 'site_name', 'status']),
            'users' => User::orderBy('name')->get(['id', 'name', 'role']),
            'canManageQuotes' => $canManageQuotes,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'installation_id' => ['nullable', 'exists:installations,id'],
            'person_id' => ['nullable', Rule::exists('people', 'id')->where('category', 'customer')],
            'assigned_user_id' => ['nullable', 'exists:users,id'],
            'type' => ['required', Rule::in(['task', 'visit', 'reminder', 'call', 'email', 'meeting'])],
            'title' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'priority' => ['required', Rule::in(['low', 'normal', 'high'])],
            'status' => ['nullable', Rule::in(['pending', 'in_progress', 'completed'])],
            'starts_at' => ['nullable', 'date'],
            'due_at' => ['nullable', 'date'],
        ]);

        $installation = filled($data['installation_id'] ?? null) ? Installation::find($data['installation_id']) : null;
        abort_if($installation?->person_id && filled($data['person_id'] ?? null) && $installation->person_id !== (int) $data['person_id'], 422, __('The installation does not belong to this customer.'));

        $status = $data['status'] ?? 'pending';
        CrmActivity::create([
            ...$data,
            'person_id' => $data['person_id'] ?? $installation?->person_id,
            'status' => $status,
            'completed_at' => $status === 'completed' ? now() : null,
            'created_by' => $request->user()->id,
        ]);

        return back()->with('toast', ['type' => 'success', 'message' => __('Activity created.')]);
    }

    public function update(Request $request, CrmActivity $activity): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(['pending', 'in_progress', 'completed', 'cancelled'])]]);
        $activity->update([...$data, 'completed_at' => $data['status'] === 'completed' ? now() : null]);

        return back()->with('toast', ['type' => 'success', 'message' => __('Activity updated.')]);
    }

    public function destroy(CrmActivity $activity): RedirectResponse
    {
        $activity->delete();

        return back()->with('toast', ['type' => 'success', 'message' => __('Activity deleted.')]);
    }
}

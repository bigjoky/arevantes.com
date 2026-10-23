<?php

namespace App\Http\Controllers;

use App\Models\CompanyProfile;
use App\Models\HrDepartment;
use App\Models\HrEmploymentDocument;
use App\Models\HrEmploymentProfile;
use App\Models\HrLeaveRequest;
use App\Models\Person;
use App\Services\Company\CompanyWorkspace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class CompanyWorkspaceController extends Controller
{
    public function index(Request $request, CompanyWorkspace $workspace)
    {
        $area = match (true) {
            $request->is('company/staff') => 'staff', $request->is('company/documents') => 'documents', $request->is('company/settings') => 'settings', default => 'dashboard'
        };
        if ($area === 'staff') {
            Gate::authorize('manage-staff');
        }
        if ($area === 'documents') {
            abort_unless(Gate::allows('manage-admin') || Gate::allows('manage-staff'), 403);
        }
        if ($area === 'settings') {
            abort_unless(Gate::allows('manage-admin') || Gate::allows('manage-tax'), 403);
        }
        $f = $request->validate(['search' => 'nullable|string|max:150', 'department' => 'nullable|integer|exists:hr_departments,id',
            'state' => ['nullable', Rule::in(['active', 'on_leave', 'ended', 'missing', 'archived'])],
            'category' => ['nullable', Rule::in(CompanyWorkspace::CATEGORIES)], 'sort' => ['nullable', Rule::in(['name', 'recent', 'oldest', 'expiry'])],
            'tab' => ['nullable', Rule::in(['employees', 'positions', 'contracts', 'training', 'absences', 'expiry', 'company', 'preferences', 'users', 'integrations', 'numbering', 'notifications', 'security'])],
            'page' => 'nullable|integer|min:1', 'year' => 'nullable|integer|between:2020,2100']);
        $canStaff = Gate::allows('manage-staff');
        $canDocs = Gate::allows('manage-admin') || $canStaff;
        $company = CompanyProfile::first();
        $staff = null;
        $documents = null;
        $leave = null;
        $certificates = null;
        if ($area === 'staff') {
            $q = $workspace->staff()->when($f['search'] ?? null, fn ($q, $s) => $q->where(fn ($q) => $q->where('first_name', 'like', "%$s%")->orWhere('last_name', 'like', "%$s%")->orWhereHas('employmentProfile', fn ($q) => $q->where('job_title', 'like', "%$s%"))))
                ->when($f['department'] ?? null, fn ($q, $d) => $q->whereHas('employmentProfile', fn ($q) => $q->where('department_id', $d)))
                ->when($f['state'] ?? null, fn ($q, $s) => $s === 'missing' ? $q->whereDoesntHave('employmentProfile') : $q->whereHas('employmentProfile', fn ($q) => $q->where('status', $s)));
            if (($f['sort'] ?? 'name') === 'recent') {
                $q->orderByDesc('id');
            } else {
                $q->orderBy('first_name')->orderBy('last_name')->orderBy('id');
            }
            $staff = $q->paginate(10)->withQueryString()->through(fn ($p) => [...$workspace->staffRow($p), 'href' => '/company/staff/'.$p->id.'?'.http_build_query(collect($f)->only(['search', 'department', 'state', 'sort', 'page'])->mapWithKeys(fn ($v, $k) => ['return_'.$k => $v])->all())]);
            if (($f['tab'] ?? '') === 'absences') {
                $leave = HrLeaveRequest::whereHas('person', fn ($q) => $q->where('category', 'staff'))->with('person:id,first_name,last_name')->latest('start_date')->paginate(10)->withQueryString()->through(fn ($l) => ['id' => $l->id, 'name' => trim($l->person->first_name.' '.$l->person->last_name), 'start' => $l->start_date->toDateString(), 'end' => $l->end_date->toDateString(), 'status' => $l->status, 'href' => '/company/staff/'.$l->person_id]);
            }
            if (($f['tab'] ?? '') === 'training') {
                $certificates = HrEmploymentDocument::whereHas('person', fn ($q) => $q->where('category', 'staff'))->whereIn('category', ['certificate', 'prevention'])->when(! Gate::allows('view-sensitive-hr'), fn ($q) => $q->whereRaw('1=0'))->with('person:id,first_name,last_name')->latest('id')->paginate(10)->withQueryString()->through(fn ($d) => ['id' => $d->id, 'title' => $d->title, 'name' => trim($d->person->first_name.' '.$d->person->last_name), 'expires' => $d->expires_at?->toDateString(), 'href' => '/human-resources/employees/'.$d->person_id.'/documents/'.$d->id]);
            }
        }
        if ($area === 'documents') {
            $q = $workspace->documents()->with('latestVersion')->when($f['search'] ?? null, fn ($q, $s) => $q->where(fn ($q) => $q->where('title', 'like', "%$s%")->orWhere('description', 'like', "%$s%")))
                ->when($f['category'] ?? null, fn ($q, $c) => $q->where('category', $c))->where('status', ($f['state'] ?? 'active') === 'archived' ? 'archived' : 'active');
            match ($f['sort'] ?? 'recent') {
                'name' => $q->orderBy('title'), 'oldest' => $q->oldest('created_at'), 'expiry' => $q->orderBy('expires_at'), default => $q->latest('updated_at')
            };
            $documents = $q->orderBy('id')->paginate(10)->withQueryString()->through(fn ($d) => [...$workspace->documentRow($d), 'href' => '/company/documents/'.$d->id.'?'.http_build_query(collect($f)->only(['search', 'category', 'state', 'sort', 'page'])->mapWithKeys(fn ($v, $k) => ['return_'.$k => $v])->all())]);
        }
        $expiries = $workspace->expiryRows();
        $active = $canStaff ? HrEmploymentProfile::where('status', 'active')->whereIn('employment_type', ['permanent', 'temporary', 'internship'])->whereHas('person', fn ($q) => $q->where('category', 'staff'))->count() : null;

        return Inertia::render('company/workspace', [
            'area' => $area, 'filters' => (object) $f, 'company' => $company?->only(['legal_name', 'trade_name', 'tax_id', 'address', 'postal_code', 'city', 'province', 'country', 'email', 'phone', 'website']),
            'metrics' => ['employees' => $active, 'documents' => $canDocs ? $workspace->documents()->where('status', 'active')->count() : null, 'configuration' => $company ? 'registered' : 'missing', 'renewals' => $workspace->expiryCount()],
            'distributions' => $workspace->distributions(), 'staff' => $staff, 'documents' => $documents, 'leave' => $leave, 'certificates' => $certificates,
            'departments' => $canStaff ? HrDepartment::orderBy('name')->get(['id', 'name', 'is_active']) : [],
            'recentDocuments' => $canDocs ? $workspace->documents()->where('status', 'active')->with('latestVersion')->latest('updated_at')->limit(5)->get()->map(fn ($d) => $workspace->documentRow($d)) : [],
            'categoryCounts' => $canDocs ? $workspace->documents()->where('status', 'active')->selectRaw('category,count(*) total')->groupBy('category')->pluck('total', 'category') : [],
            'expiries' => $expiries, 'tasks' => $workspace->tasks(),
            'capabilities' => ['staff' => $canStaff, 'documents' => $canDocs, 'upload' => Gate::allows('manage-admin'), 'settings' => Gate::allows('manage-admin') || Gate::allows('manage-tax'), 'editCompany' => Gate::allows('manage-tax'), 'users' => Gate::allows('manage-access'), 'audit' => Gate::allows('export-audit'), 'backups' => Gate::allows('manage-admin'), 'sensitive' => Gate::allows('view-sensitive-hr'), 'folders' => false, 'trainingRegister' => false, 'multiCompany' => false],
        ]);
    }

    public function staff(Request $request, Person $staff, CompanyWorkspace $workspace)
    {
        Gate::authorize('manage-staff');
        abort_unless($staff->category === 'staff', 404);
        $request->validate(['tab' => ['nullable', Rule::in(['summary', 'contracts', 'training', 'absences', 'documents', 'history'])], 'return_search' => 'nullable|string|max:150', 'return_page' => 'nullable|integer|min:1', 'return_department' => 'nullable|integer', 'return_state' => 'nullable|string|max:20', 'return_sort' => 'nullable|string|max:20']);
        $person = $workspace->staff()->findOrFail($staff->id);
        $canSensitive = Gate::allows('view-sensitive-hr');
        $documents = $staff->employmentDocuments()->when($request->input('tab') === 'training', fn ($q) => $q->whereIn('category', ['certificate', 'prevention']))->when(! $canSensitive, fn ($q) => $q->whereRaw('1=0'))->latest('id')->paginate(10)->withQueryString()->through(fn ($d) => ['id' => $d->id, 'title' => $d->title, 'category' => $d->category, 'mime' => $d->mime_type, 'size' => $d->size, 'expires' => $d->expires_at?->toDateString(), 'href' => '/human-resources/employees/'.$staff->id.'/documents/'.$d->id]);
        $history = $staff->contractChanges()->with('changedBy:id,name')->latest('effective_date')->paginate(10)->withQueryString()->through(fn ($c) => ['id' => $c->id, 'date' => $c->effective_date->toDateString(), 'actor' => $c->changedBy?->name, 'fields' => array_values(array_intersect(array_keys($c->new_values ?? []), ['job_title', 'department_id', 'employment_type', 'status', 'start_date', 'end_date', 'work_location', 'manager_person_id']))]);
        $leave = $staff->leaveRequests()->latest('start_date')->select(['id', 'start_date', 'end_date', 'status'])->paginate(10)->withQueryString()->through(fn ($l) => ['id' => $l->id, 'start' => $l->start_date->toDateString(), 'end' => $l->end_date->toDateString(), 'status' => $l->status]);

        return Inertia::render('company/employee', ['employee' => $workspace->staffRow($person), 'documents' => $documents, 'history' => $history, 'leave' => $leave, 'tab' => $request->input('tab', 'summary'), 'canSensitive' => $canSensitive, 'departments' => HrDepartment::where('is_active', true)->orderBy('name')->get(['id', 'name']), 'fingerprint' => CompanyStaffController::fingerprint($staff), 'returnFilters' => $request->only(['return_search', 'return_page', 'return_department', 'return_state', 'return_sort'])]);
    }
}

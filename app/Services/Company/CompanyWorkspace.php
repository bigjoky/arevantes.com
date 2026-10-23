<?php

namespace App\Services\Company;

use App\Models\CompanyDocument;
use App\Models\CompanyProfile;
use App\Models\HrDepartment;
use App\Models\HrEmployeeTask;
use App\Models\HrEmploymentProfile;
use App\Models\Person;
use Illuminate\Support\Facades\Gate;

class CompanyWorkspace
{
    public const CATEGORIES = ['general', 'legal', 'tax', 'insurance', 'fleet', 'prevention', 'contracts', 'other'];

    public function documents()
    {
        return CompanyDocument::query()->when(! Gate::allows('manage-admin') && ! Gate::allows('manage-staff'), fn ($q) => $q->whereRaw('1=0'))->where('company_profile_id', CompanyProfile::value('id'))
            ->when(! Gate::allows('manage-admin'), fn ($q) => $q->where('visibility', 'staff'));
    }

    public function staff()
    {
        return Person::query()->where('category', 'staff')->select(['id', 'first_name', 'last_name', 'company_name', 'staff_role'])
            ->with(['employmentProfile' => fn ($q) => $q->select(['id', 'person_id', 'department_id', 'employee_number', 'job_title', 'employment_type', 'status', 'start_date', 'end_date', 'probation_end_date', 'work_location'])->with('department:id,name')]);
    }

    public function staffRow(Person $staff): array
    {
        $profile = $staff->employmentProfile;

        return ['id' => $staff->id, 'name' => trim($staff->first_name.' '.$staff->last_name) ?: $staff->company_name,
            'job' => $profile?->job_title, 'department' => $profile?->department?->name,
            'department_id' => $profile?->department_id, 'type' => $profile?->employment_type,
            'status' => $profile?->status, 'number' => $profile?->employee_number,
            'start' => $profile?->start_date?->toDateString(), 'end' => $profile?->end_date?->toDateString(),
            'probation' => $profile?->probation_end_date?->toDateString(), 'location' => $profile?->work_location,
            'href' => '/company/staff/'.$staff->id];
    }

    public function documentRow(CompanyDocument $d): array
    {
        return ['id' => $d->id, 'title' => $d->title, 'description' => $d->description, 'category' => $d->category,
            'visibility' => $d->visibility, 'status' => $d->status, 'version' => $d->version, 'revision' => $d->revision,
            'expires' => $d->expires_at?->toDateString(), 'created' => $d->created_at?->toIso8601String(),
            'updated' => $d->updated_at?->toIso8601String(), 'mime' => $d->latestVersion?->mime_type,
            'size' => $d->latestVersion?->size, 'href' => '/company/documents/'.$d->id];
    }

    public function expiryRows(): array
    {
        $rows = collect();
        if (Gate::allows('manage-staff')) {
            $profiles = HrEmploymentProfile::whereHas('person', fn ($q) => $q->where('category', 'staff'))
                ->whereBetween('end_date', [today()->toDateString(), today()->addDays(30)->toDateString()])
                ->where('status', '!=', 'ended')->with('person:id,first_name,last_name,company_name')->orderBy('end_date')->limit(20)->get();
            foreach ($profiles as $p) {
                $rows->push(['id' => 'contract-'.$p->id, 'title' => trim($p->person->first_name.' '.$p->person->last_name), 'type' => 'contract', 'date' => $p->end_date->toDateString(), 'href' => '/company/staff/'.$p->person_id]);
            }
        }
        if (Gate::allows('manage-staff') || Gate::allows('manage-admin')) {
            foreach ($this->documents()->where('status', 'active')->whereBetween('expires_at', [today()->toDateString(), today()->addDays(30)->toDateString()])->orderBy('expires_at')->limit(20)->get() as $d) {
                $rows->push(['id' => 'document-'.$d->id, 'title' => $d->title, 'type' => $d->category, 'date' => $d->expires_at->toDateString(), 'href' => '/company/documents/'.$d->id]);
            }
        }

        return $rows->sortBy('date')->values()->all();
    }

    public function expiryCount(): ?int
    {
        if (! Gate::allows('manage-staff') && ! Gate::allows('manage-admin')) {
            return null;
        }
        $count = $this->documents()->where('status', 'active')->whereBetween('expires_at', [today()->toDateString(), today()->addDays(30)->toDateString()])->count();
        if (Gate::allows('manage-staff')) {
            $count += HrEmploymentProfile::whereHas('person', fn ($q) => $q->where('category', 'staff'))->where('status', '!=', 'ended')->whereBetween('end_date', [today()->toDateString(), today()->addDays(30)->toDateString()])->count();
        }

        return $count;
    }

    public function distributions(): array
    {
        if (! Gate::allows('manage-staff')) {
            return ['departments' => [], 'contracts' => []];
        }
        $base = HrEmploymentProfile::where('status', 'active')->whereIn('employment_type', ['permanent', 'temporary', 'internship'])->whereHas('person', fn ($q) => $q->where('category', 'staff'));
        $departments = (clone $base)->whereNotNull('department_id')->selectRaw('department_id, count(*) total')->groupBy('department_id')->get();
        $names = HrDepartment::whereIn('id', $departments->pluck('department_id'))->pluck('name', 'id');

        return ['departments' => $departments->map(fn ($r) => ['name' => $names[$r->department_id] ?? null, 'total' => (int) $r->total]),
            'contracts' => (clone $base)->selectRaw('employment_type as name, count(*) total')->groupBy('employment_type')->get()->map(fn ($r) => ['name' => $r->name, 'total' => (int) $r->total])];
    }

    public function tasks(): array
    {
        return Gate::allows('manage-staff') ? HrEmployeeTask::whereNotIn('status', ['completed', 'cancelled'])->orderBy('due_date')->limit(8)->get(['id', 'title', 'due_date', 'status'])->map(fn ($t) => ['id' => $t->id, 'title' => $t->title, 'due' => $t->due_date?->toDateString(), 'status' => $t->status, 'href' => '/human-resources/tasks'])->all() : [];
    }
}

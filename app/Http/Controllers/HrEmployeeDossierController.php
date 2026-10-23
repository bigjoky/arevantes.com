<?php

namespace App\Http\Controllers;

use App\Models\HrEmergencyContact;
use App\Models\HrEmploymentDocument;
use App\Models\Person;
use App\Rules\SafeUpload;
use App\Services\Security\SecurityEventRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class HrEmployeeDossierController extends Controller
{
    public function show(Person $staff): Response
    {
        $this->ensureStaff($staff);
        $staff->load([
            'employmentProfile.department',
            'employmentProfile.manager',
            'contractChanges.changedBy',
            'emergencyContacts',
            'employmentDocuments.uploadedBy',
        ]);

        $profile = $staff->employmentProfile;
        $canViewSensitive = Gate::allows('view-sensitive-hr');
        $canManageSensitive = Gate::allows('manage-sensitive-hr');

        return Inertia::render('human-resources/dossier', [
            'canViewSensitive' => $canViewSensitive,
            'canManageSensitive' => $canManageSensitive,
            'employee' => [
                'id' => $staff->id,
                'name' => $this->displayName($staff),
                'email' => $canViewSensitive ? $staff->email : null,
                'phone' => $canViewSensitive ? $staff->phone : null,
                'staffRole' => $staff->staff_role,
                'employment' => $profile ? [
                    'employeeNumber' => $profile->employee_number,
                    'jobTitle' => $profile->job_title,
                    'professionalCategory' => $profile->professional_category,
                    'department' => $profile->department?->name,
                    'manager' => $profile->manager ? $this->displayName($profile->manager) : null,
                    'employmentType' => $profile->employment_type,
                    'status' => $profile->status,
                    'workLocation' => $profile->work_location,
                    'scheduleType' => $profile->schedule_type,
                    'weeklyHours' => $profile->weekly_hours,
                    'startDate' => $profile->start_date?->toDateString(),
                    'probationEndDate' => $profile->probation_end_date?->toDateString(),
                    'endDate' => $profile->end_date?->toDateString(),
                    'annualReferenceSalary' => $canViewSensitive ? $profile->annual_reference_salary : null,
                    'collectiveAgreement' => $canViewSensitive ? $profile->collective_agreement : null,
                    'costCenter' => $canViewSensitive ? $profile->cost_center : null,
                    'payrollCode' => $canViewSensitive ? $profile->payroll_code : null,
                ] : null,
                'history' => $staff->contractChanges
                    ->sortByDesc('effective_date')
                    ->values()
                    ->map(fn ($change) => [
                        'id' => $change->id,
                        'effectiveDate' => $change->effective_date->toDateString(),
                        'reason' => $change->reason,
                        'changedBy' => $change->changedBy?->name,
                        'changes' => $canViewSensitive ? $change->new_values : $this->withoutSensitiveValues($change->new_values),
                    ]),
                'emergencyContacts' => $canViewSensitive ? $staff->emergencyContacts
                    ->sortByDesc('is_primary')
                    ->values()
                    ->map(fn (HrEmergencyContact $contact) => [
                        'id' => $contact->id,
                        'name' => $contact->name,
                        'relationship' => $contact->relationship,
                        'phone' => $contact->phone,
                        'preventionNotes' => $contact->prevention_notes,
                        'isPrimary' => $contact->is_primary,
                    ]) : [],
                'documents' => $staff->employmentDocuments
                    ->when(! $canViewSensitive, fn ($documents) => $documents->filter(fn ($d) => $d->visibility === 'employee' && request()->user()->person_id === $staff->id))
                    ->sortByDesc('created_at')
                    ->values()
                    ->map(fn (HrEmploymentDocument $document) => [
                        'id' => $document->id,
                        'category' => $document->category,
                        'title' => $document->title,
                        'originalName' => $document->original_name,
                        'size' => $document->size,
                        'visibility' => $document->visibility,
                        'expiresAt' => $document->expires_at?->toDateString(),
                        'isExpired' => $document->expires_at?->isPast() ?? false,
                        'uploadedBy' => $document->uploadedBy?->name,
                    ]),
            ],
        ]);
    }

    public function storeContact(Request $request, Person $staff): RedirectResponse
    {
        $this->ensureStaff($staff);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'relationship' => ['nullable', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:100'],
            'prevention_notes' => ['nullable', 'string', 'max:2000'],
            'is_primary' => ['boolean'],
        ]);

        DB::transaction(function () use ($staff, $data) {
            Person::query()->whereKey($staff->id)->lockForUpdate()->firstOrFail();
            if ($data['is_primary'] ?? false) {
                $staff->emergencyContacts()->update(['is_primary' => false]);
            }
            $staff->emergencyContacts()->create($data);
        });

        return back()->with('success', __('Emergency contact added.'));
    }

    public function destroyContact(Person $staff, HrEmergencyContact $contact): RedirectResponse
    {
        $this->ensureStaff($staff);
        abort_unless($contact->person_id === $staff->id, 404);
        $contact->delete();

        return back()->with('success', __('Emergency contact deleted.'));
    }

    public function storeDocument(Request $request, Person $staff): RedirectResponse
    {
        $this->ensureStaff($staff);
        $data = $request->validate([
            'category' => ['required', Rule::in(['contract', 'identity', 'certificate', 'prevention', 'payroll', 'other'])],
            'title' => ['required', 'string', 'max:255'],
            'visibility' => ['required', Rule::in(['hr_only', 'employee'])],
            'expires_at' => ['nullable', 'date'],
            'file' => ['required', 'file', new SafeUpload(['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'], 12288)],
        ]);

        $file = $data['file'];
        $path = null;
        try {
            $path = $file->store("human-resources/{$staff->id}", config('filesystems.private'));
            DB::transaction(fn () => $staff->employmentDocuments()->create([
                ...$data,
                'uploaded_by' => $request->user()->id,
                'original_name' => $file->getClientOriginalName(),
                'path' => $path,
                'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                'size' => $file->getSize(),
                'file' => null,
            ]));
        } catch (\Throwable $exception) {
            if ($path) {
                Storage::disk(config('filesystems.private'))->delete($path);
            }
            throw $exception;
        }

        return back()->with('success', __('Employment document uploaded.'));
    }

    public function downloadDocument(Person $staff, HrEmploymentDocument $document, SecurityEventRecorder $events): StreamedResponse
    {
        $this->ensureStaff($staff);
        abort_unless($document->person_id === $staff->id && Storage::disk(config('filesystems.private'))->exists($document->path), 404);
        abort_unless(Gate::allows('view-sensitive-hr') || ($document->visibility === 'employee' && request()->user()->person_id === $staff->id), 403);
        $events->record('hr_document_downloaded', request()->user(), [
            'document_id' => $document->id,
            'person_id' => $staff->id,
        ]);

        return Storage::disk(config('filesystems.private'))->download($document->path, $document->original_name, [
            'Content-Type' => $document->mime_type,
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function destroyDocument(Person $staff, HrEmploymentDocument $document): RedirectResponse
    {
        $this->ensureStaff($staff);
        abort_unless($document->person_id === $staff->id, 404);
        $document->delete();

        return back()->with('success', __('Employment document deleted.'));
    }

    public function export(Person $staff, SecurityEventRecorder $events): StreamedResponse
    {
        $this->ensureStaff($staff);
        $staff->load('employmentProfile.department');
        $profile = $staff->employmentProfile;
        $events->record('hr_dossier_exported', request()->user(), ['person_id' => $staff->id], 'warning');

        return response()->streamDownload(function () use ($staff, $profile) {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['employee_number', 'name', 'tax_id', 'job_title', 'professional_category', 'contract_type', 'weekly_hours', 'work_location', 'department', 'start_date', 'end_date', 'annual_reference_salary', 'collective_agreement', 'cost_center', 'payroll_code']);
            fputcsv($output, array_map($this->csvSafe(...), [
                $profile?->employee_number,
                $this->displayName($staff),
                $staff->tax_id,
                $profile?->job_title,
                $profile?->professional_category,
                $profile?->employment_type,
                $profile?->weekly_hours,
                $profile?->work_location,
                $profile?->department?->name,
                $profile?->start_date?->toDateString(),
                $profile?->end_date?->toDateString(),
                $profile?->annual_reference_salary,
                $profile?->collective_agreement,
                $profile?->cost_center,
                $profile?->payroll_code,
            ]));
            fclose($output);
        }, "gestoria-empleado-{$staff->id}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function ensureStaff(Person $staff): void
    {
        abort_unless($staff->category === 'staff', 404);
    }

    /** @param array<string, mixed> $values */
    private function withoutSensitiveValues(array $values): array
    {
        return collect($values)->except([
            'annual_reference_salary',
            'collective_agreement',
            'cost_center',
            'payroll_code',
        ])->all();
    }

    private function displayName(Person $person): string
    {
        return $person->company_name ?: trim($person->first_name.' '.($person->last_name ?? ''));
    }

    private function csvSafe(mixed $value): mixed
    {
        if (is_string($value) && preg_match('/^[=+\-@\t\r]/u', $value) === 1) {
            return "'{$value}";
        }

        return $value;
    }
}

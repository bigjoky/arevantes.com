<?php

namespace App\Http\Controllers;

use App\Models\HrLeaveAttachment;
use App\Models\HrLeaveBalance;
use App\Models\HrLeavePolicy;
use App\Models\HrLeaveRequest;
use App\Models\Person;
use App\Notifications\LeaveRequestDecisionNotification;
use App\Rules\SafeUpload;
use App\Services\HumanResources\LeaveBalanceCalculator;
use App\Services\HumanResources\LeaveCoverageService;
use App\Services\HumanResources\LeaveRequestCreator;
use App\Services\Security\SecurityEventRecorder;
use App\Services\Work\TechnicianSchedule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class HrLeaveController extends Controller
{
    public function index(LeaveBalanceCalculator $balances, LeaveCoverageService $coverage): Response|RedirectResponse
    {
        if (Gate::denies('view-sensitive-hr')) {
            return to_route('company.staff', ['tab' => 'absences']);
        }
        $staff = Person::query()
            ->where('category', 'staff')
            ->with('user:id,person_id')
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();
        $policies = HrLeavePolicy::query()->orderBy('name')->get();

        return Inertia::render('human-resources/leave', [
            'staff' => $staff->map(fn (Person $person) => [
                'id' => $person->id,
                'name' => $this->displayName($person),
                'balances' => $policies->where('is_active', true)->map(fn (HrLeavePolicy $policy) => $balances->calculate($person, $policy, now()->year))->values(),
            ]),
            'policies' => $policies->map(fn (HrLeavePolicy $policy) => [
                'id' => $policy->id,
                'type' => $policy->type,
                'name' => $policy->name,
                'annualAllowanceDays' => $policy->annual_allowance_days,
                'accrualMethod' => $policy->accrual_method,
                'dayCountMethod' => $policy->day_count_method,
                'carryoverLimitDays' => $policy->carryover_limit_days,
                'requiresAttachment' => $policy->requires_attachment,
                'isActive' => $policy->is_active,
            ]),
            'requests' => HrLeaveRequest::query()
                ->with(['person.user', 'reviewer:id,name', 'substitute', 'attachments'])
                ->orderByRaw("case when status = 'pending' then 0 else 1 end")
                ->orderByDesc('start_date')
                ->get()
                ->map(fn (HrLeaveRequest $leave) => [
                    'id' => $leave->id,
                    'personId' => $leave->person_id,
                    'personName' => $this->displayName($leave->person),
                    'type' => $leave->type,
                    'startDate' => $leave->start_date->toDateString(),
                    'endDate' => $leave->end_date->toDateString(),
                    'requestedDays' => $leave->requested_days,
                    'status' => $leave->status,
                    'notes' => $leave->notes,
                    'decisionNotes' => $leave->decision_notes,
                    'reviewerName' => $leave->reviewer?->name,
                    'reviewedAt' => $leave->reviewed_at?->toIso8601String(),
                    'substitutePersonId' => $leave->substitute_person_id,
                    'substituteName' => $leave->substitute ? $this->displayName($leave->substitute) : null,
                    'attachments' => $leave->attachments->map(fn (HrLeaveAttachment $attachment) => [
                        'id' => $attachment->id,
                        'name' => $attachment->original_name,
                    ]),
                    'coverageConflicts' => $coverage->conflicts($leave)->map(fn ($order) => [
                        'id' => $order->id,
                        'number' => $order->number,
                        'title' => $order->title,
                        'scheduledStart' => $order->scheduled_start?->toIso8601String(),
                    ]),
                ]),
        ]);
    }

    public function store(Request $request, LeaveRequestCreator $creator): RedirectResponse
    {
        $validated = $request->validate([
            'person_id' => ['required', Rule::exists('people', 'id')->where('category', 'staff')],
            'type' => ['required', Rule::in(['vacation', 'sick', 'personal', 'other'])],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'attachment' => ['nullable', 'file', new SafeUpload(['pdf', 'jpg', 'jpeg', 'png'], 12288)],
        ]);

        $person = Person::findOrFail($validated['person_id']);
        $creator->create($person, collect($validated)->except('person_id')->all());

        return back()->with('success', __('Leave request created.'));
    }

    public function update(
        Request $request,
        HrLeaveRequest $leave,
        LeaveCoverageService $coverage,
        LeaveBalanceCalculator $balances,
    ): RedirectResponse {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['approved', 'rejected', 'cancelled'])],
            'decision_notes' => ['nullable', 'string', 'max:2000'],
            'substitute_person_id' => [
                'nullable',
                Rule::exists('people', 'id')->where('category', 'staff'),
                Rule::notIn([$leave->person_id]),
            ],
        ]);

        $leave = DB::transaction(function () use ($request, $leave, $validated, $coverage, $balances) {
            $lockedLeave = HrLeaveRequest::query()
                ->with(['person.user', 'person.employmentProfile'])
                ->lockForUpdate()
                ->findOrFail($leave->id);
            if ($lockedLeave->status !== 'pending') {
                throw ValidationException::withMessages(['status' => __('Only pending leave requests can be reviewed.')]);
            }

            $reviewed = in_array($validated['status'], ['approved', 'rejected'], true);
            if ($validated['status'] === 'approved') {
                $policy = HrLeavePolicy::query()->where('type', $lockedLeave->type)->firstOrFail();
                $balances->assertApprovable($lockedLeave->person, $policy, $lockedLeave);
                $conflicts = $coverage->conflicts($lockedLeave);
                if ($conflicts->isNotEmpty() && empty($validated['substitute_person_id'])) {
                    throw ValidationException::withMessages([
                        'substitute_person_id' => __('A substitute is required because this absence leaves scheduled work orders uncovered.'),
                    ]);
                }
                if (! empty($validated['substitute_person_id'])) {
                    $substitute = Person::query()
                        ->with(['user', 'employmentProfile'])
                        ->whereKey($validated['substitute_person_id'])
                        ->lockForUpdate()
                        ->firstOrFail();
                    $coverage->assertSubstituteAvailable($substitute, $lockedLeave);
                    $coverage->reassign($conflicts, $substitute);
                }
            }

            if ($validated['status'] === 'approved') {
                app(TechnicianSchedule::class)->lockTechnicians([$lockedLeave->person->user?->id]);
                if ($coverage->conflicts($lockedLeave)->isNotEmpty()) {
                    throw ValidationException::withMessages(['status' => __('Scheduled work changed while this absence was being reviewed. Refresh and review its coverage.')]);
                }
            }

            $lockedLeave->update([
                ...$validated,
                'reviewed_by' => $reviewed ? $request->user()->id : null,
                'reviewed_at' => $reviewed ? now() : null,
            ]);

            return $lockedLeave;
        });

        if (in_array($leave->status, ['approved', 'rejected'], true) && $leave->person->user) {
            $leave->person->user->notify(new LeaveRequestDecisionNotification($leave));
        }

        return back()->with('success', __('Leave request updated.'));
    }

    public function updatePolicy(Request $request, HrLeavePolicy $policy): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'annual_allowance_days' => ['required', 'numeric', 'min:0', 'max:366'],
            'accrual_method' => ['required', Rule::in(['annual', 'monthly', 'none'])],
            'day_count_method' => ['sometimes', Rule::in(['calendar_days', 'working_days'])],
            'carryover_limit_days' => ['required', 'numeric', 'min:0', 'max:366'],
            'requires_attachment' => ['boolean'],
            'is_active' => ['boolean'],
        ]);
        $data['day_count_method'] ??= $policy->day_count_method;
        $policy->update($data);

        return back()->with('success', __('Leave policy updated.'));
    }

    public function updateBalance(Request $request, Person $staff, HrLeavePolicy $policy): RedirectResponse
    {
        abort_unless($staff->category === 'staff', 404);
        $data = $request->validate([
            'year' => ['required', 'integer', 'min:2020', 'max:2100'],
            'carryover_days' => ['required', 'numeric', 'min:0', 'max:366'],
            'adjustment_days' => ['required', 'numeric', 'min:-366', 'max:366'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $data['carryover_days'] = min((float) $data['carryover_days'], (float) $policy->carryover_limit_days);
        HrLeaveBalance::updateOrCreate(
            ['person_id' => $staff->id, 'policy_id' => $policy->id, 'year' => $data['year']],
            $data,
        );

        return back()->with('success', __('Leave balance updated.'));
    }

    public function downloadAttachment(HrLeaveRequest $leave, HrLeaveAttachment $attachment, SecurityEventRecorder $events): StreamedResponse
    {
        abort_unless($attachment->leave_request_id === $leave->id && Storage::disk(config('filesystems.private'))->exists($attachment->path), 404);
        $events->record('hr_leave_attachment_downloaded', request()->user(), [
            'leave_request_id' => $leave->id,
            'attachment_id' => $attachment->id,
        ]);

        return Storage::disk(config('filesystems.private'))->download($attachment->path, $attachment->original_name, [
            'Content-Type' => $attachment->mime_type,
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function destroy(HrLeaveRequest $leave): RedirectResponse
    {
        abort_unless(in_array($leave->status, ['pending', 'cancelled'], true), 422);

        $leave->delete();

        return back()->with('success', __('Leave request deleted.'));
    }

    private function displayName(Person $person): string
    {
        return $person->company_name
            ?: trim($person->first_name.' '.($person->last_name ?? ''));
    }
}

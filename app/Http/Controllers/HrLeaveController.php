<?php

namespace App\Http\Controllers;

use App\Models\HrLeaveRequest;
use App\Models\Person;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class HrLeaveController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('human-resources/leave', [
            'staff' => Person::query()
                ->where('category', 'staff')
                ->orderBy('first_name')
                ->orderBy('last_name')
                ->get()
                ->map(fn (Person $person) => [
                    'id' => $person->id,
                    'name' => $this->displayName($person),
                ]),
            'requests' => HrLeaveRequest::query()
                ->with(['person', 'reviewer:id,name'])
                ->orderByRaw("case when status = 'pending' then 0 else 1 end")
                ->orderByDesc('start_date')
                ->get()
                ->map(fn (HrLeaveRequest $leave) => [
                    'id' => $leave->id,
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
                ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'person_id' => ['required', Rule::exists('people', 'id')->where('category', 'staff')],
            'type' => ['required', Rule::in(['vacation', 'sick', 'personal', 'other'])],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $validated['requested_days'] = CarbonImmutable::parse($validated['start_date'])
            ->diffInDays(CarbonImmutable::parse($validated['end_date'])) + 1;
        $validated['status'] = 'pending';

        HrLeaveRequest::create($validated);

        return back()->with('success', __('Leave request created.'));
    }

    public function update(Request $request, HrLeaveRequest $leave): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['pending', 'approved', 'rejected', 'cancelled'])],
            'decision_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $reviewed = in_array($validated['status'], ['approved', 'rejected'], true);
        $leave->update([
            ...$validated,
            'reviewed_by' => $reviewed ? $request->user()->id : null,
            'reviewed_at' => $reviewed ? now() : null,
        ]);

        return back()->with('success', __('Leave request updated.'));
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

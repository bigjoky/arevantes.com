<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ManagesPersonProfiles;
use App\Models\Person;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StaffController extends Controller
{
    use ManagesPersonProfiles;

    /**
     * Show staff profiles.
     */
    public function index(): Response
    {
        return Inertia::render('people/index', [
            'people' => Person::query()
                ->where('category', 'staff')
                ->with('user:id,person_id,email,role')
                ->withCount('installations')
                ->latest()
                ->get()
                ->map(fn (Person $person) => [
                    ...$this->personSummary($person),
                    'access' => $person->user ? ['email' => $person->user->email, 'role' => $person->user->role] : null,
                ]),
            ...$this->sectionProps('staff'),
        ]);
    }

    /**
     * Show the staff creation form.
     */
    public function create(): Response
    {
        return Inertia::render('people/create', [
            'category' => 'staff',
            ...$this->sectionProps('staff'),
        ]);
    }

    /**
     * Show a staff profile.
     */
    public function show(Person $staff): Response
    {
        $this->ensurePersonCategory($staff, 'staff');
        $staff->load('user:id,person_id,email,role,created_at');

        return Inertia::render('people/show', [
            'person' => [
                ...$this->personSummary($staff),
                'tax_id' => $staff->tax_id,
                'lead_source' => $staff->lead_source,
                'billing_email' => $staff->billing_email,
                'address_line_1' => $staff->address_line_1,
                'address_line_2' => $staff->address_line_2,
                'province_state' => $staff->province_state,
                'locality' => $staff->locality,
                'postal_code' => $staff->postal_code,
                'preferred_language' => $staff->preferred_language,
                'emergency_contact_name' => $staff->emergency_contact_name,
                'emergency_contact_phone' => $staff->emergency_contact_phone,
                'notes' => $staff->notes,
                'installations' => [],
                'access' => $staff->user ? [
                    'id' => $staff->user->id,
                    'email' => $staff->user->email,
                    'role' => $staff->user->role,
                    'created_at' => $staff->user->created_at->toIso8601String(),
                ] : null,
            ],
            'availableAccessUsers' => $staff->user || ! request()->user()->can('manage-access') ? [] : User::query()
                ->whereNull('person_id')
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'role'])
                ->map(fn (User $user) => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                ]),
            ...$this->sectionProps('staff'),
        ]);
    }

    /**
     * Edit a staff profile.
     */
    public function edit(Person $staff): Response
    {
        $this->ensurePersonCategory($staff, 'staff');

        return Inertia::render('people/edit', [
            'person' => [
                ...$this->personSummary($staff),
                'tax_id' => $staff->tax_id,
                'lead_source' => $staff->lead_source,
                'billing_email' => $staff->billing_email,
                'address_line_1' => $staff->address_line_1,
                'address_line_2' => $staff->address_line_2,
                'province_state' => $staff->province_state,
                'locality' => $staff->locality,
                'postal_code' => $staff->postal_code,
                'preferred_language' => $staff->preferred_language,
                'emergency_contact_name' => $staff->emergency_contact_name,
                'emergency_contact_phone' => $staff->emergency_contact_phone,
                'notes' => $staff->notes,
            ],
            'category' => 'staff',
            ...$this->sectionProps('staff'),
        ]);
    }

    /**
     * Store a new staff profile.
     */
    public function store(Request $request): RedirectResponse
    {
        $staff = Person::create($this->validatedPersonData($request, 'staff'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Staff profile added.')]);

        return to_route('staff.show', $staff);
    }

    /**
     * Update a staff profile.
     */
    public function update(Request $request, Person $staff): RedirectResponse
    {
        $this->ensurePersonCategory($staff, 'staff');

        $staff->update($this->validatedPersonData($request, 'staff'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Staff profile updated.')]);

        return to_route('staff.show', $staff);
    }

    /**
     * Delete a staff profile.
     */
    public function destroy(Person $staff): RedirectResponse
    {
        $this->ensurePersonCategory($staff, 'staff');

        abort_if($staff->user?->id === request()->user()->id, 422, __('You cannot delete your own staff profile.'));
        $staff->user?->delete();
        $staff->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Staff profile deleted.')]);

        return to_route('staff.index');
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ManagesPersonProfiles;
use App\Models\Person;
use App\Models\User;
use App\Services\Customers\CustomerProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PersonController extends Controller
{
    use ManagesPersonProfiles;

    /**
     * Show customer profiles.
     */
    public function index(Request $request): Response
    {
        $search = trim((string) $request->input('search'));
        $query = Person::query()
            ->when($request->boolean('archived'), fn ($query) => $query->onlyTrashed())
            ->where('category', 'customer')
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('first_name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%")
                ->orWhere('company_name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%")
                ->orWhere('tax_id', 'like', "%{$search}%")))
            ->when($request->filled('customer_type'), fn ($query) => $query->where('customer_type', (string) $request->input('customer_type')))
            ->when($request->filled('lead_source'), fn ($query) => $query->where('lead_source', (string) $request->input('lead_source')))
            ->withCount('installations')
            ->latest();

        return Inertia::render('people/index', [
            'people' => $query->paginate(20)->withQueryString()->through(fn (Person $person) => $this->personSummary($person)),
            'filters' => $request->only(['search', 'customer_type', 'lead_source', 'archived']),
            'leadSources' => Person::query()
                ->when($request->boolean('archived'), fn ($query) => $query->onlyTrashed())
                ->where('category', 'customer')
                ->whereNotNull('lead_source')
                ->distinct()
                ->orderBy('lead_source')
                ->pluck('lead_source'),
            ...$this->sectionProps('customer'),
        ]);
    }

    /**
     * Show the customer creation form.
     */
    public function create(): Response
    {
        return Inertia::render('people/create', [
            'category' => 'customer',
            ...$this->sectionProps('customer'),
        ]);
    }

    /**
     * Show a customer profile.
     */
    public function show(Person $person, CustomerProfile $profile): Response
    {
        $this->ensurePersonCategory($person, 'customer');

        $person->load(['installations' => fn ($query) => $query->latest(), 'portalAccesses' => fn ($query) => $query->latest()]);

        return Inertia::render('people/show', [
            'person' => [
                ...$this->personSummary($person),
                'tax_id' => $person->tax_id,
                'lead_source' => $person->lead_source,
                'billing_email' => $person->billing_email,
                'address_line_1' => $person->address_line_1,
                'address_line_2' => $person->address_line_2,
                'province_state' => $person->province_state,
                'locality' => $person->locality,
                'postal_code' => $person->postal_code,
                'preferred_language' => $person->preferred_language,
                'emergency_contact_name' => $person->emergency_contact_name,
                'emergency_contact_phone' => $person->emergency_contact_phone,
                'notes' => $person->notes,
                'installations' => $person->installations->map(fn ($installation) => [
                    'id' => $installation->id,
                    'service_type' => $installation->service_type,
                    'status' => $installation->status,
                    'project_reference' => $installation->project_reference,
                    'site_name' => $installation->site_name,
                    'site_city' => $installation->site_city,
                    'site_country' => $installation->site_country,
                    'scheduled_date' => $installation->scheduled_date?->toDateString(),
                ]),
                'portal_accesses' => $person->portalAccesses->map(fn ($access) => ['id' => $access->id, 'recipient_email' => $access->recipient_email, 'sent_at' => $access->sent_at?->toIso8601String(), 'first_accessed_at' => $access->first_accessed_at?->toIso8601String(), 'last_accessed_at' => $access->last_accessed_at?->toIso8601String(), 'expires_at' => $access->expires_at->toIso8601String(), 'revoked_at' => $access->revoked_at?->toIso8601String()]),
            ],
            'customer360' => $profile->build($person),
            'activityUsers' => User::query()->orderBy('name')->get(['id', 'name', 'role']),
            ...$this->sectionProps('customer'),
        ]);
    }

    /**
     * Edit a customer.
     */
    public function edit(Person $person): Response
    {
        $this->ensurePersonCategory($person, 'customer');

        return Inertia::render('people/edit', [
            'person' => [
                ...$this->personSummary($person),
                'tax_id' => $person->tax_id,
                'lead_source' => $person->lead_source,
                'billing_email' => $person->billing_email,
                'address_line_1' => $person->address_line_1,
                'address_line_2' => $person->address_line_2,
                'province_state' => $person->province_state,
                'locality' => $person->locality,
                'postal_code' => $person->postal_code,
                'preferred_language' => $person->preferred_language,
                'emergency_contact_name' => $person->emergency_contact_name,
                'emergency_contact_phone' => $person->emergency_contact_phone,
                'notes' => $person->notes,
            ],
            'category' => 'customer',
            ...$this->sectionProps('customer'),
        ]);
    }

    /**
     * Store a new customer.
     */
    public function store(Request $request): RedirectResponse
    {
        $person = Person::create($this->validatedPersonData($request, 'customer'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Customer added.')]);

        return to_route('people.show', $person);
    }

    /**
     * Update a customer.
     */
    public function update(Request $request, Person $person): RedirectResponse
    {
        $this->ensurePersonCategory($person, 'customer');

        $person->update($this->validatedPersonData($request, 'customer'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Customer updated.')]);

        return to_route('people.show', $person);
    }

    /**
     * Delete a customer.
     */
    public function destroy(Person $person): RedirectResponse
    {
        $this->ensurePersonCategory($person, 'customer');

        $person->portalAccesses()->whereNull('revoked_at')->update(['revoked_at' => now()]);
        $person->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Customer archived.')]);

        return to_route('people.index');
    }

    /**
     * Restore an archived customer.
     */
    public function restore(int $person): RedirectResponse
    {
        $customer = Person::onlyTrashed()->findOrFail($person);
        $this->ensurePersonCategory($customer, 'customer');
        $customer->restore();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Customer restored.')]);

        return to_route('people.show', $customer);
    }
}

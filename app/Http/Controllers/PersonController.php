<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ManagesPersonProfiles;
use App\Models\CrmTag;
use App\Models\Person;
use App\Models\SavedCrmFilter;
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
            ->when($request->filled('lifecycle_status'), fn ($query) => $query->where('lifecycle_status', (string) $request->input('lifecycle_status')))
            ->when($request->filled('lead_source'), fn ($query) => $query->where('lead_source', (string) $request->input('lead_source')))
            ->when($request->filled('tag'), fn ($query) => $query->whereHas('tags', fn ($tags) => $tags->whereKey($request->integer('tag'))))
            ->with('tags:id,name,color')
            ->withCount('installations')
            ->latest();

        return Inertia::render('people/index', [
            'people' => $query->paginate(20)->withQueryString()->through(fn (Person $person) => $this->personSummary($person)),
            'filters' => $request->only(['search', 'customer_type', 'lifecycle_status', 'lead_source', 'tag', 'archived']),
            'tags' => CrmTag::orderBy('name')->get(['id', 'name', 'color']),
            'savedFilters' => SavedCrmFilter::where('user_id', $request->user()->id)->where('scope', 'customers')->orderByDesc('is_default')->orderBy('name')->get(['id', 'name', 'filters', 'is_default']),
            'duplicateGroups' => $request->boolean('archived') ? [] : $this->duplicateCustomerGroups(),
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

    private function duplicateCustomerGroups(): array
    {
        $customers = Person::query()->where('category', 'customer')->get(['id', 'first_name', 'last_name', 'company_name', 'email', 'phone', 'tax_id']);
        $groups = collect();

        foreach (['email', 'phone', 'tax_id'] as $field) {
            $customers->filter(fn (Person $person) => filled($person->{$field}))
                ->groupBy(function (Person $person) use ($field): string {
                    $value = mb_strtolower(trim((string) $person->{$field}));

                    return $field === 'phone' ? preg_replace('/\D+/', '', $value) : preg_replace('/\s+/', '', $value);
                })
                ->filter(fn ($matches) => $matches->count() > 1)
                ->each(fn ($matches) => $groups->push($matches->values()));
        }

        return $groups
            ->unique(fn ($matches) => $matches->pluck('id')->sort()->implode('-'))
            ->take(10)
            ->map(fn ($matches) => $matches->map(fn (Person $person) => [
                'id' => $person->id,
                'name' => $person->company_name ?: trim($person->first_name.' '.$person->last_name),
                'email' => $person->email,
                'phone' => $person->phone,
                'tax_id' => $person->tax_id,
            ])->all())
            ->values()
            ->all();
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

        $person->load([
            'installations' => fn ($query) => $query->latest(),
            'sharedInstallations' => fn ($query) => $query->latest('installations.created_at'),
            'portalAccesses' => fn ($query) => $query->latest(),
            'contacts',
            'tags:id,name,color',
            'consents.recordedBy:id,name',
        ]);

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
                'preferred_contact_channel' => $person->preferred_contact_channel,
                'do_not_contact' => $person->do_not_contact,
                'data_source' => $person->data_source,
                'emergency_contact_name' => $person->emergency_contact_name,
                'emergency_contact_phone' => $person->emergency_contact_phone,
                'notes' => $person->notes,
                'contacts' => $person->contacts,
                'tags' => $person->tags,
                'consents' => $person->consents,
                'installations' => $person->installations->concat($person->sharedInstallations)->unique('id')->sortByDesc('created_at')->values()->map(fn ($installation) => [
                    'id' => $installation->id,
                    'service_type' => $installation->service_type,
                    'status' => $installation->status,
                    'project_reference' => $installation->project_reference,
                    'site_name' => $installation->site_name,
                    'site_city' => $installation->site_city,
                    'site_country' => $installation->site_country,
                    'scheduled_date' => $installation->scheduled_date?->toDateString(),
                    'relationship' => $person->sharedInstallations->firstWhere('id', $installation->id)?->pivot?->role ?? 'owner',
                ]),
                'portal_accesses' => $person->portalAccesses->map(fn ($access) => ['id' => $access->id, 'recipient_email' => $access->recipient_email, 'sent_at' => $access->sent_at?->toIso8601String(), 'first_accessed_at' => $access->first_accessed_at?->toIso8601String(), 'last_accessed_at' => $access->last_accessed_at?->toIso8601String(), 'expires_at' => $access->expires_at->toIso8601String(), 'revoked_at' => $access->revoked_at?->toIso8601String()]),
            ],
            'customer360' => $profile->build($person),
            'activityUsers' => User::query()->orderBy('name')->get(['id', 'name', 'role']),
            'availableTags' => CrmTag::orderBy('name')->get(['id', 'name', 'color']),
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
                'preferred_contact_channel' => $person->preferred_contact_channel,
                'do_not_contact' => $person->do_not_contact,
                'data_source' => $person->data_source,
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

<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ManagesPersonProfiles;
use App\Models\CrmTag;
use App\Models\Person;
use App\Models\SavedCrmFilter;
use App\Models\User;
use App\Services\Customers\CustomerProfile;
use App\Services\Customers\CustomerWorkspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
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
        return Inertia::render('people/index', $this->workspaceListing($request));
    }

    private function workspaceListing(Request $request): array
    {
        $validation = Validator::make($request->query(), [
            'search' => ['nullable', 'string', 'max:255'],
            'customer_type' => ['nullable', Rule::in(['residential', 'business', 'community', 'public_sector'])],
            'lifecycle_status' => ['nullable', Rule::in(['lead', 'active', 'inactive', 'former'])],
            'province_state' => ['nullable', 'string', 'max:255'],
            'lead_source' => ['nullable', 'string', 'max:255'],
            'tag' => ['nullable', 'integer'],
            'archived' => ['nullable', 'boolean'],
            'has_opportunities' => ['nullable', 'boolean'],
            'has_quotes' => ['nullable', 'boolean'],
            'sort' => ['nullable', Rule::in(['name', 'created_at', 'volume', 'last_activity_at'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', Rule::in([15, 30, 50])],
            'page' => ['nullable', 'integer', 'min:1'],
            'relation_page' => ['nullable', 'integer', 'min:1'],
            'tab' => ['nullable', Rule::in(['summary', 'opportunities', 'quotes', 'projects', 'installations', 'invoices', 'documents', 'activity'])],
        ]);

        abort_if($validation->fails(), 422, $validation->errors()->first());

        return [
            ...app(CustomerWorkspace::class)->listing($request),
            'filters' => $request->only(['search', 'customer_type', 'lifecycle_status', 'lead_source', 'tag', 'archived', 'province_state', 'has_opportunities', 'has_quotes', 'sort', 'direction', 'per_page', 'page']),
            'tags' => CrmTag::orderBy('name')->get(['id', 'name', 'color']),
            'savedFilters' => SavedCrmFilter::where('user_id', $request->user()->id)->where('scope', 'customers')->orderByDesc('is_default')->orderBy('name')->get(['id', 'name', 'filters', 'is_default']),
            'duplicateGroups' => Inertia::optional(fn () => $this->duplicateCustomerGroups()),
            'leadSources' => Person::query()->where('category', 'customer')->whereNotNull('lead_source')->distinct()->orderBy('lead_source')->pluck('lead_source'),
            ...$this->sectionProps('customer'),
        ];
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
    public function show(Request $request, Person $person, CustomerProfile $profile): Response
    {
        $this->ensurePersonCategory($person, 'customer');

        $person->load([
            'installations' => fn ($query) => $query->when($request->user()->cannot('view-installations'), fn ($q) => $q->whereRaw('1 = 0'))->latest()->limit(5),
            'sharedInstallations' => fn ($query) => $query->when($request->user()->cannot('view-installations'), fn ($q) => $q->whereRaw('1 = 0'))->latest('installations.created_at')->limit(5),
            'contacts',
            'tags:id,name,color',
            'consents.recordedBy:id,name',
        ]);

        return Inertia::render('people/show', [
            ...$this->workspaceListing($request),
            'customerWorkspace' => app(CustomerWorkspace::class)->detail($person, $request),
            'person' => [
                ...$this->personSummary($person),
                'created_at' => $person->created_at?->toIso8601String(),
                'tax_id' => $person->tax_id,
                'lead_source' => $person->lead_source,
                'billing_email' => $person->billing_email,
                'billing_name' => $person->billing_name,
                'billing_tax_id' => $person->billing_tax_id,
                'billing_address_line_1' => $person->billing_address_line_1,
                'billing_address_line_2' => $person->billing_address_line_2,
                'billing_city' => $person->billing_city,
                'billing_locality' => $person->billing_locality,
                'billing_province_state' => $person->billing_province_state,
                'billing_postal_code' => $person->billing_postal_code,
                'billing_country' => $person->billing_country,
                'payment_terms_days' => $person->payment_terms_days,
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
                'portal_accesses' => [],
            ],
            'customer360' => Inertia::optional(fn () => $profile->build($person, $request->user())),
            'activityUsers' => $request->user()->can('manage-operations') ? User::query()->orderBy('name')->get(['id', 'name', 'role']) : [],
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
                'billing_name' => $person->billing_name,
                'billing_tax_id' => $person->billing_tax_id,
                'billing_address_line_1' => $person->billing_address_line_1,
                'billing_address_line_2' => $person->billing_address_line_2,
                'billing_city' => $person->billing_city,
                'billing_locality' => $person->billing_locality,
                'billing_province_state' => $person->billing_province_state,
                'billing_postal_code' => $person->billing_postal_code,
                'billing_country' => $person->billing_country,
                'payment_terms_days' => $person->payment_terms_days,
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

<?php

namespace App\Http\Controllers\Api\Mobile\V1;

use App\Http\Controllers\Controller;
use App\Models\CommercialRoutePlan;
use App\Models\CommercialVisit;
use App\Models\CrmActivity;
use App\Models\Opportunity;
use App\Models\Person;
use App\Services\Analytics\CommercialConversionAnalytics;
use App\Services\Crm\LeadResponseSla;
use App\Services\Crm\OpportunitySalesCycle;
use App\Services\Customers\OpportunityCustomerResolver;
use App\Services\Geocoding\NominatimGeocoder;
use App\Support\BusinessLineCatalog;
use App\Support\Crm\CommercialRoutePayload;
use App\Support\Crm\OpportunityCatalog;
use App\Support\PhoneNormalizer;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CommercialLeadController extends Controller
{
    public function reverseGeocode(Request $request, NominatimGeocoder $geocoder): JsonResponse
    {
        $data = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);
        $place = $geocoder->reverse((float) $data['latitude'], (float) $data['longitude']);
        $address = $place['address'] ?? [];
        $streetAddress = trim(implode(' ', array_filter([
            $address['road'] ?? null,
            $address['house_number'] ?? null,
        ])));

        return response()->json(['data' => [
            'address' => $streetAddress ?: (filled($place['display_name'] ?? null) ? Str::limit($place['display_name'], 255, '') : null),
            'city' => $address['municipality'] ?? $address['locality'] ?? null,
            'postal_code' => $address['postal_code'] ?? null,
            'display_name' => $place['display_name'] ?? null,
        ]]);
    }

    public function index(Request $request, CommercialConversionAnalytics $conversionAnalytics): JsonResponse
    {
        $validated = $request->validate([
            'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);
        $limit = $validated['limit'] ?? 100;
        $base = Opportunity::query()
            ->where('owner_user_id', $request->user()->id)
            ->whereNotIn('stage', ['won', 'lost']);
        $opportunities = (clone $base)
            ->with([
                'customer:id,first_name,last_name,company_name,phone,email,address_line_1,city,postal_code,preferred_contact_channel,do_not_contact',
                'nextActivity',
            ])
            ->orderByDesc('commercial_score')
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get();
        $lastVisits = CommercialVisit::query()
            ->where('user_id', $request->user()->id)
            ->whereIn('opportunity_id', $opportunities->modelKeys())
            ->latest('visited_at')
            ->get()
            ->unique('opportunity_id')
            ->keyBy('opportunity_id');
        $agenda = CrmActivity::query()
            ->with([
                'person:id,first_name,last_name,company_name,phone,email,address_line_1,city,postal_code,preferred_contact_channel,do_not_contact',
                'opportunity:id,code,title,stage,commercial_score',
            ])
            ->where('assigned_user_id', $request->user()->id)
            ->whereIn('status', ['pending', 'in_progress'])
            ->whereHas('opportunity', fn ($query) => $query
                ->where('owner_user_id', $request->user()->id)
                ->whereNotIn('stage', ['won', 'lost']))
            ->orderByRaw('case when due_at is null then 1 else 0 end')
            ->orderBy('due_at')
            ->limit($limit)
            ->get();
        $route = CommercialRoutePlan::query()
            ->with('stops.customer:id,first_name,last_name,company_name,phone,email,address_line_1,city,postal_code,preferred_contact_channel,do_not_contact')
            ->where('user_id', $request->user()->id)
            ->whereDate('route_date', today())
            ->first();

        return response()->json(['data' => [
            'summary' => [
                'open' => (clone $base)->count(),
                'overdue' => (clone $base)->whereHas('nextActivity', fn ($query) => $query->where('due_at', '<', now()))->count(),
                'due_today' => (clone $base)->whereHas('nextActivity', fn ($query) => $query->whereDate('due_at', today()))->count(),
                'pipeline_value' => (float) (clone $base)->sum('estimated_value'),
            ],
            'leads' => $opportunities->map(fn (Opportunity $opportunity) => [
                'id' => $opportunity->id,
                'code' => $opportunity->code,
                'title' => $opportunity->title,
                'stage' => $opportunity->stage,
                'probability' => $opportunity->probability,
                'commercial_score' => $opportunity->commercial_score,
                'estimated_value' => (float) $opportunity->estimated_value,
                'source' => $opportunity->source,
                'expected_close_on' => $opportunity->expected_close_on?->toDateString(),
                'updated_at' => $opportunity->updated_at->toISOString(),
                'customer' => $opportunity->customer ? $this->customerSummary($opportunity->customer) : null,
                'next_action' => $opportunity->nextActivity ? $this->activitySummary($opportunity->nextActivity) : null,
                'last_visit' => ($visit = $lastVisits->get($opportunity->id)) ? [
                    'visited_at' => $visit->visited_at->toISOString(),
                    'latitude' => $visit->latitude,
                    'longitude' => $visit->longitude,
                    'address' => $visit->address,
                ] : null,
            ])->values(),
            'agenda' => $agenda->map(fn (CrmActivity $activity) => [
                ...$this->activitySummary($activity),
                'customer' => $activity->person ? $this->customerSummary($activity->person) : null,
                'opportunity' => $activity->opportunity ? [
                    'id' => $activity->opportunity->id,
                    'code' => $activity->opportunity->code,
                    'title' => $activity->opportunity->title,
                    'stage' => $activity->opportunity->stage,
                    'commercial_score' => $activity->opportunity->commercial_score,
                ] : null,
            ])->values(),
            'route' => CommercialRoutePayload::make($route),
            'conversions' => $conversionAnalytics->forCommercial(
                $request->user(),
                today()->subMonths(11)->startOfMonth(),
                now(),
            ),
            'generated_at' => now()->toISOString(),
        ]]);
    }

    public function store(
        Request $request,
        OpportunityCustomerResolver $customerResolver,
        OpportunitySalesCycle $salesCycle,
        LeadResponseSla $responseSla,
    ): JsonResponse {
        return $this->createLead($request, $customerResolver, $salesCycle, $responseSla);
    }

    public function convertVisit(Request $request, CommercialVisit $visit): JsonResponse
    {
        return DB::transaction(function () use ($request, $visit): JsonResponse {
            $visit = CommercialVisit::whereKey($visit->id)->lockForUpdate()->firstOrFail();
            if ($visit->opportunity_id) {
                return response()->json(['data' => $visit->only(['id', 'person_id', 'opportunity_id'])]);
            }
            abort_unless(in_array($visit->outcome, ['interested', 'follow_up'], true), 422, 'Solo se convierten visitas con interés o seguimiento.');
            $request->merge([
                'client_uuid' => $visit->client_uuid, 'visited_at' => $visit->visited_at->toISOString(),
                'latitude' => $visit->latitude, 'longitude' => $visit->longitude,
                'accuracy_meters' => $visit->accuracy_meters, 'address' => $visit->address,
                'notes' => $visit->notes, 'source' => 'outbound',
            ]);

            return $this->createLead($request, app(OpportunityCustomerResolver::class), app(OpportunitySalesCycle::class), app(LeadResponseSla::class), $visit);
        });
    }

    private function createLead(
        Request $request,
        OpportunityCustomerResolver $customerResolver,
        OpportunitySalesCycle $salesCycle,
        LeadResponseSla $responseSla,
        ?CommercialVisit $existingVisit = null,
    ): JsonResponse {
        $data = $request->validate([
            'client_uuid' => ['required', 'uuid', Rule::unique('commercial_visits', 'client_uuid')->ignore($existingVisit?->id)],
            'first_name' => ['nullable', 'string', 'max:255', 'required_without:company_name'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'company_name' => ['nullable', 'string', 'max:255', 'required_without:first_name'],
            'phone' => [
                'nullable', 'string', 'max:40', 'required_without:email',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (filled($value) && ! PhoneNormalizer::isPlausibleMobile((string) $value)) {
                        $fail(__('Enter a valid mobile number.'));
                    }
                },
            ],
            'email' => ['nullable', 'email:rfc', 'max:255', 'required_without:phone'],
            'preferred_contact_channel' => ['required', Rule::in(['phone', 'whatsapp', 'email', 'sms'])],
            'consent_status' => ['required', Rule::in(['not_recorded', 'granted', 'denied'])],
            'consent_notes' => ['nullable', 'string', 'max:1000'],
            'service_interest' => ['required', Rule::in(BusinessLineCatalog::SERVICE_INTERESTS)],
            'source' => ['required', Rule::in(array_keys(OpportunityCatalog::SOURCES))],
            'source_detail' => ['nullable', 'string', 'max:255', 'required_if:source,other'],
            'estimated_value' => ['nullable', 'numeric', 'min:0', 'max:9999999999999.99'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'notes' => ['nullable', 'string', 'max:3000'],
            'visited_at' => ['required', 'date', 'before_or_equal:now'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'accuracy_meters' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'next_action_at' => ['required', 'date', 'after:visited_at', ...($existingVisit ? ['after:now'] : [])],
        ]);

        if ($existingVisit) {
            $data['visited_at'] = $existingVisit->visited_at;
            $data['next_action_at'] = CarbonImmutable::parse($data['next_action_at'])->timezone(config('app.timezone'));
        }

        $visit = DB::transaction(function () use ($request, $data, $customerResolver, $salesCycle, $responseSla, $existingVisit): CommercialVisit {
            $customer = $this->resolveCustomer($data, $customerResolver);
            $customerAttributes = array_filter([
                'first_name' => $data['first_name'] ?? null,
                'last_name' => $data['last_name'] ?? null,
                'company_name' => $data['company_name'] ?? null,
                'email' => filled($data['email'] ?? null) ? mb_strtolower(trim($data['email'])) : null,
                'address_line_1' => $data['address'] ?? null,
                'city' => $data['city'] ?? null,
                'postal_code' => $data['postal_code'] ?? null,
                'preferred_contact_channel' => $data['preferred_contact_channel'],
                'lead_source' => $data['source'] === 'other' ? $data['source_detail'] : $data['source'],
                'data_source' => 'mobile_commercial_visit',
                'lifecycle_status' => 'lead',
            ], fn (mixed $value) => filled($value));
            $customerAttributes['service_interests'] = collect([...(array) $customer->service_interests, $data['service_interest']])->unique()->values()->all();
            if ($data['consent_status'] !== 'not_recorded') {
                $customerAttributes['do_not_contact'] = $data['consent_status'] === 'denied';
            }
            $customer->update($customerAttributes);

            if ($data['consent_status'] !== 'not_recorded') {
                $customer->consents()->create([
                    'recorded_by' => $request->user()->id,
                    'channel' => $data['preferred_contact_channel'],
                    'status' => $data['consent_status'],
                    'lawful_basis' => 'consent',
                    'source' => 'mobile_commercial_visit',
                    'captured_at' => $data['visited_at'],
                    'notes' => $data['consent_notes'] ?? null,
                ]);
            }

            $opportunity = Opportunity::create($salesCycle->prepareActiveData([
                'person_id' => $customer->id,
                'owner_user_id' => $request->user()->id,
                'created_by' => $request->user()->id,
                'title' => __(':interest · commercial visit', ['interest' => $data['service_interest']]),
                'commercial_kind' => 'new_business',
                'stage' => 'new',
                'probability' => 10,
                'estimated_value' => $data['estimated_value'] ?? 0,
                'source' => $data['source'],
                'source_detail' => $data['source'] === 'other' ? $data['source_detail'] : null,
                'notes' => $data['notes'] ?? null,
            ], $request->user()));
            $opportunity->update(['code' => 'OPP-'.now()->format('Y').'-'.str_pad((string) $opportunity->id, 5, '0', STR_PAD_LEFT)]);

            $activity = CrmActivity::create([
                'opportunity_id' => $opportunity->id,
                'person_id' => $customer->id,
                'assigned_user_id' => $request->user()->id,
                'created_by' => $request->user()->id,
                'type' => 'visit',
                'title' => __('Commercial visit'),
                'notes' => $data['notes'] ?? null,
                'priority' => 'normal',
                'status' => 'completed',
                'starts_at' => $data['visited_at'],
                'due_at' => $data['visited_at'],
                'completed_at' => $data['visited_at'],
            ]);
            $responseSla->start($opportunity);
            $responseSla->record($activity);
            $salesCycle->ensureNextAction($opportunity, $request->user(), [
                'type' => $customer->do_not_contact ? 'task' : 'call',
                'title' => $customer->do_not_contact
                    ? __('Review lead without contacting the customer')
                    : __('Contact and qualify customer'),
                'priority' => 'normal',
                'due_at' => $data['next_action_at'],
            ]);
            $salesCycle->refreshScore($opportunity);

            if ($existingVisit) {
                $existingVisit->update(['person_id' => $customer->id, 'opportunity_id' => $opportunity->id]);

                return $existingVisit;
            }

            return CommercialVisit::create([
                ...Arr::only($data, ['client_uuid', 'visited_at', 'latitude', 'longitude', 'accuracy_meters', 'address', 'notes']),
                'user_id' => $request->user()->id,
                'person_id' => $customer->id,
                'opportunity_id' => $opportunity->id,
            ]);
        });

        $visit->load('customer:id,first_name,last_name,company_name,phone,email', 'opportunity:id,code,title,stage,owner_user_id');

        return response()->json(['data' => $existingVisit ? $visit->only(['id', 'person_id', 'opportunity_id']) : $visit], 201);
    }

    private function resolveCustomer(array $data, OpportunityCustomerResolver $resolver): Person
    {
        if (filled($data['phone'] ?? null)) {
            return $resolver->resolve($data['phone'], [
                ...Arr::only($data, ['first_name', 'last_name', 'company_name', 'email', 'preferred_contact_channel']),
                'lead_source' => $data['source'] === 'other' ? $data['source_detail'] : $data['source'],
            ]);
        }

        return Person::query()
            ->where('category', 'customer')
            ->whereRaw('lower(email) = ?', [mb_strtolower(trim($data['email']))])
            ->first() ?? Person::create([
                'category' => 'customer',
                'customer_type' => filled($data['company_name'] ?? null) ? 'business' : 'residential',
                'lifecycle_status' => 'lead',
                'first_name' => trim($data['first_name'] ?? $data['company_name']),
                'last_name' => $data['last_name'] ?? null,
                'company_name' => $data['company_name'] ?? null,
                'email' => mb_strtolower(trim($data['email'])),
                'country' => 'Spain',
                'preferred_language' => 'es',
                'preferred_contact_channel' => $data['preferred_contact_channel'],
                'lead_source' => $data['source'],
                'data_source' => 'mobile_commercial_visit',
            ]);
    }

    /** @return array<string, mixed> */
    private function customerSummary(Person $customer): array
    {
        return [
            'id' => $customer->id,
            'name' => $customer->company_name ?: trim($customer->first_name.' '.($customer->last_name ?? '')),
            'phone' => $customer->phone,
            'email' => $customer->email,
            'address' => $customer->address_line_1,
            'city' => $customer->city,
            'postal_code' => $customer->postal_code,
            'preferred_contact_channel' => $customer->preferred_contact_channel,
            'do_not_contact' => $customer->do_not_contact,
        ];
    }

    /** @return array<string, mixed> */
    private function activitySummary(CrmActivity $activity): array
    {
        return [
            'id' => $activity->id,
            'type' => $activity->type,
            'title' => $activity->title,
            'priority' => $activity->priority,
            'status' => $activity->status,
            'due_at' => $activity->due_at?->toISOString(),
        ];
    }
}

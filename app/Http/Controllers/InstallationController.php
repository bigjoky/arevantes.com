<?php

namespace App\Http\Controllers;

use App\Models\Installation;
use App\Models\Person;
use App\Models\RemoteStation;
use App\Models\SerialNumber;
use App\Models\User;
use App\Services\Operations\ProjectProfitability;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class InstallationController extends Controller
{
    /**
     * List installations across all customers.
     */
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'service_type' => ['nullable', Rule::in(['solar', 'ev_charger', 'hvac', 'electrical'])],
            'status' => ['nullable', Rule::in(['lead', 'survey_booked', 'quoted', 'approved', 'scheduled', 'in_progress', 'completed', 'cancelled'])],
        ]);

        $installations = Installation::query()
            ->with(['customer:id,first_name,last_name,company_name', 'people:id,first_name,last_name,company_name,email,phone', 'remoteStation'])
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query
                        ->where('project_reference', 'like', "%{$search}%")
                        ->orWhere('site_name', 'like', "%{$search}%")
                        ->orWhere('site_address_line_1', 'like', "%{$search}%")
                        ->orWhereHas('customer', fn ($query) => $query
                            ->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('company_name', 'like', "%{$search}%"))
                        ->orWhereHas('people', fn ($query) => $query
                            ->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('company_name', 'like', "%{$search}%"));
                });
            })
            ->when($filters['service_type'] ?? null, fn ($query, string $serviceType) => $query->where('service_type', $serviceType))
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->latest()
            ->get();

        return Inertia::render('installations/index', [
            'installations' => $installations->map(fn (Installation $installation) => $this->installationPayload($installation)),
            'filters' => $filters,
        ]);
    }

    /**
     * Show the installation creation form.
     */
    public function create(Request $request): Response
    {
        $customer = null;

        if ($request->filled('customer')) {
            $customer = Person::query()
                ->where('category', 'customer')
                ->findOrFail($request->integer('customer'));
        }

        [$street, $streetNumber] = $this->splitStreetAddress($customer?->address_line_1);

        return Inertia::render('installations/create', [
            'customers' => $this->customerOptions(),
            'remoteStations' => $this->remoteStationOptions(),
            'assignees' => $this->assigneeOptions(),
            'installation' => $customer ? [
                'person_id' => $customer->id,
                'site_address_line_1' => $street,
                'site_street_number' => $streetNumber,
                'site_address_line_2' => $customer->address_line_2,
                'site_city' => $customer->city,
                'site_locality' => $customer->locality,
                'site_province_state' => $customer->province_state,
                'site_postal_code' => $customer->postal_code,
                'site_country' => $customer->country,
            ] : null,
        ]);
    }

    /**
     * Show an installation profile.
     */
    public function show(Request $request, Installation $installation, ProjectProfitability $profitability): Response
    {
        $installation->load(['customer', 'people', 'remoteStation', 'assets.product:id,name,sku', 'assets.serial:id,serial_number', 'assets.sourcePurchase:id,number', 'assets.replacement:id,asset_tag,name', 'assets.events']);

        return Inertia::render('installations/show', [
            'installation' => $this->installationPayload($installation),
            'monitoringHistory' => $installation->monitoringReadings()->latest('recorded_at')->limit(288)->get()->reverse()->values()->map(fn ($reading) => [
                'provider' => $reading->provider,
                'status' => $reading->status,
                'current_power_kw' => $reading->current_power_kw,
                'today_energy_kwh' => $reading->today_energy_kwh,
                'recorded_at' => $reading->recorded_at->toIso8601String(),
            ]),
            'monitoringAlerts' => $installation->monitoringAlerts()->latest('started_at')->limit(20)->get(),
            'activities' => $installation->activities()->with('assignedUser:id,name')->latest()->limit(20)->get(),
            'quotes' => $installation->quotes()->latest()->get(),
            'assets' => $installation->assets,
            'assetOptions' => [
                'serials' => SerialNumber::with('product:id,name,sku')->where('installation_id', $installation->id)->whereDoesntHave('installationAsset')->get(),
                'workOrders' => $installation->workOrders()->latest()->get(['id', 'number', 'title']),
                'tickets' => $installation->serviceTickets()->latest()->get(['id', 'number', 'title']),
                'purchases' => $installation->purchaseDocuments()->latest()->get(['id', 'number']),
            ],
            'profitability' => $profitability->for($installation),
            'canManageAssets' => $request->user()->can('manage-installations'),
            'latestAiGeneration' => InstallationAiController::payload($installation->aiGenerations()->latest()->first()),
            'customers' => $this->customerOptions(),
        ]);
    }

    /**
     * Edit an installation.
     */
    public function edit(Installation $installation): Response
    {
        $installation->load(['customer', 'people', 'remoteStation']);

        return Inertia::render('installations/edit', [
            'installation' => $this->installationPayload($installation),
            'customers' => $this->customerOptions(),
            'remoteStations' => $this->remoteStationOptions($installation),
            'assignees' => $this->assigneeOptions(),
        ]);
    }

    /**
     * Store a new installation.
     */
    public function store(Request $request): RedirectResponse
    {
        [$data, $remoteStation] = $this->validatedInstallationData($request);
        $installation = DB::transaction(function () use ($data, $remoteStation): Installation {
            $installation = Installation::create($data);
            $this->bindRemoteStation($installation, $remoteStation);

            return $installation;
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Installation added.')]);

        return to_route('installations.show', $installation);
    }

    /**
     * Update an installation.
     */
    public function update(Request $request, Installation $installation): RedirectResponse
    {
        [$data, $remoteStation] = $this->validatedInstallationData($request, $installation);
        DB::transaction(function () use ($installation, $data, $remoteStation): void {
            $installation->update($data);
            $this->bindRemoteStation($installation, $remoteStation);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Installation updated.')]);

        return to_route('installations.show', $installation);
    }

    /**
     * Delete an installation.
     */
    public function destroy(Installation $installation): RedirectResponse
    {
        $person = $installation->customer;

        $installation->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Installation deleted.')]);

        return $person ? to_route('people.show', $person) : to_route('dashboard');
    }

    /**
     * Convert simple "key: value" lines into structured JSON.
     *
     * @return array<int|string, string>|null
     */
    private function parseExtraDetails(?string $details): ?array
    {
        if (! $details) {
            return null;
        }

        $lines = collect(preg_split('/\R/', $details))
            ->map(fn (string $line) => trim($line))
            ->filter()
            ->values();

        if ($lines->isEmpty()) {
            return null;
        }

        $parsed = [];

        foreach ($lines as $line) {
            if (str_contains($line, ':')) {
                [$key, $value] = array_map('trim', explode(':', $line, 2));

                if ($key !== '' && $value !== '') {
                    $parsed[$key] = $value;

                    continue;
                }
            }

            $parsed[] = $line;
        }

        return $parsed;
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function splitStreetAddress(?string $address): array
    {
        if (! $address) {
            return [null, null];
        }

        if (preg_match('/^(.*?)(?:,|\s)\s*(\d+[A-Za-z]?(?:[-\/]\d+[A-Za-z]?)?)$/u', trim($address), $matches)) {
            return [trim($matches[1]), $matches[2]];
        }

        return [$address, null];
    }

    /**
     * @return array{0: array<string, mixed>, 1: RemoteStation|null}
     */
    private function validatedInstallationData(Request $request, ?Installation $installation = null): array
    {
        $validated = $request->validate([
            'person_id' => [
                'nullable',
                Rule::exists('people', 'id')->where('category', 'customer'),
            ],
            'commercial_user_id' => ['nullable', Rule::exists('users', 'id')->where('role', 'commercial')],
            'technician_user_id' => ['nullable', Rule::exists('users', 'id')->where('role', 'technician')],
            'service_type' => ['required', Rule::in(['solar', 'ev_charger', 'hvac', 'electrical'])],
            'monitoring_provider' => ['nullable', Rule::in(['deye', 'huawei'])],
            'remote_station_id' => ['nullable', 'integer', 'exists:remote_stations,id', 'required_with:monitoring_provider'],
            'status' => ['required', Rule::in(['lead', 'survey_booked', 'quoted', 'approved', 'scheduled', 'in_progress', 'completed', 'cancelled'])],
            'project_reference' => ['nullable', 'string', 'max:255'],
            'site_name' => ['nullable', 'string', 'max:255'],
            'site_address_line_1' => ['required', 'string', 'max:255'],
            'site_street_number' => ['nullable', 'string', 'max:30'],
            'site_address_line_2' => ['nullable', 'string', 'max:255'],
            'site_city' => ['nullable', 'string', 'max:255'],
            'site_locality' => ['nullable', 'string', 'max:255'],
            'site_province_state' => ['nullable', 'string', 'max:255'],
            'site_postal_code' => ['nullable', 'string', 'max:255'],
            'site_country' => ['nullable', 'string', 'max:255'],
            'site_latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'site_longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'site_geocoding_provider' => ['nullable', 'string', Rule::in(['nominatim'])],
            'site_geocoding_place_id' => ['nullable', 'string', 'max:255'],
            'site_geocoding_osm_type' => ['nullable', 'string', 'max:20'],
            'site_geocoding_osm_id' => ['nullable', 'string', 'max:255'],
            'site_geocoding_display_name' => ['nullable', 'string', 'max:2000'],
            'site_geocoding_data' => ['nullable', 'json'],
            'survey_date' => ['nullable', 'date'],
            'scheduled_date' => ['nullable', 'date'],
            'completed_date' => ['nullable', 'date'],
            'estimated_budget' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'system_power_kw' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'panel_count' => ['nullable', 'integer', 'min:0'],
            'inverter_brand' => ['nullable', 'string', 'max:255'],
            'battery_capacity_kwh' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'charger_power_kw' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'charger_connector_type' => ['nullable', 'string', 'max:255'],
            'hvac_system_type' => ['nullable', 'string', 'max:255'],
            'electrical_scope' => ['nullable', 'string', 'max:255'],
            'site_notes' => ['nullable', 'string'],
            'technical_notes' => ['nullable', 'string'],
            'extra_details' => ['nullable', 'string'],
        ]);

        $remoteStation = isset($validated['remote_station_id'])
            ? RemoteStation::find($validated['remote_station_id'])
            : null;

        if ($remoteStation && $remoteStation->provider !== ($validated['monitoring_provider'] ?? null)) {
            throw ValidationException::withMessages(['remote_station_id' => __('The selected station does not belong to this provider.')]);
        }

        if ($remoteStation?->installation_id && $remoteStation->installation_id !== $installation?->id) {
            throw ValidationException::withMessages(['remote_station_id' => __('This remote station is already linked to another installation.')]);
        }

        unset($validated['monitoring_provider'], $validated['remote_station_id']);

        $validated['extra_details'] = $this->parseExtraDetails($validated['extra_details'] ?? null);

        if (! empty($validated['site_geocoding_data'])) {
            $validated['site_geocoding_data'] = json_decode($validated['site_geocoding_data'], true, flags: JSON_THROW_ON_ERROR);
            $validated['site_geocoded_at'] = now();
        }

        return [$validated, $remoteStation];
    }

    private function bindRemoteStation(Installation $installation, ?RemoteStation $remoteStation): void
    {
        RemoteStation::query()
            ->where('installation_id', $installation->id)
            ->when($remoteStation, fn ($query) => $query->where('id', '!=', $remoteStation->id))
            ->update(['installation_id' => null]);

        $monitoring = [
            'deye_station_id' => null,
            'deye_station_name' => null,
            'huawei_station_code' => null,
            'huawei_station_name' => null,
        ];

        if ($remoteStation) {
            $remoteStation->update(['installation_id' => $installation->id]);
            $monitoring[$remoteStation->provider === 'deye' ? 'deye_station_id' : 'huawei_station_code'] = $remoteStation->remote_identifier;
            $monitoring[$remoteStation->provider === 'deye' ? 'deye_station_name' : 'huawei_station_name'] = $remoteStation->name;
        }

        $installation->update($monitoring);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function customerOptions(): array
    {
        return Person::query()
            ->where('category', 'customer')
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get()
            ->map(fn (Person $person) => [
                'id' => $person->id,
                'name' => trim($person->first_name.' '.($person->last_name ?? '')),
                'company_name' => $person->company_name,
                'email' => $person->email,
                'phone' => $person->phone,
                'address_line_1' => $person->address_line_1,
                'address_line_2' => $person->address_line_2,
                'city' => $person->city,
                'locality' => $person->locality,
                'province_state' => $person->province_state,
                'postal_code' => $person->postal_code,
                'country' => $person->country,
            ])
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function remoteStationOptions(?Installation $installation = null): array
    {
        return RemoteStation::query()
            ->where(fn ($query) => $query
                ->whereNull('installation_id')
                ->when($installation, fn ($query) => $query->orWhere('installation_id', $installation->id)))
            ->orderBy('provider')
            ->orderBy('name')
            ->get()
            ->map(fn (RemoteStation $station) => [
                'id' => $station->id,
                'provider' => $station->provider,
                'remote_identifier' => $station->remote_identifier,
                'name' => $station->name,
                'address' => $station->address,
                'installation_id' => $station->installation_id,
            ])
            ->all();
    }

    private function assigneeOptions(): array
    {
        return User::query()->whereIn('role', ['commercial', 'technician'])->orderBy('name')->get(['id', 'name', 'role'])->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function installationPayload(Installation $installation): array
    {
        return [
            'id' => $installation->id,
            'person_id' => $installation->person_id,
            'commercial_user_id' => $installation->commercial_user_id,
            'technician_user_id' => $installation->technician_user_id,
            'customer_name' => $installation->customer
                ? trim($installation->customer->first_name.' '.($installation->customer->last_name ?? ''))
                : null,
            'customer_company' => $installation->customer?->company_name,
            'people' => $installation->people->map(fn (Person $person) => [
                'id' => $person->id,
                'name' => trim($person->first_name.' '.($person->last_name ?? '')),
                'company_name' => $person->company_name,
                'email' => $person->email,
                'phone' => $person->phone,
                'role' => $person->pivot->role,
                'ownership_percentage' => $person->pivot->ownership_percentage,
                'is_primary' => (bool) $person->pivot->is_primary,
            ])->values(),
            'service_type' => $installation->service_type,
            'monitoring_provider' => $installation->remoteStation?->provider,
            'remote_station_id' => $installation->remoteStation?->id,
            'deye_station_id' => $installation->deye_station_id,
            'deye_station_name' => $installation->deye_station_name,
            'deye_status' => $installation->deye_status,
            'deye_current_power_kw' => $installation->deye_current_power_kw,
            'deye_today_energy_kwh' => $installation->deye_today_energy_kwh,
            'deye_status_message' => $installation->deye_status_message,
            'deye_synced_at' => $installation->deye_synced_at?->toIso8601String(),
            'huawei_station_code' => $installation->huawei_station_code,
            'huawei_station_name' => $installation->huawei_station_name,
            'huawei_status' => $installation->huawei_status,
            'huawei_current_power_kw' => $installation->huawei_current_power_kw,
            'huawei_today_energy_kwh' => $installation->huawei_today_energy_kwh,
            'huawei_status_message' => $installation->huawei_status_message,
            'huawei_synced_at' => $installation->huawei_synced_at?->toIso8601String(),
            'status' => $installation->status,
            'project_reference' => $installation->project_reference,
            'site_name' => $installation->site_name,
            'site_address_line_1' => $installation->site_address_line_1,
            'site_street_number' => $installation->site_street_number,
            'site_address_line_2' => $installation->site_address_line_2,
            'site_city' => $installation->site_city,
            'site_locality' => $installation->site_locality,
            'site_province_state' => $installation->site_province_state,
            'site_postal_code' => $installation->site_postal_code,
            'site_country' => $installation->site_country,
            'site_latitude' => $installation->site_latitude,
            'site_longitude' => $installation->site_longitude,
            'site_geocoding_provider' => $installation->site_geocoding_provider,
            'site_geocoding_place_id' => $installation->site_geocoding_place_id,
            'site_geocoding_osm_type' => $installation->site_geocoding_osm_type,
            'site_geocoding_osm_id' => $installation->site_geocoding_osm_id,
            'site_geocoding_display_name' => $installation->site_geocoding_display_name,
            'site_geocoding_data' => $installation->site_geocoding_data,
            'survey_date' => $installation->survey_date?->toDateString(),
            'scheduled_date' => $installation->scheduled_date?->toDateString(),
            'completed_date' => $installation->completed_date?->toDateString(),
            'estimated_budget' => $installation->estimated_budget,
            'system_power_kw' => $installation->system_power_kw,
            'panel_count' => $installation->panel_count,
            'inverter_brand' => $installation->inverter_brand,
            'battery_capacity_kwh' => $installation->battery_capacity_kwh,
            'charger_power_kw' => $installation->charger_power_kw,
            'charger_connector_type' => $installation->charger_connector_type,
            'hvac_system_type' => $installation->hvac_system_type,
            'electrical_scope' => $installation->electrical_scope,
            'site_notes' => $installation->site_notes,
            'technical_notes' => $installation->technical_notes,
            'extra_details' => $installation->extra_details,
        ];
    }
}

import { Form } from '@inertiajs/react';
import { Check, Loader2, MapPin, PlugZap, RefreshCw } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import LocationFields from '@/components/locations/location-fields';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useLocale } from '@/hooks/use-locale';
import type { ServiceType } from '@/lib/business-lines';

export type CustomerOption = {
    id: number;
    name: string;
    company_name: string | null;
    email: string | null;
    phone: string | null;
    address_line_1: string | null;
    address_line_2: string | null;
    city: string | null;
    locality: string | null;
    province_state: string | null;
    postal_code: string | null;
    country: string | null;
};

export type RemoteStationOption = {
    id: number;
    provider: 'deye' | 'huawei';
    remote_identifier: string;
    name: string | null;
    address: string | null;
    installation_id: number | null;
};

export type AssigneeOption = {
    id: number;
    name: string;
    role: 'commercial' | 'technician';
};

export type InstallationFormValue = {
    id?: number;
    person_id?: number | null;
    commercial_user_id?: number | null;
    technician_user_id?: number | null;
    service_type?: ServiceType;
    monitoring_provider?: 'deye' | 'huawei' | null;
    remote_station_id?: number | null;
    deye_station_id?: string | null;
    huawei_station_code?: string | null;
    status?: string;
    project_reference?: string | null;
    site_name?: string | null;
    site_address_line_1?: string | null;
    site_street_number?: string | null;
    site_address_line_2?: string | null;
    site_city?: string | null;
    site_locality?: string | null;
    site_province_state?: string | null;
    site_postal_code?: string | null;
    site_country?: string | null;
    site_latitude?: string | null;
    site_longitude?: string | null;
    site_geocoding_provider?: string | null;
    site_geocoding_place_id?: string | null;
    site_geocoding_osm_type?: string | null;
    site_geocoding_osm_id?: string | null;
    site_geocoding_display_name?: string | null;
    site_geocoding_data?: GeocodingResult | null;
    survey_date?: string | null;
    scheduled_date?: string | null;
    completed_date?: string | null;
    estimated_budget?: string | null;
    system_power_kw?: string | null;
    panel_count?: number | null;
    inverter_brand?: string | null;
    battery_capacity_kwh?: string | null;
    charger_power_kw?: string | null;
    charger_connector_type?: string | null;
    hvac_system_type?: string | null;
    electrical_scope?: string | null;
    electrical_voltage_v?: number | null;
    electrical_phase?: 'single_phase' | 'three_phase' | 'other' | null;
    contracted_power_kw?: string | null;
    electrical_certificate_required?: boolean;
    security_system_type?: string | null;
    security_zones?: number | null;
    security_monitoring_mode?:
        | 'local'
        | 'self_monitored'
        | 'alarm_receiving_centre'
        | 'hybrid'
        | null;
    security_grade?:
        | 'unclassified'
        | 'grade_1'
        | 'grade_2'
        | 'grade_3'
        | 'grade_4'
        | null;
    site_notes?: string | null;
    technical_notes?: string | null;
};

type InstallationFormProps = {
    action: string;
    customers: CustomerOption[];
    remoteStations: RemoteStationOption[];
    assignees: AssigneeOption[];
    installation?: InstallationFormValue;
    method?: 'post' | 'patch';
    submitLabel?: string;
};

const fieldClassName = 'grid gap-2';
const textareaClassName =
    'rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50';

export const statusLabels = {
    lead: 'Lead',
    survey_booked: 'Survey booked',
    quoted: 'Quoted',
    approved: 'Approved',
    scheduled: 'Scheduled',
    in_progress: 'In progress',
    completed: 'Completed',
    cancelled: 'Cancelled',
};

export default function InstallationForm({
    action,
    customers,
    remoteStations,
    assignees,
    installation,
    method = 'post',
    submitLabel = 'Add installation',
}: InstallationFormProps) {
    const { t } = useLocale();
    const [serviceType, setServiceType] = useState<ServiceType>(
        installation?.service_type ?? 'solar',
    );
    const [customerId, setCustomerId] = useState(
        installation?.person_id?.toString() ?? 'unassigned',
    );
    const localizedServiceLabels = {
        solar: t.solarInstallation,
        ev_charger: t.evChargingPoint,
        hvac: t.airconClima,
        electrical: t.electricalInstallation,
        security: t.securityInstallation,
    };
    const localizedStatusLabels = {
        lead: t.lead,
        survey_booked: t.surveyBooked,
        quoted: t.quoted,
        approved: t.approved,
        scheduled: t.scheduled,
        in_progress: t.inProgress,
        completed: t.completed,
        cancelled: t.cancelled,
    };
    const finalSubmitLabel =
        submitLabel === 'Add installation'
            ? t.addInstallation
            : t.saveInstallation;

    return (
        <Form
            action={action}
            method={method}
            options={{ preserveScroll: true }}
            resetOnSuccess={method === 'post'}
            className="space-y-6"
        >
            {({ processing, errors }) => (
                <>
                    <div className={fieldClassName}>
                        <Label htmlFor="person_id">{t.customer}</Label>
                        <Select
                            value={customerId}
                            onValueChange={setCustomerId}
                        >
                            <SelectTrigger id="person_id" className="w-full">
                                <SelectValue placeholder={t.selectCustomer} />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="unassigned">
                                    {t.unassignedCustomer}
                                </SelectItem>
                                {customers.map((customer) => (
                                    <SelectItem
                                        key={customer.id}
                                        value={customer.id.toString()}
                                    >
                                        {customer.company_name || customer.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <input
                            type="hidden"
                            name="person_id"
                            value={
                                customerId === 'unassigned' ? '' : customerId
                            }
                        />
                        <InputError message={errors.person_id} />
                    </div>

                    <div className="grid gap-4 md:grid-cols-2">
                        <div className={fieldClassName}>
                            <Label htmlFor="service_type">
                                {t.serviceType}
                            </Label>
                            <Select
                                name="service_type"
                                defaultValue={serviceType}
                                onValueChange={(value) =>
                                    setServiceType(value as ServiceType)
                                }
                            >
                                <SelectTrigger
                                    id="service_type"
                                    className="w-full"
                                >
                                    <SelectValue
                                        placeholder={t.selectService}
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    {Object.entries(localizedServiceLabels).map(
                                        ([value, label]) => (
                                            <SelectItem
                                                key={value}
                                                value={value}
                                            >
                                                {label}
                                            </SelectItem>
                                        ),
                                    )}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.service_type} />
                        </div>

                        <div className={fieldClassName}>
                            <Label htmlFor="status">{t.status}</Label>
                            <Select
                                name="status"
                                defaultValue={installation?.status ?? 'lead'}
                            >
                                <SelectTrigger id="status" className="w-full">
                                    <SelectValue placeholder={t.selectStatus} />
                                </SelectTrigger>
                                <SelectContent>
                                    {Object.entries(localizedStatusLabels).map(
                                        ([value, label]) => (
                                            <SelectItem
                                                key={value}
                                                value={value}
                                            >
                                                {label}
                                            </SelectItem>
                                        ),
                                    )}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.status} />
                        </div>

                        <Field
                            id="project_reference"
                            label={t.projectReference}
                            placeholder="FV-2026-001"
                            defaultValue={installation?.project_reference}
                            error={errors.project_reference}
                        />
                        <Field
                            id="site_name"
                            label={t.siteName}
                            placeholder="Home, warehouse, community garage..."
                            defaultValue={installation?.site_name}
                            error={errors.site_name}
                        />
                    </div>

                    <div className="grid gap-4 md:grid-cols-2">
                        <div className={fieldClassName}>
                            <Label htmlFor="commercial_user_id">
                                Commercial owner
                            </Label>
                            <Select
                                name="commercial_user_id"
                                defaultValue={installation?.commercial_user_id?.toString()}
                            >
                                <SelectTrigger
                                    id="commercial_user_id"
                                    className="w-full"
                                >
                                    <SelectValue placeholder="Assign commercial" />
                                </SelectTrigger>
                                <SelectContent>
                                    {assignees
                                        .filter(
                                            (user) =>
                                                user.role === 'commercial',
                                        )
                                        .map((user) => (
                                            <SelectItem
                                                key={user.id}
                                                value={user.id.toString()}
                                            >
                                                {user.name}
                                            </SelectItem>
                                        ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.commercial_user_id} />
                        </div>
                        <div className={fieldClassName}>
                            <Label htmlFor="technician_user_id">
                                Technical owner
                            </Label>
                            <Select
                                name="technician_user_id"
                                defaultValue={installation?.technician_user_id?.toString()}
                            >
                                <SelectTrigger
                                    id="technician_user_id"
                                    className="w-full"
                                >
                                    <SelectValue placeholder="Assign technician" />
                                </SelectTrigger>
                                <SelectContent>
                                    {assignees
                                        .filter(
                                            (user) =>
                                                user.role === 'technician',
                                        )
                                        .map((user) => (
                                            <SelectItem
                                                key={user.id}
                                                value={user.id.toString()}
                                            >
                                                {user.name}
                                            </SelectItem>
                                        ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.technician_user_id} />
                        </div>
                    </div>

                    <div className="grid gap-4 md:grid-cols-[minmax(0,2fr)_minmax(8rem,0.5fr)_minmax(0,1fr)]">
                        <Field
                            id="site_address_line_1"
                            label={t.street}
                            placeholder="Street name"
                            defaultValue={installation?.site_address_line_1}
                            error={errors.site_address_line_1}
                            required
                        />
                        <Field
                            id="site_street_number"
                            label={t.streetNumber}
                            placeholder="10, 10B..."
                            defaultValue={installation?.site_street_number}
                            error={errors.site_street_number}
                        />
                        <Field
                            id="site_address_line_2"
                            label={t.address2}
                            placeholder="Garage, roof access, floor..."
                            defaultValue={installation?.site_address_line_2}
                            error={errors.site_address_line_2}
                        />
                    </div>

                    <LocationFields
                        prefix="site_"
                        errors={errors}
                        defaults={{
                            country: installation?.site_country,
                            province: installation?.site_province_state,
                            municipality: installation?.site_city,
                            locality: installation?.site_locality,
                            postalCode: installation?.site_postal_code,
                        }}
                    />

                    <GeocodingFields
                        installation={installation}
                        errors={errors}
                    />

                    <div className="grid gap-4 md:grid-cols-4">
                        <Field
                            id="survey_date"
                            label={t.surveyDate}
                            type="date"
                            defaultValue={installation?.survey_date}
                            error={errors.survey_date}
                        />
                        <Field
                            id="scheduled_date"
                            label={t.scheduledDate}
                            type="date"
                            defaultValue={installation?.scheduled_date}
                            error={errors.scheduled_date}
                        />
                        <Field
                            id="completed_date"
                            label={t.completedDate}
                            type="date"
                            defaultValue={installation?.completed_date}
                            error={errors.completed_date}
                        />
                        <Field
                            id="estimated_budget"
                            label={t.budgetEstimate}
                            type="number"
                            step="0.01"
                            placeholder="0.00"
                            defaultValue={installation?.estimated_budget}
                            error={errors.estimated_budget}
                        />
                    </div>

                    <ServiceSpecificFields
                        serviceType={serviceType}
                        installation={installation}
                        remoteStations={remoteStations}
                        errors={errors}
                    />

                    <div className="grid gap-4 md:grid-cols-2">
                        <TextareaField
                            id="site_notes"
                            label={t.siteNotes}
                            placeholder="Roof access, garage level, machinery room..."
                            defaultValue={installation?.site_notes}
                            error={errors.site_notes}
                        />
                        <TextareaField
                            id="technical_notes"
                            label={t.technicalNotes}
                            placeholder="Meter location, protections, cable route..."
                            defaultValue={installation?.technical_notes}
                            error={errors.technical_notes}
                        />
                    </div>

                    <Button disabled={processing}>
                        <PlugZap />
                        {finalSubmitLabel}
                    </Button>
                </>
            )}
        </Form>
    );
}

type GeocodingResult = {
    provider: 'nominatim';
    place_id: string;
    osm_type: string | null;
    osm_id: string | null;
    latitude: string;
    longitude: string;
    display_name: string | null;
    category: string | null;
    type: string | null;
    importance: number | null;
    bounding_box: string[] | null;
    requested_house_number: string | null;
    house_number_match: boolean;
    address: {
        road: string | null;
        house_number: string | null;
        locality: string | null;
        municipality: string | null;
        province: string | null;
        postal_code: string | null;
        country: string | null;
        country_code: string | null;
    };
};

function GeocodingFields({
    installation,
    errors,
}: {
    installation?: InstallationFormValue;
    errors: Record<string, string | undefined>;
}) {
    const { locale, t } = useLocale();
    const [latitude, setLatitude] = useState(installation?.site_latitude ?? '');
    const [longitude, setLongitude] = useState(
        installation?.site_longitude ?? '',
    );
    const [selected, setSelected] = useState<GeocodingResult | null>(
        installation?.site_geocoding_data ?? null,
    );
    const [results, setResults] = useState<GeocodingResult[]>([]);
    const [searching, setSearching] = useState(false);
    const [message, setMessage] = useState<string | null>(null);

    async function locateAddress(form: HTMLFormElement | null) {
        if (!form) {
            return;
        }

        const data = new FormData(form);
        const query = [
            data.get('site_address_line_1'),
            data.get('site_street_number'),
            data.get('site_address_line_2'),
            data.get('site_locality'),
            data.get('site_city'),
            data.get('site_province_state'),
            data.get('site_postal_code'),
            data.get('site_country'),
        ]
            .filter(Boolean)
            .join(', ');

        setSearching(true);
        setMessage(null);
        setResults([]);

        try {
            const csrfToken = document
                .querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
                ?.getAttribute('content');
            const response = await fetch('/geocoding/search', {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken ?? '',
                },
                body: JSON.stringify({
                    query,
                    language: locale,
                    street: data.get('site_address_line_1'),
                    street_number: data.get('site_street_number'),
                    locality: data.get('site_locality'),
                    municipality: data.get('site_city'),
                    province: data.get('site_province_state'),
                    postal_code: data.get('site_postal_code'),
                    country: data.get('site_country'),
                }),
            });
            const payload = await response.json();

            if (!response.ok) {
                throw new Error(payload.message ?? t.locationSearchError);
            }

            const matches = payload.results as GeocodingResult[];
            setResults(matches);

            if (matches.length === 1) {
                selectResult(matches[0]);
            } else if (matches.length === 0) {
                setMessage(t.locationNotFound);
            }
        } catch (error) {
            setMessage(
                error instanceof Error ? error.message : t.locationSearchError,
            );
        } finally {
            setSearching(false);
        }
    }

    function selectResult(result: GeocodingResult) {
        setSelected(result);
        setLatitude(result.latitude);
        setLongitude(result.longitude);
        setResults([]);
        setMessage(
            result.requested_house_number && !result.house_number_match
                ? t.streetLevelMatch
                : t.locationFound,
        );
    }

    return (
        <div className="space-y-3 border-y py-4">
            <div className="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <Label>{t.mapCoordinates}</Label>
                    {selected?.display_name && (
                        <div className="mt-1 max-w-3xl text-sm text-muted-foreground">
                            <p>{selected.display_name}</p>
                            {selected.requested_house_number && (
                                <p
                                    className={
                                        selected.house_number_match
                                            ? 'mt-1 text-xs text-green-700'
                                            : 'mt-1 text-xs text-amber-700'
                                    }
                                >
                                    {selected.house_number_match
                                        ? `${t.numberConfirmed}: ${selected.address.house_number}`
                                        : `${t.numberNotConfirmed}: ${selected.requested_house_number}`}
                                </p>
                            )}
                        </div>
                    )}
                </div>
                <Button
                    type="button"
                    variant="outline"
                    disabled={searching}
                    onClick={(event) => locateAddress(event.currentTarget.form)}
                >
                    {searching ? (
                        <Loader2 className="animate-spin" />
                    ) : (
                        <MapPin />
                    )}
                    {searching ? t.locatingAddress : t.locateAddress}
                </Button>
            </div>

            {results.length > 1 && (
                <div className="grid gap-2" role="list">
                    {results.map((result) => (
                        <button
                            key={`${result.osm_type}-${result.osm_id}-${result.place_id}`}
                            type="button"
                            className="rounded-md border px-3 py-2 text-left text-sm hover:bg-muted"
                            onClick={() => selectResult(result)}
                        >
                            <span className="block">{result.display_name}</span>
                            {result.requested_house_number && (
                                <span
                                    className={
                                        result.house_number_match
                                            ? 'mt-1 block text-xs text-green-700'
                                            : 'mt-1 block text-xs text-amber-700'
                                    }
                                >
                                    {result.house_number_match
                                        ? `${t.numberConfirmed}: ${result.address.house_number}`
                                        : `${t.numberNotConfirmed}: ${result.requested_house_number}`}
                                </span>
                            )}
                        </button>
                    ))}
                </div>
            )}

            {message && (
                <p className="flex items-center gap-2 text-sm text-muted-foreground">
                    {selected && <Check className="size-4 text-green-600" />}
                    {message}
                </p>
            )}

            <div className="grid gap-4 md:grid-cols-2">
                <div className={fieldClassName}>
                    <Label htmlFor="site_latitude">Latitude</Label>
                    <Input
                        id="site_latitude"
                        name="site_latitude"
                        type="number"
                        min="-90"
                        max="90"
                        step="0.0000001"
                        placeholder="37.8882"
                        value={latitude}
                        onChange={(event) => setLatitude(event.target.value)}
                    />
                    <InputError message={errors.site_latitude} />
                </div>
                <div className={fieldClassName}>
                    <Label htmlFor="site_longitude">Longitude</Label>
                    <Input
                        id="site_longitude"
                        name="site_longitude"
                        type="number"
                        min="-180"
                        max="180"
                        step="0.0000001"
                        placeholder="-4.7794"
                        value={longitude}
                        onChange={(event) => setLongitude(event.target.value)}
                    />
                    <InputError message={errors.site_longitude} />
                </div>
            </div>

            {selected && (
                <>
                    <input
                        type="hidden"
                        name="site_geocoding_provider"
                        value={selected.provider}
                    />
                    <input
                        type="hidden"
                        name="site_geocoding_place_id"
                        value={selected.place_id}
                    />
                    <input
                        type="hidden"
                        name="site_geocoding_osm_type"
                        value={selected.osm_type ?? ''}
                    />
                    <input
                        type="hidden"
                        name="site_geocoding_osm_id"
                        value={selected.osm_id ?? ''}
                    />
                    <input
                        type="hidden"
                        name="site_geocoding_display_name"
                        value={selected.display_name ?? ''}
                    />
                    <input
                        type="hidden"
                        name="site_geocoding_data"
                        value={JSON.stringify(selected)}
                    />
                </>
            )}

            <p className="text-xs text-muted-foreground">
                Data ©{' '}
                <a
                    href="https://www.openstreetmap.org/copyright"
                    target="_blank"
                    rel="noreferrer"
                    className="underline underline-offset-2"
                >
                    OpenStreetMap contributors
                </a>
                , ODbL 1.0
            </p>
        </div>
    );
}

function ServiceSpecificFields({
    serviceType,
    installation,
    remoteStations,
    errors,
}: {
    serviceType: ServiceType;
    installation?: InstallationFormValue;
    remoteStations: RemoteStationOption[];
    errors: Record<string, string | undefined>;
}) {
    const { t } = useLocale();
    const [monitoringProvider, setMonitoringProvider] = useState(
        installation?.monitoring_provider ?? 'none',
    );
    const [remoteStationId, setRemoteStationId] = useState(
        installation?.remote_station_id?.toString() ?? 'none',
    );
    const [stationOptions, setStationOptions] = useState(remoteStations);
    const [stationsRefreshing, setStationsRefreshing] = useState(false);
    const [stationsMessage, setStationsMessage] = useState<string | null>(null);
    const providerStations = stationOptions.filter(
        (station) => station.provider === monitoringProvider,
    );

    async function refreshStations() {
        if (monitoringProvider === 'none') {
            return;
        }

        setStationsRefreshing(true);
        setStationsMessage(null);

        try {
            const response = await fetch('/remote-stations/refresh', {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN':
                        document
                            .querySelector('meta[name="csrf-token"]')
                            ?.getAttribute('content') ?? '',
                },
                body: JSON.stringify({
                    provider: monitoringProvider,
                    installation_id: installation?.id ?? null,
                }),
            });
            const data = (await response.json()) as {
                stations?: RemoteStationOption[];
                message?: string;
            };

            if (!response.ok || !data.stations) {
                throw new Error(data.message || 'Unable to refresh stations.');
            }

            setStationOptions((current) => [
                ...current.filter(
                    (station) => station.provider !== monitoringProvider,
                ),
                ...data.stations!,
            ]);
            setStationsMessage(`${data.stations.length} stations available.`);
        } catch (error) {
            setStationsMessage(
                error instanceof Error
                    ? error.message
                    : 'Unable to refresh stations.',
            );
        } finally {
            setStationsRefreshing(false);
        }
    }

    if (serviceType === 'solar') {
        return (
            <div className="grid gap-4 md:grid-cols-4">
                <div className={fieldClassName}>
                    <Label htmlFor="monitoring_provider">
                        {t.monitoringProvider}
                    </Label>
                    <Select
                        value={monitoringProvider}
                        onValueChange={(value) => {
                            setMonitoringProvider(value);
                            setRemoteStationId('none');
                        }}
                    >
                        <SelectTrigger
                            id="monitoring_provider"
                            className="w-full"
                        >
                            <SelectValue placeholder={t.selectProvider} />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="none">
                                {t.noMonitoring}
                            </SelectItem>
                            <SelectItem value="huawei">
                                Huawei FusionSolar
                            </SelectItem>
                            <SelectItem value="deye">Deye Cloud</SelectItem>
                        </SelectContent>
                    </Select>
                    <input
                        type="hidden"
                        name="monitoring_provider"
                        value={
                            monitoringProvider === 'none'
                                ? ''
                                : monitoringProvider
                        }
                    />
                    <InputError message={errors.monitoring_provider} />
                </div>
                <div className={fieldClassName}>
                    <div className="flex items-center justify-between gap-2">
                        <Label htmlFor="remote_station_id">
                            {t.remoteStation}
                        </Label>
                        <Button
                            type="button"
                            size="icon"
                            variant="ghost"
                            className="size-7"
                            title="Refresh available stations"
                            disabled={
                                monitoringProvider === 'none' ||
                                stationsRefreshing
                            }
                            onClick={refreshStations}
                        >
                            <RefreshCw
                                className={
                                    stationsRefreshing ? 'animate-spin' : ''
                                }
                            />
                        </Button>
                    </div>
                    <Select
                        value={remoteStationId}
                        disabled={monitoringProvider === 'none'}
                        onValueChange={setRemoteStationId}
                    >
                        <SelectTrigger
                            id="remote_station_id"
                            className="w-full"
                        >
                            <SelectValue placeholder={t.selectRemoteStation} />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="none">
                                {t.noRemoteStation}
                            </SelectItem>
                            {providerStations.map((station) => (
                                <SelectItem
                                    key={station.id}
                                    value={station.id.toString()}
                                >
                                    {station.name || station.remote_identifier}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <input
                        type="hidden"
                        name="remote_station_id"
                        value={
                            remoteStationId === 'none' ? '' : remoteStationId
                        }
                    />
                    <InputError message={errors.remote_station_id} />
                    <p className="text-xs text-muted-foreground">
                        {stationsMessage ||
                            'Available stations update automatically every 5 minutes.'}
                    </p>
                </div>
                <Field
                    id="system_power_kw"
                    label={t.systemKw}
                    type="number"
                    step="0.01"
                    placeholder="5.50"
                    defaultValue={installation?.system_power_kw}
                    error={errors.system_power_kw}
                />
                <Field
                    id="panel_count"
                    label={t.panelCount}
                    type="number"
                    step="1"
                    placeholder="12"
                    defaultValue={installation?.panel_count?.toString()}
                    error={errors.panel_count}
                />
                <Field
                    id="inverter_brand"
                    label={t.inverterBrand}
                    placeholder="Huawei, Fronius, SolarEdge..."
                    defaultValue={installation?.inverter_brand}
                    error={errors.inverter_brand}
                />
                <Field
                    id="battery_capacity_kwh"
                    label={t.batteryKwh}
                    type="number"
                    step="0.01"
                    placeholder="10.00"
                    defaultValue={installation?.battery_capacity_kwh}
                    error={errors.battery_capacity_kwh}
                />
            </div>
        );
    }

    if (serviceType === 'ev_charger') {
        return (
            <div className="grid gap-4 md:grid-cols-2">
                <Field
                    id="charger_power_kw"
                    label={t.chargerKw}
                    type="number"
                    step="0.01"
                    placeholder="7.40"
                    defaultValue={installation?.charger_power_kw}
                    error={errors.charger_power_kw}
                />
                <Field
                    id="charger_connector_type"
                    label={t.chargerConnector}
                    placeholder="Type 2, CCS..."
                    defaultValue={installation?.charger_connector_type}
                    error={errors.charger_connector_type}
                />
            </div>
        );
    }

    if (serviceType === 'hvac') {
        return (
            <Field
                id="hvac_system_type"
                label={t.climaSystem}
                placeholder="Split, ducted, aerothermal..."
                defaultValue={installation?.hvac_system_type}
                error={errors.hvac_system_type}
            />
        );
    }

    if (serviceType === 'electrical') {
        return (
            <div className="grid gap-4 md:grid-cols-2">
                <Field
                    id="electrical_scope"
                    label={t.electricalScope}
                    placeholder="Panel upgrade, wiring, certification..."
                    defaultValue={installation?.electrical_scope}
                    error={errors.electrical_scope}
                />
                <Field
                    id="electrical_voltage_v"
                    label={t.electricalVoltage}
                    type="number"
                    step="1"
                    placeholder="230"
                    defaultValue={installation?.electrical_voltage_v?.toString()}
                    error={errors.electrical_voltage_v}
                />
                <SelectField
                    id="electrical_phase"
                    label={t.electricalPhase}
                    defaultValue={installation?.electrical_phase ?? ''}
                    options={[
                        ['single_phase', t.singlePhase],
                        ['three_phase', t.threePhase],
                        ['other', t.other],
                    ]}
                    error={errors.electrical_phase}
                />
                <Field
                    id="contracted_power_kw"
                    label={t.contractedPower}
                    type="number"
                    step="0.01"
                    placeholder="5.75"
                    defaultValue={installation?.contracted_power_kw}
                    error={errors.contracted_power_kw}
                />
                <label className="flex items-center gap-3 rounded-md border p-3 text-sm">
                    <input
                        type="hidden"
                        name="electrical_certificate_required"
                        value="0"
                    />
                    <input
                        type="checkbox"
                        name="electrical_certificate_required"
                        value="1"
                        defaultChecked={
                            installation?.electrical_certificate_required
                        }
                        className="size-4"
                    />
                    {t.electricalCertificateRequired}
                </label>
            </div>
        );
    }

    return (
        <div className="grid gap-4 md:grid-cols-2">
            <Field
                id="security_system_type"
                label={t.securitySystemType}
                placeholder="Intrusion alarm, CCTV, access control..."
                defaultValue={installation?.security_system_type}
                error={errors.security_system_type}
            />
            <Field
                id="security_zones"
                label={t.securityZones}
                type="number"
                step="1"
                placeholder="8"
                defaultValue={installation?.security_zones?.toString()}
                error={errors.security_zones}
            />
            <SelectField
                id="security_monitoring_mode"
                label={t.securityMonitoringMode}
                defaultValue={installation?.security_monitoring_mode ?? ''}
                options={[
                    ['local', t.localAlarm],
                    ['self_monitored', t.selfMonitored],
                    ['alarm_receiving_centre', t.alarmReceivingCentre],
                    ['hybrid', t.hybridMonitoring],
                ]}
                error={errors.security_monitoring_mode}
            />
            <SelectField
                id="security_grade"
                label={t.securityGrade}
                defaultValue={installation?.security_grade ?? ''}
                options={[
                    ['unclassified', t.unclassified],
                    ['grade_1', 'Grade 1'],
                    ['grade_2', 'Grade 2'],
                    ['grade_3', 'Grade 3'],
                    ['grade_4', 'Grade 4'],
                ]}
                error={errors.security_grade}
            />
        </div>
    );
}

function SelectField({
    id,
    label,
    defaultValue,
    options,
    error,
}: {
    id: string;
    label: string;
    defaultValue: string;
    options: Array<readonly [string, string]>;
    error?: string;
}) {
    return (
        <div className={fieldClassName}>
            <Label htmlFor={id}>{label}</Label>
            <select
                id={id}
                name={id}
                defaultValue={defaultValue}
                className="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
            >
                <option value="">—</option>
                {options.map(([value, optionLabel]) => (
                    <option key={value} value={value}>
                        {optionLabel}
                    </option>
                ))}
            </select>
            <InputError message={error} />
        </div>
    );
}

function Field({
    id,
    label,
    error,
    defaultValue,
    required,
    type = 'text',
    step,
    placeholder,
}: {
    id: string;
    label: string;
    error?: string;
    defaultValue?: string | null;
    required?: boolean;
    type?: string;
    step?: string;
    placeholder?: string;
}) {
    return (
        <div className={fieldClassName}>
            <Label htmlFor={id}>{label}</Label>
            <Input
                id={id}
                name={id}
                type={type}
                min={type === 'number' ? '0' : undefined}
                step={step}
                required={required}
                placeholder={placeholder}
                defaultValue={defaultValue ?? ''}
            />
            <InputError message={error} />
        </div>
    );
}

function TextareaField({
    id,
    label,
    error,
    defaultValue,
    placeholder,
}: {
    id: string;
    label: string;
    error?: string;
    defaultValue?: string | null;
    placeholder?: string;
}) {
    return (
        <div className={fieldClassName}>
            <Label htmlFor={id}>{label}</Label>
            <textarea
                id={id}
                name={id}
                rows={4}
                className={textareaClassName}
                placeholder={placeholder}
                defaultValue={defaultValue ?? ''}
            />
            <InputError message={error} />
        </div>
    );
}

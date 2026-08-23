import { Form, Head, Link } from '@inertiajs/react';
import {
    BatteryCharging,
    LoaderCircle,
    PlugZap,
    Snowflake,
    Sparkles,
    Sun,
    ThumbsUp,
    PencilLine,
    Plus,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { lazy, Suspense, useState } from 'react';
import { AssetRmaPanel } from '@/components/installations/asset-rma-panel';
import type { AssetRma } from '@/components/installations/asset-rma-panel';
import {
    serviceLabels,
    statusLabels,
} from '@/components/installations/installation-form';
import type { MonitoringPoint } from '@/components/installations/monitoring-chart';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useLocale } from '@/hooks/use-locale';

type Installation = {
    id: number;
    person_id: number | null;
    customer_name: string | null;
    customer_company: string | null;
    people: Array<{
        id: number;
        name: string;
        company_name: string | null;
        email: string | null;
        phone: string | null;
        role: string;
        ownership_percentage: string | null;
        is_primary: boolean;
    }>;
    service_type: keyof typeof serviceLabels;
    deye_station_id: string | null;
    deye_station_name: string | null;
    deye_status: string | null;
    deye_current_power_kw: string | null;
    deye_today_energy_kwh: string | null;
    deye_status_message: string | null;
    deye_synced_at: string | null;
    huawei_station_code: string | null;
    huawei_station_name: string | null;
    huawei_status: string | null;
    huawei_current_power_kw: string | null;
    huawei_today_energy_kwh: string | null;
    huawei_status_message: string | null;
    huawei_synced_at: string | null;
    status: keyof typeof statusLabels;
    financial_status: 'not_required' | 'pending' | 'partial' | 'cleared';
    financially_cleared_at: string | null;
    project_reference: string | null;
    site_name: string | null;
    site_address_line_1: string | null;
    site_street_number: string | null;
    site_address_line_2: string | null;
    site_city: string | null;
    site_locality: string | null;
    site_province_state: string | null;
    site_postal_code: string | null;
    site_country: string | null;
    site_latitude: string | null;
    site_longitude: string | null;
    survey_date: string | null;
    scheduled_date: string | null;
    completed_date: string | null;
    estimated_budget: string | null;
    system_power_kw: string | null;
    panel_count: number | null;
    inverter_brand: string | null;
    battery_capacity_kwh: string | null;
    charger_power_kw: string | null;
    charger_connector_type: string | null;
    hvac_system_type: string | null;
    electrical_scope: string | null;
    site_notes: string | null;
    technical_notes: string | null;
};

type MonitoringAlert = {
    id: number;
    provider: string;
    status: string;
    message: string;
    occurrences: number;
    started_at: string;
    last_seen_at: string;
    resolved_at: string | null;
};

function financialStatusLabel(status: Installation['financial_status']) {
    return {
        not_required: 'No payment required',
        pending: 'Acceptance payment pending',
        partial: 'Acceptance payment partial',
        cleared: 'Financially cleared',
    }[status];
}
type Activity = {
    id: number;
    type: string;
    title: string;
    status: string;
    due_at: string | null;
    assigned_user: { name: string } | null;
};
type Quote = {
    id: number;
    number: string;
    status: string;
    amount: string;
    valid_until: string | null;
};
type Asset = {
    id: number;
    asset_tag: string;
    name: string;
    category: string;
    manufacturer: string | null;
    model: string | null;
    serial_number: string | null;
    status: string;
    warranty_ends_on: string | null;
    replacement: { asset_tag: string; name: string } | null;
    events: {
        id: number;
        type: string;
        summary: string;
        occurred_at: string;
    }[];
    rmas: AssetRma[];
};
type Profitability = {
    planned_revenue: number;
    actual_revenue: number;
    planned_cost: number;
    actual_cost: number;
    planned_margin: number;
    actual_margin: number;
};
type AssetOptions = {
    serials: {
        id: number;
        serial_number: string;
        product: { name: string; sku: string | null };
    }[];
    purchases: { id: number; number: string }[];
    workOrders: { id: number; number: string; title: string }[];
    tickets: { id: number; number: string; title: string }[];
};
type CustomerOption = {
    id: number;
    name: string;
    company_name: string | null;
};
type AiGeneration = {
    uuid: string;
    status: 'queued' | 'processing' | 'completed' | 'failed';
    mode: 'quick' | 'operational';
    result: {
        summary: string;
        health_assessment?: string;
        commercial_angle?: string;
        missing_information: string[];
        risks: string[];
        next_actions: string[];
        action_items?: {
            title: string;
            type: 'task' | 'visit' | 'reminder';
            priority: 'low' | 'normal' | 'high';
            reason: string;
            due_in_days: number;
        }[];
        confidence: number;
    } | null;
    sources: { title: string; version: number; score: number | null }[];
    model: string | null;
    duration_ms: number | null;
    message: string | null;
    action_proposals: {
        uuid: string;
        action: 'create_crm_activity';
        status: 'proposed' | 'executed' | 'cancelled' | 'failed';
        payload: {
            title: string;
            type: 'task' | 'visit' | 'reminder';
            priority: 'low' | 'normal' | 'high';
            reason: string;
            due_in_days: number;
        };
        executed_at: string | null;
    }[];
};
const MonitoringChart = lazy(
    () => import('@/components/installations/monitoring-chart'),
);

export default function InstallationShow({
    installation,
    monitoringHistory,
    monitoringAlerts,
    activities,
    quotes,
    assets,
    assetOptions,
    profitability,
    canManageAssets,
    latestAiGeneration,
    customers,
}: {
    installation: Installation;
    monitoringHistory: MonitoringPoint[];
    monitoringAlerts: MonitoringAlert[];
    activities: Activity[];
    quotes: Quote[];
    assets: Asset[];
    assetOptions: AssetOptions;
    profitability: Profitability;
    canManageAssets: boolean;
    latestAiGeneration: AiGeneration | null;
    customers: CustomerOption[];
}) {
    const { locale } = useLocale();
    const title =
        installation.project_reference ||
        serviceLabels[installation.service_type];
    const [aiGeneration, setAiGeneration] = useState<AiGeneration | null>(
        latestAiGeneration,
    );
    const [aiError, setAiError] = useState<string | null>(null);
    const [aiLoading, setAiLoading] = useState(false);
    const [aiActivityMessage, setAiActivityMessage] = useState<string | null>(
        null,
    );
    const [aiActivityLoading, setAiActivityLoading] = useState(false);
    const [aiActionConfirmationOpen, setAiActionConfirmationOpen] =
        useState(false);
    const [selectedAiProposals, setSelectedAiProposals] = useState<string[]>(
        [],
    );
    const [aiFeedbackMessage, setAiFeedbackMessage] = useState<string | null>(
        null,
    );
    const [aiFeedbackLoading, setAiFeedbackLoading] = useState(false);
    const [aiCorrectionOpen, setAiCorrectionOpen] = useState(false);
    const [aiCorrectionSummary, setAiCorrectionSummary] = useState('');
    const [aiCorrectionRisks, setAiCorrectionRisks] = useState('');
    const [aiCorrectionActions, setAiCorrectionActions] = useState('');
    const [aiCorrectionNotes, setAiCorrectionNotes] = useState('');
    const aiResult = aiGeneration?.result;
    const aiActionProposals = aiGeneration?.action_proposals ?? [];
    const pendingAiActionProposals = aiActionProposals.filter(
        (proposal) => proposal.status === 'proposed',
    );

    async function generateBriefing(mode: 'quick' | 'operational') {
        setAiLoading(true);
        setAiError(null);

        try {
            const response = await fetch(
                `/installations/${installation.id}/ai/briefing`,
                {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN':
                            document
                                .querySelector('meta[name="csrf-token"]')
                                ?.getAttribute('content') ?? '',
                    },
                    body: JSON.stringify({ mode }),
                },
            );
            const data = (await response.json()) as {
                generation?: AiGeneration;
                message?: string;
            };

            if (!response.ok) {
                throw new Error(data.message || 'Unable to generate briefing.');
            }

            if (!data.generation) {
                throw new Error('No generation was returned.');
            }

            setAiGeneration(data.generation);

            let generation = data.generation;

            while (
                generation.status === 'queued' ||
                generation.status === 'processing'
            ) {
                await new Promise((resolve) =>
                    window.setTimeout(resolve, 1000),
                );
                const statusResponse = await fetch(
                    `/installations/${installation.id}/ai/briefings/${generation.uuid}`,
                    { headers: { Accept: 'application/json' } },
                );
                const statusData = (await statusResponse.json()) as {
                    generation: AiGeneration;
                };

                if (!statusResponse.ok) {
                    throw new Error('Unable to check briefing status.');
                }

                generation = statusData.generation;
                setAiGeneration(generation);
            }

            if (generation.status === 'failed') {
                throw new Error(
                    generation.message || 'Unable to generate briefing.',
                );
            }
        } catch (error) {
            setAiError(
                error instanceof Error
                    ? error.message
                    : 'Unable to generate briefing.',
            );
        } finally {
            setAiLoading(false);
        }
    }

    async function createAiActivities() {
        if (!aiGeneration?.result) {
            return;
        }

        setAiActivityLoading(true);
        setAiActivityMessage(null);
        setAiError(null);

        try {
            const response = await fetch(
                `/installations/${installation.id}/ai/briefings/${aiGeneration.uuid}/activities`,
                {
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
                        confirmed: true,
                        proposal_uuids: selectedAiProposals,
                    }),
                },
            );
            const data = (await response.json()) as {
                created?: { id: number; title: string }[];
                message?: string;
            };

            if (!response.ok) {
                throw new Error(data.message || 'Unable to create activities.');
            }

            setAiActivityMessage(
                `${data.created?.length ?? 0} CRM activities ready in Operations.`,
            );
            setAiGeneration((current) =>
                current
                    ? {
                          ...current,
                          action_proposals: current.action_proposals.map(
                              (proposal) =>
                                  selectedAiProposals.includes(proposal.uuid)
                                      ? {
                                            ...proposal,
                                            status: 'executed',
                                            executed_at:
                                                new Date().toISOString(),
                                        }
                                      : proposal,
                          ),
                      }
                    : current,
            );
            setAiActionConfirmationOpen(false);
        } catch (error) {
            setAiError(
                error instanceof Error
                    ? error.message
                    : 'Unable to create activities.',
            );
        } finally {
            setAiActivityLoading(false);
        }
    }

    function openAiCorrection() {
        if (!aiResult) {
            return;
        }

        setAiCorrectionSummary(aiResult.summary);
        setAiCorrectionRisks(aiResult.risks.join('\n'));
        setAiCorrectionActions(aiResult.next_actions.join('\n'));
        setAiCorrectionOpen(true);
    }

    async function submitAiFeedback(rating: 'helpful' | 'needs_correction') {
        if (!aiGeneration?.result) {
            return;
        }

        setAiFeedbackLoading(true);
        setAiError(null);

        try {
            const response = await fetch(
                `/installations/${installation.id}/ai/briefings/${aiGeneration.uuid}/feedback`,
                {
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
                        rating,
                        notes:
                            rating === 'needs_correction'
                                ? aiCorrectionNotes
                                : null,
                        summary:
                            rating === 'needs_correction'
                                ? aiCorrectionSummary
                                : null,
                        risks:
                            rating === 'needs_correction'
                                ? aiCorrectionRisks
                                : null,
                        next_actions:
                            rating === 'needs_correction'
                                ? aiCorrectionActions
                                : null,
                    }),
                },
            );
            const data = (await response.json()) as { message?: string };

            if (!response.ok) {
                throw new Error(data.message || 'Unable to save feedback.');
            }

            setAiFeedbackMessage(
                rating === 'helpful'
                    ? 'Marked as useful for Arevia evaluation.'
                    : 'Correction saved for administrator review.',
            );
            setAiCorrectionOpen(false);
        } catch (error) {
            setAiError(
                error instanceof Error
                    ? error.message
                    : 'Unable to save feedback.',
            );
        } finally {
            setAiFeedbackLoading(false);
        }
    }

    return (
        <>
            <Head title={title} />

            <div className="flex min-w-0 flex-1 flex-col gap-6 p-4 md:p-6">
                <Card className="rounded-lg">
                    <CardHeader className="flex-col items-start justify-between gap-4 sm:flex-row">
                        <div>
                            <div className="flex flex-wrap items-center gap-2">
                                <ServiceIcon
                                    service={installation.service_type}
                                />
                                <CardTitle className="text-xl tracking-tight">
                                    {title}
                                </CardTitle>
                                <Badge variant="outline">
                                    {statusLabels[installation.status]}
                                </Badge>
                                <Badge variant="outline">
                                    {financialStatusLabel(
                                        installation.financial_status,
                                    )}
                                </Badge>
                            </div>
                            <CardDescription>
                                {installation.customer_company ||
                                    installation.customer_name ||
                                    'No customer'}
                            </CardDescription>
                        </div>
                        <div className="flex w-full flex-wrap gap-2 sm:w-auto sm:justify-end">
                            <Button variant="outline" asChild>
                                <Link href="/installations">
                                    All installations
                                </Link>
                            </Button>
                            {installation.person_id !== null ? (
                                <Button variant="outline" asChild>
                                    <Link
                                        href={`/people/${installation.person_id}`}
                                    >
                                        Customer
                                    </Link>
                                </Button>
                            ) : null}
                            <Button asChild>
                                <Link
                                    href={`/installations/${installation.id}/edit`}
                                >
                                    Edit
                                </Link>
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent className="grid gap-6 lg:grid-cols-3">
                        <InfoBlock
                            title="Site"
                            items={[
                                ['Site name', installation.site_name],
                                ['Address', installation.site_address_line_1],
                                ['Number', installation.site_street_number],
                                ['Address 2', installation.site_address_line_2],
                                ['Municipality', installation.site_city],
                                ['Locality', installation.site_locality],
                                ['Province', installation.site_province_state],
                                ['Postal code', installation.site_postal_code],
                                ['Country', installation.site_country],
                                ['Latitude', installation.site_latitude],
                                ['Longitude', installation.site_longitude],
                            ]}
                        />
                        <InfoBlock
                            title="Dates"
                            items={[
                                ['Survey', installation.survey_date],
                                ['Scheduled', installation.scheduled_date],
                                ['Completed', installation.completed_date],
                                ['Budget', installation.estimated_budget],
                                [
                                    'Financial clearance',
                                    installation.financially_cleared_at,
                                ],
                            ]}
                        />
                        <InfoBlock
                            title="Technical"
                            items={technicalItems(installation)}
                        />
                    </CardContent>
                </Card>

                <Card className="rounded-lg">
                    <CardHeader>
                        <CardTitle>Personas vinculadas</CardTitle>
                        <CardDescription>
                            Propietarios, copropietarios y contactos autorizados
                            de esta instalación.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-5 lg:grid-cols-[1fr_24rem]">
                        <div className="divide-y border-y">
                            {installation.people.map((person) => (
                                <div
                                    key={person.id}
                                    className="flex flex-wrap items-center justify-between gap-3 py-3"
                                >
                                    <div className="min-w-0 text-sm">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <Link
                                                href={`/people/${person.id}`}
                                                className="font-medium hover:underline"
                                            >
                                                {person.company_name ||
                                                    person.name}
                                            </Link>
                                            <Badge variant="outline">
                                                {personRoleLabel(person.role)}
                                            </Badge>
                                            {person.is_primary && (
                                                <Badge>Titular principal</Badge>
                                            )}
                                        </div>
                                        <div className="mt-1 text-xs text-muted-foreground">
                                            {[
                                                person.email,
                                                person.phone,
                                                person.ownership_percentage
                                                    ? `${person.ownership_percentage}% propiedad`
                                                    : null,
                                            ]
                                                .filter(Boolean)
                                                .join(' · ') ||
                                                'Sin datos de contacto'}
                                        </div>
                                    </div>
                                    {!person.is_primary && (
                                        <Form
                                            action={`/installations/${installation.id}/people/${person.id}`}
                                            method="delete"
                                        >
                                            {({ processing }) => (
                                                <Button
                                                    size="sm"
                                                    variant="ghost"
                                                    disabled={processing}
                                                >
                                                    Quitar
                                                </Button>
                                            )}
                                        </Form>
                                    )}
                                </div>
                            ))}
                            {installation.people.length === 0 && (
                                <div className="py-6 text-sm text-muted-foreground">
                                    No hay personas vinculadas.
                                </div>
                            )}
                        </div>
                        <Form
                            action={`/installations/${installation.id}/people`}
                            method="post"
                            className="grid content-start gap-3 rounded-md border p-4"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <label className="grid gap-1 text-sm">
                                        <span className="font-medium">
                                            Persona
                                        </span>
                                        <select
                                            name="person_id"
                                            required
                                            className="h-9 rounded-md border border-input bg-background px-3"
                                        >
                                            <option value="">
                                                Seleccionar cliente
                                            </option>
                                            {customers
                                                .filter(
                                                    (customer) =>
                                                        !installation.people.some(
                                                            (person) =>
                                                                person.id ===
                                                                customer.id,
                                                        ),
                                                )
                                                .map((customer) => (
                                                    <option
                                                        key={customer.id}
                                                        value={customer.id}
                                                    >
                                                        {customer.company_name ||
                                                            customer.name}
                                                    </option>
                                                ))}
                                        </select>
                                        {errors.person_id && (
                                            <span className="text-xs text-destructive">
                                                {errors.person_id}
                                            </span>
                                        )}
                                    </label>
                                    <label className="grid gap-1 text-sm">
                                        <span className="font-medium">
                                            Relación
                                        </span>
                                        <select
                                            name="role"
                                            defaultValue="co_owner"
                                            className="h-9 rounded-md border border-input bg-background px-3"
                                        >
                                            <option value="owner">
                                                Propietario
                                            </option>
                                            <option value="co_owner">
                                                Copropietario
                                            </option>
                                            <option value="tenant">
                                                Inquilino
                                            </option>
                                            <option value="authorized_contact">
                                                Contacto autorizado
                                            </option>
                                        </select>
                                    </label>
                                    <label className="grid gap-1 text-sm">
                                        <span className="font-medium">
                                            Porcentaje de propiedad (opcional)
                                        </span>
                                        <input
                                            name="ownership_percentage"
                                            type="number"
                                            min="0.01"
                                            max="100"
                                            step="0.01"
                                            className="h-9 rounded-md border border-input bg-background px-3"
                                        />
                                    </label>
                                    <Button disabled={processing}>
                                        Asignar persona
                                    </Button>
                                </>
                            )}
                        </Form>
                    </CardContent>
                </Card>

                {installation.deye_station_id && (
                    <Card className="rounded-lg">
                        <CardHeader className="flex-row items-start justify-between gap-3">
                            <div>
                                <CardTitle>Deye Cloud monitoring</CardTitle>
                                <CardDescription>
                                    {installation.deye_station_name ||
                                        `Station ${installation.deye_station_id}`}
                                </CardDescription>
                            </div>
                            <Form
                                action={`/installations/${installation.id}/deye/sync`}
                                method="post"
                            >
                                {({ processing }) => (
                                    <Button disabled={processing}>
                                        Sync now
                                    </Button>
                                )}
                            </Form>
                        </CardHeader>
                        <CardContent className="grid gap-4 md:grid-cols-4">
                            <InfoBlock
                                title="Status"
                                items={[
                                    ['State', installation.deye_status],
                                    [
                                        'Detail',
                                        installation.deye_status_message,
                                    ],
                                ]}
                            />
                            <InfoBlock
                                title="Production"
                                items={[
                                    [
                                        'Now (kW)',
                                        installation.deye_current_power_kw,
                                    ],
                                    [
                                        'Today (kWh)',
                                        installation.deye_today_energy_kwh,
                                    ],
                                ]}
                            />
                            <InfoBlock
                                title="Connection"
                                items={[
                                    [
                                        'Station ID',
                                        installation.deye_station_id,
                                    ],
                                    [
                                        'Last sync',
                                        formatEuropeanDateTime(
                                            installation.deye_synced_at,
                                            locale,
                                        ),
                                    ],
                                ]}
                            />
                        </CardContent>
                    </Card>
                )}

                {installation.huawei_station_code && (
                    <Card className="rounded-lg">
                        <CardHeader className="flex-row items-start justify-between gap-3">
                            <div>
                                <CardTitle>
                                    Huawei FusionSolar monitoring
                                </CardTitle>
                                <CardDescription>
                                    {installation.huawei_station_name ||
                                        installation.huawei_station_code}
                                </CardDescription>
                            </div>
                            <Form
                                action={`/installations/${installation.id}/huawei/sync`}
                                method="post"
                            >
                                {({ processing }) => (
                                    <Button disabled={processing}>
                                        Sync now
                                    </Button>
                                )}
                            </Form>
                        </CardHeader>
                        <CardContent className="grid gap-4 md:grid-cols-3">
                            <InfoBlock
                                title="Status"
                                items={[
                                    ['State', installation.huawei_status],
                                    [
                                        'Detail',
                                        installation.huawei_status_message,
                                    ],
                                ]}
                            />
                            <InfoBlock
                                title="Production"
                                items={[
                                    [
                                        'Now (kW)',
                                        installation.huawei_current_power_kw,
                                    ],
                                    [
                                        'Today (kWh)',
                                        installation.huawei_today_energy_kwh,
                                    ],
                                ]}
                            />
                            <InfoBlock
                                title="Connection"
                                items={[
                                    [
                                        'Plant code',
                                        installation.huawei_station_code,
                                    ],
                                    [
                                        'Last sync',
                                        formatEuropeanDateTime(
                                            installation.huawei_synced_at,
                                            locale,
                                        ),
                                    ],
                                ]}
                            />
                        </CardContent>
                    </Card>
                )}

                {installation.deye_station_id ||
                installation.huawei_station_code ? (
                    <Card className="rounded-lg">
                        <CardHeader>
                            <CardTitle>Production history</CardTitle>
                            <CardDescription>
                                Recent synchronized power and daily energy
                                readings.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Suspense
                                fallback={
                                    <div className="h-72 animate-pulse bg-muted" />
                                }
                            >
                                <MonitoringChart points={monitoringHistory} />
                            </Suspense>
                        </CardContent>
                    </Card>
                ) : null}

                <section className="grid gap-6 lg:grid-cols-2">
                    <div className="border-t pt-5">
                        <h2 className="font-semibold">Monitoring incidents</h2>
                        <div className="mt-3 grid gap-2">
                            {monitoringAlerts.length ? (
                                monitoringAlerts.map((alert) => (
                                    <div
                                        key={alert.id}
                                        className="flex items-start justify-between gap-3 border-b py-2 text-sm"
                                    >
                                        <div>
                                            <div className="font-medium">
                                                {alert.provider.toUpperCase()} ·{' '}
                                                {alert.status}
                                            </div>
                                            <div className="text-muted-foreground">
                                                {alert.message}
                                            </div>
                                        </div>
                                        <Badge variant="outline">
                                            {alert.resolved_at
                                                ? 'Resolved'
                                                : `${alert.occurrences} checks`}
                                        </Badge>
                                    </div>
                                ))
                            ) : (
                                <p className="text-sm text-muted-foreground">
                                    No monitoring incidents.
                                </p>
                            )}
                        </div>
                    </div>
                    <div className="border-t pt-5">
                        <div className="flex items-center justify-between">
                            <h2 className="font-semibold">Operations</h2>
                            <Button size="sm" variant="outline" asChild>
                                <Link href="/operations">Open agenda</Link>
                            </Button>
                        </div>
                        <div className="mt-3 grid gap-2">
                            {activities.slice(0, 5).map((activity) => (
                                <div
                                    key={activity.id}
                                    className="flex justify-between border-b py-2 text-sm"
                                >
                                    <div>
                                        <div className="font-medium">
                                            {activity.title}
                                        </div>
                                        <div className="text-xs text-muted-foreground">
                                            {activity.type} ·{' '}
                                            {activity.assigned_user?.name ||
                                                'Unassigned'}
                                        </div>
                                    </div>
                                    <Badge variant="outline">
                                        {activity.status}
                                    </Badge>
                                </div>
                            ))}
                            {quotes.slice(0, 3).map((quote) => (
                                <div
                                    key={quote.id}
                                    className="flex justify-between border-b py-2 text-sm"
                                >
                                    <div className="font-medium">
                                        {quote.number}
                                    </div>
                                    <div>
                                        {Number(quote.amount).toLocaleString(
                                            'es-ES',
                                            {
                                                style: 'currency',
                                                currency: 'EUR',
                                            },
                                        )}{' '}
                                        · {quote.status}
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                </section>

                <section className="grid gap-6 lg:grid-cols-[1.4fr_1fr]">
                    <Card className="rounded-lg">
                        <CardHeader className="flex-row items-start justify-between gap-3">
                            <div>
                                <CardTitle>Installed assets</CardTitle>
                                <CardDescription>
                                    Equipment, warranty and technical
                                    traceability.
                                </CardDescription>
                            </div>
                            {canManageAssets && (
                                <AssetDialog
                                    installationId={installation.id}
                                    options={assetOptions}
                                />
                            )}
                        </CardHeader>
                        <CardContent className="grid gap-3">
                            {assets.map((asset) => (
                                <div
                                    key={asset.id}
                                    className="border-t pt-3 text-sm"
                                >
                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                        <div className="font-medium">
                                            {asset.asset_tag} · {asset.name}
                                        </div>
                                        <Badge variant="outline">
                                            {asset.status}
                                        </Badge>
                                    </div>
                                    <div className="mt-1 text-muted-foreground">
                                        {[
                                            asset.manufacturer,
                                            asset.model,
                                            asset.serial_number,
                                        ]
                                            .filter(Boolean)
                                            .join(' · ') || asset.category}
                                    </div>
                                    {asset.replacement && (
                                        <div className="mt-1 text-xs">
                                            Replaced by{' '}
                                            {asset.replacement.asset_tag}
                                        </div>
                                    )}
                                    {asset.events[0] && (
                                        <div className="mt-1 text-xs text-muted-foreground">
                                            Latest: {asset.events[0].summary}
                                        </div>
                                    )}
                                    {(canManageAssets ||
                                        asset.rmas.length > 0) && (
                                        <AssetRmaPanel
                                            installationId={installation.id}
                                            asset={asset}
                                            options={assetOptions}
                                            canManage={canManageAssets}
                                        />
                                    )}
                                    {canManageAssets && (
                                        <div className="mt-2 flex flex-wrap gap-4">
                                            <details>
                                                <summary className="cursor-pointer text-xs underline underline-offset-2">
                                                    Add technical event
                                                </summary>
                                                <Form
                                                    action={`/installations/${installation.id}/assets/${asset.id}/events`}
                                                    method="post"
                                                    className="mt-2 grid gap-2"
                                                >
                                                    <input
                                                        type="hidden"
                                                        name="type"
                                                        value="note"
                                                    />
                                                    <input
                                                        type="hidden"
                                                        name="occurred_at"
                                                        value={localDateTimeNow()}
                                                    />
                                                    <Input
                                                        name="summary"
                                                        required
                                                        placeholder="Event summary"
                                                    />
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                    >
                                                        Save event
                                                    </Button>
                                                </Form>
                                            </details>
                                            {!asset.replacement &&
                                                asset.status !== 'replaced' && (
                                                    <details>
                                                        <summary className="cursor-pointer text-xs underline underline-offset-2">
                                                            Register replacement
                                                        </summary>
                                                        <Form
                                                            action={`/installations/${installation.id}/assets/${asset.id}/replacement`}
                                                            method="post"
                                                            className="mt-2 grid gap-2"
                                                        >
                                                            <input
                                                                type="hidden"
                                                                name="name"
                                                                value={
                                                                    asset.name
                                                                }
                                                            />
                                                            <input
                                                                type="hidden"
                                                                name="category"
                                                                value={
                                                                    asset.category
                                                                }
                                                            />
                                                            <input
                                                                type="hidden"
                                                                name="status"
                                                                value="active"
                                                            />
                                                            <input
                                                                type="hidden"
                                                                name="commissioned_at"
                                                                value={localDateTimeNow()}
                                                            />
                                                            <Input
                                                                name="serial_number"
                                                                required
                                                                placeholder="Replacement serial"
                                                            />
                                                            <Button
                                                                size="sm"
                                                                variant="outline"
                                                            >
                                                                Replace asset
                                                            </Button>
                                                        </Form>
                                                    </details>
                                                )}
                                        </div>
                                    )}
                                </div>
                            ))}
                            {assets.length === 0 && (
                                <p className="text-sm text-muted-foreground">
                                    No installed assets registered.
                                </p>
                            )}
                        </CardContent>
                    </Card>
                    <Card className="rounded-lg">
                        <CardHeader>
                            <CardTitle>Project profitability</CardTitle>
                            <CardDescription>
                                Plan compared with actual execution.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid grid-cols-2 gap-4 text-sm">
                            <Value
                                label="Planned revenue"
                                value={money(profitability.planned_revenue)}
                            />
                            <Value
                                label="Actual revenue"
                                value={money(profitability.actual_revenue)}
                            />
                            <Value
                                label="Planned cost"
                                value={money(profitability.planned_cost)}
                            />
                            <Value
                                label="Actual cost"
                                value={money(profitability.actual_cost)}
                            />
                            <Value
                                label="Planned margin"
                                value={money(profitability.planned_margin)}
                            />
                            <Value
                                label="Actual margin"
                                value={money(profitability.actual_margin)}
                            />
                        </CardContent>
                    </Card>
                </section>

                <Card className="rounded-lg">
                    <CardHeader className="flex-row items-start justify-between gap-3">
                        <div>
                            <div className="flex items-center gap-2">
                                <Sparkles className="size-5 text-muted-foreground" />
                                <CardTitle>Arevia</CardTitle>
                            </div>
                            <CardDescription>
                                Private analysis using CRM data and indexed
                                knowledge.
                            </CardDescription>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            <Button
                                variant="outline"
                                onClick={() => generateBriefing('quick')}
                                disabled={aiLoading}
                            >
                                {aiLoading ? (
                                    <LoaderCircle className="size-4 animate-spin" />
                                ) : (
                                    <Sparkles className="size-4" />
                                )}
                                Quick summary
                            </Button>
                            <Button
                                onClick={() => generateBriefing('operational')}
                                disabled={aiLoading}
                            >
                                {aiLoading ? (
                                    <LoaderCircle className="size-4 animate-spin" />
                                ) : (
                                    <Sparkles className="size-4" />
                                )}
                                Operational plan
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent>
                        {aiError ? (
                            <div className="rounded-md border border-destructive/30 bg-destructive/10 p-4 text-sm text-destructive">
                                {aiError}
                            </div>
                        ) : (
                            <div className="space-y-4 rounded-md border p-4 text-sm text-muted-foreground">
                                {aiGeneration?.result ? (
                                    <>
                                        <p className="text-foreground">
                                            {aiGeneration.result.summary}
                                        </p>
                                        <div className="grid gap-3 md:grid-cols-2">
                                            <div className="rounded-md border bg-muted/30 p-3">
                                                <div className="font-medium text-foreground">
                                                    Health
                                                </div>
                                                <p className="mt-1">
                                                    {aiGeneration.result
                                                        .health_assessment ||
                                                        'No health assessment was returned.'}
                                                </p>
                                            </div>
                                            {aiGeneration.mode ===
                                            'operational' ? (
                                                <div className="rounded-md border bg-muted/30 p-3">
                                                    <div className="font-medium text-foreground">
                                                        Commercial angle
                                                    </div>
                                                    <p className="mt-1">
                                                        {aiGeneration.result
                                                            .commercial_angle ||
                                                            'No commercial angle was returned.'}
                                                    </p>
                                                </div>
                                            ) : null}
                                        </div>
                                        {[
                                            [
                                                'Missing information',
                                                aiGeneration.result
                                                    .missing_information,
                                            ],
                                            [
                                                'Risks',
                                                aiGeneration.result.risks,
                                            ],
                                            [
                                                'Next actions',
                                                aiGeneration.result
                                                    .next_actions,
                                            ],
                                        ].map(([label, items]) => (
                                            <div key={label as string}>
                                                <div className="font-medium text-foreground">
                                                    {label as string}
                                                </div>
                                                {(items as string[]).length >
                                                0 ? (
                                                    <ul className="mt-1 list-disc space-y-1 pl-5">
                                                        {(
                                                            items as string[]
                                                        ).map((item) => (
                                                            <li key={item}>
                                                                {item}
                                                            </li>
                                                        ))}
                                                    </ul>
                                                ) : (
                                                    <p className="mt-1">
                                                        None identified.
                                                    </p>
                                                )}
                                            </div>
                                        ))}
                                        {aiGeneration.mode === 'operational' &&
                                        aiActionProposals.length > 0 ? (
                                            <div className="border-t pt-3">
                                                <div className="flex flex-wrap items-center justify-between gap-3">
                                                    <div>
                                                        <div className="font-medium text-foreground">
                                                            CRM action items
                                                        </div>
                                                        <p className="text-xs">
                                                            Review each proposal
                                                            before it can modify
                                                            CRM data.
                                                        </p>
                                                    </div>
                                                    {pendingAiActionProposals.length >
                                                    0 ? (
                                                        <Button
                                                            size="sm"
                                                            variant="outline"
                                                            onClick={() => {
                                                                setSelectedAiProposals(
                                                                    pendingAiActionProposals.map(
                                                                        (
                                                                            proposal,
                                                                        ) =>
                                                                            proposal.uuid,
                                                                    ),
                                                                );
                                                                setAiActionConfirmationOpen(
                                                                    true,
                                                                );
                                                            }}
                                                        >
                                                            <Sparkles className="size-4" />
                                                            Review tasks
                                                        </Button>
                                                    ) : (
                                                        <Badge variant="outline">
                                                            Executed
                                                        </Badge>
                                                    )}
                                                </div>
                                                <div className="mt-3 grid gap-2">
                                                    {aiActionProposals.map(
                                                        (proposal) => (
                                                            <div
                                                                key={
                                                                    proposal.uuid
                                                                }
                                                                className="rounded-md border p-3"
                                                            >
                                                                <div className="flex flex-wrap items-center gap-2">
                                                                    <span className="font-medium text-foreground">
                                                                        {
                                                                            proposal
                                                                                .payload
                                                                                .title
                                                                        }
                                                                    </span>
                                                                    <Badge variant="outline">
                                                                        {
                                                                            proposal
                                                                                .payload
                                                                                .type
                                                                        }
                                                                    </Badge>
                                                                    <Badge variant="outline">
                                                                        {
                                                                            proposal
                                                                                .payload
                                                                                .priority
                                                                        }
                                                                    </Badge>
                                                                    <Badge variant="outline">
                                                                        {
                                                                            proposal
                                                                                .payload
                                                                                .due_in_days
                                                                        }{' '}
                                                                        days
                                                                    </Badge>
                                                                </div>
                                                                <p className="mt-1">
                                                                    {
                                                                        proposal
                                                                            .payload
                                                                            .reason
                                                                    }
                                                                </p>
                                                                <p className="mt-1 text-xs font-medium tracking-wide uppercase">
                                                                    {
                                                                        proposal.status
                                                                    }
                                                                </p>
                                                            </div>
                                                        ),
                                                    )}
                                                </div>
                                                <Dialog
                                                    open={
                                                        aiActionConfirmationOpen
                                                    }
                                                    onOpenChange={
                                                        setAiActionConfirmationOpen
                                                    }
                                                >
                                                    <DialogContent>
                                                        <DialogHeader>
                                                            <DialogTitle>
                                                                Confirm CRM
                                                                changes
                                                            </DialogTitle>
                                                        </DialogHeader>
                                                        <p className="text-sm text-muted-foreground">
                                                            Select the tasks to
                                                            create. This action
                                                            will write to CRM
                                                            and will be fully
                                                            audited.
                                                        </p>
                                                        <div className="grid gap-3">
                                                            {pendingAiActionProposals.map(
                                                                (proposal) => (
                                                                    <label
                                                                        key={
                                                                            proposal.uuid
                                                                        }
                                                                        className="flex cursor-pointer gap-3 rounded-md border p-3"
                                                                    >
                                                                        <input
                                                                            type="checkbox"
                                                                            className="mt-1 size-4"
                                                                            checked={selectedAiProposals.includes(
                                                                                proposal.uuid,
                                                                            )}
                                                                            onChange={() =>
                                                                                setSelectedAiProposals(
                                                                                    (
                                                                                        current,
                                                                                    ) =>
                                                                                        current.includes(
                                                                                            proposal.uuid,
                                                                                        )
                                                                                            ? current.filter(
                                                                                                  (
                                                                                                      uuid,
                                                                                                  ) =>
                                                                                                      uuid !==
                                                                                                      proposal.uuid,
                                                                                              )
                                                                                            : [
                                                                                                  ...current,
                                                                                                  proposal.uuid,
                                                                                              ],
                                                                                )
                                                                            }
                                                                        />
                                                                        <span>
                                                                            <span className="block font-medium">
                                                                                {
                                                                                    proposal
                                                                                        .payload
                                                                                        .title
                                                                                }
                                                                            </span>
                                                                            <span className="text-xs text-muted-foreground">
                                                                                {
                                                                                    proposal
                                                                                        .payload
                                                                                        .type
                                                                                }{' '}
                                                                                ·{' '}
                                                                                {
                                                                                    proposal
                                                                                        .payload
                                                                                        .priority
                                                                                }{' '}
                                                                                ·
                                                                                due
                                                                                in{' '}
                                                                                {
                                                                                    proposal
                                                                                        .payload
                                                                                        .due_in_days
                                                                                }{' '}
                                                                                days
                                                                            </span>
                                                                        </span>
                                                                    </label>
                                                                ),
                                                            )}
                                                        </div>
                                                        <div className="flex justify-end gap-2">
                                                            <Button
                                                                variant="outline"
                                                                onClick={() =>
                                                                    setAiActionConfirmationOpen(
                                                                        false,
                                                                    )
                                                                }
                                                            >
                                                                Cancel
                                                            </Button>
                                                            <Button
                                                                onClick={
                                                                    createAiActivities
                                                                }
                                                                disabled={
                                                                    aiActivityLoading ||
                                                                    selectedAiProposals.length ===
                                                                        0
                                                                }
                                                            >
                                                                {aiActivityLoading ? (
                                                                    <LoaderCircle className="size-4 animate-spin" />
                                                                ) : null}
                                                                Confirm and
                                                                create{' '}
                                                                {
                                                                    selectedAiProposals.length
                                                                }
                                                            </Button>
                                                        </div>
                                                    </DialogContent>
                                                </Dialog>
                                                {aiActivityMessage ? (
                                                    <div className="mt-3 rounded-md border border-emerald-500/30 bg-emerald-500/10 p-3 text-emerald-700">
                                                        {aiActivityMessage}
                                                    </div>
                                                ) : null}
                                            </div>
                                        ) : null}
                                        {aiGeneration.sources.length > 0 ? (
                                            <div className="border-t pt-3">
                                                <div className="font-medium text-foreground">
                                                    Sources
                                                </div>
                                                <div className="mt-2 flex flex-wrap gap-2">
                                                    {aiGeneration.sources.map(
                                                        (source) => (
                                                            <Badge
                                                                key={`${source.title}-${source.version}`}
                                                                variant="outline"
                                                            >
                                                                {source.title} ·
                                                                v
                                                                {source.version}
                                                            </Badge>
                                                        ),
                                                    )}
                                                </div>
                                            </div>
                                        ) : null}
                                        <div className="border-t pt-3">
                                            <div className="flex flex-wrap items-center justify-between gap-3">
                                                <div>
                                                    <div className="font-medium text-foreground">
                                                        Help Arevia improve
                                                    </div>
                                                    <p className="text-xs">
                                                        Validate the answer or
                                                        send a corrected version
                                                        for review.
                                                    </p>
                                                </div>
                                                <div className="flex gap-2">
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        onClick={() =>
                                                            submitAiFeedback(
                                                                'helpful',
                                                            )
                                                        }
                                                        disabled={
                                                            aiFeedbackLoading
                                                        }
                                                    >
                                                        <ThumbsUp /> Useful
                                                    </Button>
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        onClick={
                                                            openAiCorrection
                                                        }
                                                        disabled={
                                                            aiFeedbackLoading
                                                        }
                                                    >
                                                        <PencilLine /> Correct
                                                    </Button>
                                                </div>
                                            </div>
                                            {aiCorrectionOpen ? (
                                                <div className="mt-4 grid gap-3 rounded-md border p-3">
                                                    <label className="grid gap-1">
                                                        <span className="text-xs font-medium text-foreground">
                                                            Corrected summary
                                                        </span>
                                                        <textarea
                                                            rows={3}
                                                            value={
                                                                aiCorrectionSummary
                                                            }
                                                            onChange={(event) =>
                                                                setAiCorrectionSummary(
                                                                    event.target
                                                                        .value,
                                                                )
                                                            }
                                                            className="rounded-md border bg-transparent px-3 py-2"
                                                        />
                                                    </label>
                                                    <div className="grid gap-3 md:grid-cols-2">
                                                        <label className="grid gap-1">
                                                            <span className="text-xs font-medium text-foreground">
                                                                Risks, one per
                                                                line
                                                            </span>
                                                            <textarea
                                                                rows={4}
                                                                value={
                                                                    aiCorrectionRisks
                                                                }
                                                                onChange={(
                                                                    event,
                                                                ) =>
                                                                    setAiCorrectionRisks(
                                                                        event
                                                                            .target
                                                                            .value,
                                                                    )
                                                                }
                                                                className="rounded-md border bg-transparent px-3 py-2"
                                                            />
                                                        </label>
                                                        <label className="grid gap-1">
                                                            <span className="text-xs font-medium text-foreground">
                                                                Actions, one per
                                                                line
                                                            </span>
                                                            <textarea
                                                                rows={4}
                                                                value={
                                                                    aiCorrectionActions
                                                                }
                                                                onChange={(
                                                                    event,
                                                                ) =>
                                                                    setAiCorrectionActions(
                                                                        event
                                                                            .target
                                                                            .value,
                                                                    )
                                                                }
                                                                className="rounded-md border bg-transparent px-3 py-2"
                                                            />
                                                        </label>
                                                    </div>
                                                    <label className="grid gap-1">
                                                        <span className="text-xs font-medium text-foreground">
                                                            Reviewer note
                                                        </span>
                                                        <textarea
                                                            rows={2}
                                                            value={
                                                                aiCorrectionNotes
                                                            }
                                                            onChange={(event) =>
                                                                setAiCorrectionNotes(
                                                                    event.target
                                                                        .value,
                                                                )
                                                            }
                                                            className="rounded-md border bg-transparent px-3 py-2"
                                                        />
                                                    </label>
                                                    <div className="flex justify-end gap-2">
                                                        <Button
                                                            size="sm"
                                                            variant="ghost"
                                                            onClick={() =>
                                                                setAiCorrectionOpen(
                                                                    false,
                                                                )
                                                            }
                                                        >
                                                            Cancel
                                                        </Button>
                                                        <Button
                                                            size="sm"
                                                            onClick={() =>
                                                                submitAiFeedback(
                                                                    'needs_correction',
                                                                )
                                                            }
                                                            disabled={
                                                                aiFeedbackLoading
                                                            }
                                                        >
                                                            Send correction
                                                        </Button>
                                                    </div>
                                                </div>
                                            ) : null}
                                            {aiFeedbackMessage ? (
                                                <p className="mt-3 text-sm text-emerald-700">
                                                    {aiFeedbackMessage}
                                                </p>
                                            ) : null}
                                        </div>
                                    </>
                                ) : aiLoading ? (
                                    <p>Preparing the briefing…</p>
                                ) : (
                                    <p>
                                        Use a quick summary for daily checks or
                                        an operational plan to create CRM tasks.
                                    </p>
                                )}
                            </div>
                        )}
                    </CardContent>
                </Card>

                <Card className="rounded-lg">
                    <CardHeader>
                        <CardTitle>Notes</CardTitle>
                        <CardDescription>
                            Site observations and technical notes.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-6 lg:grid-cols-[1fr_1fr_auto]">
                        <div>
                            <div className="font-medium">Site notes</div>
                            <div className="mt-1 text-sm whitespace-pre-line text-muted-foreground">
                                {installation.site_notes || 'No site notes'}
                            </div>
                        </div>
                        <div>
                            <div className="font-medium">Technical notes</div>
                            <div className="mt-1 text-sm whitespace-pre-line text-muted-foreground">
                                {installation.technical_notes ||
                                    'No technical notes'}
                            </div>
                        </div>
                        <Form
                            action={`/installations/${installation.id}`}
                            method="delete"
                        >
                            {({ processing }) => (
                                <Button
                                    variant="destructive"
                                    disabled={processing}
                                >
                                    Delete installation
                                </Button>
                            )}
                        </Form>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

function formatEuropeanDateTime(
    value: string | null,
    locale: 'en' | 'es',
): string | null {
    if (!value) {
        return null;
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return new Intl.DateTimeFormat(locale === 'es' ? 'es-ES' : 'en-GB', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        hour12: false,
        timeZone: 'Europe/Madrid',
    }).format(date);
}

function technicalItems(installation: Installation): [string, string | null][] {
    if (installation.service_type === 'solar') {
        return [
            ['System kW', installation.system_power_kw],
            ['Panels', installation.panel_count?.toString() ?? null],
            ['Inverter', installation.inverter_brand],
            ['Battery kWh', installation.battery_capacity_kwh],
        ];
    }

    if (installation.service_type === 'ev_charger') {
        return [
            ['Charger kW', installation.charger_power_kw],
            ['Connector', installation.charger_connector_type],
        ];
    }

    if (installation.service_type === 'hvac') {
        return [['Clima system', installation.hvac_system_type]];
    }

    return [['Electrical scope', installation.electrical_scope]];
}

function InfoBlock({
    title,
    items,
}: {
    title: string;
    items: [string, string | null][];
}) {
    return (
        <div className="rounded-md border p-4">
            <div className="font-medium">{title}</div>
            <dl className="mt-3 grid gap-2 text-sm">
                {items.map(([label, value]) => (
                    <div key={label}>
                        <dt className="text-muted-foreground">{label}</dt>
                        <dd>{value || '—'}</dd>
                    </div>
                ))}
            </dl>
        </div>
    );
}

function Value({ label, value }: { label: string; value: string }) {
    return (
        <div>
            <div className="text-muted-foreground">{label}</div>
            <div className="font-semibold">{value}</div>
        </div>
    );
}

function AssetDialog({
    installationId,
    options,
}: {
    installationId: number;
    options: AssetOptions;
}) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm">
                    <Plus /> Add asset
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Register installed asset</DialogTitle>
                </DialogHeader>
                <Form
                    action={`/installations/${installationId}/assets`}
                    method="post"
                    onSuccess={() => setOpen(false)}
                    className="grid gap-4"
                >
                    <div className="grid gap-2">
                        <Label>Name</Label>
                        <Input name="name" required />
                    </div>
                    <div className="grid gap-2">
                        <Label>Category</Label>
                        <Input
                            name="category"
                            required
                            placeholder="inverter, charger, HVAC…"
                        />
                    </div>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label>Manufacturer</Label>
                            <Input name="manufacturer" />
                        </div>
                        <div className="grid gap-2">
                            <Label>Model</Label>
                            <Input name="model" />
                        </div>
                    </div>
                    {options.serials.length > 0 ? (
                        <div className="grid gap-2">
                            <Label>Inventory serial</Label>
                            <select
                                name="serial_number_id"
                                className="h-9 rounded-md border bg-transparent px-3 text-sm"
                            >
                                <option value="">Manual / no serial</option>
                                {options.serials.map((serial) => (
                                    <option key={serial.id} value={serial.id}>
                                        {serial.product.name} ·{' '}
                                        {serial.serial_number}
                                    </option>
                                ))}
                            </select>
                        </div>
                    ) : (
                        <div className="grid gap-2">
                            <Label>Serial number</Label>
                            <Input name="serial_number" />
                        </div>
                    )}
                    {options.purchases.length > 0 && (
                        <div className="grid gap-2">
                            <Label>Source purchase</Label>
                            <select
                                name="source_purchase_document_id"
                                className="h-9 rounded-md border bg-transparent px-3 text-sm"
                            >
                                <option value="">Not linked</option>
                                {options.purchases.map((purchase) => (
                                    <option
                                        key={purchase.id}
                                        value={purchase.id}
                                    >
                                        {purchase.number}
                                    </option>
                                ))}
                            </select>
                        </div>
                    )}
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label>Commissioned</Label>
                            <Input
                                name="commissioned_at"
                                type="datetime-local"
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label>Warranty ends</Label>
                            <Input name="warranty_ends_on" type="date" />
                        </div>
                    </div>
                    <Button>Register asset</Button>
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function money(value: number) {
    return new Intl.NumberFormat('es-ES', {
        style: 'currency',
        currency: 'EUR',
    }).format(value);
}

function localDateTimeNow() {
    const date = new Date();
    date.setMinutes(date.getMinutes() - date.getTimezoneOffset());

    return date.toISOString().slice(0, 16);
}

function ServiceIcon({ service }: { service: keyof typeof serviceLabels }) {
    const icons: Record<keyof typeof serviceLabels, LucideIcon> = {
        solar: Sun,
        ev_charger: BatteryCharging,
        hvac: Snowflake,
        electrical: PlugZap,
    };
    const Icon = icons[service];

    return <Icon className="size-5 text-muted-foreground" />;
}

function personRoleLabel(role: string) {
    return (
        {
            owner: 'Propietario',
            co_owner: 'Copropietario',
            tenant: 'Inquilino',
            authorized_contact: 'Contacto autorizado',
        }[role] || role
    );
}

InstallationShow.layout = {
    breadcrumbs: [{ title: 'Installation', href: '/dashboard' }],
};

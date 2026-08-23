import { Form, Head, Link, router, useForm } from '@inertiajs/react';
import {
    Archive,
    Bookmark,
    CalendarClock,
    CircleDollarSign,
    Clock3,
    History,
    LayoutGrid,
    List,
    Pencil,
    Plus,
    RotateCcw,
    Search,
    Settings2,
    Target,
    Timer,
    TrendingUp,
    UserPlus,
    X,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';

const stages = [
    { value: 'new', label: 'Nuevo', probability: 10 },
    { value: 'qualified', label: 'Cualificado', probability: 25 },
    { value: 'survey', label: 'Visita técnica', probability: 40 },
    { value: 'proposal', label: 'Propuesta', probability: 60 },
    { value: 'negotiation', label: 'Negociación', probability: 80 },
    { value: 'won', label: 'Ganada', probability: 100 },
    { value: 'lost', label: 'Perdida', probability: 0 },
] as const;

const contactChannelOptions = [
    ['phone', 'Teléfono'],
    ['whatsapp', 'WhatsApp'],
    ['email', 'Email'],
    ['sms', 'SMS'],
];

type Customer = {
    id: number;
    first_name: string;
    last_name: string | null;
    company_name: string | null;
    phone: string | null;
    email: string | null;
    preferred_contact_channel: string | null;
    do_not_contact: boolean;
};
type Installation = {
    id: number;
    person_id: number | null;
    project_reference: string | null;
    site_name: string | null;
};
type UserOption = { id: number; name: string };
type WonOpportunity = {
    id: number;
    person_id: number;
    code: string;
    title: string;
};
type Activity = {
    id: number;
    title: string;
    type: string;
    priority: string;
    due_at: string;
    assigned_user: UserOption | null;
};
type StageHistory = {
    id: number;
    from_stage: string | null;
    to_stage: string;
    changed_at: string;
    changed_by: UserOption | null;
};
type Opportunity = {
    id: number;
    code: string;
    title: string;
    commercial_kind: string;
    parent_opportunity_id: number | null;
    recurrence_interval: string | null;
    renewal_due_on: string | null;
    stage: string;
    probability: number;
    commercial_score: number;
    score_breakdown: Record<
        string,
        { met: boolean; points: number; weight: number }
    >;
    estimated_value: string;
    expected_close_on: string | null;
    source: string | null;
    source_detail: string | null;
    lost_reason: string | null;
    lost_reason_detail: string | null;
    notes: string | null;
    person_id: number;
    installation_id: number | null;
    owner_user_id: number | null;
    customer: Customer | null;
    installation: Installation | null;
    owner: UserOption | null;
    parent_opportunity: WonOpportunity | null;
    quote: { id: number; number: string; status: string } | null;
    next_activity: Activity | null;
    stage_history: StageHistory[];
    available_stages: string[];
    stage_entered_at: string;
    days_in_stage: number;
    is_stale: boolean;
    first_response_due_at: string | null;
    first_responded_at: string | null;
    first_response_minutes: number | null;
    response_sla_status: 'met' | 'breached' | 'pending' | 'not_applicable';
    deleted_at: string | null;
};

type ForecastRow = {
    month: string | null;
    owner_id: number | null;
    owner_name: string;
    count: number;
    amount: number;
    weighted_amount: number;
    average_probability: number;
    opportunities: Array<{
        id: number;
        code: string;
        title: string;
        amount: number;
        probability: number;
        weighted_amount: number;
    }>;
};

type FormData = {
    person_id: string;
    customer_phone: string;
    customer_first_name: string;
    customer_last_name: string;
    customer_company_name: string;
    customer_email: string;
    customer_preferred_contact_channel: string;
    customer_consent_status: string;
    customer_consent_channel: string;
    customer_consent_notes: string;
    installation_id: string;
    owner_user_id: string;
    title: string;
    commercial_kind: string;
    parent_opportunity_id: string;
    recurrence_interval: string;
    renewal_due_on: string;
    stage: string;
    probability: string;
    estimated_value: string;
    expected_close_on: string;
    source: string;
    source_detail: string;
    lost_reason: string;
    lost_reason_detail: string;
    notes: string;
    next_action_type: string;
    next_action_title: string;
    next_action_priority: string;
    next_action_due_at: string;
};

type CrmSettings = {
    stale_after_days: number;
    lead_response_sla_minutes: number;
    lead_response_escalation_minutes: number;
    customer_contactable_weight: number;
    source_identified_weight: number;
    owner_assigned_weight: number;
    expected_close_set_weight: number;
    next_action_scheduled_weight: number;
    estimated_value_set_weight: number;
    advanced_stage_weight: number;
};

type ResponsePerformanceRow = {
    owner_id: number;
    owner_name: string;
    total: number;
    met: number;
    breached: number;
    pending: number;
    met_rate: number | null;
    average_minutes: number | null;
};

const emptyForm: FormData = {
    person_id: '',
    customer_phone: '',
    customer_first_name: '',
    customer_last_name: '',
    customer_company_name: '',
    customer_email: '',
    customer_preferred_contact_channel: 'phone',
    customer_consent_status: 'not_recorded',
    customer_consent_channel: 'phone',
    customer_consent_notes: '',
    installation_id: '',
    owner_user_id: '',
    title: '',
    commercial_kind: 'new_business',
    parent_opportunity_id: '',
    recurrence_interval: '',
    renewal_due_on: '',
    stage: 'new',
    probability: '10',
    estimated_value: '0',
    expected_close_on: '',
    source: '',
    source_detail: '',
    lost_reason: '',
    lost_reason_detail: '',
    notes: '',
    next_action_type: 'call',
    next_action_title: 'Contactar y cualificar al cliente',
    next_action_priority: 'normal',
    next_action_due_at: nextBusinessAction(),
};

export default function OpportunitiesIndex({
    opportunities,
    customers,
    installations,
    users,
    sourceOptions,
    commercialKindOptions,
    recurrenceOptions,
    lostReasonOptions,
    wonOpportunities,
    filters = {},
    metrics,
    forecast = [],
    responsePerformance = [],
    savedFilters = [],
    crmSettings,
    canConfigureCrm = false,
}: {
    opportunities: Opportunity[];
    customers: Customer[];
    installations: Installation[];
    users: UserOption[];
    sourceOptions: Record<string, string>;
    commercialKindOptions: Record<string, string>;
    recurrenceOptions: Record<string, string>;
    lostReasonOptions: Record<string, string>;
    wonOpportunities: WonOpportunity[];
    filters: {
        search?: string;
        stage?: string;
        owner?: string;
        source?: string;
        commercial_kind?: string;
        attention?: string;
        archived?: string;
        period?: string;
        view?: string;
    };
    metrics: {
        pipeline_value: number;
        weighted_forecast: number;
        won_value: number;
        new_leads: number;
        won_count: number;
        lost_count: number;
        conversion_rate: number;
        average_cycle_days: number | null;
        overdue: number;
        without_next_action: number;
        stale: number;
        renewals_due: number;
        response_sla_breached: number;
        response_sla_pending: number;
        response_sla_met_rate: number | null;
        average_first_response_minutes: number | null;
        funnel: Record<string, number>;
        stage_days: Record<string, number | null>;
    };
    forecast?: ForecastRow[];
    responsePerformance?: ResponsePerformanceRow[];
    savedFilters?: Array<{
        id: number;
        name: string;
        filters: Record<string, string>;
        is_default: boolean;
    }>;
    crmSettings: CrmSettings;
    canConfigureCrm?: boolean;
}) {
    const [open, setOpen] = useState(false);
    const [editing, setEditing] = useState<Opportunity | null>(null);
    const [matchedCustomer, setMatchedCustomer] = useState<Customer | null>(
        null,
    );
    const [customerLookup, setCustomerLookup] = useState<
        'idle' | 'checking' | 'found' | 'new' | 'invalid'
    >('idle');
    const form = useForm<FormData>(emptyForm);
    const availableInstallations = useMemo(
        () =>
            installations.filter(
                (item) => String(item.person_id) === form.data.person_id,
            ),
        [form.data.person_id, installations],
    );
    const availableOrigins = useMemo(
        () =>
            wonOpportunities.filter(
                (item) =>
                    String(item.person_id) === form.data.person_id &&
                    item.id !== editing?.id,
            ),
        [editing?.id, form.data.person_id, wonOpportunities],
    );

    useEffect(() => {
        if (!open || editing) {
            return;
        }

        const phone = form.data.customer_phone.trim();
        const digits = phone.replace(/\D/g, '');

        if (digits.length < 9) {
            return;
        }

        const abortController = new AbortController();
        const timer = window.setTimeout(async () => {
            setCustomerLookup('checking');

            try {
                const response = await fetch(
                    `/crm/customers/lookup?phone=${encodeURIComponent(phone)}`,
                    {
                        headers: { Accept: 'application/json' },
                        signal: abortController.signal,
                    },
                );

                if (!response.ok) {
                    setMatchedCustomer(null);
                    setCustomerLookup('invalid');

                    return;
                }

                const result = (await response.json()) as {
                    customer: Customer | null;
                };

                if (result.customer) {
                    setMatchedCustomer(result.customer);
                    setCustomerLookup('found');
                    form.setData('person_id', String(result.customer.id));
                    form.setData('customer_first_name', '');
                    form.setData('customer_last_name', '');
                    form.setData('customer_company_name', '');
                    form.setData('customer_email', '');
                    form.setData(
                        'customer_preferred_contact_channel',
                        result.customer.preferred_contact_channel || 'phone',
                    );
                    form.setData(
                        'customer_consent_channel',
                        result.customer.preferred_contact_channel || 'phone',
                    );
                } else {
                    setMatchedCustomer(null);
                    setCustomerLookup('new');
                    form.setData('person_id', '');
                    form.setData('installation_id', '');
                    form.setData('parent_opportunity_id', '');
                }
            } catch (error) {
                if (
                    error instanceof DOMException &&
                    error.name === 'AbortError'
                ) {
                    return;
                }

                setCustomerLookup('invalid');
            }
        }, 350);

        return () => {
            window.clearTimeout(timer);
            abortController.abort();
        };
        // The lookup must only restart when the entered mobile changes.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [editing, form.data.customer_phone, open]);

    useEffect(() => {
        const params = new URLSearchParams(window.location.search);
        const selected = opportunities.find(
            (item) => String(item.id) === params.get('opportunity'),
        );

        if (selected) {
            openEditor(selected);
        } else if (params.get('create') === '1') {
            openCreator(params.get('customer') || undefined);
        }
        // The query string is only an entry point; subsequent state is local.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    function openCreator(customerId?: string) {
        const customer = customers.find(
            (item) => String(item.id) === customerId,
        );

        setEditing(null);
        setMatchedCustomer(customer || null);
        setCustomerLookup(customer ? 'found' : 'idle');
        form.clearErrors();
        form.setData({
            ...emptyForm,
            person_id: customer ? String(customer.id) : '',
            customer_phone: customer?.phone || '',
            customer_preferred_contact_channel:
                customer?.preferred_contact_channel || 'phone',
            customer_consent_channel:
                customer?.preferred_contact_channel || 'phone',
            next_action_due_at: nextBusinessAction(),
        });
        setOpen(true);
    }

    function openEditor(item: Opportunity) {
        setEditing(item);
        setMatchedCustomer(null);
        setCustomerLookup('idle');
        form.clearErrors();
        form.setData({
            person_id: String(item.person_id),
            customer_phone: '',
            customer_first_name: '',
            customer_last_name: '',
            customer_company_name: '',
            customer_email: '',
            customer_preferred_contact_channel: 'phone',
            customer_consent_status: 'not_recorded',
            customer_consent_channel: 'phone',
            customer_consent_notes: '',
            installation_id: item.installation_id
                ? String(item.installation_id)
                : '',
            owner_user_id: item.owner_user_id ? String(item.owner_user_id) : '',
            title: item.title,
            commercial_kind: item.commercial_kind,
            parent_opportunity_id: item.parent_opportunity_id
                ? String(item.parent_opportunity_id)
                : '',
            recurrence_interval: item.recurrence_interval || '',
            renewal_due_on: item.renewal_due_on || '',
            stage: item.stage,
            probability: String(item.probability),
            estimated_value: item.estimated_value,
            expected_close_on: item.expected_close_on || '',
            source: item.source || '',
            source_detail: item.source_detail || '',
            lost_reason: item.lost_reason || '',
            lost_reason_detail: item.lost_reason_detail || '',
            notes: item.notes || '',
            next_action_type: 'call',
            next_action_title: 'Contactar y cualificar al cliente',
            next_action_priority: 'normal',
            next_action_due_at: nextBusinessAction(),
        });
        setOpen(true);
    }

    function submit(event: React.FormEvent) {
        event.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        };

        if (editing) {
            form.patch(`/opportunities/${editing.id}`, options);
        } else {
            form.post('/opportunities', options);
        }
    }

    function changeStage(item: Opportunity, stage: string) {
        const probability =
            stages.find((candidate) => candidate.value === stage)
                ?.probability ?? item.probability;

        if (stage === 'lost') {
            openEditor({ ...item, stage, probability });

            return;
        }

        router.patch(
            `/opportunities/${item.id}`,
            {
                person_id: item.person_id,
                installation_id: item.installation_id,
                owner_user_id: item.owner_user_id,
                title: item.title,
                commercial_kind: item.commercial_kind,
                parent_opportunity_id: item.parent_opportunity_id,
                recurrence_interval: item.recurrence_interval,
                renewal_due_on: item.renewal_due_on,
                stage,
                probability,
                estimated_value: item.estimated_value,
                expected_close_on: item.expected_close_on,
                source: item.source,
                source_detail: item.source_detail,
                lost_reason: null,
                lost_reason_detail: null,
                notes: item.notes,
            },
            { preserveScroll: true },
        );
    }

    return (
        <>
            <Head title="Oportunidades" />
            <div className="flex min-w-0 flex-1 flex-col gap-6 p-4 md:p-6">
                <header className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold">Oportunidades</h1>
                        <p className="text-sm text-muted-foreground">
                            Embudo comercial y previsión ponderada de ventas.
                        </p>
                    </div>
                    <div className="flex gap-2">
                        {canConfigureCrm && (
                            <CrmSettingsDialog settings={crmSettings} />
                        )}
                        <Button variant="outline" asChild>
                            <Link
                                href={
                                    filters.archived
                                        ? '/opportunities'
                                        : '/opportunities?archived=1'
                                }
                            >
                                {filters.archived ? <RotateCcw /> : <Archive />}
                                {filters.archived
                                    ? 'Oportunidades activas'
                                    : 'Archivadas'}
                            </Link>
                        </Button>
                        {!filters.archived && (
                            <Button onClick={() => openCreator()}>
                                <Plus /> Nueva oportunidad
                            </Button>
                        )}
                    </div>
                </header>

                <section className="grid gap-px overflow-hidden border bg-border sm:grid-cols-2 xl:grid-cols-4">
                    <Metric
                        icon={CircleDollarSign}
                        label="Pipeline abierto"
                        value={money(metrics.pipeline_value)}
                    />
                    <Metric
                        icon={TrendingUp}
                        label="Previsión ponderada"
                        value={money(metrics.weighted_forecast)}
                    />
                    <Metric
                        icon={Target}
                        label={`Ganado · ${periodLabel(filters.period)}`}
                        value={money(metrics.won_value)}
                        detail={`${metrics.won_count} oportunidades`}
                    />
                    <Metric
                        icon={UserPlus}
                        label={`Nuevos leads · ${periodLabel(filters.period)}`}
                        value={String(metrics.new_leads)}
                    />
                    <Metric
                        icon={Target}
                        label="Conversión"
                        value={`${metrics.conversion_rate}%`}
                        detail={`${metrics.won_count} ganadas · ${metrics.lost_count} perdidas`}
                    />
                    <Metric
                        icon={Timer}
                        label="Ciclo medio"
                        value={
                            metrics.average_cycle_days === null
                                ? '—'
                                : `${metrics.average_cycle_days} días`
                        }
                    />
                    <Metric
                        icon={CalendarClock}
                        label="Cierres vencidos"
                        value={String(metrics.overdue)}
                    />
                    <Metric
                        icon={Clock3}
                        label="Sin próxima acción"
                        value={String(metrics.without_next_action)}
                        detail={`${metrics.stale} estancadas`}
                    />
                    <Metric
                        icon={CalendarClock}
                        label="Renovaciones próximas"
                        value={String(metrics.renewals_due)}
                        detail="Próximos 30 días"
                    />
                    <Metric
                        icon={Clock3}
                        label="Leads fuera de SLA"
                        value={String(metrics.response_sla_breached)}
                        detail={`${metrics.response_sla_pending} pendientes dentro de plazo`}
                    />
                    <Metric
                        icon={Timer}
                        label="Cumplimiento primera respuesta"
                        value={
                            metrics.response_sla_met_rate === null
                                ? '—'
                                : `${metrics.response_sla_met_rate}%`
                        }
                        detail={
                            metrics.average_first_response_minutes === null
                                ? 'Sin respuestas registradas'
                                : `Media ${formatMinutes(metrics.average_first_response_minutes)}`
                        }
                    />
                </section>

                <FunnelSummary funnel={metrics.funnel} />

                {responsePerformance.length > 0 && (
                    <ResponsePerformanceSummary rows={responsePerformance} />
                )}

                {forecast.length > 0 && <ForecastSummary rows={forecast} />}

                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div className="flex flex-wrap items-center gap-2">
                        {savedFilters.length > 0 && (
                            <span className="inline-flex items-center gap-1 text-xs font-medium text-muted-foreground">
                                <Bookmark className="size-3.5" /> Vistas
                                guardadas
                            </span>
                        )}
                        {savedFilters.map((filter) => (
                            <div key={filter.id} className="inline-flex">
                                <Button size="sm" variant="outline" asChild>
                                    <Link
                                        href={opportunityFilterUrl(
                                            filter.filters,
                                        )}
                                    >
                                        {filter.name}
                                        {filter.is_default
                                            ? ' · predeterminada'
                                            : ''}
                                    </Link>
                                </Button>
                                <Form
                                    action={`/crm/filters/${filter.id}`}
                                    method="delete"
                                >
                                    <Button
                                        size="sm"
                                        variant="ghost"
                                        aria-label={`Eliminar filtro ${filter.name}`}
                                    >
                                        <X />
                                    </Button>
                                </Form>
                            </div>
                        ))}
                    </div>
                    <div className="flex gap-1 rounded-md border p-1">
                        <Button
                            size="sm"
                            variant={
                                filters.view === 'table' ? 'ghost' : 'secondary'
                            }
                            asChild
                        >
                            <Link
                                href={opportunityFilterUrl({
                                    ...filters,
                                    view: 'kanban',
                                })}
                            >
                                <LayoutGrid /> Kanban
                            </Link>
                        </Button>
                        <Button
                            size="sm"
                            variant={
                                filters.view === 'table' ? 'secondary' : 'ghost'
                            }
                            asChild
                        >
                            <Link
                                href={opportunityFilterUrl({
                                    ...filters,
                                    view: 'table',
                                })}
                            >
                                <List /> Tabla
                            </Link>
                        </Button>
                    </div>
                </div>

                <Form
                    action="/opportunities"
                    method="get"
                    className="grid gap-3 rounded-md border p-3 md:grid-cols-2 xl:grid-cols-4 2xl:grid-cols-[minmax(14rem,1fr)_10rem_11rem_11rem_12rem_12rem_11rem_auto]"
                >
                    {filters.archived && (
                        <input type="hidden" name="archived" value="1" />
                    )}
                    <div className="relative">
                        <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            name="search"
                            defaultValue={filters.search}
                            placeholder="Buscar oportunidad o cliente"
                            className="pl-9"
                        />
                    </div>
                    <NativeSelect
                        name="stage"
                        defaultValue={filters.stage}
                        options={[
                            ['', 'Todas las etapas'],
                            ...stages.map((stage) => [
                                stage.value,
                                stage.label,
                            ]),
                        ]}
                    />
                    <NativeSelect
                        name="owner"
                        defaultValue={filters.owner}
                        options={[
                            ['', 'Todos los responsables'],
                            ['me', 'Mis oportunidades'],
                            ...users.map((user) => [
                                String(user.id),
                                user.name,
                            ]),
                        ]}
                    />
                    <NativeSelect
                        name="source"
                        defaultValue={filters.source}
                        options={[
                            ['', 'Todos los orígenes'],
                            ...Object.entries(sourceOptions),
                        ]}
                    />
                    <NativeSelect
                        name="commercial_kind"
                        defaultValue={filters.commercial_kind}
                        options={[
                            ['', 'Todos los tipos'],
                            ...Object.entries(commercialKindOptions),
                        ]}
                    />
                    <NativeSelect
                        name="attention"
                        defaultValue={filters.attention}
                        options={[
                            ['', 'Todas las situaciones'],
                            ['overdue', 'Cierre vencido'],
                            ['no_action', 'Sin próxima acción'],
                            ['stale', 'Estancada en etapa'],
                            ['response_sla', 'Primera respuesta fuera de SLA'],
                            ['renewal_due', 'Renovación próxima'],
                        ]}
                    />
                    <NativeSelect
                        name="period"
                        defaultValue={filters.period || '90'}
                        options={[
                            ['30', 'Últimos 30 días'],
                            ['90', 'Últimos 90 días'],
                            ['365', 'Último año'],
                            ['all', 'Todo el histórico'],
                        ]}
                    />
                    <div className="flex gap-2">
                        <Button variant="outline">
                            <Search /> Filtrar
                        </Button>
                        {Object.values(filters).some(Boolean) && (
                            <Button type="button" variant="ghost" asChild>
                                <Link href="/opportunities">
                                    <X /> Limpiar
                                </Link>
                            </Button>
                        )}
                    </div>
                </Form>

                {Object.values(filters).some(Boolean) && (
                    <Form
                        action="/crm/filters"
                        method="post"
                        className="flex flex-wrap items-end gap-2"
                    >
                        <input
                            type="hidden"
                            name="scope"
                            value="opportunities"
                        />
                        {Object.entries(filters).map(([key, value]) =>
                            value && key !== 'archived' ? (
                                <input
                                    key={key}
                                    type="hidden"
                                    name={`filters[${key}]`}
                                    value={value}
                                />
                            ) : null,
                        )}
                        <div className="grid gap-1">
                            <Label
                                htmlFor="opportunity_filter_name"
                                className="text-xs text-muted-foreground"
                            >
                                Guardar vista actual
                            </Label>
                            <Input
                                id="opportunity_filter_name"
                                name="name"
                                required
                                placeholder="Nombre de la vista"
                            />
                        </div>
                        <label className="flex h-9 items-center gap-2 rounded-md border px-3 text-xs">
                            <input
                                type="checkbox"
                                name="is_default"
                                value="1"
                            />{' '}
                            Predeterminada
                        </label>
                        <Button size="sm" variant="outline">
                            <Bookmark /> Guardar
                        </Button>
                    </Form>
                )}

                {filters.view === 'table' ? (
                    <OpportunityTable
                        opportunities={opportunities}
                        sourceOptions={sourceOptions}
                        commercialKindOptions={commercialKindOptions}
                        onEdit={openEditor}
                    />
                ) : (
                    <section className="snap-x snap-proximity [scrollbar-gutter:stable] overflow-x-auto pb-3">
                        <div className="grid min-w-[98rem] grid-cols-7 gap-3">
                            {stages.map((stage) => {
                                const items = opportunities.filter(
                                    (item) => item.stage === stage.value,
                                );

                                return (
                                    <div
                                        key={stage.value}
                                        className="min-w-0 border-t-2 pt-3"
                                    >
                                        <div className="mb-3 flex items-center justify-between px-1">
                                            <div className="text-sm font-semibold">
                                                {stage.label}
                                            </div>
                                            <Badge variant="secondary">
                                                {items.length}
                                            </Badge>
                                        </div>
                                        <div className="grid content-start gap-2">
                                            {items.map((item) => (
                                                <article
                                                    key={item.id}
                                                    className="rounded-md border bg-card p-3 shadow-xs"
                                                >
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            openEditor(item)
                                                        }
                                                        className="w-full text-left"
                                                    >
                                                        <span className="text-xs text-muted-foreground">
                                                            {item.code}
                                                        </span>
                                                        <span className="mt-1 block text-sm leading-snug font-medium">
                                                            {item.title}
                                                        </span>
                                                        {item.customer && (
                                                            <span className="mt-1 block truncate text-xs text-muted-foreground">
                                                                {customerName(
                                                                    item.customer,
                                                                )}
                                                            </span>
                                                        )}
                                                        {item.source && (
                                                            <span className="mt-2 block">
                                                                <Badge
                                                                    variant="outline"
                                                                    title={
                                                                        item.source_detail ||
                                                                        undefined
                                                                    }
                                                                >
                                                                    {sourceOptions[
                                                                        item
                                                                            .source
                                                                    ] ||
                                                                        item.source}
                                                                </Badge>
                                                            </span>
                                                        )}
                                                        <span className="mt-2 block">
                                                            <Badge variant="secondary">
                                                                {commercialKindOptions[
                                                                    item
                                                                        .commercial_kind
                                                                ] ||
                                                                    item.commercial_kind}
                                                            </Badge>
                                                        </span>
                                                        {item.renewal_due_on && (
                                                            <span className="mt-1 block text-xs text-muted-foreground">
                                                                Renovación:{' '}
                                                                {date(
                                                                    item.renewal_due_on,
                                                                )}
                                                            </span>
                                                        )}
                                                        <span className="mt-2 block text-xs text-muted-foreground">
                                                            Scoring{' '}
                                                            {
                                                                item.commercial_score
                                                            }
                                                            /100
                                                        </span>
                                                        <span
                                                            className={`mt-1 block text-xs ${item.is_stale ? 'font-medium text-amber-700 dark:text-amber-300' : 'text-muted-foreground'}`}
                                                        >
                                                            {item.days_in_stage}{' '}
                                                            días en etapa
                                                            {item.is_stale
                                                                ? ' · estancada'
                                                                : ''}
                                                        </span>
                                                        <ResponseSlaBadge
                                                            opportunity={item}
                                                        />
                                                    </button>
                                                    <div className="mt-3 flex items-end justify-between gap-2 border-t pt-2">
                                                        <div>
                                                            <div className="text-sm font-semibold">
                                                                {money(
                                                                    Number(
                                                                        item.estimated_value,
                                                                    ),
                                                                )}
                                                            </div>
                                                            <div className="text-xs text-muted-foreground">
                                                                {
                                                                    item.probability
                                                                }
                                                                % ·{' '}
                                                                {date(
                                                                    item.expected_close_on,
                                                                )}
                                                            </div>
                                                        </div>
                                                        <Button
                                                            type="button"
                                                            variant="ghost"
                                                            size="icon"
                                                            className="size-8"
                                                            onClick={() =>
                                                                openEditor(item)
                                                            }
                                                            aria-label={`Edit ${item.title}`}
                                                        >
                                                            <Pencil className="size-3.5" />
                                                        </Button>
                                                    </div>
                                                    <div
                                                        className={`mt-2 rounded-md border px-2 py-1.5 text-xs ${
                                                            item.next_activity &&
                                                            isOverdue(
                                                                item
                                                                    .next_activity
                                                                    .due_at,
                                                            )
                                                                ? 'border-amber-300 bg-amber-50 text-amber-900 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200'
                                                                : 'text-muted-foreground'
                                                        }`}
                                                    >
                                                        {item.next_activity ? (
                                                            <>
                                                                <div className="flex items-center gap-1 font-medium">
                                                                    <Clock3 className="size-3" />
                                                                    Próxima
                                                                    acción
                                                                </div>
                                                                <div className="mt-1 truncate">
                                                                    {
                                                                        item
                                                                            .next_activity
                                                                            .title
                                                                    }{' '}
                                                                    ·{' '}
                                                                    {dateTime(
                                                                        item
                                                                            .next_activity
                                                                            .due_at,
                                                                    )}
                                                                </div>
                                                            </>
                                                        ) : (
                                                            <span>
                                                                Sin próxima
                                                                acción
                                                            </span>
                                                        )}
                                                    </div>
                                                    {!item.deleted_at &&
                                                        ![
                                                            'won',
                                                            'lost',
                                                        ].includes(
                                                            item.stage,
                                                        ) && (
                                                            <OpportunityActivityDialog
                                                                opportunity={
                                                                    item
                                                                }
                                                                users={users}
                                                            />
                                                        )}
                                                    {item.deleted_at ? (
                                                        <Badge
                                                            variant="secondary"
                                                            className="mt-2"
                                                        >
                                                            Archivada
                                                        </Badge>
                                                    ) : (
                                                        <select
                                                            value={item.stage}
                                                            disabled={
                                                                item
                                                                    .available_stages
                                                                    .length <= 1
                                                            }
                                                            onChange={(event) =>
                                                                changeStage(
                                                                    item,
                                                                    event.target
                                                                        .value,
                                                                )
                                                            }
                                                            aria-label={`Estado de ${item.title}`}
                                                            className="mt-2 h-9 w-full rounded-md border border-input bg-background px-3 text-sm font-medium shadow-xs"
                                                        >
                                                            {stages
                                                                .filter(
                                                                    (option) =>
                                                                        item.available_stages.includes(
                                                                            option.value,
                                                                        ),
                                                                )
                                                                .map(
                                                                    (
                                                                        option,
                                                                    ) => (
                                                                        <option
                                                                            key={
                                                                                option.value
                                                                            }
                                                                            value={
                                                                                option.value
                                                                            }
                                                                        >
                                                                            {
                                                                                option.label
                                                                            }
                                                                        </option>
                                                                    ),
                                                                )}
                                                        </select>
                                                    )}
                                                </article>
                                            ))}
                                            {items.length === 0 && (
                                                <div className="rounded-md border border-dashed p-4 text-center text-xs text-muted-foreground">
                                                    No opportunities
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    </section>
                )}
            </div>

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                    <form onSubmit={submit} className="grid gap-5">
                        <DialogHeader>
                            <DialogTitle>
                                {editing
                                    ? 'Edit opportunity'
                                    : 'New opportunity'}
                            </DialogTitle>
                            <DialogDescription>
                                {editing?.code ||
                                    'Add a commercial lead to the pipeline.'}
                            </DialogDescription>
                        </DialogHeader>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field
                                label="Title"
                                error={form.errors.title}
                                className="sm:col-span-2"
                            >
                                <Input
                                    value={form.data.title}
                                    onChange={(event) =>
                                        form.setData(
                                            'title',
                                            event.target.value,
                                        )
                                    }
                                />
                            </Field>
                            {editing ? (
                                <SelectField
                                    label="Cliente"
                                    value={form.data.person_id}
                                    error={form.errors.person_id}
                                    onChange={(value) => {
                                        form.setData('person_id', value);
                                        form.setData('installation_id', '');
                                        form.setData(
                                            'parent_opportunity_id',
                                            '',
                                        );
                                    }}
                                    options={customers.map((item) => [
                                        String(item.id),
                                        customerName(item),
                                    ])}
                                />
                            ) : (
                                <div className="grid gap-3 rounded-md border bg-muted/20 p-4 sm:col-span-2">
                                    <div>
                                        <div className="text-sm font-medium">
                                            Cliente por teléfono móvil
                                        </div>
                                        <p className="text-xs text-muted-foreground">
                                            Buscaremos primero un cliente
                                            existente. Si no coincide ninguno,
                                            se creará junto con la oportunidad.
                                        </p>
                                    </div>
                                    <Field
                                        label="Teléfono móvil"
                                        error={form.errors.customer_phone}
                                    >
                                        <Input
                                            type="tel"
                                            autoComplete="tel"
                                            placeholder="600 123 123"
                                            value={form.data.customer_phone}
                                            onChange={(event) => {
                                                const phone =
                                                    event.target.value;
                                                form.setData(
                                                    'customer_phone',
                                                    phone,
                                                );
                                                form.clearErrors(
                                                    'customer_phone',
                                                    'customer_first_name',
                                                );

                                                if (
                                                    phone.replace(/\D/g, '')
                                                        .length < 9
                                                ) {
                                                    setMatchedCustomer(null);
                                                    setCustomerLookup('idle');
                                                    form.setData(
                                                        'person_id',
                                                        '',
                                                    );
                                                    form.setData(
                                                        'installation_id',
                                                        '',
                                                    );
                                                    form.setData(
                                                        'parent_opportunity_id',
                                                        '',
                                                    );
                                                }
                                            }}
                                        />
                                    </Field>
                                    {customerLookup === 'checking' && (
                                        <p className="text-xs text-muted-foreground">
                                            Buscando coincidencias…
                                        </p>
                                    )}
                                    {customerLookup === 'invalid' && (
                                        <p className="text-xs text-destructive">
                                            Introduce un teléfono móvil válido.
                                        </p>
                                    )}
                                    {customerLookup === 'found' &&
                                        matchedCustomer && (
                                            <div className="rounded-md border border-emerald-300 bg-emerald-50 p-3 text-sm text-emerald-950 dark:border-emerald-900 dark:bg-emerald-950/30 dark:text-emerald-100">
                                                <div className="font-medium">
                                                    Cliente encontrado:{' '}
                                                    {customerName(
                                                        matchedCustomer,
                                                    )}
                                                </div>
                                                <div className="mt-1 text-xs opacity-80">
                                                    {matchedCustomer.phone}
                                                    {matchedCustomer.email
                                                        ? ` · ${matchedCustomer.email}`
                                                        : ''}
                                                </div>
                                            </div>
                                        )}
                                    {customerLookup === 'new' && (
                                        <div className="grid gap-3 border-t pt-3 sm:grid-cols-2">
                                            <div className="sm:col-span-2">
                                                <p className="text-sm font-medium">
                                                    Nuevo cliente
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    No hay coincidencias. Indica
                                                    un nombre o una empresa.
                                                </p>
                                            </div>
                                            <Field
                                                label="Nombre"
                                                error={
                                                    form.errors
                                                        .customer_first_name
                                                }
                                            >
                                                <Input
                                                    autoComplete="given-name"
                                                    value={
                                                        form.data
                                                            .customer_first_name
                                                    }
                                                    onChange={(event) =>
                                                        form.setData(
                                                            'customer_first_name',
                                                            event.target.value,
                                                        )
                                                    }
                                                />
                                            </Field>
                                            <Field label="Apellidos">
                                                <Input
                                                    autoComplete="family-name"
                                                    value={
                                                        form.data
                                                            .customer_last_name
                                                    }
                                                    onChange={(event) =>
                                                        form.setData(
                                                            'customer_last_name',
                                                            event.target.value,
                                                        )
                                                    }
                                                />
                                            </Field>
                                            <Field label="Empresa">
                                                <Input
                                                    autoComplete="organization"
                                                    value={
                                                        form.data
                                                            .customer_company_name
                                                    }
                                                    onChange={(event) =>
                                                        form.setData(
                                                            'customer_company_name',
                                                            event.target.value,
                                                        )
                                                    }
                                                />
                                            </Field>
                                            <Field
                                                label="Email"
                                                error={
                                                    form.errors.customer_email
                                                }
                                            >
                                                <Input
                                                    type="email"
                                                    autoComplete="email"
                                                    value={
                                                        form.data.customer_email
                                                    }
                                                    onChange={(event) =>
                                                        form.setData(
                                                            'customer_email',
                                                            event.target.value,
                                                        )
                                                    }
                                                />
                                            </Field>
                                            <SelectField
                                                label="Canal de contacto preferido"
                                                value={
                                                    form.data
                                                        .customer_preferred_contact_channel
                                                }
                                                error={
                                                    form.errors
                                                        .customer_preferred_contact_channel
                                                }
                                                onChange={(value) => {
                                                    form.setData(
                                                        'customer_preferred_contact_channel',
                                                        value,
                                                    );
                                                    form.setData(
                                                        'customer_consent_channel',
                                                        value,
                                                    );
                                                }}
                                                options={contactChannelOptions}
                                            />
                                        </div>
                                    )}
                                    {['found', 'new'].includes(
                                        customerLookup,
                                    ) && (
                                        <div className="grid gap-3 border-t pt-3 sm:grid-cols-2">
                                            <div className="sm:col-span-2">
                                                <p className="text-sm font-medium">
                                                    Comunicaciones comerciales
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    Registra únicamente lo que
                                                    el cliente haya manifestado.
                                                    La aceptación de privacidad
                                                    no equivale a consentimiento
                                                    comercial.
                                                </p>
                                            </div>
                                            {matchedCustomer?.do_not_contact && (
                                                <div className="rounded-md border border-amber-300 bg-amber-50 p-3 text-xs text-amber-950 sm:col-span-2 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-100">
                                                    Este cliente figura como no
                                                    contactable. Solo cambia el
                                                    estado si ha dado una nueva
                                                    autorización expresa.
                                                </div>
                                            )}
                                            <SelectField
                                                label="Estado"
                                                value={
                                                    form.data
                                                        .customer_consent_status
                                                }
                                                error={
                                                    form.errors
                                                        .customer_consent_status
                                                }
                                                onChange={(value) =>
                                                    form.setData(
                                                        'customer_consent_status',
                                                        value,
                                                    )
                                                }
                                                options={[
                                                    [
                                                        'not_recorded',
                                                        'No registrar ahora',
                                                    ],
                                                    [
                                                        'granted',
                                                        'Autoriza comunicaciones',
                                                    ],
                                                    ['denied', 'No autoriza'],
                                                ]}
                                            />
                                            {form.data
                                                .customer_consent_status !==
                                                'not_recorded' && (
                                                <>
                                                    <SelectField
                                                        label="Canal registrado"
                                                        value={
                                                            form.data
                                                                .customer_consent_channel
                                                        }
                                                        error={
                                                            form.errors
                                                                .customer_consent_channel
                                                        }
                                                        onChange={(value) =>
                                                            form.setData(
                                                                'customer_consent_channel',
                                                                value,
                                                            )
                                                        }
                                                        options={
                                                            contactChannelOptions
                                                        }
                                                    />
                                                    <Field
                                                        label="Evidencia o notas"
                                                        error={
                                                            form.errors
                                                                .customer_consent_notes
                                                        }
                                                        className="sm:col-span-2"
                                                    >
                                                        <Textarea
                                                            placeholder="Ej.: autorización verbal durante llamada"
                                                            value={
                                                                form.data
                                                                    .customer_consent_notes
                                                            }
                                                            onChange={(event) =>
                                                                form.setData(
                                                                    'customer_consent_notes',
                                                                    event.target
                                                                        .value,
                                                                )
                                                            }
                                                        />
                                                    </Field>
                                                </>
                                            )}
                                        </div>
                                    )}
                                </div>
                            )}
                            <SelectField
                                label="Installation"
                                value={form.data.installation_id || 'none'}
                                error={form.errors.installation_id}
                                onChange={(value) =>
                                    form.setData(
                                        'installation_id',
                                        value === 'none' ? '' : value,
                                    )
                                }
                                options={[
                                    ['none', 'No installation'],
                                    ...availableInstallations.map((item) => [
                                        String(item.id),
                                        installationName(item),
                                    ]),
                                ]}
                            />
                            <SelectField
                                label="Tipo comercial"
                                value={form.data.commercial_kind}
                                error={form.errors.commercial_kind}
                                onChange={(value) => {
                                    form.setData('commercial_kind', value);

                                    if (value === 'new_business') {
                                        form.setData(
                                            'parent_opportunity_id',
                                            '',
                                        );
                                    }

                                    if (value !== 'recurring') {
                                        form.setData('recurrence_interval', '');
                                    }

                                    if (
                                        !['renewal', 'recurring'].includes(
                                            value,
                                        )
                                    ) {
                                        form.setData('renewal_due_on', '');
                                    }
                                }}
                                options={Object.entries(commercialKindOptions)}
                            />
                            {['renewal', 'upsell', 'recurring'].includes(
                                form.data.commercial_kind,
                            ) && (
                                <SelectField
                                    label="Oportunidad de origen"
                                    value={
                                        form.data.parent_opportunity_id ||
                                        'none'
                                    }
                                    error={form.errors.parent_opportunity_id}
                                    onChange={(value) =>
                                        form.setData(
                                            'parent_opportunity_id',
                                            value === 'none' ? '' : value,
                                        )
                                    }
                                    options={[
                                        ['none', 'Sin vincular'],
                                        ...availableOrigins.map((item) => [
                                            String(item.id),
                                            `${item.code} · ${item.title}`,
                                        ]),
                                    ]}
                                />
                            )}
                            {form.data.commercial_kind === 'recurring' && (
                                <SelectField
                                    label="Periodicidad"
                                    value={
                                        form.data.recurrence_interval || 'none'
                                    }
                                    error={form.errors.recurrence_interval}
                                    onChange={(value) =>
                                        form.setData(
                                            'recurrence_interval',
                                            value === 'none' ? '' : value,
                                        )
                                    }
                                    options={[
                                        ['none', 'Seleccionar'],
                                        ...Object.entries(recurrenceOptions),
                                    ]}
                                />
                            )}
                            {['renewal', 'recurring'].includes(
                                form.data.commercial_kind,
                            ) && (
                                <Field
                                    label="Próxima renovación"
                                    error={form.errors.renewal_due_on}
                                >
                                    <Input
                                        type="date"
                                        value={form.data.renewal_due_on}
                                        onChange={(event) =>
                                            form.setData(
                                                'renewal_due_on',
                                                event.target.value,
                                            )
                                        }
                                    />
                                </Field>
                            )}
                            <SelectField
                                label="Stage"
                                value={form.data.stage}
                                error={form.errors.stage}
                                onChange={(value) => {
                                    form.setData('stage', value);
                                    form.setData(
                                        'probability',
                                        String(
                                            stages.find(
                                                (item) => item.value === value,
                                            )?.probability ?? 0,
                                        ),
                                    );
                                }}
                                options={stages
                                    .filter(
                                        (item) =>
                                            !editing ||
                                            editing.available_stages.includes(
                                                item.value,
                                            ),
                                    )
                                    .map((item) => [item.value, item.label])}
                            />
                            <SelectField
                                label="Owner"
                                value={form.data.owner_user_id || 'none'}
                                error={form.errors.owner_user_id}
                                onChange={(value) =>
                                    form.setData(
                                        'owner_user_id',
                                        value === 'none' ? '' : value,
                                    )
                                }
                                options={[
                                    ['none', 'Unassigned'],
                                    ...users.map((item) => [
                                        String(item.id),
                                        item.name,
                                    ]),
                                ]}
                            />
                            <Field
                                label="Estimated value"
                                error={form.errors.estimated_value}
                            >
                                <Input
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    value={form.data.estimated_value}
                                    onChange={(event) =>
                                        form.setData(
                                            'estimated_value',
                                            event.target.value,
                                        )
                                    }
                                />
                            </Field>
                            <Field
                                label="Probability (%)"
                                error={form.errors.probability}
                            >
                                <Input
                                    type="number"
                                    min="0"
                                    max="100"
                                    value={form.data.probability}
                                    onChange={(event) =>
                                        form.setData(
                                            'probability',
                                            event.target.value,
                                        )
                                    }
                                />
                            </Field>
                            <Field
                                label="Expected close"
                                error={form.errors.expected_close_on}
                            >
                                <Input
                                    type="date"
                                    value={form.data.expected_close_on}
                                    onChange={(event) =>
                                        form.setData(
                                            'expected_close_on',
                                            event.target.value,
                                        )
                                    }
                                />
                            </Field>
                            <SelectField
                                label="Origen"
                                value={form.data.source || 'none'}
                                error={form.errors.source}
                                onChange={(value) =>
                                    form.setData(
                                        'source',
                                        value === 'none' ? '' : value,
                                    )
                                }
                                options={[
                                    ['none', 'Sin especificar'],
                                    ...Object.entries(sourceOptions),
                                ]}
                            />
                            {form.data.source === 'other' && (
                                <Field
                                    label="Detalle del origen"
                                    error={form.errors.source_detail}
                                >
                                    <Input
                                        value={form.data.source_detail}
                                        onChange={(event) =>
                                            form.setData(
                                                'source_detail',
                                                event.target.value,
                                            )
                                        }
                                    />
                                </Field>
                            )}
                            {!editing &&
                                !['won', 'lost'].includes(form.data.stage) && (
                                    <div className="grid gap-3 rounded-md border bg-muted/20 p-4 sm:col-span-2 sm:grid-cols-2">
                                        <div className="sm:col-span-2">
                                            <p className="text-sm font-medium">
                                                Próxima acción obligatoria
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                La oportunidad se crea ya con
                                                responsable, acción y fecha de
                                                seguimiento.
                                            </p>
                                        </div>
                                        <SelectField
                                            label="Tipo de acción"
                                            value={form.data.next_action_type}
                                            error={form.errors.next_action_type}
                                            onChange={(value) =>
                                                form.setData(
                                                    'next_action_type',
                                                    value,
                                                )
                                            }
                                            options={[
                                                ['call', 'Llamada'],
                                                ['task', 'Tarea'],
                                                ['meeting', 'Reunión'],
                                                ['visit', 'Visita'],
                                                ['email', 'Email'],
                                                ['reminder', 'Recordatorio'],
                                            ]}
                                        />
                                        <SelectField
                                            label="Prioridad"
                                            value={
                                                form.data.next_action_priority
                                            }
                                            error={
                                                form.errors.next_action_priority
                                            }
                                            onChange={(value) =>
                                                form.setData(
                                                    'next_action_priority',
                                                    value,
                                                )
                                            }
                                            options={[
                                                ['low', 'Baja'],
                                                ['normal', 'Normal'],
                                                ['high', 'Alta'],
                                            ]}
                                        />
                                        <Field
                                            label="Acción"
                                            error={
                                                form.errors.next_action_title
                                            }
                                        >
                                            <Input
                                                value={
                                                    form.data.next_action_title
                                                }
                                                onChange={(event) =>
                                                    form.setData(
                                                        'next_action_title',
                                                        event.target.value,
                                                    )
                                                }
                                            />
                                        </Field>
                                        <Field
                                            label="Fecha y hora"
                                            error={
                                                form.errors.next_action_due_at
                                            }
                                        >
                                            <Input
                                                type="datetime-local"
                                                value={
                                                    form.data.next_action_due_at
                                                }
                                                onChange={(event) =>
                                                    form.setData(
                                                        'next_action_due_at',
                                                        event.target.value,
                                                    )
                                                }
                                            />
                                        </Field>
                                    </div>
                                )}
                            {form.data.stage === 'lost' && (
                                <SelectField
                                    label="Motivo de pérdida"
                                    value={form.data.lost_reason}
                                    error={form.errors.lost_reason}
                                    onChange={(value) =>
                                        form.setData('lost_reason', value)
                                    }
                                    options={Object.entries(lostReasonOptions)}
                                />
                            )}
                            {form.data.stage === 'lost' &&
                                form.data.lost_reason === 'other' && (
                                    <Field
                                        label="Detalle del motivo"
                                        error={form.errors.lost_reason_detail}
                                    >
                                        <Textarea
                                            value={form.data.lost_reason_detail}
                                            onChange={(event) =>
                                                form.setData(
                                                    'lost_reason_detail',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                    </Field>
                                )}
                            <Field
                                label="Notes"
                                error={form.errors.notes}
                                className="sm:col-span-2"
                            >
                                <Textarea
                                    value={form.data.notes}
                                    onChange={(event) =>
                                        form.setData(
                                            'notes',
                                            event.target.value,
                                        )
                                    }
                                />
                            </Field>
                            {editing && (
                                <div className="sm:col-span-2">
                                    <div className="mb-2 flex items-center justify-between gap-3 text-sm font-medium">
                                        <span>Explicación del scoring</span>
                                        <Badge variant="outline">
                                            {editing.commercial_score}/100
                                        </Badge>
                                    </div>
                                    <div className="grid gap-2 rounded-md border p-3 sm:grid-cols-2">
                                        {Object.entries(
                                            editing.score_breakdown,
                                        ).map(([criterion, result]) => (
                                            <div
                                                key={criterion}
                                                className="flex items-center justify-between gap-3 text-xs"
                                            >
                                                <span
                                                    className={
                                                        result.met
                                                            ? 'text-foreground'
                                                            : 'text-muted-foreground'
                                                    }
                                                >
                                                    {result.met ? '✓' : '○'}{' '}
                                                    {scoreLabel(criterion)}
                                                </span>
                                                <span className="font-medium tabular-nums">
                                                    {result.points}/
                                                    {result.weight}
                                                </span>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            )}
                            {editing && editing.stage_history.length > 0 && (
                                <div className="sm:col-span-2">
                                    <div className="mb-2 flex items-center gap-2 text-sm font-medium">
                                        <History className="size-4" />
                                        Historial de etapas
                                    </div>
                                    <div className="max-h-36 divide-y overflow-y-auto rounded-md border px-3">
                                        {editing.stage_history.map((change) => (
                                            <div
                                                key={change.id}
                                                className="flex items-center justify-between gap-3 py-2 text-xs"
                                            >
                                                <span>
                                                    {change.from_stage
                                                        ? `${stageLabel(change.from_stage)} → `
                                                        : ''}
                                                    <strong>
                                                        {stageLabel(
                                                            change.to_stage,
                                                        )}
                                                    </strong>
                                                </span>
                                                <span className="text-right text-muted-foreground">
                                                    {change.changed_by?.name ||
                                                        'Sistema'}{' '}
                                                    ·{' '}
                                                    {dateTime(
                                                        change.changed_at,
                                                    )}
                                                </span>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            )}
                        </div>
                        <DialogFooter className="gap-2 sm:justify-between">
                            <div className="flex gap-2">
                                {editing && !editing.deleted_at && (
                                    <Button
                                        type="button"
                                        variant="destructive"
                                        onClick={() =>
                                            router.delete(
                                                `/opportunities/${editing.id}`,
                                                {
                                                    onSuccess: () =>
                                                        setOpen(false),
                                                },
                                            )
                                        }
                                    >
                                        <Archive /> Archivar
                                    </Button>
                                )}
                                {editing?.deleted_at && (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() =>
                                            router.post(
                                                `/opportunities/${editing.id}/restore`,
                                                {},
                                                {
                                                    onSuccess: () =>
                                                        setOpen(false),
                                                },
                                            )
                                        }
                                    >
                                        <RotateCcw /> Restaurar
                                    </Button>
                                )}
                                {editing && !editing.deleted_at && (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        asChild
                                    >
                                        <Link
                                            href={
                                                editing.quote
                                                    ? `/quotes/${editing.quote.id}`
                                                    : `/quotes/create?opportunity=${editing.id}`
                                            }
                                        >
                                            {editing.quote
                                                ? `Open ${editing.quote.number}`
                                                : 'Prepare quote'}
                                        </Link>
                                    </Button>
                                )}
                            </div>
                            <div className="flex gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => setOpen(false)}
                                >
                                    Cancel
                                </Button>
                                {!editing?.deleted_at && (
                                    <Button disabled={form.processing}>
                                        {editing
                                            ? 'Save changes'
                                            : 'Create opportunity'}
                                    </Button>
                                )}
                            </div>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}

function FunnelSummary({ funnel }: { funnel: Record<string, number> }) {
    const total = Object.values(funnel).reduce((sum, count) => sum + count, 0);

    return (
        <section className="rounded-md border">
            <div className="border-b px-4 py-3">
                <h2 className="text-sm font-semibold">
                    Distribución actual del embudo
                </h2>
            </div>
            <div className="grid sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-7">
                {stages.map((stage) => {
                    const count = funnel[stage.value] || 0;
                    const percentage = total > 0 ? (count / total) * 100 : 0;

                    return (
                        <div
                            key={stage.value}
                            className="border-b p-3 last:border-b-0 sm:border-r lg:border-b-0"
                        >
                            <div className="flex items-center justify-between gap-2 text-xs text-muted-foreground">
                                <span>{stage.label}</span>
                                <span>{percentage.toFixed(0)}%</span>
                            </div>
                            <div className="mt-1 text-lg font-semibold">
                                {count}
                            </div>
                            <div className="mt-2 h-1.5 overflow-hidden rounded-full bg-muted">
                                <div
                                    className="h-full rounded-full bg-primary"
                                    style={{ width: `${percentage}%` }}
                                />
                            </div>
                        </div>
                    );
                })}
            </div>
        </section>
    );
}

function ResponsePerformanceSummary({
    rows,
}: {
    rows: ResponsePerformanceRow[];
}) {
    return (
        <section className="rounded-md border">
            <div className="border-b px-4 py-3">
                <h2 className="font-semibold">
                    Rendimiento de primera respuesta
                </h2>
                <p className="text-sm text-muted-foreground">
                    Comparativa por responsable sobre las oportunidades
                    visibles con SLA aplicable. Datos en tiempo real según los
                    filtros actuales.
                </p>
            </div>
            <div className="overflow-x-auto">
                <table className="w-full min-w-[52rem] text-sm">
                    <thead className="border-b bg-muted/40 text-left text-xs text-muted-foreground">
                        <tr>
                            <th className="p-3">Responsable</th>
                            <th className="p-3 text-right">Leads</th>
                            <th className="p-3">Cumplimiento</th>
                            <th className="p-3 text-right">En SLA</th>
                            <th className="p-3 text-right">Fuera de SLA</th>
                            <th className="p-3 text-right">Pendientes</th>
                            <th className="p-3 text-right">Tiempo medio</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y">
                        {rows.map((row) => (
                            <tr key={row.owner_id}>
                                <td className="p-3 font-medium">
                                    <Link
                                        className="text-primary hover:underline"
                                        href={`/opportunities?owner=${row.owner_id}`}
                                    >
                                        {row.owner_name}
                                    </Link>
                                </td>
                                <td className="p-3 text-right tabular-nums">
                                    {row.total}
                                </td>
                                <td className="p-3">
                                    <div className="flex items-center gap-3">
                                        <div className="h-2 min-w-24 flex-1 overflow-hidden rounded-full bg-muted">
                                            <div
                                                className="h-full rounded-full bg-emerald-500"
                                                style={{
                                                    width: `${row.met_rate ?? 0}%`,
                                                }}
                                            />
                                        </div>
                                        <span className="w-12 text-right font-medium tabular-nums">
                                            {row.met_rate === null
                                                ? '—'
                                                : `${row.met_rate}%`}
                                        </span>
                                    </div>
                                </td>
                                <td className="p-3 text-right tabular-nums text-emerald-700 dark:text-emerald-300">
                                    {row.met}
                                </td>
                                <td
                                    className={`p-3 text-right tabular-nums ${row.breached > 0 ? 'font-semibold text-destructive' : ''}`}
                                >
                                    {row.breached}
                                </td>
                                <td className="p-3 text-right tabular-nums">
                                    {row.pending}
                                </td>
                                <td className="p-3 text-right tabular-nums">
                                    {row.average_minutes === null
                                        ? '—'
                                        : formatMinutes(row.average_minutes)}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </section>
    );
}

function ForecastSummary({ rows }: { rows: ForecastRow[] }) {
    return (
        <section className="rounded-md border">
            <div className="border-b px-4 py-3">
                <h2 className="font-semibold">Forecast comercial</h2>
                <p className="text-sm text-muted-foreground">
                    Previsión ponderada por mes y responsable. Despliega una
                    fila para ver las oportunidades que explican el importe.
                </p>
            </div>
            <div className="overflow-x-auto">
                <table className="w-full min-w-[48rem] text-sm">
                    <thead className="border-b bg-muted/40 text-left text-xs text-muted-foreground">
                        <tr>
                            <th className="p-3">Mes previsto</th>
                            <th className="p-3">Responsable</th>
                            <th className="p-3 text-right">Oportunidades</th>
                            <th className="p-3 text-right">Prob. ponderada</th>
                            <th className="p-3 text-right">Pipeline</th>
                            <th className="p-3 text-right">Ponderado</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y">
                        {rows.map((row) => (
                            <tr
                                key={`${row.month || 'unscheduled'}-${row.owner_id || 'unassigned'}`}
                                className="align-top"
                            >
                                <td className="p-3 font-medium">
                                    {forecastMonth(row.month)}
                                </td>
                                <td className="p-3">{row.owner_name}</td>
                                <td className="p-3 text-right">
                                    <details className="group">
                                        <summary className="cursor-pointer list-none font-medium text-primary hover:underline">
                                            {row.count}
                                        </summary>
                                        <div className="mt-2 min-w-72 space-y-2 rounded-md border bg-background p-2 text-left shadow-sm">
                                            {row.opportunities.map((item) => (
                                                <Link
                                                    key={item.id}
                                                    href={`/opportunities?opportunity=${item.id}`}
                                                    className="block rounded px-2 py-1.5 hover:bg-muted"
                                                >
                                                    <span className="block font-medium">
                                                        {item.code} ·{' '}
                                                        {item.title}
                                                    </span>
                                                    <span className="text-xs text-muted-foreground">
                                                        {money(item.amount)} ·{' '}
                                                        {item.probability}% ·{' '}
                                                        {money(
                                                            item.weighted_amount,
                                                        )}{' '}
                                                        ponderado
                                                    </span>
                                                </Link>
                                            ))}
                                        </div>
                                    </details>
                                </td>
                                <td className="p-3 text-right tabular-nums">
                                    {row.average_probability}%
                                </td>
                                <td className="p-3 text-right tabular-nums">
                                    {money(row.amount)}
                                </td>
                                <td className="p-3 text-right font-semibold tabular-nums">
                                    {money(row.weighted_amount)}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </section>
    );
}

function OpportunityActivityDialog({
    opportunity,
    users,
}: {
    opportunity: Opportunity;
    users: UserOption[];
}) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    className="mt-1 h-7 w-full justify-start px-2 text-xs"
                >
                    <Plus className="size-3" /> Programar acción
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Próxima acción</DialogTitle>
                    <DialogDescription>
                        Programa el siguiente seguimiento de {opportunity.code}.
                    </DialogDescription>
                </DialogHeader>
                <Form
                    action={`/opportunities/${opportunity.id}/activities`}
                    method="post"
                    resetOnSuccess
                    onSuccess={() => setOpen(false)}
                    className="grid gap-4"
                >
                    {({ errors, processing }) => (
                        <>
                            <Field label="Título" error={errors.title}>
                                <Input
                                    name="title"
                                    placeholder="Llamar para revisar la propuesta"
                                    required
                                />
                            </Field>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <Field label="Tipo" error={errors.type}>
                                    <NativeSelect
                                        name="type"
                                        defaultValue="call"
                                        options={[
                                            ['call', 'Llamada'],
                                            ['email', 'Email'],
                                            ['meeting', 'Reunión'],
                                            ['visit', 'Visita'],
                                            ['task', 'Tarea'],
                                            ['reminder', 'Recordatorio'],
                                        ]}
                                    />
                                </Field>
                                <Field
                                    label="Responsable"
                                    error={errors.assigned_user_id}
                                >
                                    <NativeSelect
                                        name="assigned_user_id"
                                        defaultValue={
                                            opportunity.owner_user_id
                                                ? String(
                                                      opportunity.owner_user_id,
                                                  )
                                                : ''
                                        }
                                        options={[
                                            ['', 'Sin asignar'],
                                            ...users.map((user) => [
                                                String(user.id),
                                                user.name,
                                            ]),
                                        ]}
                                    />
                                </Field>
                                <Field
                                    label="Fecha límite"
                                    error={errors.due_at}
                                >
                                    <Input
                                        name="due_at"
                                        type="datetime-local"
                                        required
                                    />
                                </Field>
                                <Field
                                    label="Prioridad"
                                    error={errors.priority}
                                >
                                    <NativeSelect
                                        name="priority"
                                        defaultValue="normal"
                                        options={[
                                            ['low', 'Baja'],
                                            ['normal', 'Normal'],
                                            ['high', 'Alta'],
                                        ]}
                                    />
                                </Field>
                            </div>
                            <Field label="Notas" error={errors.notes}>
                                <Textarea name="notes" />
                            </Field>
                            <DialogFooter>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => setOpen(false)}
                                >
                                    Cancelar
                                </Button>
                                <Button disabled={processing}>Programar</Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function OpportunityTable({
    opportunities,
    sourceOptions,
    commercialKindOptions,
    onEdit,
}: {
    opportunities: Opportunity[];
    sourceOptions: Record<string, string>;
    commercialKindOptions: Record<string, string>;
    onEdit: (opportunity: Opportunity) => void;
}) {
    return (
        <div className="overflow-x-auto rounded-md border">
            <table className="w-full min-w-[70rem] text-sm">
                <thead className="border-b bg-muted/40 text-left text-xs text-muted-foreground">
                    <tr>
                        <th className="p-3">Oportunidad</th>
                        <th className="p-3">Cliente</th>
                        <th className="p-3">Tipo</th>
                        <th className="p-3">Etapa</th>
                        <th className="p-3">Responsable</th>
                        <th className="p-3 text-right">Importe</th>
                        <th className="p-3 text-right">Forecast</th>
                        <th className="p-3">Próxima acción</th>
                        <th className="p-3 text-right">Score</th>
                    </tr>
                </thead>
                <tbody className="divide-y">
                    {opportunities.map((item) => (
                        <tr key={item.id} className="hover:bg-muted/30">
                            <td className="p-3">
                                <button
                                    type="button"
                                    onClick={() => onEdit(item)}
                                    className="text-left font-medium hover:underline"
                                >
                                    {item.code} · {item.title}
                                </button>
                                {item.source && (
                                    <div className="text-xs text-muted-foreground">
                                        {sourceOptions[item.source] ||
                                            item.source}
                                    </div>
                                )}
                            </td>
                            <td className="p-3">
                                {item.customer
                                    ? customerName(item.customer)
                                    : '—'}
                            </td>
                            <td className="p-3">
                                <Badge variant="secondary">
                                    {commercialKindOptions[
                                        item.commercial_kind
                                    ] || item.commercial_kind}
                                </Badge>
                                {item.renewal_due_on && (
                                    <div className="mt-1 text-xs text-muted-foreground">
                                        {date(item.renewal_due_on)}
                                    </div>
                                )}
                            </td>
                            <td className="p-3">
                                <Badge variant="outline">
                                    {stageLabel(item.stage)}
                                </Badge>
                                <div
                                    className={`mt-1 text-xs ${item.is_stale ? 'font-medium text-amber-700 dark:text-amber-300' : 'text-muted-foreground'}`}
                                >
                                    {item.days_in_stage} días
                                </div>
                                <ResponseSlaBadge opportunity={item} />
                            </td>
                            <td className="p-3">{item.owner?.name || '—'}</td>
                            <td className="p-3 text-right tabular-nums">
                                {money(Number(item.estimated_value))}
                            </td>
                            <td className="p-3 text-right tabular-nums">
                                {money(
                                    (Number(item.estimated_value) *
                                        item.probability) /
                                        100,
                                )}
                            </td>
                            <td className="p-3">
                                {item.next_activity
                                    ? `${item.next_activity.title} · ${dateTime(item.next_activity.due_at)}`
                                    : '—'}
                            </td>
                            <td className="p-3 text-right font-medium tabular-nums">
                                {item.commercial_score}/100
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

function CrmSettingsDialog({ settings }: { settings: CrmSettings }) {
    const [open, setOpen] = useState(false);
    const form = useForm(
        Object.fromEntries(
            Object.entries(settings).map(([key, value]) => [
                key,
                String(value),
            ]),
        ) as Record<keyof CrmSettings, string>,
    );
    const weightFields: Array<[keyof CrmSettings, string]> = [
        ['customer_contactable_weight', 'Cliente contactable'],
        ['source_identified_weight', 'Origen identificado'],
        ['owner_assigned_weight', 'Responsable asignado'],
        ['expected_close_set_weight', 'Fecha prevista'],
        ['next_action_scheduled_weight', 'Próxima acción'],
        ['estimated_value_set_weight', 'Importe estimado'],
        ['advanced_stage_weight', 'Etapa avanzada'],
    ];
    const total = weightFields.reduce(
        (sum, [field]) => sum + (Number(form.data[field]) || 0),
        0,
    );
    const globalError = (form.errors as Record<string, string>).weights;

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="outline">
                    <Settings2 /> Configuración CRM
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Gobierno comercial</DialogTitle>
                    <DialogDescription>
                        Define cuándo una oportunidad está estancada y cómo se
                        calcula su scoring. Los pesos deben sumar 100 puntos.
                    </DialogDescription>
                </DialogHeader>
                <form
                    className="grid gap-5"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.patch('/crm/settings', {
                            preserveScroll: true,
                            onSuccess: () => setOpen(false),
                        });
                    }}
                >
                    <Field
                        label="Días para considerar una etapa estancada"
                        error={form.errors.stale_after_days}
                    >
                        <Input
                            type="number"
                            min="1"
                            max="365"
                            value={form.data.stale_after_days}
                            onChange={(event) =>
                                form.setData(
                                    'stale_after_days',
                                    event.target.value,
                                )
                            }
                        />
                    </Field>
                    <Field
                        label="SLA de primera respuesta (minutos)"
                        error={form.errors.lead_response_sla_minutes}
                    >
                        <Input
                            type="number"
                            min="15"
                            max="10080"
                            step="15"
                            value={form.data.lead_response_sla_minutes}
                            onChange={(event) =>
                                form.setData(
                                    'lead_response_sla_minutes',
                                    event.target.value,
                                )
                            }
                        />
                    </Field>
                    <Field
                        label="Escalado tras el primer aviso (minutos)"
                        error={form.errors.lead_response_escalation_minutes}
                    >
                        <Input
                            type="number"
                            min="15"
                            max="10080"
                            step="15"
                            value={form.data.lead_response_escalation_minutes}
                            onChange={(event) =>
                                form.setData(
                                    'lead_response_escalation_minutes',
                                    event.target.value,
                                )
                            }
                        />
                    </Field>
                    <div>
                        <div className="mb-3 flex items-center justify-between gap-3">
                            <Label>Pesos del scoring</Label>
                            <Badge
                                variant={
                                    total === 100 ? 'outline' : 'destructive'
                                }
                            >
                                {total}/100 puntos
                            </Badge>
                        </div>
                        <div className="grid gap-3 sm:grid-cols-2">
                            {weightFields.map(([field, label]) => (
                                <Field
                                    key={field}
                                    label={label}
                                    error={form.errors[field]}
                                >
                                    <Input
                                        type="number"
                                        min="0"
                                        max="100"
                                        value={form.data[field]}
                                        onChange={(event) =>
                                            form.setData(
                                                field,
                                                event.target.value,
                                            )
                                        }
                                    />
                                </Field>
                            ))}
                        </div>
                        {globalError && (
                            <InputError
                                className="mt-2"
                                message={globalError}
                            />
                        )}
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setOpen(false)}
                        >
                            Cancelar
                        </Button>
                        <Button disabled={form.processing || total !== 100}>
                            Guardar y recalcular
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function Field({
    label,
    error,
    className = '',
    children,
}: {
    label: string;
    error?: string;
    className?: string;
    children: React.ReactNode;
}) {
    return (
        <div className={`grid gap-2 ${className}`}>
            <Label>{label}</Label>
            {children}
            <InputError message={error} />
        </div>
    );
}

function NativeSelect({
    name,
    defaultValue,
    options,
}: {
    name: string;
    defaultValue?: string;
    options: (readonly [string, string] | string[])[];
}) {
    return (
        <select
            name={name}
            defaultValue={defaultValue || ''}
            className="h-9 w-full rounded-md border border-input bg-background px-3 text-sm shadow-xs"
        >
            {options.map(([value, label]) => (
                <option key={value} value={value}>
                    {label}
                </option>
            ))}
        </select>
    );
}

function SelectField({
    label,
    value,
    error,
    onChange,
    options,
}: {
    label: string;
    value: string;
    error?: string;
    onChange: (value: string) => void;
    options: string[][];
}) {
    return (
        <Field label={label} error={error}>
            <Select value={value} onValueChange={onChange}>
                <SelectTrigger>
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    {options.map(([key, text]) => (
                        <SelectItem key={key} value={key}>
                            {text}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
        </Field>
    );
}

function Metric({
    icon: Icon,
    label,
    value,
    detail,
}: {
    icon: typeof Target;
    label: string;
    value: string;
    detail?: string;
}) {
    return (
        <div className="flex items-center gap-3 bg-background p-4">
            <Icon className="size-4 text-muted-foreground" />
            <div>
                <div className="text-xs text-muted-foreground">{label}</div>
                <div className="text-lg font-semibold">{value}</div>
                {detail && (
                    <div className="text-xs text-muted-foreground">
                        {detail}
                    </div>
                )}
            </div>
        </div>
    );
}

function customerName(customer: Customer) {
    return (
        customer.company_name ||
        `${customer.first_name} ${customer.last_name || ''}`.trim()
    );
}
function ResponseSlaBadge({ opportunity }: { opportunity: Opportunity }) {
    if (opportunity.response_sla_status === 'not_applicable') {
        return null;
    }

    const met = opportunity.response_sla_status === 'met';
    const breached = opportunity.response_sla_status === 'breached';
    const label = met
        ? `Respuesta en ${formatMinutes(opportunity.first_response_minutes || 0)}`
        : breached
          ? opportunity.first_responded_at
              ? `Respuesta tardía · ${formatMinutes(opportunity.first_response_minutes || 0)}`
              : 'Primera respuesta fuera de SLA'
          : opportunity.first_response_due_at
            ? `Responder antes de ${dateTime(opportunity.first_response_due_at)}`
            : 'SLA pendiente';

    return (
        <Badge
            variant={breached ? 'destructive' : 'outline'}
            className={`mt-2 max-w-full ${met ? 'border-emerald-300 text-emerald-700 dark:border-emerald-900 dark:text-emerald-300' : ''}`}
        >
            <span className="truncate">{label}</span>
        </Badge>
    );
}
function nextBusinessAction() {
    const value = new Date();

    value.setDate(value.getDate() + 1);

    while ([0, 6].includes(value.getDay())) {
        value.setDate(value.getDate() + 1);
    }

    value.setHours(9, 0, 0, 0);

    const localValue = new Date(
        value.getTime() - value.getTimezoneOffset() * 60_000,
    );

    return localValue.toISOString().slice(0, 16);
}
function installationName(item: Installation) {
    return (
        item.project_reference || item.site_name || `Installation #${item.id}`
    );
}
function money(value: number) {
    return new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: 'EUR',
    }).format(value);
}
function formatMinutes(value: number) {
    const minutes = Math.max(0, Math.round(value));

    if (minutes < 60) {
        return `${minutes} min`;
    }

    const hours = Math.floor(minutes / 60);
    const remainder = minutes % 60;

    return remainder > 0 ? `${hours} h ${remainder} min` : `${hours} h`;
}
function date(value: string | null) {
    return value
        ? new Intl.DateTimeFormat(undefined, { dateStyle: 'short' }).format(
              new Date(`${value}T00:00:00`),
          )
        : 'No date';
}
function dateTime(value: string) {
    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'short',
        timeStyle: 'short',
    }).format(new Date(value));
}
function forecastMonth(value: string | null) {
    if (!value) {
        return 'Sin fecha prevista';
    }

    return new Intl.DateTimeFormat('es-ES', {
        month: 'long',
        year: 'numeric',
    }).format(new Date(`${value}-01T00:00:00`));
}
function isOverdue(value: string) {
    return new Date(value).getTime() < Date.now();
}
function stageLabel(value: string) {
    return stages.find((stage) => stage.value === value)?.label || value;
}
function scoreLabel(value: string) {
    const labels: Record<string, string> = {
        customer_contactable: 'Cliente contactable',
        source_identified: 'Origen identificado',
        owner_assigned: 'Responsable asignado',
        expected_close_set: 'Fecha prevista',
        next_action_scheduled: 'Próxima acción',
        estimated_value_set: 'Importe estimado',
        advanced_stage: 'Etapa avanzada',
    };

    return labels[value] || value;
}
function periodLabel(value?: string) {
    if (value === '30') {
        return '30 días';
    }

    if (value === '365') {
        return '1 año';
    }

    if (value === 'all') {
        return 'histórico';
    }

    return '90 días';
}

function opportunityFilterUrl(filters: Record<string, string | undefined>) {
    const params = new URLSearchParams();

    Object.entries(filters).forEach(([key, value]) => {
        if (value && key !== 'archived') {
            params.set(key, value);
        }
    });

    return `/opportunities?${params.toString()}`;
}

import { Form, Head, Link, router, useForm } from '@inertiajs/react';
import {
    Archive,
    CalendarClock,
    Clock3,
    Plus,
    RotateCcw,
    Settings2,
    Target,
    Timer,
} from 'lucide-react';
import {
    cloneElement,
    isValidElement,
    useEffect,
    useId,
    useMemo,
    useState,
} from 'react';
import { toast } from 'sonner';
import { CrmWorkspace } from '@/components/crm/crm-workspace';
import InputError from '@/components/input-error';
import { PageHeader, PageShell } from '@/components/page-shell';
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
import { useCommercialFormProtection } from '@/hooks/use-commercial-form-protection';
import {
    interestLabel,
    interestOptions,
    probabilityAfterStageChange,
} from '@/lib/opportunity-interest';

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
export type Opportunity = {
    stage_label: string;
    created_at: string;
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
    change_note: string;
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
    change_note: '',
    next_action_type: 'call',
    next_action_title: 'Contactar y cualificar al cliente',
    next_action_priority: 'normal',
    next_action_due_at: nextBusinessAction(),
};

export default function OpportunitiesIndex({
    stageOptions,
    stageTotals,
    pagination,
    crmTasks,
    calendarPagination,
    crmEvents,
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
    section = 'pipeline',
    metrics,
    forecast = [],
    responsePerformance = [],
    savedFilters = [],
    crmSettings,
    canConfigureCrm = false,
}: {
    calendarPagination: {
        month: string;
        prev_month: string;
        next_month: string;
        prev: string | null;
        next: string | null;
        total: number;
    } | null;
    stageOptions: Record<string, string>;
    stageTotals: Record<string, { count: number; amount: number }>;
    pagination: {
        current_page: number;
        last_page: number;
        total: number;
        prev: string | null;
        next: string | null;
    } | null;
    crmTasks: Array<{
        id: number;
        title: string;
        due_at: string;
        opportunity_id: number;
        opportunity: { title: string } | null;
    }>;
    crmEvents: Array<{
        id: number;
        summary: string;
        occurred_at: string;
        opportunity_id: number;
        actor: { name: string } | null;
    }>;
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
    section?: 'pipeline' | 'analysis';
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
    const stages = Object.entries(stageOptions).map(([value, label]) => ({
        value,
        label,
    }));
    const [open, setOpen] = useState(false);
    const [editing, setEditing] = useState<Opportunity | null>(null);
    const [matchedCustomer, setMatchedCustomer] = useState<Customer | null>(
        null,
    );
    const [customerLookup, setCustomerLookup] = useState<
        'idle' | 'checking' | 'found' | 'new' | 'invalid'
    >('idle');
    const form = useForm<FormData>(emptyForm);
    const protection = useCommercialFormProtection(() => form, open);
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
        const editorData = {
            ...emptyForm,
            person_id: customer ? String(customer.id) : '',
            customer_phone: customer?.phone || '',
            customer_preferred_contact_channel:
                customer?.preferred_contact_channel || 'phone',
            customer_consent_channel:
                customer?.preferred_contact_channel || 'phone',
            next_action_due_at: nextBusinessAction(),
        };
        form.setData(editorData);

        if (protection.mobile) {
            form.setDefaults(editorData);
        }

        setOpen(true);
    }

    function openEditor(item: Opportunity) {
        setEditing(item);
        setMatchedCustomer(null);
        setCustomerLookup('idle');
        form.clearErrors();
        const editorData = {
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
            change_note: '',
            next_action_type: 'call',
            next_action_title: 'Contactar y cualificar al cliente',
            next_action_priority: 'normal',
            next_action_due_at: nextBusinessAction(),
        };
        form.setData(editorData);

        if (protection.mobile) {
            form.setDefaults(editorData);
        }

        setOpen(true);
    }

    function submit(event: React.FormEvent) {
        event.preventDefault();
        const options = {
            preserveScroll: true,
            onBefore: protection.beginSubmit,
            onFinish: protection.finishSubmit,
            onSuccess: () => protection.saved(() => setOpen(false)),
        };

        if (editing) {
            form.patch(`/opportunities/${editing.id}`, options);
        } else {
            form.post('/opportunities', options);
        }
    }

    function changeStage(item: Opportunity, stage: string) {
        const probability = probabilityAfterStageChange(
            item.probability,
            item.stage,
            stage,
        );

        if (stage === 'lost') {
            openEditor({ ...item, stage, probability });

            return;
        }

        router.patch(
            `/opportunities/${item.id}/stage`,
            {
                stage,
                expected_stage: item.stage,
                probability,
                change_note: `Cambio de etapa desde el pipeline: ${stageOptions[item.stage]} → ${stageOptions[stage]}.`,
            },
            {
                preserveScroll: true,
                onError: (errors) =>
                    toast.error(
                        Object.values(errors)[0] ||
                            'No se ha podido cambiar la etapa.',
                    ),
            },
        );
    }

    return (
        <>
            <Head title="CRM" />
            <PageShell className="crm-workspace">
                <PageHeader
                    title="CRM"
                    description="Gestiona el pipeline comercial desde el primer contacto hasta la instalación."
                    secondaryNavigation={null}
                    secondaryActions={
                        <details className="crm-tools">
                            <summary>Herramientas CRM</summary>
                            <div className="flex flex-wrap gap-2">
                                <Button size="sm" variant="ghost" asChild>
                                    <Link
                                        href={opportunityFilterUrl({
                                            ...filters,
                                            section:
                                                section === 'analysis'
                                                    ? 'pipeline'
                                                    : 'analysis',
                                        })}
                                    >
                                        {section === 'analysis'
                                            ? 'Trabajo diario'
                                            : 'Análisis comercial'}
                                    </Link>
                                </Button>
                                <Button size="sm" variant="ghost" asChild>
                                    <Link
                                        href={
                                            filters.archived
                                                ? '/opportunities'
                                                : '/opportunities?archived=1'
                                        }
                                    >
                                        {filters.archived
                                            ? 'Oportunidades activas'
                                            : 'Archivadas'}
                                    </Link>
                                </Button>
                                {canConfigureCrm && (
                                    <CrmSettingsDialog settings={crmSettings} />
                                )}
                            </div>
                        </details>
                    }
                    primaryAction={
                        !filters.archived && (
                            <Button onClick={() => openCreator()}>
                                <Plus /> Nueva oportunidad
                            </Button>
                        )
                    }
                    actions={
                        <div className="crm-view-switch">
                            {[
                                ['kanban', 'Kanban'],
                                ['table', 'Tabla'],
                                ['calendar', 'Calendario'],
                            ].map(([view, label]) => (
                                <Button
                                    key={view}
                                    variant={
                                        (filters.view || 'kanban') === view
                                            ? 'secondary'
                                            : 'outline'
                                    }
                                    asChild
                                >
                                    <Link
                                        href={opportunityFilterUrl({
                                            ...filters,
                                            view,
                                        })}
                                    >
                                        {label}
                                    </Link>
                                </Button>
                            ))}
                        </div>
                    }
                />
                {section === 'pipeline' ? (
                    <>
                        <details className="crm-saved-views">
                            <summary>Vistas guardadas</summary>
                            <div className="flex flex-wrap items-center gap-2">
                                {savedFilters.map((filter) => (
                                    <div
                                        key={filter.id}
                                        className="flex items-center gap-1"
                                    >
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            asChild
                                        >
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
                                                variant="ghost"
                                                size="sm"
                                                aria-label={`Eliminar filtro ${filter.name}`}
                                            >
                                                ×
                                            </Button>
                                        </Form>
                                    </div>
                                ))}
                                <Form
                                    action="/crm/filters"
                                    method="post"
                                    className="flex gap-2"
                                >
                                    <input
                                        type="hidden"
                                        name="scope"
                                        value="opportunities"
                                    />
                                    {Object.entries(filters)
                                        .filter(([, value]) => Boolean(value))
                                        .map(([key, value]) => (
                                            <input
                                                key={key}
                                                type="hidden"
                                                name={`filters[${key}]`}
                                                value={value}
                                            />
                                        ))}
                                    <Input
                                        name="name"
                                        aria-label="Nombre de vista guardada"
                                        placeholder="Nombre de vista"
                                        className="h-8 w-40"
                                        required
                                    />
                                    <Button size="sm" variant="ghost">
                                        Guardar vista
                                    </Button>
                                    <label className="flex items-center gap-2 text-xs">
                                        <input
                                            type="checkbox"
                                            name="is_default"
                                            value="1"
                                        />
                                        Predeterminada
                                    </label>
                                </Form>
                            </div>
                        </details>
                        <CrmWorkspace
                            opportunities={opportunities}
                            stages={stageOptions}
                            totals={stageTotals}
                            pagination={pagination}
                            filters={filters}
                            users={users}
                            sources={sourceOptions}
                            kinds={commercialKindOptions}
                            calendar={calendarPagination}
                            tasks={crmTasks}
                            events={crmEvents}
                            onCreate={(stage) => {
                                openCreator();

                                if (stage) {
                                    form.setData('stage', stage);
                                }
                            }}
                            onMove={changeStage}
                        />
                    </>
                ) : (
                    <>
                        <div className="space-y-6">
                            <Form
                                action="/opportunities"
                                method="get"
                                className="flex gap-2"
                            >
                                <input
                                    type="hidden"
                                    name="section"
                                    value="analysis"
                                />
                                {Object.entries(filters)
                                    .filter(
                                        ([key, value]) =>
                                            key !== 'period' && Boolean(value),
                                    )
                                    .map(([key, value]) => (
                                        <input
                                            key={key}
                                            type="hidden"
                                            name={key}
                                            value={value}
                                        />
                                    ))}
                                <label className="flex items-center gap-2 text-sm">
                                    Periodo
                                    <select
                                        name="period"
                                        defaultValue={filters.period || '90'}
                                    >
                                        <option value="30">30 días</option>
                                        <option value="90">90 días</option>
                                        <option value="365">1 año</option>
                                        <option value="all">
                                            Todo el histórico
                                        </option>
                                    </select>
                                </label>
                                <Button variant="outline" size="sm">
                                    Aplicar
                                </Button>
                            </Form>
                            <section className="grid gap-px overflow-hidden border bg-border sm:grid-cols-2 xl:grid-cols-4">
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
                                    icon={Clock3}
                                    label="Cumplimiento primera respuesta"
                                    value={
                                        metrics.response_sla_met_rate === null
                                            ? '—'
                                            : `${metrics.response_sla_met_rate}%`
                                    }
                                    detail={
                                        metrics.average_first_response_minutes ===
                                        null
                                            ? 'Sin respuestas registradas'
                                            : `Media ${formatMinutes(metrics.average_first_response_minutes)}`
                                    }
                                />
                                <Metric
                                    icon={CalendarClock}
                                    label="Renovaciones próximas"
                                    value={String(metrics.renewals_due)}
                                    detail="Próximos 30 días"
                                />
                            </section>
                            <FunnelSummary
                                funnel={metrics.funnel}
                                stages={stages}
                            />
                            {responsePerformance.length > 0 && (
                                <ResponsePerformanceSummary
                                    rows={responsePerformance}
                                />
                            )}
                            {forecast.length > 0 && (
                                <ForecastSummary rows={forecast} />
                            )}
                        </div>
                    </>
                )}
            </PageShell>

            <Dialog
                open={open}
                onOpenChange={(value) => {
                    if (value) {
                        setOpen(true);
                    } else if (!protection.confirmationOpen) {
                        protection.requestClose(() => setOpen(false));
                    }
                }}
            >
                <DialogContent
                    className="commercial-opportunity-dialog max-h-[90vh] overflow-y-auto sm:max-w-2xl"
                    onInteractOutside={(event) => {
                        event.preventDefault();

                        if (!protection.confirmationOpen) {
                            protection.requestClose(() => setOpen(false));
                        }
                    }}
                >
                    {protection.confirmation}
                    <form onSubmit={submit} className="grid gap-5">
                        <DialogHeader>
                            <DialogTitle>
                                {editing
                                    ? 'Edit opportunity'
                                    : 'New opportunity'}
                            </DialogTitle>
                            <DialogDescription>
                                {editing?.code ||
                                    'Añade un lead al pipeline comercial.'}
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
                                    form.setData(
                                        'probability',
                                        String(
                                            probabilityAfterStageChange(
                                                Number(form.data.probability),
                                                form.data.stage,
                                                value,
                                            ),
                                        ),
                                    );
                                    form.setData('stage', value);
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
                                label="Comercial responsable"
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
                            <SelectField
                                label="Interest level"
                                error={form.errors.probability}
                                value={form.data.probability}
                                disabled={['won', 'lost'].includes(
                                    form.data.stage,
                                )}
                                onChange={(value) =>
                                    form.setData('probability', value)
                                }
                                options={
                                    ['won', 'lost'].includes(form.data.stage)
                                        ? [
                                              [
                                                  form.data.probability,
                                                  interestLabel(
                                                      Number(
                                                          form.data.probability,
                                                      ),
                                                      form.data.stage,
                                                  ),
                                              ],
                                          ]
                                        : interestOptions(form.data.probability)
                                }
                            />
                            <Field
                                label="Fecha prevista de cierre"
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
                            {editing ? (
                                <Field
                                    label="Nota de modificación"
                                    error={form.errors.change_note}
                                    className="sm:col-span-2"
                                >
                                    <Textarea
                                        value={form.data.change_note}
                                        onChange={(event) =>
                                            form.setData(
                                                'change_note',
                                                event.target.value,
                                            )
                                        }
                                        placeholder="Explica el motivo del cambio, especialmente si avanza o retrocede de etapa."
                                    />
                                </Field>
                            ) : null}
                            {editing ? (
                                <p className="text-xs text-muted-foreground sm:col-span-2">
                                    El scoring y el histórico completo se
                                    consultan en el{' '}
                                    <Link
                                        href={`/opportunities/${editing.id}`}
                                        className="font-medium text-foreground hover:underline"
                                    >
                                        expediente de la oportunidad
                                    </Link>
                                    .
                                </p>
                            ) : null}
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
                                    onClick={() =>
                                        protection.requestClose(() =>
                                            setOpen(false),
                                        )
                                    }
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

function FunnelSummary({
    funnel,
    stages,
}: {
    funnel: Record<string, number>;
    stages: Array<{ value: string; label: string }>;
}) {
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
                    Comparativa por responsable sobre las oportunidades visibles
                    con SLA aplicable. Datos en tiempo real según los filtros
                    actuales.
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
                                <td className="p-3 text-right text-emerald-700 tabular-nums dark:text-emerald-300">
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
                                                    href={`/opportunities/${item.id}`}
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
    const fieldId = useId();
    const control =
        isValidElement<{ id?: string }>(children) &&
        (children.type === Input || children.type === Textarea)
            ? cloneElement(children, { id: fieldId })
            : children;

    return (
        <div className={`grid gap-2 ${className}`}>
            <Label htmlFor={fieldId}>{label}</Label>
            {control}
            <InputError message={error} />
        </div>
    );
}

function SelectField({
    label,
    value,
    error,
    onChange,
    options,
    disabled = false,
}: {
    label: string;
    value: string;
    error?: string;
    onChange: (value: string) => void;
    options: string[][];
    disabled?: boolean;
}) {
    return (
        <Field label={label} error={error}>
            <Select value={value} onValueChange={onChange} disabled={disabled}>
                <SelectTrigger aria-label={label}>
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
    return new Intl.NumberFormat(document.documentElement.lang, {
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
function forecastMonth(value: string | null) {
    if (!value) {
        return 'Sin fecha prevista';
    }

    return new Intl.DateTimeFormat('es-ES', {
        month: 'long',
        year: 'numeric',
    }).format(new Date(`${value}-01T00:00:00`));
}
function opportunityFilterUrl(filters: Record<string, string | undefined>) {
    const params = new URLSearchParams();

    Object.entries(filters).forEach(([key, value]) => {
        if (value) {
            params.set(key, value);
        }
    });

    return `/opportunities?${params.toString()}`;
}

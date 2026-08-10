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

type Customer = {
    id: number;
    first_name: string;
    last_name: string | null;
    company_name: string | null;
};
type Installation = {
    id: number;
    person_id: number | null;
    project_reference: string | null;
    site_name: string | null;
};
type UserOption = { id: number; name: string };
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
    quote: { id: number; number: string; status: string } | null;
    next_activity: Activity | null;
    stage_history: StageHistory[];
    deleted_at: string | null;
};

type FormData = {
    person_id: string;
    installation_id: string;
    owner_user_id: string;
    title: string;
    stage: string;
    probability: string;
    estimated_value: string;
    expected_close_on: string;
    source: string;
    source_detail: string;
    lost_reason: string;
    lost_reason_detail: string;
    notes: string;
};

const emptyForm: FormData = {
    person_id: '',
    installation_id: '',
    owner_user_id: '',
    title: '',
    stage: 'new',
    probability: '10',
    estimated_value: '0',
    expected_close_on: '',
    source: '',
    source_detail: '',
    lost_reason: '',
    lost_reason_detail: '',
    notes: '',
};

export default function OpportunitiesIndex({
    opportunities,
    customers,
    installations,
    users,
    sourceOptions,
    lostReasonOptions,
    filters = {},
    metrics,
    savedFilters = [],
}: {
    opportunities: Opportunity[];
    customers: Customer[];
    installations: Installation[];
    users: UserOption[];
    sourceOptions: Record<string, string>;
    lostReasonOptions: Record<string, string>;
    filters: {
        search?: string;
        stage?: string;
        owner?: string;
        source?: string;
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
        funnel: Record<string, number>;
        stage_days: Record<string, number | null>;
    };
    savedFilters?: Array<{
        id: number;
        name: string;
        filters: Record<string, string>;
        is_default: boolean;
    }>;
}) {
    const [open, setOpen] = useState(false);
    const [editing, setEditing] = useState<Opportunity | null>(null);
    const form = useForm<FormData>(emptyForm);
    const availableInstallations = useMemo(
        () =>
            installations.filter(
                (item) => String(item.person_id) === form.data.person_id,
            ),
        [form.data.person_id, installations],
    );

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
        setEditing(null);
        form.clearErrors();
        form.setData({ ...emptyForm, person_id: customerId || '' });
        setOpen(true);
    }

    function openEditor(item: Opportunity) {
        setEditing(item);
        form.clearErrors();
        form.setData({
            person_id: String(item.person_id),
            installation_id: item.installation_id
                ? String(item.installation_id)
                : '',
            owner_user_id: item.owner_user_id ? String(item.owner_user_id) : '',
            title: item.title,
            stage: item.stage,
            probability: String(item.probability),
            estimated_value: item.estimated_value,
            expected_close_on: item.expected_close_on || '',
            source: item.source || '',
            source_detail: item.source_detail || '',
            lost_reason: item.lost_reason || '',
            lost_reason_detail: item.lost_reason_detail || '',
            notes: item.notes || '',
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
                </section>

                <FunnelSummary funnel={metrics.funnel} />

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
                    className="grid gap-3 rounded-md border p-3 md:grid-cols-2 xl:grid-cols-[minmax(14rem,1fr)_10rem_11rem_12rem_12rem_11rem_auto]"
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
                        name="attention"
                        defaultValue={filters.attention}
                        options={[
                            ['', 'Todas las situaciones'],
                            ['overdue', 'Cierre vencido'],
                            ['no_action', 'Sin próxima acción'],
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
                        onEdit={openEditor}
                    />
                ) : (
                    <section className="overflow-x-auto pb-3">
                        <div className="grid min-w-[119rem] grid-cols-7 gap-3">
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
                                                        <span className="mt-2 block text-xs text-muted-foreground">
                                                            Scoring{' '}
                                                            {
                                                                item.commercial_score
                                                            }
                                                            /100
                                                        </span>
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
                                                            {stages.map(
                                                                (option) => (
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
                            <SelectField
                                label="Customer"
                                value={form.data.person_id}
                                error={form.errors.person_id}
                                onChange={(value) => {
                                    form.setData('person_id', value);
                                    form.setData('installation_id', '');
                                }}
                                options={customers.map((item) => [
                                    String(item.id),
                                    customerName(item),
                                ])}
                            />
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
                                options={stages.map((item) => [
                                    item.value,
                                    item.label,
                                ])}
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
    onEdit,
}: {
    opportunities: Opportunity[];
    sourceOptions: Record<string, string>;
    onEdit: (opportunity: Opportunity) => void;
}) {
    return (
        <div className="overflow-x-auto rounded-md border">
            <table className="w-full min-w-[70rem] text-sm">
                <thead className="border-b bg-muted/40 text-left text-xs text-muted-foreground">
                    <tr>
                        <th className="p-3">Oportunidad</th>
                        <th className="p-3">Cliente</th>
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
                                <Badge variant="outline">
                                    {stageLabel(item.stage)}
                                </Badge>
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
function isOverdue(value: string) {
    return new Date(value).getTime() < Date.now();
}
function stageLabel(value: string) {
    return stages.find((stage) => stage.value === value)?.label || value;
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

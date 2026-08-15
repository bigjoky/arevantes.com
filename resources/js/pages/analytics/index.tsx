import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    Bell,
    BookOpen,
    ChevronRight,
    Download,
    FileSpreadsheet,
    Gauge,
    MailCheck,
    ShieldCheck,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import type { FormEvent, ReactNode } from 'react';
import { PageHeader, PageShell } from '@/components/page-shell';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';

type Filter = {
    period: string;
    from: string;
    to: string;
    commercial_id: number | null;
    project_id: number | null;
    area: string;
};

type Quality = {
    score: number | null;
    complete: number;
    total: number;
    label: string;
};

type Metric = {
    key: string;
    name: string;
    description: string;
    formula: string;
    owner: string;
    source: string;
    frequency: string;
    unit: 'currency' | 'percent' | 'number';
    areas: string[];
    direction: 'up' | 'down';
    value: number;
    previous: number;
    previous_delta_percent: number | null;
    target: number | null;
    target_delta_percent: number | null;
    quality: Quality;
};

type RecordRow = {
    id: number;
    date: string | null;
    reference: string | null;
    project: string | null;
    status: string;
    value: number;
    url: string;
};

type Alert = {
    id: number;
    metric_key: string;
    operator: string;
    threshold: string;
    enabled: boolean;
    current_value: number | null;
    triggered: boolean;
};

type Subscription = {
    id: number;
    area: string;
    frequency: string;
    format: string;
};

type Props = {
    filters: Filter;
    areas: Array<{ key: string; name: string }>;
    metrics: Metric[];
    dictionary: Omit<Metric, 'value' | 'previous' | 'quality'>[];
    selectedMetric: Metric | null;
    records: RecordRow[];
    commercials: Array<{ id: number; name: string }>;
    projects: Array<{
        id: number;
        project_reference: string | null;
        site_name: string | null;
    }>;
    alerts: Alert[];
    subscriptions: Subscription[];
};

export default function AnalyticsIndex(props: Props) {
    const [section, setSection] = useState<
        'dashboard' | 'dictionary' | 'automation'
    >('dashboard');
    const [filter, setFilter] = useState(props.filters);

    const query = (overrides: Partial<Filter> = {}) => ({
        ...filter,
        ...overrides,
        metric: undefined,
    });

    const applyFilters = (event: FormEvent) => {
        event.preventDefault();
        router.get('/analytics', query(), { preserveState: true });
    };

    const openMetric = (key: string) => {
        router.get(
            '/analytics',
            { ...filter, metric: key },
            { preserveState: true },
        );
    };

    return (
        <>
            <Head title="Analítica y dirección" />
            <PageShell>
                <PageHeader
                    title="Analítica y dirección"
                    description="KPIs reconciliados con sus módulos de origen, trazables hasta cada registro."
                    actions={
                        <div className="flex gap-2">
                            <Button variant="outline" asChild>
                                <a href={exportUrl('excel', filter)}>
                                    <FileSpreadsheet className="size-4" /> Excel
                                </a>
                            </Button>
                            <Button variant="outline" asChild>
                                <a href={exportUrl('pdf', filter)}>
                                    <Download className="size-4" /> PDF
                                </a>
                            </Button>
                        </div>
                    }
                />

                <div className="flex flex-wrap gap-2">
                    {props.areas.map((area) => (
                        <Button
                            key={area.key}
                            size="sm"
                            variant={
                                props.filters.area === area.key
                                    ? 'default'
                                    : 'outline'
                            }
                            onClick={() => {
                                setFilter((current) => ({
                                    ...current,
                                    area: area.key,
                                }));
                                router.get(
                                    '/analytics',
                                    query({ area: area.key }),
                                );
                            }}
                        >
                            {area.name}
                        </Button>
                    ))}
                </div>

                <form
                    onSubmit={applyFilters}
                    className="grid gap-3 rounded-lg border bg-card p-4 md:grid-cols-2 xl:grid-cols-[1fr_1fr_1fr_1fr_1fr_auto]"
                >
                    <FilterField label="Periodo">
                        <select
                            className={selectClass}
                            value={filter.period}
                            onChange={(event) =>
                                setFilter({
                                    ...filter,
                                    period: event.target.value,
                                })
                            }
                        >
                            <option value="month">Mes actual</option>
                            <option value="quarter">Trimestre actual</option>
                            <option value="year">Año actual</option>
                            <option value="last_12_months">
                                Últimos 12 meses
                            </option>
                            <option value="custom">Personalizado</option>
                        </select>
                    </FilterField>
                    <FilterField label="Desde">
                        <Input
                            type="date"
                            value={filter.from}
                            disabled={filter.period !== 'custom'}
                            onChange={(event) =>
                                setFilter({
                                    ...filter,
                                    from: event.target.value,
                                })
                            }
                        />
                    </FilterField>
                    <FilterField label="Hasta">
                        <Input
                            type="date"
                            value={filter.to}
                            disabled={filter.period !== 'custom'}
                            onChange={(event) =>
                                setFilter({ ...filter, to: event.target.value })
                            }
                        />
                    </FilterField>
                    <FilterField label="Comercial">
                        <select
                            className={selectClass}
                            value={filter.commercial_id ?? ''}
                            onChange={(event) =>
                                setFilter({
                                    ...filter,
                                    commercial_id: event.target.value
                                        ? Number(event.target.value)
                                        : null,
                                })
                            }
                        >
                            <option value="">Todos</option>
                            {props.commercials.map((commercial) => (
                                <option
                                    key={commercial.id}
                                    value={commercial.id}
                                >
                                    {commercial.name}
                                </option>
                            ))}
                        </select>
                    </FilterField>
                    <FilterField label="Proyecto">
                        <select
                            className={selectClass}
                            value={filter.project_id ?? ''}
                            onChange={(event) =>
                                setFilter({
                                    ...filter,
                                    project_id: event.target.value
                                        ? Number(event.target.value)
                                        : null,
                                })
                            }
                        >
                            <option value="">Todos</option>
                            {props.projects.map((project) => (
                                <option key={project.id} value={project.id}>
                                    {project.project_reference ||
                                        project.site_name}
                                </option>
                            ))}
                        </select>
                    </FilterField>
                    <div className="flex items-end">
                        <Button type="submit" className="w-full">
                            Aplicar
                        </Button>
                    </div>
                </form>

                <div className="flex gap-1 border-b">
                    <SectionButton
                        active={section === 'dashboard'}
                        onClick={() => setSection('dashboard')}
                    >
                        <Gauge className="size-4" /> Panel
                    </SectionButton>
                    <SectionButton
                        active={section === 'dictionary'}
                        onClick={() => setSection('dictionary')}
                    >
                        <BookOpen className="size-4" /> Diccionario
                    </SectionButton>
                    <SectionButton
                        active={section === 'automation'}
                        onClick={() => setSection('automation')}
                    >
                        <Bell className="size-4" /> Alertas e informes
                    </SectionButton>
                </div>

                {section === 'dashboard' ? (
                    <Dashboard
                        metrics={props.metrics}
                        selectedMetric={props.selectedMetric}
                        records={props.records}
                        onOpen={openMetric}
                    />
                ) : null}
                {section === 'dictionary' ? (
                    <Dictionary definitions={props.dictionary} />
                ) : null}
                {section === 'automation' ? <Automation {...props} /> : null}
            </PageShell>
        </>
    );
}

function Dashboard({
    metrics,
    selectedMetric,
    records,
    onOpen,
}: {
    metrics: Metric[];
    selectedMetric: Metric | null;
    records: RecordRow[];
    onOpen: (key: string) => void;
}) {
    return (
        <>
            <section className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                {metrics.map((metric) => (
                    <button
                        key={metric.key}
                        onClick={() => onOpen(metric.key)}
                        className="text-left"
                    >
                        <Card className="h-full gap-4 p-5 transition-colors hover:border-primary/50">
                            <div className="flex items-start justify-between gap-4">
                                <div>
                                    <div className="text-sm text-muted-foreground">
                                        {metric.name}
                                    </div>
                                    <div className="mt-1 text-3xl font-semibold tracking-tight">
                                        {formatValue(metric.value, metric.unit)}
                                    </div>
                                </div>
                                <ChevronRight className="size-4 text-muted-foreground" />
                            </div>
                            <div className="grid grid-cols-2 gap-3 text-xs">
                                <Comparison
                                    label="Periodo anterior"
                                    value={metric.previous_delta_percent}
                                    direction={metric.direction}
                                />
                                <Comparison
                                    label="Objetivo / presupuesto"
                                    value={metric.target_delta_percent}
                                    missing={metric.target === null}
                                    direction={metric.direction}
                                />
                            </div>
                            <div className="flex items-center justify-between border-t pt-3 text-xs">
                                <span className="text-muted-foreground">
                                    Calidad del dato
                                </span>
                                <Badge
                                    variant={
                                        metric.quality.score === null ||
                                        metric.quality.score >= 98
                                            ? 'outline'
                                            : 'destructive'
                                    }
                                >
                                    {metric.quality.score === null
                                        ? '—'
                                        : `${metric.quality.score}%`}{' '}
                                    · {metric.quality.label}
                                </Badge>
                            </div>
                        </Card>
                    </button>
                ))}
            </section>
            {selectedMetric ? (
                <Drilldown metric={selectedMetric} records={records} />
            ) : (
                <div className="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground">
                    Selecciona un KPI para explicar su fórmula y abrir los
                    registros que forman el total.
                </div>
            )}
        </>
    );
}

function Drilldown({
    metric,
    records,
}: {
    metric: Metric;
    records: RecordRow[];
}) {
    return (
        <Card className="gap-0 overflow-hidden py-0">
            <div className="grid gap-4 border-b p-5 lg:grid-cols-[1fr_auto]">
                <div>
                    <div className="flex items-center gap-2">
                        <h2 className="text-lg font-semibold">{metric.name}</h2>
                        <Badge variant="outline">
                            {records.length} registros
                        </Badge>
                    </div>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {metric.formula}
                    </p>
                    <div className="mt-3 flex flex-wrap gap-x-5 gap-y-1 text-xs text-muted-foreground">
                        <span>Propietario: {metric.owner}</span>
                        <span>Fuente: {metric.source}</span>
                        <span>Frecuencia: {metric.frequency}</span>
                    </div>
                </div>
                <div className="text-right">
                    <div className="text-2xl font-semibold">
                        {formatValue(metric.value, metric.unit)}
                    </div>
                    <div className="text-xs text-muted-foreground">
                        Total reconciliado
                    </div>
                </div>
            </div>
            <div className="overflow-x-auto">
                <table className="w-full text-sm">
                    <thead className="bg-muted/50 text-left text-xs text-muted-foreground">
                        <tr>
                            <th className="px-4 py-3">Fecha</th>
                            <th className="px-4 py-3">Referencia</th>
                            <th className="px-4 py-3">Proyecto</th>
                            <th className="px-4 py-3">Estado</th>
                            <th className="px-4 py-3 text-right">Valor</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y">
                        {records.length ? (
                            records.map((row, index) => (
                                <tr key={`${row.url}-${index}`}>
                                    <td className="px-4 py-3">
                                        {formatDate(row.date)}
                                    </td>
                                    <td className="px-4 py-3">
                                        <Link
                                            className="font-medium text-primary hover:underline"
                                            href={row.url}
                                        >
                                            {row.reference || `#${row.id}`}
                                        </Link>
                                    </td>
                                    <td className="px-4 py-3 text-muted-foreground">
                                        {row.project || 'Sin proyecto'}
                                    </td>
                                    <td className="px-4 py-3">
                                        <Badge variant="outline">
                                            {row.status}
                                        </Badge>
                                    </td>
                                    <td className="px-4 py-3 text-right tabular-nums">
                                        {formatValue(
                                            row.value,
                                            metric.unit === 'percent'
                                                ? 'number'
                                                : metric.unit,
                                        )}
                                    </td>
                                </tr>
                            ))
                        ) : (
                            <tr>
                                <td
                                    colSpan={5}
                                    className="p-8 text-center text-muted-foreground"
                                >
                                    No hay registros para estos filtros.
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>
        </Card>
    );
}

function Dictionary({ definitions }: { definitions: Props['dictionary'] }) {
    return (
        <div className="grid gap-3">
            {definitions.map((metric) => (
                <Card key={metric.key} className="gap-3 p-5">
                    <div className="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 className="font-semibold">{metric.name}</h2>
                            <p className="mt-1 text-sm text-muted-foreground">
                                {metric.description}
                            </p>
                        </div>
                        <Badge variant="outline">{metric.frequency}</Badge>
                    </div>
                    <div className="rounded-md bg-muted/50 p-3 text-sm">
                        <span className="font-medium">
                            Fórmula documentada:
                        </span>{' '}
                        {metric.formula}
                    </div>
                    <div className="flex flex-wrap gap-x-6 gap-y-1 text-xs text-muted-foreground">
                        <span>Propietario: {metric.owner}</span>
                        <span>Fuente única: {metric.source}</span>
                        <span>Paneles: {metric.areas.join(', ')}</span>
                    </div>
                </Card>
            ))}
        </div>
    );
}

function Automation(props: Props) {
    const firstMetric = props.metrics[0]?.key ?? '';
    const alert = useForm({
        metric_key: firstMetric,
        operator: '<',
        threshold: '',
        area: props.filters.area,
        period: props.filters.period,
        from: props.filters.from,
        to: props.filters.to,
    });
    const subscription = useForm({
        area: props.filters.area,
        frequency: 'weekly',
        format: 'pdf',
    });

    return (
        <div className="grid gap-6 xl:grid-cols-2">
            <Card className="p-5">
                <div className="flex items-center gap-2">
                    <Bell className="size-4" />
                    <h2 className="font-semibold">Alertas configurables</h2>
                </div>
                <form
                    className="grid gap-3 sm:grid-cols-3"
                    onSubmit={(event) => {
                        event.preventDefault();
                        alert.post('/analytics/alerts', {
                            preserveScroll: true,
                            onSuccess: () => alert.reset('threshold'),
                        });
                    }}
                >
                    <select
                        className={selectClass}
                        value={alert.data.metric_key}
                        onChange={(e) =>
                            alert.setData('metric_key', e.target.value)
                        }
                    >
                        {props.metrics.map((metric) => (
                            <option key={metric.key} value={metric.key}>
                                {metric.name}
                            </option>
                        ))}
                    </select>
                    <div className="flex gap-2">
                        <select
                            className={`${selectClass} w-20`}
                            value={alert.data.operator}
                            onChange={(e) =>
                                alert.setData('operator', e.target.value)
                            }
                        >
                            <option>&lt;</option>
                            <option>&lt;=</option>
                            <option>&gt;</option>
                            <option>&gt;=</option>
                            <option>=</option>
                        </select>
                        <Input
                            required
                            type="number"
                            step="0.01"
                            value={alert.data.threshold}
                            onChange={(e) =>
                                alert.setData('threshold', e.target.value)
                            }
                        />
                    </div>
                    <Button disabled={alert.processing}>Crear alerta</Button>
                </form>
                <div className="divide-y">
                    {props.alerts.map((item) => (
                        <div
                            key={item.id}
                            className="flex items-center justify-between py-3 text-sm"
                        >
                            <span>
                                {
                                    props.dictionary.find(
                                        (metric) =>
                                            metric.key === item.metric_key,
                                    )?.name
                                }{' '}
                                {item.operator} {item.threshold}
                            </span>
                            <div className="flex items-center gap-2">
                                <Badge
                                    variant={
                                        item.triggered
                                            ? 'destructive'
                                            : item.enabled
                                              ? 'outline'
                                              : 'secondary'
                                    }
                                >
                                    {item.triggered
                                        ? 'Umbral alcanzado'
                                        : item.enabled
                                          ? 'Activa'
                                          : 'Pausada'}
                                </Badge>
                                <Button
                                    size="sm"
                                    variant="ghost"
                                    onClick={() =>
                                        router.patch(
                                            `/analytics/alerts/${item.id}`,
                                            { enabled: !item.enabled },
                                            { preserveScroll: true },
                                        )
                                    }
                                >
                                    {item.enabled ? 'Pausar' : 'Activar'}
                                </Button>
                                <Button
                                    size="icon"
                                    variant="ghost"
                                    onClick={() =>
                                        router.delete(
                                            `/analytics/alerts/${item.id}`,
                                            { preserveScroll: true },
                                        )
                                    }
                                >
                                    <Trash2 className="size-4" />
                                </Button>
                            </div>
                        </div>
                    ))}
                </div>
            </Card>
            <Card className="p-5">
                <div className="flex items-center gap-2">
                    <MailCheck className="size-4" />
                    <h2 className="font-semibold">Suscripción a informes</h2>
                </div>
                <p className="text-sm text-muted-foreground">
                    Recibe un informe del panel con los mismos filtros,
                    definiciones y totales.
                </p>
                <form
                    className="grid gap-3 sm:grid-cols-3"
                    onSubmit={(event) => {
                        event.preventDefault();
                        subscription.post('/analytics/subscriptions', {
                            preserveScroll: true,
                        });
                    }}
                >
                    <select
                        className={selectClass}
                        value={subscription.data.area}
                        onChange={(e) =>
                            subscription.setData('area', e.target.value)
                        }
                    >
                        {props.areas.map((area) => (
                            <option key={area.key} value={area.key}>
                                {area.name}
                            </option>
                        ))}
                    </select>
                    <select
                        className={selectClass}
                        value={subscription.data.frequency}
                        onChange={(e) =>
                            subscription.setData('frequency', e.target.value)
                        }
                    >
                        <option value="daily">Diario</option>
                        <option value="weekly">Semanal</option>
                        <option value="monthly">Mensual</option>
                    </select>
                    <div className="flex gap-2">
                        <select
                            className={selectClass}
                            value={subscription.data.format}
                            onChange={(e) =>
                                subscription.setData('format', e.target.value)
                            }
                        >
                            <option value="pdf">PDF</option>
                            <option value="excel">Excel</option>
                        </select>
                        <Button disabled={subscription.processing}>
                            Guardar
                        </Button>
                    </div>
                </form>
                <div className="divide-y">
                    {props.subscriptions.map((item) => (
                        <div
                            key={item.id}
                            className="flex items-center justify-between py-3 text-sm"
                        >
                            <span>
                                {
                                    props.areas.find(
                                        (area) => area.key === item.area,
                                    )?.name
                                }{' '}
                                · {item.frequency} · {item.format.toUpperCase()}
                            </span>
                            <Button
                                size="icon"
                                variant="ghost"
                                onClick={() =>
                                    router.delete(
                                        `/analytics/subscriptions/${item.id}`,
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                <Trash2 className="size-4" />
                            </Button>
                        </div>
                    ))}
                </div>
                <div className="mt-auto flex items-center gap-2 rounded-md bg-muted/50 p-3 text-xs text-muted-foreground">
                    <ShieldCheck className="size-4" />
                    Los informes solo incluyen datos permitidos para el
                    destinatario.
                </div>
            </Card>
        </div>
    );
}

function FilterField({
    label,
    children,
}: {
    label: string;
    children: ReactNode;
}) {
    return (
        <label className="grid gap-1 text-xs font-medium text-muted-foreground">
            <span>{label}</span>
            {children}
        </label>
    );
}
function SectionButton({
    active,
    onClick,
    children,
}: {
    active: boolean;
    onClick: () => void;
    children: ReactNode;
}) {
    return (
        <button
            onClick={onClick}
            className={`flex items-center gap-2 border-b-2 px-3 py-2 text-sm ${active ? 'border-primary font-medium text-foreground' : 'border-transparent text-muted-foreground'}`}
        >
            {children}
        </button>
    );
}
function Comparison({
    label,
    value,
    missing = false,
    direction,
}: {
    label: string;
    value: number | null;
    missing?: boolean;
    direction: Metric['direction'];
}) {
    const favorable =
        value === null || value === 0
            ? null
            : direction === 'up'
              ? value > 0
              : value < 0;

    return (
        <div>
            <div className="text-muted-foreground">{label}</div>
            <div
                className={`mt-1 font-medium ${favorable === true ? 'text-emerald-600 dark:text-emerald-400' : favorable === false ? 'text-destructive' : ''}`}
            >
                {missing
                    ? 'Sin objetivo'
                    : value === null
                      ? 'Sin base'
                      : `${value > 0 ? '+' : ''}${value}%`}
            </div>
        </div>
    );
}
const selectClass =
    'h-9 w-full rounded-md border border-input bg-background px-3 text-sm outline-none focus:border-ring focus:ring-2 focus:ring-ring/20';
const formatValue = (value: number, unit: Metric['unit']) =>
    unit === 'currency'
        ? new Intl.NumberFormat('es-ES', {
              style: 'currency',
              currency: 'EUR',
              maximumFractionDigits: 0,
          }).format(value)
        : `${new Intl.NumberFormat('es-ES', { maximumFractionDigits: 1 }).format(value)}${unit === 'percent' ? '%' : ''}`;
const formatDate = (date: string | null) =>
    date
        ? new Intl.DateTimeFormat('es-ES').format(new Date(date))
        : 'Sin fecha';
const exportUrl = (format: string, filter: Filter) =>
    `/analytics/export/${format}?${new URLSearchParams(
        Object.entries(filter)
            .filter(([, value]) => value !== null)
            .map(([key, value]) => [key, String(value)]),
    ).toString()}`;

AnalyticsIndex.layout = {
    breadcrumbs: [{ title: 'Analítica', href: '/analytics' }],
};

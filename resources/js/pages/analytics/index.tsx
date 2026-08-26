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
    area: string | null;
    channel: 'database' | 'mail';
    enabled: boolean;
    current_value: number | null;
    triggered: boolean;
    last_evaluated_at: string | null;
};

type Subscription = {
    id: number;
    area: string;
    frequency: string;
    format: string;
    next_send_at: string | null;
};

type ConversionRow = {
    key: string;
    label: string;
    closed: number;
    won: number;
    lost: number;
    win_rate: number;
    won_value: number;
};

type CommercialConversions = {
    period: { from: string; to: string };
    summary: {
        closed: number;
        won: number;
        lost: number;
        win_rate: number;
        won_value: number;
        attribution_rate: number;
    };
    by_source: ConversionRow[];
    by_commercial: ConversionRow[];
    trend: Array<{
        period: string;
        closed: number;
        won: number;
        win_rate: number;
    }>;
};

type MarginSummary = {
    projects: number;
    planned_revenue: number;
    planned_margin: number;
    planned_margin_percent: number | null;
    committed_margin: number;
    committed_margin_percent: number | null;
    actual_revenue: number;
    actual_margin: number;
    actual_margin_percent: number | null;
    outstanding_cash: number;
    at_risk: number;
    quality_score: number | null;
};

type ProjectMargin = {
    installation_id: number;
    reference: string;
    service_type: string;
    status: string;
    planned_revenue: number;
    planned_margin: number;
    committed_margin: number;
    actual_revenue: number;
    actual_margin: number;
    outstanding_cash: number;
    quality: { score: number; issues: string[] };
    sources: { url: string };
};

type ProjectMargins = {
    summary: MarginSummary;
    by_line: Array<
        MarginSummary & {
            service_type: string;
            label: string;
        }
    >;
    projects: ProjectMargin[];
    definitions: {
        planned: string;
        committed: string;
        actual: string;
    };
};

type RecurringHealth = {
    summary: {
        installed_base: number;
        active_contracts: number;
        attached_installations: number;
        attach_rate: number;
        annual_recurring_value: number;
        renewals_due: number;
        renewals_prepared: number;
        renewal_conversion: number;
        response_sla_attainment: number;
        resolution_sla_attainment: number;
        first_time_fix_rate: number;
        actual_service_cost: number | null;
    };
    by_line: {
        service_type: string;
        installed_base: number;
        active_contracts: number;
        attach_rate: number;
        annual_recurring_value: number;
    }[];
    definitions: Record<string, string>;
};
type RecurringEconomics = {
    summary: {
        cycles: number;
        planned_revenue: number;
        planned_margin: number;
        settled_revenue: number;
        actual_cost: number;
        actual_margin: number;
        unsettled_cycles: number;
    };
    cycles: {
        id: number;
        plan: string;
        plan_version: number;
        installation: string | null;
        business_line: string | null;
        period_starts_on: string;
        period_ends_on: string;
        planned_margin: number;
        actual_margin: number;
        tickets: number;
    }[];
    definition: string;
};

type Props = {
    filters: Filter;
    generatedAt: string;
    areas: Array<{ key: string; name: string }>;
    metrics: Metric[];
    dictionary: Omit<Metric, 'value' | 'previous' | 'quality'>[];
    selectedMetric: Metric | null;
    records: RecordRow[];
    recordsHasMore: boolean;
    commercials: Array<{ id: number; name: string }>;
    projects: Array<{
        id: number;
        project_reference: string | null;
        site_name: string | null;
    }>;
    alerts: Alert[];
    subscriptions: Subscription[];
    commercialConversions: CommercialConversions | null;
    projectMargins: ProjectMargins | null;
    recurringHealth: RecurringHealth | null;
    recurringEconomics: RecurringEconomics | null;
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
                    description={`Corte ${formatDate(props.filters.to)} · calculado ${formatDateTime(props.generatedAt)} · trazable hasta el registro de origen.`}
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
                        recordsHasMore={props.recordsHasMore}
                        conversions={props.commercialConversions}
                        projectMargins={props.projectMargins}
                        recurringHealth={props.recurringHealth}
                        recurringEconomics={props.recurringEconomics}
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
    recordsHasMore,
    conversions,
    projectMargins,
    recurringHealth,
    recurringEconomics,
    onOpen,
}: {
    metrics: Metric[];
    selectedMetric: Metric | null;
    records: RecordRow[];
    recordsHasMore: boolean;
    conversions: CommercialConversions | null;
    projectMargins: ProjectMargins | null;
    recurringHealth: RecurringHealth | null;
    recurringEconomics: RecurringEconomics | null;
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
            {recurringHealth ? (
                <RecurringHealthPanel health={recurringHealth} />
            ) : null}
            {recurringEconomics ? (
                <RecurringEconomicsPanel economics={recurringEconomics} />
            ) : null}
            {projectMargins ? (
                <ProjectMarginPanel analysis={projectMargins} />
            ) : null}
            {conversions ? (
                <CommercialConversionPanel conversions={conversions} />
            ) : null}
            {selectedMetric ? (
                <Drilldown
                    metric={selectedMetric}
                    records={records}
                    recordsHasMore={recordsHasMore}
                />
            ) : (
                <div className="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground">
                    Selecciona un KPI para explicar su fórmula y abrir los
                    registros que forman el total.
                </div>
            )}
        </>
    );
}

function RecurringHealthPanel({ health }: { health: RecurringHealth }) {
    return (
        <section className="grid gap-4" aria-labelledby="recurring-title">
            <div>
                <h2 id="recurring-title" className="text-lg font-semibold">
                    Salud de la cartera recurrente
                </h2>
                <p className="mt-1 text-sm text-muted-foreground">
                    Contratos activos, renovaciones y cumplimiento operativo
                    calculados desde instalaciones, planes y tickets enlazados.
                </p>
            </div>
            <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-6">
                <ConversionKpi
                    label="Attach rate"
                    value={`${health.summary.attach_rate}%`}
                />
                <ConversionKpi
                    label="Contratos activos"
                    value={`${health.summary.active_contracts}`}
                />
                <ConversionKpi
                    label="ARR recurrente"
                    value={formatValue(
                        health.summary.annual_recurring_value,
                        'currency',
                    )}
                />
                <ConversionKpi
                    label="Renovación ganada"
                    value={`${health.summary.renewal_conversion}%`}
                />
                <ConversionKpi
                    label="SLA resolución"
                    value={`${health.summary.resolution_sla_attainment}%`}
                />
                <ConversionKpi
                    label="First-time fix"
                    value={`${health.summary.first_time_fix_rate}%`}
                />
            </div>
            <Card className="gap-0 overflow-hidden py-0">
                <div className="overflow-x-auto">
                    <table className="w-full min-w-[48rem] text-sm">
                        <thead className="bg-muted/50 text-left text-xs text-muted-foreground">
                            <tr>
                                <th className="px-4 py-3">Línea</th>
                                <th className="px-3 py-3 text-right">
                                    Base instalada
                                </th>
                                <th className="px-3 py-3 text-right">
                                    Contratos
                                </th>
                                <th className="px-3 py-3 text-right">
                                    Attach rate
                                </th>
                                <th className="px-4 py-3 text-right">ARR</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {health.by_line.map((line) => (
                                <tr key={line.service_type}>
                                    <td className="px-4 py-3 font-medium">
                                        {line.service_type.replaceAll('_', ' ')}
                                    </td>
                                    <td className="px-3 py-3 text-right tabular-nums">
                                        {line.installed_base}
                                    </td>
                                    <td className="px-3 py-3 text-right tabular-nums">
                                        {line.active_contracts}
                                    </td>
                                    <td className="px-3 py-3 text-right tabular-nums">
                                        {line.attach_rate}%
                                    </td>
                                    <td className="px-4 py-3 text-right tabular-nums">
                                        {formatValue(
                                            line.annual_recurring_value,
                                            'currency',
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </Card>
            <div className="grid gap-3 rounded-lg border p-4 text-sm sm:grid-cols-3">
                <div>
                    <span className="text-muted-foreground">
                        Renovaciones preparadas
                    </span>
                    <div className="font-semibold">
                        {health.summary.renewals_prepared}/
                        {health.summary.renewals_due}
                    </div>
                </div>
                <div>
                    <span className="text-muted-foreground">
                        SLA de respuesta
                    </span>
                    <div className="font-semibold">
                        {health.summary.response_sla_attainment}%
                    </div>
                </div>
                <div>
                    <span className="text-muted-foreground">
                        Coste real de servicio
                    </span>
                    <div className="font-semibold">
                        {health.summary.actual_service_cost === null
                            ? 'Restringido'
                            : formatValue(
                                  health.summary.actual_service_cost,
                                  'currency',
                              )}
                    </div>
                </div>
            </div>
            <details className="rounded-lg border px-4 py-3 text-sm">
                <summary className="cursor-pointer font-medium">
                    Definiciones y población
                </summary>
                <div className="mt-3 grid gap-2 text-xs text-muted-foreground">
                    {Object.entries(health.definitions).map(([key, value]) => (
                        <p key={key}>
                            <strong>{key.replaceAll('_', ' ')}:</strong> {value}
                        </p>
                    ))}
                </div>
            </details>
        </section>
    );
}

function RecurringEconomicsPanel({
    economics,
}: {
    economics: RecurringEconomics;
}) {
    return (
        <section className="grid gap-4" aria-labelledby="service-margin-title">
            <div>
                <h2 id="service-margin-title" className="text-lg font-semibold">
                    Margen por ciclo de servicio
                </h2>
                <p className="mt-1 text-sm text-muted-foreground">
                    {economics.definition}
                </p>
            </div>
            <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                <ConversionKpi
                    label="Ciclos aprobados"
                    value={`${economics.summary.cycles}`}
                />
                <ConversionKpi
                    label="Ingresos previstos"
                    value={formatValue(
                        economics.summary.planned_revenue,
                        'currency',
                    )}
                />
                <ConversionKpi
                    label="Margen previsto"
                    value={formatValue(
                        economics.summary.planned_margin,
                        'currency',
                    )}
                />
                <ConversionKpi
                    label="Margen real"
                    value={formatValue(
                        economics.summary.actual_margin,
                        'currency',
                    )}
                />
                <ConversionKpi
                    label="Pendientes de liquidar"
                    value={`${economics.summary.unsettled_cycles}`}
                />
            </div>
            {economics.cycles.length > 0 ? (
                <Card className="gap-0 overflow-hidden py-0">
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[44rem] text-sm">
                            <thead className="bg-muted/50 text-left text-xs text-muted-foreground">
                                <tr>
                                    <th className="px-4 py-3">
                                        Ciclo contractual
                                    </th>
                                    <th className="px-3 py-3 text-right">
                                        Margen previsto
                                    </th>
                                    <th className="px-3 py-3 text-right">
                                        Margen real
                                    </th>
                                    <th className="px-4 py-3 text-right">
                                        Tickets
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {economics.cycles.map((cycle) => (
                                    <tr key={cycle.id}>
                                        <td className="px-4 py-3">
                                            <div className="font-medium">
                                                {cycle.plan} · v
                                                {cycle.plan_version}
                                            </div>
                                            <div className="text-xs text-muted-foreground">
                                                {cycle.installation ||
                                                    'Instalación'}{' '}
                                                · {cycle.period_starts_on}–
                                                {cycle.period_ends_on}
                                            </div>
                                        </td>
                                        <td className="px-3 py-3 text-right tabular-nums">
                                            {formatValue(
                                                cycle.planned_margin,
                                                'currency',
                                            )}
                                        </td>
                                        <td
                                            className={`px-3 py-3 text-right font-medium tabular-nums ${cycle.actual_margin < 0 ? 'text-destructive' : ''}`}
                                        >
                                            {formatValue(
                                                cycle.actual_margin,
                                                'currency',
                                            )}
                                        </td>
                                        <td className="px-4 py-3 text-right tabular-nums">
                                            {cycle.tickets}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </Card>
            ) : (
                <div className="rounded-lg border border-dashed p-6 text-sm text-muted-foreground">
                    Todavía no hay ciclos de servicio con capacidad aprobada.
                </div>
            )}
        </section>
    );
}

function ProjectMarginPanel({ analysis }: { analysis: ProjectMargins }) {
    return (
        <section className="grid gap-4" aria-labelledby="margin-title">
            <div>
                <h2 id="margin-title" className="text-lg font-semibold">
                    Rentabilidad por línea de negocio
                </h2>
                <p className="mt-1 text-sm text-muted-foreground">
                    Una definición común para margen previsto, comprometido y
                    real. Cada proyecto enlaza su expediente y muestra la
                    calidad de los datos utilizados.
                </p>
            </div>
            <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                <ConversionKpi
                    label="Margen previsto"
                    value={marginValue(
                        analysis.summary.planned_margin,
                        analysis.summary.planned_margin_percent,
                    )}
                />
                <ConversionKpi
                    label="Margen comprometido"
                    value={marginValue(
                        analysis.summary.committed_margin,
                        analysis.summary.committed_margin_percent,
                    )}
                />
                <ConversionKpi
                    label="Margen real"
                    value={marginValue(
                        analysis.summary.actual_margin,
                        analysis.summary.actual_margin_percent,
                    )}
                />
                <ConversionKpi
                    label="Pendiente de cobro"
                    value={formatValue(
                        analysis.summary.outstanding_cash,
                        'currency',
                    )}
                />
                <ConversionKpi
                    label="Proyectos en riesgo"
                    value={`${analysis.summary.at_risk}/${analysis.summary.projects}`}
                />
            </div>
            <Card className="gap-0 overflow-hidden py-0">
                <div className="overflow-x-auto">
                    <table className="w-full min-w-[54rem] text-sm">
                        <thead className="bg-muted/50 text-left text-xs text-muted-foreground">
                            <tr>
                                <th className="px-4 py-3">Línea</th>
                                <th className="px-3 py-3 text-right">
                                    Proyectos
                                </th>
                                <th className="px-3 py-3 text-right">
                                    Previsto
                                </th>
                                <th className="px-3 py-3 text-right">
                                    Comprometido
                                </th>
                                <th className="px-3 py-3 text-right">Real</th>
                                <th className="px-4 py-3 text-right">
                                    Calidad
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {analysis.by_line.map((line) => (
                                <tr key={line.service_type}>
                                    <td className="px-4 py-3 font-medium">
                                        {line.label}
                                    </td>
                                    <td className="px-3 py-3 text-right tabular-nums">
                                        {line.projects}
                                    </td>
                                    <td className="px-3 py-3 text-right tabular-nums">
                                        {marginValue(
                                            line.planned_margin,
                                            line.planned_margin_percent,
                                        )}
                                    </td>
                                    <td className="px-3 py-3 text-right tabular-nums">
                                        {marginValue(
                                            line.committed_margin,
                                            line.committed_margin_percent,
                                        )}
                                    </td>
                                    <td className="px-3 py-3 text-right tabular-nums">
                                        {marginValue(
                                            line.actual_margin,
                                            line.actual_margin_percent,
                                        )}
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        {line.quality_score === null
                                            ? '—'
                                            : `${line.quality_score}%`}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </Card>
            <details className="rounded-lg border bg-card">
                <summary className="cursor-pointer px-4 py-3 font-medium">
                    Proyectos y datos de origen
                </summary>
                <div className="overflow-x-auto border-t">
                    <table className="w-full min-w-[58rem] text-sm">
                        <thead className="text-left text-xs text-muted-foreground">
                            <tr>
                                <th className="px-4 py-3">Proyecto</th>
                                <th className="px-3 py-3 text-right">
                                    Previsto
                                </th>
                                <th className="px-3 py-3 text-right">
                                    Comprometido
                                </th>
                                <th className="px-3 py-3 text-right">Real</th>
                                <th className="px-4 py-3">Calidad</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {analysis.projects.map((project) => (
                                <tr key={project.installation_id}>
                                    <td className="px-4 py-3">
                                        <Link
                                            href={project.sources.url}
                                            className="font-medium underline underline-offset-2"
                                        >
                                            {project.reference}
                                        </Link>
                                        <div className="text-xs text-muted-foreground">
                                            {project.service_type} ·{' '}
                                            {project.status}
                                        </div>
                                    </td>
                                    <td className="px-3 py-3 text-right tabular-nums">
                                        {formatValue(
                                            project.planned_margin,
                                            'currency',
                                        )}
                                    </td>
                                    <td className="px-3 py-3 text-right tabular-nums">
                                        {formatValue(
                                            project.committed_margin,
                                            'currency',
                                        )}
                                    </td>
                                    <td className="px-3 py-3 text-right tabular-nums">
                                        {formatValue(
                                            project.actual_margin,
                                            'currency',
                                        )}
                                    </td>
                                    <td className="px-4 py-3">
                                        <Badge
                                            variant={
                                                project.quality.score >= 80
                                                    ? 'outline'
                                                    : 'destructive'
                                            }
                                        >
                                            {project.quality.score}%
                                        </Badge>
                                        {project.quality.issues.length ? (
                                            <div className="mt-1 max-w-sm text-xs text-muted-foreground">
                                                {project.quality.issues.join(
                                                    ' · ',
                                                )}
                                            </div>
                                        ) : null}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    {!analysis.projects.length ? (
                        <p className="p-5 text-sm text-muted-foreground">
                            No hay proyectos activos en el periodo seleccionado.
                        </p>
                    ) : null}
                </div>
            </details>
            <div className="grid gap-2 text-xs text-muted-foreground md:grid-cols-3">
                <p>
                    <strong>Previsto:</strong> {analysis.definitions.planned}
                </p>
                <p>
                    <strong>Comprometido:</strong>{' '}
                    {analysis.definitions.committed}
                </p>
                <p>
                    <strong>Real:</strong> {analysis.definitions.actual}
                </p>
            </div>
        </section>
    );
}

function marginValue(value: number, percent: number | null): string {
    return `${formatValue(value, 'currency')}${percent === null ? '' : ` · ${percent}%`}`;
}

function CommercialConversionPanel({
    conversions,
}: {
    conversions: CommercialConversions;
}) {
    const summary = conversions.summary;

    return (
        <section className="grid gap-4" aria-labelledby="conversion-title">
            <div>
                <h2 id="conversion-title" className="text-lg font-semibold">
                    Conversión por atribución
                </h2>
                <p className="mt-1 text-sm text-muted-foreground">
                    Resultados cerrados entre{' '}
                    {formatDate(conversions.period.from)} y{' '}
                    {formatDate(conversions.period.to)}, calculados desde el
                    historial real de etapas.
                </p>
            </div>
            <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <ConversionKpi
                    label="Cerradas"
                    value={String(summary.closed)}
                />
                <ConversionKpi label="Ganadas" value={String(summary.won)} />
                <ConversionKpi
                    label="Conversión"
                    value={`${summary.win_rate}%`}
                />
                <ConversionKpi
                    label="Valor ganado"
                    value={formatValue(summary.won_value, 'currency')}
                />
            </div>
            {summary.attribution_rate < 100 && summary.closed > 0 ? (
                <div className="rounded-lg border border-amber-300/60 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:bg-amber-950/30 dark:text-amber-200">
                    El {summary.attribution_rate}% de los cierres tiene un
                    origen informado. Completa el origen para comparar canales
                    con precisión.
                </div>
            ) : null}
            <div className="grid gap-4 xl:grid-cols-2">
                <ConversionTable
                    title="Por origen"
                    rows={conversions.by_source}
                />
                <ConversionTable
                    title="Por comercial"
                    rows={conversions.by_commercial}
                />
            </div>
            <Card className="p-5">
                <h3 className="font-semibold">Evolución mensual</h3>
                <div className="mt-3 grid gap-3">
                    {conversions.trend.map((point) => (
                        <div
                            key={point.period}
                            className="grid grid-cols-[5rem_1fr_auto] items-center gap-3 text-sm"
                        >
                            <span className="text-muted-foreground">
                                {formatMonth(point.period)}
                            </span>
                            <div
                                className="h-2 overflow-hidden rounded-full bg-muted"
                                aria-hidden="true"
                            >
                                <div
                                    className="h-full rounded-full bg-primary"
                                    style={{ width: `${point.win_rate}%` }}
                                />
                            </div>
                            <span className="w-20 text-right tabular-nums">
                                {point.win_rate}%{' '}
                                <span className="text-xs text-muted-foreground">
                                    ({point.won}/{point.closed})
                                </span>
                            </span>
                        </div>
                    ))}
                    {!conversions.trend.length ? (
                        <p className="text-sm text-muted-foreground">
                            No hay cierres en este periodo.
                        </p>
                    ) : null}
                </div>
            </Card>
        </section>
    );
}

function ConversionKpi({ label, value }: { label: string; value: string }) {
    return (
        <Card className="gap-1 p-4">
            <span className="text-xs text-muted-foreground">{label}</span>
            <strong className="text-2xl tabular-nums">{value}</strong>
        </Card>
    );
}

function ConversionTable({
    title,
    rows,
}: {
    title: string;
    rows: ConversionRow[];
}) {
    return (
        <Card className="gap-0 overflow-hidden py-0">
            <h3 className="border-b p-4 font-semibold">{title}</h3>
            <div className="overflow-x-auto">
                <table className="w-full text-sm">
                    <thead className="bg-muted/50 text-left text-xs text-muted-foreground">
                        <tr>
                            <th className="px-4 py-3">Atribución</th>
                            <th className="px-3 py-3 text-right">Cierres</th>
                            <th className="px-3 py-3 text-right">Conversión</th>
                            <th className="px-4 py-3 text-right">
                                Valor ganado
                            </th>
                        </tr>
                    </thead>
                    <tbody className="divide-y">
                        {rows.map((row) => (
                            <tr key={row.key}>
                                <td className="px-4 py-3 font-medium">
                                    {row.label}
                                </td>
                                <td className="px-3 py-3 text-right tabular-nums">
                                    {row.won}/{row.closed}
                                </td>
                                <td className="px-3 py-3 text-right tabular-nums">
                                    {row.win_rate}%
                                </td>
                                <td className="px-4 py-3 text-right tabular-nums">
                                    {formatValue(row.won_value, 'currency')}
                                </td>
                            </tr>
                        ))}
                        {!rows.length ? (
                            <tr>
                                <td
                                    colSpan={4}
                                    className="p-6 text-center text-muted-foreground"
                                >
                                    Sin cierres para este desglose.
                                </td>
                            </tr>
                        ) : null}
                    </tbody>
                </table>
            </div>
        </Card>
    );
}

function Drilldown({
    metric,
    records,
    recordsHasMore,
}: {
    metric: Metric;
    records: RecordRow[];
    recordsHasMore: boolean;
}) {
    return (
        <Card className="gap-0 overflow-hidden py-0">
            <div className="grid gap-4 border-b p-5 lg:grid-cols-[1fr_auto]">
                <div>
                    <div className="flex items-center gap-2">
                        <h2 className="text-lg font-semibold">{metric.name}</h2>
                        <Badge variant="outline">
                            {records.length} registros mostrados
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
            {recordsHasMore ? (
                <div className="border-b bg-amber-50 px-5 py-3 text-xs text-amber-900 dark:bg-amber-950/30 dark:text-amber-200">
                    Hay más registros. Acota el periodo o el proyecto para
                    mantener el análisis rápido y obtener un detalle completo.
                </div>
            ) : null}
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
        channel: 'database',
    });
    const subscription = useForm({
        area: props.filters.area,
        frequency: 'weekly',
        format: 'pdf',
        period: props.filters.period,
        from: props.filters.from,
        to: props.filters.to,
        commercial_id: props.filters.commercial_id,
        project_id: props.filters.project_id,
    });

    return (
        <div className="grid gap-6 xl:grid-cols-2">
            <Card className="p-5">
                <div className="flex items-center gap-2">
                    <Bell className="size-4" />
                    <h2 className="font-semibold">Alertas configurables</h2>
                </div>
                <form
                    className="grid gap-3 sm:grid-cols-4"
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
                    <select
                        className={selectClass}
                        value={alert.data.channel}
                        onChange={(e) =>
                            alert.setData(
                                'channel',
                                e.target.value as 'database' | 'mail',
                            )
                        }
                        aria-label="Canal de notificación"
                    >
                        <option value="database">En la aplicación</option>
                        <option value="mail">Aplicación y correo</option>
                    </select>
                    <Button disabled={alert.processing}>Crear alerta</Button>
                </form>
                <div className="divide-y">
                    {props.alerts.map((item) => (
                        <div
                            key={item.id}
                            className="flex items-center justify-between py-3 text-sm"
                        >
                            <div>
                                <span>
                                    {
                                        props.dictionary.find(
                                            (metric) =>
                                                metric.key === item.metric_key,
                                        )?.name
                                    }{' '}
                                    {item.operator} {item.threshold}
                                </span>
                                <div className="text-xs text-muted-foreground">
                                    {item.area
                                        ? props.areas.find(
                                              (area) => area.key === item.area,
                                          )?.name
                                        : 'Panel anterior'}
                                    {' · '}
                                    {item.current_value === null
                                        ? 'Pendiente de evaluación'
                                        : `Último valor: ${item.current_value}`}
                                </div>
                            </div>
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
                                · {frequencyLabel(item.frequency)} ·{' '}
                                {item.format.toUpperCase()}
                                {item.next_send_at
                                    ? ` · Próximo ${formatDate(item.next_send_at)}`
                                    : ''}
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
const formatDateTime = (date: string) =>
    new Intl.DateTimeFormat('es-ES', {
        dateStyle: 'short',
        timeStyle: 'short',
    }).format(new Date(date));
const formatMonth = (period: string) =>
    new Intl.DateTimeFormat('es-ES', {
        month: 'short',
        year: 'numeric',
    }).format(new Date(`${period}-01T12:00:00`));
const frequencyLabel = (frequency: string) =>
    ({ daily: 'Diario', weekly: 'Semanal', monthly: 'Mensual' })[frequency] ??
    frequency;
const exportUrl = (format: string, filter: Filter) =>
    `/analytics/export/${format}?${new URLSearchParams(
        Object.entries(filter)
            .filter(([, value]) => value !== null)
            .map(([key, value]) => [key, String(value)]),
    ).toString()}`;

AnalyticsIndex.layout = {
    breadcrumbs: [{ title: 'Analítica', href: '/analytics' }],
};

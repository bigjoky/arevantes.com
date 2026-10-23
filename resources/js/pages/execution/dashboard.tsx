import { Head, Link, router } from '@inertiajs/react';
import {
    Building2,
    CheckCircle2,
    CircleDollarSign,
    ClipboardList,
    Cog,
    Wrench,
} from 'lucide-react';
import { useCustomerNavigation } from '@/components/customers/use-customer-navigation';
import { PageHeader, PageShell } from '@/components/page-shell';
import { Distribution, PowerChart } from '@/components/projects/project-charts';
import {
    date,
    money,
    phaseColors,
    phaseLabels,
    power,
    projectUrl,
} from '@/components/projects/types';
import type { Filters, Permissions } from '@/components/projects/types';
import { Skeleton } from '@/components/ui/skeleton';
import { useLocale } from '@/hooks/use-locale';
import { translate } from '@/i18n';
import { serviceLabel } from '@/lib/business-lines';
type Props = {
    filters: Filters;
    period: { from: string; to: string };
    total: number;
    groups: Record<string, { total: number }>;
    value: string | null;
    valueMissingCount: number | null;
    power: number;
    powerSeries: { month: string; power: number }[];
    powerDatedCount: number;
    types: { type: string | null; total: number }[];
    upcoming: {
        title: string;
        reference: string;
        date: string;
        href: string;
    }[];
    permissions: Permissions;
};
const afterSalesKey = 'after_sales';
const icons = {
    preparation: ClipboardList,
    execution: Wrench,
    completed: CheckCircle2,
    after_sales: Cog,
};
const mainPhases = ['preparation', 'execution', 'completed', 'after_sales'];
const periods: [string, string][] = [
    ['last12', 'Últimos 12 meses'],
    ['month', 'Este mes'],
    ['quarter', 'Este trimestre'],
    ['year', 'Este año'],
];
const typeColors = ['#699dea', '#ffb843', '#60bb9e', '#9760d6', '#91a0b8'];
export default function ProjectDashboard(props: Props) {
    const {
        filters,
        period,
        total,
        groups,
        value,
        valueMissingCount,
        power: capacity,
        powerSeries,
        powerDatedCount,
        types,
        upcoming,
        permissions,
    } = props;
    const { locale } = useLocale();
    const loading = useCustomerNavigation();
    const scope = { created_from: period.from, created_to: period.to };
    const states = Object.entries(phaseLabels)
        .filter(([key]) => key !== afterSalesKey || permissions.planning)
        .map(([key, label]) => ({
            key,
            label,
            total: groups[key]?.total ?? 0,
            color: phaseColors[key],
            href: projectUrl({ ...scope, phase: key }),
        }));

    return (
        <>
            <Head title="Proyectos" />
            <PageShell className="projects-workspace">
                <PageHeader
                    title="Proyectos"
                    description="Controla la ejecución de tus proyectos desde el presupuesto hasta la postventa."
                    breadcrumbs={[{ title: 'Proyectos', href: '/execution' }]}
                    secondaryNavigation={null}
                    actions={
                        <label className="projects-filter">
                            <span>Periodo</span>
                            <select
                                value={filters.period ?? 'last12'}
                                onChange={(e) =>
                                    router.get('/execution', {
                                        ...filters,
                                        period: e.target.value,
                                    })
                                }
                            >
                                {periods.map(([key, label]) => (
                                    <option key={key} value={key}>
                                        {label}
                                    </option>
                                ))}
                            </select>
                        </label>
                    }
                />
                <p className="projects-source">
                    Proyectos creados del {date(period.from)} al{' '}
                    {date(period.to)} · Estado actual
                </p>
                <div className="projects-kpis">
                    <Link href={projectUrl(scope)}>
                        <Building2 />
                        <span>Proyectos totales</span>
                        <strong>{total}</strong>
                    </Link>
                    {mainPhases
                        .filter(
                            (key) =>
                                key !== afterSalesKey || permissions.planning,
                        )
                        .map((key) => {
                            const Icon = icons[key as keyof typeof icons];

                            return (
                                <Link
                                    key={key}
                                    href={projectUrl({ ...scope, phase: key })}
                                >
                                    <Icon />
                                    <span>{phaseLabels[key]}</span>
                                    <strong>{groups[key]?.total ?? 0}</strong>
                                    <small>
                                        {total
                                            ? Math.round(
                                                  ((groups[key]?.total ?? 0) /
                                                      total) *
                                                      100,
                                              )
                                            : 0}
                                        % del periodo
                                    </small>
                                </Link>
                            );
                        })}
                    {permissions.finance && (
                        <div>
                            <CircleDollarSign />
                            <span>Valor total</span>
                            <strong>{money(value ?? 0)}</strong>
                            <small>Presupuesto aceptado o valor estimado</small>
                            {!!valueMissingCount && (
                                <small>
                                    {valueMissingCount} proyectos sin valor
                                    registrado
                                </small>
                            )}
                        </div>
                    )}
                </div>
                {loading && (
                    <div className="projects-dashboard-skeleton">
                        <Skeleton className="h-40 w-full" />
                        <Skeleton className="h-40 w-full" />
                    </div>
                )}
                {!total && (
                    <p className="projects-empty">
                        Todavía no hay proyectos en este periodo.
                    </p>
                )}
                <div className="projects-dashboard-grid">
                    <section className="projects-panel">
                        <h2>Estado de proyectos</h2>
                        <Distribution data={states} total={total} />
                    </section>
                    <section className="projects-panel">
                        <header>
                            <h2>Potencia en ejecución</h2>
                            <strong>{power(capacity)}</strong>
                        </header>
                        <PowerChart
                            data={powerSeries}
                            hasDates={powerDatedCount > 0}
                        />
                        <p className="projects-source">
                            FV en ejecución, agrupada por inicio programado. Sin
                            fecha, la potencia solo participa en el total.
                        </p>
                    </section>
                    <section className="projects-panel">
                        <h2>Tipo de instalación</h2>
                        <Distribution
                            total={total}
                            data={types.map((t, i) => ({
                                key: t.type ?? 'unknown',
                                label: t.type
                                    ? translate(serviceLabel(t.type), locale)
                                    : translate('Sin clasificar', locale),
                                total: Number(t.total),
                                color: typeColors[i % typeColors.length],
                            }))}
                        />
                    </section>
                    <section className="projects-panel">
                        <h2>Próximas fechas clave</h2>
                        {upcoming.length ? (
                            upcoming.map((event, index) => (
                                <Link
                                    className="projects-event"
                                    href={event.href}
                                    key={event.href + event.date + index}
                                >
                                    <strong>{event.title}</strong>
                                    <span>{event.reference}</span>
                                    <time>{date(event.date)}</time>
                                </Link>
                            ))
                        ) : (
                            <p className="projects-empty">
                                No hay próximas fechas registradas.
                            </p>
                        )}
                    </section>
                </div>
                <div className="projects-area-links">
                    <Link href={projectUrl(scope)}>Ver proyectos</Link>
                    <Link href="/installations">Ver instalaciones</Link>
                    {permissions.planning && (
                        <Link href="/after-sales?view=installations">
                            Ver Postventa
                        </Link>
                    )}
                </div>
            </PageShell>
        </>
    );
}

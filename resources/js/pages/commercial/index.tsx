import { Head, Link, router, usePage } from '@inertiajs/react';
import { ChartColumnIncreasing, FileText, Filter, Percent } from 'lucide-react';
import { CommercialActivity } from '@/components/dashboard/commercial-activity';
import { CommercialEvolution } from '@/components/dashboard/commercial-evolution';
import { Panel } from '@/components/dashboard/financial-home';
import { PageHeader, PageShell } from '@/components/page-shell';
import { useLocale } from '@/hooks/use-locale';
import { translate } from '@/i18n';
import type { CommercialOverview } from '@/types/commercial-dashboard';

export default function CommercialDashboard({
    overview,
}: {
    overview: CommercialOverview;
}) {
    const { locale } = useLocale();
    const { workspaceContext } = usePage().props;
    const t = (key: string) => translate(key, locale);
    const formatLocale = locale === 'es' ? 'es-ES' : 'en-GB';
    const money = (value: number) =>
        new Intl.NumberFormat(formatLocale, {
            style: 'currency',
            currency: 'EUR',
        }).format(value);
    const percent = (value: number) =>
        `${new Intl.NumberFormat(formatLocale, { maximumFractionDigits: 1 }).format(value)}%`;
    const date = (value: string) =>
        new Intl.DateTimeFormat(formatLocale, {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
        }).format(new Date(`${value}T12:00:00`));
    const statuses: Record<string, string> = {
        draft: 'Borradores',
        sent: 'Enviados',
        accepted: 'Aceptados',
        rejected: 'Rechazados',
        expired: 'Caducados',
    };
    const kpis = [
        ...(overview.permissions.sales
            ? [
                  {
                      key: 'sales',
                      title: 'Ventas del periodo',
                      value: money(overview.kpis.sales ?? 0),
                      icon: ChartColumnIncreasing,
                      detail:
                          overview.kpis.sales_change === null
                              ? t('Sin base comparable')
                              : `${percent(overview.kpis.sales_change)} ${t('vs. periodo anterior')}`,
                  },
              ]
            : []),
        ...(overview.permissions.quotes
            ? [
                  {
                      key: 'quotes',
                      title: 'Presupuestos pendientes',
                      value: money(overview.kpis.pending_quotes ?? 0),
                      icon: FileText,
                      detail: t('Enviados vigentes del periodo'),
                  },
              ]
            : []),
        ...(overview.permissions.crm
            ? [
                  {
                      key: 'conversion',
                      title: 'Tasa de conversión',
                      value:
                          overview.kpis.conversion === null
                              ? '—'
                              : percent(overview.kpis.conversion),
                      icon: Percent,
                      detail: t('Ganadas / oportunidades cerradas'),
                  },
                  {
                      key: 'pipeline',
                      title: 'Pipeline activo',
                      value: String(overview.kpis.pipeline ?? 0),
                      icon: Filter,
                      detail: t('Oportunidades creadas en el periodo'),
                  },
              ]
            : []),
    ];

    return (
        <>
            <Head title={t('Comercial')} />
            <PageShell mode="standard" className="commercial-dashboard">
                <PageHeader
                    title={t('Comercial')}
                    description={`${t('Visión general de la actividad comercial de')} ${workspaceContext?.companyName ?? t('Tu empresa')}`}
                    secondaryNavigation={null}
                    metadata={
                        <>
                            {date(overview.from)} – {date(overview.to)} ·{' '}
                            {t(
                                overview.scope === 'own'
                                    ? 'Mi ámbito comercial'
                                    : 'Todo el equipo',
                            )}
                        </>
                    }
                    actions={
                        <div className="commercial-header-tools">
                            <time dateTime={overview.today}>
                                {new Intl.DateTimeFormat(formatLocale, {
                                    weekday: 'long',
                                    day: 'numeric',
                                    month: 'long',
                                    year: 'numeric',
                                }).format(
                                    new Date(`${overview.today}T12:00:00`),
                                )}
                            </time>
                            <label
                                className="sr-only"
                                htmlFor="commercial-period"
                            >
                                {t('Periodo comercial')}
                            </label>
                            <select
                                id="commercial-period"
                                value={overview.period}
                                onChange={(event) =>
                                    router.get(
                                        '/commercial',
                                        { period: event.target.value },
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                {(
                                    [
                                        ['month', 'Este mes'],
                                        ['previous_month', 'Mes anterior'],
                                        ['quarter', 'Este trimestre'],
                                        ['year', 'Este año'],
                                    ] as const
                                ).map(([value, label]) => (
                                    <option value={value} key={value}>
                                        {t(label)}
                                    </option>
                                ))}
                            </select>
                        </div>
                    }
                />
                <div className="commercial-kpis">
                    {kpis.map(({ key, title, value, icon: Icon, detail }) => (
                        <section key={key} className="home-card home-metric">
                            <div className="home-metric-icon">
                                <Icon aria-hidden="true" />
                            </div>
                            <div>
                                <h2>{t(title)}</h2>
                                <strong>{value}</strong>
                                <p>{detail}</p>
                            </div>
                        </section>
                    ))}
                </div>
                <div className="commercial-main-row">
                    <Panel
                        title={t('Evolución comercial')}
                        extra={
                            <span>
                                {t('Últimos 12 meses')} ·{' '}
                                {date(`${overview.monthly[0].month}-01`)} –{' '}
                                {date(overview.to)}
                            </span>
                        }
                    >
                        <CommercialEvolution
                            overview={overview}
                            money={money}
                            percent={percent}
                            locale={formatLocale}
                            t={t}
                        />
                    </Panel>
                    {overview.permissions.sales && (
                        <Panel title={t('Objetivos comerciales')}>
                            {overview.target ? (
                                <>
                                    <div className="home-year-state">
                                        <div
                                            className="home-donut"
                                            style={{
                                                background: `conic-gradient(var(--commercial-sales) ${Math.min(100, Math.max(0, (overview.target.achieved / overview.target.amount) * 100))}%, var(--border) 0)`,
                                            }}
                                        >
                                            <span>
                                                {Math.round(
                                                    (overview.target.achieved /
                                                        overview.target
                                                            .amount) *
                                                        100,
                                                )}
                                                %
                                            </span>
                                        </div>
                                        <div>
                                            <strong>
                                                {t('Objetivo para el periodo')}
                                            </strong>
                                            <p>
                                                {money(overview.target.amount)}
                                            </p>
                                        </div>
                                    </div>
                                    <dl className="commercial-summary-list">
                                        <div>
                                            <dt>{t('Ventas realizadas')}</dt>
                                            <dd>
                                                {money(
                                                    overview.target.achieved,
                                                )}
                                            </dd>
                                        </div>
                                        <div>
                                            <dt>
                                                {t('Pendiente de alcanzar')}
                                            </dt>
                                            <dd>
                                                {money(
                                                    Math.max(
                                                        0,
                                                        overview.target.amount -
                                                            overview.target
                                                                .achieved,
                                                    ),
                                                )}
                                            </dd>
                                        </div>
                                    </dl>
                                    <p className="home-source">
                                        {t(
                                            'Presupuesto financiero de ingresos, prorrateado al periodo',
                                        )}
                                    </p>
                                </>
                            ) : (
                                <>
                                    <div className="commercial-target-empty">
                                        <Percent aria-hidden="true" />
                                        <p>
                                            {t(
                                                'No hay un objetivo comercial disponible para este periodo.',
                                            )}
                                        </p>
                                    </div>
                                    <p className="home-source">
                                        {t(
                                            'Objetivos individuales pendientes de integración',
                                        )}
                                    </p>
                                </>
                            )}
                        </Panel>
                    )}
                </div>
                <div className="commercial-summary-row">
                    {overview.pipeline && (
                        <Panel
                            title={t('Pipeline comercial (CRM)')}
                            extra={
                                <Link href="/opportunities">
                                    {t('Ver CRM')}
                                </Link>
                            }
                        >
                            <dl className="commercial-pipeline">
                                {overview.pipeline.map((stage) => (
                                    <div key={stage.stage}>
                                        <dt>
                                            <span
                                                className={
                                                    stage.closed
                                                        ? 'commercial-stage-closed'
                                                        : 'commercial-stage-active'
                                                }
                                                aria-hidden="true"
                                            />
                                            {stage.label}
                                        </dt>
                                        <dd>{stage.count}</dd>
                                    </div>
                                ))}
                            </dl>
                            <p className="home-source">
                                {t(
                                    'Etapas actuales de las oportunidades creadas en el periodo',
                                )}
                            </p>
                        </Panel>
                    )}
                    {overview.quotes && (
                        <Panel
                            title={t('Presupuestos')}
                            extra={<Link href="/quotes">{t('Ver todos')}</Link>}
                        >
                            <dl className="commercial-summary-list">
                                {overview.quotes.map((row) => (
                                    <div key={row.status}>
                                        <dt>
                                            <Link
                                                href={`/quotes?status=${row.status}`}
                                            >
                                                {t(
                                                    statuses[row.status] ??
                                                        row.status,
                                                )}
                                            </Link>
                                        </dt>
                                        <dd>{row.count}</dd>
                                        <dd>{money(row.amount)}</dd>
                                    </div>
                                ))}
                            </dl>
                            <p className="home-source">
                                {t(
                                    'Estado actual por fecha de emisión del periodo',
                                )}
                            </p>
                        </Panel>
                    )}
                    {overview.customers && (
                        <Panel
                            title={t('Clientes')}
                            extra={<Link href="/people">{t('Ver todos')}</Link>}
                        >
                            <dl className="commercial-summary-list">
                                <div>
                                    <dt>{t('Nuevos en el periodo')}</dt>
                                    <dd>{overview.customers.new}</dd>
                                </div>
                                {(
                                    [
                                        ['active', 'Activos'],
                                        ['lead', 'Potenciales'],
                                        ['inactive', 'Inactivos'],
                                        ['former', 'Antiguos'],
                                    ] as const
                                ).map(([status, label]) => (
                                    <div key={status}>
                                        <dt>{t(label)}</dt>
                                        <dd>
                                            {overview.customers?.statuses[
                                                status
                                            ] ?? 0}
                                        </dd>
                                    </div>
                                ))}
                            </dl>
                            <p className="home-source">
                                {t(
                                    'Estado actual de los clientes creados en el periodo',
                                )}
                            </p>
                        </Panel>
                    )}
                </div>
                <div className="commercial-bottom-row">
                    <Panel title={t('Actividad comercial reciente')}>
                        <CommercialActivity
                            events={overview.activity}
                            t={t}
                            locale={formatLocale}
                            statuses={statuses}
                        />
                    </Panel>
                    {overview.tasks !== null && (
                        <Panel
                            title={t('Tareas comerciales')}
                            className="commercial-tasks"
                        >
                            <div className="commercial-task-list">
                                {overview.tasks.map((task) => (
                                    <Link href={task.href} key={task.id}>
                                        <FileText aria-hidden="true" />
                                        <div>
                                            <strong>{task.title}</strong>
                                            <span>{task.reference}</span>
                                        </div>
                                        <time
                                            dateTime={task.due_at}
                                            className={
                                                task.overdue
                                                    ? 'commercial-overdue'
                                                    : ''
                                            }
                                        >
                                            {new Intl.DateTimeFormat(
                                                formatLocale,
                                                {
                                                    day: '2-digit',
                                                    month: '2-digit',
                                                    hour: '2-digit',
                                                    minute: '2-digit',
                                                },
                                            ).format(new Date(task.due_at))}
                                            {task.overdue && (
                                                <span>{t('Vencido')}</span>
                                            )}
                                        </time>
                                    </Link>
                                ))}
                            </div>
                            {!overview.tasks.length && (
                                <p className="home-empty">
                                    {t(
                                        'No hay tareas comerciales pendientes en este periodo.',
                                    )}
                                </p>
                            )}
                            <p className="home-source">
                                {t(
                                    'Acciones CRM pendientes con vencimiento en el periodo',
                                )}
                            </p>
                        </Panel>
                    )}
                </div>
            </PageShell>
        </>
    );
}

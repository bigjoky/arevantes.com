import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowDown,
    ArrowUp,
    Banknote,
    BookOpen,
    Building2,
    ChartColumnIncreasing,
    ChartNoAxesCombined,
    ChevronRight,
    ClipboardList,
    FilePlus2,
    UserPlus,
    FileText,
    Landmark,
    PieChart,
    Receipt,
    Wallet,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import {
    Bar,
    CartesianGrid,
    ComposedChart,
    Legend,
    Line,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import { HomeHeading } from '@/components/dashboard/home-heading';
import { MobileFinancialHome } from '@/components/mobile/mobile-financial-home';
import { PageShell } from '@/components/page-shell';
import { useLocale } from '@/hooks/use-locale';
import { useIsMobile } from '@/hooks/use-mobile';
import { translate } from '@/i18n';
import type { FinancialOverview } from '@/types/financial-dashboard';

export function FinancialHome({ overview }: { overview: FinancialOverview }) {
    const isMobile = useIsMobile(1024);
    const { locale } = useLocale();
    const { auth } = usePage().props;
    const t = (key: string) => translate(key, locale);
    const formatLocale = locale === 'es' ? 'es-ES' : 'en-GB';
    const money = (value: number, currency = 'EUR') =>
        /^[A-Z]{3}$/.test(currency)
            ? new Intl.NumberFormat(formatLocale, {
                  style: 'currency',
                  currency,
              }).format(value)
            : `${new Intl.NumberFormat(formatLocale, { maximumFractionDigits: 2 }).format(value)} ${currency}`;
    const date = (value: string) =>
        new Intl.DateTimeFormat(formatLocale, {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
        }).format(new Date(`${value}T12:00:00`));
    const account = (view: string) =>
        `/accounting?year=${overview.year}&view=${view}`;
    const pending = t('Pendiente de integración');
    const actions: Array<{ label: string; href: string; icon: LucideIcon }> = [
        ...(auth.abilities.manageAccounting && overview.fiscal_status === 'open'
            ? [
                  {
                      label: 'Nuevo asiento',
                      href: `${account('journal')}&create=1`,
                      icon: FilePlus2,
                  },
              ]
            : []),
        ...(auth.abilities.manageInstallations
            ? [
                  {
                      label: 'Registrar factura',
                      href: '/sales/create?type=invoice',
                      icon: Receipt,
                  },
              ]
            : []),
        ...(auth.abilities.manageTreasury
            ? [{ label: 'Conciliar banco', href: '/banking', icon: Landmark }]
            : []),
        ...(auth.abilities.manageCustomers
            ? [
                  {
                      label: 'Nuevo cliente',
                      href: '/people/create',
                      icon: UserPlus,
                  },
              ]
            : []),
        ...(auth.abilities.manageQuotes
            ? [
                  {
                      label: 'Nuevo presupuesto',
                      href: '/quotes/create',
                      icon: FilePlus2,
                  },
              ]
            : []),
        ...(auth.abilities.manageOperations &&
        auth.abilities.manageInstallations
            ? [
                  {
                      label: 'Orden de trabajo',
                      href: '/work-orders?create=1',
                      icon: ClipboardList,
                  },
              ]
            : []),
        {
            label: 'Balance de situación',
            href: account('balance'),
            icon: ChartNoAxesCombined,
        },
        {
            label: 'Cuenta de resultados',
            href: account('profit'),
            icon: ChartColumnIncreasing,
        },
        {
            label: 'Informes',
            href: `/workspace/modules/accounting/reports?year=${overview.year}`,
            icon: BookOpen,
        },
        { label: 'Libro Diario', href: account('journal'), icon: FileText },
    ];
    const actionOrder = [
        'Nuevo cliente',
        'Nuevo presupuesto',
        'Registrar factura',
        'Orden de trabajo',
        'Conciliar banco',
        'Nuevo asiento',
        'Libro Diario',
        'Balance de situación',
        'Cuenta de resultados',
        'Informes',
    ];
    actions.sort(
        (a, b) => actionOrder.indexOf(a.label) - actionOrder.indexOf(b.label),
    );
    const monthly = overview.monthly.map((month) => ({
        ...month,
        label: new Intl.DateTimeFormat(formatLocale, { month: 'short' }).format(
            new Date(overview.year, month.month - 1, 1),
        ),
    }));
    const status = (value: string) =>
        t(
            (
                {
                    posted: 'Contabilizado',
                    draft: 'Borrador',
                    pending: 'Pendiente',
                    issued: 'Emitida',
                    received: 'Recibida',
                    paid: 'Pagada',
                    cancelled: 'Anulada',
                    approved: 'Aprobada',
                    registered: 'Registrada',
                    review_required: 'Revisión requerida',
                    overdue: 'Vencido',
                    partial: 'Parcialmente pagada',
                    partially_paid: 'Parcialmente pagada',
                    closed: 'Cerrada',
                    fulfilled: 'Completada',
                } as Record<string, string>
            )[value] ?? value,
        );
    const percentage = overview.fiscal_progress;

    if (isMobile) {
return <MobileFinancialHome overview={overview} />;
}

    return (
        <>
            <Head title={t('Inicio')} />
            <PageShell mode="standard" className="financial-home">
                <HomeHeading today={overview.today} />
                <div className="home-metrics">
                    {(
                        [
                            {
                                key: 'income',
                                label: 'Ingresos acumulados',
                                icon: ChartColumnIncreasing,
                            },
                            {
                                key: 'expenses',
                                label: 'Gastos acumulados',
                                icon: ChartNoAxesCombined,
                            },
                            {
                                key: 'result',
                                label: 'Resultado del ejercicio',
                                icon: PieChart,
                            },
                        ] as const
                    ).map(({ key, label, icon: Icon }) => {
                        const comparison = overview.comparison[key];

                        return (
                            <section
                                className="home-card home-metric"
                                key={key}
                            >
                                <div className={`home-metric-icon ${''}`}>
                                    <Icon aria-hidden="true" />
                                </div>
                                <div>
                                    <h2>{t(label)}</h2>
                                    <strong>
                                        {money(overview.metrics[key])}
                                    </strong>
                                    <p>
                                        {comparison === null ? (
                                            t('Sin base comparable')
                                        ) : (
                                            <>
                                                <span
                                                    className={
                                                        key === 'expenses'
                                                            ? 'text-muted-foreground'
                                                            : comparison < 0
                                                              ? 'text-red-600'
                                                              : 'text-emerald-700'
                                                    }
                                                >
                                                    {comparison < 0 ? (
                                                        <ArrowDown />
                                                    ) : (
                                                        <ArrowUp />
                                                    )}
                                                    {new Intl.NumberFormat(
                                                        locale,
                                                        {
                                                            maximumFractionDigits: 1,
                                                            signDisplay:
                                                                'exceptZero',
                                                        },
                                                    ).format(comparison)}
                                                    %
                                                </span>{' '}
                                                {t('vs.')} {overview.year - 1}
                                            </>
                                        )}
                                    </p>
                                </div>
                            </section>
                        );
                    })}
                    <section className="home-card home-metric">
                        <div className="home-metric-icon">
                            <Receipt aria-hidden="true" />
                        </div>
                        <div>
                            <h2>{t('IVA a ingresar')}</h2>
                            <strong>
                                {overview.vat_payable === null
                                    ? '—'
                                    : money(overview.vat_payable)}
                            </strong>
                            <p>
                                {overview.vat_payable === null
                                    ? pending
                                    : t('Periodo seleccionado')}
                            </p>
                        </div>
                    </section>
                </div>
                <div className="home-middle">
                    <Panel
                        title={t('Evolución mensual')}
                        className="home-chart"
                        extra={
                            <span className="home-period">
                                {t('Ejercicio')} {overview.year}
                            </span>
                        }
                    >
                        {overview.posted_entries === 0 && (
                            <p className="home-empty">
                                {t(
                                    'Sin movimientos contabilizados en este periodo',
                                )}
                            </p>
                        )}
                        <FinancialEvolution
                            monthly={monthly}
                            money={money}
                            locale={formatLocale}
                            t={t}
                        />
                        <p className="home-source">
                            {t('Asientos contabilizados hasta')}{' '}
                            {date(overview.as_of)}
                        </p>
                    </Panel>
                    <Panel title={t('Estado del ejercicio')}>
                        <div className="home-year-state">
                            <div
                                className="home-donut"
                                style={{
                                    background: `conic-gradient(#008665 ${percentage ?? 0}%, var(--border) 0)`,
                                }}
                            >
                                <span>
                                    {percentage === null
                                        ? '—'
                                        : `${percentage}%`}
                                </span>
                            </div>
                            <div>
                                <strong>
                                    {t(
                                        overview.fiscal_status === 'closed'
                                            ? 'Ejercicio cerrado'
                                            : overview.fiscal_status === 'open'
                                              ? 'Periodo en curso'
                                              : 'Ejercicio no configurado',
                                    )}
                                </strong>
                                <p>
                                    {overview.fiscal_dates?.starts_on &&
                                    overview.fiscal_dates?.ends_on
                                        ? `${date(overview.fiscal_dates.starts_on)} – ${date(overview.fiscal_dates.ends_on)}`
                                        : t('Fechas no disponibles')}
                                </p>
                                {overview.fiscal_dates?.starts_on ===
                                    `${overview.year}-01-01` &&
                                    overview.fiscal_dates?.ends_on ===
                                        `${overview.year}-12-31` && (
                                        <p>
                                            {overview.elapsed_months} / 12{' '}
                                            {t('meses')}
                                        </p>
                                    )}
                            </div>
                        </div>
                        <dl className="home-state-list">
                            <div>
                                <dt>
                                    <i />
                                    {t('Asientos contabilizados')}
                                </dt>
                                <dd>{overview.posted_entries}</dd>
                            </div>
                            <div>
                                <dt>
                                    <i />
                                    {t('Asientos pendientes')}
                                </dt>
                                <dd>{overview.pending_entries}</dd>
                            </div>
                            <div>
                                <dt>
                                    <i />
                                    {t('Documentos por procesar')}
                                </dt>
                                <dd
                                    title={pending}
                                    aria-label={
                                        overview.pending_documents === null
                                            ? pending
                                            : undefined
                                    }
                                >
                                    {overview.pending_documents ?? '—'}
                                </dd>
                            </div>
                        </dl>
                        <Link
                            className="home-year-link"
                            href={`${account('journal')}&area=overview`}
                        >
                            {overview.fiscal_status === 'closed'
                                ? t('Ejercicio cerrado')
                                : overview.fiscal_status === 'open'
                                  ? t('Ver cierre de ejercicio')
                                  : t('Configurar ejercicio')}
                            <ChevronRight size={16} />
                        </Link>
                    </Panel>
                    <Panel title={t('Saldos principales')}>
                        <div className="home-balances">
                            {(
                                [
                                    {
                                        key: 'treasury',
                                        label: 'Tesorería',
                                        icon: Wallet,
                                    },
                                    {
                                        key: 'customers',
                                        label: 'Clientes',
                                        icon: Banknote,
                                    },
                                    {
                                        key: 'suppliers',
                                        label: 'Proveedores',
                                        icon: Building2,
                                    },
                                    {
                                        key: 'vat',
                                        label: 'Hacienda (IVA)',
                                        icon: Receipt,
                                    },
                                ] as const
                            ).map(({ key, label, icon: Icon }) => (
                                <Link
                                    href={
                                        key === 'treasury'
                                            ? `/finance?area=treasury&year=${overview.year}`
                                            : key === 'vat'
                                              ? `/tax?year=${overview.year}`
                                              : account('parties')
                                    }
                                    key={key}
                                >
                                    <Icon aria-hidden="true" />
                                    <span>{t(label)}</span>
                                    <strong>
                                        {money(overview.balances[key])}
                                    </strong>
                                </Link>
                            ))}
                            <Link href={account('profit')}>
                                <FileText aria-hidden="true" />
                                <span>{t('Resultado ejercicio')}</span>
                                <strong>
                                    {money(overview.metrics.result)}
                                </strong>
                            </Link>
                        </div>
                        <p className="home-source">
                            {t('Saldos contables del ejercicio')}
                        </p>
                    </Panel>
                </div>
                <div className="home-access-row">
                    <Panel title={t('Accesos rápidos')}>
                        <div className="home-shortcuts">
                            {actions.map(({ label, href, icon: Icon }) => (
                                <Link key={label} href={href}>
                                    <Icon aria-hidden="true" />
                                    <span>{t(label)}</span>
                                </Link>
                            ))}
                        </div>
                    </Panel>
                </div>
                <div className="home-bottom">
                    <RecentDocuments
                        overview={overview}
                        money={money}
                        date={date}
                        status={status}
                        t={t}
                    />
                    <Panel
                        title={t('Tareas pendientes')}
                        className="home-tasks"
                        extra={
                            auth.abilities.manageTreasury && (
                                <Link href="/banking">{t('Ver todas')}</Link>
                            )
                        }
                    >
                        <div className="home-task-list">
                            {overview.tasks.map((task) => (
                                <Link href={task.href} key={task.id}>
                                    <span
                                        className="home-task-square"
                                        aria-hidden="true"
                                    />
                                    <strong>{task.title}</strong>
                                    <span>
                                        {task.count} {t('movimientos')}
                                    </span>
                                    <ChevronRight size={14} />
                                </Link>
                            ))}
                        </div>
                        {!overview.tasks.length && (
                            <p className="home-empty">
                                {t('Sin conciliaciones pendientes')}
                            </p>
                        )}
                        <p className="home-source">
                            {t(
                                'Otras tareas y vencimientos pendientes de integración',
                            )}
                        </p>
                    </Panel>
                    <Panel
                        title={t('Actividad reciente')}
                        className="home-recent-activity"
                        extra={
                            <Link href={account('journal')}>
                                {t('Ver todas')}
                            </Link>
                        }
                    >
                        <div className="home-activity">
                            {overview.activity.map((event) => (
                                <Link href={event.href} key={event.id}>
                                    <FileText aria-hidden="true" />
                                    <div>
                                        <strong>{event.description}</strong>
                                        <span>{event.reference}</span>
                                    </div>
                                    <time dateTime={event.occurred_at}>
                                        {new Intl.DateTimeFormat(formatLocale, {
                                            day: '2-digit',
                                            month: '2-digit',
                                            hour: '2-digit',
                                            minute: '2-digit',
                                        }).format(new Date(event.occurred_at))}
                                    </time>
                                </Link>
                            ))}
                        </div>
                        {!overview.activity.length && (
                            <p className="home-empty">
                                {t('No hay actividad reciente')}
                            </p>
                        )}
                        <p className="home-source">
                            {t('Actividad de contabilización')}
                        </p>
                    </Panel>
                </div>
                <Link
                    className="home-operational-link"
                    href="/dashboard?section=operations"
                >
                    {t('Ver resumen operativo')}
                    <ChevronRight size={14} />
                </Link>
            </PageShell>
        </>
    );
}

export function Panel({
    title,
    children,
    extra,
    className = '',
}: {
    title: string;
    children: ReactNode;
    extra?: ReactNode;
    className?: string;
}) {
    return (
        <section className={`home-card home-panel ${className}`}>
            <div className="home-panel-heading">
                <h2>{title}</h2>
                <div>{extra}</div>
            </div>
            {children}
        </section>
    );
}

function FinancialEvolution({
    monthly,
    money,
    locale,
    t,
}: {
    monthly: Array<FinancialOverview['monthly'][number] & { label: string }>;
    money: (value: number) => string;
    locale: string;
    t: (key: string) => string;
}) {
    return (
        <>
            <div
                className="home-chart-canvas"
                role="img"
                aria-label={t('Ingresos, gastos y resultado por mes')}
            >
                <ResponsiveContainer width="100%" height="100%">
                    <ComposedChart
                        data={monthly}
                        margin={{
                            top: 16,
                            right: 10,
                            bottom: 0,
                            left: -16,
                        }}
                        accessibilityLayer
                    >
                        <CartesianGrid
                            vertical={false}
                            stroke="var(--border)"
                        />
                        <XAxis
                            dataKey="label"
                            tickLine={false}
                            axisLine={false}
                            tick={{
                                fontSize: 10,
                                fill: 'var(--muted-foreground)',
                            }}
                        />
                        <YAxis
                            tickLine={false}
                            axisLine={false}
                            tick={{
                                fontSize: 10,
                                fill: 'var(--muted-foreground)',
                            }}
                            tickFormatter={(value: number) =>
                                new Intl.NumberFormat(locale, {
                                    notation: 'compact',
                                }).format(value)
                            }
                        />
                        <Tooltip
                            formatter={(value) =>
                                value == null ? '—' : money(Number(value))
                            }
                            contentStyle={{
                                background: 'var(--background)',
                                borderColor: 'var(--border)',
                                borderRadius: 8,
                            }}
                        />
                        <Legend
                            iconSize={10}
                            wrapperStyle={{
                                fontSize: 11,
                                paddingTop: 12,
                            }}
                        />
                        <Bar
                            isAnimationActive={false}
                            name={t('Ingresos')}
                            dataKey="income"
                            fill="#008665"
                            radius={[3, 3, 0, 0]}
                            maxBarSize={16}
                        />
                        <Bar
                            isAnimationActive={false}
                            name={t('Gastos')}
                            dataKey="expenses"
                            fill="#92cfbb"
                            radius={[3, 3, 0, 0]}
                            maxBarSize={16}
                        />
                        <Line
                            isAnimationActive={false}
                            name={t('Resultado')}
                            dataKey="result"
                            stroke="#00694f"
                            strokeWidth={2}
                            dot={{ r: 2 }}
                            connectNulls={false}
                        />
                    </ComposedChart>
                </ResponsiveContainer>
            </div>
            <details className="home-chart-data">
                <summary>{t('Ver datos del gráfico')}</summary>
                <table className="home-table">
                    <caption className="sr-only">
                        {t('Ingresos, gastos y resultado por mes')}
                    </caption>
                    <thead>
                        <tr>
                            {['Mes', 'Ingresos', 'Gastos', 'Resultado'].map(
                                (label) => (
                                    <th key={label} scope="col">
                                        {t(label)}
                                    </th>
                                ),
                            )}
                        </tr>
                    </thead>
                    <tbody>
                        {monthly.map((month) => (
                            <tr key={month.month}>
                                <th scope="row">{month.label}</th>
                                <td>
                                    {month.income === null
                                        ? '—'
                                        : money(month.income)}
                                </td>
                                <td>
                                    {month.expenses === null
                                        ? '—'
                                        : money(month.expenses)}
                                </td>
                                <td>
                                    {month.result === null
                                        ? '—'
                                        : money(month.result)}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </details>
        </>
    );
}

function RecentDocuments({
    overview,
    money,
    date,
    status,
    t,
}: {
    overview: FinancialOverview;
    money: (value: number, currency?: string) => string;
    date: (value: string) => string;
    status: (value: string) => string;
    t: (key: string) => string;
}) {
    return (
        <Panel
            title={t('Últimos documentos')}
            className="home-documents"
            extra={<Link href="/expenses">{t('Ver gastos')}</Link>}
        >
            <div className="home-table-wrap">
                <table className="home-table">
                    <thead>
                        <tr>
                            {[
                                'Fecha',
                                'Tipo',
                                'Número',
                                'Tercero',
                                'Importe',
                                'Estado',
                            ].map((label) => (
                                <th key={label}>{t(label)}</th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {overview.documents.map((document) => (
                            <tr key={document.id}>
                                <td>{date(document.date)}</td>
                                <td>
                                    {t(
                                        document.type.includes('credit')
                                            ? 'Rectificativa'
                                            : document.type === 'receipt'
                                              ? 'Recibo'
                                              : document.type.includes(
                                                      'invoice',
                                                  )
                                                ? 'Factura'
                                                : 'Gasto',
                                    )}
                                </td>
                                <td>
                                    <Link href={document.href}>
                                        {document.number ?? t('Borrador')}
                                    </Link>
                                </td>
                                <td className="home-concept">
                                    {document.party ?? '—'}
                                </td>
                                <td className="home-number">
                                    {money(
                                        document.amount,
                                        document.currency ?? 'EUR',
                                    )}
                                </td>
                                <td>
                                    <span
                                        className={`home-status home-status-${document.status}`}
                                    >
                                        {status(document.status)}
                                    </span>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            <div className="home-document-list">
                {overview.documents.map((document) => (
                    <Link href={document.href} key={document.id}>
                        <FileText aria-hidden="true" />
                        <div>
                            <strong>{document.number ?? t('Borrador')}</strong>
                            <span>{document.party ?? '—'}</span>
                            <time dateTime={document.date}>
                                {date(document.date)}
                            </time>
                        </div>
                        <div>
                            <strong>
                                {money(
                                    document.amount,
                                    document.currency ?? 'EUR',
                                )}
                            </strong>
                            <span
                                className={`home-status home-status-${document.status}`}
                            >
                                {status(document.status)}
                            </span>
                        </div>
                    </Link>
                ))}
            </div>
            {!overview.documents.length &&
                (overview.documents_available ? (
                    <p className="home-empty">
                        {t('Todavía no hay documentos recientes')}
                    </p>
                ) : (
                    <p className="home-empty">
                        {t('Documentos no accesibles para tu perfil')}
                    </p>
                ))}
        </Panel>
    );
}

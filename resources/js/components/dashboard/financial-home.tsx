import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowDown,
    ArrowUp,
    Banknote,
    BookOpen,
    Building2,
    Calculator,
    ChartColumnIncreasing,
    ChartNoAxesCombined,
    ChevronRight,
    FilePlus2,
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
import { useLocale } from '@/hooks/use-locale';
import { translate } from '@/i18n';
import type { FinancialOverview } from '@/types/financial-dashboard';

export function FinancialHome({ overview }: { overview: FinancialOverview }) {
    const { locale } = useLocale();
    const { auth, workspaceContext } = usePage().props;
    const t = (key: string) => translate(key, locale);
    const money = (value: number) =>
        new Intl.NumberFormat(locale, {
            style: 'currency',
            currency: 'EUR',
        }).format(value);
    const date = (value: string) =>
        new Intl.DateTimeFormat(locale).format(new Date(`${value}T12:00:00`));
    const account = (view: string) =>
        `/accounting?year=${overview.year}&view=${view}`;
    const empty = (
        <p className="home-empty">{t('Sin registros en este periodo')}</p>
    );
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
        ...(auth.abilities.manageTax
            ? [
                  {
                      label: 'Modelo 303',
                      href: `/tax/models/303?year=${overview.year}`,
                      icon: Calculator,
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
        { label: 'Libro Mayor', href: account('ledger'), icon: BookOpen },
        { label: 'Libro Diario', href: account('journal'), icon: FileText },
    ];
    const monthly = overview.monthly.map((month) => ({
        ...month,
        label: new Intl.DateTimeFormat(locale, { month: 'short' }).format(
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
                    partially_paid: 'Parcialmente pagada',
                    closed: 'Cerrada',
                    fulfilled: 'Completada',
                } as Record<string, string>
            )[value] ?? value,
        );
    const percentage = Math.round((overview.elapsed_months / 12) * 100);

    return (
        <>
            <Head title={t('Inicio')} />
            <main className="financial-home">
                <div className="home-watermark" aria-hidden="true">
                    A
                </div>
                <div className="home-heading">
                    <div>
                        <p className="home-eyebrow">
                            {t('Bienvenido de nuevo')}
                        </p>
                        <h1>{t('Inicio')}</h1>
                        <p className="home-subtitle">
                            {t('Resumen de la situación contable y financiera')}{' '}
                            · {workspaceContext?.companyName ?? t('Tu empresa')}
                        </p>
                    </div>
                    <div className="home-date">
                        <span>
                            {new Intl.DateTimeFormat(locale, {
                                weekday: 'long',
                            }).format(new Date(`${overview.today}T12:00:00`))}
                        </span>
                        <strong>
                            {new Intl.DateTimeFormat(locale, {
                                day: 'numeric',
                                month: 'long',
                                year: 'numeric',
                            }).format(new Date(`${overview.today}T12:00:00`))}
                        </strong>
                        <p>
                            {t('Controlar los números es construir el futuro.')}
                        </p>
                        <i />
                    </div>
                </div>
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
                                <div
                                    className={`home-metric-icon ${key === 'expenses' ? 'home-expense-icon' : ''}`}
                                >
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
                                                        comparison < 0
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
                        title={t('Evolución financiera')}
                        className="home-chart"
                        extra={
                            <span className="home-period">
                                {t('Ejercicio')} {overview.year}
                            </span>
                        }
                    >
                        <div
                            className="home-chart-canvas"
                            role="img"
                            aria-label={t(
                                'Ingresos, gastos y resultado por mes',
                            )}
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
                                            value == null
                                                ? '—'
                                                : money(Number(value))
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
                                    background: `conic-gradient(#008665 ${percentage}%, var(--border) 0)`,
                                }}
                            >
                                <span>{percentage}%</span>
                            </div>
                            <div>
                                <strong>{t('Avance del calendario')}</strong>
                                <p>
                                    01/01/{overview.year} – 31/12/
                                    {overview.year}
                                </p>
                                <p>
                                    {overview.elapsed_months} / 12 {t('meses')}
                                </p>
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
                                <dd title={pending}>
                                    {overview.pending_documents ?? '—'}
                                </dd>
                            </div>
                        </dl>
                        <Link
                            className="home-year-link"
                            href={account('journal')}
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
                                    href={account(
                                        key === 'customers' ||
                                            key === 'suppliers'
                                            ? 'parties'
                                            : 'ledger',
                                    )}
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
                    <Panel
                        title={t('Últimos asientos')}
                        extra={
                            <Link href={account('journal')}>
                                {t('Ver todos')}
                            </Link>
                        }
                    >
                        <div className="home-table-wrap">
                            <table className="home-table">
                                <thead>
                                    <tr>
                                        {[
                                            'Fecha',
                                            'Asiento',
                                            'Concepto',
                                            'Debe',
                                            'Haber',
                                            'Estado',
                                        ].map((label) => (
                                            <th key={label}>{t(label)}</th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody>
                                    {overview.entries.map((entry) => (
                                        <tr key={entry.id}>
                                            <td>{date(entry.date)}</td>
                                            <td>
                                                <Link href={entry.href}>
                                                    {entry.number}
                                                </Link>
                                            </td>
                                            <td className="home-concept">
                                                <Link href={entry.href}>
                                                    {entry.description}
                                                </Link>
                                            </td>
                                            <td className="home-number">
                                                {money(entry.debit)}
                                            </td>
                                            <td className="home-number">
                                                {money(entry.credit)}
                                            </td>
                                            <td>
                                                <span
                                                    className={`home-status ${entry.status === 'posted' ? 'home-status-posted' : ''}`}
                                                >
                                                    {status(entry.status)}
                                                </span>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                        {!overview.entries.length && empty}
                    </Panel>
                </div>
                <div className="home-bottom">
                    <Panel title={t('Últimos documentos')}>
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
                                                    document.type.includes(
                                                        'credit',
                                                    )
                                                        ? 'Rectificativa'
                                                        : document.type ===
                                                            'receipt'
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
                                                    {document.number ??
                                                        t('Borrador')}
                                                </Link>
                                            </td>
                                            <td className="home-concept">
                                                {document.party ?? '—'}
                                            </td>
                                            <td className="home-number">
                                                {money(document.amount)}
                                            </td>
                                            <td>
                                                <span className="home-status">
                                                    {status(document.status)}
                                                </span>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                        {!overview.documents.length &&
                            (overview.documents_available ? (
                                empty
                            ) : (
                                <p className="home-empty">
                                    {t(
                                        'Documentos no accesibles para tu perfil',
                                    )}
                                </p>
                            ))}
                    </Panel>
                    <Panel
                        title={t('Tareas pendientes')}
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
                    <Panel title={t('Actividad reciente')}>
                        <div className="home-activity">
                            {overview.activity.map((event) => (
                                <Link href={event.href} key={event.id}>
                                    <FileText aria-hidden="true" />
                                    <div>
                                        <strong>{event.description}</strong>
                                        <span>{event.reference}</span>
                                    </div>
                                    <time dateTime={event.occurred_at}>
                                        {new Intl.DateTimeFormat(locale, {
                                            day: '2-digit',
                                            month: '2-digit',
                                            hour: '2-digit',
                                            minute: '2-digit',
                                        }).format(new Date(event.occurred_at))}
                                    </time>
                                </Link>
                            ))}
                        </div>
                        {!overview.activity.length && empty}
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
            </main>
        </>
    );
}

function Panel({
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

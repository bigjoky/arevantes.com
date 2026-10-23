import { Head, Link, router } from '@inertiajs/react';
import {
    FileText,
    Wallet,
    CreditCard,
    TriangleAlert,
    CircleCheck,
    Banknote,
    ChevronRight,
    Zap,
} from 'lucide-react';
import {
    Area,
    AreaChart,
    CartesianGrid,
    XAxis,
    YAxis,
    Tooltip,
    ResponsiveContainer,
    Pie,
    PieChart,
    Cell,
} from 'recharts';
import {
    Panel,
    Empty,
    InvoiceTable,
    MovementTable,
    money,
    labels,
} from '@/components/billing/workspace';
import type {
    Capabilities,
    Invoice,
    Movement,
} from '@/components/billing/workspace';
import { PageHeader, PageShell } from '@/components/page-shell';
type Context = {
    year: number;
    period: string;
    from: string;
    to: string;
    fiscalAvailable: boolean;
    years: { year: number }[];
};
type Series = {
    month: string;
    invoiced: string | null;
    received: string | null;
};
const cards = [
    ['invoiced', 'Facturado en el periodo', FileText],
    ['receivable', 'Pendiente de cobro', Wallet],
    ['payable', 'Pendiente de pago', CreditCard],
    ['overdue', 'Facturas vencidas', TriangleAlert],
    ['received', 'Cobrado en el periodo', CircleCheck],
    ['paid', 'Pagado en el periodo', Banknote],
] as const;
const colors = ['#009565', '#4085ff', '#ff4b50', '#ffb743'];
export default function BillingDashboard({
    context,
    capabilities,
    metrics,
    counts,
    series,
    upcoming,
    states,
    customers,
    suppliers,
    movements,
}: {
    context: Context;
    capabilities: Capabilities;
    metrics: Record<string, string | null>;
    counts: Record<string, number | null>;
    series: Series[];
    upcoming: {
        label: string;
        count: number | null;
        amount: string | null;
        href: string;
    }[];
    states: { payment_state: string; count: number }[];
    customers: Invoice[] | null;
    suppliers: Invoice[] | null;
    movements: Movement[] | null;
}) {
    const chart = series.map((r) => ({
        month: new Intl.DateTimeFormat('es-ES', { month: 'short' }).format(
            new Date(r.month + '-15T12:00:00'),
        ),
        invoiced: r.invoiced === null ? undefined : Number(r.invoiced),
        received: r.received === null ? undefined : Number(r.received),
    }));
    const hasChart = chart.some(
        (r) => (r.invoiced ?? 0) !== 0 || (r.received ?? 0) !== 0,
    );

    return (
        <PageShell className="billing-workspace">
            <Head title="Facturación" />
            <PageHeader
                title="Facturación"
                description="Controla la emisión, recepción y seguimiento de las facturas de tu empresa."
                breadcrumbs={[
                    { title: 'Facturación', href: '/billing' },
                    { title: 'Dashboard', href: '/billing' },
                ]}
                actions={
                    <div className="billing-period">
                        <label className="sr-only" htmlFor="billing-year">
                            Ejercicio
                        </label>
                        <select
                            id="billing-year"
                            value={context.year}
                            onChange={(e) =>
                                router.get('/billing', {
                                    year: e.target.value,
                                    period: context.period,
                                })
                            }
                        >
                            {!context.years.some(
                                (y) => y.year === context.year,
                            ) && (
                                <option value={context.year}>
                                    {context.year} · sin ejercicio configurado
                                </option>
                            )}
                            {context.years.map((y) => (
                                <option key={y.year} value={y.year}>
                                    Ejercicio {y.year}
                                </option>
                            ))}
                        </select>
                        <label className="sr-only" htmlFor="billing-period">
                            Periodo
                        </label>
                        <select
                            id="billing-period"
                            value={context.period}
                            onChange={(e) =>
                                router.get('/billing', {
                                    year: context.year,
                                    period: e.target.value,
                                })
                            }
                        >
                            <option value="month">Este mes</option>
                            <option value="quarter">Este trimestre</option>
                            <option value="year">Ejercicio completo</option>
                        </select>
                    </div>
                }
            />
            {!context.fiscalAvailable && (
                <p role="status" className="billing-notice">
                    No hay un ejercicio fiscal configurado para este año. La
                    consulta usa el año natural; configura el ejercicio antes de
                    emitir.
                </p>
            )}
            <div className="billing-metrics">
                {cards.map(([key, title, Icon], index) => (
                    <Panel
                        key={key}
                        title=""
                        className={`billing-metric metric-${key}`}
                    >
                        <div className="metric-head">
                            <span className="metric-icon">
                                <Icon aria-hidden="true" />
                            </span>
                            <span>{title}</span>
                        </div>
                        <strong>{money(metrics[key])}</strong>
                        <p>
                            {metrics[key] === null
                                ? 'Sin acceso a esta información'
                                : counts[key] != null
                                  ? `${counts[key]} facturas con saldo`
                                  : 'Importe total · IVA incluido'}
                        </p>
                        <div className="billing-spark" aria-hidden="true">
                            {(key === 'invoiced' || key === 'received') &&
                            hasChart ? (
                                <ResponsiveContainer>
                                    <AreaChart data={chart}>
                                        <Area
                                            type="monotone"
                                            dataKey={key}
                                            stroke={
                                                colors[index % colors.length]
                                            }
                                            fill={colors[index % colors.length]}
                                            fillOpacity={0.12}
                                        />
                                    </AreaChart>
                                </ResponsiveContainer>
                            ) : null}
                        </div>
                    </Panel>
                ))}
            </div>
            <div className="billing-dashboard-main">
                <Panel
                    title="Evolución facturación y cobros"
                    action={
                        <div className="billing-legend">
                            <span>● Facturación</span>
                            <span>● Cobros</span>
                        </div>
                    }
                >
                    {hasChart ? (
                        <div className="billing-chart">
                            <ResponsiveContainer>
                                <AreaChart data={chart}>
                                    <CartesianGrid stroke="#e7ebef" />
                                    <XAxis dataKey="month" tickLine={false} />
                                    <YAxis
                                        tickFormatter={(v) => `${v / 1000}k €`}
                                        width={55}
                                    />
                                    <Tooltip
                                        formatter={(value) =>
                                            money(String(value))
                                        }
                                    />
                                    <Area
                                        type="monotone"
                                        dataKey="invoiced"
                                        name="Facturación"
                                        stroke="#009565"
                                        fill="#009565"
                                        fillOpacity={0.06}
                                    />
                                    {capabilities.payments && (
                                        <Area
                                            type="monotone"
                                            dataKey="received"
                                            name="Cobros"
                                            stroke="#7bdeb0"
                                            fill="#7bdeb0"
                                            fillOpacity={0.1}
                                        />
                                    )}
                                </AreaChart>
                            </ResponsiveContainer>
                        </div>
                    ) : (
                        <Empty unavailable={!capabilities.customers}>
                            No hay facturas emitidas ni cobros para este
                            ejercicio.
                        </Empty>
                    )}
                </Panel>
                <Panel
                    title="Vencimientos próximos"
                    action={
                        capabilities.customers ? (
                            <Link href="/sales?state=pending">
                                Ver todos <ChevronRight />
                            </Link>
                        ) : undefined
                    }
                >
                    <div className="billing-due">
                        {upcoming.map((r, i) => (
                            <div key={r.label}>
                                <span style={{ color: colors[i] }}>●</span>
                                <span>{r.label}</span>
                                {r.count === null ? (
                                    <span>Sin acceso</span>
                                ) : (
                                    <Link href={r.href}>
                                        {r.count} facturas
                                    </Link>
                                )}
                                <strong>{money(r.amount)}</strong>
                            </div>
                        ))}
                    </div>
                </Panel>
                <Panel title="Resumen por estado">
                    {states.length ? (
                        <div className="billing-donut">
                            <ResponsiveContainer width="50%" height={160}>
                                <PieChart>
                                    <Pie
                                        data={states}
                                        dataKey="count"
                                        nameKey="payment_state"
                                        innerRadius={45}
                                        outerRadius={65}
                                    >
                                        {states.map((r, i) => (
                                            <Cell
                                                key={r.payment_state}
                                                fill={colors[i % colors.length]}
                                            />
                                        ))}
                                    </Pie>
                                    <Tooltip formatter={(v) => String(v)} />
                                </PieChart>
                            </ResponsiveContainer>
                            <ul>
                                {states.map((r, i) => (
                                    <li key={r.payment_state}>
                                        <span
                                            style={{
                                                color: colors[
                                                    i % colors.length
                                                ],
                                            }}
                                        >
                                            ●
                                        </span>
                                        {labels[r.payment_state]}
                                        <strong>{r.count}</strong>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    ) : (
                        <Empty unavailable={!capabilities.customers} />
                    )}
                </Panel>
            </div>
            <div className="billing-dashboard-invoices">
                <Panel
                    title="Últimas facturas de clientes"
                    action={
                        capabilities.customers ? (
                            <Link href="/sales">
                                Ver todas <ChevronRight />
                            </Link>
                        ) : undefined
                    }
                >
                    <InvoiceTable rows={customers} compact />
                </Panel>
                <Panel
                    title="Últimas facturas de proveedores"
                    action={
                        capabilities.suppliers ? (
                            <Link href="/billing/suppliers">
                                Ver todas <ChevronRight />
                            </Link>
                        ) : undefined
                    }
                >
                    <InvoiceTable rows={suppliers} compact />
                </Panel>
            </div>
            <div className="billing-dashboard-bottom">
                <Panel
                    title="Cobros y pagos recientes"
                    action={
                        capabilities.payments ? (
                            <Link href="/billing/payments">
                                Ver todos <ChevronRight />
                            </Link>
                        ) : undefined
                    }
                >
                    <MovementTable rows={movements} />
                </Panel>
                <Panel title="Necesidades de facturación">
                    <Empty unavailable>
                        El seguimiento de hitos contractuales de facturación
                        está pendiente de implantación.
                    </Empty>
                </Panel>
                <Panel title="Información clave" className="billing-key">
                    <h3>
                        <Zap aria-hidden="true" /> Información clave
                    </h3>
                    <ul>
                        {['receivable', 'overdue', 'payable'].map((key) => (
                            <li key={key}>
                                <CircleCheck aria-hidden="true" />
                                {counts[key] === null
                                    ? 'Sin acceso'
                                    : `${counts[key]} facturas ${key === 'receivable' ? 'pendientes de cobro' : key === 'payable' ? 'pendientes de pago' : 'vencidas'}`}
                            </li>
                        ))}
                    </ul>
                    <p>
                        Facturación por fecha de emisión; cobros y pagos por
                        fecha efectiva. Saldos abiertos de todos los ejercicios,
                        con rectificativas aplicadas.
                    </p>
                </Panel>
            </div>
        </PageShell>
    );
}

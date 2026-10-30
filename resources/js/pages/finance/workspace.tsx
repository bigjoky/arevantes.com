import '@/../css/finance-mobile.css';
import '@/../css/finance.css';
import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    Landmark,
    Wallet,
    ArrowDownLeft,
    ArrowUpRight,
    CalendarClock,
    TrendingUp,
    Plus,
} from 'lucide-react';
import { useState } from 'react';
import {
    Area,
    AreaChart,
    Bar,
    BarChart,
    CartesianGrid,
    Line,
    ComposedChart,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
    Legend,
} from 'recharts';
import {
    Panel,
    Empty,
    QueryFilters,
    Pagination,
    InvoiceTable,
    money,
    date,
} from '@/components/billing/workspace';
import type { Filters, Invoice, Page } from '@/components/billing/workspace';
import { financeLabel } from '@/components/finance/labels';
import Form from '@/components/finance/protected-form';
import { PageShell, PageHeader } from '@/components/page-shell';
import Dialog from '@/components/projects/protected-dialog';
import { Button } from '@/components/ui/button';
import {
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { financeView } from '@/lib/finance/navigation';
export type Account = {
    id: number;
    name: string;
    bank_name: string | null;
    iban_masked: string | null;
    currency: string;
    balance: string;
    pending: string;
    updated_on: string | null;
    is_active: boolean;
    available: null;
    href: string;
};
type Movement = {
    id: number;
    source: string;
    date: string;
    value_on: string | null;
    description: string;
    reference: string | null;
    amount: string;
    account: string;
    status: string;
    href: string;
};
type Day = { date: string; in: string; out: string; closing: string };
type Manual = {
    id: number;
    description: string;
    direction: string;
    amount: string;
    due_on: string;
    probability: number;
    recurrence: string | null;
    status: string;
    financial_category: { name: string } | null;
};
type Props = {
    cashCategories: {
        name: string;
        movements: number;
        in: string;
        out: string;
    }[];
    forecastPeriods: {
        period: string;
        in: string;
        out: string;
        closing: string | null;
    }[];
    operationKey: string;
    filters: Filters & {
        area?: string;
        tab?: string;
        month?: string;
        months?: string;
        horizon?: string;
        scenario?: string;
        source?: string;
    };
    metrics: Record<string, string | null>;
    accounts: Account[];
    allAccounts: Account[];
    totals: { in: string; out: string; net: string };
    movements: Page<Movement>;
    series: { month: string; in: string; out: string; net: string }[];
    receipts: Invoice[];
    payments: Invoice[];
    due: Page<Invoice>;
    forecast: {
        closing30: string | null;
        closing90: string | null;
        available: boolean;
        openingBalance: string;
        closing: string | null;
        horizon: number;
        incoming: string;
        outgoing: string;
        lowestBalance: string;
        lowestBalanceOn: string;
        days: Day[];
        scenario: string;
    };
    manualForecasts: Page<Manual>;
    categories: { id: number; name: string; reporting_group: string }[];
    capabilities: {
        manage: boolean;
        historicalBalances: boolean;
        availableBalance: boolean;
        multiCompany: boolean;
        manualMovement: boolean;
    };
};
export function FinanceAccounts({ accounts }: { accounts: Account[] }) {
    return (
        <div className="finance-accounts">
            {accounts.length ? (
                accounts.map((a) => (
                    <Link
                        key={a.id}
                        href={a.href}
                        className="finance-bank-card"
                    >
                        <Landmark aria-hidden />
                        <strong>{a.bank_name || a.name}</strong>
                        <span>{a.name}</span>
                        <span>{a.iban_masked || 'Sin IBAN registrado'}</span>
                        <b>
                            {new Intl.NumberFormat('es-ES', {
                                style: 'currency',
                                currency: a.currency,
                            }).format(Number(a.balance))}
                        </b>
                        <small>
                            Saldo registrado ·{' '}
                            {a.updated_on
                                ? date(a.updated_on)
                                : 'Sin movimientos'}
                        </small>
                        <small>
                            {a.is_active ? 'Activa' : 'Inactiva'} · Pendiente de
                            conciliar: {money(a.pending)}
                        </small>
                    </Link>
                ))
            ) : (
                <Empty />
            )}
        </div>
    );
}
function Kpi({
    label,
    value,
    icon: Icon,
    note,
}: {
    label: string;
    value: string | null;
    icon: typeof Wallet;
    note: string;
}) {
    return (
        <Panel title="">
            <div className="finance-kpi">
                <Icon aria-hidden />
                <div>
                    <span>{label}</span>
                    <strong>
                        {value === null ? 'No disponible' : money(value)}
                    </strong>
                    <small>{note}</small>
                </div>
            </div>
        </Panel>
    );
}
function Chart({
    series,
    kind,
}: {
    series: Props['series'];
    kind: 'net' | 'bars';
}) {
    return series.some((r) => Number(r.in) !== 0 || Number(r.out) !== 0) ? (
        <div className="finance-chart">
            <ResponsiveContainer width="100%" height="100%">
                {kind === 'bars' ? (
                    <BarChart data={series}>
                        <CartesianGrid strokeDasharray="3 3" vertical={false} />
                        <XAxis dataKey="month" tick={{ fontSize: 11 }} />
                        <YAxis tick={{ fontSize: 11 }} />
                        <Tooltip formatter={(v) => money(String(v))} />
                        <Legend />
                        <Bar
                            dataKey="in"
                            name="Entradas"
                            fill="#008b65"
                            radius={[3, 3, 0, 0]}
                        />
                        <Bar
                            dataKey="out"
                            name="Salidas"
                            fill="#f79532"
                            radius={[3, 3, 0, 0]}
                        />
                    </BarChart>
                ) : (
                    <AreaChart data={series}>
                        <CartesianGrid vertical={false} />
                        <XAxis dataKey="month" tick={{ fontSize: 11 }} />
                        <YAxis tick={{ fontSize: 11 }} />
                        <Tooltip formatter={(v) => money(String(v))} />
                        <Area
                            dataKey="net"
                            name="Flujo neto registrado"
                            stroke="#008b65"
                            fill="#d5f3e7"
                        />
                    </AreaChart>
                )}
            </ResponsiveContainer>
        </div>
    ) : (
        <Empty />
    );
}
function MovementTable({ rows }: { rows: Movement[] }) {
    return rows.length ? (
        <>
            <div className="finance-desktop">
                <div className="finance-table-scroll">
                    <table>
                        <thead>
                            <tr>
                                {[
                                    'Fecha',
                                    'Concepto',
                                    'Tipo',
                                    'Cuenta',
                                    'Importe',
                                    'Estado',
                                    'Acciones',
                                ].map((h) => (
                                    <th key={h}>{h}</th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((r) => (
                                <tr key={`${r.source}-${r.id}`}>
                                    <td>{date(r.date)}</td>
                                    <td>
                                        <Link href={r.href}>
                                            {r.description}
                                        </Link>
                                        <small>{r.reference}</small>
                                    </td>
                                    <td>
                                        {Number(r.amount) > 0
                                            ? 'Entrada'
                                            : 'Salida'}
                                    </td>
                                    <td>{r.account}</td>
                                    <td
                                        className={
                                            Number(r.amount) < 0
                                                ? 'finance-negative'
                                                : 'finance-positive'
                                        }
                                    >
                                        {money(r.amount)}
                                    </td>
                                    <td>
                                        {r.status === 'unmatched'
                                            ? 'Sin vincular al banco'
                                            : r.status === 'pending'
                                              ? 'Sin conciliar'
                                              : r.status === 'reconciled'
                                                ? 'Conciliado'
                                                : 'Contabilizado'}
                                    </td>
                                    <td>
                                        <Link
                                            href={r.href}
                                            aria-label={`Abrir ${r.description}`}
                                        >
                                            •••
                                        </Link>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
            <div className="finance-mobile">
                {rows.map((r) => (
                    <Link
                        className="finance-movement-card"
                        href={r.href}
                        key={`${r.source}-${r.id}`}
                    >
                        <span>
                            {date(r.date)} · {r.account}
                        </span>
                        <strong>{r.description}</strong>
                        <b>{money(r.amount)}</b>
                        <small>
                            {r.status === 'pending'
                                ? 'Sin conciliar'
                                : r.status === 'unmatched'
                                  ? 'Sin vincular al banco'
                                  : 'Procesado'}
                        </small>
                    </Link>
                ))}
            </div>
        </>
    ) : (
        <Empty />
    );
}
function ForecastDialog({
    categories,
    operationKey,
}: {
    categories: Props['categories'];
    operationKey: string;
}) {
    const [open, setOpen] = useState(false);
    const [key, setKey] = useState(operationKey);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button>
                    <Plus />
                    Nueva previsión
                </Button>
            </DialogTrigger>
            <DialogContent className="finance-mobile-dialog">
                <DialogHeader>
                    <DialogTitle>Nueva previsión manual</DialogTitle>
                </DialogHeader>
                <Form
                    action="/finance/forecast-items"
                    method="post"
                    onSuccess={() => {
                        setOpen(false);
                        setKey(crypto.randomUUID());
                    }}
                >
                    {({ processing, errors }) => (
                        <div className="finance-form">
                            <input
                                type="hidden"
                                name="operation_key"
                                value={key}
                            />
                            <label>
                                Concepto
                                <Input
                                    name="description"
                                    required
                                    maxLength={255}
                                />
                            </label>
                            <label>
                                Tipo
                                <select name="direction">
                                    <option value="in">Entrada</option>
                                    <option value="out">Salida</option>
                                </select>
                            </label>
                            <label>
                                Categoría
                                <select name="financial_category_id">
                                    <option value="">
                                        Categoría por defecto
                                    </option>
                                    {categories.map((c) => (
                                        <option key={c.id} value={c.id}>
                                            {c.name}
                                        </option>
                                    ))}
                                </select>
                            </label>
                            <label>
                                Importe EUR
                                <Input
                                    name="amount"
                                    type="number"
                                    step="0.01"
                                    min="0.01"
                                    required
                                />
                            </label>
                            <label>
                                Fecha prevista
                                <Input name="due_on" type="date" required />
                            </label>
                            <label>
                                Probabilidad %
                                <Input
                                    name="probability"
                                    type="number"
                                    min="0"
                                    max="100"
                                    defaultValue="100"
                                    required
                                />
                            </label>
                            <label>
                                Recurrencia
                                <select name="recurrence">
                                    <option value="">Una vez</option>
                                    <option value="monthly">Mensual</option>
                                    <option value="quarterly">
                                        Trimestral
                                    </option>
                                    <option value="annual">Anual</option>
                                </select>
                            </label>
                            {Object.entries(errors).map(([k, v]) => (
                                <p role="alert" key={k}>
                                    {v}
                                </p>
                            ))}
                            <p>
                                Registra únicamente importes no incluidos en
                                facturas u otras previsiones.
                            </p>
                            <Button disabled={processing}>
                                Guardar previsión
                            </Button>
                        </div>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
export default function FinanceWorkspace(p: Props) {
    const { url } = usePage();
    const area = p.filters.area;
    const tab =
        p.filters.tab ?? (area === 'treasury' ? 'movements' : 'overview');
    const title =
        area === 'treasury'
            ? 'Tesorería'
            : area === 'forecasts'
              ? 'Previsiones de tesorería'
              : 'Finanzas';
    const update = (key: string, value: string) =>
        router.get(
            '/finance',
            { ...p.filters, [key]: value, page: undefined },
            { preserveScroll: true },
        );
    const forecastDays = p.forecast.days.slice(0, p.forecast.horizon + 1);

    return (
        <PageShell className="finance-workspace">
            <Head title={title} />
            <PageHeader
                title={title}
                description={
                    area === 'treasury'
                        ? 'Consulta la posición de tesorería y los movimientos de tu empresa.'
                        : area === 'forecasts'
                          ? 'Anticipa tus necesidades de liquidez con cobros y pagos previstos.'
                          : 'Controla tu liquidez, tus bancos y las previsiones de tesorería.'
                }
                breadcrumbs={[
                    { title: 'Finanzas', href: '/finance' },
                    ...(area ? [{ title, href: `/finance?area=${area}` }] : []),
                ]}
                actions={
                    area === 'forecasts' && p.capabilities.manage ? (
                        <ForecastDialog
                            categories={p.categories}
                            operationKey={p.operationKey}
                        />
                    ) : (
                        <Button asChild variant="outline">
                            <Link href="/finance?view=manage">
                                Más herramientas
                            </Link>
                        </Button>
                    )
                }
            />
            <nav
                className="finance-mobile-access"
                aria-label="Accesos de tesorería"
            >
                <Button asChild variant="outline">
                    <Link
                        href={financeView(url, {
                            area: 'treasury',
                            tab: 'movements',
                        })}
                    >
                        Tesorería
                    </Link>
                </Button>
                <Button asChild variant="outline">
                    <Link href="/billing/payments?tab=due">Vencimientos</Link>
                </Button>
                <Button asChild variant="outline">
                    <Link href="/billing/payments">Cobros y pagos</Link>
                </Button>
                <Button asChild variant="outline">
                    <Link href="/banking?tab=reconciliation">
                        Bancos y conciliación
                    </Link>
                </Button>
            </nav>
            <div className="finance-controls">
                <label>
                    Periodo
                    <Input
                        type="month"
                        value={
                            p.filters.month ??
                            new Date().toISOString().slice(0, 7)
                        }
                        onChange={(e) => update('month', e.target.value)}
                    />
                </label>
                <label>
                    Cuenta
                    <select
                        value={p.filters.account ?? ''}
                        onChange={(e) => update('account', e.target.value)}
                    >
                        <option value="">Todas las cuentas</option>
                        {p.allAccounts.map((a) => (
                            <option key={a.id} value={a.id}>
                                {a.name} · {a.currency}
                            </option>
                        ))}
                    </select>
                </label>
            </div>
            {area && (
                <nav className="finance-tabs" aria-label="Vistas de Finanzas">
                    {(area === 'treasury'
                        ? [
                              ['movements', 'Movimientos'],
                              ['position', 'Posición de tesorería'],
                              ['categories', 'Resumen por categorías'],
                              ['analysis', 'Análisis'],
                          ]
                        : [
                              ['overview', 'Vista general'],
                              ['detail', 'Detalle de previsiones'],
                              ['scenarios', 'Escenarios'],
                          ]
                    ).map(([key, label]) => (
                        <Link
                            key={key}
                            href={financeView(url, { area, tab: key })}
                            aria-current={tab === key ? 'page' : undefined}
                        >
                            {label}
                        </Link>
                    ))}
                </nav>
            )}
            {!area ? (
                <>
                    <div className="finance-six">
                        <Kpi
                            label="Saldo bancario registrado"
                            value={p.metrics.registered}
                            icon={Wallet}
                            note="Saldo inicial + movimientos importados EUR"
                        />
                        <Kpi
                            label="Cobros pendientes"
                            value={p.metrics.receivable}
                            icon={ArrowDownLeft}
                            note="Saldos reales de Facturación"
                        />
                        <Kpi
                            label="Pagos pendientes"
                            value={p.metrics.payable}
                            icon={ArrowUpRight}
                            note="Saldos reales de Facturación"
                        />
                        <Kpi
                            label="Flujo bancario del periodo"
                            value={p.metrics.flow}
                            icon={TrendingUp}
                            note="Extractos · transferencias propias excluidas"
                        />
                        <Kpi
                            label="Previsión a 30 días"
                            value={p.metrics.forecast30}
                            icon={CalendarClock}
                            note="Estimación sobre saldo registrado"
                        />
                        <Kpi
                            label="Vencimientos próximos"
                            value={p.metrics.due7}
                            icon={CalendarClock}
                            note="Cobros y pagos · próximos 7 días"
                        />
                    </div>
                    <div className="finance-two">
                        <Panel
                            title="Evolución del flujo bancario"
                            action={
                                <select
                                    aria-label="Periodo del gráfico"
                                    value={p.filters.months ?? '12'}
                                    onChange={(e) =>
                                        update('months', e.target.value)
                                    }
                                >
                                    {[3, 6, 12].map((n) => (
                                        <option key={n} value={n}>
                                            Últimos {n} meses
                                        </option>
                                    ))}
                                </select>
                            }
                        >
                            <Chart series={p.series} kind="net" />
                            <p>
                                Flujo neto de extractos; no representa un
                                histórico de saldo disponible.
                            </p>
                        </Panel>
                        <Panel title="Entradas y salidas registradas">
                            <Chart series={p.series} kind="bars" />
                        </Panel>
                    </div>
                    <div className="finance-two">
                        <Panel
                            title="Próximos cobros"
                            action={
                                <Link href="/billing/payments?tab=due&kind=receipt">
                                    Ver todos
                                </Link>
                            }
                        >
                            <InvoiceTable rows={p.receipts} compact />
                        </Panel>
                        <Panel
                            title="Próximos pagos"
                            action={
                                <Link href="/billing/payments?tab=due&kind=payment">
                                    Ver todos
                                </Link>
                            }
                        >
                            <InvoiceTable rows={p.payments} compact />
                        </Panel>
                    </div>
                </>
            ) : area === 'treasury' ? (
                <>
                    {tab === 'position' ? (
                        <Panel title="Posición por cuenta">
                            <FinanceAccounts accounts={p.accounts} />
                            <p>
                                El saldo disponible confirmado no está
                                disponible. Se muestran movimientos registrados
                                hasta hoy; la conciliación pendiente no se resta
                                otra vez.
                            </p>
                        </Panel>
                    ) : tab === 'categories' ? (
                        <Panel title="Clasificación de movimientos">
                            {p.cashCategories.length ? (
                                <div className="finance-four">
                                    {p.cashCategories.map((c) => (
                                        <div
                                            className="finance-movement-card"
                                            key={c.name}
                                        >
                                            <strong>{c.name}</strong>
                                            <small>
                                                {c.movements} movimientos
                                            </small>
                                            <span>Entradas: {money(c.in)}</span>
                                            <span>Salidas: {money(c.out)}</span>
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <Empty />
                            )}
                            <p>
                                La clasificación financiera de extractos no está
                                disponible para todos los movimientos. Consulta
                                las categorías reales y sus reglas en las
                                herramientas existentes.
                            </p>
                            <Button variant="outline" asChild>
                                <Link href="/finance?view=manage">
                                    Ver categorías y análisis existente
                                </Link>
                            </Button>
                        </Panel>
                    ) : tab === 'analysis' ? (
                        <>
                            <div className="finance-two">
                                <Panel title="Entradas y salidas">
                                    <Chart series={p.series} kind="bars" />
                                </Panel>
                                <Panel title="Flujo neto registrado">
                                    <Chart series={p.series} kind="net" />
                                </Panel>
                            </div>
                        </>
                    ) : (
                        <Panel title="Movimientos de tesorería">
                            <QueryFilters path="/finance" filters={p.filters}>
                                <input
                                    type="hidden"
                                    name="area"
                                    value="treasury"
                                />
                                <input
                                    type="hidden"
                                    name="tab"
                                    value="movements"
                                />
                                <label>
                                    Origen
                                    <select
                                        name="source"
                                        defaultValue={
                                            p.filters.source ?? 'bank'
                                        }
                                    >
                                        <option value="bank">
                                            Extractos bancarios
                                        </option>
                                        <option value="unmatched">
                                            Cobros y pagos sin vincular al banco
                                        </option>
                                    </select>
                                </label>
                                <label>
                                    Tipo
                                    <select
                                        name="type"
                                        defaultValue={p.filters.type ?? ''}
                                    >
                                        <option value="">Todos</option>
                                        <option value="in">Entradas</option>
                                        <option value="out">Salidas</option>
                                    </select>
                                </label>
                                <label>
                                    Desde
                                    <Input
                                        type="date"
                                        name="from"
                                        defaultValue={p.filters.from}
                                    />
                                </label>
                                <label>
                                    Hasta
                                    <Input
                                        type="date"
                                        name="to"
                                        defaultValue={p.filters.to}
                                    />
                                </label>
                                <label>
                                    Ordenar
                                    <select
                                        name="sort"
                                        defaultValue={p.filters.sort ?? 'date'}
                                    >
                                        <option value="date">Fecha</option>
                                        <option value="amount">Importe</option>
                                        <option value="description">
                                            Concepto
                                        </option>
                                        <option value="account">Cuenta</option>
                                    </select>
                                </label>
                                <label>
                                    Dirección
                                    <select
                                        name="direction"
                                        defaultValue={
                                            p.filters.direction ?? 'desc'
                                        }
                                    >
                                        <option value="desc">
                                            Descendente
                                        </option>
                                        <option value="asc">Ascendente</option>
                                    </select>
                                </label>
                            </QueryFilters>
                            <div className="finance-four">
                                <Kpi
                                    label="Entradas del periodo"
                                    value={p.totals.in}
                                    icon={ArrowDownLeft}
                                    note="Movimientos bancarios"
                                />
                                <Kpi
                                    label="Salidas del periodo"
                                    value={p.totals.out}
                                    icon={ArrowUpRight}
                                    note="Movimientos bancarios"
                                />
                                <Kpi
                                    label="Flujo neto"
                                    value={p.totals.net}
                                    icon={TrendingUp}
                                    note="Transferencias propias excluidas"
                                />
                                <Kpi
                                    label="Saldo registrado"
                                    value={p.metrics.registered}
                                    icon={Wallet}
                                    note="Continuidad entre ejercicios"
                                />
                            </div>
                            <MovementTable rows={p.movements.data} />
                            <Pagination page={p.movements} noun="movimientos" />
                            <p>
                                Los cobros/pagos vinculados a un extracto
                                aparecen una sola vez. Los no vinculados se
                                consultan aparte y no incrementan el saldo
                                bancario.
                            </p>
                        </Panel>
                    )}
                </>
            ) : (
                <>
                    <div className="finance-controls">
                        <label>
                            Horizonte
                            <select
                                value={String(p.forecast.horizon)}
                                onChange={(e) =>
                                    update('horizon', e.target.value)
                                }
                            >
                                {[7, 30, 60, 90].map((n) => (
                                    <option key={n} value={n}>
                                        Próximos {n} días
                                    </option>
                                ))}
                            </select>
                        </label>
                        <label>
                            Escenario
                            <select
                                value={p.forecast.scenario}
                                onChange={(e) =>
                                    update('scenario', e.target.value)
                                }
                            >
                                <option value="probable">Probable</option>
                                <option value="conservative">
                                    Conservador
                                </option>
                                <option value="optimistic">Optimista</option>
                            </select>
                        </label>
                    </div>
                    {tab === 'detail' ? (
                        <>
                            <Panel title="Obligaciones con vencimiento">
                                <InvoiceTable rows={p.due.data} />
                                <Pagination page={p.due} noun="vencimientos" />
                            </Panel>
                            <Panel title="Previsiones manuales">
                                <div className="finance-table-scroll">
                                    <table>
                                        <thead>
                                            <tr>
                                                <th>Concepto</th>
                                                <th>Fecha</th>
                                                <th>Importe</th>
                                                <th>Estado</th>
                                                <th>Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {p.manualForecasts.data.map((f) => (
                                                <tr key={f.id}>
                                                    <td
                                                        data-label={financeLabel(
                                                            'Concepto',
                                                        )}
                                                    >
                                                        {f.description}
                                                        <small>
                                                            {
                                                                f
                                                                    .financial_category
                                                                    ?.name
                                                            }{' '}
                                                            · {f.probability}% ·{' '}
                                                            {f.recurrence ??
                                                                'Una vez'}
                                                        </small>
                                                    </td>
                                                    <td
                                                        data-label={financeLabel(
                                                            'Fecha',
                                                        )}
                                                    >
                                                        {date(f.due_on)}
                                                    </td>
                                                    <td
                                                        data-label={financeLabel(
                                                            'Importe',
                                                        )}
                                                    >
                                                        {f.direction === 'out'
                                                            ? '−'
                                                            : ''}
                                                        {money(f.amount)}
                                                    </td>
                                                    <td
                                                        data-label={financeLabel(
                                                            'Estado',
                                                        )}
                                                    >
                                                        {f.status}
                                                    </td>
                                                    <td
                                                        data-label={financeLabel(
                                                            'Acciones',
                                                        )}
                                                    >
                                                        {p.capabilities
                                                            .manage &&
                                                        f.status ===
                                                            'planned' ? (
                                                            <Form
                                                                action={`/finance/forecast-items/${f.id}`}
                                                                method="patch"
                                                            >
                                                                <input
                                                                    type="hidden"
                                                                    name="status"
                                                                    value="cancelled"
                                                                />
                                                                <Button
                                                                    size="sm"
                                                                    variant="outline"
                                                                >
                                                                    Anular
                                                                    previsión
                                                                </Button>
                                                            </Form>
                                                        ) : (
                                                            <Link href="/finance?view=manage#forecast-items">
                                                                Ver detalle
                                                            </Link>
                                                        )}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                                <Pagination
                                    page={p.manualForecasts}
                                    noun="previsiones"
                                />
                            </Panel>
                        </>
                    ) : (
                        <>
                            <div className="finance-five">
                                <Kpi
                                    label="Saldo inicial registrado"
                                    value={
                                        p.forecast.available
                                            ? p.forecast.openingBalance
                                            : null
                                    }
                                    icon={Wallet}
                                    note="Estimación base · cuentas EUR activas"
                                />
                                <Kpi
                                    label="Cobros previstos"
                                    value={p.forecast.incoming}
                                    icon={ArrowDownLeft}
                                    note="Según escenario seleccionado"
                                />
                                <Kpi
                                    label="Pagos previstos"
                                    value={p.forecast.outgoing}
                                    icon={ArrowUpRight}
                                    note="Obligaciones y previsiones"
                                />
                                <Kpi
                                    label="Saldo estimado a 30 días"
                                    value={p.forecast.closing30}
                                    icon={CalendarClock}
                                    note="Estimación a 30 días"
                                />
                                <Kpi
                                    label="Saldo estimado a 90 días"
                                    value={p.forecast.closing90}
                                    icon={CalendarClock}
                                    note="Estimación a 90 días"
                                />
                            </div>
                            <Panel title="Previsión de tesorería">
                                {p.forecast.available ? (
                                    <div className="finance-chart">
                                        <ResponsiveContainer
                                            width="100%"
                                            height="100%"
                                        >
                                            <ComposedChart data={forecastDays}>
                                                <CartesianGrid
                                                    vertical={false}
                                                />
                                                <XAxis
                                                    dataKey="date"
                                                    tick={{ fontSize: 10 }}
                                                />
                                                <YAxis />
                                                <Tooltip
                                                    formatter={(v) =>
                                                        money(String(v))
                                                    }
                                                />
                                                <Legend />
                                                <Bar
                                                    dataKey="in"
                                                    name="Entradas previstas"
                                                    fill="#94d9bc"
                                                />
                                                <Bar
                                                    dataKey="out"
                                                    name="Salidas previstas"
                                                    fill="#ffad65"
                                                />
                                                <Line
                                                    dataKey="closing"
                                                    name="Saldo estimado"
                                                    stroke="#008b65"
                                                    dot={false}
                                                />
                                            </ComposedChart>
                                        </ResponsiveContainer>
                                    </div>
                                ) : (
                                    <p>
                                        No hay saldo base de cuentas EUR activas
                                        para este ámbito. Las obligaciones
                                        siguen disponibles en el detalle.
                                    </p>
                                )}
                                <p>
                                    Estimación: saldo registrado más entradas
                                    previstas menos salidas previstas. Vencidos
                                    se trasladan a hoy; vencimientos sin fecha
                                    no se inventan. Probable: 90% de cobros;
                                    conservador: 70%; optimista: 100%.
                                    Previsiones manuales usan su probabilidad y
                                    recurrencia registradas.
                                </p>
                                {p.forecast.available && (
                                    <p>
                                        Mínimo estimado en 90 días:{' '}
                                        {money(p.forecast.lowestBalance)} ·{' '}
                                        {date(p.forecast.lowestBalanceOn)}
                                    </p>
                                )}
                            </Panel>
                            {tab === 'scenarios' && (
                                <Panel title="Supuestos del escenario">
                                    <p>
                                        Los escenarios reutilizan las reglas
                                        existentes del ERP; no son una
                                        predicción garantizada. Los compromisos
                                        de compra descuentan facturas derivadas.
                                        No se prevé el valor completo de un
                                        presupuesto o proyecto como ingreso.
                                    </p>
                                </Panel>
                            )}
                        </>
                    )}
                    <Panel title="Resumen por periodo">
                        <div className="finance-four">
                            {p.forecastPeriods.map((period) => (
                                <div
                                    key={period.period}
                                    className="finance-movement-card"
                                >
                                    <strong>{period.period}</strong>
                                    <span>Entradas: {money(period.in)}</span>
                                    <span>Salidas: {money(period.out)}</span>
                                    <b>
                                        Saldo estimado:{' '}
                                        {period.closing === null
                                            ? 'No disponible'
                                            : money(period.closing)}
                                    </b>
                                </div>
                            ))}
                        </div>
                    </Panel>
                </>
            )}
            <p className="finance-footnote">
                Importes EUR. Saldo disponible confirmado, histórico completo de
                saldos e aislamiento multientidad: capacidades pendientes. Las
                facturas se gestionan en Facturación; no se duplican movimientos
                ni se realizan pagos bancarios desde estas vistas.
            </p>
        </PageShell>
    );
}

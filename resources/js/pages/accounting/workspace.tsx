import { Form, Head, Link, router } from '@inertiajs/react';
import {
    BookOpen,
    CircleDollarSign,
    Download,
    FileText,
    Landmark,
    Plus,
    Scale,
    Search,
} from 'lucide-react';
import { useState } from 'react';
import {
    Area,
    AreaChart,
    Bar,
    BarChart,
    CartesianGrid,
    Legend,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import {
    Empty,
    Panel,
    Pagination,
    date,
    money,
} from '@/components/billing/workspace';
import type { Page } from '@/components/billing/workspace';
import { PageHeader, PageShell, PageTabs } from '@/components/page-shell';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import '@/../css/accounting.css';

export type Entry = {
    id: number;
    number: string;
    description: string;
    entry_date: string;
    status: string;
    entry_kind: string;
    debit: string;
    credit: string;
    href: string;
    annotated_description?: string | null;
    lines: Line[];
    source_reference?: string | null;
    creator?: string | null;
    correction_links?: { id: number; entry_id: number | null }[];
};
export type Line = {
    id: number;
    code: string;
    name: string;
    description: string | null;
    debit: string;
    credit: string;
};
type Account = {
    id: number;
    code: string;
    name: string;
    type: string;
    parent_code: string | null;
    level: number;
    is_active: boolean;
    is_postable: boolean;
    balance: string;
    opening: string;
    net: string;
    debit: string;
    credit: string;
};
type Filters = Record<string, string | number> & {
    year: number;
    from: string;
    to: string;
};
type Props = {
    kinds: string[];
    area: string;
    view: string;
    filters: Filters;
    company: { legal_name: string } | null;
    fiscalYear: { year: number; status: string } | null;
    summary: {
        assets: string;
        liabilities: string;
        equity: string;
        income: string;
        expenses: string;
        result: string;
        unclosedResult: string;
        difference: string;
        equityAndLiabilities: string;
        postedEntries: number;
        draftEntries: number;
        provisional: boolean;
    };
    series: {
        month: string;
        income: string;
        expenses: string;
        result: string;
    }[];
    entries: Page<Entry>;
    accounts: Page<Account>;
    groups: { code: string; balance: string }[];
    reportAccounts: Account[];
    ledger: null | {
        account: { code: string; name: string };
        opening: string;
        lines: Page<{
            id: number;
            entry_id: number;
            number: string;
            entry_date: string;
            description: string;
            debit: string;
            credit: string;
            running: string;
        }>;
    };
    capabilities: {
        manage: boolean;
        accounts: boolean;
        books: boolean;
        multiCompany: boolean;
        statutoryMapping: boolean;
        import: boolean;
    };
};
export const entryStatus = (status: string) =>
    status === 'posted'
        ? 'Contabilizado'
        : status === 'draft'
          ? 'Borrador'
          : status;
const kindLabels: Record<string, string> = {
    asset_capitalization: 'Capitalización de activo',
    bank_reclassification: 'Reclasificación bancaria',
    creditor_correction: 'Corrección de acreedor',
    expense_correction: 'Corrección de gasto',
    automatic: 'Automático',
    manual: 'Manual',
    document: 'Documento',
    payment: 'Pago',
    reversal: 'Reversión',
    opening: 'Apertura',
    closing: 'Cierre',
    accrual: 'Periodificación',
    adjustment: 'Ajuste',
    bank: 'Banco',
    closing_reversal: 'Reversión de cierre',
    opening_reversal: 'Reversión de apertura',
    tax_settlement: 'Liquidación de IVA',
};
export const accountTypes: Record<string, string> = {
    asset: 'Activo',
    liability: 'Pasivo',
    equity: 'Patrimonio neto',
    income: 'Ingreso',
    expense: 'Gasto',
};
const titles: Record<string, string> = {
    overview: 'Contabilidad',
    entries: 'Asientos',
    accounts: 'Plan contable',
    reports: 'Informes contables',
    books: 'Libros oficiales',
};
const descriptions: Record<string, string> = {
    overview: 'Vista general de la situación contable de tu empresa.',
    entries: 'Consulta, crea y gestiona los asientos contables.',
    accounts: 'Gestiona las cuentas contables y su estructura.',
    reports: 'Consulta los principales informes contables.',
    books: 'Consulta la información disponible para preparar tus libros.',
};
export function State({ status }: { status: string }) {
    return (
        <span className={`accounting-state ${status}`}>
            {entryStatus(status)}
        </span>
    );
}
export function EntryTable({
    entries,
    lines = false,
}: {
    entries: Page<Entry>;
    lines?: boolean;
}) {
    return (
        <>
            <div className="accounting-table">
                <table>
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Asiento</th>
                            <th>Tipo</th>
                            <th>Concepto</th>
                            <th>Debe</th>
                            <th>Haber</th>
                            <th>Estado</th>
                            <th>
                                <span className="sr-only">Acciones</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        {entries.data.map((e) => (
                            <tr key={e.id}>
                                <td>{date(e.entry_date)}</td>
                                <td>
                                    <Link href={e.href}>{e.number}</Link>
                                </td>
                                <td>
                                    {kindLabels[e.entry_kind] ?? e.entry_kind}
                                </td>
                                <td>
                                    <Link href={e.href}>{e.description}</Link>
                                    {e.annotated_description && (
                                        <p className="text-sm">
                                            Anotación: {e.annotated_description}
                                        </p>
                                    )}
                                    {e.source_reference && (
                                        <p className="text-xs">
                                            Documento: {e.source_reference}
                                        </p>
                                    )}
                                    {e.creator && (
                                        <p className="text-xs">
                                            Usuario: {e.creator}
                                        </p>
                                    )}
                                    {e.correction_links?.map((c) => (
                                        <p key={c.id} className="text-xs">
                                            <Link
                                                href={
                                                    c.entry_id
                                                        ? `/accounting/entries/${c.entry_id}`
                                                        : e.href
                                                }
                                            >
                                                {c.entry_id
                                                    ? 'Rectificación relacionada'
                                                    : 'Anotación auditada'}
                                            </Link>
                                        </p>
                                    ))}
                                    {lines &&
                                        e.lines.map((l) => (
                                            <div
                                                className="accounting-line"
                                                key={l.id}
                                            >
                                                <span>
                                                    {l.code} · {l.name}
                                                </span>
                                                <span>
                                                    {money(l.debit)} /{' '}
                                                    {money(l.credit)}
                                                </span>
                                            </div>
                                        ))}
                                </td>
                                <td>{money(e.debit)}</td>
                                <td>{money(e.credit)}</td>
                                <td>
                                    <State status={e.status} />
                                </td>
                                <td>
                                    <Link
                                        aria-label="Ver asiento"
                                        href={e.href}
                                    >
                                        ···
                                    </Link>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            <div className="accounting-mobile-list">
                {entries.data.map((e) => (
                    <Link
                        key={e.id}
                        href={e.href}
                        className="accounting-mobile-row"
                    >
                        <span>
                            {date(e.entry_date)} · {e.number}
                        </span>
                        <strong>{e.description}</strong>
                        <span>
                            {money(e.debit)} <State status={e.status} />
                        </span>
                        {lines &&
                            e.lines.map((l) => (
                                <small key={l.id}>
                                    {l.code} · {l.name}: {money(l.debit)} /{' '}
                                    {money(l.credit)}
                                </small>
                            ))}
                    </Link>
                ))}
            </div>
            {!entries.total && (
                <Empty>No hay asientos para estos filtros.</Empty>
            )}
            <Pagination noun="asientos" page={entries} />
        </>
    );
}

export default function AccountingWorkspace(p: Props) {
    const { area, filters: f, summary: s } = p;
    const [months, setMonths] = useState(12);
    const base =
        area === 'accounts'
            ? '/accounting/accounts'
            : area === 'books'
              ? '/workspace/modules/accounting/books'
              : '/accounting';
    const href = (values: Record<string, string | number>) =>
        `${base}?${new URLSearchParams(
            Object.entries({ ...f, area, view: p.view, ...values })
                .filter(([, v]) => v !== null && v !== undefined && v !== '')
                .map(([k, v]) => [k, String(v)]),
        )}`;
    const tabs = (items: [string, string][], key = 'tab') => (
        <PageTabs>
            {items.map(([id, label]) => (
                <Link
                    key={id}
                    href={href({ [key]: id, page: 1 })}
                    className={
                        (key === 'view'
                            ? p.view
                            : (f.tab ??
                              (area === 'books' ? 'commercial' : 'all'))) === id
                            ? 'active'
                            : ''
                    }
                >
                    {label}
                </Link>
            ))}
        </PageTabs>
    );
    const [chartYear, chartMonth] = f.to.split('-').map(Number);
    const firstChartMonth = new Date(
        Date.UTC(chartYear, chartMonth - months, 1),
    )
        .toISOString()
        .slice(0, 7);
    const chart = p.series
        .filter(
            (r) => r.month >= firstChartMonth && r.month <= f.to.slice(0, 7),
        )
        .map((r) => ({
            ...r,
            income: Number(r.income),
            expenses: Number(r.expenses),
            result: Number(r.result),
        }));
    const exportHref =
        '/accounting/export?' +
        new URLSearchParams({
            year: String(f.year),
            from: f.from,
            to: f.to,
            ...(f.account ? { account: String(f.account) } : {}),
        });
    const manageHref = `/accounting?area=management&year=${f.year}`;

    return (
        <>
            <Head title={titles[area]} />
            <PageShell mode="full" className="accounting-workspace">
                <PageHeader
                    title={titles[area]}
                    description={descriptions[area]}
                    secondaryNavigation={null}
                    metadata={
                        <>
                            {p.company?.legal_name ?? 'Empresa sin configurar'}{' '}
                            · {date(f.from)} — {date(f.to)}
                        </>
                    }
                    actions={
                        <>
                            <label className="accounting-year">
                                Ejercicio
                                <Input
                                    type="number"
                                    min={2020}
                                    max={2100}
                                    defaultValue={f.year}
                                    onBlur={(e) => {
                                        if (
                                            e.target.value &&
                                            Number(e.target.value) !== f.year
                                        ) {
                                            router.get(base, {
                                                area,
                                                view: p.view,
                                                year: e.target.value,
                                            });
                                        }
                                    }}
                                />
                            </label>
                            {area === 'entries' && p.capabilities.manage && (
                                <Button asChild>
                                    <Link
                                        href={`/accounting/entries/create?year=${f.year}`}
                                    >
                                        <Plus />
                                        Nuevo asiento
                                    </Link>
                                </Button>
                            )}
                            <Button variant="outline" asChild>
                                <Link href="/accounting/control">
                                    Configuración contable
                                </Link>
                            </Button>
                            <Button variant="outline" asChild>
                                <Link href="/accounting/review">
                                    Revisión contable
                                </Link>
                            </Button>
                            {area === 'accounts' && p.capabilities.accounts && (
                                <Button asChild>
                                    <Link href="/accounting/accounts?mode=manage">
                                        <Plus />
                                        Nueva cuenta
                                    </Link>
                                </Button>
                            )}
                        </>
                    }
                />
                {area === 'overview' && (
                    <>
                        <div className="accounting-kpis">
                            {[
                                [Landmark, 'Total activo', s.assets],
                                [Scale, 'Total pasivo', s.liabilities],
                                [CircleDollarSign, 'Patrimonio neto', s.equity],
                                [FileText, 'Resultado del ejercicio', s.result],
                            ].map(([Icon, label, value]) => {
                                const I = Icon as typeof Landmark;

                                return (
                                    <Panel
                                        key={String(label)}
                                        title={String(label)}
                                        className="accounting-kpi"
                                    >
                                        <I />
                                        <strong>{money(String(value))}</strong>
                                        <small>
                                            Saldos contabilizados · provisional
                                        </small>
                                    </Panel>
                                );
                            })}
                        </div>
                        <p className="accounting-notice">
                            Información provisional. La estructura oficial de
                            las cuentas anuales requiere validación contable.
                        </p>
                        {s.difference !== '0.00' && (
                            <p role="alert" className="accounting-alert">
                                Descuadre de la ecuación contable:{' '}
                                {money(s.difference)}
                            </p>
                        )}
                        <div className="accounting-grid">
                            <Panel
                                title="Evolución del resultado"
                                action={
                                    <select
                                        aria-label="Meses del gráfico"
                                        value={months}
                                        onChange={(e) =>
                                            setMonths(Number(e.target.value))
                                        }
                                    >
                                        <option value={3}>
                                            Últimos 3 meses
                                        </option>
                                        <option value={6}>
                                            Últimos 6 meses
                                        </option>
                                        <option value={12}>
                                            Últimos 12 meses
                                        </option>
                                    </select>
                                }
                            >
                                <div className="accounting-chart">
                                    <ResponsiveContainer>
                                        <AreaChart data={chart}>
                                            <CartesianGrid strokeDasharray="3 3" />
                                            <XAxis dataKey="month" />
                                            <YAxis />
                                            <Tooltip
                                                formatter={(v) =>
                                                    money(Number(v))
                                                }
                                            />
                                            <Area
                                                type="monotone"
                                                dataKey="result"
                                                name="Resultado"
                                                stroke="#00865e"
                                                fill="#d8f2e6"
                                            />
                                        </AreaChart>
                                    </ResponsiveContainer>
                                </div>
                                {!chart.length && (
                                    <Empty>
                                        No hay movimientos contabilizados de
                                        ingresos y gastos.
                                    </Empty>
                                )}
                            </Panel>
                            <Panel title="Ingresos y gastos contables">
                                <div className="accounting-chart">
                                    <ResponsiveContainer>
                                        <BarChart data={chart}>
                                            <CartesianGrid strokeDasharray="3 3" />
                                            <XAxis dataKey="month" />
                                            <YAxis />
                                            <Tooltip
                                                formatter={(v) =>
                                                    money(Number(v))
                                                }
                                            />
                                            <Legend />
                                            <Bar
                                                dataKey="income"
                                                name="Ingresos"
                                                fill="#00865e"
                                            />
                                            <Bar
                                                dataKey="expenses"
                                                name="Gastos"
                                                fill="#f59e42"
                                            />
                                        </BarChart>
                                    </ResponsiveContainer>
                                </div>
                            </Panel>
                        </div>
                        <div className="accounting-grid accounting-bottom">
                            <Panel title="Saldos principales por grupo">
                                <p className="accounting-muted">
                                    Saldo deudor neto: Debe menos Haber.
                                </p>
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Grupo</th>
                                            <th>Saldo</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {p.groups.map((g) => (
                                            <tr key={g.code}>
                                                <td>
                                                    <Link
                                                        href={`/accounting/accounts?group=${g.code}&year=${f.year}`}
                                                    >
                                                        {g.code}
                                                    </Link>
                                                </td>
                                                <td>{money(g.balance)}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                                {!p.groups.length && (
                                    <Empty>No hay saldos contabilizados.</Empty>
                                )}
                            </Panel>
                            <Panel
                                title="Últimos asientos contables"
                                action={
                                    <Link href="/accounting?area=entries">
                                        Ver todos →
                                    </Link>
                                }
                            >
                                <EntryTable entries={p.entries} />
                            </Panel>
                        </div>
                        <p className="accounting-muted">
                            {s.postedEntries} asientos contabilizados ·{' '}
                            {s.draftEntries} borradores excluidos · Resultado
                            pendiente de regularizar: {money(s.unclosedResult)}
                        </p>
                        <Button variant="outline" asChild>
                            <Link href={manageHref}>
                                Ejercicio, cierres y mapeos
                            </Link>
                        </Button>
                    </>
                )}
                {area === 'entries' && (
                    <>
                        <PageTabs>
                            <Link
                                href={href({ entry_status: '', tab: 'all' })}
                                className={
                                    !f.entry_status && f.tab !== 'import'
                                        ? 'active'
                                        : ''
                                }
                            >
                                Todos
                            </Link>
                            <Link
                                href={href({
                                    entry_status: 'draft',
                                    tab: 'all',
                                })}
                                className={
                                    f.entry_status === 'draft' ? 'active' : ''
                                }
                            >
                                Borradores
                            </Link>
                            <Link href="/accounting/templates?tab=recurring">
                                Periódicos
                            </Link>
                            <Link href="/accounting/templates">Plantillas</Link>
                            <Link
                                href={href({ tab: 'import' })}
                                className={f.tab === 'import' ? 'active' : ''}
                            >
                                Importación
                            </Link>
                        </PageTabs>
                        {f.tab === 'import' ? (
                            <Panel title="Importación de asientos">
                                <Empty unavailable>
                                    No hay un importador validado. La
                                    previsualización, detección de duplicados y
                                    confirmación quedan pendientes.
                                </Empty>
                            </Panel>
                        ) : (
                            <>
                                <FiltersForm
                                    base={base}
                                    f={f}
                                    area={area}
                                    view={p.view}
                                    kinds={p.kinds}
                                />
                                <Panel title={`${p.entries.total} asientos`}>
                                    <EntryTable entries={p.entries} />
                                </Panel>
                            </>
                        )}
                    </>
                )}
                {area === 'accounts' && (
                    <>
                        {tabs([
                            ['all', 'Todas las cuentas'],
                            ['groups', 'Grupos'],
                            ['operating', 'Cuentas de explotación'],
                            ['financial', 'Cuentas financieras'],
                        ])}
                        <FiltersForm
                            base={base}
                            f={f}
                            area={area}
                            view={p.view}
                            kinds={p.kinds}
                        />
                        <Panel title="Plan contable">
                            <div className="accounting-table accounting-always-table">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Código</th>
                                            <th>Descripción</th>
                                            <th>Nivel</th>
                                            <th>Naturaleza</th>
                                            <th>Saldo</th>
                                            <th>Estado</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {p.accounts.data.map((a) => (
                                            <tr key={a.id}>
                                                <td>
                                                    <Link
                                                        href={`/accounting/accounts/${a.id}?year=${f.year}`}
                                                    >
                                                        {a.code}
                                                    </Link>
                                                </td>
                                                <td>
                                                    <Link
                                                        href={`/accounting/accounts/${a.id}?year=${f.year}`}
                                                    >
                                                        {a.name}
                                                    </Link>
                                                </td>
                                                <td>{a.level}</td>
                                                <td>
                                                    {accountTypes[a.type] ??
                                                        a.type}
                                                </td>
                                                <td>{money(a.balance)}</td>
                                                <td>
                                                    <span className="accounting-state">
                                                        {a.is_active
                                                            ? 'Activa'
                                                            : 'Inactiva'}
                                                    </span>
                                                </td>
                                                <td>
                                                    <Link
                                                        href={`/accounting?area=reports&view=ledger&account=${a.code}&year=${f.year}`}
                                                    >
                                                        Ver Mayor
                                                    </Link>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                            {!p.accounts.total && (
                                <Empty>
                                    No hay cuentas para estos filtros.
                                </Empty>
                            )}
                            <Pagination noun="cuentas" page={p.accounts} />
                        </Panel>
                    </>
                )}
                {area === 'reports' && (
                    <>
                        {tabs(
                            [
                                ['balance', 'Balance de situación'],
                                ['profit', 'Pérdidas y ganancias'],
                                ['journal', 'Libro Diario'],
                                ['ledger', 'Libro Mayor'],
                                ['trial', 'Sumas y saldos'],
                                ['equity', 'Patrimonio'],
                                ['cashflow', 'Flujos de efectivo'],
                            ],
                            'view',
                        )}
                        <FiltersForm
                            base={base}
                            f={f}
                            area={area}
                            view={p.view}
                            kinds={p.kinds}
                        />
                        {['equity', 'cashflow'].includes(p.view) ? (
                            <Panel
                                title={
                                    p.view === 'equity'
                                        ? 'Estado de cambios en patrimonio'
                                        : 'Estado de flujos de efectivo'
                                }
                            >
                                <Empty unavailable>
                                    No hay un modelo contable verificable para
                                    este estado. Consulta las capacidades
                                    pendientes en el roadmap.
                                </Empty>
                            </Panel>
                        ) : (
                            <>
                                <div className="accounting-report-actions">
                                    <p>
                                        Informe de trabajo provisional · solo
                                        contabilizados
                                    </p>
                                    <Button variant="outline" asChild>
                                        <a href={exportHref}>
                                            <Download />
                                            Exportar apuntes CSV
                                        </a>
                                    </Button>
                                </div>
                                {p.view === 'journal' ? (
                                    <Panel title="Libro Diario">
                                        <EntryTable entries={p.entries} lines />
                                    </Panel>
                                ) : p.view === 'ledger' ? (
                                    <Panel title="Libro Mayor">
                                        {p.ledger ? (
                                            <>
                                                <p>
                                                    {p.ledger.account.code} ·{' '}
                                                    {p.ledger.account.name} ·
                                                    Saldo inicial:{' '}
                                                    {money(p.ledger.opening)}
                                                </p>
                                                <div className="accounting-table accounting-always-table">
                                                    <table>
                                                        <thead>
                                                            <tr>
                                                                <th>Fecha</th>
                                                                <th>Asiento</th>
                                                                <th>
                                                                    Concepto
                                                                </th>
                                                                <th>Debe</th>
                                                                <th>Haber</th>
                                                                <th>
                                                                    Saldo
                                                                    acumulado
                                                                </th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            {p.ledger.lines.data.map(
                                                                (l) => (
                                                                    <tr
                                                                        key={
                                                                            l.id
                                                                        }
                                                                    >
                                                                        <td>
                                                                            {date(
                                                                                l.entry_date,
                                                                            )}
                                                                        </td>
                                                                        <td>
                                                                            <Link
                                                                                href={`/accounting/entries/${l.entry_id}`}
                                                                            >
                                                                                {
                                                                                    l.number
                                                                                }
                                                                            </Link>
                                                                        </td>
                                                                        <td>
                                                                            {
                                                                                l.description
                                                                            }
                                                                        </td>
                                                                        <td>
                                                                            {money(
                                                                                l.debit,
                                                                            )}
                                                                        </td>
                                                                        <td>
                                                                            {money(
                                                                                l.credit,
                                                                            )}
                                                                        </td>
                                                                        <td>
                                                                            {money(
                                                                                l.running,
                                                                            )}
                                                                        </td>
                                                                    </tr>
                                                                ),
                                                            )}
                                                        </tbody>
                                                    </table>
                                                </div>
                                                <Pagination
                                                    noun="apuntes"
                                                    page={p.ledger.lines}
                                                />
                                            </>
                                        ) : (
                                            <Empty>
                                                Selecciona una cuenta para
                                                consultar su Mayor.
                                            </Empty>
                                        )}
                                    </Panel>
                                ) : p.view === 'balance' ? (
                                    <>
                                        <div className="accounting-grid">
                                            <ReportAccounts
                                                title="Activo"
                                                accounts={p.reportAccounts.filter(
                                                    (a) => a.type === 'asset',
                                                )}
                                                total={s.assets}
                                            />
                                            <ReportAccounts
                                                title="Patrimonio neto y pasivo"
                                                accounts={p.reportAccounts.filter(
                                                    (a) =>
                                                        [
                                                            'liability',
                                                            'equity',
                                                        ].includes(a.type),
                                                )}
                                                total={s.equityAndLiabilities}
                                            />
                                        </div>
                                        <p className="accounting-notice">
                                            Resultado no regularizado:{' '}
                                            {money(s.unclosedResult)}.
                                            Diferencia de la ecuación:{' '}
                                            {money(s.difference)}. Agrupación
                                            por naturaleza configurada;
                                            pendiente matriz oficial PGC.
                                        </p>
                                    </>
                                ) : p.view === 'profit' ? (
                                    <>
                                        <div className="accounting-kpis">
                                            <Panel title="Ingresos">
                                                <strong>
                                                    {money(s.income)}
                                                </strong>
                                            </Panel>
                                            <Panel title="Gastos">
                                                <strong>
                                                    {money(s.expenses)}
                                                </strong>
                                            </Panel>
                                            <Panel title="Resultado">
                                                <strong>
                                                    {money(s.result)}
                                                </strong>
                                            </Panel>
                                        </div>
                                        <p className="accounting-notice">
                                            Excluye asientos de cierre y su
                                            reversión. Desglose de explotación,
                                            financiero e impuestos pendiente de
                                            matriz validada.
                                        </p>
                                    </>
                                ) : (
                                    <Panel title="Sumas y saldos del periodo">
                                        <p className="accounting-muted">
                                            Saldo neto Debe menos Haber. Los
                                            importes corresponden al intervalo
                                            seleccionado.
                                        </p>
                                        <div className="accounting-table accounting-always-table">
                                            <table>
                                                <thead>
                                                    <tr>
                                                        <th>Código</th>
                                                        <th>Cuenta</th>
                                                        <th>Saldo inicial</th>
                                                        <th>Debe</th>
                                                        <th>Haber</th>
                                                        <th>Saldo final</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    {p.reportAccounts.map(
                                                        (a) => (
                                                            <tr key={a.id}>
                                                                <td>
                                                                    {a.code}
                                                                </td>
                                                                <td>
                                                                    {a.name}
                                                                </td>
                                                                <td>
                                                                    {money(
                                                                        a.opening,
                                                                    )}
                                                                </td>
                                                                <td>
                                                                    {money(
                                                                        a.debit,
                                                                    )}
                                                                </td>
                                                                <td>
                                                                    {money(
                                                                        a.credit,
                                                                    )}
                                                                </td>
                                                                <td>
                                                                    {money(
                                                                        a.net,
                                                                    )}
                                                                </td>
                                                            </tr>
                                                        ),
                                                    )}
                                                </tbody>
                                            </table>
                                        </div>
                                    </Panel>
                                )}
                            </>
                        )}
                    </>
                )}
                {area === 'books' && (
                    <>
                        {tabs([
                            ['commercial', 'Libros mercantiles'],
                            ['tax', 'Libros fiscales'],
                            ['settings', 'Configuración'],
                        ])}
                        {f.tab === 'tax' ? (
                            <Panel title="Libros fiscales">
                                <Button asChild variant="outline">
                                    <Link href={`/tax/issued?year=${f.year}`}>
                                        Libro de facturas emitidas
                                    </Link>
                                </Button>{' '}
                                <Button asChild variant="outline">
                                    <Link href={`/tax/received?year=${f.year}`}>
                                        Libro de facturas recibidas
                                    </Link>
                                </Button>
                                <p className="accounting-muted">
                                    Los libros de IVA no sustituyen los libros
                                    contables mercantiles.
                                </p>
                            </Panel>
                        ) : f.tab === 'settings' ? (
                            <Panel title="Configuración de libros">
                                <Empty unavailable>
                                    Versionado, formatos de legalización y
                                    registro de evidencias pendientes de
                                    implementación.
                                </Empty>
                            </Panel>
                        ) : (
                            <div className="accounting-books">
                                {[
                                    [
                                        'Libro Diario',
                                        'Consulta todos los apuntes contabilizados.',
                                        'journal',
                                    ],
                                    [
                                        'Libro de Inventarios y Cuentas Anuales',
                                        'La consulta de balances no constituye el libro completo.',
                                        'balance',
                                    ],
                                    [
                                        'Libro de Actas',
                                        'No hay un registro societario de actas vinculado.',
                                        null,
                                    ],
                                    [
                                        'Libro Registro de Socios',
                                        'No hay un registro de participaciones vinculado.',
                                        null,
                                    ],
                                    [
                                        'Legalización de libros',
                                        'Requiere documentación y evidencia verificables.',
                                        null,
                                    ],
                                ].map(([title, description, view]) => (
                                    <Panel title={title!} key={title}>
                                        <div className="accounting-book">
                                            <BookOpen />
                                            <p>{description}</p>
                                            <span className="accounting-muted">
                                                {view
                                                    ? 'Consulta disponible · generación oficial pendiente'
                                                    : 'Capacidad no disponible'}
                                            </span>
                                            {view && (
                                                <Button
                                                    asChild
                                                    variant="outline"
                                                >
                                                    <Link
                                                        href={`/accounting?area=reports&view=${view}&year=${f.year}`}
                                                    >
                                                        Consultar
                                                    </Link>
                                                </Button>
                                            )}
                                        </div>
                                    </Panel>
                                ))}
                            </div>
                        )}
                    </>
                )}
            </PageShell>
        </>
    );
}
function ReportAccounts({
    title,
    accounts,
    total,
}: {
    title: string;
    accounts: Account[];
    total: string;
}) {
    return (
        <Panel title={title}>
            <table>
                <thead>
                    <tr>
                        <th>Cuenta</th>
                        <th>Saldo</th>
                    </tr>
                </thead>
                <tbody>
                    {accounts.map((a) => (
                        <tr key={a.id}>
                            <td>
                                <Link
                                    href={`/accounting?area=reports&view=ledger&account=${a.code}`}
                                >
                                    {a.code} · {a.name}
                                </Link>
                            </td>
                            <td>{money(a.balance)}</td>
                        </tr>
                    ))}
                </tbody>
                <tfoot>
                    <tr>
                        <th>Total</th>
                        <th>{money(total)}</th>
                    </tr>
                </tfoot>
            </table>
            {!accounts.length && <Empty>No hay saldos contabilizados.</Empty>}
        </Panel>
    );
}
function FiltersForm({
    base,
    f,
    area,
    view,
    kinds,
}: {
    kinds: string[];
    base: string;
    f: Filters;
    area: string;
    view: string;
}) {
    return (
        <Form action={base} method="get" className="accounting-filters">
            <input type="hidden" name="area" value={area} />
            <input type="hidden" name="view" value={view} />
            <input type="hidden" name="year" value={f.year} />
            {f.tab && <input type="hidden" name="tab" value={f.tab} />}
            {(area !== 'reports' || view === 'journal') && (
                <label>
                    <span className="sr-only">Buscar</span>
                    <Input
                        name="search"
                        defaultValue={f.search}
                        placeholder="Buscar código, concepto o número..."
                    />
                </label>
            )}
            <label>
                Desde
                <Input name="from" type="date" defaultValue={f.from} />
            </label>
            <label>
                Hasta
                <Input name="to" type="date" defaultValue={f.to} />
            </label>
            {area === 'entries' && (
                <>
                    <label>
                        Referencia
                        <Input
                            name="reference"
                            defaultValue={f.reference ?? ''}
                        />
                    </label>
                    <label>
                        Concepto
                        <Input name="concept" defaultValue={f.concept ?? ''} />
                    </label>
                    <label>
                        Estado
                        <select
                            name="entry_status"
                            defaultValue={f.entry_status ?? ''}
                        >
                            <option value="">Todos</option>
                            <option value="posted">Contabilizados</option>
                            <option value="draft">Borradores</option>
                        </select>
                    </label>
                    <label>
                        Tipo
                        <select name="kind" defaultValue={f.kind ?? ''}>
                            <option value="">Todos</option>
                            {kinds.map((k) => (
                                <option key={k} value={k}>
                                    {kindLabels[k] ?? k}
                                </option>
                            ))}
                        </select>
                    </label>
                </>
            )}
            {area === 'accounts' ? (
                <>
                    <label>
                        Grupo
                        <Input
                            name="group"
                            type="number"
                            min={1}
                            max={9}
                            defaultValue={f.group}
                        />
                    </label>
                    <label>
                        Estado
                        <select name="active" defaultValue={f.active ?? ''}>
                            <option value="">Todos</option>
                            <option value="yes">Activas</option>
                            <option value="no">Inactivas</option>
                        </select>
                    </label>
                </>
            ) : area !== 'reports' || ['ledger', 'journal'].includes(view) ? (
                <label>
                    Cuenta
                    <Input
                        name="account"
                        placeholder="Código de cuenta"
                        defaultValue={f.account}
                    />
                </label>
            ) : null}
            {(area === 'entries' ||
                (area === 'reports' && view === 'journal')) && (
                <label>
                    Ordenar
                    <select name="sort" defaultValue={f.sort ?? 'date_desc'}>
                        <option value="date_desc">Fecha descendente</option>
                        <option value="date_asc">Fecha ascendente</option>
                        <option value="number">Número</option>
                    </select>
                </label>
            )}
            <Button variant="outline">
                <Search />
                Filtrar
            </Button>
        </Form>
    );
}

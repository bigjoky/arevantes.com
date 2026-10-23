import { Form, Head, Link, router } from '@inertiajs/react';
import {
    CalendarClock,
    FileCheck2,
    FileText,
    Percent,
    Plus,
    Receipt,
    ShieldCheck,
} from 'lucide-react';
import {
    Bar,
    CartesianGrid,
    Cell,
    ComposedChart,
    Legend,
    Pie,
    PieChart,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import {
    Panel,
    Empty,
    Pagination,
    money,
    date,
} from '@/components/billing/workspace';
import type { Page } from '@/components/billing/workspace';
import { PageShell, PageHeader } from '@/components/page-shell';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import '@/../css/fiscal.css';

type Declaration = {
    id: number;
    model: string;
    period: string;
    from: string;
    to: string;
    status: string;
    amount: string;
    preparedAt: string | null;
    filedOn: string | null;
    reference: string | null;
    evidence: boolean;
    receiptHref: string | null;
    href: string;
    deadline: null;
};
type Source = {
    id: number;
    source: string;
    number: string;
    date: string;
    direction: string;
    base: string;
    vat: string;
    withholding: string;
    href: string;
};
type Props = {
    area: 'dashboard' | 'taxes' | 'models' | 'periods';
    filters: Record<string, string | number> & { year: number; tab?: string };
    period: { from: string; to: string; quarter: number | null };
    company: { legal_name: string; tax_id: string } | null;
    census: {
        current: boolean;
        supported: boolean;
        settings: Record<string, string> | null;
        status: string | null;
        author: string | null;
        source_reference: string | null;
        version_id: number | null;
    };
    metrics: {
        documents: number;
        outputVat: string;
        supportedVat: string;
        withholding: string;
        deductibleVat: null;
        result: null;
        nextDeadline: null;
    };
    series: { month: string; outputVat: string; supportedVat: string }[];
    rates: Page<{
        id: number;
        name: string;
        rate: string;
        kind: string;
        is_active: boolean;
        is_default: boolean;
    }>;
    declarations: Page<Declaration>;
    declarationStates: { name: string; value: number }[];
    documents: Page<Source>;
    years: {
        id: number;
        year: number;
        starts_on: string;
        ends_on: string;
        status: string;
        closed_at: string | null;
        reopened_at: string | null;
    }[];
    periods: {
        name: string;
        from: string;
        to: string;
        status: string;
        deadline: null;
        href: string;
    }[];
    modelCatalog: {
        code: string;
        name: string;
        applicable: boolean | null;
        supported: boolean;
        href: string | null;
    }[];
    excludedForeignExpenses: number;
    calculationVersion: string;
    officialCalendar: string;
    capabilities: {
        manageTax: boolean;
        manageCensus: boolean;
        manageYears: boolean;
        multiCompany: false;
        deadlines: false;
        independentFiscalClose: false;
        deductibleSummary?: false;
    };
};
const stateNames: Record<string, string> = {
    draft: 'Borrador',
    approved: 'Revisado internamente',
    prepared: 'Preparado / contabilizado',
    filed: 'Presentación registrada',
    evidence_pending: 'Evidencia pendiente',
    not_started: 'Sin preparar',
    open: 'Abierto',
    closed: 'Cerrado contablemente',
};
const configNames: Record<string, string> = {
    model303: 'Obligación 303',
    vat_regime: 'Régimen de IVA',
    periodicity: 'Periodicidad',
    territory: 'Territorio',
    cash_accounting: 'Criterio de caja',
    cash_recipient: 'Destinatario de caja',
    prorrata: 'Prorrata',
    redeme: 'REDEME',
    sii: 'SII',
};
const configValues: Record<string, string> = {
    unknown: 'Pendiente de revisar',
    yes: 'Sí',
    no: 'No',
    general: 'General',
    simplified: 'Simplificado',
    mixed: 'Mixto',
    quarterly: 'Trimestral',
    monthly: 'Mensual',
    common: 'Común',
    foral: 'Foral',
    none: 'Sin prorrata',
    special: 'Especial',
};
const paths = {
    dashboard: '/fiscal',
    taxes: '/fiscal/impuestos',
    models: '/fiscal/modelos',
    periods: '/fiscal/periodos',
};
const colors = ['#00906a', '#54a2ff', '#ffb84a', '#9e7edd', '#f76b70'];
function State({ value }: { value: string }) {
    return (
        <span className={`fiscal-state fiscal-state-${value}`}>
            {stateNames[value] ?? value}
        </span>
    );
}
function Kpi({
    label,
    value,
    note,
    icon: Icon,
}: {
    label: string;
    value: string | null;
    note: string;
    icon: typeof Receipt;
}) {
    return (
        <Panel title="">
            <div className="fiscal-kpi">
                <Icon aria-hidden />
                <div>
                    <span>{label}</span>
                    <strong
                        className={
                            value === null ? 'fiscal-missing' : undefined
                        }
                    >
                        {value === null ? 'No disponible' : money(value)}
                    </strong>
                    <small>{note}</small>
                </div>
            </div>
        </Panel>
    );
}
function Donut({ data }: { data: { name: string; value: number }[] }) {
    const valid = data.filter((r) => r.value > 0);

    return valid.length ? (
        <>
            <div
                className="fiscal-donut"
                role="img"
                aria-label={valid
                    .map((r) => `${r.name}: ${r.value}`)
                    .join('; ')}
            >
                <ResponsiveContainer width="100%" height="100%">
                    <PieChart>
                        <Pie
                            data={valid}
                            dataKey="value"
                            nameKey="name"
                            innerRadius={49}
                            outerRadius={69}
                        >
                            {valid.map((r, i) => (
                                <Cell
                                    key={r.name}
                                    fill={colors[i % colors.length]}
                                />
                            ))}
                        </Pie>
                        <Tooltip />
                    </PieChart>
                </ResponsiveContainer>
            </div>
            <ul className="fiscal-key">
                {data.map((r, i) => (
                    <li key={r.name}>
                        <i style={{ background: colors[i % colors.length] }} />
                        <span>{r.name}</span>
                        <b>{r.value}</b>
                    </li>
                ))}
            </ul>
        </>
    ) : (
        <Empty>Sin registros para mostrar una distribución.</Empty>
    );
}
function ReturnTable({ rows }: { rows: Declaration[] }) {
    if (!rows.length) {
        return (
            <Empty>No hay declaraciones registradas para estos filtros.</Empty>
        );
    }

    return (
        <>
            <div className="fiscal-desktop fiscal-scroll">
                <table>
                    <thead>
                        <tr>
                            {[
                                'Modelo',
                                'Periodo',
                                'Preparación',
                                'Resultado guardado',
                                'Estado',
                                'Presentación',
                                'Documentos',
                            ].map((h) => (
                                <th key={h}>{h}</th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {rows.map((r) => (
                            <tr key={`${r.model}-${r.id}`}>
                                <td>
                                    <Link href={r.href}>Modelo {r.model}</Link>
                                </td>
                                <td>{r.period}</td>
                                <td>{date(r.preparedAt)}</td>
                                <td>{money(r.amount)}</td>
                                <td>
                                    <State value={r.status} />
                                </td>
                                <td>
                                    {date(r.filedOn)}
                                    <small>{r.reference}</small>
                                </td>
                                <td>
                                    {r.receiptHref ? (
                                        <a href={r.receiptHref}>Justificante</a>
                                    ) : (
                                        'Sin evidencia verificada'
                                    )}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            <div className="fiscal-mobile">
                {rows.map((r) => (
                    <article
                        className="fiscal-record"
                        key={`${r.model}-${r.id}`}
                    >
                        <Link href={r.href}>
                            <strong>
                                Modelo {r.model} · {r.period}
                            </strong>
                        </Link>
                        <State value={r.status} />
                        <b>{money(r.amount)}</b>
                        <small>Resultado guardado · {date(r.preparedAt)}</small>
                        {r.receiptHref && (
                            <a href={r.receiptHref}>Descargar justificante</a>
                        )}
                    </article>
                ))}
            </div>
        </>
    );
}
function Sources({ page }: { page: Page<Source> }) {
    return (
        <Panel title="Documentos de origen">
            <p className="fiscal-note">
                Importes documentales EUR por fecha de emisión / gasto. No
                equivalen a casillas de una declaración.
            </p>
            {page.data.length ? (
                <>
                    <div className="fiscal-desktop fiscal-scroll">
                        <table>
                            <thead>
                                <tr>
                                    {[
                                        'Fecha',
                                        'Documento',
                                        'Registro',
                                        'Base',
                                        'IVA registrado',
                                        'Retención registrada',
                                    ].map((h) => (
                                        <th key={h}>{h}</th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {page.data.map((r) => (
                                    <tr key={`${r.source}-${r.id}`}>
                                        <td>{date(r.date)}</td>
                                        <td>
                                            <Link href={r.href}>
                                                {r.number}
                                            </Link>
                                        </td>
                                        <td>
                                            {r.direction === 'issued'
                                                ? 'Emitida'
                                                : 'Recibida'}
                                        </td>
                                        <td>{money(r.base)}</td>
                                        <td>{money(r.vat)}</td>
                                        <td>{money(r.withholding)}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <div className="fiscal-mobile">
                        {page.data.map((r) => (
                            <Link
                                className="fiscal-record"
                                href={r.href}
                                key={`${r.source}-${r.id}`}
                            >
                                <strong>{r.number}</strong>
                                <span>{date(r.date)}</span>
                                <b>IVA: {money(r.vat)}</b>
                                <small>Retención: {money(r.withholding)}</small>
                            </Link>
                        ))}
                    </div>
                </>
            ) : (
                <Empty>No hay documentos elegibles en este periodo.</Empty>
            )}
            <Pagination page={page} noun="documentos" />
        </Panel>
    );
}
function Config({ p }: { p: Props }) {
    return (
        <Panel
            title="Configuración fiscal"
            action={
                p.capabilities.manageCensus && (
                    <Link
                        href={`/company-fiscal/census?year=${p.filters.year}`}
                    >
                        Revisar censo
                    </Link>
                )
            }
        >
            <p className="fiscal-note">
                {p.census.current
                    ? 'Censo revisado y vigente para la identidad registrada.'
                    : 'Configuración pendiente de revisión o desactualizada.'}
            </p>
            <dl className="fiscal-dl">
                {Object.entries(configNames).map(([k, label]) => (
                    <div key={k}>
                        <dt>{label}</dt>
                        <dd>
                            {configValues[
                                p.census.settings?.[k] ?? 'unknown'
                            ] ?? 'Pendiente de revisar'}
                        </dd>
                    </div>
                ))}
            </dl>
            {p.census.author && (
                <p className="fiscal-note">
                    Revisión: {p.census.author} · Versión {p.census.version_id}
                </p>
            )}
            {p.census.source_reference && (
                <p className="fiscal-note">
                    Fuente: {p.census.source_reference}
                </p>
            )}
        </Panel>
    );
}
export default function FiscalWorkspace(p: Props) {
    const title = {
        dashboard: 'Fiscal',
        taxes: 'Impuestos',
        models: 'Modelos',
        periods: 'Periodos fiscales',
    }[p.area];
    const subtitle = {
        dashboard: 'Gestiona tus impuestos, modelos y obligaciones fiscales.',
        taxes: 'Consulta los impuestos y tipos configurados en tu empresa.',
        models: 'Prepara y revisa modelos tributarios y sus evidencias de presentación.',
        periods:
            'Consulta ejercicios, periodos y el estado de preparación fiscal.',
    }[p.area];
    const tab =
        p.filters.tab ??
        (p.area === 'taxes' ? 'vat' : p.area === 'models' ? 'main' : 'years');
    const update = (key: string, value: string) => {
        const next = { ...p.filters, [key]: value };
        delete next.page;
        delete next.documents_page;
        delete next.rates_page;
        router.get(paths[p.area], next, { preserveScroll: true });
    };
    const tabs =
        p.area === 'taxes'
            ? [
                  ['vat', 'Tipos de IVA'],
                  ['other', 'Otros impuestos'],
                  ['withholding', 'Retenciones'],
                  ['regimes', 'Regímenes y configuraciones'],
              ]
            : p.area === 'models'
              ? [
                    ['main', 'Modelos principales'],
                    ['annual', 'Modelos anuales'],
                    ['informative', 'Modelos informativos'],
                    ['filings', 'Presentaciones'],
                ]
              : [
                    ['years', 'Ejercicios'],
                    ['vat', 'Periodos de IVA'],
                    ['retention', 'Periodos de retenciones'],
                    ['closing', 'Cierre fiscal'],
                ];

    return (
        <PageShell className="fiscal-workspace">
            <Head title={title} />
            <PageHeader
                secondaryNavigation={null}
                breadcrumbs={[
                    { title: 'Fiscal', href: '/fiscal' },
                    ...(p.area === 'dashboard'
                        ? []
                        : [{ title, href: paths[p.area] }]),
                ]}
                title={title}
                description={subtitle}
                actions={
                    <div className="fiscal-controls">
                        <label>
                            Ejercicio
                            <select
                                value={p.filters.year}
                                onChange={(e) => update('year', e.target.value)}
                            >
                                {Array.from(
                                    new Set([
                                        p.filters.year,
                                        ...p.years.map((y) => y.year),
                                    ]),
                                )
                                    .sort((a, b) => b - a)
                                    .map((y) => (
                                        <option key={y}>{y}</option>
                                    ))}
                            </select>
                        </label>
                        <label>
                            Periodo
                            <select
                                value={p.period.quarter ?? ''}
                                onChange={(e) =>
                                    update('quarter', e.target.value)
                                }
                            >
                                <option value="">Todo el ejercicio</option>
                                {[1, 2, 3, 4].map((q) => (
                                    <option key={q} value={q}>
                                        {q}T
                                    </option>
                                ))}
                            </select>
                        </label>
                    </div>
                }
            />
            <p className="fiscal-context">
                {p.company?.legal_name ?? 'Empresa sin configurar'} ·{' '}
                {p.company?.tax_id ?? 'NIF pendiente'} · {date(p.period.from)} –{' '}
                {date(p.period.to)}
            </p>
            {p.area !== 'dashboard' && (
                <nav className="fiscal-tabs" aria-label="Vistas fiscales">
                    {tabs.map(([key, label]) => (
                        <Link
                            key={key}
                            aria-current={tab === key ? 'page' : undefined}
                            href={`${paths[p.area]}?year=${p.filters.year}&tab=${key}`}
                        >
                            {label}
                        </Link>
                    ))}
                </nav>
            )}
            {p.area === 'dashboard' ? (
                <>
                    <div className="fiscal-five">
                        <Kpi
                            label="IVA repercutido registrado"
                            value={p.metrics.outputVat}
                            icon={Receipt}
                            note="Facturas emitidas y rectificativas"
                        />
                        <Kpi
                            label="IVA soportado registrado"
                            value={p.metrics.supportedVat}
                            icon={FileText}
                            note="Deducibilidad pendiente de revisión"
                        />
                        <Kpi
                            label="Resultado IVA definitivo"
                            value={null}
                            icon={ShieldCheck}
                            note="Consulta los borradores y declaraciones"
                        />
                        <Kpi
                            label="Retenciones registradas"
                            value={p.metrics.withholding}
                            icon={Percent}
                            note="Gastos · naturaleza sin clasificar"
                        />
                        <Panel title="">
                            <div className="fiscal-kpi">
                                <CalendarClock aria-hidden />
                                <div>
                                    <span>Próximo vencimiento</span>
                                    <strong className="fiscal-missing">
                                        Pendiente de verificar
                                    </strong>
                                    <small>
                                        <a
                                            href={p.officialCalendar}
                                            target="_blank"
                                            rel="noreferrer"
                                        >
                                            Consultar calendario AEAT ↗
                                        </a>
                                    </small>
                                </div>
                            </div>
                        </Panel>
                    </div>
                    <div className="fiscal-chart-grid">
                        <Panel
                            title="Evolución del IVA registrado"
                            action={<span>Ejercicio {p.filters.year}</span>}
                        >
                            {p.series.length ? (
                                <div
                                    className="fiscal-chart"
                                    role="img"
                                    aria-label="IVA repercutido y soportado registrado por mes"
                                >
                                    <ResponsiveContainer
                                        width="100%"
                                        height="100%"
                                    >
                                        <ComposedChart data={p.series}>
                                            <CartesianGrid
                                                vertical={false}
                                                stroke="#edf0f3"
                                            />
                                            <XAxis
                                                dataKey="month"
                                                tick={{ fontSize: 11 }}
                                            />
                                            <YAxis tick={{ fontSize: 11 }} />
                                            <Tooltip
                                                formatter={(v) =>
                                                    money(String(v))
                                                }
                                            />
                                            <Legend />
                                            <Bar
                                                dataKey="outputVat"
                                                name="IVA repercutido registrado"
                                                fill="#008f68"
                                                radius={[3, 3, 0, 0]}
                                            />
                                            <Bar
                                                dataKey="supportedVat"
                                                name="IVA soportado registrado"
                                                fill="#54a2ff"
                                                radius={[3, 3, 0, 0]}
                                            />
                                        </ComposedChart>
                                    </ResponsiveContainer>
                                </div>
                            ) : (
                                <Empty>
                                    No hay datos documentales en este ejercicio.
                                </Empty>
                            )}
                        </Panel>
                        <Panel title="Declaraciones del ejercicio">
                            <Donut
                                data={p.declarationStates.map((r) => ({
                                    ...r,
                                    name:
                                        r.name === 'filed'
                                            ? 'Marcadas como presentadas'
                                            : (stateNames[r.name] ?? r.name),
                                }))}
                            />
                            <p className="fiscal-note">
                                Estados internos. Presentación sujeta a
                                evidencia verificada en el listado.
                            </p>
                        </Panel>
                    </div>
                    <div className="fiscal-two">
                        <Panel title="Próximos vencimientos fiscales">
                            <Empty unavailable>
                                No hay calendario aplicable validado. Las fechas
                                no se deducen de la fecha de presentación.
                            </Empty>
                            <a
                                href={p.officialCalendar}
                                target="_blank"
                                rel="noreferrer"
                            >
                                Calendario oficial AEAT ↗
                            </a>
                        </Panel>
                        <Panel
                            title="Últimas declaraciones"
                            action={
                                <Link
                                    href={`/fiscal/modelos?year=${p.filters.year}&tab=filings`}
                                >
                                    Ver todas →
                                </Link>
                            }
                        >
                            <ReturnTable
                                rows={p.declarations.data.slice(0, 5)}
                            />
                        </Panel>
                    </div>
                    <Sources page={p.documents} />
                </>
            ) : p.area === 'taxes' ? (
                <>
                    {tab === 'regimes' ? (
                        <Config p={p} />
                    ) : (
                        <Panel
                            title={
                                tab === 'withholding'
                                    ? 'Tipos de retención configurados'
                                    : tab === 'other'
                                      ? 'Otros impuestos configurados'
                                      : 'Tipos de IVA'
                            }
                            action={
                                p.capabilities.manageTax &&
                                tab === 'vat' && (
                                    <Dialog>
                                        <DialogTrigger asChild>
                                            <Button>
                                                <Plus />
                                                Nuevo tipo
                                            </Button>
                                        </DialogTrigger>
                                        <DialogContent>
                                            <DialogHeader>
                                                <DialogTitle>
                                                    Nuevo tipo impositivo
                                                </DialogTitle>
                                            </DialogHeader>
                                            <Form
                                                action="/fiscal/tax-rates"
                                                method="post"
                                            >
                                                {({ errors, processing }) => (
                                                    <div className="fiscal-form">
                                                        <label>
                                                            Descripción
                                                            <Input
                                                                name="name"
                                                                required
                                                                maxLength={100}
                                                            />
                                                        </label>
                                                        <label>
                                                            Porcentaje
                                                            <Input
                                                                name="rate"
                                                                type="number"
                                                                min="0"
                                                                max="100"
                                                                step="0.01"
                                                                required
                                                            />
                                                        </label>
                                                        <input
                                                            type="hidden"
                                                            name="kind"
                                                            value="vat"
                                                        />
                                                        <p>
                                                            Se crea un nuevo
                                                            tipo. Los
                                                            porcentajes de
                                                            documentos
                                                            históricos
                                                            permanecen en sus
                                                            líneas.
                                                        </p>
                                                        {Object.values(
                                                            errors,
                                                        ).map((e, i) => (
                                                            <p
                                                                role="alert"
                                                                key={i}
                                                            >
                                                                {e}
                                                            </p>
                                                        ))}
                                                        <Button
                                                            disabled={
                                                                processing
                                                            }
                                                        >
                                                            Guardar tipo
                                                        </Button>
                                                    </div>
                                                )}
                                            </Form>
                                        </DialogContent>
                                    </Dialog>
                                )
                            }
                        >
                            <form
                                className="fiscal-filters"
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    const data = new FormData(e.currentTarget);
                                    router.get(
                                        paths.taxes,
                                        {
                                            year: p.filters.year,
                                            tab,
                                            search: String(
                                                data.get('search') ?? '',
                                            ),
                                            state: String(
                                                data.get('state') ?? '',
                                            ),
                                        },
                                        { preserveScroll: true },
                                    );
                                }}
                            >
                                <label>
                                    Buscar por descripción
                                    <Input
                                        name="search"
                                        placeholder="Buscar impuestos..."
                                        defaultValue={String(
                                            p.filters.search ?? '',
                                        )}
                                    />
                                </label>
                                <label>
                                    Estado
                                    <select
                                        name="state"
                                        defaultValue={String(
                                            p.filters.state ?? '',
                                        )}
                                    >
                                        <option value="">Todos</option>
                                        <option value="active">Activo</option>
                                        <option value="inactive">
                                            Inactivo
                                        </option>
                                    </select>
                                </label>
                                <Button variant="outline">
                                    Aplicar filtros
                                </Button>
                            </form>
                            {p.rates.data.length ? (
                                <>
                                    <div className="fiscal-desktop fiscal-scroll">
                                        <table>
                                            <thead>
                                                <tr>
                                                    {[
                                                        'Identificador',
                                                        'Descripción',
                                                        'Porcentaje',
                                                        'Tipo',
                                                        'Estado',
                                                        'Vigencia',
                                                    ].map((h) => (
                                                        <th key={h}>{h}</th>
                                                    ))}
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {p.rates.data.map((r) => (
                                                    <tr key={r.id}>
                                                        <td>#{r.id}</td>
                                                        <td>{r.name}</td>
                                                        <td>
                                                            {new Intl.NumberFormat(
                                                                'es-ES',
                                                                {
                                                                    maximumFractionDigits: 2,
                                                                },
                                                            ).format(
                                                                Number(r.rate),
                                                            )}
                                                            %
                                                        </td>
                                                        <td>
                                                            {r.kind === 'vat'
                                                                ? 'IVA'
                                                                : r.kind ===
                                                                    'exempt'
                                                                  ? 'Exención configurada'
                                                                  : r.kind ===
                                                                      'withholding'
                                                                    ? 'Retención'
                                                                    : r.kind}
                                                        </td>
                                                        <td>
                                                            <span className="fiscal-state">
                                                                {r.is_active
                                                                    ? 'Activo'
                                                                    : 'Inactivo'}
                                                            </span>
                                                        </td>
                                                        <td>
                                                            Sin fechas
                                                            configuradas
                                                        </td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                    <div className="fiscal-mobile">
                                        {p.rates.data.map((r) => (
                                            <article
                                                className="fiscal-record"
                                                key={r.id}
                                            >
                                                <strong>{r.name}</strong>
                                                <b>
                                                    {new Intl.NumberFormat(
                                                        'es-ES',
                                                        {
                                                            maximumFractionDigits: 2,
                                                        },
                                                    ).format(Number(r.rate))}
                                                    %
                                                </b>
                                                <span>
                                                    {r.is_active
                                                        ? 'Activo'
                                                        : 'Inactivo'}
                                                </span>
                                            </article>
                                        ))}
                                    </div>
                                </>
                            ) : (
                                <Empty>
                                    No hay impuestos configurados para estos
                                    filtros.
                                </Empty>
                            )}
                            <Pagination page={p.rates} noun="tipos" />
                            <p className="fiscal-note">
                                Un porcentaje cero no acredita exención, no
                                sujeción o inversión del sujeto pasivo. La
                                vigencia temporal y tratamientos especiales
                                requieren ampliar el catálogo.
                            </p>
                        </Panel>
                    )}
                    <div className="fiscal-two">
                        <Panel
                            title={
                                tab === 'withholding'
                                    ? 'Retenciones en documentos'
                                    : 'IVA en documentos'
                            }
                        >
                            <dl className="fiscal-dl">
                                <div>
                                    <dt>IVA repercutido registrado</dt>
                                    <dd>{money(p.metrics.outputVat)}</dd>
                                </div>
                                <div>
                                    <dt>IVA soportado registrado</dt>
                                    <dd>{money(p.metrics.supportedVat)}</dd>
                                </div>
                                <div>
                                    <dt>
                                        Retenciones sin clasificación de
                                        naturaleza
                                    </dt>
                                    <dd>{money(p.metrics.withholding)}</dd>
                                </div>
                                <div>
                                    <dt>
                                        Deducibilidad / distribución fiscal por
                                        tratamiento
                                    </dt>
                                    <dd>Pendiente de revisión</dd>
                                </div>
                            </dl>
                            <Link href={`/tax?year=${p.filters.year}`}>
                                Consultar libros de IVA →
                            </Link>
                        </Panel>
                        {tab !== 'regimes' ? (
                            <Config p={p} />
                        ) : (
                            <Panel title="Tratamientos especiales">
                                <Empty unavailable>
                                    La configuración censal admite regímenes
                                    especiales; el cálculo completo de estos
                                    tratamientos aún no está disponible.
                                </Empty>
                            </Panel>
                        )}
                    </div>
                    <Sources page={p.documents} />
                </>
            ) : p.area === 'models' ? (
                <>
                    {tab !== 'filings' && (
                        <Panel title="Capacidades y aplicabilidad">
                            <div className="fiscal-two">
                                {p.modelCatalog
                                    .filter(
                                        (m) =>
                                            tab !== 'annual' ||
                                            m.code === '200',
                                    )
                                    .filter(() => tab !== 'informative')
                                    .map((m) => (
                                        <article
                                            className="fiscal-model"
                                            key={m.code}
                                        >
                                            <FileCheck2 aria-hidden />
                                            <div>
                                                <strong>Modelo {m.code}</strong>
                                                <span>{m.name}</span>
                                                <small>
                                                    {m.applicable === null
                                                        ? 'Aplicabilidad pendiente de validación'
                                                        : m.applicable
                                                          ? 'Obligación según censo revisado'
                                                          : 'No obligado según censo revisado'}
                                                </small>
                                                <small>
                                                    {m.supported
                                                        ? 'Ámbito censal compatible. Cada periodo requiere revisión.'
                                                        : 'Cálculo sujeto al ámbito y validaciones del servicio existente.'}
                                                </small>
                                                {m.href && (
                                                    <Link href={m.href}>
                                                        Abrir preparación y
                                                        detalle →
                                                    </Link>
                                                )}
                                            </div>
                                        </article>
                                    ))}
                            </div>
                            {tab === 'informative' && (
                                <Empty unavailable>
                                    No existe catálogo de obligaciones
                                    informativas verificadas. No se activan
                                    modelos por defecto.
                                </Empty>
                            )}
                        </Panel>
                    )}
                    <Panel
                        title={
                            tab === 'filings'
                                ? 'Declaraciones y evidencias'
                                : 'Declaraciones del ejercicio'
                        }
                    >
                        <form
                            className="fiscal-filters"
                            onSubmit={(e) => {
                                e.preventDefault();
                                const f = new FormData(e.currentTarget);
                                router.get(
                                    paths.models,
                                    {
                                        ...p.filters,
                                        page: 1,
                                        search: String(f.get('search') ?? ''),
                                        state: String(f.get('state') ?? ''),
                                        model: String(f.get('model') ?? ''),
                                    },
                                    { preserveScroll: true },
                                );
                            }}
                        >
                            <label>
                                Buscar modelo o referencia
                                <Input
                                    name="search"
                                    defaultValue={String(
                                        p.filters.search ?? '',
                                    )}
                                />
                            </label>
                            <label>
                                Modelo
                                <select
                                    name="model"
                                    defaultValue={String(p.filters.model ?? '')}
                                >
                                    <option value="">
                                        Todos los registrados
                                    </option>
                                    <option>303</option>
                                    <option>200</option>
                                </select>
                            </label>
                            <label>
                                Estado interno
                                <select
                                    name="state"
                                    defaultValue={String(p.filters.state ?? '')}
                                >
                                    <option value="">Todos</option>
                                    {[
                                        'draft',
                                        'approved',
                                        'prepared',
                                        'filed',
                                    ].map((s) => (
                                        <option key={s} value={s}>
                                            {stateNames[s]}
                                        </option>
                                    ))}
                                </select>
                            </label>
                            <Button variant="outline">Aplicar filtros</Button>
                        </form>
                        <ReturnTable rows={p.declarations.data} />
                        <Pagination
                            page={p.declarations}
                            noun="declaraciones"
                        />
                        <p className="fiscal-note">
                            Un resultado guardado es un borrador o una
                            liquidación interna hasta revisar su ámbito. Un PDF
                            o CSV de trabajo no acredita presentación. Los
                            justificantes se comprueban con su huella guardada.
                        </p>
                    </Panel>
                    <div className="fiscal-two">
                        <Panel title={`Estado interno (${p.filters.year})`}>
                            <Donut
                                data={p.declarationStates.map((r) => ({
                                    ...r,
                                    name:
                                        r.name === 'filed'
                                            ? 'Marcadas como presentadas'
                                            : (stateNames[r.name] ?? r.name),
                                }))}
                            />
                        </Panel>
                        <Panel title={`Calendario fiscal ${p.filters.year}`}>
                            <Empty unavailable>
                                Vencimientos pendientes de verificar según
                                obligaciones, periodo y forma de presentación.
                            </Empty>
                            <a
                                href={p.officialCalendar}
                                target="_blank"
                                rel="noreferrer"
                            >
                                Consultar calendario oficial AEAT ↗
                            </a>
                        </Panel>
                    </div>
                </>
            ) : (
                <>
                    {tab === 'retention' ? (
                        <Panel title="Periodos de retenciones">
                            <Empty unavailable>
                                La naturaleza de las retenciones y sus
                                obligaciones deben revisarse antes de asignar
                                modelos y periodicidad.
                            </Empty>
                        </Panel>
                    ) : tab === 'closing' ? (
                        <Panel title="Revisión previa al cierre fiscal">
                            <dl className="fiscal-dl">
                                <div>
                                    <dt>Identidad y censo</dt>
                                    <dd>
                                        {p.census.current
                                            ? 'Revisado'
                                            : 'Pendiente'}
                                    </dd>
                                </div>
                                <div>
                                    <dt>
                                        Deducibilidad y operaciones especiales
                                    </dt>
                                    <dd>Revisión por documento pendiente</dd>
                                </div>
                                <div>
                                    <dt>Declaraciones y evidencias</dt>
                                    <dd>
                                        <Link
                                            href={`/fiscal/modelos?year=${p.filters.year}&tab=filings`}
                                        >
                                            Consultar registros
                                        </Link>
                                    </dd>
                                </div>
                                <div>
                                    <dt>Calendario de obligaciones</dt>
                                    <dd>Pendiente de verificar</dd>
                                </div>
                                <div>
                                    <dt>Cierre fiscal independiente</dt>
                                    <dd>No disponible</dd>
                                </div>
                            </dl>
                            {p.capabilities.manageYears && (
                                <Link href="/company-fiscal">
                                    Abrir gestión existente de ejercicios
                                    contables y series →
                                </Link>
                            )}
                            <p className="fiscal-note">
                                Cerrar un ejercicio contable no acredita que las
                                obligaciones fiscales estén cumplidas. La
                                reapertura existente conserva motivo y
                                auditoría.
                            </p>
                        </Panel>
                    ) : (
                        <div className="fiscal-two">
                            <Panel
                                title="Ejercicios existentes"
                                action={
                                    p.capabilities.manageYears && (
                                        <Link href="/company-fiscal">
                                            Gestionar ejercicios →
                                        </Link>
                                    )
                                }
                            >
                                <div className="fiscal-scroll">
                                    <table>
                                        <thead>
                                            <tr>
                                                <th>Año</th>
                                                <th>Inicio</th>
                                                <th>Fin</th>
                                                <th>Estado</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {p.years.map((y) => (
                                                <tr key={y.id}>
                                                    <td>
                                                        <button
                                                            onClick={() =>
                                                                update(
                                                                    'year',
                                                                    String(
                                                                        y.year,
                                                                    ),
                                                                )
                                                            }
                                                        >
                                                            {y.year}
                                                        </button>
                                                    </td>
                                                    <td>{date(y.starts_on)}</td>
                                                    <td>{date(y.ends_on)}</td>
                                                    <td>
                                                        <State
                                                            value={y.status}
                                                        />
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                                {!p.years.length && (
                                    <Empty>
                                        No existen ejercicios configurados.
                                    </Empty>
                                )}
                                <p className="fiscal-note">
                                    Estado del ejercicio contable y las series
                                    existentes.
                                </p>
                            </Panel>
                            <Config p={p} />
                        </div>
                    )}
                    {(tab === 'years' || tab === 'vat') && (
                        <Panel title={`Periodos de IVA (${p.filters.year})`}>
                            {p.periods.length ? (
                                <div className="fiscal-periods">
                                    {p.periods.map((r) => (
                                        <Link
                                            key={r.name}
                                            className="fiscal-record"
                                            href={r.href}
                                        >
                                            <strong>
                                                {r.name} · {p.filters.year}
                                            </strong>
                                            <span>
                                                {date(r.from)} – {date(r.to)}
                                            </span>
                                            <State value={r.status} />
                                            <small>
                                                Vencimiento: pendiente de
                                                verificar
                                            </small>
                                        </Link>
                                    ))}
                                </div>
                            ) : (
                                <Empty unavailable>
                                    El censo vigente debe confirmar la
                                    obligación y periodicidad. No se crean
                                    periodos fiscales por suposición.
                                </Empty>
                            )}
                        </Panel>
                    )}
                </>
            )}
            <p className="fiscal-footnote">
                {p.metrics.documents} documentos elegibles · importes EUR ·{' '}
                {p.excludedForeignExpenses
                    ? `${p.excludedForeignExpenses} gastos en otra moneda excluidos · `
                    : ''}
                La aplicación utiliza una única empresa configurada; la
                segregación multientidad queda pendiente. Los libros, cálculos y
                revisiones existentes se conservan.
            </p>
        </PageShell>
    );
}

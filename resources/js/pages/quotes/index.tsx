import '../../../css/commercial-mobile.css';
import { Head, Link, router } from '@inertiajs/react';
import {
    CalendarDays,
    Columns3,
    List,
    Plus,
    Search,
    SlidersHorizontal,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { useCustomerNavigation } from '@/components/customers/use-customer-navigation';
import { PageHeader, PageShell, PaginationBar } from '@/components/page-shell';
import {
    quoteDate,
    quoteMoney,
    quoteUrl,
    statusLabels,
} from '@/components/quotes/types';
import type { QuoteFilters, QuoteListRow } from '@/components/quotes/types';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { Skeleton } from '@/components/ui/skeleton';
import { useLocale } from '@/hooks/use-locale';
import { translate } from '@/i18n';
import { serviceLabels } from '@/lib/business-lines';
import type { ServiceType } from '@/lib/business-lines';

type Props = {
    quotes: {
        data: QuoteListRow[];
        total: number;
        from: number | null;
        to: number | null;
        links: { url: string | null; label: string; active: boolean }[];
    };
    filters: QuoteFilters;
    statuses: string[];
    summary: {
        total: number;
        statuses: Record<string, { count: number; amount: string }>;
    };
    owners: { id: number; name: string }[];
    customers: {
        id: number;
        first_name: string;
        last_name: string | null;
        company_name: string | null;
    }[];
    recentActivity: {
        id: number;
        to_status: string;
        changed_at: string;
        changed_by: { name: string } | null;
        quote: { id: number; number: string };
    }[];
};
const filterKeys = {
    service: 'service_type',
    status: 'status',
    owner: 'owner',
    sort: 'sort',
    direction: 'direction',
};
const sortOptions: [string, string][] = [
    ['issue_date', 'Fecha'],
    ['number', 'Referencia'],
    ['amount', 'Importe'],
    ['valid_until', 'Validez'],
    ['updated_at', 'Última actualización'],
];
const statusColors: Record<string, string> = {
    draft: '#a7b0bd',
    sent: '#1485e8',
    accepted: '#20a877',
    rejected: '#ef6677',
    expired: '#e9a52d',
};
const directionOptions: [string, string][] = [
    ['asc', 'Ascendente'],
    ['desc', 'Descendente'],
];
export default function QuotesIndex(props: Props) {
    const {
        quotes,
        filters,
        statuses,
        summary,
        owners,
        customers,
        recentActivity,
    } = props;
    const { locale } = useLocale();
    const translatedServices = Object.fromEntries(
        Object.entries(serviceLabels).map(([key, label]) => [
            key,
            translate(label, locale),
        ]),
    );
    const loading = useCustomerNavigation();
    const timer = useRef<ReturnType<typeof setTimeout> | null>(null);
    const [search, setSearch] = useState(filters.search ?? '');
    useEffect(
        () => () => {
            if (timer.current) {
                clearTimeout(timer.current);
            }
        },
        [],
    );
    const update = (patch: QuoteFilters) => {
        if (timer.current) {
            clearTimeout(timer.current);
        }

        router.get(
            '/quotes',
            Object.fromEntries(
                Object.entries({ ...filters, ...patch, page: '' }).filter(
                    ([, v]) => v !== '',
                ),
            ),
            { preserveScroll: true },
        );
    };
    const select = (
        label: string,
        key: string,
        options: [string, string][],
    ) => (
        <label className="quotes-filter">
            <span>{label}</span>
            <select
                value={
                    filters[key] ??
                    (key === filterKeys.sort
                        ? 'issue_date'
                        : key === filterKeys.direction
                          ? 'desc'
                          : '')
                }
                onChange={(e) => update({ [key]: e.target.value })}
            >
                {key !== filterKeys.sort && key !== filterKeys.direction && (
                    <option value="">Todos</option>
                )}
                {options.map(([value, text]) => (
                    <option key={value} value={value}>
                        {text}
                    </option>
                ))}
            </select>
        </label>
    );
    const moreFilters = (
        <div className="quotes-extra-filters">
            {select('Aprobación', 'approval', [
                ['not_required', 'Sin aprobación'],
                ['pending', 'Pendiente de aprobación'],
                ['approved', 'Aprobada'],
                ['rejected', 'Rechazada'],
            ])}
            {select(
                'Tipo',
                filterKeys.service,
                Object.entries(translatedServices),
            )}
            <label>
                Validez desde
                <input
                    type="date"
                    value={filters.valid_from ?? ''}
                    onChange={(e) => update({ valid_from: e.target.value })}
                />
            </label>
            <label>
                Validez hasta
                <input
                    type="date"
                    value={filters.valid_to ?? ''}
                    onChange={(e) => update({ valid_to: e.target.value })}
                />
            </label>
            <label>
                Importe mínimo
                <input
                    type="number"
                    min="0"
                    step="0.01"
                    value={filters.min_amount ?? ''}
                    onChange={(e) => update({ min_amount: e.target.value })}
                />
            </label>
            <label>
                Importe máximo
                <input
                    type="number"
                    min="0"
                    step="0.01"
                    value={filters.max_amount ?? ''}
                    onChange={(e) => update({ max_amount: e.target.value })}
                />
            </label>
            <Button variant="outline" onClick={() => router.get('/quotes')}>
                Limpiar filtros
            </Button>
        </div>
    );
    const view = filters.view ?? 'table';
    const rowCard = (q: QuoteListRow) => (
        <article className="quotes-mobile-card" key={q.id}>
            <Link href={quoteUrl(filters, q.id)}>{q.number}</Link>
            <span>{customerName(q)}</span>
            <span className={`quote-status quote-status-${q.effective_status}`}>
                {statusLabels[q.effective_status]}
            </span>
            <time dateTime={q.issue_date}>{quoteDate(q.issue_date)}</time>
            <strong>{quoteMoney(q.amount)}</strong>
            <small>
                {q.creator?.name ?? '—'} · {quoteDate(q.valid_until)}
            </small>
        </article>
    );

    return (
        <>
            <Head title="Presupuestos" />
            <PageShell className="quotes-workspace">
                <PageHeader
                    title="Presupuestos"
                    description="Gestiona tus presupuestos desde la creación hasta su aceptación."
                    breadcrumbs={[
                        { title: 'Comercial', href: '/commercial' },
                        { title: 'Presupuestos', href: '/quotes' },
                    ]}
                    secondaryNavigation={null}
                    actions={
                        <>
                            <nav
                                className="quotes-view-switch"
                                aria-label="Vista de presupuestos"
                            >
                                {[
                                    ['table', 'Tabla', List],
                                    ['kanban', 'Kanban', Columns3],
                                    ['calendar', 'Calendario', CalendarDays],
                                ].map(([key, label, Icon]) => {
                                    const ViewIcon = Icon as typeof List;

                                    return (
                                        <Button
                                            key={String(key)}
                                            variant={
                                                view === key
                                                    ? 'secondary'
                                                    : 'outline'
                                            }
                                            size="sm"
                                            aria-pressed={view === key}
                                            onClick={() =>
                                                update({ view: String(key) })
                                            }
                                        >
                                            <ViewIcon />
                                            {String(label)}
                                        </Button>
                                    );
                                })}
                            </nav>
                            <Button asChild>
                                <Link href="/quotes/create">
                                    <Plus />
                                    Nuevo presupuesto
                                </Link>
                            </Button>
                        </>
                    }
                />
                <div className="quotes-list-layout">
                    <div className="quotes-list-main">
                        <div className="quotes-kpis">
                            {statuses.map((s) => (
                                <button
                                    key={s}
                                    onClick={() =>
                                        update({
                                            status:
                                                filters.status === s ? '' : s,
                                        })
                                    }
                                    aria-pressed={filters.status === s}
                                >
                                    <span
                                        className={`quote-status quote-status-${s}`}
                                    >
                                        {statusLabels[s]}
                                    </span>
                                    <strong>{summary.statuses[s].count}</strong>
                                    <small>
                                        {quoteMoney(summary.statuses[s].amount)}
                                    </small>
                                </button>
                            ))}
                            <div>
                                <span>Total del filtro</span>
                                <strong>{summary.total}</strong>
                                <small>IVA incluido</small>
                            </div>
                        </div>
                        <div className="quotes-filters">
                            <label className="quotes-search">
                                <Search size={16} />
                                <input
                                    aria-label="Buscar presupuestos"
                                    placeholder="Buscar presupuestos..."
                                    value={search}
                                    onChange={(e) => {
                                        const value = e.target.value;
                                        setSearch(value);

                                        if (timer.current) {
                                            clearTimeout(timer.current);
                                        }

                                        timer.current = setTimeout(
                                            () => update({ search: value }),
                                            300,
                                        );
                                    }}
                                />
                            </label>
                            {select(
                                'Estado',
                                filterKeys.status,
                                statuses.map((s) => [s, statusLabels[s]]),
                            )}
                            {select(
                                'Cliente',
                                'customer',
                                customers.map((c) => [
                                    String(c.id),
                                    c.company_name ||
                                        `${c.first_name} ${c.last_name ?? ''}`,
                                ]),
                            )}
                            {select(
                                'Autor',
                                filterKeys.owner,
                                owners.map((o) => [String(o.id), o.name]),
                            )}
                            <label className="quotes-filter">
                                Fecha desde
                                <input
                                    type="date"
                                    value={filters.date_from ?? ''}
                                    onChange={(e) =>
                                        update({ date_from: e.target.value })
                                    }
                                />
                            </label>
                            <label className="quotes-filter">
                                Fecha hasta
                                <input
                                    type="date"
                                    value={filters.date_to ?? ''}
                                    onChange={(e) =>
                                        update({ date_to: e.target.value })
                                    }
                                />
                            </label>
                            <Sheet>
                                <SheetTrigger asChild>
                                    <Button variant="outline">
                                        <SlidersHorizontal />
                                        Más filtros
                                    </Button>
                                </SheetTrigger>
                                <SheetContent>
                                    <SheetHeader>
                                        <SheetTitle>
                                            Filtros de presupuestos
                                        </SheetTitle>
                                    </SheetHeader>
                                    {moreFilters}
                                </SheetContent>
                            </Sheet>
                        </div>
                        <div className="quotes-table-card" aria-busy={loading}>
                            {loading && (
                                <div className="quotes-loading" role="status">
                                    <Skeleton className="h-5 w-48" />
                                    Cargando presupuestos…
                                </div>
                            )}
                            <div className="quotes-list-heading">
                                <span>
                                    Mostrando {quotes.from ?? 0}–
                                    {quotes.to ?? 0} de {quotes.total}{' '}
                                    presupuestos
                                </span>
                                {select(
                                    'Ordenar por',
                                    filterKeys.sort,
                                    sortOptions,
                                )}
                                {select(
                                    'Dirección',
                                    filterKeys.direction,
                                    directionOptions,
                                )}
                            </div>
                            {view === 'table' ? (
                                <>
                                    <div className="quotes-table-scroll">
                                        <table>
                                            <thead>
                                                <tr>
                                                    {[
                                                        'Referencia',
                                                        'Cliente',
                                                        'Oportunidad',
                                                        'Tipo',
                                                        'Importe',
                                                        'Estado',
                                                        'Autor',
                                                        'Fecha',
                                                        'Validez',
                                                        'Acciones',
                                                    ].map((h) => (
                                                        <th key={h} scope="col">
                                                            {h}
                                                        </th>
                                                    ))}
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {quotes.data.map((q) => (
                                                    <tr key={q.id}>
                                                        <td>
                                                            <Link
                                                                href={quoteUrl(
                                                                    filters,
                                                                    q.id,
                                                                )}
                                                            >
                                                                {q.number}
                                                            </Link>
                                                            <small>
                                                                v{q.version}
                                                            </small>
                                                        </td>
                                                        <td>
                                                            {customerName(q)}
                                                        </td>
                                                        <td>
                                                            {q.opportunity
                                                                ?.title ??
                                                                'Presupuesto directo'}
                                                        </td>
                                                        <td>
                                                            {q.service_type
                                                                ? translatedServices[
                                                                      q.service_type as ServiceType
                                                                  ]
                                                                : '—'}
                                                        </td>
                                                        <td className="quotes-amount">
                                                            {quoteMoney(
                                                                q.amount,
                                                            )}
                                                        </td>
                                                        <td>
                                                            <span
                                                                className={`quote-status quote-status-${q.effective_status}`}
                                                            >
                                                                {
                                                                    statusLabels[
                                                                        q
                                                                            .effective_status
                                                                    ]
                                                                }
                                                            </span>
                                                            {q.approval_status ===
                                                                'pending' && (
                                                                <small>
                                                                    Pendiente de
                                                                    aprobación
                                                                </small>
                                                            )}
                                                        </td>
                                                        <td>
                                                            {q.creator?.name ??
                                                                '—'}
                                                        </td>
                                                        <td>
                                                            {quoteDate(
                                                                q.issue_date,
                                                            )}
                                                        </td>
                                                        <td>
                                                            {quoteDate(
                                                                q.valid_until,
                                                            )}
                                                        </td>
                                                        <td>
                                                            <Link
                                                                href={quoteUrl(
                                                                    filters,
                                                                    q.id,
                                                                )}
                                                                aria-label={`Abrir ${q.number}`}
                                                            >
                                                                ···
                                                            </Link>
                                                        </td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                    <div className="quotes-mobile-list">
                                        {quotes.data.map(rowCard)}
                                    </div>
                                </>
                            ) : view === 'kanban' ? (
                                <div className="quotes-kanban">
                                    {statuses.map((s) => (
                                        <section key={s}>
                                            <h2>
                                                {statusLabels[s]} ·{' '}
                                                {summary.statuses[s].count}
                                            </h2>
                                            {quotes.data
                                                .filter(
                                                    (q) =>
                                                        q.effective_status ===
                                                        s,
                                                )
                                                .map(rowCard)}
                                        </section>
                                    ))}
                                </div>
                            ) : (
                                <div className="quotes-calendar">
                                    <h2>
                                        Validez de los presupuestos de esta
                                        página
                                    </h2>
                                    {[...quotes.data]
                                        .sort((a, b) =>
                                            (
                                                a.valid_until ?? '9999'
                                            ).localeCompare(
                                                b.valid_until ?? '9999',
                                            ),
                                        )
                                        .map((q) => (
                                            <div key={q.id}>
                                                <time>
                                                    {quoteDate(q.valid_until)}
                                                </time>
                                                {rowCard(q)}
                                            </div>
                                        ))}
                                </div>
                            )}
                            {quotes.total === 0 && (
                                <div className="quotes-empty">
                                    <p>
                                        No hay presupuestos que coincidan con
                                        los filtros.
                                    </p>
                                    <Button asChild>
                                        <Link href="/quotes/create">
                                            Nuevo presupuesto
                                        </Link>
                                    </Button>
                                </div>
                            )}
                            {view !== 'table' && (
                                <p className="quotes-source">
                                    Vista de los registros paginados; los
                                    totales resumen todo el filtro.
                                </p>
                            )}
                            <div className="quotes-pagination">
                                <PaginationBar
                                    links={quotes.links}
                                    total={quotes.total}
                                    noun="presupuestos"
                                />
                                <label>
                                    Registros por página
                                    <select
                                        value={filters.per_page ?? '15'}
                                        onChange={(e) =>
                                            update({ per_page: e.target.value })
                                        }
                                    >
                                        {[15, 30, 50].map((n) => (
                                            <option key={n} value={n}>
                                                {n} por página
                                            </option>
                                        ))}
                                    </select>
                                </label>
                            </div>
                        </div>
                    </div>
                    <aside className="quotes-list-aside">
                        <section className="quote-panel">
                            <h2>Estado de presupuestos</h2>
                            <svg
                                className="quotes-status-chart"
                                viewBox="0 0 100 100"
                                role="img"
                                aria-label="Estado de presupuestos"
                            >
                                <circle
                                    cx="50"
                                    cy="50"
                                    r="37"
                                    fill="none"
                                    stroke="var(--muted)"
                                    strokeWidth="12"
                                />
                                {statuses.map((s, index) => {
                                    const percentage = summary.total
                                        ? (summary.statuses[s].count /
                                              summary.total) *
                                          100
                                        : 0;
                                    const previous = summary.total
                                        ? (statuses
                                              .slice(0, index)
                                              .reduce(
                                                  (sum, state) =>
                                                      sum +
                                                      summary.statuses[state]
                                                          .count,
                                                  0,
                                              ) /
                                              summary.total) *
                                          100
                                        : 0;

                                    return (
                                        <circle
                                            key={s}
                                            cx="50"
                                            cy="50"
                                            r="37"
                                            fill="none"
                                            stroke={statusColors[s]}
                                            strokeWidth="12"
                                            pathLength="100"
                                            strokeDasharray={`${percentage} ${100 - percentage}`}
                                            strokeDashoffset={-previous}
                                            transform="rotate(-90 50 50)"
                                        />
                                    );
                                })}
                                <text
                                    x="50"
                                    y="51"
                                    textAnchor="middle"
                                    fill="currentColor"
                                    fontSize="16"
                                    fontWeight="600"
                                >
                                    {summary.total}
                                </text>
                                <text
                                    x="50"
                                    y="62"
                                    textAnchor="middle"
                                    fill="currentColor"
                                    fontSize="6"
                                >
                                    Presupuestos
                                </text>
                            </svg>
                            <span>Presupuestos del filtro</span>
                            {statuses.map((s) => (
                                <div className="quotes-state-row" key={s}>
                                    <span
                                        className={`quote-status quote-status-${s}`}
                                    >
                                        {statusLabels[s]}
                                    </span>
                                    <strong>{summary.statuses[s].count}</strong>
                                </div>
                            ))}
                        </section>
                        <section className="quote-panel">
                            <h2>Importes por estado</h2>
                            {statuses.map((s) => (
                                <div className="quotes-state-row" key={s}>
                                    <span>{statusLabels[s]}</span>
                                    <strong>
                                        {quoteMoney(summary.statuses[s].amount)}
                                    </strong>
                                </div>
                            ))}
                            <p className="quotes-source">
                                Importes con IVA, mismo filtro que el listado.
                            </p>
                        </section>
                        <section className="quote-panel">
                            <h2>Actividad reciente</h2>
                            {recentActivity.map((a) => (
                                <Link
                                    className="quotes-activity"
                                    key={a.id}
                                    href={quoteUrl(filters, a.quote.id)}
                                >
                                    <strong>{a.quote.number}</strong>
                                    <span>
                                        {statusLabels[a.to_status]} ·{' '}
                                        {a.changed_by?.name ?? '—'}
                                    </span>
                                    <time>{quoteDate(a.changed_at)}</time>
                                </Link>
                            ))}
                            {!recentActivity.length && (
                                <p>Sin actividad registrada.</p>
                            )}
                        </section>
                    </aside>
                </div>
            </PageShell>
        </>
    );
}
function customerName(q: QuoteListRow) {
    return (
        q.customer?.company_name ||
        `${q.customer?.first_name ?? ''} ${q.customer?.last_name ?? ''}`.trim() ||
        '—'
    );
}
QuotesIndex.layout = {
    breadcrumbs: [
        { title: 'Comercial', href: '/commercial' },
        { title: 'Presupuestos', href: '/quotes' },
    ],
};

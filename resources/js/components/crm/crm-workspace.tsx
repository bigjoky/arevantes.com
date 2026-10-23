import { Form, Link, router, usePage } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import type { Opportunity } from '@/pages/opportunities/index';

type Filters = Record<string, string | undefined>;
type Pagination = {
    current_page: number;
    last_page: number;
    total: number;
    prev: string | null;
    next: string | null;
};
type Task = {
    id: number;
    title: string;
    due_at: string;
    opportunity_id: number;
    opportunity: { title: string } | null;
};
type Event = {
    id: number;
    summary: string;
    occurred_at: string;
    opportunity_id: number;
    actor: { name: string } | null;
};
const money = (value: number) =>
    new Intl.NumberFormat('es-ES', {
        style: 'currency',
        currency: 'EUR',
    }).format(value);
const date = (value: string) =>
    new Intl.DateTimeFormat('es-ES', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    }).format(new Date(value));
const url = (filters: Filters) =>
    '/opportunities?' +
    new URLSearchParams(
        Object.entries(filters).filter((entry): entry is [string, string] =>
            Boolean(entry[1]),
        ),
    ).toString();
export function CrmWorkspace({
    opportunities,
    stages,
    totals,
    pagination,
    filters,
    users,
    sources,
    kinds,
    tasks,
    calendar,
    events,
    onCreate,
    onMove,
}: {
    opportunities: Opportunity[];
    stages: Record<string, string>;
    totals: Record<string, { count: number; amount: number }>;
    pagination: Pagination | null;
    filters: Filters;
    users: Array<{ id: number; name: string }>;
    sources: Record<string, string>;
    kinds: Record<string, string>;
    calendar: {
        month: string;
        prev_month: string;
        next_month: string;
        prev: string | null;
        next: string | null;
        total: number;
    } | null;
    tasks: Task[];
    events: Event[];
    onCreate: (stage?: string) => void;
    onMove: (item: Opportunity, stage: string) => void;
}) {
    const page = usePage();
    const debounce = useRef<ReturnType<typeof setTimeout> | null>(null);
    useEffect(
        () => () => {
            if (debounce.current) {
                clearTimeout(debounce.current);
            }
        },
        [],
    );
    const [dragged, setDragged] = useState<Opportunity | null>(null);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState('');
    useEffect(() => {
        const start = router.on('start', () => setLoading(true));
        const finish = router.on('finish', () => setLoading(false));

        return () => {
            start();
            finish();
        };
    }, []);
    const move = (item: Opportunity, stage: string) => {
        if (!item.available_stages.includes(stage)) {
            setError(
                'Esta transición no está disponible. Utiliza las etapas permitidas.',
            );

            return;
        }

        setError('');
        onMove(item, stage);
        setDragged(null);
    };
    const table = (items: Opportunity[]) => (
        <div className="crm-table-scroll">
            <table className="crm-table">
                <thead>
                    <tr>
                        {[
                            'Nombre',
                            'Cliente',
                            'Etapa',
                            'Valor',
                            'Prob.',
                            'Comercial',
                            'Creación',
                            'Próxima acción',
                        ].map((label) => (
                            <th key={label}>{label}</th>
                        ))}
                    </tr>
                </thead>
                <tbody>
                    {items.map((item) => (
                        <tr key={item.id}>
                            <td>
                                <Link href={`/opportunities/${item.id}`}>
                                    {item.title}
                                </Link>
                            </td>
                            <td>
                                {item.customer?.company_name ||
                                    [
                                        item.customer?.first_name,
                                        item.customer?.last_name,
                                    ]
                                        .filter(Boolean)
                                        .join(' ') ||
                                    '—'}
                            </td>
                            <td>{stages[item.stage] || item.stage}</td>
                            <td>{money(Number(item.estimated_value))}</td>
                            <td>{item.probability}%</td>
                            <td>{item.owner?.name || 'Sin asignar'}</td>
                            <td>{date(item.created_at)}</td>
                            <td>
                                {item.next_activity ? (
                                    <Link href={`/opportunities/${item.id}`}>
                                        {item.next_activity.title}
                                        <time>
                                            {date(item.next_activity.due_at)}
                                        </time>
                                    </Link>
                                ) : (
                                    'Sin próxima acción'
                                )}
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
            {!items.length && (
                <p className="crm-empty">
                    No hay oportunidades con estos filtros.
                </p>
            )}
        </div>
    );

    const filterForm = (
        <Form
            action="/opportunities"
            method="get"
            className="crm-filters"
            onStart={() => setLoading(true)}
            onFinish={() => setLoading(false)}
        >
            <input type="hidden" name="view" value={filters.view || 'kanban'} />
            {filters.archived && (
                <input type="hidden" name="archived" value="1" />
            )}
            <label className="crm-search">
                <span>Buscar oportunidades</span>
                <Input
                    name="search"
                    onChange={(event) => {
                        if (debounce.current) {
                            clearTimeout(debounce.current);
                        }

                        const form = event.currentTarget.form;

                        if (!form) {
                            return;
                        }

                        const values = Object.fromEntries(
                            new FormData(form).entries(),
                        ) as Filters;
                        debounce.current = setTimeout(
                            () =>
                                router.get(
                                    url(values),
                                    {},
                                    {
                                        preserveState: true,
                                        preserveScroll: true,
                                    },
                                ),
                            350,
                        );
                    }}
                    placeholder="Buscar oportunidades…"
                    defaultValue={filters.search}
                />
            </label>
            <label>
                Comercial
                <select name="owner" defaultValue={filters.owner || ''}>
                    <option value="">Todos</option>
                    <option value="me">Mis oportunidades</option>
                    {users.map((user) => (
                        <option key={user.id} value={user.id}>
                            {user.name}
                        </option>
                    ))}
                </select>
            </label>
            <label>
                Origen
                <select name="source" defaultValue={filters.source || ''}>
                    <option value="">Todos</option>
                    {Object.entries(sources).map(([key, label]) => (
                        <option key={key} value={key}>
                            {label}
                        </option>
                    ))}
                </select>
            </label>
            <label>
                Estado
                <select name="status" defaultValue={filters.status || 'all'}>
                    <option value="all">Todas</option>
                    <option value="active">Oportunidades activas</option>
                    <option value="won">Ganadas</option>
                    <option value="lost">Perdidas</option>
                </select>
            </label>
            <fieldset>
                <legend>Fecha de creación</legend>
                <div>
                    <input
                        aria-label="Desde"
                        name="from"
                        type="date"
                        defaultValue={filters.from}
                    />
                    <input
                        aria-label="Hasta"
                        name="to"
                        type="date"
                        defaultValue={filters.to}
                    />
                </div>
            </fieldset>
            <details>
                <summary>Más filtros</summary>
                <label>
                    Etapa
                    <select name="stage" defaultValue={filters.stage || ''}>
                        <option value="">Todas</option>
                        {Object.entries(stages).map(([key, label]) => (
                            <option key={key} value={key}>
                                {label}
                            </option>
                        ))}
                    </select>
                </label>
                <label>
                    Seguimiento
                    <select
                        name="attention"
                        defaultValue={filters.attention || ''}
                    >
                        <option value="">Todos</option>
                        <option value="stale">Estancadas</option>
                        <option value="no_action">Sin próxima acción</option>
                        <option value="overdue">Cierres vencidos</option>
                        <option value="response_sla">Fuera de SLA</option>
                        <option value="renewal_due">Renovaciones</option>
                    </select>
                </label>
                <label>
                    Tipo comercial
                    <select
                        name="commercial_kind"
                        defaultValue={filters.commercial_kind || ''}
                    >
                        <option value="">Todos</option>
                        {Object.entries(kinds).map(([key, label]) => (
                            <option key={key} value={key}>
                                {label}
                            </option>
                        ))}
                    </select>
                </label>
                <label>
                    Ordenar
                    <select
                        name="sort"
                        defaultValue={filters.sort || 'created_at'}
                    >
                        <option value="created_at">Creación</option>
                        <option value="title">Nombre</option>
                        <option value="estimated_value">Valor</option>
                    </select>
                </label>
                <label>
                    Dirección
                    <select
                        name="direction"
                        defaultValue={filters.direction || 'desc'}
                    >
                        <option value="desc">Descendente</option>
                        <option value="asc">Ascendente</option>
                    </select>
                </label>
            </details>
            <Button type="submit" variant="outline" disabled={loading}>
                Aplicar
            </Button>
            <Link href="/opportunities">Limpiar</Link>
        </Form>
    );

    return (
        <>
            <div className="hidden sm:block">{filterForm}</div>
            <Sheet>
                <SheetTrigger asChild>
                    <Button className="sm:hidden" variant="outline">
                        Filtros CRM
                    </Button>
                </SheetTrigger>
                <SheetContent
                    aria-describedby={undefined}
                    side="bottom"
                    className="max-h-[85vh] overflow-y-auto"
                >
                    <SheetHeader>
                        <SheetTitle>Filtros CRM</SheetTitle>
                    </SheetHeader>
                    {filterForm}
                </SheetContent>
            </Sheet>
            {Object.keys(page.props.errors).length > 0 && (
                <p role="alert">
                    {Object.values(page.props.errors).join(' · ')}
                </p>
            )}
            {error && <p role="alert">{error}</p>}
            <section aria-busy={loading} className="crm-primary">
                {filters.view === 'calendar' ? (
                    <div className="crm-panel">
                        <header>
                            <h2>Agenda comercial · {calendar?.month}</h2>
                            {calendar && (
                                <nav aria-label="Mes de agenda">
                                    <Link
                                        href={url({
                                            ...filters,
                                            calendar_month: calendar.prev_month,
                                        })}
                                    >
                                        Mes anterior
                                    </Link>{' '}
                                    ·{' '}
                                    <Link
                                        href={url({
                                            ...filters,
                                            calendar_month: calendar.next_month,
                                        })}
                                    >
                                        Mes siguiente
                                    </Link>
                                </nav>
                            )}
                        </header>
                        <p className="crm-caption">
                            Próximas acciones con fecha real de las
                            oportunidades filtradas
                        </p>
                        {tasks.map((task) => (
                            <Link
                                className="crm-task"
                                key={task.id}
                                href={`/opportunities/${task.opportunity_id}`}
                            >
                                <strong>{task.title}</strong>
                                <time>{date(task.due_at)}</time>
                                <span>{task.opportunity?.title}</span>
                            </Link>
                        ))}
                        {calendar && (
                            <nav
                                className="crm-pagination"
                                aria-label="Paginación de agenda"
                            >
                                {calendar.prev && (
                                    <Link href={calendar.prev}>Anterior</Link>
                                )}
                                <span>{calendar.total} acciones</span>
                                {calendar.next && (
                                    <Link href={calendar.next}>Siguiente</Link>
                                )}
                            </nav>
                        )}
                        {!tasks.length && (
                            <p className="crm-empty">
                                No hay acciones programadas con estos filtros.
                            </p>
                        )}
                    </div>
                ) : filters.view === 'table' ? (
                    <section className="crm-panel">
                        <h2>
                            Oportunidades <span>{pagination?.total || 0}</span>
                        </h2>
                        {table(opportunities)}
                        {pagination && (
                            <nav
                                className="crm-pagination"
                                aria-label="Paginación de oportunidades"
                            >
                                {pagination.prev && (
                                    <Link href={pagination.prev}>Anterior</Link>
                                )}
                                <span>
                                    Página {pagination.current_page} de{' '}
                                    {pagination.last_page}
                                </span>
                                {pagination.next && (
                                    <Link href={pagination.next}>
                                        Siguiente
                                    </Link>
                                )}
                            </nav>
                        )}
                    </section>
                ) : (
                    <section
                        className="crm-board"
                        aria-label="Pipeline comercial"
                        tabIndex={0}
                    >
                        <div className="crm-columns">
                            {Object.entries(stages).map(
                                ([stage, label], index) => {
                                    const items = opportunities.filter(
                                        (item) => item.stage === stage,
                                    );
                                    const total = totals[stage];

                                    return (
                                        <section
                                            className="crm-column"
                                            key={stage}
                                            onDragOver={(event) => {
                                                if (
                                                    dragged?.available_stages.includes(
                                                        stage,
                                                    )
                                                ) {
                                                    event.preventDefault();
                                                }
                                            }}
                                            onDrop={(event) => {
                                                event.preventDefault();

                                                if (dragged) {
                                                    move(dragged, stage);
                                                }
                                            }}
                                        >
                                            <header data-tone={index % 5}>
                                                <h2>
                                                    {index + 1}. {label}
                                                </h2>
                                                <div>
                                                    <strong>
                                                        {total?.count || 0}
                                                    </strong>
                                                    <span>
                                                        {money(
                                                            total?.amount || 0,
                                                        )}
                                                    </span>
                                                </div>
                                            </header>
                                            <div className="crm-cards">
                                                {items.map((item) => (
                                                    <article
                                                        key={item.id}
                                                        draggable={
                                                            !item.deleted_at
                                                        }
                                                        onDragStart={() =>
                                                            setDragged(item)
                                                        }
                                                        onDragEnd={() =>
                                                            setDragged(null)
                                                        }
                                                        className="crm-card"
                                                    >
                                                        <Link
                                                            href={`/opportunities/${item.id}`}
                                                        >
                                                            <strong>
                                                                {item.customer
                                                                    ?.company_name ||
                                                                    item.title}
                                                            </strong>
                                                            <span>
                                                                {item.customer
                                                                    ?.company_name
                                                                    ? item.title
                                                                    : item.code}
                                                            </span>
                                                            <div>
                                                                <small>
                                                                    {item.source
                                                                        ? sources[
                                                                              item
                                                                                  .source
                                                                          ] ||
                                                                          item.source
                                                                        : 'Sin origen'}
                                                                </small>
                                                                <b>
                                                                    {money(
                                                                        Number(
                                                                            item.estimated_value,
                                                                        ),
                                                                    )}
                                                                </b>
                                                            </div>
                                                            <footer>
                                                                <time>
                                                                    {
                                                                        item.days_in_stage
                                                                    }{' '}
                                                                    días en
                                                                    etapa
                                                                    {item.is_stale
                                                                        ? ' · estancada'
                                                                        : ''}
                                                                </time>
                                                                <abbr
                                                                    title={
                                                                        item
                                                                            .owner
                                                                            ?.name ||
                                                                        'Sin asignar'
                                                                    }
                                                                >
                                                                    {item.owner?.name
                                                                        .split(
                                                                            ' ',
                                                                        )
                                                                        .map(
                                                                            (
                                                                                word,
                                                                            ) =>
                                                                                word[0],
                                                                        )
                                                                        .slice(
                                                                            0,
                                                                            2,
                                                                        )
                                                                        .join(
                                                                            '',
                                                                        ) ||
                                                                        '—'}
                                                                </abbr>
                                                            </footer>
                                                        </Link>
                                                        {!item.deleted_at && (
                                                            <select
                                                                disabled={
                                                                    loading
                                                                }
                                                                aria-label={`Cambiar etapa de ${item.title}`}
                                                                value={
                                                                    item.stage
                                                                }
                                                                onChange={(
                                                                    event,
                                                                ) =>
                                                                    move(
                                                                        item,
                                                                        event
                                                                            .target
                                                                            .value,
                                                                    )
                                                                }
                                                            >
                                                                {item.available_stages.map(
                                                                    (key) => (
                                                                        <option
                                                                            key={
                                                                                key
                                                                            }
                                                                            value={
                                                                                key
                                                                            }
                                                                        >
                                                                            {stages[
                                                                                key
                                                                            ] ||
                                                                                key}
                                                                        </option>
                                                                    ),
                                                                )}
                                                            </select>
                                                        )}
                                                    </article>
                                                ))}
                                                {!items.length && (
                                                    <p className="crm-empty">
                                                        No hay oportunidades en
                                                        esta etapa.
                                                    </p>
                                                )}
                                                {(total?.count || 0) > 8 && (
                                                    <nav
                                                        aria-label={`Lotes de ${label}`}
                                                        className="grid gap-1 text-center text-xs"
                                                    >
                                                        <span>
                                                            Lote{' '}
                                                            {filters.page_stage ===
                                                            stage
                                                                ? filters.stage_page ||
                                                                  1
                                                                : 1}{' '}
                                                            de{' '}
                                                            {Math.ceil(
                                                                (total?.count ||
                                                                    0) / 8,
                                                            )}
                                                        </span>
                                                        {filters.page_stage ===
                                                            stage &&
                                                            Number(
                                                                filters.stage_page ||
                                                                    1,
                                                            ) > 1 && (
                                                                <Link
                                                                    href={url({
                                                                        ...filters,
                                                                        page_stage:
                                                                            stage,
                                                                        stage_page:
                                                                            String(
                                                                                Number(
                                                                                    filters.stage_page ||
                                                                                        1,
                                                                                ) -
                                                                                    1,
                                                                            ),
                                                                    })}
                                                                    preserveScroll
                                                                >
                                                                    Lote
                                                                    anterior
                                                                </Link>
                                                            )}
                                                        {(filters.page_stage ===
                                                        stage
                                                            ? Number(
                                                                  filters.stage_page ||
                                                                      1,
                                                              )
                                                            : 1) <
                                                            Math.ceil(
                                                                (total?.count ||
                                                                    0) / 8,
                                                            ) && (
                                                            <Link
                                                                href={url({
                                                                    ...filters,
                                                                    page_stage:
                                                                        stage,
                                                                    stage_page:
                                                                        String(
                                                                            filters.page_stage ===
                                                                                stage
                                                                                ? Number(
                                                                                      filters.stage_page ||
                                                                                          1,
                                                                                  ) +
                                                                                      1
                                                                                : 2,
                                                                        ),
                                                                })}
                                                                preserveScroll
                                                            >
                                                                Siguiente lote
                                                            </Link>
                                                        )}
                                                    </nav>
                                                )}
                                                {!filters.archived && (
                                                    <Button
                                                        variant="ghost"
                                                        onClick={() =>
                                                            onCreate(stage)
                                                        }
                                                    >
                                                        <Plus /> Añadir
                                                        oportunidad
                                                    </Button>
                                                )}
                                            </div>
                                        </section>
                                    );
                                },
                            )}
                        </div>
                    </section>
                )}
            </section>
            {filters.view !== 'table' && filters.view !== 'calendar' && (
                <div className="crm-bottom">
                    <section className="crm-panel">
                        <header>
                            <h2>Oportunidades</h2>
                            <Link href={url({ ...filters, view: 'table' })}>
                                Ver todas
                            </Link>
                        </header>
                        {table(opportunities.slice(0, 8))}
                    </section>
                    <aside>
                        <section className="crm-panel">
                            <h2>Tareas comerciales</h2>
                            {tasks.map((task) => (
                                <Link
                                    className="crm-task"
                                    key={task.id}
                                    href={`/opportunities/${task.opportunity_id}`}
                                >
                                    <strong>{task.title}</strong>
                                    <time>{date(task.due_at)}</time>
                                    <span>{task.opportunity?.title}</span>
                                </Link>
                            ))}
                            {!tasks.length && (
                                <p className="crm-empty">
                                    No hay tareas comerciales pendientes.
                                </p>
                            )}
                        </section>
                        <section className="crm-panel">
                            <h2>Actividad reciente</h2>
                            {events.map((event) => (
                                <Link
                                    className="crm-event"
                                    key={event.id}
                                    href={`/opportunities/${event.opportunity_id}`}
                                >
                                    <strong>{event.summary}</strong>
                                    <time>{date(event.occurred_at)}</time>
                                    <span>{event.actor?.name || '—'}</span>
                                </Link>
                            ))}
                            {!events.length && (
                                <p className="crm-empty">
                                    No hay actividad reciente.
                                </p>
                            )}
                        </section>
                    </aside>
                </div>
            )}
        </>
    );
}

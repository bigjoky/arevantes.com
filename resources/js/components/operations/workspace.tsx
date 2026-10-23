import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    CalendarDays,
    CheckCircle2,
    ClipboardList,
    Clock3,
    Plus,
    Wrench,
} from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { useCustomerNavigation } from '@/components/customers/use-customer-navigation';
import { PageShell, PageHeader } from '@/components/page-shell';
import { Button } from '@/components/ui/button';

export type Order = {
    id: number;
    number: string;
    title: string;
    kind: string;
    status: string;
    scheduled_start: string | null;
    scheduled_end: string | null;
    updated_at: string;
    scheduled_start_day?: string;
    scheduled_end_day?: string;
    estimated_duration_minutes: number | null;
    assigned_user_id: number | null;
    assigned_user: { id: number; name: string } | null;
    installation: {
        id: number;
        project_reference: string | null;
        site_name: string | null;
        site_address_line_1: string | null;
        site_city: string | null;
        customer: {
            id: number;
            company_name: string | null;
            first_name: string;
            last_name: string | null;
        } | null;
    };
    checklist_items_count: number;
    completed_tasks_count: number;
    planning_issues?: { message: string }[];
    instructions: string | null;
    completion_notes: string | null;
    completed_at: string | null;
};
type Page<T> = {
    data: T[];
    total: number;
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};
export type Workspace = {
    orders: Order[] | Page<Order>;
    users: { id: number; name: string }[];
    canManage: boolean;
    filters: Record<string, string>;
    statuses: string[];
    kinds: string[];
    timezone: string;
    range: { start: string; end: string; previous: string; next: string };
    days: string[];
    backlog: Page<Order>;
    truncated: boolean;
};
export const statuses: Record<string, string> = {
    planned: 'Planificada',
    in_progress: 'En ejecución',
    blocked: 'Bloqueada',
    completed: 'Finalizada',
    cancelled: 'Cancelada',
};
export const kinds: Record<string, string> = {
    survey: 'Visita técnica',
    installation: 'Instalación',
    commissioning: 'Puesta en marcha',
    maintenance: 'Mantenimiento',
    repair: 'Reparación',
};
export function date(value: string | null) {
    return value
        ? new Date(value).toLocaleString('es-ES', {
              day: '2-digit',
              month: '2-digit',
              hour: '2-digit',
              minute: '2-digit',
          })
        : '—';
}
export function Badge({ status }: { status: string }) {
    return (
        <span className={`ops-badge ops-${status}`}>
            {statuses[status] ?? status}
        </span>
    );
}
export function Card({
    title,
    href,
    children,
}: {
    title: string;
    href?: string;
    children: ReactNode;
}) {
    return (
        <section className="ops-card">
            <header>
                <h2>{title}</h2>
                {href && <Link href={href}>Ver todos ›</Link>}
            </header>
            {children}
        </section>
    );
}
export function Empty() {
    return <p className="ops-empty">No hay trabajos para esta selección.</p>;
}
export function Pagination({ page }: { page: Page<unknown> }) {
    return (
        <footer className="ops-pagination">
            <span>
                {page.total} resultados · Página {page.current_page} de{' '}
                {page.last_page}
            </span>
            <div>
                {page.prev_page_url && (
                    <Link href={page.prev_page_url}>← Anterior</Link>
                )}
                {page.next_page_url && (
                    <Link href={page.next_page_url}>Siguiente →</Link>
                )}
            </div>
        </footer>
    );
}
export function OrderCards({ orders }: { orders: Order[] }) {
    return orders.length ? (
        <div className="ops-order-cards">
            {orders.map((o) => (
                <Link
                    className="ops-order-card"
                    href={`/work-orders/${o.id}`}
                    key={o.id}
                >
                    <ClipboardList size={18} />
                    <div>
                        <strong>{o.number}</strong>
                        <p>{o.title}</p>
                        <small>
                            {o.installation.site_name} ·{' '}
                            {o.assigned_user?.name ?? 'Sin asignar'}
                        </small>
                    </div>
                    <div>
                        <Badge status={o.status} />
                        <small>{date(o.scheduled_start)}</small>
                    </div>
                </Link>
            ))}
        </div>
    ) : (
        <Empty />
    );
}
export function Frame({
    title,
    description,
    actions,
    children,
}: {
    title: string;
    description: string;
    actions?: ReactNode;
    children: ReactNode;
}) {
    const loading = useCustomerNavigation();

    return (
        <PageShell className="ops-workspace" aria-busy={loading}>
            {loading && <p role="status">Cargando operaciones…</p>}
            <Head title={title} />
            <PageHeader
                title={title}
                description={description}
                breadcrumbs={[
                    { title: 'Operaciones', href: '/operations' },
                    { title, href: '/operations' },
                ]}
                primaryAction={actions}
            />
            {children}
        </PageShell>
    );
}
export function Range({ data, path }: { data: Workspace; path: string }) {
    const query = (week: string) =>
        `${path}?week=${week}&view=${data.filters.view ?? 'week'}`;

    return (
        <div className="ops-range">
            <Button asChild variant="outline">
                <Link
                    href={query(data.range.previous)}
                    aria-label="Periodo anterior"
                >
                    ←
                </Link>
            </Button>
            <span>
                <CalendarDays size={15} />
                {data.range.start} – {data.range.end}
            </span>
            <Button asChild variant="outline">
                <Link
                    href={query(data.range.next)}
                    aria-label="Periodo siguiente"
                >
                    →
                </Link>
            </Button>
            <Button asChild variant="outline">
                <Link href={path}>Hoy</Link>
            </Button>
        </div>
    );
}
export function ScheduleForm({
    order,
    users,
    onClose,
}: {
    order: Order;
    users: Workspace['users'];
    onClose: () => void;
}) {
    const local = (v: string | null) =>
        v
            ? new Date(
                  new Date(v).getTime() -
                      new Date(v).getTimezoneOffset() * 60000,
              )
                  .toISOString()
                  .slice(0, 16)
            : '';
    const form = useForm({
        assigned_user_id: String(order.assigned_user_id ?? ''),
        scheduled_start: local(order.scheduled_start),
        scheduled_end: local(order.scheduled_end),
        reschedule_reason: '',
        expected_updated_at: order.updated_at,
    });

    return (
        <form
            className="ops-schedule-form"
            onSubmit={(e) => {
                e.preventDefault();
                form.transform((d) => ({
                    ...d,
                    scheduled_start: new Date(d.scheduled_start).toISOString(),
                    scheduled_end: new Date(d.scheduled_end).toISOString(),
                }));
                form.patch(`/work-orders/${order.id}/schedule`, {
                    preserveScroll: true,
                    onSuccess: onClose,
                });
            }}
        >
            <h3>{order.number} · Asignar / reprogramar</h3>
            <label>
                Técnico
                <select
                    required
                    value={form.data.assigned_user_id}
                    onChange={(e) =>
                        form.setData('assigned_user_id', e.target.value)
                    }
                >
                    <option value="">Seleccionar</option>
                    {users.map((u) => (
                        <option key={u.id} value={u.id}>
                            {u.name}
                        </option>
                    ))}
                </select>
            </label>
            <label>
                Inicio
                <input
                    required
                    type="datetime-local"
                    value={form.data.scheduled_start}
                    onChange={(e) =>
                        form.setData('scheduled_start', e.target.value)
                    }
                />
            </label>
            <label>
                Fin previsto
                <input
                    required
                    type="datetime-local"
                    value={form.data.scheduled_end}
                    onChange={(e) =>
                        form.setData('scheduled_end', e.target.value)
                    }
                />
            </label>
            <label>
                Motivo
                <input
                    required
                    value={form.data.reschedule_reason}
                    onChange={(e) =>
                        form.setData('reschedule_reason', e.target.value)
                    }
                />
            </label>
            {Object.values(form.errors).map((error) => (
                <p role="alert" key={error}>
                    {error}
                </p>
            ))}
            <Button disabled={form.processing}>Guardar planificación</Button>
            <Button variant="outline" type="button" onClick={onClose}>
                Cancelar
            </Button>
        </form>
    );
}
export function Planning({
    data,
    compact = false,
}: {
    data: Workspace;
    compact?: boolean;
}) {
    const [selected, setSelected] = useState<Order | null>(null);
    const orders = data.orders as Order[];

    return (
        <>
            {selected && (
                <ScheduleForm
                    order={selected}
                    users={data.users}
                    onClose={() => setSelected(null)}
                />
            )}
            <div className="ops-planning-layout">
                <Card
                    title="Planificación semanal"
                    href={compact ? '/operations/planning' : undefined}
                >
                    <div className="ops-matrix">
                        <div className="ops-matrix-row">
                            <strong>Técnicos</strong>
                            {data.days.map((day) => (
                                <strong key={day}>
                                    {new Date(
                                        `${day}T12:00:00`,
                                    ).toLocaleDateString('es-ES', {
                                        weekday: 'short',
                                        day: 'numeric',
                                    })}
                                </strong>
                            ))}
                        </div>
                        {data.users.map((user) => (
                            <div className="ops-matrix-row" key={user.id}>
                                <strong>{user.name}</strong>
                                {data.days.map((day) => (
                                    <div key={day}>
                                        {orders
                                            .filter(
                                                (o) =>
                                                    o.assigned_user_id ===
                                                        user.id &&
                                                    o.scheduled_start!.slice(
                                                        0,
                                                        10,
                                                    ) <= day &&
                                                    o.scheduled_end!.slice(
                                                        0,
                                                        10,
                                                    ) >= day,
                                            )
                                            .map((o) => (
                                                <div
                                                    className={`ops-slot ops-${o.status}`}
                                                    key={o.id}
                                                >
                                                    <Link
                                                        href={`/work-orders/${o.id}`}
                                                    >
                                                        <b>{o.number}</b>
                                                        <small>
                                                            {
                                                                o.installation
                                                                    .site_name
                                                            }
                                                        </small>
                                                        <small>
                                                            {date(
                                                                o.scheduled_start,
                                                            )}{' '}
                                                            · {kinds[o.kind]}
                                                        </small>
                                                    </Link>
                                                    {o.planning_issues?.map(
                                                        (issue) => (
                                                            <small
                                                                role="status"
                                                                key={
                                                                    issue.message
                                                                }
                                                            >
                                                                ⚠{' '}
                                                                {issue.message}
                                                            </small>
                                                        ),
                                                    )}
                                                    {!compact &&
                                                        data.canManage && (
                                                            <button
                                                                onClick={() =>
                                                                    setSelected(
                                                                        o,
                                                                    )
                                                                }
                                                            >
                                                                Reprogramar
                                                            </button>
                                                        )}
                                                </div>
                                            ))}
                                    </div>
                                ))}
                            </div>
                        ))}
                    </div>
                    <div className="ops-planning-mobile">
                        <OrderCards orders={orders} />
                        {!compact &&
                            data.canManage &&
                            orders.map((o) => (
                                <Button
                                    key={o.id}
                                    variant="outline"
                                    onClick={() => setSelected(o)}
                                >
                                    Reprogramar {o.number}
                                </Button>
                            ))}
                    </div>
                    {!orders.length && (
                        <div className="ops-desktop-empty">
                            <Empty />
                        </div>
                    )}
                    {data.truncated && (
                        <p role="status">
                            Se ha alcanzado el límite de trabajos. Reduce el
                            rango o selecciona un técnico.
                        </p>
                    )}
                </Card>
                {!compact && (
                    <Card title="Pendientes de planificar">
                        {data.backlog.data.map((o) => (
                            <div className="ops-backlog" key={o.id}>
                                <Link href={`/work-orders/${o.id}`}>
                                    <b>{o.number}</b>
                                    <p>{o.title}</p>
                                </Link>
                                {data.canManage && (
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        onClick={() => setSelected(o)}
                                    >
                                        Asignar
                                    </Button>
                                )}
                            </div>
                        ))}
                        {!data.backlog.total && <Empty />}
                        <Pagination page={data.backlog} />
                    </Card>
                )}
            </div>
        </>
    );
}
export function Filters({ data }: { data: Workspace }) {
    return (
        <form className="ops-filters" method="get">
            <label>
                Buscar
                <input
                    name="q"
                    placeholder="Buscar órdenes de trabajo…"
                    defaultValue={data.filters.q}
                />
            </label>
            <details className="ops-filter-more">
                <summary>Más filtros</summary>
                <div className="ops-filter-options">
                    <label>
                        Estado
                        <select
                            name="status"
                            defaultValue={data.filters.status ?? ''}
                        >
                            <option value="">Todos</option>
                            {data.statuses.map((s) => (
                                <option key={s} value={s}>
                                    {statuses[s]}
                                </option>
                            ))}
                        </select>
                    </label>
                    <label>
                        Tipo
                        <select
                            name="kind"
                            defaultValue={data.filters.kind ?? ''}
                        >
                            <option value="">Todos</option>
                            {data.kinds.map((k) => (
                                <option key={k} value={k}>
                                    {kinds[k]}
                                </option>
                            ))}
                        </select>
                    </label>
                    <label>
                        Técnico
                        <select
                            name="owner"
                            defaultValue={data.filters.owner ?? ''}
                        >
                            <option value="">Todos</option>
                            {data.users.map((u) => (
                                <option key={u.id} value={u.id}>
                                    {u.name}
                                </option>
                            ))}
                        </select>
                    </label>
                    <label>
                        <input
                            type="checkbox"
                            name="mine"
                            value="1"
                            defaultChecked={!!data.filters.mine}
                        />{' '}
                        Mis trabajos
                    </label>
                    <label>
                        Inicio desde
                        <input
                            type="date"
                            name="date_from"
                            defaultValue={data.filters.date_from ?? ''}
                        />
                    </label>
                    <label>
                        Inicio hasta
                        <input
                            type="date"
                            name="date_to"
                            defaultValue={data.filters.date_to ?? ''}
                        />
                    </label>
                    <label>
                        Por página
                        <select
                            name="per_page"
                            defaultValue={data.filters.per_page ?? '15'}
                        >
                            {[10, 15, 30, 50].map((size) => (
                                <option value={size} key={size}>
                                    {size}
                                </option>
                            ))}
                        </select>
                    </label>
                </div>
            </details>
            <Button variant="outline">Filtrar</Button>
            <Link href="/work-orders">Limpiar</Link>
        </form>
    );
}
export function NewOrder({ canManage }: { canManage: boolean }) {
    return canManage ? (
        <Button asChild>
            <Link href="/work-orders/create">
                <Plus size={16} /> Nueva orden de trabajo
            </Link>
        </Button>
    ) : null;
}
export function Progress({ order }: { order: Order }) {
    const total = order.checklist_items_count;

    return (
        <div className="ops-progress">
            <CheckCircle2 size={25} />
            <strong>
                {total
                    ? `${Math.round((order.completed_tasks_count / total) * 100)}%`
                    : '—'}
            </strong>
            <progress
                aria-label="Progreso de tareas"
                max={total || 1}
                value={order.completed_tasks_count}
            />
            <small>
                {total
                    ? `${order.completed_tasks_count} / ${total} tareas completadas`
                    : 'Sin tareas registradas'}
            </small>
        </div>
    );
}
export const metricIcons = [
    CalendarDays,
    Clock3,
    Wrench,
    ClipboardList,
    CheckCircle2,
];
export function Transition({
    order,
    transitions,
}: {
    order: Order;
    transitions: string[];
}) {
    const form = useForm({
        status: '',
        completion_notes: order.completion_notes ?? '',
        expected_updated_at: order.updated_at,
    });

    return (
        <form
            className="ops-schedule-form"
            onSubmit={(e) => {
                e.preventDefault();
                form.patch(`/work-orders/${order.id}`, {
                    preserveScroll: true,
                });
            }}
        >
            <label>
                Estado
                <select
                    required
                    value={form.data.status}
                    onChange={(e) => form.setData('status', e.target.value)}
                >
                    <option value="">Seleccionar transición</option>
                    {transitions.map((s) => (
                        <option key={s} value={s}>
                            {statuses[s]}
                        </option>
                    ))}
                </select>
            </label>
            <label>
                Notas de finalización
                <textarea
                    value={form.data.completion_notes}
                    onChange={(e) =>
                        form.setData('completion_notes', e.target.value)
                    }
                />
            </label>
            {Object.values(form.errors).map((e) => (
                <p key={e} role="alert">
                    {e}
                </p>
            ))}
            <Button disabled={form.processing || !form.data.status}>
                Actualizar estado
            </Button>
        </form>
    );
}
export function completeTask(orderId: number, id: number, completed: boolean) {
    router.patch(
        `/work-orders/${orderId}/checklist/${id}`,
        { completed },
        { preserveScroll: true },
    );
}

import { Head, Link } from '@inertiajs/react';
import { CalendarDays, ChevronLeft, ChevronRight, Clock3 } from 'lucide-react';
import { useState } from 'react';
import type { Order, Workspace } from '@/components/operations/workspace';
import { statuses } from '@/components/operations/workspace';
import { PageShell } from '@/components/page-shell';
import { useLocale } from '@/hooks/use-locale';

type Activity = {
    id: number;
    title: string;
    type: string;
    starts_at: string | null;
    due_at: string | null;
    opportunity_id: number | null;
};
export function MobileAgenda({
    data,
    activities,
}: {
    data: Workspace;
    activities: Activity[];
}) {
    const { locale } = useLocale();
    const formatLocale = locale === 'es' ? 'es-ES' : 'en-GB';
    const [now] = useState(() => new Date());
    const orders: Order[] = Array.isArray(data.orders)
        ? data.orders
        : data.orders.data;
    const view = data.filters.view ?? 'week';
    const today = new Intl.DateTimeFormat('en-CA', {
        timeZone: data.timezone,
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
    }).format(new Date());
    const events = [
        ...orders.map((order) => ({
            key: `order-${order.id}`,
            title: order.title || order.number,
            subtitle: `${order.number} · ${order.installation?.site_name ?? ''}`,
            at: order.scheduled_start,
            href: `/work-orders/${order.id}`,
            kind: 'Orden de trabajo',
            status: statuses[order.status] ?? order.status,
        })),
        ...activities.map((activity) => ({
            key: `activity-${activity.id}`,
            title: activity.title,
            subtitle: activity.type,
            at: activity.starts_at ?? activity.due_at,
            href: activity.opportunity_id
                ? `/opportunities/${activity.opportunity_id}`
                : `/operations/activities?focus=${activity.id}`,
            kind: 'Actividad',
            status: '',
        })),
    ]
        .filter(
            (event) =>
                event.at &&
                (view !== 'list' ||
                    new Date(event.at).getTime() >= now.getTime()),
        )
        .sort((a, b) => new Date(a.at!).getTime() - new Date(b.at!).getTime());
    const format = (date: string, time = false) =>
        new Intl.DateTimeFormat(formatLocale, {
            timeZone: data.timezone,
            ...(time
                ? { hour: '2-digit', minute: '2-digit' }
                : { weekday: 'long', day: 'numeric', month: 'long' }),
        }).format(new Date(date));

    return (
        <PageShell className="mobile-agenda">
            <Head title="Agenda" />
            <h1>Agenda</h1>
            <nav aria-label="Vista de agenda" className="mobile-segments">
                {[
                    ['day', 'Hoy'],
                    ['week', 'Semana'],
                    ['list', 'Próximas'],
                ].map(([value, label]) => (
                    <Link
                        key={value}
                        href={`/operations/agenda?view=${value}&week=${today}`}
                        aria-current={view === value ? 'page' : undefined}
                    >
                        {label}
                    </Link>
                ))}
            </nav>
            <div className="mobile-agenda-range">
                <Link
                    href={`/operations/agenda?view=${view}&week=${data.range.previous}`}
                    aria-label="Periodo anterior"
                >
                    <ChevronLeft />
                </Link>
                <span>
                    {data.range.start} — {data.range.end}
                </span>
                <Link
                    href={`/operations/agenda?view=${view}&week=${data.range.next}`}
                    aria-label="Periodo siguiente"
                >
                    <ChevronRight />
                </Link>
            </div>
            <p className="mobile-capability-note">
                {data.timezone}
                {view === 'list'
                    ? ' · Próximas dentro del rango seleccionado'
                    : ''}
            </p>
            <ol className="mobile-timeline">
                {events.map((event, index) => (
                    <li key={event.key}>
                        {event.at &&
                            (index === 0 ||
                                format(events[index - 1].at!) !==
                                    format(event.at)) && (
                                <h2>{format(event.at)}</h2>
                            )}
                        <Link href={event.href}>
                            <time dateTime={event.at ?? undefined}>
                                {event.at ? format(event.at, true) : '—'}
                            </time>
                            <span
                                className={`mobile-timeline-dot ${event.kind === 'Actividad' ? 'activity' : ''}`}
                            />
                            <div>
                                <b>{event.title}</b>
                                <small>{event.subtitle}</small>
                                <span>
                                    {event.kind}
                                    {event.status ? ` · ${event.status}` : ''}
                                </span>
                            </div>
                            <ChevronRight />
                        </Link>
                    </li>
                ))}
            </ol>
            {!events.length && (
                <div className="mobile-empty">
                    <CalendarDays />
                    <p>No hay eventos autorizados en este rango.</p>
                </div>
            )}
            {data.truncated && (
                <p role="status">
                    Se ha alcanzado el límite de eventos. Selecciona un rango
                    menor.
                </p>
            )}
            <p className="mobile-capability-note">
                <Clock3 className="inline size-3" /> Cada evento conserva su
                origen y abre su ficha real.
            </p>
        </PageShell>
    );
}

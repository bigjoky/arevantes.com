import { Link } from '@inertiajs/react';
import type { CommercialOverview } from '@/types/commercial-dashboard';

export function CommercialActivity({
    events,
    t,
    locale,
    statuses,
}: {
    events: CommercialOverview['activity'];
    t: (key: string) => string;
    locale: string;
    statuses: Record<string, string>;
}) {
    const date = (value: string) =>
        new Intl.DateTimeFormat(locale, {
            day: '2-digit',
            month: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
        }).format(new Date(value));
    const description = (event: CommercialOverview['activity'][number]) =>
        event.type === 'Presupuesto'
            ? t(statuses[event.description] ?? event.description)
            : event.description;

    if (!events.length) {
return (
            <p className="home-empty">
                {t('No existe actividad comercial reciente en este periodo.')}
            </p>
        );
}

    return (
        <>
            <div className="commercial-activity-table home-table-wrap">
                <table className="home-table">
                    <thead>
                        <tr>
                            {[
                                'Fecha',
                                'Tipo',
                                'Descripción',
                                'Relacionado',
                                'Usuario',
                            ].map((label) => (
                                <th scope="col" key={label}>
                                    {t(label)}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {events.map((event) => (
                            <tr key={event.id}>
                                <td>
                                    <time dateTime={event.date}>
                                        {date(event.date)}
                                    </time>
                                </td>
                                <td>{t(event.type)}</td>
                                <td className="commercial-event-description">
                                    <Link href={event.href}>
                                        {description(event)}
                                    </Link>
                                </td>
                                <td>{event.reference ?? '—'}</td>
                                <td>{event.user ?? '—'}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            <div className="commercial-activity-mobile">
                {events.map((event) => (
                    <Link href={event.href} key={event.id}>
                        <strong>{description(event)}</strong>
                        <span>{event.reference ?? t(event.type)}</span>
                        <span>
                            {event.user ?? '—'} ·{' '}
                            <time dateTime={event.date}>
                                {date(event.date)}
                            </time>
                        </span>
                    </Link>
                ))}
            </div>
        </>
    );
}

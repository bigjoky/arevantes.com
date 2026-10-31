import { Link } from '@inertiajs/react';
import {
    Card,
    Frame,
    OrderCards,
    Range,
    date,
} from '@/components/operations/workspace';
import type { Workspace, Order } from '@/components/operations/workspace';

type Props = Workspace & {
    calendarUser: { id: number; name: string };
    calendarDays: {
        date: string;
        slots: { start: string; end: string }[] | null;
        holiday: string | null;
        absence: string | null;
        vacation: boolean | null;
        occupied_minutes: number | null;
        available_minutes: number | null;
        conflict: boolean;
    }[];
};
export default function IndividualCalendar(data: Props) {
    const path = `/operations/calendar/${data.calendarUser.id}`;

    return (
        <Frame
            title={`Calendario · ${data.calendarUser.name}`}
            description="Agenda individual basada en la planificación operativa."
        >
            <Range data={data} path={path} />
            <nav className="ops-tabs">
                {[
                    ['day', 'Día'],
                    ['week', 'Semana'],
                    ['month', 'Mes'],
                ].map(([view, label]) => (
                    <Link
                        key={view}
                        href={`${path}?week=${data.filters.week ?? data.range.start}&view=${view}`}
                    >
                        {label}
                    </Link>
                ))}
            </nav>
            <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                {data.calendarDays.map((day) => (
                    <Card key={day.date} title={day.date}>
                        <p>
                            {day.slots === null
                                ? 'Horario no configurado'
                                : day.slots.length === 0
                                  ? 'Sin turnos'
                                  : day.slots
                                        .map(
                                            (slot) =>
                                                `${slot.start} – ${slot.end}`,
                                        )
                                        .join(' · ')}
                        </p>
                        {day.holiday && <p>Festivo · {day.holiday}</p>}
                        {day.absence && (
                            <p>
                                {day.vacation
                                    ? day.absence === 'approved'
                                        ? 'Vacaciones autorizadas'
                                        : 'Vacaciones pendientes'
                                    : day.absence === 'approved'
                                      ? 'Ausencia autorizada'
                                      : 'Ausencia pendiente'}
                            </p>
                        )}
                        <p>
                            Ocupado:{' '}
                            {day.occupied_minutes === null
                                ? 'Sin información'
                                : `${day.occupied_minutes} min`}{' '}
                            · Disponible:{' '}
                            {day.available_minutes === null
                                ? 'Desconocido'
                                : `${day.available_minutes} min`}
                        </p>
                        {day.conflict && (
                            <p role="alert">
                                Hay trabajo asignado fuera de turno o durante
                                una ausencia o festivo.
                            </p>
                        )}
                    </Card>
                ))}
            </div>
            <Card title="Órdenes asignadas">
                <OrderCards orders={data.orders as Order[]} />
            </Card>
            <Card title="Visitas y actividades">
                {data.activities?.map((activity) => (
                    <p key={activity.id}>
                        {date(activity.starts_at ?? activity.due_at)} ·{' '}
                        {activity.title}
                    </p>
                ))}
            </Card>
        </Frame>
    );
}

import { Link } from '@inertiajs/react';
import { MobileAgenda } from '@/components/mobile/mobile-agenda';
import {
    Frame,
    Range,
    OrderCards,
    Card,
    date,
} from '@/components/operations/workspace';
import type { Workspace } from '@/components/operations/workspace';
import { useIsMobile } from '@/hooks/use-mobile';
type Activity = {
    id: number;
    title: string;
    type: string;
    starts_at: string | null;
    due_at: string | null;
    installation_id: number | null;
    opportunity_id: number | null;
};
const agendaViews = [
    ['day', 'Día'],
    ['week', 'Semana'],
    ['month', 'Mes'],
    ['list', 'Lista'],
];
export default function Agenda(data: Workspace & { activities: Activity[] }) {
    const isMobile = useIsMobile(1024);

    if (isMobile) {
        return <MobileAgenda data={data} activities={data.activities} />;
    }

    return (
        <Frame
            title="Agenda"
            description="Visión temporal de las órdenes, visitas y actividades reales."
        >
            <Range data={data} path="/operations/agenda" />
            <nav className="ops-tabs">
                {agendaViews.map(([view, label]) => (
                    <Link
                        key={view}
                        href={`/operations/agenda?view=${view}&week=${data.range.start}`}
                    >
                        {label}
                    </Link>
                ))}
            </nav>
            <div className="ops-agenda">
                <Card title="Órdenes de trabajo">
                    <OrderCards
                        orders={
                            data.orders as Exclude<
                                Workspace['orders'],
                                { data: unknown[] }
                            >
                        }
                    />
                </Card>
                <Card title="Visitas y actividades">
                    {data.activities.map((a) => (
                        <div className="ops-backlog" key={a.id}>
                            <div>
                                <b>{a.title}</b>
                                <p>
                                    {a.type} · {date(a.starts_at ?? a.due_at)}
                                </p>
                            </div>
                            <Link
                                href={
                                    a.opportunity_id
                                        ? `/opportunities/${a.opportunity_id}`
                                        : `/operations/activities?focus=${a.id}`
                                }
                            >
                                Abrir →
                            </Link>
                        </div>
                    ))}
                    {!data.activities.length && (
                        <p className="ops-empty">
                            No hay actividades en este rango.
                        </p>
                    )}
                </Card>
            </div>
            <small>Zona horaria: {data.timezone}</small>
            {data.truncated && (
                <p>
                    Se ha alcanzado el límite de eventos. Selecciona un rango
                    menor.
                </p>
            )}
        </Frame>
    );
}

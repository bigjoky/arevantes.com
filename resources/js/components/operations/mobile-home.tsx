import { Link, usePage } from '@inertiajs/react';
import {
    Card,
    Frame,
    Filters,
    OrderCards,
} from '@/components/operations/workspace';
import type { Order, Workspace } from '@/components/operations/workspace';

export function MobileOperationsHome({
    data,
}: {
    data: Workspace & {
        attention: Order[];
        upcoming: Order[];
        execution: Order[];
    };
}) {
    const { auth } = usePage<{ auth: { user: { role: string } } }>().props;

    return (
        <Frame
            title="Operaciones"
            description="Consulta tus trabajos y registra la actividad en campo."
        >
            <nav
                className="ops-mobile-shortcuts"
                aria-label="Accesos operativos"
            >
                <Link href="/work-orders?mine=1">Mis trabajos</Link>
                <Link href="/work-orders?metric=today">Trabajos de hoy</Link>
                <Link href="/operations/agenda">Agenda</Link>
                {data.canManage && (
                    <Link href="/operations/planning">Planificación</Link>
                )}
            </nav>
            {auth.user.role === 'technician' && (
                <p>Solo se muestran las órdenes autorizadas para tu usuario.</p>
            )}
            <Filters data={data} />
            <Card title="En ejecución" href="/work-orders?status=in_progress">
                <OrderCards orders={data.execution} />
            </Card>
            <Card title="Próximos trabajos" href="/work-orders?status=planned">
                <OrderCards orders={data.upcoming} />
            </Card>
            <Card title="Trabajos que requieren atención" href="/work-orders">
                <OrderCards orders={data.attention} />
            </Card>
            <p className="ops-empty">
                Los resúmenes muestran los trabajos cargados. Abre Mis trabajos
                para consultar el listado completo.
            </p>
        </Frame>
    );
}

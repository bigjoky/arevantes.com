import { Link } from '@inertiajs/react';
import { MobileOperationsHome } from '@/components/operations/mobile-home';
import {
    Card,
    Frame,
    Planning,
    OrderCards,
    metricIcons,
} from '@/components/operations/workspace';
import type { Workspace, Order } from '@/components/operations/workspace';
import { useIsMobile } from '@/hooks/use-mobile';
type Props = Workspace & {
    metrics: Record<string, number>;
    attention: Order[];
    execution: Order[];
    upcoming: Order[];
    materialRisks: {
        work_order_id: number;
        product: string;
        shortage_quantity: number;
        severity: string;
    }[];
};
export default function Dashboard(data: Props) {
    const mobile = useIsMobile(1024);

    if (mobile) {
        return <MobileOperationsHome data={data} />;
    }

    const labels = [
        'Trabajos de hoy',
        'Pendientes de planificar',
        'En ejecución',
        'Retrasados',
        'Finalizados esta semana',
    ];

    return (
        <Frame
            title="Operaciones"
            description="Planifica, coordina y supervisa la ejecución de los trabajos de AREVANTES."
        >
            <div className="ops-kpis">
                {Object.entries(data.metrics).map(([key, value], i) => {
                    const Icon = metricIcons[i];

                    return (
                        <Link
                            className="ops-card ops-kpi"
                            href={
                                key === 'pending'
                                    ? '/operations/planning'
                                    : key === 'in_progress'
                                      ? '/work-orders?status=in_progress'
                                      : `/work-orders?metric=${key}`
                            }
                            key={key}
                        >
                            <Icon />
                            <div>
                                <span>{labels[i]}</span>
                                <strong>{value}</strong>
                            </div>
                        </Link>
                    );
                })}
                <div className="ops-card ops-kpi">
                    <div>
                        <span>Trabajos planificados esta semana</span>
                        <strong>{(data.orders as Order[]).length}</strong>
                        <small>Carga por asignaciones reales</small>
                    </div>
                </div>
            </div>
            <div className="ops-dashboard-main">
                <Planning data={data} compact />
                <Card
                    title="Trabajos que requieren atención"
                    href="/work-orders"
                >
                    <OrderCards orders={data.attention} />
                </Card>
            </div>
            <div className="ops-dashboard-bottom">
                <Card title="Próximas instalaciones" href="/installations">
                    <OrderCards orders={data.upcoming} />
                </Card>
                <Card
                    title="Órdenes en ejecución"
                    href="/work-orders?status=in_progress"
                >
                    <OrderCards orders={data.execution} />
                </Card>
                <Card title="Material pendiente">
                    {data.materialRisks.map((risk, i) => (
                        <Link
                            className="ops-backlog"
                            href={`/work-orders/${risk.work_order_id}?tab=materials`}
                            key={`${risk.work_order_id}-${i}`}
                        >
                            <span>
                                {risk.product}
                                <small>
                                    {risk.shortage_quantity} unidades pendientes
                                </small>
                            </span>
                            <span>{risk.severity}</span>
                        </Link>
                    ))}
                    {!data.materialRisks.length && (
                        <p>
                            No hay riesgos de material detectados en los
                            próximos 7 días.
                        </p>
                    )}
                </Card>
            </div>
        </Frame>
    );
}

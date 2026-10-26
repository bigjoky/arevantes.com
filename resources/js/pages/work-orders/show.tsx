import { Link, usePage } from '@inertiajs/react';
import { useState } from 'react';
import {
    OrderEvidenceUpload,
    OrderTimer,
} from '@/components/operations/mobile-execution';
import {
    Frame,
    Card,
    Badge,
    Progress,
    Transition,
    ScheduleForm,
    Pagination,
    completeTask,
    date,
    kinds,
} from '@/components/operations/workspace';
import type { Workspace, Order } from '@/components/operations/workspace';
import { Button } from '@/components/ui/button';
import {
    operationsOrderUrl,
    operationsReturnUrl,
} from '@/lib/operations/navigation';
type Item = {
    id: number;
    label?: string;
    completed_at?: string | null;
    is_required?: boolean;
    original_name?: string;
    size?: number;
    event?: string;
    created_at?: string;
    product?: { name: string; sku: string };
    warehouse?: { name: string };
    quantity?: string;
    status?: string;
    started_at?: string;
    ended_at?: string;
    duration_minutes?: number;
    notes?: string;
    user?: { name: string };
};
type Props = Workspace & {
    order: Order;
    runningTimer: { id: number; started_at: string } | null;
    tab: string;
    transitions: string[];
    content: {
        data: Item[];
        total: number;
        current_page: number;
        last_page: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    } | null;
};
const tabs = [
    ['summary', 'Resumen'],
    ['tasks', 'Tareas'],
    ['technicians', 'Técnicos'],
    ['materials', 'Materiales'],
    ['documents', 'Documentos'],
    ['photos', 'Fotos'],
    ['reports', 'Partes'],
    ['activity', 'Actividad'],
];
export default function Detail(data: Props) {
    const o = data.order;
    const { url } = usePage();
    const returnTo = operationsReturnUrl(
        new URL(url, 'https://operations.invalid').searchParams.get(
            'return_to',
        ),
    );
    const orderLink = (tab?: string) => operationsOrderUrl(o.id, returnTo, tab);
    const [scheduling, setScheduling] = useState(false);
    const summary = data.tab === tabs[0][0];
    const tasks = data.tab === tabs[1][0];
    const files = [tabs[4][0], tabs[5][0]].includes(data.tab);

    return (
        <Frame
            title={o.number}
            description={`${o.title} · ${kinds[o.kind] ?? o.kind}`}
            actions={
                <div className="flex flex-wrap gap-2">
                    {data.canManage && (
                        <Button
                            variant="outline"
                            disabled={scheduling}
                            onClick={() => setScheduling(true)}
                        >
                            Reprogramar
                        </Button>
                    )}
                    <Button asChild variant="outline">
                        <Link href={`/work-orders/manage?work_order=${o.id}`}>
                            Gestionar orden
                        </Link>
                    </Button>
                </div>
            }
        >
            <Link href={returnTo}>← Volver al listado o agenda</Link>
            <Badge status={o.status} />
            <nav className="ops-tabs">
                {tabs.map(([key, label]) => (
                    <Link
                        className={data.tab === key ? 'active' : ''}
                        key={key}
                        href={orderLink(key)}
                    >
                        {label}
                    </Link>
                ))}
            </nav>
            {scheduling && (
                <ScheduleForm
                    order={o}
                    users={data.users}
                    onClose={() => setScheduling(false)}
                />
            )}{' '}
            {summary ? (
                <>
                    <div className="ops-detail-top">
                        <Card title="Información general">
                            <dl className="ops-info">
                                <dt>Referencia</dt>
                                <dd>{o.number}</dd>
                                <dt>Tipo</dt>
                                <dd>{kinds[o.kind]}</dd>
                                <dt>Proyecto</dt>
                                <dd>
                                    {o.installation.project_reference ? (
                                        <Link
                                            href={`/execution/projects/${o.installation.id}`}
                                        >
                                            {o.installation.project_reference} →
                                        </Link>
                                    ) : (
                                        '—'
                                    )}
                                </dd>
                                <dt>Instalación</dt>
                                <dd>
                                    <Link
                                        href={`/installations/${o.installation.id}`}
                                    >
                                        {o.installation.site_name ??
                                            o.installation.id}{' '}
                                        →
                                    </Link>
                                </dd>
                                <dt>Cliente</dt>
                                <dd>
                                    {o.installation.customer && (
                                        <Link
                                            href={`/people/${o.installation.customer.id}`}
                                        >
                                            {o.installation.customer
                                                .company_name ??
                                                o.installation.customer
                                                    .first_name}
                                        </Link>
                                    )}
                                </dd>
                                <dt>Inicio previsto</dt>
                                <dd>{date(o.scheduled_start)}</dd>
                                <dt>Fin previsto</dt>
                                <dd>{date(o.scheduled_end)}</dd>
                                <dt>Responsable</dt>
                                <dd>
                                    {o.assigned_user?.name ?? 'Sin asignar'}
                                </dd>
                            </dl>
                        </Card>
                        <Card title="Progreso">
                            <Progress order={o} />
                        </Card>
                        <Card title="Ubicación">
                            <p>
                                {o.installation.site_address_line_1 ??
                                    'Dirección no registrada'}
                            </p>
                            <p>{o.installation.site_city}</p>
                            <small>Ubicación de la instalación</small>
                            {o.installation.site_address_line_1 && (
                                <a
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    href={`https://www.google.com/maps/search/?api=1&query=${encodeURIComponent([o.installation.site_address_line_1, o.installation.site_city].filter(Boolean).join(', '))}`}
                                >
                                    Abrir ubicación en mapas
                                </a>
                            )}
                        </Card>
                    </div>
                    <div className="ops-dashboard-bottom">
                        <Card title="Tareas">
                            <p>
                                {o.completed_tasks_count} de{' '}
                                {o.checklist_items_count} completadas
                            </p>
                            <Link href={orderLink('tasks')}>
                                Ver checklist →
                            </Link>
                        </Card>
                        <Card title="Materiales asignados">
                            <Link href={orderLink('materials')}>
                                Ver reservas de almacén →
                            </Link>
                        </Card>
                        <Card title="Documentos">
                            <Link href={orderLink('documents')}>
                                Ver documentos de la orden →
                            </Link>
                        </Card>
                    </div>
                    <Card title="Observaciones">
                        <p className="whitespace-pre-wrap">
                            {o.completion_notes ||
                                'Sin observaciones registradas'}
                        </p>
                    </Card>
                    <Card title="Instrucciones">
                        <p className="whitespace-pre-wrap">
                            {o.instructions ?? 'Sin instrucciones registradas'}
                        </p>
                    </Card>
                    {!scheduling && !!data.transitions.length && (
                        <Card title="Estado de la orden">
                            <Transition
                                order={o}
                                transitions={data.transitions}
                            />
                        </Card>
                    )}
                </>
            ) : data.tab === tabs[2][0] ? (
                <Card title="Técnico asignado">
                    <p>{o.assigned_user?.name ?? 'Sin asignar'}</p>
                    <p>
                        {date(o.scheduled_start)} – {date(o.scheduled_end)}
                    </p>
                </Card>
            ) : (
                <Card
                    title={
                        tabs.find(([key]) => key === data.tab)?.[1] ?? data.tab
                    }
                >
                    {files && <OrderEvidenceUpload orderId={o.id} />}
                    {data.tab === 'reports' && (
                        <OrderTimer
                            orderId={o.id}
                            running={data.runningTimer}
                            canStart={
                                o.status === 'in_progress' ||
                                data.transitions.includes('in_progress')
                            }
                        />
                    )}
                    {data.content?.data.map((item) => (
                        <div className="ops-backlog" key={item.id}>
                            {tasks ? (
                                <label className="flex items-center gap-3">
                                    <input
                                        type="checkbox"
                                        checked={!!item.completed_at}
                                        onChange={(e) =>
                                            completeTask(
                                                o.id,
                                                item.id,
                                                e.target.checked,
                                            )
                                        }
                                    />
                                    <span>
                                        {item.label}
                                        {item.is_required && (
                                            <small>Obligatoria</small>
                                        )}
                                    </span>
                                </label>
                            ) : files ? (
                                <>
                                    <span>
                                        {item.original_name}
                                        <small>
                                            {Math.round(
                                                (item.size ?? 0) / 1024,
                                            )}{' '}
                                            KB · {date(item.created_at ?? null)}
                                        </small>
                                    </span>
                                    <a
                                        href={`/work-orders/${o.id}/attachments/${item.id}`}
                                    >
                                        Descargar
                                    </a>
                                </>
                            ) : item.product ? (
                                <>
                                    <span>
                                        {item.product.name}
                                        <small>{item.warehouse?.name}</small>
                                    </span>
                                    <span>
                                        {item.quantity} · {item.status}
                                    </span>
                                </>
                            ) : item.event ? (
                                <>
                                    <b>{item.event}</b>
                                    <span>{date(item.created_at ?? null)}</span>
                                </>
                            ) : (
                                <>
                                    <div>
                                        <b>{item.user?.name}</b>
                                        <p>{item.notes}</p>
                                    </div>
                                    <span>
                                        {date(item.started_at ?? null)} ·{' '}
                                        {item.duration_minutes ?? '—'} min
                                    </span>
                                </>
                            )}
                        </div>
                    ))}
                    {!data.content?.total && (
                        <p className="ops-empty">
                            No hay registros en esta sección.
                        </p>
                    )}
                    {data.content && <Pagination page={data.content} />}
                    <Button asChild variant="outline">
                        <Link href={`/work-orders/manage?work_order=${o.id}`}>
                            Gestionar contenido
                        </Link>
                    </Button>
                </Card>
            )}
        </Frame>
    );
}

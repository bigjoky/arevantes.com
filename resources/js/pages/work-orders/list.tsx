import { Link } from '@inertiajs/react';
import {
    Frame,
    Filters,
    NewOrder,
    Badge,
    OrderCards,
    Pagination,
    kinds,
    statuses,
    date,
} from '@/components/operations/workspace';
import type { Workspace } from '@/components/operations/workspace';
export default function List(
    data: Workspace & { counts: Record<string, number> },
) {
    const page = data.orders as Exclude<Workspace['orders'], Array<unknown>>;

    return (
        <Frame
            title="Órdenes de trabajo"
            description="Gestiona y realiza el seguimiento de todos los trabajos de instalación y mantenimiento."
            actions={<NewOrder canManage={data.canManage} />}
        >
            <Filters data={data} />
            <nav className="ops-tabs">
                <Link
                    className={!data.filters.status ? 'active' : ''}
                    href="/work-orders"
                >
                    Todas
                </Link>
                {data.statuses.map((s) => (
                    <Link key={s} href={`/work-orders?status=${s}`}>
                        {statuses[s]} <b>{data.counts[s] ?? 0}</b>
                    </Link>
                ))}
            </nav>
            <section className="ops-card">
                <div className="ops-table-desktop">
                    <table>
                        <thead>
                            <tr>
                                {[
                                    'Referencia',
                                    'Tipo',
                                    'Instalación / Proyecto',
                                    'Cliente',
                                    'Técnico',
                                    'Estado',
                                    'Inicio',
                                    'Fin previsto',
                                ].map((c) => (
                                    <th key={c}>{c}</th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {page.data.map((o) => (
                                <tr key={o.id}>
                                    <td>
                                        <Link href={`/work-orders/${o.id}`}>
                                            {o.number}
                                        </Link>
                                        <small>{o.title}</small>
                                    </td>
                                    <td>{kinds[o.kind] ?? o.kind}</td>
                                    <td>
                                        {o.installation.site_name}
                                        <small>
                                            {o.installation.project_reference}
                                        </small>
                                    </td>
                                    <td>
                                        {o.installation.customer
                                            ?.company_name ??
                                            o.installation.customer
                                                ?.first_name ??
                                            '—'}
                                    </td>
                                    <td>
                                        {o.assigned_user?.name ?? 'Sin asignar'}
                                    </td>
                                    <td>
                                        <Badge status={o.status} />
                                    </td>
                                    <td>{date(o.scheduled_start)}</td>
                                    <td>{date(o.scheduled_end)}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                <div className="ops-list-mobile">
                    {page.data.length > 0 && <OrderCards orders={page.data} />}
                </div>
                {!page.total && (
                    <p className="ops-empty">
                        No hay órdenes de trabajo para estos filtros.
                    </p>
                )}
                <Pagination page={page} />
            </section>
        </Frame>
    );
}

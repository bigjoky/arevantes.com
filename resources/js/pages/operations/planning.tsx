import { Link } from '@inertiajs/react';
import {
    Frame,
    Planning,
    Range,
    NewOrder,
    kinds,
    statuses,
} from '@/components/operations/workspace';
import type { Workspace } from '@/components/operations/workspace';
export default function PlanningPage(data: Workspace) {
    return (
        <Frame
            title="Planificación"
            description="Asigna recursos, organiza los trabajos y controla la carga de los técnicos."
            actions={<NewOrder canManage={data.canManage} />}
        >
            <nav className="ops-range" aria-label="Vista de planificación">
                {[
                    ['day', 'Día'],
                    ['week', 'Semana'],
                    ['month', 'Mes'],
                ].map(([view, label]) => (
                    <a
                        key={view}
                        href={`?${new URLSearchParams({ ...data.filters, view })}`}
                    >
                        {label}
                    </a>
                ))}
            </nav>
            <nav className="ops-range">
                <Link href="/operations/vehicles">Vehículos</Link>
                {data.users.map((user) => (
                    <Link
                        key={user.id}
                        href={`/operations/calendar/${user.id}`}
                    >
                        Calendario · {user.name}
                    </Link>
                ))}
            </nav>
            <Range data={data} path="/operations/planning" />
            <form className="ops-filters" method="get">
                <input type="hidden" name="week" value={data.range.start} />
                <label>
                    Técnico
                    <select
                        name="owner"
                        defaultValue={data.filters.owner ?? ''}
                    >
                        <option value="">Todos</option>
                        {data.users.map((user) => (
                            <option key={user.id} value={user.id}>
                                {user.name}
                            </option>
                        ))}
                    </select>
                </label>
                <input
                    type="hidden"
                    name="view"
                    value={data.filters.view ?? 'week'}
                />
                <label>
                    Tipo
                    <select name="kind" defaultValue={data.filters.kind ?? ''}>
                        <option value="">Todos</option>
                        {data.kinds.map((kind) => (
                            <option key={kind} value={kind}>
                                {kinds[kind] ?? kind}
                            </option>
                        ))}
                    </select>
                </label>
                <label>
                    Estado
                    <select
                        name="status"
                        defaultValue={data.filters.status ?? ''}
                    >
                        <option value="">Activas</option>
                        {data.statuses.map((status) => (
                            <option key={status} value={status}>
                                {statuses[status] ?? status}
                            </option>
                        ))}
                    </select>
                </label>
                <label>
                    Vehículo
                    <select
                        name="vehicle"
                        defaultValue={data.filters.vehicle ?? ''}
                    >
                        <option value="">Todos</option>
                        {data.vehicles.map((vehicle) => (
                            <option key={vehicle.id} value={vehicle.id}>
                                {vehicle.registration} · {vehicle.name}
                            </option>
                        ))}
                    </select>
                </label>
                <label>
                    Proyecto
                    <select
                        name="installation"
                        defaultValue={data.filters.installation ?? ''}
                    >
                        <option value="">Todos</option>
                        {data.installations?.map((installation) => (
                            <option
                                key={installation.id}
                                value={installation.id}
                            >
                                {installation.project_reference ??
                                    installation.site_name ??
                                    installation.id}
                            </option>
                        ))}
                    </select>
                </label>
                <button type="submit">Filtrar</button>
            </form>
            <Planning data={data} />
        </Frame>
    );
}

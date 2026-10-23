import {
    Frame,
    Planning,
    Range,
    NewOrder,
} from '@/components/operations/workspace';
import type { Workspace } from '@/components/operations/workspace';
export default function PlanningPage(data: Workspace) {
    return (
        <Frame
            title="Planificación"
            description="Asigna recursos, organiza los trabajos y controla la carga de los técnicos."
            actions={<NewOrder canManage={data.canManage} />}
        >
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
                <button type="submit">Filtrar</button>
            </form>
            <Planning data={data} />
        </Frame>
    );
}

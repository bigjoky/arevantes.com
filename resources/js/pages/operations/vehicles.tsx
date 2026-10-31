import { Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { Card, Frame, date } from '@/components/operations/workspace';
import { Button } from '@/components/ui/button';

type Vehicle = {
    id: number;
    registration: string;
    name: string;
    make: string | null;
    model: string | null;
    type: string | null;
    status: string;
    current_state: string;
    base_location: string | null;
    notes: string | null;
    blocks: {
        id: number;
        starts_at_utc: string;
        ends_at_utc: string;
        reason: string;
        cancelled_at_utc: string | null;
        cancellation_reason: string | null;
    }[];
};
type Order = {
    id: number;
    number: string;
    title: string;
    scheduled_start: string | null;
    scheduled_end: string | null;
    vehicle: Vehicle;
    assigned_user: { name: string } | null;
};
type Visit = {
    id: number;
    title: string;
    starts_at: string | null;
    due_at: string | null;
    vehicle: Vehicle;
    assigned_user: { name: string } | null;
};
type Props = {
    vehicles: Vehicle[];
    orders: Order[];
    visits: Visit[];
    canManage: boolean;
    timezone: string;
};
const formStates: Record<string, string> = {
    available: 'Disponible',
    maintenance: 'Mantenimiento',
    out_of_service: 'Fuera de servicio',
};
const states: Record<string, string> = {
    ...formStates,
    assigned: 'Asignado',
    blocked: 'Bloqueado',
};

function VehicleForm({ vehicle }: { vehicle?: Vehicle }) {
    const form = useForm({
        registration: vehicle?.registration ?? '',
        registration_key: '',
        confirm_conflicts: false,
        name: vehicle?.name ?? '',
        make: vehicle?.make ?? '',
        model: vehicle?.model ?? '',
        type: vehicle?.type ?? '',
        status: vehicle?.status ?? 'available',
        base_location: vehicle?.base_location ?? '',
        notes: vehicle?.notes ?? '',
    });
    const fields = [
        ['registration', 'Matrícula'],
        ['name', 'Nombre interno'],
        ['make', 'Marca'],
        ['model', 'Modelo'],
        ['type', 'Tipo'],
        ['base_location', 'Base habitual'],
        ['notes', 'Observaciones'],
    ] as const;

    return (
        <form
            className="ops-filters"
            onSubmit={(event) => {
                event.preventDefault();

                if (form.processing) {
                    return;
                }

                if (vehicle) {
                    form.put(`/operations/vehicles/${vehicle.id}`, {
                        preserveScroll: true,
                    });
                } else {
                    form.post('/operations/vehicles', {
                        preserveScroll: true,
                        onSuccess: () => form.reset(),
                    });
                }
            }}
        >
            {fields.map(([key, label]) => (
                <label key={key}>
                    {label}
                    <input
                        required={key === 'registration' || key === 'name'}
                        value={form.data[key]}
                        onChange={(event) =>
                            form.setData(key, event.target.value)
                        }
                    />
                    {form.errors[key] && (
                        <span role="alert">{form.errors[key]}</span>
                    )}
                </label>
            ))}
            <label>
                Estado
                <select
                    value={form.data.status}
                    onChange={(event) =>
                        form.setData('status', event.target.value)
                    }
                >
                    {Object.entries(formStates).map(([key, label]) => (
                        <option key={key} value={key}>
                            {label}
                        </option>
                    ))}
                </select>
            </label>
            {vehicle && form.data.status !== 'available' && (
                <label>
                    <input
                        type="checkbox"
                        checked={form.data.confirm_conflicts}
                        onChange={(event) =>
                            form.setData(
                                'confirm_conflicts',
                                event.target.checked,
                            )
                        }
                    />{' '}
                    He revisado las asignaciones afectadas
                </label>
            )}
            {form.errors.confirm_conflicts && (
                <p role="alert">{form.errors.confirm_conflicts}</p>
            )}
            {form.errors.registration_key && (
                <p role="alert">{form.errors.registration_key}</p>
            )}
            <Button disabled={form.processing}>
                {vehicle ? 'Guardar vehículo' : 'Crear vehículo'}
            </Button>
        </form>
    );
}

function BlockForm({ vehicle }: { vehicle: Vehicle }) {
    const form = useForm({ starts_at: '', ends_at: '', reason: '' });

    return (
        <form
            className="ops-filters"
            onSubmit={(event) => {
                event.preventDefault();

                if (!form.processing) {
                    form.post(`/operations/vehicles/${vehicle.id}/blocks`, {
                        preserveScroll: true,
                        onSuccess: () => form.reset(),
                    });
                }
            }}
        >
            <label>
                Inicio del bloqueo
                <input
                    type="datetime-local"
                    required
                    value={form.data.starts_at}
                    onChange={(event) =>
                        form.setData('starts_at', event.target.value)
                    }
                />
            </label>
            <label>
                Fin del bloqueo
                <input
                    type="datetime-local"
                    required
                    value={form.data.ends_at}
                    onChange={(event) =>
                        form.setData('ends_at', event.target.value)
                    }
                />
            </label>
            <label>
                Motivo del bloqueo
                <input
                    required
                    value={form.data.reason}
                    onChange={(event) =>
                        form.setData('reason', event.target.value)
                    }
                />
            </label>
            {Object.values(form.errors).map((error) => (
                <p role="alert" key={error}>
                    {error}
                </p>
            ))}
            <Button disabled={form.processing}>Registrar bloqueo</Button>
        </form>
    );
}

function CancelBlock({
    vehicleId,
    blockId,
}: {
    vehicleId: number;
    blockId: number;
}) {
    const form = useForm({ reason: '' });

    return (
        <form
            className="ops-filters"
            onSubmit={(event) => {
                event.preventDefault();

                if (!form.processing) {
                    form.patch(
                        `/operations/vehicles/${vehicleId}/blocks/${blockId}/cancel`,
                        { preserveScroll: true },
                    );
                }
            }}
        >
            <label>
                Motivo de anulación
                <input
                    required
                    value={form.data.reason}
                    onChange={(event) =>
                        form.setData('reason', event.target.value)
                    }
                />
            </label>
            {form.errors.reason && <p role="alert">{form.errors.reason}</p>}
            <Button disabled={form.processing}>Anular bloqueo</Button>
        </form>
    );
}

function VisitHistory({ id }: { id: number }) {
    const [rows, setRows] = useState<
        | {
              id: number;
              reason: string;
              actor: { name: string } | null;
              previous_vehicle: { registration: string } | null;
              next_vehicle: { registration: string } | null;
              previous_assignee: { name: string } | null;
              next_assignee: { name: string } | null;
          }[]
        | null
    >(null);
    const [error, setError] = useState('');

    return (
        <div>
            <Button
                type="button"
                variant="outline"
                onClick={async () => {
                    try {
                        const response = await fetch(
                            `/operations/activities/${id}/vehicle-history`,
                            { headers: { Accept: 'application/json' } },
                        );

                        if (!response.ok) {
                            throw new Error(
                                'No se pudo consultar el historial de vehículos.',
                            );
                        }

                        setRows((await response.json()).history);
                    } catch (e) {
                        setError(
                            e instanceof Error
                                ? e.message
                                : 'Error de disponibilidad',
                        );
                    }
                }}
            >
                Ver historial de vehículos
            </Button>
            {error && <p role="alert">{error}</p>}
            {rows &&
                (rows.length ? (
                    rows.map((row) => (
                        <p key={row.id}>
                            {row.actor?.name ?? '—'} · {row.reason} ·{' '}
                            {row.previous_vehicle?.registration ?? '—'} →{' '}
                            {row.next_vehicle?.registration ?? '—'} ·{' '}
                            {row.previous_assignee?.name ?? '—'} →{' '}
                            {row.next_assignee?.name ?? '—'}
                        </p>
                    ))
                ) : (
                    <p>Sin cambios de vehículo</p>
                ))}
        </div>
    );
}

function VehicleHistory({ id }: { id: number }) {
    const [history, setHistory] = useState<{
        rows: {
            id: number;
            reason: string;
            created_at: string;
            actor: { name: string } | null;
            work_order: { id: number; number: string } | null;
            activity: { id: number; title: string } | null;
            old_start_utc: string | null;
            new_start_utc: string | null;
            previous_vehicle: { registration: string } | null;
            next_vehicle: { registration: string } | null;
            previous_assignee: { name: string } | null;
            next_assignee: { name: string } | null;
        }[];
        truncated: boolean;
    } | null>(null);
    const [error, setError] = useState('');

    return (
        <div>
            <Button
                type="button"
                variant="outline"
                onClick={async () => {
                    try {
                        const response = await fetch(
                            `/operations/vehicles/${id}/history`,
                            { headers: { Accept: 'application/json' } },
                        );

                        if (!response.ok) {
throw new Error(
                                'No se pudo consultar el historial de vehículos.',
                            );
}

                        const result = await response.json();
                        setHistory({
                            rows: result.history,
                            truncated: result.truncated,
                        });
                    } catch (e) {
                        setError(
                            e instanceof Error
                                ? e.message
                                : 'Error de disponibilidad',
                        );
                    }
                }}
            >
                Ver historial de vehículos
            </Button>
            {error && <p role="alert">{error}</p>}
            {history &&
                (history.rows.length ? (
                    history.rows.map((row) => (
                        <p key={row.id}>
                            {date(row.created_at)} · {row.actor?.name ?? '—'} ·{' '}
                            {row.work_order?.number ??
                                row.activity?.title ??
                                '—'}{' '}
                            · {row.reason} ·{' '}
                            {row.previous_vehicle?.registration ?? '—'} →{' '}
                            {row.next_vehicle?.registration ?? '—'} ·{' '}
                            {row.previous_assignee?.name ?? '—'} →{' '}
                            {row.next_assignee?.name ?? '—'} ·{' '}
                            {date(row.old_start_utc)} →{' '}
                            {date(row.new_start_utc)}
                        </p>
                    ))
                ) : (
                    <p>Sin cambios de vehículo</p>
                ))}
            {history?.truncated && (
                <p>Se muestran las 100 revisiones más recientes.</p>
            )}
        </div>
    );
}

export default function Vehicles({
    vehicles,
    orders,
    visits,
    canManage,
    timezone,
}: Props) {
    return (
        <Frame
            title="Vehículos operativos"
            description="Consulta las asignaciones y los bloqueos de los vehículos."
        >
            <Link href="/operations/planning">Volver a planificación</Link>
            {canManage && (
                <Card title="Crear vehículo">
                    <VehicleForm />
                </Card>
            )}
            {vehicles.length === 0 && <p>No hay vehículos registrados.</p>}
            {vehicles.map((vehicle) => (
                <Card
                    key={vehicle.id}
                    title={`${vehicle.registration} · ${vehicle.name}`}
                >
                    <p>
                        {states[vehicle.current_state] ?? vehicle.current_state}{' '}
                        · {vehicle.make || '—'} {vehicle.model || ''} ·{' '}
                        {vehicle.base_location || '—'}
                    </p>
                    {orders
                        .filter((order) => order.vehicle.id === vehicle.id)
                        .map((order) => (
                            <p key={order.id}>
                                <Link href={`/work-orders/${order.id}`}>
                                    {order.number} · {order.title}
                                </Link>{' '}
                                · {date(order.scheduled_start)} –{' '}
                                {date(order.scheduled_end)} ·{' '}
                                {order.assigned_user?.name ?? 'Sin técnico'}
                            </p>
                        ))}
                    {visits
                        .filter((visit) => visit.vehicle.id === vehicle.id)
                        .map((visit) => (
                            <div key={visit.id}>
                                <p>
                                    <Link
                                        href={`/operations/activities?focus=${visit.id}`}
                                    >
                                        {visit.title}
                                    </Link>{' '}
                                    · {date(visit.starts_at)} –{' '}
                                    {date(visit.due_at)} ·{' '}
                                    {visit.assigned_user?.name ?? 'Sin técnico'}
                                </p>
                                <VisitHistory id={visit.id} />
                            </div>
                        ))}
                    <VehicleHistory id={vehicle.id} />
                    <Link href={`/operations/planning?vehicle=${vehicle.id}`}>
                        Ver asignaciones en planificación
                    </Link>
                    <p>Documentación del vehículo: no disponible.</p>
                    {canManage && (
                        <>
                            <details>
                                <summary>Editar vehículo</summary>
                                <VehicleForm vehicle={vehicle} />
                            </details>
                            <details>
                                <summary>
                                    Programar bloqueo o mantenimiento
                                </summary>
                                <BlockForm vehicle={vehicle} />
                            </details>
                            {vehicle.blocks.map((block) => (
                                <div key={block.id}>
                                    <p>
                                        {date(block.starts_at_utc)} –{' '}
                                        {date(block.ends_at_utc)} ·{' '}
                                        {block.reason}{' '}
                                        {block.cancelled_at_utc
                                            ? '· Anulado'
                                            : ''}
                                    </p>
                                    {!block.cancelled_at_utc && (
                                        <CancelBlock
                                            vehicleId={vehicle.id}
                                            blockId={block.id}
                                        />
                                    )}
                                </div>
                            ))}
                        </>
                    )}
                </Card>
            ))}
            <small>Zona horaria: {timezone}</small>
        </Frame>
    );
}

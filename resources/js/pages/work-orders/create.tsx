import { Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { Frame, Card, kinds } from '@/components/operations/workspace';
import type { Workspace } from '@/components/operations/workspace';
import { Button } from '@/components/ui/button';
import { useOperationsFormProtection } from '@/hooks/use-operations-form-protection';
type Props = Workspace & {
    installations: {
        id: number;
        project_reference: string | null;
        site_name: string | null;
        service_type: string;
    }[];
    selectedInstallation: number | null;
    selectedKind?: string;
    templates: {
        id: number;
        name: string;
        kind: string;
        service_type: string | null;
    }[];
};
export default function Create(data: Props) {
    const form = useForm({
        installation_id: String(data.selectedInstallation ?? ''),
        assigned_user_id: '',
        vehicle_id: '',
        vehicle_required: false,
        title: '',
        kind: data.selectedKind || 'installation',
        work_order_template_id: '',
        scheduled_start: '',
        scheduled_end: '',
        instructions: '',
    });

    const [vehiclePreview, setVehiclePreview] = useState<{
        signature: string;
        assignee: { status: string; issues: string[] } | null;
        vehicles: {
            id: number;
            registration: string;
            name: string;
            status: string;
            issues: string[];
        }[];
    } | null>(null);
    const [vehicleError, setVehicleError] = useState('');
    const signature = JSON.stringify([
        form.data.scheduled_start,
        form.data.scheduled_end,
        form.data.assigned_user_id,
        form.data.installation_id,
        form.data.kind,
        form.data.work_order_template_id,
    ]);
    const vehicleReady = !form.data.vehicle_id
        ? !form.data.vehicle_required
        : vehiclePreview?.signature === signature &&
          vehiclePreview.vehicles.find(
              (item) => String(item.id) === form.data.vehicle_id,
          )?.status === 'available' &&
          (!form.data.assigned_user_id ||
              vehiclePreview.assignee?.status === 'available');
    const protection = useOperationsFormProtection(() => form);

    return (
        <Frame
            title="Nueva orden de trabajo"
            description="Crea un trabajo vinculado a una instalación real."
        >
            {protection.confirmation}
            <Link href="/work-orders">← Volver a órdenes de trabajo</Link>
            <Card title="Información de la orden">
                <form method="get" className="ops-filters">
                    <label>
                        Buscar instalación
                        <input
                            name="q"
                            placeholder="Referencia o nombre de instalación"
                        />
                    </label>
                    <Button variant="outline">Buscar</Button>
                </form>
                <form
                    className="ops-schedule-form"
                    onSubmit={(e) => {
                        e.preventDefault();

                        if (!vehicleReady || !protection.beginSubmit()) {
                            return;
                        }

                        form.transform((d) => ({
                            ...d,
                            scheduled_start: d.scheduled_start,
                            scheduled_end: d.scheduled_end,
                        }));
                        form.post('/work-orders', {
                            onSuccess: () => protection.saved(),
                            onFinish: protection.finishSubmit,
                        });
                    }}
                >
                    <label>
                        Instalación
                        <select
                            required
                            value={form.data.installation_id}
                            onChange={(e) =>
                                form.setData({
                                    ...form.data,
                                    installation_id: e.target.value,
                                    work_order_template_id: '',
                                })
                            }
                        >
                            <option value="">Seleccionar</option>
                            {data.installations.map((i) => (
                                <option key={i.id} value={i.id}>
                                    {i.project_reference ?? i.site_name ?? i.id}
                                </option>
                            ))}
                        </select>
                    </label>
                    <label>
                        Técnico
                        <select
                            value={form.data.assigned_user_id}
                            onChange={(e) =>
                                form.setData('assigned_user_id', e.target.value)
                            }
                        >
                            <option value="">Sin asignar</option>
                            {data.users.map((u) => (
                                <option key={u.id} value={u.id}>
                                    {u.name}
                                </option>
                            ))}
                        </select>
                    </label>
                    <label>
                        Vehículo
                        <select
                            value={form.data.vehicle_id}
                            onChange={(event) =>
                                form.setData('vehicle_id', event.target.value)
                            }
                        >
                            <option value="">Sin vehículo</option>
                            {data.vehicles.map((vehicle) => (
                                <option key={vehicle.id} value={vehicle.id}>
                                    {vehicle.registration} · {vehicle.name}
                                </option>
                            ))}
                        </select>
                    </label>
                    <label>
                        <input
                            type="checkbox"
                            checked={form.data.vehicle_required}
                            onChange={(event) =>
                                form.setData(
                                    'vehicle_required',
                                    event.target.checked,
                                )
                            }
                        />{' '}
                        Vehículo necesario para esta intervención
                    </label>
                    <Button
                        type="button"
                        disabled={
                            !form.data.scheduled_start ||
                            !form.data.scheduled_end
                        }
                        onClick={async () => {
                            setVehicleError('');

                            try {
                                const params = new URLSearchParams({
                                    starts_at: form.data.scheduled_start,
                                    ends_at: form.data.scheduled_end,
                                });

                                if (form.data.assigned_user_id) {
                                    params.set(
                                        'assignee_id',
                                        form.data.assigned_user_id,
                                    );
                                }

                                if (form.data.installation_id) {
                                    params.set(
                                        'installation_id',
                                        form.data.installation_id,
                                    );
                                }

                                if (form.data.kind) {
                                    params.set('kind', form.data.kind);
                                }

                                if (form.data.work_order_template_id) {
                                    params.set(
                                        'work_order_template_id',
                                        form.data.work_order_template_id,
                                    );
                                }

                                const response = await fetch(
                                    `/operations/vehicles/availability?${params}`,
                                    { headers: { Accept: 'application/json' } },
                                );

                                if (!response.ok) {
                                    throw new Error(
                                        'No se pudo comprobar la disponibilidad del vehículo.',
                                    );
                                }

                                const result = await response.json();
                                setVehiclePreview({
                                    signature,
                                    vehicles: result.vehicles,
                                    assignee: result.assignee,
                                });
                            } catch (error) {
                                setVehicleError(
                                    error instanceof Error
                                        ? error.message
                                        : 'Error de disponibilidad',
                                );
                            }
                        }}
                    >
                        Consultar disponibilidad de vehículos
                    </Button>
                    {vehicleError && <p role="alert">{vehicleError}</p>}
                    {vehiclePreview?.signature === signature && (
                        <div aria-live="polite">
                            {vehiclePreview.assignee && (
                                <p>
                                    Técnico ·{' '}
                                    {vehiclePreview.assignee.status ===
                                    'available'
                                        ? 'Disponible'
                                        : vehiclePreview.assignee.status ===
                                            'unknown'
                                          ? 'Disponibilidad desconocida'
                                          : 'No disponible'}{' '}
                                    {vehiclePreview.assignee.issues.join(' ')}
                                </p>
                            )}
                            {vehiclePreview.vehicles.map((vehicle) => (
                                <p key={vehicle.id}>
                                    {vehicle.registration} · {vehicle.name} ·{' '}
                                    {vehicle.status === 'available'
                                        ? 'Disponible'
                                        : vehicle.status === 'unknown'
                                          ? 'Disponibilidad desconocida'
                                          : 'No disponible'}{' '}
                                    {vehicle.issues.join(' ')}
                                </p>
                            ))}
                        </div>
                    )}
                    <label>
                        Título
                        <input
                            required
                            value={form.data.title}
                            onChange={(e) =>
                                form.setData('title', e.target.value)
                            }
                        />
                    </label>
                    <label>
                        Tipo
                        <select
                            value={form.data.kind}
                            onChange={(e) =>
                                form.setData({
                                    ...form.data,
                                    kind: e.target.value,
                                    work_order_template_id: '',
                                })
                            }
                        >
                            {data.kinds.map((k) => (
                                <option key={k} value={k}>
                                    {kinds[k]}
                                </option>
                            ))}
                        </select>
                    </label>
                    <label>
                        Plantilla
                        <select
                            value={form.data.work_order_template_id}
                            onChange={(e) =>
                                form.setData(
                                    'work_order_template_id',
                                    e.target.value,
                                )
                            }
                        >
                            <option value="">Plantilla del servicio</option>
                            {data.templates
                                .filter(
                                    (t) =>
                                        t.kind === form.data.kind &&
                                        (!t.service_type ||
                                            t.service_type ===
                                                data.installations.find(
                                                    (i) =>
                                                        String(i.id) ===
                                                        form.data
                                                            .installation_id,
                                                )?.service_type),
                                )
                                .map((t) => (
                                    <option key={t.id} value={t.id}>
                                        {t.name}
                                    </option>
                                ))}
                        </select>
                    </label>
                    <label>
                        Inicio previsto
                        <input
                            type="datetime-local"
                            value={form.data.scheduled_start}
                            onChange={(e) =>
                                form.setData('scheduled_start', e.target.value)
                            }
                        />
                    </label>
                    <label>
                        Fin previsto
                        <input
                            type="datetime-local"
                            value={form.data.scheduled_end}
                            onChange={(e) =>
                                form.setData('scheduled_end', e.target.value)
                            }
                        />
                    </label>
                    <label>
                        Instrucciones
                        <textarea
                            value={form.data.instructions}
                            onChange={(e) =>
                                form.setData('instructions', e.target.value)
                            }
                        />
                    </label>
                    {Object.values(form.errors).map((error) => (
                        <p key={error} role="alert">
                            {error}
                        </p>
                    ))}
                    <Button disabled={form.processing || !vehicleReady}>
                        Crear orden de trabajo
                    </Button>
                </form>
                {data.installations.length === 100 && (
                    <p>
                        Se muestran las últimas 100 instalaciones. Busca para
                        localizar otra.
                    </p>
                )}
            </Card>
        </Frame>
    );
}

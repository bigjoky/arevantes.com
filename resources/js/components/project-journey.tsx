import { Link, useForm } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';

export type JourneyBlock = {
    key: string;
    title: string;
    state: string;
    detail: string;
    owner: string | null;
    href: string | null;
    action: string | null;
};
export type Journey = {
    materials?: {
        description: string;
        planned: number;
        consumed: number;
        reserved: number;
        available: number;
        ordered: number | null;
        received: number | null;
    }[];
    blocks: JourneyBlock[];
    next: JourneyBlock | null;
    closure: {
        closed_at: string | null;
        can_manage: boolean;
        installation_id: number;
        technical_required: boolean | null;
        revision: number;
        updated_at: string;
        delivery_confirmed_at: string | null;
        tasks: {
            id: number;
            title: string;
            status: string;
            required_for_closure: boolean;
        }[];
    } | null;
};
const labels: Record<string, string> = {
    complete: 'Comprobado',
    pending: 'Pendiente',
    blocked: 'Requiere atención',
    unrecorded: 'Sin registrar',
    unavailable: 'No disponible',
    not_required: 'No necesario',
};
export default function ProjectJourney({ journey }: { journey?: Journey }) {
    const form = useForm({
        action: journey?.closure?.closed_at ? 'reopen' : 'close',
        reason: '',
        economic_exception: false,
        expected_updated_at: '',
        required_activity_ids:
            journey?.closure?.tasks
                .filter((t) => t.required_for_closure)
                .map((t) => t.id) || [],
    });

    if (!journey) {
        return null;
    }

    return (
        <section
            className="my-5 grid min-w-0 grid-cols-1 gap-4 rounded-xl border p-4 sm:p-5"
            aria-label="Recorrido del expediente"
        >
            <div>
                <h2 className="text-lg font-semibold">
                    Recorrido del expediente
                </h2>
                <p className="text-sm text-muted-foreground">
                    Aceptación, cobro, preparación, ejecución y cierre son
                    hechos distintos.
                </p>
            </div>
            {journey.next && (
                <div className="rounded-lg bg-muted p-3 text-sm">
                    <strong>Siguiente paso: {journey.next.title}</strong>
                    <p>{journey.next.detail}</p>
                    {journey.next.href && (
                        <Link
                            className="mt-2 inline-block underline"
                            href={journey.next.href}
                        >
                            {journey.next.action || 'Resolver pendiente'}
                        </Link>
                    )}
                </div>
            )}
            <div className="grid min-w-0 gap-3 md:grid-cols-2 xl:grid-cols-3">
                {journey.blocks.map((block) => (
                    <article
                        key={block.key}
                        className="min-w-0 rounded-lg border p-3"
                    >
                        <div className="flex flex-wrap items-start justify-between gap-2">
                            <h3 className="font-medium">{block.title}</h3>
                            <span
                                className={`rounded px-2 py-1 text-xs ${block.state === 'blocked' ? 'bg-amber-100 text-amber-900' : 'bg-muted text-muted-foreground'}`}
                            >
                                {labels[block.state] || block.state}
                            </span>
                        </div>
                        <p className="mt-2 text-sm break-words text-muted-foreground">
                            {block.detail}
                        </p>
                        <p className="mt-2 text-xs">
                            Responsable: {block.owner || 'Sin asignar'}
                        </p>
                        {block.href && (
                            <Link
                                className="mt-2 inline-block text-sm underline"
                                href={block.href}
                            >
                                {block.action || 'Consultar'}
                            </Link>
                        )}
                    </article>
                ))}
            </div>
            {!!journey.materials?.length && (
                <details className="min-w-0 rounded-lg border p-3">
                    <summary className="cursor-pointer font-medium">
                        Ver material necesario, pedido, recibido y consumido
                    </summary>
                    <div className="mt-3 overflow-x-auto">
                        <table className="w-full min-w-[42rem] text-left text-sm">
                            <thead>
                                <tr>
                                    {[
                                        'Material',
                                        'Necesario',
                                        'Material pedido',
                                        'Recibido neto',
                                        'Reservado',
                                        'Consumido',
                                        'Disponible libre',
                                    ].map((h) => (
                                        <th className="p-2" key={h}>
                                            {h}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {journey.materials.map((row, index) => (
                                    <tr
                                        className="border-t"
                                        key={`${row.description}-${index}`}
                                    >
                                        <td className="p-2">
                                            {row.description}
                                        </td>
                                        {[
                                            row.planned,
                                            row.ordered,
                                            row.received,
                                            row.reserved,
                                            row.consumed,
                                            row.available,
                                        ].map((value, i) => (
                                            <td className="p-2" key={i}>
                                                {value === null
                                                    ? 'No disponible'
                                                    : value}
                                            </td>
                                        ))}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <p className="mt-2 text-xs text-muted-foreground">
                        Pedido no equivale a recibido. La reserva no equivale a
                        consumo. Disponible libre es existencia física sin
                        reservar.
                    </p>
                </details>
            )}
            {journey.closure?.can_manage && (
                <details className="border-t pt-3">
                    <summary className="cursor-pointer font-medium">
                        Gestionar cierre y requisitos del expediente
                    </summary>
                    <form
                        className="mt-3 grid gap-3"
                        onSubmit={(e) => {
                            e.preventDefault();
                            form.transform((data) => ({
                                ...data,
                                expected_updated_at:
                                    journey.closure!.updated_at,
                                expected_revision: journey.closure!.revision,
                            }));
                            form.post(
                                `/installations/${journey.closure!.installation_id}/dossier`,
                                { preserveScroll: true },
                            );
                        }}
                    >
                        <label className="grid gap-1 text-sm">
                            Acción
                            <select
                                className="w-full min-w-0 rounded border bg-background p-2"
                                value={form.data.action}
                                onChange={(e) =>
                                    form.setData('action', e.target.value)
                                }
                            >
                                <option
                                    value="close"
                                    disabled={!!journey.closure.closed_at}
                                >
                                    Cerrar expediente
                                </option>
                                <option
                                    value="reopen"
                                    disabled={!journey.closure.closed_at}
                                >
                                    Reabrir expediente
                                </option>
                                <option value="confirm_delivery">
                                    Confirmar entrega documental revisada
                                </option>
                                <option value="set_required_tasks">
                                    Actualizar tareas obligatorias para el
                                    cierre
                                </option>
                                <option value="require_technical">
                                    Requerir visita o ingeniería antes de
                                    ejecutar
                                </option>
                                <option value="waive_technical">
                                    Marcar validación técnica como no necesaria
                                </option>
                            </select>
                        </label>
                        {form.data.action === 'set_required_tasks' && (
                            <fieldset className="grid gap-2">
                                <legend className="text-sm font-medium">
                                    Selecciona únicamente las tareas que
                                    condicionan la entrega
                                </legend>
                                {journey.closure.tasks.length ? (
                                    journey.closure.tasks.map((task) => (
                                        <label
                                            className="flex items-start gap-2 text-sm"
                                            key={task.id}
                                        >
                                            <input
                                                type="checkbox"
                                                checked={form.data.required_activity_ids.includes(
                                                    task.id,
                                                )}
                                                onChange={(e) =>
                                                    form.setData(
                                                        'required_activity_ids',
                                                        e.target.checked
                                                            ? [
                                                                  ...form.data
                                                                      .required_activity_ids,
                                                                  task.id,
                                                              ]
                                                            : form.data.required_activity_ids.filter(
                                                                  (id) =>
                                                                      id !==
                                                                      task.id,
                                                              ),
                                                    )
                                                }
                                            />
                                            {task.title} · {task.status}
                                        </label>
                                    ))
                                ) : (
                                    <p className="text-sm">
                                        No hay tareas independientes
                                        registradas.
                                    </p>
                                )}
                            </fieldset>
                        )}
                        {form.data.action === 'close' && (
                            <label className="flex items-start gap-2 text-sm">
                                <input
                                    type="checkbox"
                                    checked={form.data.economic_exception}
                                    onChange={(e) =>
                                        form.setData(
                                            'economic_exception',
                                            e.target.checked,
                                        )
                                    }
                                />
                                Autorizar excepción económica como
                                administrador: cerrar con cobros o facturas
                                pendientes. El motivo quedará registrado; los
                                pendientes se conservarán.
                            </label>
                        )}
                        <p className="text-xs text-muted-foreground">
                            El cierre requiere ejecución terminada, entrega
                            confirmada y tareas obligatorias resueltas. Los
                            pendientes económicos requieren una excepción
                            expresa de administrador con motivo. Las tareas
                            informativas no bloquean.
                        </p>
                        <label className="grid gap-1 text-sm">
                            Motivo obligatorio
                            <Textarea
                                required
                                value={form.data.reason}
                                onChange={(e) =>
                                    form.setData('reason', e.target.value)
                                }
                            />
                        </label>
                        {Object.entries(form.errors).map(([key, value]) => (
                            <InputError key={key} message={value} />
                        ))}
                        <Button
                            className="justify-self-start"
                            disabled={form.processing}
                        >
                            Registrar decisión
                        </Button>
                    </form>
                </details>
            )}
        </section>
    );
}

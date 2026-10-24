import { Link, useForm } from '@inertiajs/react';
import { Frame, Card, kinds } from '@/components/operations/workspace';
import type { Workspace } from '@/components/operations/workspace';
import { Button } from '@/components/ui/button';
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
        title: '',
        kind: data.selectedKind || 'installation',
        work_order_template_id: '',
        scheduled_start: '',
        scheduled_end: '',
        instructions: '',
    });

    return (
        <Frame
            title="Nueva orden de trabajo"
            description="Crea un trabajo vinculado a una instalación real."
        >
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
                        form.transform((d) => ({
                            ...d,
                            scheduled_start: d.scheduled_start
                                ? new Date(d.scheduled_start).toISOString()
                                : '',
                            scheduled_end: d.scheduled_end
                                ? new Date(d.scheduled_end).toISOString()
                                : '',
                        }));
                        form.post('/work-orders');
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
                    <Button disabled={form.processing}>
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

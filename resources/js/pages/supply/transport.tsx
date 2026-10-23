import { Head, Link, useForm } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { PageHeader, PageShell } from '@/components/page-shell';
import { Panel } from '@/components/supply/workspace';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
type Option = {
    is_usable?: boolean;
    id: number;
    name: string;
};
export default function Transport({
    products,
    warehouses,
    installations,
    users,
    deliveries,
}: {
    products: Option[];
    warehouses: Option[];
    installations: {
        id: number;
        project_reference: string | null;
        site_name: string | null;
    }[];
    users: Option[];
    deliveries: { id: number; number: string; installation_id: number }[];
}) {
    const form = useForm({
        type: 'delivery',
        operation_key: crypto.randomUUID(),
        installation_id: '',
        recipient_id: '',
        source_movement_id: '',
        source_warehouse_id: '',
        destination_warehouse_id: '',
        product_id: '',
        quantity: '',
        serials: '',
        notes: '',
        material_condition: 'usable',
    });
    const isReturn = form.data.type === 'internal_return';
    const selects = [
        ['product_id', 'Producto', products],
        ['source_warehouse_id', 'Almacén de origen', warehouses],
        ['destination_warehouse_id', 'Almacén de destino', warehouses],
        ['recipient_id', 'Receptor', users],
    ] as const;

    return (
        <>
            <Head title="Entrega de material" />
            <PageShell className="supply-workspace">
                <PageHeader
                    title={
                        form.data.type === 'delivery'
                            ? 'Entrega de material'
                            : 'Devolución interna'
                    }
                    description="Traslada material entre almacenes reales conservando sus reservas y números de serie."
                    secondaryActions={
                        <Button variant="outline" asChild>
                            <Link href="/inventory">Volver a inventario</Link>
                        </Button>
                    }
                />
                <Panel title="Movimiento físico">
                    <form
                        className="supply-filters"
                        onSubmit={(event) => {
                            event.preventDefault();
                            form.post('/inventory/transport');
                        }}
                    >
                        <label>
                            Operación
                            <select
                                value={form.data.type}
                                onChange={(e) =>
                                    form.setData('type', e.target.value)
                                }
                            >
                                <option value="delivery">Entrega</option>
                                <option value="internal_return">
                                    Devolución interna
                                </option>
                            </select>
                        </label>
                        <label>
                            Instalación
                            <select
                                required
                                value={form.data.installation_id}
                                onChange={(e) =>
                                    form.setData(
                                        'installation_id',
                                        e.target.value,
                                    )
                                }
                            >
                                <option value="">Selecciona instalación</option>
                                {installations.map((r) => (
                                    <option key={r.id} value={r.id}>
                                        {r.project_reference ??
                                            r.site_name ??
                                            r.id}
                                    </option>
                                ))}
                            </select>
                            <InputError message={form.errors.installation_id} />
                        </label>
                        {selects.map(([key, label, rows]) => (
                            <label key={key}>
                                {label}
                                <select
                                    required={key !== 'recipient_id'}
                                    value={form.data[key]}
                                    onChange={(e) =>
                                        form.setData(key, e.target.value)
                                    }
                                >
                                    <option value="">Seleccionar</option>
                                    {rows.map((r) => (
                                        <option key={r.id} value={r.id}>
                                            {r.name}
                                            {r.is_usable === false
                                                ? ' (cuarentena)'
                                                : ''}
                                        </option>
                                    ))}
                                </select>
                                <InputError message={form.errors[key]} />
                            </label>
                        ))}
                        {isReturn && (
                            <label>
                                Entrega de origen
                                <select
                                    required
                                    value={form.data.source_movement_id}
                                    onChange={(e) =>
                                        form.setData(
                                            'source_movement_id',
                                            e.target.value,
                                        )
                                    }
                                >
                                    <option value="">Seleccionar</option>
                                    {deliveries
                                        .filter(
                                            (r) =>
                                                String(r.installation_id) ===
                                                form.data.installation_id,
                                        )
                                        .map((r) => (
                                            <option key={r.id} value={r.id}>
                                                {r.number}
                                            </option>
                                        ))}
                                </select>
                                <InputError
                                    message={form.errors.source_movement_id}
                                />
                            </label>
                        )}
                        {isReturn && (
                            <label>
                                Estado del material
                                <select
                                    value={form.data.material_condition}
                                    onChange={(e) =>
                                        form.setData(
                                            'material_condition',
                                            e.target.value,
                                        )
                                    }
                                >
                                    <option value="usable">Utilizable</option>
                                    <option value="damaged">
                                        Dañado (cuarentena)
                                    </option>
                                </select>
                            </label>
                        )}
                        <label>
                            Cantidad
                            <Input
                                required
                                type="number"
                                step="0.001"
                                min="0.001"
                                value={form.data.quantity}
                                onChange={(e) =>
                                    form.setData('quantity', e.target.value)
                                }
                            />
                            <InputError message={form.errors.quantity} />
                        </label>
                        <label>
                            Números de serie
                            <Input
                                value={form.data.serials}
                                onChange={(e) =>
                                    form.setData('serials', e.target.value)
                                }
                            />
                        </label>
                        <label>
                            Observaciones
                            <Input
                                value={form.data.notes}
                                onChange={(e) =>
                                    form.setData('notes', e.target.value)
                                }
                            />
                        </label>
                        <div className="w-full">
                            <InputError
                                message={form.errors.material_condition}
                            />
                            <InputError message={form.errors.operation_key} />
                            <InputError message={form.errors.serials} />
                            <InputError
                                message={
                                    (form.errors as Record<string, string>)
                                        .lines
                                }
                            />
                            <p className="supply-note mb-4">
                                La entrega no registra consumo. El material
                                dañado debe destinarse a un almacén no
                                utilizable.
                            </p>
                            <Button disabled={form.processing} type="submit">
                                Registrar movimiento
                            </Button>
                        </div>
                    </form>
                </Panel>
            </PageShell>
        </>
    );
}

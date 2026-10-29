import { useForm } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { useOperationsFormProtection } from '@/hooks/use-operations-form-protection';
import { useSupplyConfirmation } from './confirm-operation';
import { qty } from './workspace';

export type MaterialOptions = {
    products: { id: number; name: string; unit?: { symbol: string } | null }[];
    warehouses: { id: number; name: string }[];
};
export type ReservedMaterial = {
    id: number;
    quantity: string;
    consumed_quantity?: string;
    status?: string;
    product?: { name: string; unit?: { symbol: string } | null };
    warehouse?: { name: string };
};

export function ReserveOrderMaterial({
    orderId,
    options,
}: {
    orderId: number;
    options: MaterialOptions;
}) {
    const form = useForm({
        warehouse_id: '',
        product_id: '',
        quantity: '',
        reserved_until: '',
    });
    const protection = useOperationsFormProtection(() => form);
    const confirmation = useSupplyConfirmation();

    return (
        <>
            {protection.confirmation}
            {confirmation.dialog}
            <form
                className="supply-operation-form"
                onSubmit={(event) => {
                    event.preventDefault();
                    confirmation.confirm(() => {
                        if (!protection.beginSubmit()) {
                            return;
                        }

                        form.post(`/work-orders/${orderId}/materials`, {
                            preserveScroll: true,
                            onSuccess: () => protection.saved(),
                            onFinish: protection.finishSubmit,
                        });
                    });
                }}
            >
                <h3>Reservar material para esta orden</h3>
                <label>
                    Producto
                    <select
                        required
                        value={form.data.product_id}
                        onChange={(e) =>
                            form.setData('product_id', e.target.value)
                        }
                    >
                        <option value="">Seleccionar</option>
                        {options.products.map((p) => (
                            <option key={p.id} value={p.id}>
                                {p.name} {p.unit?.symbol}
                            </option>
                        ))}
                    </select>
                </label>
                <label>
                    Almacén de origen
                    <select
                        required
                        value={form.data.warehouse_id}
                        onChange={(e) =>
                            form.setData('warehouse_id', e.target.value)
                        }
                    >
                        <option value="">Seleccionar</option>
                        {options.warehouses.map((p) => (
                            <option key={p.id} value={p.id}>
                                {p.name}
                            </option>
                        ))}
                    </select>
                </label>
                <label>
                    Cantidad
                    <input
                        required
                        type="number"
                        step="0.001"
                        min="0.001"
                        value={form.data.quantity}
                        onChange={(e) =>
                            form.setData('quantity', e.target.value)
                        }
                    />
                </label>
                <label>
                    Reservado hasta
                    <input
                        type="datetime-local"
                        value={form.data.reserved_until}
                        onChange={(e) =>
                            form.setData('reserved_until', e.target.value)
                        }
                    />
                </label>
                {Object.entries(form.errors).map(([key, error]) => (
                    <InputError key={key} message={error} />
                ))}
                <p>
                    La reserva no entrega ni consume material. El servidor
                    comprobará la disponibilidad del almacén y las condiciones
                    de la instalación.
                </p>
                <Button disabled={form.processing}>Reservar material</Button>
            </form>
        </>
    );
}

export function ConsumeOrderMaterial({
    orderId,
    row,
}: {
    orderId: number;
    row: ReservedMaterial;
}) {
    const form = useForm({
        quantity: '',
        serials: '',
        operation_key: crypto.randomUUID(),
    });
    const protection = useOperationsFormProtection(() => form);
    const confirmation = useSupplyConfirmation();

    return (
        <>
            {protection.confirmation}
            {confirmation.dialog}
            <form
                className="supply-operation-form"
                onSubmit={(event) => {
                    event.preventDefault();
                    confirmation.confirm(() => {
                        if (!protection.beginSubmit()) {
                            return;
                        }

                        form.post(
                            `/work-orders/${orderId}/materials/${row.id}/consume`,
                            {
                                preserveScroll: true,
                                onSuccess: () => {
                                    const clean = {
                                        quantity: '',
                                        serials: '',
                                        operation_key: crypto.randomUUID(),
                                    };
                                    form.setDefaults(clean);
                                    protection.saved();
                                    form.setData(clean);
                                },
                                onFinish: protection.finishSubmit,
                            },
                        );
                    });
                }}
            >
                <h3>{row.product?.name}</h3>
                <p>
                    {row.warehouse?.name} · Reservado: {qty(row.quantity)}{' '}
                    {row.product?.unit?.symbol} · Consumido:{' '}
                    {qty(row.consumed_quantity ?? '0')}{' '}
                    {row.product?.unit?.symbol}
                </p>
                <label>
                    Cantidad a consumir
                    <input
                        required
                        type="number"
                        min="0.001"
                        step="0.001"
                        value={form.data.quantity}
                        onChange={(e) =>
                            form.setData('quantity', e.target.value)
                        }
                    />
                </label>
                <label>
                    Números de serie
                    <input
                        value={form.data.serials}
                        onChange={(e) =>
                            form.setData('serials', e.target.value)
                        }
                    />
                </label>
                {Object.entries(form.errors).map(([key, error]) => (
                    <InputError key={key} message={error} />
                ))}
                <Button disabled={form.processing}>Registrar consumo</Button>
            </form>
        </>
    );
}

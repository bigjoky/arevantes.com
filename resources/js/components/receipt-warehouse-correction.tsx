import { useForm, usePage } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { useSupplyConfirmation } from '@/components/supply/confirm-operation';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { useOperationsFormProtection } from '@/hooks/use-operations-form-protection';
export default function ReceiptWarehouseCorrection({
    orderId,
    receiptId,
    warehouseId,
    warehouses,
}: {
    orderId: number;
    receiptId: number;
    warehouseId: number;
    warehouses: {
        id: number;
        name: string;
        code: string;
        is_usable?: boolean;
    }[];
}) {
    const page = usePage();
    const abilities = (
        page.props.auth as {
            abilities?: { managePurchases?: boolean; manageCatalog?: boolean };
        }
    )?.abilities;
    const form = useForm({
        warehouse_id: String(warehouseId),
        expected_warehouse_id: warehouseId,
        reason: '',
        operation_key: crypto.randomUUID(),
    });
    const protection = useOperationsFormProtection(() => form);
    const confirmation = useSupplyConfirmation();
    const warehouse = warehouses.find((w) => w.id === warehouseId);

    return (
        <div className="min-w-0">
            {protection.confirmation}
            {confirmation.dialog}
            <p>Almacén registrado: {warehouse?.name || `#${warehouseId}`}</p>
            {abilities?.managePurchases && abilities?.manageCatalog && (
                <details>
                    <summary className="cursor-pointer underline">
                        Corregir almacén de recepción
                    </summary>
                    <form
                        className="mt-2 grid gap-2"
                        onSubmit={(e) => {
                            e.preventDefault();
                            confirmation.confirm(() => {
                                if (!protection.beginSubmit()) {
                                    return;
                                }

                                form.post(
                                    `/purchases/${orderId}/receipts/${receiptId}/warehouse`,
                                    {
                                        preserveScroll: true,
                                        onSuccess: () => {
                                            const clean = {
                                                ...form.data,
                                                expected_warehouse_id: Number(
                                                    form.data.warehouse_id,
                                                ),
                                                reason: '',
                                                operation_key:
                                                    crypto.randomUUID(),
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
                        <p className="text-xs text-muted-foreground">
                            Se registra un traslado compensatorio. No altera las
                            cantidades recibidas. Material consumido, trasladado
                            o reservado requiere revisión previa.
                        </p>
                        <label>
                            Almacén correcto
                            <select
                                className="block w-full rounded border bg-background p-2"
                                value={form.data.warehouse_id}
                                onChange={(e) =>
                                    form.setData('warehouse_id', e.target.value)
                                }
                            >
                                {warehouses
                                    .filter((w) => w.is_usable !== false)
                                    .map((w) => (
                                        <option key={w.id} value={w.id}>
                                            {w.name} · {w.code}
                                        </option>
                                    ))}
                            </select>
                        </label>
                        <label>
                            Motivo
                            <Textarea
                                required
                                value={form.data.reason}
                                onChange={(e) =>
                                    form.setData('reason', e.target.value)
                                }
                            />
                        </label>
                        {Object.entries(form.errors).map(([k, v]) => (
                            <InputError key={k} message={v} />
                        ))}
                        <Button
                            disabled={
                                form.processing ||
                                form.data.warehouse_id === String(warehouseId)
                            }
                        >
                            Registrar corrección
                        </Button>
                    </form>
                </details>
            )}
        </div>
    );
}

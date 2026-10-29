import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { useSupplyConfirmation } from '@/components/supply/confirm-operation';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { useOperationsFormProtection } from '@/hooks/use-operations-form-protection';
export type NeedOptions = {
    products: { id: number; name: string }[];
    installations: {
        id: number;
        project_reference: string | null;
        site_name: string | null;
    }[];
};
export function RequirementDialog({ options }: { options: NeedOptions }) {
    const [open, setOpen] = useState(false);
    const form = useForm({
        installation_id: '',
        product_id: '',
        planned_quantity: '',
    });
    const protection = useOperationsFormProtection(() => form);
    const confirmation = useSupplyConfirmation();

    return (
        <>
            {' '}
            {protection.confirmation}
            {confirmation.dialog}
            <Dialog
                open={open}
                onOpenChange={(value) => {
                    if (value) {
                        setOpen(true);
                    } else {
                        protection.requestClose(() => setOpen(false));
                    }
                }}
            >
                <DialogTrigger asChild>
                    <Button>Nueva necesidad</Button>
                </DialogTrigger>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Nueva necesidad de material</DialogTitle>
                    </DialogHeader>
                    <form
                        className="supply-filters"
                        onSubmit={(event) => {
                            event.preventDefault();
                            confirmation.confirm(() => {
                                if (!protection.beginSubmit()) {
                                    return;
                                }

                                form.post('/inventory/needs', {
                                    onSuccess: () => {
                                        protection.saved(() => setOpen(false));
                                    },
                                    onFinish: protection.finishSubmit,
                                });
                            });
                        }}
                    >
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
                                <option value="">Seleccionar</option>
                                {options.installations.map((r) => (
                                    <option key={r.id} value={r.id}>
                                        {r.project_reference ??
                                            r.site_name ??
                                            r.id}
                                    </option>
                                ))}
                            </select>
                        </label>
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
                                {options.products.map((r) => (
                                    <option key={r.id} value={r.id}>
                                        {r.name}
                                    </option>
                                ))}
                            </select>
                        </label>
                        <label>
                            Cantidad requerida
                            <Input
                                type="number"
                                required
                                step="0.001"
                                min="0.001"
                                value={form.data.planned_quantity}
                                onChange={(e) =>
                                    form.setData(
                                        'planned_quantity',
                                        e.target.value,
                                    )
                                }
                            />
                        </label>
                        {Object.entries(form.errors).map(([key, message]) => (
                            <InputError key={key} message={message} />
                        ))}
                        <Button type="submit" disabled={form.processing}>
                            Registrar necesidad
                        </Button>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}

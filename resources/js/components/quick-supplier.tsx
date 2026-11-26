import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

export type QuickSupplierValue = {
    id: number;
    legal_name: string;
    trade_name: string | null;
    tax_id: string | null;
    country: string;
    is_active: boolean;
};
async function post(
    url: string,
    payload: unknown,
): Promise<{
    status?: string;
    fields?: Partial<typeof initial>;
    existing?: boolean;
    supplier: QuickSupplierValue;
}> {
    const token =
        document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
            ?.content ?? '';
    const response = await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': token,
        },
        body: JSON.stringify(payload),
    });
    const data = await response.json();

    if (!response.ok) {
        throw new Error(
            Object.values(data.errors ?? {})
                .flat()
                .join(' ') || 'No se ha podido completar la operación.',
        );
    }

    return data;
}
const initial = {
    tax_id: '',
    legal_name: '',
    trade_name: '',
    address_line_1: '',
    address_line_2: '',
    postal_code: '',
    city: '',
    province: '',
    country: 'ES',
};
export function QuickSupplier({
    onSelect,
}: {
    onSelect: (supplier: QuickSupplierValue) => void;
}) {
    const [open, setOpen] = useState(false);
    const [values, setValues] = useState(initial);
    const [message, setMessage] = useState('');
    const [existing, setExisting] = useState<QuickSupplierValue | null>(null);
    const [busy, setBusy] = useState(false);
    async function lookup() {
        setBusy(true);
        setMessage('');

        try {
            const data = await post('/catalog/suppliers/quick-lookup', {
                tax_id: values.tax_id,
                country: values.country.toUpperCase(),
            });

            if (data.status === 'found') {
                setValues((v) => {
                    if (
                        v.country.toUpperCase() !== 'ES' ||
                        v.tax_id.replace(/[^a-z0-9]/gi, '').toUpperCase() !==
                        values.tax_id.replace(/[^a-z0-9]/gi, '').toUpperCase()
                    ) {
return v;
}

                    const next = { ...v };

                    for (const key of [
                        'legal_name',
                        'address_line_1',
                        'postal_code',
                        'city',
                        'province',
                    ] as const) {
                        if (!next[key] && data.fields?.[key]) {
next[key] = data.fields[key];
}
                    }

                    return next;
                });
                setMessage(
                    'Datos registrales para revisar. El domicilio social puede diferir del domicilio fiscal.',
                );
            } else {
                setMessage(
                    data.status === 'not_found'
                        ? 'No se han encontrado datos. Puedes continuar manualmente.'
                        : 'Consulta no disponible. Puedes continuar manualmente.',
                );
            }
        } catch {
            setMessage(
                'No se ha podido consultar. Puedes continuar manualmente.',
            );
        } finally {
            setBusy(false);
        }
    }
    async function save() {
        setBusy(true);
        setMessage('');
        setExisting(null);

        try {
            const data = await post('/catalog/suppliers/quick-create', values);

            if (data.existing) {
                setExisting(data.supplier);
                setMessage(
                    'Ya existe un proveedor con este identificador y país.',
                );
            } else {
                onSelect(data.supplier);
                setOpen(false);
                setValues(initial);
            }
        } catch (error) {
            setMessage(
                error instanceof Error
                    ? error.message
                    : 'No se ha podido guardar el proveedor.',
            );
        } finally {
            setBusy(false);
        }
    }
    const fields: [keyof typeof initial, string][] = [
        ['tax_id', 'NIF/CIF'],
        ['legal_name', 'Razón social / nombre fiscal'],
        ['trade_name', 'Nombre comercial (opcional)'],
        ['address_line_1', 'Dirección'],
        ['address_line_2', 'Dirección adicional'],
        ['postal_code', 'Código postal'],
        ['city', 'Municipio'],
        ['province', 'Provincia'],
        ['country', 'País (código de dos letras)'],
    ];

    return (
        <>
            <Button
                type="button"
                variant="outline"
                onClick={() => {
                    setOpen(true);
                    setMessage('');
                }}
            >
                + Nuevo proveedor
            </Button>
            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>Nuevo proveedor</DialogTitle>
                    </DialogHeader>
                    <div className="grid gap-3 sm:grid-cols-2">
                        {fields.map(([key, label]) => (
                            <label key={key} className="grid gap-1 text-sm">
                                {label}
                                <input
                                    className="min-w-0 rounded border px-3 py-2"
                                    value={values[key]}
                                    onChange={(event) => {
                                        setValues((v) => ({
                                            ...v,
                                            [key]: event.target.value,
                                        }));
                                        setExisting(null);
                                    }}
                                />
                            </label>
                        ))}
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            disabled={busy || !values.tax_id || values.country.toUpperCase() !== 'ES'}
                            onClick={lookup}
                        >
                            Consultar CIF
                        </Button>
                        <Button type="button" disabled={busy} onClick={save}>
                            Guardar proveedor
                        </Button>
                        {existing?.is_active && (
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => {
                                    onSelect(existing);
                                    setOpen(false);
                                }}
                            >
                                Seleccionar proveedor existente
                            </Button>
                        )}
                    </div>
                    {message && (
                        <p role="status" className="text-sm">
                            {message}
                            {existing && !existing.is_active
                                ? ' El proveedor está inactivo; revísalo en el catálogo.'
                                : ''}
                        </p>
                    )}
                </DialogContent>
            </Dialog>
        </>
    );
}

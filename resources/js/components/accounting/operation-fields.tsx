import { usePage } from '@inertiajs/react';
import { useState } from 'react';
import { AccountInput, roleNames } from './account-input';
type Kind = 'sale' | 'purchase' | 'expense' | 'receipt';
type Options = {
    accounts: { id: number; code: string; name: string; type: string }[];
    categories: { id: number; name: string }[];
};
export function AccountingOperationFields({
    kind,
    initial = {},
    categoryId,
}: {
    kind: Kind;
    initial?: Record<string, string> | null;
    categoryId?: number | null;
}) {
    const options = usePage().props.accountingInputOptions as
        | Options
        | null
        | undefined;
    const [values, setValues] = useState<Record<string, string>>(initial ?? {});

    if (!options) {
        return null;
    }

    const roles = {
        sale: ['receivable', 'revenue', 'customer_advance'],
        purchase: ['supplier', 'purchase'],
        expense: ['supplier', 'creditor', 'expense', 'withholding'],
        receipt: ['bank', 'cash', 'customer_advance'],
    }[kind];

    return (
        <fieldset className="my-4 space-y-3 rounded border p-4">
            <legend className="font-semibold">
                Decisiones contables de esta operación
            </legend>
            <p className="text-sm text-muted-foreground">
                Opcional. Una cuenta indicada aquí tiene prioridad sobre el
                tercero, producto y reglas. Si se deja vacía, se utiliza la
                jerarquía configurada. El cobro de una factura contabilizada
                conserva su cuenta de cliente.
            </p>
            <div className="grid gap-3 sm:grid-cols-2">
                {roles.map((role) => (
                    <label key={role}>
                        {roleNames[role]}
                        <AccountInput
                            name={`accounting_overrides[${role}]`}
                            value={values[role] ?? ''}
                            onChange={(code) =>
                                setValues({ ...values, [role]: code })
                            }
                            accounts={options.accounts}
                        />
                    </label>
                ))}
                {['sale', 'purchase'].includes(kind) && (
                    <label>
                        Categoría contable
                        <select
                            name="accounting_category_id"
                            defaultValue={categoryId ?? ''}
                            className="w-full rounded border p-2"
                        >
                            <option value="">Sin categoría específica</option>
                            {options.categories.map((c) => (
                                <option key={c.id} value={c.id}>
                                    {c.name}
                                </option>
                            ))}
                        </select>
                    </label>
                )}
            </div>
            <label className="block text-sm">
                <input
                    type="checkbox"
                    name="accounting_confirmation"
                    value="1"
                />{' '}
                Confirmo las decisiones contables indicadas.
            </label>
            <p className="text-xs">
                En gastos, elige una sola contrapartida: proveedor o acreedor.
                En cobros, se aplica la cuenta de la forma de pago seleccionada;
                el anticipo solo corresponde a documentos sin emitir.
            </p>
            <p className="text-xs">
                No cambia asientos existentes. Las cuentas fiscales y la
                naturaleza de cada apunte siguen protegidas.
            </p>
        </fieldset>
    );
}
export function readAccountingFields(
    element: HTMLFormElement,
): Record<string, Record<string, string> | string> {
    const result: Record<string, Record<string, string> | string> = {};
    const overrides: Record<string, string> = {};

    for (const [key, value] of new FormData(element).entries()) {
        const match = /^accounting_overrides\[([a-z_]+)\]$/.exec(key);

        if (match && typeof value === 'string') {
            overrides[match[1]] = value;
        }

        if (key === 'accounting_category_id' && typeof value === 'string') {
            result.accounting_category_id = value;
        }
    }

    if (Object.keys(overrides).length) {
        result.accounting_overrides = overrides;
    }

    return result;
}

import { useState } from 'react';
import { Input } from '@/components/ui/input';
type Account = { id?: number; code: string; name: string; type?: string };
export const roleNames: Record<string, string> = {
    receivable: 'Clientes',
    supplier: 'Proveedores',
    creditor: 'Acreedores',
    credit_card: 'Tarjeta de crédito',
    treasury_other: 'Otros fondos de tesorería',
    bank: 'Bancos',
    cash: 'Caja',
    revenue: 'Ingresos',
    purchase: 'Compras',
    expense: 'Gastos',
    inventory: 'Existencias',
    fixed_asset: 'Inmovilizado',
    customer_advance: 'Anticipos de clientes',
    supplier_advance: 'Anticipos a proveedores',
    output_vat: 'IVA repercutido',
    input_vat: 'IVA soportado',
    withholding: 'Retenciones',
    employee_advance: 'Anticipos al personal',
    employee_payable: 'Remuneraciones pendientes',
};
export function AccountInput({
    value,
    onChange,
    accounts,
    required = false,
    name,
}: {
    value: string;
    onChange: (v: string) => void;
    accounts: Account[];
    required?: boolean;
    name?: string;
}) {
    const [id] = useState(() => `accounts-${crypto.randomUUID()}`);

    return (
        <>
            <Input
                name={name}
                list={id}
                value={value}
                onChange={(e) => onChange(e.target.value)}
                placeholder="Buscar cuenta por código o nombre"
                required={required}
            />
            <datalist id={id}>
                {accounts.map((a) => (
                    <option key={a.code} value={a.code}>
                        {a.code} · {a.name}
                    </option>
                ))}
            </datalist>
        </>
    );
}

import { useState } from 'react';

type Option = { id: number; label: string };

export type PaymentSources = {
    bankAccounts: Option[];
    cards: Option[];
    employees: Option[];
    cashAccounts: Option[];
    otherAccounts: Option[];
};

const configuration = {
    bank_transfer: {
        type: 'bank_account',
        key: 'bankAccounts',
        label: 'Bank account',
    },
    direct_debit: {
        type: 'bank_account',
        key: 'bankAccounts',
        label: 'Bank account',
    },
    card: { type: 'card', key: 'cards', label: 'Card' },
    cash: {
        type: 'ledger_account',
        key: 'cashAccounts',
        label: 'Cash account',
    },
    employee_funds: { type: 'employee', key: 'employees', label: 'Paid by' },
    other: {
        type: 'ledger_account',
        key: 'otherAccounts',
        label: 'Accounting account',
    },
} as const;

export function PaymentSourceFields({ sources }: { sources: PaymentSources }) {
    const [method, setMethod] =
        useState<keyof typeof configuration>('bank_transfer');
    const selected = configuration[method];
    const options = sources[selected.key];

    return (
        <>
            <div className="grid gap-2">
                <label className="text-sm font-medium">Method</label>
                <select
                    name="method"
                    value={method}
                    onChange={(event) =>
                        setMethod(
                            event.target.value as keyof typeof configuration,
                        )
                    }
                    className="h-9 rounded-md border bg-background px-3 text-sm"
                >
                    <option value="bank_transfer">Bank transfer</option>
                    <option value="card">Card</option>
                    <option value="cash">Cash</option>
                    <option value="direct_debit">Direct debit</option>
                    <option value="employee_funds">Paid by employee</option>
                    <option value="other">Other</option>
                </select>
            </div>
            <div className="grid gap-2">
                <label className="text-sm font-medium">{selected.label}</label>
                <input
                    type="hidden"
                    name="funding_source_type"
                    value={selected.type}
                />
                <select
                    key={method}
                    name="funding_source_id"
                    required
                    defaultValue=""
                    className="h-9 rounded-md border bg-background px-3 text-sm"
                >
                    <option value="" disabled>
                        Select payment source
                    </option>
                    {options.map((option) => (
                        <option key={option.id} value={option.id}>
                            {option.label}
                        </option>
                    ))}
                </select>
                {options.length === 0 && (
                    <p className="text-xs text-destructive">
                        No compatible payment sources are configured.
                    </p>
                )}
            </div>
        </>
    );
}

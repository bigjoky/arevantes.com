import { Form, Head, Link, usePage } from '@inertiajs/react';
import { ArrowLeft, Download, Trash2, WalletCards } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
type Expense = {
    id: number;
    number: string;
    type: string;
    document_number: string | null;
    invoice_series: string | null;
    merchant_name: string | null;
    issuer_legal_name: string | null;
    issuer_tax_id: string | null;
    expense_date: string;
    operation_date: string | null;
    due_date: string | null;
    currency_code: string;
    tax_regime: string;
    subtotal: string;
    tax_amount: string;
    withholding_rate: string;
    withholding_amount: string;
    deductible_expense_percent: string;
    amount: string;
    paid_amount: string;
    status: string;
    notes: string | null;
    category: { name: string };
    supplier: { legal_name: string; trade_name: string | null } | null;
    tax_lines: {
        id: number;
        kind: string;
        taxable_base: string;
        tax_rate: string;
        tax_amount: string;
        deductible_percent: string;
        deductible_amount: string;
    }[];
    attachments: {
        id: number;
        original_name: string;
        mime_type: string;
        size: number;
    }[];
    payments: {
        id: number;
        paid_at: string;
        amount: string;
        method: string;
        reference: string | null;
    }[];
};
export default function ExpenseShow({ expense }: { expense: Expense }) {
    const canManagePayments = usePage<{
        auth: { abilities: { managePurchasePayments: boolean } };
    }>().props.auth.abilities.managePurchasePayments;
    const outstanding = Math.max(
        0,
        Number(expense.amount) - Number(expense.paid_amount),
    );

    return (
        <>
            <Head title={expense.number} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <header className="flex items-start justify-between gap-3">
                    <div>
                        <div className="flex items-center gap-2">
                            <h1 className="text-xl font-semibold">
                                {expense.number}
                            </h1>
                            <Badge variant="outline">{expense.status}</Badge>
                        </div>
                        <p className="text-sm text-muted-foreground">
                            {expense.category.name} ·{' '}
                            {expense.supplier?.trade_name ||
                                expense.supplier?.legal_name ||
                                expense.merchant_name ||
                                'Expense'}
                        </p>
                    </div>
                    <Button variant="outline" asChild>
                        <Link href="/expenses">
                            <ArrowLeft /> Expenses
                        </Link>
                    </Button>
                </header>
                <section className="grid gap-4 border-y py-4 text-sm sm:grid-cols-3 xl:grid-cols-6">
                    <Info
                        label="Document"
                        value={
                            [expense.invoice_series, expense.document_number]
                                .filter(Boolean)
                                .join(' / ') || '—'
                        }
                    />
                    <Info
                        label="Issuer"
                        value={
                            expense.issuer_legal_name ||
                            expense.merchant_name ||
                            '—'
                        }
                    />
                    <Info label="Tax ID" value={expense.issuer_tax_id || '—'} />
                    <Info
                        label="Issue date"
                        value={date(expense.expense_date)}
                    />
                    <Info
                        label="Operation date"
                        value={
                            expense.operation_date
                                ? date(expense.operation_date)
                                : '—'
                        }
                    />
                    <Info
                        label="Due"
                        value={expense.due_date ? date(expense.due_date) : '—'}
                    />
                    <Info
                        label="Fiscal document"
                        value={expense.type.replaceAll('_', ' ')}
                    />
                    <Info
                        label="VAT treatment"
                        value={fiscalLabel(expense.tax_regime)}
                    />
                    <Info label="Currency" value={expense.currency_code} />
                    <Info
                        label="Deductible expense"
                        value={percentage(expense.deductible_expense_percent)}
                    />
                </section>
                <section>
                    <h2 className="mb-3 font-semibold">Tax breakdown</h2>
                    <div className="overflow-x-auto border-y">
                        <table className="w-full min-w-[42rem] text-sm">
                            <thead>
                                <tr className="border-b text-left text-muted-foreground">
                                    <th className="py-2">Tax</th>
                                    <th className="text-right">Taxable base</th>
                                    <th className="text-right">Rate</th>
                                    <th className="text-right">Tax amount</th>
                                    <th className="text-right">Deductible</th>
                                </tr>
                            </thead>
                            <tbody>
                                {expense.tax_lines.map((line) => (
                                    <tr
                                        key={line.id}
                                        className="border-b last:border-0"
                                    >
                                        <td className="py-3">
                                            {fiscalLabel(line.kind)}
                                        </td>
                                        <td className="text-right">
                                            {money(line.taxable_base)}
                                        </td>
                                        <td className="text-right">
                                            {percentage(line.tax_rate)}
                                        </td>
                                        <td className="text-right">
                                            {money(line.tax_amount)}
                                        </td>
                                        <td className="text-right">
                                            {money(line.deductible_amount)} (
                                            {percentage(
                                                line.deductible_percent,
                                            )}
                                            )
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                        {expense.tax_lines.length === 0 && (
                            <Empty text="No tax breakdown." />
                        )}
                    </div>
                </section>
                <section className="ml-auto w-full max-w-sm border-t pt-4 text-sm">
                    <Total label="Subtotal" value={expense.subtotal} />
                    <Total label="Tax" value={expense.tax_amount} />
                    {Number(expense.withholding_amount) > 0 && (
                        <Total
                            label={
                                <>
                                    <span>Withholding</span> ·{' '}
                                    {percentage(expense.withholding_rate)}
                                </>
                            }
                            value={String(-Number(expense.withholding_amount))}
                        />
                    )}
                    <Total label="Total" value={expense.amount} strong />
                    <Total label="Paid" value={expense.paid_amount} />
                    <Total
                        label="Outstanding"
                        value={String(outstanding)}
                        strong
                    />
                </section>
                <section className="grid gap-8 border-t pt-5 xl:grid-cols-2">
                    <div>
                        <h2 className="mb-3 font-semibold">Attachments</h2>
                        <div className="border-y">
                            {expense.attachments.map((file) => (
                                <div
                                    key={file.id}
                                    className="flex items-center justify-between gap-3 border-b py-3 text-sm last:border-0"
                                >
                                    <div>
                                        <div className="font-medium">
                                            {file.original_name}
                                        </div>
                                        <div className="text-xs text-muted-foreground">
                                            {Math.ceil(file.size / 1024)} KB
                                        </div>
                                    </div>
                                    <div className="flex">
                                        <Button
                                            size="icon"
                                            variant="ghost"
                                            asChild
                                        >
                                            <a
                                                href={`/expenses/${expense.id}/attachments/${file.id}`}
                                            >
                                                <Download />
                                            </a>
                                        </Button>
                                        <Form
                                            action={`/expenses/${expense.id}/attachments/${file.id}`}
                                            method="delete"
                                        >
                                            <Button size="icon" variant="ghost">
                                                <Trash2 />
                                            </Button>
                                        </Form>
                                    </div>
                                </div>
                            ))}
                            {expense.attachments.length === 0 && (
                                <Empty text="No attachments." />
                            )}
                        </div>
                    </div>
                    <div>
                        <h2 className="mb-3 font-semibold">Payment history</h2>
                        <div className="border-y">
                            {expense.payments.map((payment) => (
                                <div
                                    key={payment.id}
                                    className="flex justify-between gap-3 border-b py-3 text-sm last:border-0"
                                >
                                    <div>
                                        <div className="font-medium">
                                            {money(payment.amount)} ·{' '}
                                            {payment.method.replaceAll(
                                                '_',
                                                ' ',
                                            )}
                                        </div>
                                        <div className="text-xs text-muted-foreground">
                                            {date(payment.paid_at)}
                                            {payment.reference
                                                ? ` · ${payment.reference}`
                                                : ''}
                                        </div>
                                    </div>
                                    {canManagePayments && (
                                        <Form
                                            action={`/supplier-payments/${payment.id}`}
                                            method="delete"
                                        >
                                            <Button size="icon" variant="ghost">
                                                <Trash2 />
                                            </Button>
                                        </Form>
                                    )}
                                </div>
                            ))}
                            {expense.payments.length === 0 && (
                                <Empty text="No payments." />
                            )}
                        </div>
                    </div>
                </section>
                {canManagePayments && outstanding > 0 && (
                    <section className="border-t pt-5">
                        <h2 className="font-semibold">Register payment</h2>
                        <Form
                            action={`/expenses/${expense.id}/payments`}
                            method="post"
                            resetOnSuccess
                            className="mt-3 grid gap-4 sm:grid-cols-4 sm:items-end"
                        >
                            <Field
                                label="Date"
                                name="paid_at"
                                type="date"
                                value={today()}
                            />
                            <Field
                                label="Amount"
                                name="amount"
                                type="number"
                                value={String(outstanding)}
                                step="0.01"
                            />
                            <div className="grid gap-2">
                                <label className="text-sm font-medium">
                                    Method
                                </label>
                                <select
                                    name="method"
                                    className="h-9 rounded-md border bg-background px-3 text-sm"
                                >
                                    <option value="bank_transfer">
                                        Bank transfer
                                    </option>
                                    <option value="card">Card</option>
                                    <option value="cash">Cash</option>
                                    <option value="direct_debit">
                                        Direct debit
                                    </option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            <Button>
                                <WalletCards /> Register payment
                            </Button>
                        </Form>
                    </section>
                )}
                <section className="flex justify-end border-t pt-5">
                    <Form
                        action={`/expenses/${expense.id}`}
                        method="delete"
                        onBefore={() => confirm('Delete this expense?')}
                    >
                        <Button
                            variant="destructive"
                            disabled={expense.payments.length > 0}
                        >
                            <Trash2 /> Delete expense
                        </Button>
                    </Form>
                </section>
            </div>
        </>
    );
}
function Info({ label, value }: { label: string; value: string }) {
    return (
        <div>
            <div className="text-muted-foreground">{label}</div>
            <div className="mt-1 font-medium">{value}</div>
        </div>
    );
}
function Total({
    label,
    value,
    strong = false,
}: {
    label: React.ReactNode;
    value: string;
    strong?: boolean;
}) {
    return (
        <div
            className={`flex justify-between border-b py-2 ${strong ? 'text-base font-semibold' : ''}`}
        >
            <span>{label}</span>
            <span>{money(value)}</span>
        </div>
    );
}
function Field({
    label,
    name,
    type,
    value,
    step,
}: {
    label: string;
    name: string;
    type: string;
    value: string;
    step?: string;
}) {
    return (
        <div className="grid gap-2">
            <label className="text-sm font-medium">{label}</label>
            <Input name={name} type={type} defaultValue={value} step={step} />
        </div>
    );
}
function Empty({ text }: { text: string }) {
    return (
        <div className="py-8 text-center text-sm text-muted-foreground">
            {text}
        </div>
    );
}
const money = (v: string) =>
    new Intl.NumberFormat(document.documentElement.lang, {
        style: 'currency',
        currency: 'EUR',
    }).format(Number(v));
const date = (v: string) =>
    new Intl.DateTimeFormat(document.documentElement.lang, {
        dateStyle: 'medium',
    }).format(new Date(`${v.slice(0, 10)}T12:00:00`));
const percentage = (value: string) =>
    `${new Intl.NumberFormat(document.documentElement.lang, { maximumFractionDigits: 2 }).format(Number(value))}%`;
const fiscalLabel = (value: string) =>
    value === 'vat'
        ? 'VAT'
        : value
              .replaceAll('_', ' ')
              .replace(/^./, (character) => character.toUpperCase());
const today = () =>
    new Intl.DateTimeFormat('en-CA', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
    }).format(new Date());
ExpenseShow.layout = {
    breadcrumbs: [
        { title: 'Expenses', href: '/expenses' },
        { title: 'Expense', href: '#' },
    ],
};

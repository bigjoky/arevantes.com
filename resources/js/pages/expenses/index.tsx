import { Form, Head, Link, usePage } from '@inertiajs/react';
import { FilePlus2, Plus, ReceiptText, WalletCards } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
type Category = { id: number; name: string };
type Supplier = { id: number; legal_name: string; trade_name: string | null };
type Installation = {
    id: number;
    project_reference: string | null;
    site_name: string | null;
};
type Expense = {
    id: number;
    number: string;
    type: string;
    merchant_name: string | null;
    expense_date: string;
    due_date: string | null;
    amount: string;
    paid_amount: string;
    status: string;
    category: { name: string };
    supplier: Supplier | null;
};
type Invoice = {
    id: number;
    number: string;
    supplier_number: string | null;
    issue_date: string;
    due_date: string | null;
    amount: string;
    paid_amount: string;
    status: string;
    supplier: Supplier;
};
export default function ExpensesIndex({
    expenses,
    supplierInvoices,
    categories,
    suppliers,
    installations,
    summary,
}: {
    expenses: Expense[];
    supplierInvoices: Invoice[];
    categories: Category[];
    suppliers: Supplier[];
    installations: Installation[];
    summary: {
        pending: number;
        paidThisMonth: number;
        expensesThisMonth: number;
    };
}) {
    const [tab, setTab] = useState<'expenses' | 'payables'>('expenses');
    const quickCreate =
        new URLSearchParams(usePage().url.split('?')[1] || '').get('create') ===
        '1';

    return (
        <>
            <Head title="Expenses" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <header className="flex items-center justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold">
                            Expenses & treasury
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Receipts, expense invoices and supplier payments.
                        </p>
                    </div>
                    {tab === 'expenses' && (
                        <ExpenseDialog
                            categories={categories}
                            suppliers={suppliers}
                            installations={installations}
                            initialOpen={quickCreate}
                        />
                    )}
                </header>
                <section className="grid grid-cols-3 border-y">
                    <Metric label="Outstanding" value={summary.pending} />
                    <Metric
                        label="Paid this month"
                        value={summary.paidThisMonth}
                    />
                    <Metric
                        label="Expenses this month"
                        value={summary.expensesThisMonth}
                    />
                </section>
                <div className="flex gap-1 border-b">
                    <Tab
                        active={tab === 'expenses'}
                        onClick={() => setTab('expenses')}
                        icon={<ReceiptText />}
                    >
                        Expenses
                    </Tab>
                    <Tab
                        active={tab === 'payables'}
                        onClick={() => setTab('payables')}
                        icon={<WalletCards />}
                    >
                        Supplier invoices
                    </Tab>
                </div>
                {tab === 'expenses' ? (
                    <section className="overflow-x-auto">
                        <table className="w-full min-w-[52rem] text-sm">
                            <thead>
                                <tr className="border-b text-left text-muted-foreground">
                                    <th className="py-2">Expense</th>
                                    <th>Merchant</th>
                                    <th>Category</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                    <th className="text-right">Total</th>
                                    <th className="text-right">Outstanding</th>
                                </tr>
                            </thead>
                            <tbody>
                                {expenses.map((expense) => (
                                    <tr key={expense.id} className="border-b">
                                        <td className="py-3 font-medium">
                                            <Link
                                                className="underline underline-offset-2"
                                                href={`/expenses/${expense.id}`}
                                            >
                                                {expense.number}
                                            </Link>
                                        </td>
                                        <td>
                                            {expense.supplier?.trade_name ||
                                                expense.supplier?.legal_name ||
                                                expense.merchant_name ||
                                                '—'}
                                        </td>
                                        <td>{expense.category.name}</td>
                                        <td>{date(expense.expense_date)}</td>
                                        <td>
                                            <Badge variant="outline">
                                                {expense.status}
                                            </Badge>
                                        </td>
                                        <td className="text-right">
                                            {money(expense.amount)}
                                        </td>
                                        <td className="text-right font-medium">
                                            {money(
                                                String(
                                                    Number(expense.amount) -
                                                        Number(
                                                            expense.paid_amount,
                                                        ),
                                                ),
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                        {expenses.length === 0 && (
                            <Empty text="No expenses registered." />
                        )}
                    </section>
                ) : (
                    <section className="overflow-x-auto">
                        <table className="w-full min-w-[52rem] text-sm">
                            <thead>
                                <tr className="border-b text-left text-muted-foreground">
                                    <th className="py-2">Internal</th>
                                    <th>Supplier invoice</th>
                                    <th>Supplier</th>
                                    <th>Due</th>
                                    <th>Status</th>
                                    <th className="text-right">Total</th>
                                    <th className="text-right">Outstanding</th>
                                </tr>
                            </thead>
                            <tbody>
                                {supplierInvoices.map((invoice) => (
                                    <tr key={invoice.id} className="border-b">
                                        <td className="py-3 font-medium">
                                            <Link
                                                className="underline underline-offset-2"
                                                href={`/purchases/${invoice.id}`}
                                            >
                                                {invoice.number}
                                            </Link>
                                        </td>
                                        <td>
                                            {invoice.supplier_number || '—'}
                                        </td>
                                        <td>
                                            {invoice.supplier.trade_name ||
                                                invoice.supplier.legal_name}
                                        </td>
                                        <td>
                                            {invoice.due_date
                                                ? date(invoice.due_date)
                                                : '—'}
                                        </td>
                                        <td>
                                            <Badge variant="outline">
                                                {invoice.status}
                                            </Badge>
                                        </td>
                                        <td className="text-right">
                                            {money(invoice.amount)}
                                        </td>
                                        <td className="text-right font-medium">
                                            {money(
                                                String(
                                                    Number(invoice.amount) -
                                                        Number(
                                                            invoice.paid_amount,
                                                        ),
                                                ),
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                        {supplierInvoices.length === 0 && (
                            <Empty text="No supplier invoices registered." />
                        )}
                    </section>
                )}
            </div>
        </>
    );
}
function ExpenseDialog({
    categories,
    suppliers,
    installations,
    initialOpen = false,
}: {
    categories: Category[];
    suppliers: Supplier[];
    installations: Installation[];
    initialOpen?: boolean;
}) {
    const [open, setOpen] = useState(initialOpen);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button>
                    <Plus /> New expense
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Register expense</DialogTitle>
                </DialogHeader>
                <Form
                    action="/expenses"
                    method="post"
                    encType="multipart/form-data"
                    onSuccess={() => setOpen(false)}
                    className="grid gap-4"
                >
                    {({ errors, processing }) => (
                        <>
                            {Object.keys(errors).length > 0 && (
                                <div
                                    role="alert"
                                    className="rounded-md border border-destructive/40 bg-destructive/5 p-3 text-sm text-destructive"
                                >
                                    Revisa los campos indicados. El gasto no se
                                    ha registrado todavía.
                                </div>
                            )}
                            <div className="grid gap-4 sm:grid-cols-2">
                                <SelectField
                                    label="Type"
                                    name="type"
                                    error={errors.type}
                                    options={[
                                        ['receipt', 'Receipt / ticket'],
                                        ['invoice', 'Expense invoice'],
                                        ['other', 'Other'],
                                    ]}
                                />
                                <SelectField
                                    label="Category"
                                    name="expense_category_id"
                                    error={errors.expense_category_id}
                                    options={categories.map((c) => [
                                        String(c.id),
                                        c.name,
                                    ])}
                                />
                                <SelectField
                                    label="Supplier (optional)"
                                    name="supplier_id"
                                    error={errors.supplier_id}
                                    options={[
                                        ['none', 'No linked supplier'],
                                        ...suppliers.map((s) => [
                                            String(s.id),
                                            s.trade_name || s.legal_name,
                                        ]),
                                    ]}
                                />
                                <SelectField
                                    label="Installation (optional)"
                                    name="installation_id"
                                    error={errors.installation_id}
                                    options={[
                                        ['none', 'No linked installation'],
                                        ...installations.map((installation) => [
                                            String(installation.id),
                                            installation.project_reference ||
                                                installation.site_name ||
                                                `#${installation.id}`,
                                        ]),
                                    ]}
                                />
                                <Field
                                    label="Merchant"
                                    name="merchant_name"
                                    error={errors.merchant_name}
                                />
                                <Field
                                    label="Document number"
                                    name="document_number"
                                    error={errors.document_number}
                                />
                                <Field
                                    label="Expense date"
                                    name="expense_date"
                                    type="date"
                                    value={today()}
                                    error={errors.expense_date}
                                />
                                <Field
                                    label="Due date"
                                    name="due_date"
                                    type="date"
                                    error={errors.due_date}
                                />
                                <Field
                                    label="Subtotal"
                                    name="subtotal"
                                    type="number"
                                    value="0"
                                    step="0.01"
                                    error={errors.subtotal}
                                />
                                <Field
                                    label="Tax amount"
                                    name="tax_amount"
                                    type="number"
                                    value="0"
                                    step="0.01"
                                    error={errors.tax_amount}
                                />
                                <div className="grid gap-2 sm:col-span-2">
                                    <Label>Receipts or invoices</Label>
                                    <Input
                                        name="attachments[]"
                                        type="file"
                                        multiple
                                        accept=".pdf,.jpg,.jpeg,.png,.webp"
                                    />
                                    <InputError
                                        message={
                                            errors.attachments ||
                                            errors['attachments.0']
                                        }
                                    />
                                </div>
                            </div>
                            <div className="grid gap-2">
                                <Label>Notes</Label>
                                <textarea
                                    name="notes"
                                    className="min-h-20 rounded-md border bg-transparent p-3 text-sm"
                                />
                                <InputError message={errors.notes} />
                                <InputError message={errors.accounting} />
                            </div>
                            <Button disabled={processing}>
                                <FilePlus2 />
                                {processing
                                    ? 'Registering expense…'
                                    : 'Register expense'}
                            </Button>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
function Metric({ label, value }: { label: string; value: number }) {
    return (
        <div className="border-r p-4 last:border-0">
            <div className="text-sm text-muted-foreground">{label}</div>
            <div className="mt-1 text-xl font-semibold">
                {money(String(value))}
            </div>
        </div>
    );
}
function Tab({
    active,
    onClick,
    icon,
    children,
}: {
    active: boolean;
    onClick: () => void;
    icon: React.ReactNode;
    children: React.ReactNode;
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            className={`flex items-center gap-2 border-b-2 px-3 py-2 text-sm [&_svg]:size-4 ${active ? 'border-primary font-medium' : 'border-transparent text-muted-foreground'}`}
        >
            {icon}
            {children}
        </button>
    );
}
function SelectField({
    label,
    name,
    options,
    error,
}: {
    label: string;
    name: string;
    options: string[][];
    error?: string;
}) {
    return (
        <div className="grid gap-2">
            <Label>{label}</Label>
            <Select name={name} defaultValue={options[0]?.[0]}>
                <SelectTrigger>
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    {options.map(([id, text]) => (
                        <SelectItem key={id} value={id}>
                            {text}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
            <InputError message={error} />
        </div>
    );
}
function Field({
    label,
    name,
    type = 'text',
    value,
    step,
    error,
}: {
    label: string;
    name: string;
    type?: string;
    value?: string;
    step?: string;
    error?: string;
}) {
    return (
        <div className="grid gap-2">
            <Label>{label}</Label>
            <Input name={name} type={type} defaultValue={value} step={step} />
            <InputError message={error} />
        </div>
    );
}
function Empty({ text }: { text: string }) {
    return (
        <div className="py-10 text-center text-sm text-muted-foreground">
            {text}
        </div>
    );
}
const money = (v: string) =>
    new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: 'EUR',
    }).format(Number(v));
const date = (v: string) =>
    new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(
        new Date(`${v.slice(0, 10)}T12:00:00`),
    );
const today = () => new Date().toISOString().slice(0, 10);
ExpensesIndex.layout = {
    breadcrumbs: [{ title: 'Expenses', href: '/expenses' }],
};

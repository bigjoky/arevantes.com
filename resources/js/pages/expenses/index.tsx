import { Form, Head, Link, usePage } from '@inertiajs/react';
import {
    FilePlus2,
    Plus,
    ReceiptText,
    Trash2,
    WalletCards,
} from 'lucide-react';
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
type Supplier = {
    id: number;
    legal_name: string;
    trade_name: string | null;
    tax_id: string | null;
};
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
    const [taxLines, setTaxLines] = useState([
        { kind: 'vat', base: 0, rate: 21, deductible: 100 },
    ]);
    const [withholdingRate, setWithholdingRate] = useState(0);
    const subtotal = taxLines
        .filter((line) => line.kind === 'vat')
        .reduce((sum, line) => sum + line.base, 0);
    const taxAmount = taxLines.reduce(
        (sum, line) => sum + (line.base * line.rate) / 100,
        0,
    );
    const withholdingAmount = (subtotal * withholdingRate) / 100;
    const total = subtotal + taxAmount - withholdingAmount;

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button>
                    <Plus /> New expense
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-4xl">
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
                            <input
                                name="currency_code"
                                type="hidden"
                                value="EUR"
                            />
                            <input
                                name="subtotal"
                                type="hidden"
                                value={subtotal.toFixed(2)}
                            />
                            <input
                                name="tax_amount"
                                type="hidden"
                                value={taxAmount.toFixed(2)}
                            />
                            <input
                                name="withholding_amount"
                                type="hidden"
                                value={withholdingAmount.toFixed(2)}
                            />
                            <div className="grid gap-4 sm:grid-cols-2">
                                <SelectField
                                    label="Fiscal document type"
                                    name="type"
                                    error={errors.type}
                                    options={[
                                        ['receipt', 'Receipt / ticket'],
                                        [
                                            'simplified_invoice',
                                            'Simplified invoice',
                                        ],
                                        ['invoice', 'Full invoice'],
                                        ['other', 'Other supporting document'],
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
                                    label="Issuer invoice number"
                                    name="document_number"
                                    error={errors.document_number}
                                />
                                <Field
                                    label="Invoice series (optional)"
                                    name="invoice_series"
                                    error={errors.invoice_series}
                                />
                                <Field
                                    label="Issuer legal name"
                                    name="issuer_legal_name"
                                    error={errors.issuer_legal_name}
                                />
                                <Field
                                    label="Issuer tax ID / VAT number"
                                    name="issuer_tax_id"
                                    error={errors.issuer_tax_id}
                                />
                                <Field
                                    label="Trade name (optional)"
                                    name="merchant_name"
                                    error={errors.merchant_name}
                                />
                                <Field
                                    label="Issue date"
                                    name="expense_date"
                                    type="date"
                                    value={today()}
                                    error={errors.expense_date}
                                />
                                <Field
                                    label="Operation date (if different)"
                                    name="operation_date"
                                    type="date"
                                    error={errors.operation_date}
                                />
                                <Field
                                    label="Due date"
                                    name="due_date"
                                    type="date"
                                    error={errors.due_date}
                                />
                                <SelectField
                                    label="VAT treatment"
                                    name="tax_regime"
                                    error={errors.tax_regime}
                                    options={[
                                        ['standard', 'Standard'],
                                        ['exempt', 'Exempt'],
                                        ['non_subject', 'Not subject to VAT'],
                                        ['reverse_charge', 'Reverse charge'],
                                        ['intra_community', 'Intra-Community'],
                                        ['import', 'Import'],
                                    ]}
                                />
                                <Field
                                    label="Deductible expense (%)"
                                    name="deductible_expense_percent"
                                    type="number"
                                    value="100"
                                    step="0.01"
                                    error={errors.deductible_expense_percent}
                                />
                            </div>

                            <section className="grid gap-3 rounded-lg border p-4">
                                <div className="flex items-center justify-between gap-3">
                                    <div>
                                        <h3 className="font-medium">
                                            Tax breakdown
                                        </h3>
                                        <p className="text-xs text-muted-foreground">
                                            Add one row for each VAT rate shown
                                            on the document.
                                        </p>
                                    </div>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={() =>
                                            setTaxLines((lines) => [
                                                ...lines,
                                                {
                                                    kind: 'vat',
                                                    base: 0,
                                                    rate: 21,
                                                    deductible: 100,
                                                },
                                            ])
                                        }
                                    >
                                        <Plus /> Add tax rate
                                    </Button>
                                </div>
                                {taxLines.map((line, index) => {
                                    const quota = (line.base * line.rate) / 100;

                                    return (
                                        <div
                                            key={index}
                                            className="grid gap-3 border-t pt-3 sm:grid-cols-[1.2fr_1fr_1fr_1fr_1fr_auto] sm:items-end"
                                        >
                                            <SelectField
                                                label="Tax"
                                                name={`tax_lines[${index}][kind]`}
                                                onValueChange={(value) =>
                                                    setTaxLines((lines) =>
                                                        lines.map(
                                                            (
                                                                item,
                                                                itemIndex,
                                                            ) =>
                                                                itemIndex ===
                                                                index
                                                                    ? {
                                                                          ...item,
                                                                          kind: value,
                                                                          deductible:
                                                                              value ===
                                                                              'equivalence_surcharge'
                                                                                  ? 0
                                                                                  : item.deductible,
                                                                      }
                                                                    : item,
                                                        ),
                                                    )
                                                }
                                                options={[
                                                    ['vat', 'VAT'],
                                                    [
                                                        'equivalence_surcharge',
                                                        'Equivalence surcharge',
                                                    ],
                                                ]}
                                            />
                                            <FiscalNumber
                                                label="Taxable base"
                                                name={`tax_lines[${index}][taxable_base]`}
                                                value={line.base}
                                                onChange={(value) =>
                                                    setTaxLines((lines) =>
                                                        lines.map(
                                                            (
                                                                item,
                                                                itemIndex,
                                                            ) =>
                                                                itemIndex ===
                                                                index
                                                                    ? {
                                                                          ...item,
                                                                          base: value,
                                                                      }
                                                                    : item,
                                                        ),
                                                    )
                                                }
                                                error={
                                                    errors[
                                                        `tax_lines.${index}.taxable_base`
                                                    ]
                                                }
                                            />
                                            <FiscalNumber
                                                label="Rate (%)"
                                                name={`tax_lines[${index}][tax_rate]`}
                                                value={line.rate}
                                                onChange={(value) =>
                                                    setTaxLines((lines) =>
                                                        lines.map(
                                                            (
                                                                item,
                                                                itemIndex,
                                                            ) =>
                                                                itemIndex ===
                                                                index
                                                                    ? {
                                                                          ...item,
                                                                          rate: value,
                                                                      }
                                                                    : item,
                                                        ),
                                                    )
                                                }
                                                error={
                                                    errors[
                                                        `tax_lines.${index}.tax_rate`
                                                    ]
                                                }
                                            />
                                            <div className="grid gap-2">
                                                <Label>Tax amount</Label>
                                                <Input
                                                    readOnly
                                                    value={quota.toFixed(2)}
                                                />
                                                <input
                                                    name={`tax_lines[${index}][tax_amount]`}
                                                    type="hidden"
                                                    value={quota.toFixed(2)}
                                                />
                                            </div>
                                            <FiscalNumber
                                                label="Deductible (%)"
                                                name={`tax_lines[${index}][deductible_percent]`}
                                                value={line.deductible}
                                                onChange={(value) =>
                                                    setTaxLines((lines) =>
                                                        lines.map(
                                                            (
                                                                item,
                                                                itemIndex,
                                                            ) =>
                                                                itemIndex ===
                                                                index
                                                                    ? {
                                                                          ...item,
                                                                          deductible:
                                                                              value,
                                                                      }
                                                                    : item,
                                                        ),
                                                    )
                                                }
                                                error={
                                                    errors[
                                                        `tax_lines.${index}.deductible_percent`
                                                    ]
                                                }
                                            />
                                            <Button
                                                type="button"
                                                size="icon"
                                                variant="ghost"
                                                disabled={taxLines.length === 1}
                                                onClick={() =>
                                                    setTaxLines((lines) =>
                                                        lines.filter(
                                                            (_, itemIndex) =>
                                                                itemIndex !==
                                                                index,
                                                        ),
                                                    )
                                                }
                                            >
                                                <Trash2 />
                                            </Button>
                                        </div>
                                    );
                                })}
                            </section>

                            <section className="grid gap-4 rounded-lg border p-4 sm:grid-cols-3">
                                <FiscalNumber
                                    label="Withholding (%)"
                                    name="withholding_rate"
                                    value={withholdingRate}
                                    onChange={setWithholdingRate}
                                    error={errors.withholding_rate}
                                />
                                <div className="grid gap-2 text-sm">
                                    <span className="text-muted-foreground">
                                        Withholding amount
                                    </span>
                                    <strong>
                                        {money(withholdingAmount.toFixed(2))}
                                    </strong>
                                </div>
                                <div className="grid gap-2 text-sm">
                                    <span className="text-muted-foreground">
                                        Document total
                                    </span>
                                    <strong className="text-lg">
                                        {money(total.toFixed(2))}
                                    </strong>
                                </div>
                            </section>

                            <div className="grid gap-4 sm:grid-cols-2">
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
    onValueChange,
}: {
    label: string;
    name: string;
    options: string[][];
    error?: string;
    onValueChange?: (value: string) => void;
}) {
    return (
        <div className="grid gap-2">
            <Label>{label}</Label>
            <Select
                name={name}
                defaultValue={options[0]?.[0]}
                onValueChange={onValueChange}
            >
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
function FiscalNumber({
    label,
    name,
    value,
    onChange,
    error,
}: {
    label: string;
    name: string;
    value: number;
    onChange: (value: number) => void;
    error?: string;
}) {
    return (
        <div className="grid gap-2">
            <Label>{label}</Label>
            <Input
                name={name}
                type="number"
                min="0"
                step="0.01"
                value={value}
                onChange={(event) => onChange(Number(event.target.value))}
            />
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
    new Intl.NumberFormat(document.documentElement.lang, {
        style: 'currency',
        currency: 'EUR',
    }).format(Number(v));
const date = (v: string) =>
    new Intl.DateTimeFormat(document.documentElement.lang, {
        dateStyle: 'medium',
    }).format(new Date(`${v.slice(0, 10)}T12:00:00`));
const today = () =>
    new Intl.DateTimeFormat('en-CA', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
    }).format(new Date());
ExpensesIndex.layout = {
    breadcrumbs: [{ title: 'Expenses', href: '/expenses' }],
};

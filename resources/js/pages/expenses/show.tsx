import { Form, Head, Link, usePage } from '@inertiajs/react';
import { ArrowLeft, Download, Trash2, WalletCards } from 'lucide-react';
import { FormErrorSummary } from '@/components/forms/form-layout';
import { PaymentSourceFields } from '@/components/payment-source-fields';
import type { PaymentSources } from '@/components/payment-source-fields';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useLocale } from '@/hooks/use-locale';
import { translate } from '@/i18n';
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
        source_label: string;
        voided_at: string | null;
        void_reason: string | null;
        voided_by: { name: string } | null;
        reconciliation: { id: number } | null;
        origin_bank_transaction_id: number | null;
    }[];
};
export default function ExpenseShow({
    expense,
    paymentSources,
    canCorrectIssuer,
    canCancelPayments,
    issuerRevision,
    issuerCorrections,
    canEditExpense,
    expenseCorrections,
}: {
    expense: Expense;
    paymentSources: PaymentSources;
    canCorrectIssuer: boolean;
    canCancelPayments: boolean;
    canEditExpense: boolean;
    expenseCorrections: {
        id: number;
        created_at: string;
        user: { name: string } | null;
        new_values: { correction_reason: string };
    }[];
    issuerRevision: string | null;
    issuerCorrections: {
        id: number;
        user: string | null;
        created_at: string;
        before: {
            issuer_legal_name: string | null;
            merchant_name: string | null;
        };
        after: {
            issuer_legal_name: string;
            merchant_name: string | null;
            correction_reason: string;
        };
    }[];
}) {
    const { locale } = useLocale();
    const canManageAttachments = usePage<{
        auth: { abilities: { manageCatalog: boolean } };
    }>().props.auth.abilities.manageCatalog;
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
                            {expense.issuer_legal_name ||
                                expense.supplier?.trade_name ||
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
                {canCorrectIssuer && (
                    <details className="rounded-md border p-4">
                        <summary className="cursor-pointer font-semibold">
                            {translate('Correct supplier names', locale)}
                        </summary>
                        <p className="mt-3 text-sm text-muted-foreground">
                            {translate(
                                'Correct only the names recorded on this expense. Amounts, tax ID, supplier record, payments and accounting stay unchanged.',
                                locale,
                            )}
                        </p>
                        <Form
                            action={`/expenses/${expense.id}/issuer`}
                            method="patch"
                            className="mt-4 grid gap-4"
                        >
                            {({ errors, processing }) => (
                                <>
                                    <FormErrorSummary errors={errors} />
                                    <input
                                        type="hidden"
                                        name="issuer_revision"
                                        value={issuerRevision ?? ''}
                                    />
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <label className="grid gap-2 text-sm font-medium">
                                            {translate(
                                                'Issuer legal name',
                                                locale,
                                            )}
                                            <Input
                                                name="issuer_legal_name"
                                                defaultValue={
                                                    expense.issuer_legal_name ||
                                                    expense.supplier
                                                        ?.legal_name ||
                                                    expense.merchant_name ||
                                                    ''
                                                }
                                                maxLength={255}
                                                required
                                            />
                                        </label>
                                        <label className="grid gap-2 text-sm font-medium">
                                            {translate(
                                                'Trade name (optional)',
                                                locale,
                                            )}
                                            <Input
                                                name="merchant_name"
                                                defaultValue={
                                                    expense.merchant_name ?? ''
                                                }
                                                maxLength={255}
                                            />
                                        </label>
                                    </div>
                                    <label className="grid gap-2 text-sm font-medium">
                                        {translate('Correction reason', locale)}
                                        <textarea
                                            name="correction_reason"
                                            required
                                            maxLength={2000}
                                            rows={2}
                                            className="rounded-md border bg-background p-3"
                                        />
                                    </label>
                                    <Button
                                        className="w-fit"
                                        disabled={processing}
                                    >
                                        {translate(
                                            'Save supplier name correction',
                                            locale,
                                        )}
                                    </Button>
                                </>
                            )}
                        </Form>
                    </details>
                )}
                {canEditExpense && (
                    <Button asChild className="w-fit">
                        <Link href={`/expenses/${expense.id}/edit`}>
                            {translate('Edit expense', locale)}
                        </Link>
                    </Button>
                )}
                {expenseCorrections.length > 0 && (
                    <section className="rounded-md border p-4">
                        <h2 className="font-semibold">
                            {translate('Expense correction history', locale)}
                        </h2>
                        {expenseCorrections.map((item) => (
                            <div key={item.id} className="mt-3 text-sm">
                                <p>
                                    {date(item.created_at)} · {item.user?.name}
                                </p>
                                <p>{item.new_values.correction_reason}</p>
                            </div>
                        ))}
                    </section>
                )}
                {issuerCorrections.length > 0 && (
                    <section className="rounded-md border p-4">
                        <h2 className="font-semibold">
                            {translate(
                                'Supplier name correction history',
                                locale,
                            )}
                        </h2>
                        {issuerCorrections.map((correction) => (
                            <div
                                key={correction.id}
                                className="mt-3 grid gap-1 border-t pt-3 text-sm break-words"
                            >
                                <p>
                                    {correction.user || '—'} ·{' '}
                                    {new Intl.DateTimeFormat(locale, {
                                        dateStyle: 'medium',
                                        timeStyle: 'short',
                                    }).format(new Date(correction.created_at))}
                                </p>
                                <p>
                                    {translate('Issuer legal name', locale)}:{' '}
                                    {correction.before.issuer_legal_name || '—'}{' '}
                                    → {correction.after.issuer_legal_name}
                                </p>
                                <p>
                                    {translate('Trade name (optional)', locale)}
                                    : {correction.before.merchant_name || '—'} →{' '}
                                    {correction.after.merchant_name || '—'}
                                </p>
                                <p>{correction.after.correction_reason}</p>
                            </div>
                        ))}
                    </section>
                )}
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
                                        {canManageAttachments && (
                                            <Form
                                                action={`/expenses/${expense.id}/attachments/${file.id}`}
                                                method="delete"
                                            >
                                                <Button
                                                    size="icon"
                                                    variant="ghost"
                                                >
                                                    <Trash2 />
                                                </Button>
                                            </Form>
                                        )}
                                    </div>
                                </div>
                            ))}
                            {expense.attachments.length === 0 && (
                                <Empty text="No attachments." />
                            )}
                        </div>
                        {canManageAttachments && (
                            <Form
                                action={`/expenses/${expense.id}/attachments`}
                                method="post"
                                resetOnSuccess
                                encType="multipart/form-data"
                                className="mt-4 grid gap-3"
                            >
                                {({ errors, processing }) => (
                                    <>
                                        <FormErrorSummary errors={errors} />
                                        <label htmlFor="expense-attachments">
                                            Add attachments
                                        </label>
                                        <Input
                                            id="expense-attachments"
                                            name="attachments[]"
                                            type="file"
                                            multiple
                                            required
                                            accept=".pdf,.jpg,.jpeg,.png,.webp"
                                        />
                                        <p className="text-xs text-muted-foreground">
                                            Up to 10 files per upload, 10 MB
                                            each.
                                        </p>
                                        <Button disabled={processing}>
                                            Upload attachments
                                        </Button>
                                    </>
                                )}
                            </Form>
                        )}
                    </div>
                    <div>
                        <h2 className="mb-3 font-semibold">Payment history</h2>
                        <div className="border-y">
                            {expense.payments.map((payment) => (
                                <div
                                    key={payment.id}
                                    className="flex flex-col justify-between gap-3 border-b py-3 text-sm last:border-0 sm:flex-row"
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
                                            {payment.source_label
                                                ? ` · ${payment.source_label}`
                                                : ''}
                                            {payment.reference
                                                ? ` · ${payment.reference}`
                                                : ''}
                                        </div>
                                        {payment.voided_at && (
                                            <div className="mt-2 text-xs text-muted-foreground">
                                                <Badge variant="secondary">
                                                    Cancelled payment
                                                </Badge>
                                                <p>
                                                    {date(payment.voided_at)} ·{' '}
                                                    {payment.voided_by?.name}
                                                </p>
                                                <p>{payment.void_reason}</p>
                                            </div>
                                        )}
                                    </div>
                                    {canCancelPayments &&
                                        !payment.voided_at &&
                                        (payment.reconciliation ||
                                        payment.origin_bank_transaction_id ? (
                                            <p className="max-w-sm text-xs text-muted-foreground">
                                                Undo the bank reconciliation
                                                before cancelling this payment.
                                            </p>
                                        ) : (
                                            <details className="w-full sm:max-w-sm">
                                                <summary className="cursor-pointer text-destructive">
                                                    Cancel incorrect payment
                                                </summary>
                                                <Form
                                                    action={`/expenses/${expense.id}/payments/${payment.id}/cancel`}
                                                    method="post"
                                                    className="mt-3 grid gap-3"
                                                >
                                                    {({
                                                        errors,
                                                        processing,
                                                    }) => (
                                                        <>
                                                            <p className="text-xs text-muted-foreground">
                                                                This cancels the
                                                                record, not a
                                                                bank transfer.
                                                                The original
                                                                payment remains
                                                                in history and
                                                                its entry is
                                                                reversed on the
                                                                original date.
                                                                Then register
                                                                the correct
                                                                payment.
                                                            </p>
                                                            <FormErrorSummary
                                                                errors={errors}
                                                            />
                                                            <label className="grid gap-1">
                                                                <span>
                                                                    Cancellation
                                                                    reason
                                                                </span>
                                                                <Input
                                                                    name="reason"
                                                                    required
                                                                    minLength={
                                                                        5
                                                                    }
                                                                    maxLength={
                                                                        2000
                                                                    }
                                                                />
                                                            </label>
                                                            <Button
                                                                variant="destructive"
                                                                disabled={
                                                                    processing
                                                                }
                                                            >
                                                                Confirm
                                                                cancellation
                                                            </Button>
                                                        </>
                                                    )}
                                                </Form>
                                            </details>
                                        ))}
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
                            className="mt-3 grid gap-4 sm:grid-cols-2 xl:grid-cols-5 xl:items-end"
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
                            <PaymentSourceFields sources={paymentSources} />
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

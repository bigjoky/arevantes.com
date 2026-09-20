import { Form, Head, Link } from '@inertiajs/react';
import {
    ArrowLeft,
    BookCheck,
    CreditCard,
    FileDown,
    LockKeyhole,
    Pencil,
    RotateCcw,
    Send,
    Trash2,
} from 'lucide-react';
import HistoricalDocumentDate from '@/components/historical-document-date';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

type Payment = {
    id: number;
    paid_at: string;
    amount: string;
    method: string;
    reference: string | null;
    accounting_entry: { number: string; status: string } | null;
};
type Document = {
    can_issue_invoice: boolean;
    can_prepare_credit_note: boolean;
    id: number;
    type: string;
    number: string;
    status: string;
    issue_date: string;
    due_date: string | null;
    billing_percentage: string | null;
    billing_sequence: number | null;
    billing_label: string | null;
    locked_at: string | null;
    fulfillment_status: string | null;
    billing_status: string | null;
    subtotal: string;
    discount_amount: string;
    tax_amount: string;
    amount: string;
    paid_amount: string;
    notes: string | null;
    customer_snapshot: Record<string, string | null>;
    quote: { id: number; number: string } | null;
    source_document: { id: number; number: string; type: string } | null;
    lines: {
        id: number;
        description: string;
        quantity: string;
        unit_price: string;
        discount_percent: string;
        tax_percent: string;
        total: string;
        product: {
            id: number;
            name: string;
            track_stock: boolean;
            track_serials: boolean;
        } | null;
    }[];
    payments: Payment[];
    deliveries: {
        id: number;
        recipient_email: string;
        status: string;
        sent_at: string | null;
        first_viewed_at: string | null;
        responded_at: string | null;
        revoked_at: string | null;
    }[];
    accounting_entry: { number: string; status: string } | null;
    recurring_service_billing: {
        id: number;
        status: string;
        service_cycle_commitment: {
            id: number;
            maintenance_plan_version: number;
            period_starts_on: string;
            period_ends_on: string;
            maintenance_plan: { id: number; name: string };
        };
    } | null;
};

type Conversion = {
    targets: string[];
    lines: {
        id: number;
        description: string;
        quantity: number;
        allocated_delivery_quantity: number;
        delivered_quantity: number;
        invoiced_quantity: number;
        remaining_delivery_quantity: number;
        remaining_invoice_quantity: number;
    }[];
    children: {
        id: number;
        type: string;
        number: string;
        status: string;
        amount: string;
        issue_date: string;
    }[];
};

type Warehouse = { id: number; code: string; name: string };
export default function SalesShow({
    document,
    warehouses,
    conversion,
}: {
    document: Document;
    warehouses: Warehouse[];
    conversion: Conversion | null;
}) {
    const customer =
        document.customer_snapshot.company_name ||
        `${document.customer_snapshot.first_name || ''} ${document.customer_snapshot.last_name || ''}`.trim();
    const isInvoice = document.type === 'invoice';
    const canIssue =
        document.can_issue_invoice &&
        ['invoice', 'credit_note'].includes(document.type) &&
        document.status === 'draft' &&
        !document.locked_at;
    const hasStockLines = document.lines.some(
        (line) => line.product?.track_stock,
    );
    const outstanding = Math.max(
        0,
        Number(document.amount) - Number(document.paid_amount),
    );

    return (
        <>
            <Head title={document.number} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <header className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <div className="flex items-center gap-2">
                            <h1 className="text-xl font-semibold">
                                {document.number}
                            </h1>
                            <Badge variant="outline">{document.status}</Badge>
                            {document.accounting_entry && (
                                <Badge
                                    variant="secondary"
                                    title="Accounting entry"
                                >
                                    <BookCheck />{' '}
                                    {document.accounting_entry.number}
                                </Badge>
                            )}
                            {document.fulfillment_status && (
                                <Badge variant="outline">
                                    Delivery {document.fulfillment_status}
                                </Badge>
                            )}
                            {document.billing_status && (
                                <Badge variant="outline">
                                    Billing {document.billing_status}
                                </Badge>
                            )}
                            {document.recurring_service_billing ? (
                                <Badge variant="secondary">
                                    Recurring cycle · v
                                    {
                                        document.recurring_service_billing
                                            .service_cycle_commitment
                                            .maintenance_plan_version
                                    }
                                </Badge>
                            ) : null}
                        </div>
                        <p className="text-sm text-muted-foreground capitalize">
                            {document.type.replaceAll('_', ' ')} · {customer}
                        </p>
                        {document.billing_percentage && (
                            <p className="text-xs text-muted-foreground">
                                {document.billing_label ||
                                    `Billing milestone ${document.billing_sequence}`}{' '}
                                · {Number(document.billing_percentage)}%
                            </p>
                        )}
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button variant="outline" asChild>
                            <Link href="/sales">
                                <ArrowLeft /> Sales
                            </Link>
                        </Button>
                        <Button asChild>
                            <a
                                href={`/sales/${document.id}/pdf`}
                                target="_blank"
                                rel="noreferrer"
                            >
                                <FileDown /> PDF
                            </a>
                        </Button>
                        {document.status === 'draft' &&
                            !document.quote &&
                            !document.source_document &&
                            !document.recurring_service_billing &&
                            !document.locked_at && (
                                <Button variant="outline" asChild>
                                    <Link href={`/sales/${document.id}/edit`}>
                                        <Pencil /> Edit
                                    </Link>
                                </Button>
                            )}
                    </div>
                </header>
                <section className="grid gap-4 border-y py-4 text-sm sm:grid-cols-2 lg:grid-cols-4">
                    <Info label="Customer" value={customer} />
                    <Info
                        label="Issue date"
                        value={date(document.issue_date)}
                    />
                    <Info
                        label="Due date"
                        value={
                            document.due_date ? date(document.due_date) : '—'
                        }
                    />
                    <Info
                        label="Origin"
                        value={
                            document.quote?.number ||
                            document.source_document?.number ||
                            document.recurring_service_billing
                                ?.service_cycle_commitment.maintenance_plan
                                .name ||
                            '—'
                        }
                    />
                </section>
                <section className="overflow-x-auto">
                    <table className="w-full min-w-[48rem] text-sm">
                        <thead>
                            <tr className="border-b text-left text-muted-foreground">
                                <th className="py-2">Description</th>
                                <th>Quantity</th>
                                <th>Price</th>
                                <th>Discount</th>
                                <th>Tax</th>
                                <th className="text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            {document.lines.map((line) => (
                                <tr key={line.id} className="border-b">
                                    <td className="py-3 font-medium">
                                        {line.description}
                                    </td>
                                    <td>{Number(line.quantity)}</td>
                                    <td>{money(line.unit_price)}</td>
                                    <td>{line.discount_percent}%</td>
                                    <td>{line.tax_percent}%</td>
                                    <td className="text-right font-medium">
                                        {money(line.total)}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </section>
                <section className="ml-auto w-full max-w-sm border-t pt-4 text-sm">
                    <Total label="Subtotal" value={document.subtotal} />
                    <Total
                        label="Discount"
                        value={`-${document.discount_amount}`}
                    />
                    <Total label="Tax" value={document.tax_amount} />
                    <Total label="Total" value={document.amount} strong />
                    {isInvoice && (
                        <>
                            <Total label="Paid" value={document.paid_amount} />
                            <Total
                                label="Outstanding"
                                value={String(outstanding)}
                                strong
                            />
                        </>
                    )}
                </section>
                {conversion && (
                    <ConversionProgress
                        document={document}
                        conversion={conversion}
                    />
                )}
                <section className="grid gap-6 border-t pt-5 lg:grid-cols-[minmax(20rem,.7fr)_minmax(0,1.3fr)]">
                    <Form
                        action={`/sales/${document.id}/send`}
                        method="post"
                        className="grid content-start gap-3"
                    >
                        <div>
                            <h2 className="font-semibold">Send document</h2>
                            <p className="text-sm text-muted-foreground">
                                Email the PDF and secure viewing link.
                            </p>
                        </div>
                        <div className="grid gap-2">
                            <label className="text-sm font-medium">
                                Recipient email
                            </label>
                            <Input
                                name="recipient_email"
                                type="email"
                                required
                                defaultValue={
                                    document.customer_snapshot.billing_email ||
                                    document.customer_snapshot.email ||
                                    ''
                                }
                            />
                        </div>
                        <Button
                            className="w-fit"
                            disabled={
                                document.type === 'invoice' &&
                                !document.locked_at
                            }
                        >
                            <Send /> Send document
                        </Button>
                    </Form>
                    <div>
                        <h2 className="mb-3 font-semibold">Delivery history</h2>
                        <div className="border-y">
                            {document.deliveries.map((delivery) => (
                                <div
                                    key={delivery.id}
                                    className="flex items-center justify-between gap-3 border-b py-3 text-sm last:border-0"
                                >
                                    <div>
                                        <div className="font-medium">
                                            {delivery.recipient_email}
                                        </div>
                                        <div className="text-xs text-muted-foreground">
                                            {delivery.sent_at
                                                ? date(delivery.sent_at)
                                                : 'Not sent'}
                                            {delivery.first_viewed_at
                                                ? ' · viewed'
                                                : ''}
                                        </div>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <Badge variant="outline">
                                            {delivery.status}
                                        </Badge>
                                        {!delivery.revoked_at &&
                                            ![
                                                'revoked',
                                                'superseded',
                                                'failed',
                                            ].includes(delivery.status) && (
                                                <Form
                                                    action={`/document-deliveries/${delivery.id}`}
                                                    method="delete"
                                                >
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                    >
                                                        Revoke
                                                    </Button>
                                                </Form>
                                            )}
                                    </div>
                                </div>
                            ))}
                            {document.deliveries.length === 0 && (
                                <div className="py-8 text-center text-sm text-muted-foreground">
                                    Not sent yet.
                                </div>
                            )}
                        </div>
                    </div>
                </section>

                {canIssue && (
                    <section className="border-t pt-5">
                        <h2 className="font-semibold">Issue document</h2>
                        <p className="mb-3 text-sm text-muted-foreground">
                            Assign the final fiscal number and lock its
                            financial content.
                        </p>
                        <Form
                            action={`/sales/${document.id}/issue`}
                            method="post"
                            className="grid gap-4 sm:grid-cols-[12rem_12rem_auto] sm:items-end"
                        >
                            <Field
                                label="Issue date"
                                name="issue_date"
                                type="date"
                                value={document.issue_date.slice(0, 10)}
                            />
                            {isInvoice && (
                                <Field
                                    label="Due date"
                                    name="due_date"
                                    type="date"
                                    value={document.due_date?.slice(0, 10)}
                                />
                            )}
                            <Button>
                                <LockKeyhole /> Issue and lock
                            </Button>
                        </Form>
                    </section>
                )}

                {document.type === 'delivery_note' &&
                    document.status === 'draft' && (
                        <section className="border-t pt-5">
                            <h2 className="font-semibold">
                                {hasStockLines
                                    ? 'Dispatch from warehouse'
                                    : 'Confirm delivery'}
                            </h2>
                            <p className="mb-3 text-sm text-muted-foreground">
                                {hasStockLines
                                    ? 'Post the stock issue and assign serialized equipment to the linked installation.'
                                    : 'Confirm that the services or non-stock items have been delivered.'}
                            </p>
                            <Form
                                action={`/sales/${document.id}/dispatch`}
                                method="post"
                                className="grid gap-4"
                            >
                                {hasStockLines && (
                                    <div className="max-w-sm">
                                        <label className="mb-2 block text-sm font-medium">
                                            Warehouse
                                        </label>
                                        <select
                                            name="warehouse_id"
                                            className="h-9 w-full rounded-md border bg-background px-3 text-sm"
                                            required
                                        >
                                            {warehouses.map((warehouse) => (
                                                <option
                                                    key={warehouse.id}
                                                    value={warehouse.id}
                                                >
                                                    {warehouse.name} (
                                                    {warehouse.code})
                                                </option>
                                            ))}
                                        </select>
                                    </div>
                                )}
                                {document.lines
                                    .filter(
                                        (line) =>
                                            line.product?.track_stock &&
                                            line.product.track_serials,
                                    )
                                    .map((line) => (
                                        <div
                                            key={line.id}
                                            className="grid max-w-xl gap-2"
                                        >
                                            <label className="text-sm font-medium">
                                                Serials for {line.product?.name}{' '}
                                                ({Number(line.quantity)}{' '}
                                                required)
                                            </label>
                                            <Input
                                                name={`serials[${line.product?.id}]`}
                                                placeholder="Comma-separated serial numbers"
                                                required
                                            />
                                        </div>
                                    ))}
                                <Button className="w-fit">
                                    <ArrowLeft className="rotate-180" />{' '}
                                    {hasStockLines
                                        ? 'Dispatch delivery note'
                                        : 'Mark as delivered'}
                                </Button>
                            </Form>
                        </section>
                    )}

                {document.can_prepare_credit_note &&
                    isInvoice &&
                    document.locked_at && (
                        <section className="grid gap-8 border-t pt-5 xl:grid-cols-[minmax(20rem,.7fr)_minmax(0,1.3fr)]">
                            <div>
                                <h2 className="font-semibold">
                                    Register payment
                                </h2>
                                <p className="mb-3 text-sm text-muted-foreground">
                                    Outstanding balance:{' '}
                                    {money(String(outstanding))}
                                </p>
                                {outstanding > 0 ? (
                                    <Form
                                        action={`/sales/${document.id}/payments`}
                                        method="post"
                                        resetOnSuccess
                                        className="grid gap-4"
                                    >
                                        <div className="grid gap-4 sm:grid-cols-2">
                                            <Field
                                                label="Payment date"
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
                                                    defaultValue="bank_transfer"
                                                    className="h-9 rounded-md border bg-background px-3 text-sm"
                                                >
                                                    <option value="bank_transfer">
                                                        Bank transfer
                                                    </option>
                                                    <option value="card">
                                                        Card
                                                    </option>
                                                    <option value="cash">
                                                        Cash
                                                    </option>
                                                    <option value="direct_debit">
                                                        Direct debit
                                                    </option>
                                                    <option value="other">
                                                        Other
                                                    </option>
                                                </select>
                                            </div>
                                            <Field
                                                label="Reference"
                                                name="reference"
                                            />
                                        </div>
                                        <Button>
                                            <CreditCard /> Register payment
                                        </Button>
                                    </Form>
                                ) : (
                                    <p className="text-sm font-medium text-green-700 dark:text-green-400">
                                        Invoice paid in full.
                                    </p>
                                )}
                            </div>
                            <div>
                                <h2 className="mb-3 font-semibold">
                                    Payment history
                                </h2>
                                <div className="border-y">
                                    {document.payments.map((payment) => (
                                        <div
                                            key={payment.id}
                                            className="flex items-center justify-between gap-4 border-b py-3 text-sm last:border-0"
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
                                                {payment.accounting_entry && (
                                                    <div className="mt-1 flex items-center gap-1 text-xs text-muted-foreground">
                                                        <BookCheck className="size-3" />
                                                        {
                                                            payment
                                                                .accounting_entry
                                                                .number
                                                        }
                                                    </div>
                                                )}
                                            </div>
                                            {!payment.accounting_entry && (
                                                <Form
                                                    action={`/sales/${document.id}/payments/${payment.id}`}
                                                    method="delete"
                                                    onBefore={() =>
                                                        confirm(
                                                            'Remove this payment?',
                                                        )
                                                    }
                                                >
                                                    <Button
                                                        size="icon"
                                                        variant="ghost"
                                                        title="Remove payment"
                                                    >
                                                        <Trash2 />
                                                    </Button>
                                                </Form>
                                            )}
                                        </div>
                                    ))}
                                    {document.payments.length === 0 && (
                                        <div className="py-8 text-center text-sm text-muted-foreground">
                                            No payments registered.
                                        </div>
                                    )}
                                </div>
                            </div>
                        </section>
                    )}
                {document.status === 'draft' &&
                    !conversion?.children.length && (
                        <section className="flex justify-end border-t pt-5">
                            <Form
                                action={`/sales/${document.id}`}
                                method="delete"
                                onBefore={() =>
                                    confirm('Delete this draft document?')
                                }
                            >
                                <Button variant="destructive">
                                    <Trash2 /> Delete draft
                                </Button>
                            </Form>
                        </section>
                    )}
                {isInvoice && document.locked_at && (
                    <section className="flex justify-end border-t pt-5">
                        <Form
                            action={`/sales/${document.id}/credit-note`}
                            method="post"
                        >
                            <Button variant="outline">
                                <RotateCcw /> Create credit note
                            </Button>
                        </Form>
                    </section>
                )}
            </div>
        </>
    );
}

function ConversionProgress({
    document,
    conversion,
}: {
    document: Document;
    conversion: Conversion;
}) {
    return (
        <section className="border-t pt-5">
            <div className="mb-4">
                <h2 className="font-semibold">Document progress</h2>
                <p className="text-sm text-muted-foreground">
                    Create deliveries and invoices for all or part of each line.
                </p>
            </div>
            <div className="overflow-x-auto">
                <table className="w-full min-w-[48rem] text-sm">
                    <thead>
                        <tr className="border-b text-left text-muted-foreground">
                            <th className="py-2">Description</th>
                            <th>
                                {document.type === 'order'
                                    ? 'Ordered'
                                    : 'Quantity'}
                            </th>
                            {document.type === 'order' && <th>Allocated</th>}
                            {document.type === 'order' && <th>Delivered</th>}
                            <th>Invoiced</th>
                            {document.type === 'order' && <th>To allocate</th>}
                            <th>To invoice</th>
                        </tr>
                    </thead>
                    <tbody>
                        {conversion.lines.map((line) => (
                            <tr key={line.id} className="border-b">
                                <td className="py-3 font-medium">
                                    {line.description}
                                </td>
                                <td>{quantity(line.quantity)}</td>
                                {document.type === 'order' && (
                                    <td>
                                        {quantity(
                                            line.allocated_delivery_quantity,
                                        )}
                                    </td>
                                )}
                                {document.type === 'order' && (
                                    <td>{quantity(line.delivered_quantity)}</td>
                                )}
                                <td>{quantity(line.invoiced_quantity)}</td>
                                {document.type === 'order' && (
                                    <td>
                                        {quantity(
                                            line.remaining_delivery_quantity,
                                        )}
                                    </td>
                                )}
                                <td>
                                    {quantity(line.remaining_invoice_quantity)}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            <div className="grid gap-6 pt-5 xl:grid-cols-2">
                {conversion.targets.map((target) => {
                    const isDelivery = target === 'delivery_note';
                    const isBlocked =
                        document.type === 'delivery_note' &&
                        target === 'invoice' &&
                        document.status !== 'delivered';
                    const remainingKey = isDelivery
                        ? 'remaining_delivery_quantity'
                        : 'remaining_invoice_quantity';
                    const hasRemaining = conversion.lines.some(
                        (line) => line[remainingKey] > 0,
                    );

                    return (
                        <Form
                            key={target}
                            action={`/sales/${document.id}/convert`}
                            method="post"
                            className="grid content-start gap-3 border-t pt-4"
                        >
                            <input type="hidden" name="type" value={target} />
                            <HistoricalDocumentDate />
                            <div>
                                <h3 className="font-medium">
                                    {isDelivery
                                        ? 'Create delivery note'
                                        : 'Create invoice'}
                                </h3>
                                {isBlocked && (
                                    <p className="text-sm text-muted-foreground">
                                        Mark this delivery note as delivered
                                        before invoicing it.
                                    </p>
                                )}
                            </div>
                            {conversion.lines.map((line) => (
                                <div
                                    key={line.id}
                                    className="grid grid-cols-[minmax(0,1fr)_7rem] items-center gap-3"
                                >
                                    <label
                                        className="truncate text-sm"
                                        htmlFor={`${target}-${line.id}`}
                                    >
                                        {line.description}
                                    </label>
                                    <Input
                                        id={`${target}-${line.id}`}
                                        name={`quantities[${line.id}]`}
                                        type="number"
                                        min="0"
                                        max={line[remainingKey]}
                                        step="0.001"
                                        defaultValue={line[remainingKey]}
                                        disabled={isBlocked}
                                    />
                                </div>
                            ))}
                            <Button
                                className="w-fit"
                                disabled={isBlocked || !hasRemaining}
                            >
                                {isDelivery
                                    ? 'Create delivery note'
                                    : 'Create invoice'}
                            </Button>
                        </Form>
                    );
                })}
            </div>
            {conversion.children.length > 0 && (
                <div className="mt-6 border-t pt-4">
                    <h3 className="mb-2 font-medium">Derived documents</h3>
                    <div className="divide-y border-y">
                        {conversion.children.map((child) => (
                            <Link
                                key={child.id}
                                href={`/sales/${child.id}`}
                                className="flex items-center justify-between gap-4 py-3 text-sm hover:text-primary"
                            >
                                <span>
                                    <span className="font-medium">
                                        {child.number}
                                    </span>{' '}
                                    <span className="text-muted-foreground capitalize">
                                        {child.type.replaceAll('_', ' ')} ·{' '}
                                        {child.status}
                                    </span>
                                </span>
                                <span className="font-medium">
                                    {money(child.amount)}
                                </span>
                            </Link>
                        ))}
                    </div>
                </div>
            )}
        </section>
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
    label: string;
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
    type = 'text',
    value,
    step,
}: {
    label: string;
    name: string;
    type?: string;
    value?: string;
    step?: string;
}) {
    return (
        <div className="grid gap-2">
            <label className="text-sm font-medium">{label}</label>
            <input
                className="h-9 rounded-md border bg-transparent px-3 text-sm"
                name={name}
                type={type}
                defaultValue={value}
                step={step}
                required={name !== 'reference' && name !== 'due_date'}
            />
        </div>
    );
}
const money = (value: string) =>
    new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: 'EUR',
    }).format(Number(value));
const date = (value: string) =>
    new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(
        new Date(`${value.slice(0, 10)}T12:00:00`),
    );
const today = () => new Date().toISOString().slice(0, 10);
const quantity = (value: number) =>
    new Intl.NumberFormat(undefined, { maximumFractionDigits: 3 }).format(
        value,
    );
SalesShow.layout = {
    breadcrumbs: [
        { title: 'Sales', href: '/sales' },
        { title: 'Document', href: '#' },
    ],
};

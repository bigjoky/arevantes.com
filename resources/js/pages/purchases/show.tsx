import { Form, Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    CircleAlert,
    CalendarClock,
    Download,
    FileInput,
    FileSearch,
    PackageCheck,
    RefreshCw,
    RotateCcw,
    ShieldCheck,
    Send,
    Trash2,
    Upload,
    WalletCards,
} from 'lucide-react';
import { PaymentSourceFields } from '@/components/payment-source-fields';
import type { PaymentSources } from '@/components/payment-source-fields';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
type Warehouse = { id: number; code: string; name: string };
type SupplierIdentity = {
    legal_name?: string | null;
    trade_name?: string | null;
    tax_id?: string | null;
    email?: string | null;
    phone?: string | null;
    address_line_1?: string | null;
    address_line_2?: string | null;
    postal_code?: string | null;
    locality?: string | null;
    city?: string | null;
    province?: string | null;
    country?: string | null;
    purchasing_contact?: {
        name?: string | null;
        position?: string | null;
        email?: string | null;
        phone?: string | null;
    } | null;
};
type Line = {
    id: number;
    description: string;
    quantity: string;
    received_quantity: string;
    returned_quantity: string;
    unit_price: string;
    tax_percent: string;
    total: string;
    product: {
        id: number;
        name: string;
        sku: string | null;
        track_stock: boolean;
        track_serials: boolean;
    };
};
type Document = {
    id: number;
    type: string;
    number: string;
    supplier_number: string | null;
    status: string;
    matching_status: string | null;
    invoicing_status: string | null;
    match_variance_amount: string;
    approved_at: string | null;
    approval_notes: string | null;
    procurement_batch_id: string | null;
    issue_date: string;
    due_date: string | null;
    expected_delivery_date: string | null;
    supplier_confirmed_delivery_date: string | null;
    supplier_confirmed_at: string | null;
    supplier_confirmation_reference: string | null;
    last_followed_up_at: string | null;
    last_follow_up_note: string | null;
    sent_at: string | null;
    created_by: number | null;
    subtotal: string;
    tax_amount: string;
    shipping_cost: string;
    amount: string;
    paid_amount: string;
    notes: string | null;
    supplier: {
        legal_name: string;
        trade_name: string | null;
        email: string | null;
    };
    assignee: { id: number; name: string } | null;
    source_document: {
        id: number;
        number: string;
        type: string;
        shipping_cost: string;
    } | null;
    derived_documents: {
        id: number;
        number: string;
        type: string;
        status: string;
        matching_status: string | null;
        amount: string;
        issue_date: string;
        supplier_number: string | null;
    }[];
    approver: { id: number; name: string } | null;
    sales_order: { id: number; number: string } | null;
    destination_warehouse: Warehouse | null;
    lines: Line[];
    receipts: {
        id: number;
        number: string;
        supplier_delivery_note: string | null;
        received_at: string;
        movement: { number: string };
    }[];
    quality_incidents: {
        id: number;
        rejected_quantity: string;
        reason: string;
        severity: string;
        notes: string | null;
        status: string;
        resolution: string | null;
        resolution_notes: string | null;
        resolved_at: string | null;
        product: { id: number; sku: string | null; name: string };
        warehouse: Warehouse;
        reporter: { id: number; name: string } | null;
        resolver: { id: number; name: string } | null;
    }[];
    returns: {
        id: number;
        number: string;
        supplier_return_number: string | null;
        returned_at: string;
        movement: { number: string };
    }[];
    payments: {
        id: number;
        paid_at: string;
        amount: string;
        method: string;
        reference: string | null;
        source_label: string;
    }[];
    attachment: {
        id: number;
        original_name: string;
        mime_type: string;
        size: number;
        extraction_status: string;
        extracted_data: Record<string, string | number> | null;
        extraction_error: string | null;
        extracted_at: string | null;
        verified_at: string | null;
        verifier: { id: number; name: string } | null;
    } | null;
    events: {
        id: number;
        event: string;
        from_status: string | null;
        to_status: string | null;
        metadata: Record<string, string | number | null> | null;
        occurred_at: string;
        user: { id: number; name: string } | null;
    }[];
    order_approvals: {
        id: number;
        status: 'pending' | 'approved' | 'rejected' | 'cancelled';
        reason: string;
        notes: string | null;
        requested_at: string;
        decided_at: string | null;
        rule: { id: number; name: string } | null;
        requester: { id: number; name: string } | null;
        decider: { id: number; name: string } | null;
    }[];
};
type MatchDetail = {
    line_id: number;
    description: string;
    ordered_quantity: number;
    received_quantity: number;
    invoiced_quantity: number;
    ordered_unit_price: number;
    invoiced_unit_price: number;
    quantity_variance: number;
    price_variance: number;
};
type OrderProgress = {
    line_id: number;
    ordered_quantity: number;
    received_quantity: number;
    returned_quantity: number;
    available_to_invoice: number;
    invoiced_quantity: number;
    pending_quantity: number;
    over_invoiced_quantity: number;
};
type ShippingProgress = {
    ordered_cost: number;
    invoiced_cost: number;
    pending_cost: number;
};
type CreditProgress = {
    line_id: number;
    description: string;
    invoiced_quantity: number;
    credited_quantity: number;
    creditable_quantity: number;
    unit_price: number;
};
type ExtractionComparison = {
    field: string;
    label: string;
    extracted: string | number | null;
    registered: string | number;
    type: 'text' | 'amount' | 'date';
    status: 'matched' | 'different' | 'missing';
};
export default function PurchaseShow({
    document,
    supplierIdentity,
    warehouses,
    matchDetails,
    orderSummary,
    creditSummary,
    creditedAmount,
    creditableShipping,
    extractionComparison,
    paymentSources,
}: {
    document: Document;
    supplierIdentity: SupplierIdentity;
    warehouses: Warehouse[];
    matchDetails: MatchDetail[];
    orderSummary: {
        status: string;
        lines: OrderProgress[];
        shipping: ShippingProgress;
    } | null;
    creditSummary: CreditProgress[];
    creditedAmount: number;
    creditableShipping: number;
    extractionComparison: ExtractionComparison[];
    paymentSources: PaymentSources;
}) {
    const { user, abilities } = usePage<{
        auth: {
            user: { id: number; role: string };
            abilities: {
                managePurchases: boolean;
                receivePurchases: boolean;
                approvePurchaseInvoices: boolean;
                approvePurchaseOrders: boolean;
                managePurchasePayments: boolean;
            };
        };
    }>().props.auth;
    const canReceive =
        document.type === 'purchase_order' &&
        ['ordered', 'partially_received'].includes(document.status) &&
        abilities.receivePurchases;
    const supplierInvoices = document.derived_documents.filter(
        (item) => item.type === 'supplier_invoice',
    );
    const supplierCredits = document.derived_documents.filter(
        (item) => item.type === 'supplier_credit_note',
    );
    const progress = new Map(
        (orderSummary?.lines || []).map((line) => [line.line_id, line]),
    );
    const hasPendingInvoice =
        orderSummary?.lines.some((line) => line.pending_quantity > 0.0005) ||
        (orderSummary?.shipping.pending_cost ?? 0) > 0.005 ||
        false;
    const hasReturnableStock = document.lines.some(
        (line) =>
            line.product.track_stock &&
            Number(line.received_quantity) - Number(line.returned_quantity) > 0,
    );
    const hasCreditableLines =
        creditSummary.some((line) => line.creditable_quantity > 0.0005) ||
        creditableShipping > 0.005;
    const outstandingAmount = Math.max(
        0,
        Number(document.amount) - creditedAmount - Number(document.paid_amount),
    );

    return (
        <>
            <Head title={document.number} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <header className="flex items-start justify-between gap-3">
                    <div>
                        <div className="flex items-center gap-2">
                            <h1 className="text-xl font-semibold">
                                {document.number}
                            </h1>
                            <Badge variant="outline">{document.status}</Badge>
                            {document.type === 'purchase_order' &&
                                orderSummary && (
                                    <Badge variant="secondary">
                                        {orderSummary.status.replaceAll(
                                            '_',
                                            ' ',
                                        )}
                                    </Badge>
                                )}
                        </div>
                        <p className="text-sm text-muted-foreground capitalize">
                            {document.type.replaceAll('_', ' ')} ·{' '}
                            {supplierIdentity.trade_name ||
                                supplierIdentity.legal_name ||
                                document.supplier.trade_name ||
                                document.supplier.legal_name}
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {['purchase_rfq', 'purchase_order'].includes(
                            document.type,
                        ) && (
                            <Button variant="outline" asChild>
                                <a
                                    href={`/purchases/${document.id}/pdf`}
                                    target="_blank"
                                    rel="noreferrer"
                                >
                                    <Download /> PDF
                                </a>
                            </Button>
                        )}
                        {document.procurement_batch_id && (
                            <Button variant="outline" asChild>
                                <Link
                                    href={`/purchase-comparisons/${document.procurement_batch_id}`}
                                >
                                    Compare offers
                                </Link>
                            </Button>
                        )}
                        <Button variant="outline" asChild>
                            <Link href="/purchases">
                                <ArrowLeft /> Purchases
                            </Link>
                        </Button>
                    </div>
                </header>
                {document.type === 'purchase_order' &&
                    ['pending_approval', 'approval_rejected'].includes(
                        document.status,
                    ) && (
                        <section
                            className={`border p-4 ${document.status === 'approval_rejected' ? 'border-red-300 bg-red-50 dark:bg-red-950/20' : 'border-amber-300 bg-amber-50 dark:bg-amber-950/20'}`}
                        >
                            <div className="flex items-start gap-3">
                                <ShieldCheck className="mt-0.5 size-5" />
                                <div className="flex-1">
                                    <h2 className="font-semibold">
                                        {document.status === 'approval_rejected'
                                            ? 'Order rejected'
                                            : 'Approval required'}
                                    </h2>
                                    <p className="mt-1 text-sm text-muted-foreground">
                                        This order cannot be sent, received or
                                        invoiced until every requirement is
                                        approved by a different authorised user.
                                    </p>
                                    <div className="mt-3 space-y-2 text-sm">
                                        {document.order_approvals.map(
                                            (approval) => (
                                                <div
                                                    key={approval.id}
                                                    className="flex flex-wrap items-center justify-between gap-2 border bg-background/60 p-2"
                                                >
                                                    <span>
                                                        {approval.reason}
                                                    </span>
                                                    <span className="text-muted-foreground capitalize">
                                                        {approval.status}
                                                        {approval.decider
                                                            ? ` · ${approval.decider.name}`
                                                            : ''}
                                                    </span>
                                                </div>
                                            ),
                                        )}
                                    </div>
                                    {abilities.approvePurchaseOrders &&
                                        document.status ===
                                            'pending_approval' &&
                                        (document.created_by !== user.id ||
                                            user.role === 'admin') && (
                                            <Form
                                                action={`/purchases/${document.id}/approve`}
                                                method="post"
                                                className="mt-3 flex max-w-2xl gap-2"
                                            >
                                                <Input
                                                    name="approval_notes"
                                                    placeholder="Approval rationale"
                                                    required
                                                />
                                                <Button>
                                                    <ShieldCheck /> Approve next
                                                </Button>
                                                <Button
                                                    name="decision"
                                                    value="rejected"
                                                    variant="destructive"
                                                >
                                                    Reject
                                                </Button>
                                            </Form>
                                        )}
                                </div>
                            </div>
                        </section>
                    )}
                <section className="grid gap-4 border-y py-4 text-sm sm:grid-cols-2 lg:grid-cols-6">
                    <Info
                        label="Supplier"
                        value={
                            supplierIdentity.legal_name ||
                            document.supplier.legal_name
                        }
                    />
                    <Info
                        label="Supplier tax ID"
                        value={supplierIdentity.tax_id || '—'}
                    />
                    {document.type === 'purchase_order' && (
                        <Info
                            label="Action owner"
                            value={document.assignee?.name || 'Unassigned'}
                        />
                    )}
                    <Info
                        label="Issue date"
                        value={date(document.issue_date)}
                    />
                    <Info
                        label="Requested delivery"
                        value={
                            document.expected_delivery_date
                                ? date(document.expected_delivery_date)
                                : '—'
                        }
                    />
                    {document.type === 'purchase_order' && (
                        <InfoLink
                            label="Supplier ETA"
                            value={
                                document.supplier_confirmed_delivery_date
                                    ? date(
                                          document.supplier_confirmed_delivery_date,
                                      )
                                    : 'Unconfirmed'
                            }
                            href="/supply-control"
                        />
                    )}
                    <Info
                        label="Supplier number"
                        value={document.supplier_number || '—'}
                    />
                    <InfoLink
                        label="Source document"
                        value={document.source_document?.number || '—'}
                        href={
                            document.source_document
                                ? `/purchases/${document.source_document.id}`
                                : null
                        }
                    />
                    <InfoLink
                        label="Sales order"
                        value={document.sales_order?.number || '—'}
                        href={
                            document.sales_order
                                ? `/sales/${document.sales_order.id}`
                                : null
                        }
                    />
                    <Info
                        label="Destination"
                        value={
                            document.destination_warehouse
                                ? `${document.destination_warehouse.name} (${document.destination_warehouse.code})`
                                : '—'
                        }
                    />
                </section>
                {abilities.managePurchases &&
                    ['purchase_rfq', 'purchase_order'].includes(
                        document.type,
                    ) &&
                    !['cancelled', 'pending_approval'].includes(
                        document.status,
                    ) && (
                        <section className="grid gap-5 border-y py-5 lg:grid-cols-2">
                            <Form
                                action={`/purchases/${document.id}/send`}
                                method="post"
                                className="grid gap-3"
                            >
                                <div>
                                    <h2 className="font-semibold">
                                        Send to supplier
                                    </h2>
                                    <p className="text-sm text-muted-foreground">
                                        Email this document with its PDF
                                        attached.
                                    </p>
                                </div>
                                <Field
                                    label="Recipient email"
                                    name="recipient_email"
                                    type="email"
                                    value={document.supplier.email || ''}
                                />
                                <textarea
                                    name="message"
                                    rows={3}
                                    className="border bg-transparent p-3 text-sm"
                                    placeholder="Optional message"
                                />
                                <Button className="justify-self-start">
                                    <Send /> Send document
                                </Button>
                            </Form>
                            {document.type === 'purchase_order' &&
                                !['closed'].includes(document.status) && (
                                    <Form
                                        action={`/purchases/${document.id}/schedule`}
                                        method="patch"
                                        className="grid content-start gap-3"
                                    >
                                        <div>
                                            <h2 className="font-semibold">
                                                Requested delivery
                                            </h2>
                                            <p className="text-sm text-muted-foreground">
                                                Set the internal target sent to
                                                the supplier. Record their
                                                confirmation in Order control.
                                            </p>
                                        </div>
                                        <Field
                                            label="Requested delivery"
                                            name="expected_delivery_date"
                                            type="date"
                                            value={
                                                document.expected_delivery_date ||
                                                ''
                                            }
                                        />
                                        <Button
                                            variant="outline"
                                            className="justify-self-start"
                                        >
                                            <CalendarClock /> Update date
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            className="justify-self-start"
                                            asChild
                                        >
                                            <Link href="/supply-control">
                                                Open order control
                                            </Link>
                                        </Button>
                                    </Form>
                                )}
                        </section>
                    )}
                {abilities.managePurchases &&
                    ['purchase_rfq', 'purchase_order'].includes(
                        document.type,
                    ) && (
                        <section className="flex flex-wrap items-end gap-4 border-b pb-5">
                            {document.status === 'closed' ? (
                                <Form
                                    action={`/purchases/${document.id}/reopen`}
                                    method="post"
                                >
                                    <Button variant="outline">
                                        <RefreshCw /> Reopen order
                                    </Button>
                                </Form>
                            ) : (
                                <>
                                    {document.type === 'purchase_order' &&
                                        [
                                            'ordered',
                                            'partially_received',
                                            'received',
                                        ].includes(document.status) && (
                                            <Form
                                                action={`/purchases/${document.id}/close`}
                                                method="post"
                                                className="flex flex-wrap items-end gap-2"
                                            >
                                                <Field
                                                    label="Closing reason"
                                                    name="reason"
                                                />
                                                <Button variant="outline">
                                                    Close order
                                                </Button>
                                            </Form>
                                        )}
                                    {![
                                        'cancelled',
                                        'accepted',
                                        'rejected',
                                    ].includes(document.status) && (
                                        <Form
                                            action={`/purchases/${document.id}/cancel`}
                                            method="post"
                                            className="flex flex-wrap items-end gap-2"
                                        >
                                            <Field
                                                label="Cancellation reason"
                                                name="reason"
                                            />
                                            <Button variant="destructive">
                                                Cancel
                                            </Button>
                                        </Form>
                                    )}
                                </>
                            )}
                        </section>
                    )}
                <section className="overflow-x-auto">
                    <table className="w-full min-w-[52rem] text-sm">
                        <thead>
                            <tr className="border-b text-left text-muted-foreground">
                                <th className="py-2">Product</th>
                                <th>
                                    {document.type === 'purchase_order'
                                        ? 'Ordered'
                                        : 'Quantity'}
                                </th>
                                {document.type === 'purchase_order' && (
                                    <>
                                        <th>Received</th>
                                        <th>Returned</th>
                                        <th>Invoiced</th>
                                        <th>To invoice</th>
                                    </>
                                )}
                                <th>Unit price</th>
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
                                    {document.type === 'purchase_order' && (
                                        <>
                                            <td>
                                                {progress.get(line.id)
                                                    ?.received_quantity ?? 0}
                                            </td>
                                            <td>
                                                {progress.get(line.id)
                                                    ?.returned_quantity ?? 0}
                                            </td>
                                            <td>
                                                {progress.get(line.id)
                                                    ?.invoiced_quantity ?? 0}
                                            </td>
                                            <td className="font-medium">
                                                {progress.get(line.id)
                                                    ?.pending_quantity ?? 0}
                                            </td>
                                        </>
                                    )}
                                    <td>{money(line.unit_price)}</td>
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
                    <Total
                        label={
                            Number(document.shipping_cost) > 0
                                ? 'Products'
                                : 'Subtotal'
                        }
                        value={String(
                            Number(document.subtotal) -
                                Number(document.shipping_cost),
                        )}
                    />
                    {Number(document.shipping_cost) > 0 && (
                        <Total
                            label="Transport (before tax)"
                            value={document.shipping_cost}
                        />
                    )}
                    <Total label="Tax" value={document.tax_amount} />
                    <Total label="Total" value={document.amount} strong />
                </section>
                {document.type === 'supplier_invoice' &&
                    document.matching_status && (
                        <MatchingPanel
                            document={document}
                            details={matchDetails}
                            canApprove={
                                abilities.approvePurchaseInvoices &&
                                (user.role === 'admin' ||
                                    document.created_by !== user.id)
                            }
                        />
                    )}
                {['supplier_invoice', 'supplier_credit_note'].includes(
                    document.type,
                ) && (
                    <SupplierDocumentPanel
                        document={document}
                        comparison={extractionComparison}
                    />
                )}
                {document.type === 'purchase_rfq' &&
                    !document.procurement_batch_id &&
                    ['draft', 'sent'].includes(document.status) && (
                        <section className="flex justify-end border-t pt-5">
                            <Form
                                action={`/purchases/${document.id}/convert`}
                                method="post"
                            >
                                <Button>
                                    <RefreshCw /> Convert to purchase order
                                </Button>
                            </Form>
                        </section>
                    )}
                {canReceive && (
                    <section className="border-t pt-5">
                        <h2 className="font-semibold">Receive goods</h2>
                        <p className="mb-3 text-sm text-muted-foreground">
                            Post a partial or complete receipt into stock.
                        </p>
                        <Form
                            action={`/purchases/${document.id}/receive`}
                            method="post"
                            className="grid gap-4"
                        >
                            <div className="grid gap-4 sm:grid-cols-2">
                                <SelectWarehouse
                                    warehouses={warehouses}
                                    selected={
                                        document.destination_warehouse?.id
                                    }
                                />
                                <Field
                                    label="Supplier delivery note"
                                    name="supplier_delivery_note"
                                />
                            </div>
                            <div className="overflow-x-auto border-y">
                                <table className="w-full min-w-[78rem] text-sm">
                                    <thead>
                                        <tr className="border-b text-left text-muted-foreground">
                                            <th className="py-2">Product</th>
                                            <th className="w-28">Receive</th>
                                            <th className="w-28">Reject</th>
                                            <th className="w-44">Reason</th>
                                            <th className="w-32">Severity</th>
                                            <th>Quality notes</th>
                                            <th>Serial numbers</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {document.lines
                                            .filter(
                                                (line) =>
                                                    line.product.track_stock &&
                                                    Number(
                                                        line.received_quantity,
                                                    ) < Number(line.quantity),
                                            )
                                            .map((line) => (
                                                <tr
                                                    key={line.id}
                                                    className="border-b last:border-0"
                                                >
                                                    <td className="py-3">
                                                        {line.product.name}
                                                        <div className="text-xs text-muted-foreground">
                                                            Pending{' '}
                                                            {Number(
                                                                line.quantity,
                                                            ) -
                                                                Number(
                                                                    line.received_quantity,
                                                                )}
                                                        </div>
                                                    </td>
                                                    <td className="p-2">
                                                        <Input
                                                            name={`quantities[${line.id}]`}
                                                            type="number"
                                                            step="0.001"
                                                            min="0"
                                                            max={
                                                                Number(
                                                                    line.quantity,
                                                                ) -
                                                                Number(
                                                                    line.received_quantity,
                                                                )
                                                            }
                                                            defaultValue={
                                                                Number(
                                                                    line.quantity,
                                                                ) -
                                                                Number(
                                                                    line.received_quantity,
                                                                )
                                                            }
                                                        />
                                                    </td>
                                                    <td className="p-2">
                                                        <Input
                                                            name={`rejected_quantities[${line.id}]`}
                                                            type="number"
                                                            step="0.001"
                                                            min="0"
                                                            max={
                                                                Number(
                                                                    line.quantity,
                                                                ) -
                                                                Number(
                                                                    line.received_quantity,
                                                                )
                                                            }
                                                            defaultValue="0"
                                                        />
                                                    </td>
                                                    <td className="p-2">
                                                        <select
                                                            name={`rejection_reasons[${line.id}]`}
                                                            className="h-9 w-full border bg-transparent px-2"
                                                            defaultValue=""
                                                        >
                                                            <option value="">
                                                                Select
                                                            </option>
                                                            <option value="damaged">
                                                                Damaged
                                                            </option>
                                                            <option value="wrong_item">
                                                                Wrong item
                                                            </option>
                                                            <option value="defective">
                                                                Defective
                                                            </option>
                                                            <option value="incomplete">
                                                                Incomplete
                                                            </option>
                                                            <option value="documentation">
                                                                Documentation
                                                            </option>
                                                            <option value="other">
                                                                Other
                                                            </option>
                                                        </select>
                                                    </td>
                                                    <td className="p-2">
                                                        <select
                                                            name={`rejection_severities[${line.id}]`}
                                                            className="h-9 w-full border bg-transparent px-2"
                                                            defaultValue="minor"
                                                        >
                                                            <option value="minor">
                                                                Minor
                                                            </option>
                                                            <option value="major">
                                                                Major
                                                            </option>
                                                            <option value="critical">
                                                                Critical
                                                            </option>
                                                        </select>
                                                    </td>
                                                    <td className="p-2">
                                                        <Input
                                                            name={`rejection_notes[${line.id}]`}
                                                            placeholder="Damage or discrepancy"
                                                        />
                                                    </td>
                                                    <td className="p-2">
                                                        {line.product
                                                            .track_serials ? (
                                                            <Input
                                                                name={`serials[${line.id}]`}
                                                                placeholder="Comma-separated serial numbers"
                                                            />
                                                        ) : (
                                                            <span className="text-muted-foreground">
                                                                Quantity
                                                                tracking
                                                            </span>
                                                        )}
                                                    </td>
                                                </tr>
                                            ))}
                                    </tbody>
                                </table>
                            </div>
                            <Button className="w-fit">
                                <PackageCheck /> Post goods receipt
                            </Button>
                        </Form>
                    </section>
                )}
                {document.type === 'purchase_order' && hasReturnableStock && (
                    <section className="border-t pt-5">
                        <h2 className="font-semibold">Return goods</h2>
                        <p className="mb-3 text-sm text-muted-foreground">
                            Return previously received stock to the supplier.
                        </p>
                        <Form
                            action={`/purchases/${document.id}/return`}
                            method="post"
                            resetOnSuccess
                            className="grid gap-4"
                        >
                            <div className="grid gap-4 sm:grid-cols-2">
                                <SelectWarehouse
                                    warehouses={warehouses}
                                    selected={
                                        document.destination_warehouse?.id
                                    }
                                />
                                <Field
                                    label="Supplier return reference"
                                    name="supplier_return_number"
                                />
                            </div>
                            <div className="overflow-x-auto border-y">
                                <table className="w-full min-w-[44rem] text-sm">
                                    <thead>
                                        <tr className="border-b text-left text-muted-foreground">
                                            <th className="py-2">Product</th>
                                            <th>Returnable</th>
                                            <th className="w-28">Return</th>
                                            <th>Serial numbers</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {document.lines
                                            .filter(
                                                (line) =>
                                                    line.product.track_stock &&
                                                    Number(
                                                        line.received_quantity,
                                                    ) -
                                                        Number(
                                                            line.returned_quantity,
                                                        ) >
                                                        0,
                                            )
                                            .map((line) => {
                                                const returnable =
                                                    Number(
                                                        line.received_quantity,
                                                    ) -
                                                    Number(
                                                        line.returned_quantity,
                                                    );

                                                return (
                                                    <tr
                                                        key={line.id}
                                                        className="border-b last:border-0"
                                                    >
                                                        <td className="py-3 font-medium">
                                                            {line.description}
                                                        </td>
                                                        <td>{returnable}</td>
                                                        <td className="p-2">
                                                            <Input
                                                                name={`quantities[${line.id}]`}
                                                                type="number"
                                                                min="0"
                                                                max={returnable}
                                                                step="0.001"
                                                                defaultValue="0"
                                                            />
                                                        </td>
                                                        <td className="p-2">
                                                            {line.product
                                                                .track_serials ? (
                                                                <Input
                                                                    name={`serials[${line.id}]`}
                                                                    placeholder="Serial numbers to return"
                                                                />
                                                            ) : (
                                                                <span className="text-muted-foreground">
                                                                    Quantity
                                                                    tracking
                                                                </span>
                                                            )}
                                                        </td>
                                                    </tr>
                                                );
                                            })}
                                    </tbody>
                                </table>
                            </div>
                            <Button variant="outline" className="w-fit">
                                <RotateCcw /> Post goods return
                            </Button>
                        </Form>
                    </section>
                )}
                {document.type === 'purchase_order' &&
                    ['ordered', 'partially_received', 'received'].includes(
                        document.status,
                    ) &&
                    hasPendingInvoice && (
                        <section className="border-t pt-5">
                            <h2 className="font-semibold">
                                Register supplier invoice
                            </h2>
                            <Form
                                action={`/purchases/${document.id}/invoice`}
                                method="post"
                                className="mt-3 grid gap-4"
                            >
                                <div className="grid gap-4 sm:grid-cols-3">
                                    <Field
                                        label="Supplier invoice number"
                                        name="supplier_number"
                                    />
                                    <Field
                                        label="Issue date"
                                        name="issue_date"
                                        type="date"
                                        value={today()}
                                    />
                                    <Field
                                        label="Due date"
                                        name="due_date"
                                        type="date"
                                    />
                                </div>
                                <div className="grid max-w-sm gap-2">
                                    <Field
                                        label="Transport (before tax)"
                                        name="shipping_cost"
                                        type="number"
                                        value={String(
                                            orderSummary?.shipping
                                                .pending_cost ?? 0,
                                        )}
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        Remaining order transport:{' '}
                                        {money(
                                            String(
                                                orderSummary?.shipping
                                                    .pending_cost ?? 0,
                                            ),
                                        )}
                                    </p>
                                </div>
                                <div className="overflow-x-auto border-y">
                                    <table className="w-full min-w-[42rem] text-sm">
                                        <thead>
                                            <tr className="border-b text-left text-muted-foreground">
                                                <th className="py-2">
                                                    Product
                                                </th>
                                                <th>Ordered</th>
                                                <th>Received</th>
                                                <th>Already invoiced</th>
                                                <th className="w-32">
                                                    Invoice now
                                                </th>
                                                <th className="w-36">
                                                    Unit price
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {document.lines.map(
                                                (line, index) => (
                                                    <tr
                                                        key={line.id}
                                                        className="border-b last:border-0"
                                                    >
                                                        <td className="py-3 font-medium">
                                                            {line.description}
                                                            <input
                                                                type="hidden"
                                                                name={`lines[${index}][line_id]`}
                                                                value={line.id}
                                                            />
                                                        </td>
                                                        <td>
                                                            {Number(
                                                                line.quantity,
                                                            )}
                                                        </td>
                                                        <td>
                                                            {line.product
                                                                .track_stock
                                                                ? progress.get(
                                                                      line.id,
                                                                  )
                                                                      ?.available_to_invoice ||
                                                                  0
                                                                : Number(
                                                                      line.quantity,
                                                                  )}
                                                        </td>
                                                        <td>
                                                            {progress.get(
                                                                line.id,
                                                            )
                                                                ?.invoiced_quantity ||
                                                                0}
                                                        </td>
                                                        <td className="p-2">
                                                            <Input
                                                                name={`lines[${index}][quantity]`}
                                                                type="number"
                                                                min="0"
                                                                step="0.001"
                                                                max={
                                                                    progress.get(
                                                                        line.id,
                                                                    )
                                                                        ?.pending_quantity
                                                                }
                                                                defaultValue={
                                                                    progress.get(
                                                                        line.id,
                                                                    )
                                                                        ?.pending_quantity ||
                                                                    0
                                                                }
                                                            />
                                                        </td>
                                                        <td className="p-2">
                                                            <Input
                                                                name={`lines[${index}][unit_price]`}
                                                                type="number"
                                                                min="0"
                                                                step="0.01"
                                                                defaultValue={Number(
                                                                    line.unit_price,
                                                                )}
                                                            />
                                                        </td>
                                                    </tr>
                                                ),
                                            )}
                                        </tbody>
                                    </table>
                                </div>
                                <Button className="w-fit">
                                    <FileInput /> Register invoice
                                </Button>
                            </Form>
                        </section>
                    )}
                {document.type === 'purchase_order' &&
                    supplierInvoices.length > 0 && (
                        <section className="border-t pt-5">
                            <h2 className="mb-3 font-semibold">
                                Supplier invoices
                            </h2>
                            <div className="border-y">
                                {supplierInvoices.map((invoice) => (
                                    <Link
                                        key={invoice.id}
                                        href={`/purchases/${invoice.id}`}
                                        className="grid gap-1 border-b py-3 text-sm last:border-0 sm:grid-cols-[1fr_auto_auto] sm:items-center sm:gap-6"
                                    >
                                        <span className="font-medium">
                                            {invoice.number} ·{' '}
                                            {invoice.supplier_number ||
                                                'No supplier reference'}
                                        </span>
                                        <span className="text-muted-foreground">
                                            {date(invoice.issue_date)}
                                        </span>
                                        <span className="font-medium">
                                            {money(invoice.amount)}
                                        </span>
                                    </Link>
                                ))}
                            </div>
                        </section>
                    )}
                {document.receipts.length > 0 && (
                    <section className="border-t pt-5">
                        <h2 className="mb-3 font-semibold">Goods receipts</h2>
                        <div className="border-y">
                            {document.receipts.map((receipt) => (
                                <div
                                    key={receipt.id}
                                    className="flex justify-between gap-4 border-b py-3 text-sm last:border-0"
                                >
                                    <span className="font-medium">
                                        {receipt.number}
                                    </span>
                                    <span className="text-muted-foreground">
                                        {receipt.supplier_delivery_note ||
                                            receipt.movement.number}
                                    </span>
                                </div>
                            ))}
                        </div>
                    </section>
                )}
                {document.quality_incidents.length > 0 && (
                    <section className="border-t pt-5">
                        <div className="mb-3 flex items-center justify-between gap-3">
                            <h2 className="font-semibold">
                                Supplier quality incidents
                            </h2>
                            <Button size="sm" variant="outline" asChild>
                                <Link href="/supplier-quality">
                                    Manage claims
                                </Link>
                            </Button>
                        </div>
                        <div className="border-y">
                            {document.quality_incidents.map((incident) => (
                                <div
                                    key={incident.id}
                                    className="grid gap-2 border-b py-3 text-sm last:border-0 sm:grid-cols-[1fr_auto]"
                                >
                                    <div>
                                        <div className="font-medium">
                                            {incident.product.name} ·{' '}
                                            {incident.rejected_quantity}{' '}
                                            rejected
                                        </div>
                                        <div className="text-muted-foreground">
                                            {label(incident.reason)}
                                            {incident.notes
                                                ? ` · ${incident.notes}`
                                                : ''}
                                        </div>
                                        {incident.resolution && (
                                            <div className="mt-1 text-muted-foreground">
                                                Resolution:{' '}
                                                {label(incident.resolution)}
                                                {incident.resolution_notes
                                                    ? ` · ${incident.resolution_notes}`
                                                    : ''}
                                            </div>
                                        )}
                                    </div>
                                    <div className="flex items-start gap-2">
                                        <Badge
                                            variant={
                                                incident.severity === 'critical'
                                                    ? 'destructive'
                                                    : 'outline'
                                            }
                                        >
                                            {label(incident.severity)}
                                        </Badge>
                                        <Badge
                                            variant={
                                                incident.status === 'resolved'
                                                    ? 'secondary'
                                                    : 'outline'
                                            }
                                        >
                                            {label(incident.status)}
                                        </Badge>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </section>
                )}
                {document.returns.length > 0 && (
                    <section className="border-t pt-5">
                        <h2 className="mb-3 font-semibold">Goods returns</h2>
                        <div className="border-y">
                            {document.returns.map((item) => (
                                <div
                                    key={item.id}
                                    className="flex justify-between gap-4 border-b py-3 text-sm last:border-0"
                                >
                                    <span className="font-medium">
                                        {item.number}
                                    </span>
                                    <span className="text-muted-foreground">
                                        {item.supplier_return_number ||
                                            item.movement.number}
                                    </span>
                                </div>
                            ))}
                        </div>
                    </section>
                )}
                {document.type === 'supplier_invoice' &&
                    supplierCredits.length > 0 && (
                        <section className="border-t pt-5">
                            <h2 className="mb-3 font-semibold">
                                Supplier credit notes
                            </h2>
                            <div className="border-y">
                                {supplierCredits.map((credit) => (
                                    <Link
                                        key={credit.id}
                                        href={`/purchases/${credit.id}`}
                                        className="flex items-center justify-between gap-4 border-b py-3 text-sm last:border-0"
                                    >
                                        <span className="font-medium">
                                            {credit.number} ·{' '}
                                            {credit.supplier_number}
                                        </span>
                                        <span>{money(credit.amount)}</span>
                                    </Link>
                                ))}
                            </div>
                        </section>
                    )}
                {document.type === 'supplier_invoice' &&
                    document.matching_status !== 'review_required' &&
                    hasCreditableLines && (
                        <section className="border-t pt-5">
                            <h2 className="font-semibold">
                                Register supplier credit note
                            </h2>
                            <Form
                                action={`/supplier-invoices/${document.id}/credit-note`}
                                method="post"
                                resetOnSuccess
                                className="mt-3 grid gap-4"
                            >
                                <div className="grid gap-4 sm:grid-cols-3">
                                    <Field
                                        label="Supplier credit note number"
                                        name="supplier_number"
                                    />
                                    <Field
                                        label="Issue date"
                                        name="issue_date"
                                        type="date"
                                        value={today()}
                                    />
                                    <Field
                                        label={`Transport credit (available ${money(String(creditableShipping))})`}
                                        name="shipping_cost"
                                        type="number"
                                        value="0"
                                    />
                                </div>
                                <div className="overflow-x-auto border-y">
                                    <table className="w-full min-w-[38rem] text-sm">
                                        <thead>
                                            <tr className="border-b text-left text-muted-foreground">
                                                <th className="py-2">
                                                    Product
                                                </th>
                                                <th>Invoiced</th>
                                                <th>Already credited</th>
                                                <th className="w-32">
                                                    Credit now
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {creditSummary.map(
                                                (line, index) => (
                                                    <tr
                                                        key={line.line_id}
                                                        className="border-b last:border-0"
                                                    >
                                                        <td className="py-3 font-medium">
                                                            {line.description}
                                                            <input
                                                                type="hidden"
                                                                name={`lines[${index}][line_id]`}
                                                                value={
                                                                    line.line_id
                                                                }
                                                            />
                                                        </td>
                                                        <td>
                                                            {
                                                                line.invoiced_quantity
                                                            }
                                                        </td>
                                                        <td>
                                                            {
                                                                line.credited_quantity
                                                            }
                                                        </td>
                                                        <td className="p-2">
                                                            <Input
                                                                name={`lines[${index}][quantity]`}
                                                                type="number"
                                                                min="0"
                                                                max={
                                                                    line.creditable_quantity
                                                                }
                                                                step="0.001"
                                                                defaultValue="0"
                                                            />
                                                        </td>
                                                    </tr>
                                                ),
                                            )}
                                        </tbody>
                                    </table>
                                </div>
                                <Button variant="outline" className="w-fit">
                                    <RotateCcw /> Register credit note
                                </Button>
                            </Form>
                        </section>
                    )}
                {document.type === 'supplier_invoice' && (
                    <section className="grid gap-8 border-t pt-5 xl:grid-cols-2">
                        <div>
                            <h2 className="font-semibold">Payment</h2>
                            <p className="mb-3 text-sm text-muted-foreground">
                                Outstanding: {money(String(outstandingAmount))}
                            </p>
                            {abilities.managePurchasePayments &&
                                document.matching_status !==
                                    'review_required' &&
                                outstandingAmount > 0 && (
                                    <Form
                                        action={`/supplier-invoices/${document.id}/payments`}
                                        method="post"
                                        resetOnSuccess
                                        className="grid gap-4 sm:grid-cols-2"
                                    >
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
                                            value={String(outstandingAmount)}
                                        />
                                        <PaymentSourceFields
                                            sources={paymentSources}
                                        />
                                        <Button>
                                            <WalletCards /> Register payment
                                        </Button>
                                    </Form>
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
                                        className="flex items-center justify-between gap-3 border-b py-3 text-sm last:border-0"
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
                                            </div>
                                        </div>
                                        {abilities.managePurchasePayments && (
                                            <Form
                                                action={`/supplier-payments/${payment.id}`}
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
                                ))}
                            </div>
                        </div>
                    </section>
                )}
                {document.events.length > 0 && (
                    <section className="border-t pt-5">
                        <h2 className="mb-3 font-semibold">Activity</h2>
                        <div className="grid gap-3">
                            {document.events.map((event) => (
                                <div
                                    key={event.id}
                                    className="flex items-start justify-between gap-4 border-b pb-3 text-sm"
                                >
                                    <div>
                                        <div className="font-medium capitalize">
                                            {event.event.replaceAll('_', ' ')}
                                        </div>
                                        <div className="text-muted-foreground">
                                            {event.user?.name || 'System'}
                                            {event.from_status &&
                                            event.to_status &&
                                            event.from_status !==
                                                event.to_status
                                                ? ` · ${event.from_status} → ${event.to_status}`
                                                : ''}
                                        </div>
                                    </div>
                                    <span className="text-xs whitespace-nowrap text-muted-foreground">
                                        {new Intl.DateTimeFormat(undefined, {
                                            dateStyle: 'medium',
                                            timeStyle: 'short',
                                        }).format(new Date(event.occurred_at))}
                                    </span>
                                </div>
                            ))}
                        </div>
                    </section>
                )}
            </div>
        </>
    );
}
function SupplierDocumentPanel({
    document,
    comparison,
}: {
    document: Document;
    comparison: ExtractionComparison[];
}) {
    const file = document.attachment;
    const differences = comparison.filter(
        (item) => item.status === 'different',
    ).length;

    return (
        <section className="border-t pt-5">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 className="font-semibold">Original document</h2>
                    <p className="text-sm text-muted-foreground">
                        Private evidence and assisted accounting validation.
                    </p>
                </div>
                {file && (
                    <Badge variant="outline">
                        {file.extraction_status.replaceAll('_', ' ')}
                    </Badge>
                )}
            </div>
            {!file ? (
                <Form
                    action={`/supplier-invoices/${document.id}/document`}
                    method="post"
                    className="mt-4 flex flex-wrap items-end gap-3"
                >
                    <div className="grid min-w-0 flex-1 gap-2 sm:max-w-lg">
                        <label className="text-sm font-medium">
                            PDF or image
                        </label>
                        <Input
                            name="document_file"
                            type="file"
                            accept="application/pdf,image/jpeg,image/png,image/webp"
                            required
                        />
                    </div>
                    <Button>
                        <Upload /> Attach original
                    </Button>
                </Form>
            ) : (
                <>
                    <div className="mt-4 flex flex-wrap items-center justify-between gap-4 border-y py-3 text-sm">
                        <div className="min-w-0">
                            <div className="truncate font-medium">
                                {file.original_name}
                            </div>
                            <div className="text-muted-foreground">
                                {file.mime_type} · {fileSize(file.size)}
                            </div>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            <Button variant="outline" asChild>
                                <a
                                    href={`/supplier-invoices/${document.id}/document`}
                                >
                                    <Download /> Download
                                </a>
                            </Button>
                            {['pending', 'failed'].includes(
                                file.extraction_status,
                            ) && (
                                <Form
                                    action={`/supplier-invoices/${document.id}/document/extract`}
                                    method="post"
                                >
                                    <Button variant="outline">
                                        <FileSearch /> Extract data
                                    </Button>
                                </Form>
                            )}
                        </div>
                    </div>
                    {file.extraction_error && (
                        <p className="mt-3 text-sm text-destructive">
                            {file.extraction_error}
                        </p>
                    )}
                    {comparison.length > 0 && (
                        <div className="mt-4 overflow-x-auto border-y">
                            <table className="w-full min-w-[42rem] text-sm">
                                <thead>
                                    <tr className="border-b text-left text-muted-foreground">
                                        <th className="py-2">Field</th>
                                        <th>Extracted</th>
                                        <th>Registered</th>
                                        <th>Result</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {comparison.map((item) => (
                                        <tr
                                            key={item.field}
                                            className="border-b last:border-0"
                                        >
                                            <td className="py-3 font-medium">
                                                {item.label}
                                            </td>
                                            <td>{displayExtracted(item)}</td>
                                            <td>{displayRegistered(item)}</td>
                                            <td>
                                                <Badge
                                                    variant={
                                                        item.status ===
                                                        'different'
                                                            ? 'destructive'
                                                            : 'outline'
                                                    }
                                                >
                                                    {item.status}
                                                </Badge>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                    {file.extraction_status === 'extracted' && (
                        <div className="mt-4 flex flex-wrap items-center justify-between gap-3">
                            <p className="text-sm text-muted-foreground">
                                {differences > 0
                                    ? `${differences} differences require review.`
                                    : 'Extracted and registered values agree.'}
                            </p>
                            <Form
                                action={`/supplier-invoices/${document.id}/document/verify`}
                                method="post"
                            >
                                <Button>
                                    <ShieldCheck /> Mark as verified
                                </Button>
                            </Form>
                        </div>
                    )}
                    {file.extraction_status === 'verified' && (
                        <p className="mt-4 text-sm text-muted-foreground">
                            Verified by {file.verifier?.name || 'staff'}.
                        </p>
                    )}
                </>
            )}
        </section>
    );
}
function MatchingPanel({
    document,
    details,
    canApprove,
}: {
    document: Document;
    details: MatchDetail[];
    canApprove: boolean;
}) {
    const needsReview = document.matching_status === 'review_required';

    return (
        <section className="border-t pt-5">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <div className="flex items-center gap-2 font-semibold">
                        {needsReview ? (
                            <CircleAlert className="size-4 text-amber-600" />
                        ) : (
                            <ShieldCheck className="size-4 text-emerald-600" />
                        )}
                        Three-way match
                    </div>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {needsReview
                            ? 'Quantity, price or transport differs from the order and goods received.'
                            : document.matching_status === 'approved'
                              ? `Variance approved by ${document.approver?.name || 'an authorised user'}.`
                              : 'Order, receipt and supplier invoice agree.'}
                    </p>
                </div>
                <div className="text-right text-sm">
                    <div className="text-muted-foreground">Amount variance</div>
                    <div className="font-semibold">
                        {money(document.match_variance_amount)}
                    </div>
                </div>
            </div>
            {document.source_document?.type === 'purchase_order' && (
                <div className="mt-4 grid border-y text-sm sm:grid-cols-2">
                    <Info
                        label="Order transport"
                        value={money(document.source_document.shipping_cost)}
                    />
                    <Info
                        label="Invoice transport"
                        value={money(document.shipping_cost)}
                    />
                </div>
            )}
            <div className="mt-4 overflow-x-auto border-y">
                <table className="w-full min-w-[48rem] text-sm">
                    <thead>
                        <tr className="border-b text-left text-muted-foreground">
                            <th className="py-2">Product</th>
                            <th>Ordered</th>
                            <th>Received</th>
                            <th>Invoiced</th>
                            <th>Order price</th>
                            <th>Invoice price</th>
                            <th>Result</th>
                        </tr>
                    </thead>
                    <tbody>
                        {details.map((line) => {
                            const differs =
                                needsReview ||
                                Math.abs(line.quantity_variance) > 0.0005 ||
                                Math.abs(line.price_variance) > 0.005;

                            return (
                                <tr
                                    key={line.line_id}
                                    className="border-b last:border-0"
                                >
                                    <td className="py-3 font-medium">
                                        {line.description}
                                    </td>
                                    <td>{line.ordered_quantity}</td>
                                    <td>{line.received_quantity}</td>
                                    <td>{line.invoiced_quantity}</td>
                                    <td>
                                        {money(String(line.ordered_unit_price))}
                                    </td>
                                    <td>
                                        {money(
                                            String(line.invoiced_unit_price),
                                        )}
                                    </td>
                                    <td>
                                        <Badge
                                            variant={
                                                differs
                                                    ? 'destructive'
                                                    : 'outline'
                                            }
                                        >
                                            {differs ? 'Review' : 'Matched'}
                                        </Badge>
                                    </td>
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
            </div>
            {needsReview && canApprove && (
                <Form
                    action={`/supplier-invoices/${document.id}/approve`}
                    method="post"
                    className="mt-4 grid max-w-2xl gap-3"
                >
                    <label className="text-sm font-medium">
                        Approval reason
                    </label>
                    <textarea
                        name="approval_notes"
                        required
                        rows={3}
                        className="rounded-md border bg-background px-3 py-2 text-sm"
                        placeholder="Explain why this variance is accepted"
                    />
                    <Button className="w-fit">
                        <ShieldCheck /> Approve and post
                    </Button>
                </Form>
            )}
            {document.approval_notes && (
                <p className="mt-4 border-l-2 pl-3 text-sm text-muted-foreground">
                    {document.approval_notes}
                </p>
            )}
        </section>
    );
}
function displayExtracted(item: ExtractionComparison) {
    if (item.extracted === '' || item.extracted === null) {
        return '—';
    }

    if (item.type === 'amount') {
        return money(String(item.extracted));
    }

    return item.type === 'date'
        ? date(String(item.extracted))
        : String(item.extracted);
}
function displayRegistered(item: ExtractionComparison) {
    if (item.registered === '') {
        return '—';
    }

    if (item.type === 'amount') {
        return money(String(item.registered));
    }

    return item.type === 'date'
        ? date(String(item.registered))
        : String(item.registered);
}
function fileSize(bytes: number) {
    if (bytes < 1024) {
        return `${bytes} B`;
    }

    if (bytes < 1024 * 1024) {
        return `${(bytes / 1024).toFixed(1)} KB`;
    }

    return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
}
function Info({ label, value }: { label: string; value: string }) {
    return (
        <div>
            <div className="text-muted-foreground">{label}</div>
            <div className="mt-1 font-medium">{value}</div>
        </div>
    );
}
function InfoLink({
    label,
    value,
    href,
}: {
    label: string;
    value: string;
    href: string | null;
}) {
    return (
        <div>
            <div className="text-muted-foreground">{label}</div>
            {href ? (
                <Link
                    href={href}
                    className="mt-1 block font-medium underline underline-offset-2"
                >
                    {value}
                </Link>
            ) : (
                <div className="mt-1 font-medium">{value}</div>
            )}
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
}: {
    label: string;
    name: string;
    type?: string;
    value?: string;
}) {
    return (
        <div className="grid gap-2">
            <label className="text-sm font-medium">{label}</label>
            <Input
                name={name}
                type={type}
                min={type === 'number' ? '0' : undefined}
                step={type === 'number' ? '0.01' : undefined}
                defaultValue={value}
            />
        </div>
    );
}
function SelectWarehouse({
    warehouses,
    selected,
}: {
    warehouses: Warehouse[];
    selected?: number;
}) {
    return (
        <div className="grid gap-2">
            <label className="text-sm font-medium">Warehouse</label>
            <select
                name="warehouse_id"
                defaultValue={selected}
                className="h-9 rounded-md border bg-background px-3 text-sm"
            >
                {warehouses.map((w) => (
                    <option key={w.id} value={w.id}>
                        {w.name} ({w.code})
                    </option>
                ))}
            </select>
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
const label = (value: string) =>
    value.replaceAll('_', ' ').replace(/^\w/, (letter) => letter.toUpperCase());
const today = () => new Date().toISOString().slice(0, 10);
PurchaseShow.layout = {
    breadcrumbs: [
        { title: 'Purchases', href: '/purchases' },
        { title: 'Document', href: '#' },
    ],
};

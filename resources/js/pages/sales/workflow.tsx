import { Form, Head, Link } from '@inertiajs/react';
import {
    ArrowRight,
    Boxes,
    FileText,
    PackageCheck,
    ReceiptText,
    ShoppingCart,
    TriangleAlert,
    Truck,
} from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { useLocale } from '@/hooks/use-locale';
import { translate } from '@/i18n';

type ProgressLine = {
    id: number;
    description: string;
    quantity: number;
    allocated_delivery_quantity: number;
    delivered_quantity: number;
    invoiced_quantity: number;
    remaining_delivery_quantity: number;
    remaining_invoice_quantity: number;
};
type DocumentBase = {
    id: number;
    number: string;
    status: string;
    issue_date: string;
    amount: string;
    customer: string;
    installation: {
        id: number;
        reference: string | null;
        name: string | null;
    } | null;
    source_document: { id: number; number: string } | null;
};
type StockStatus = {
    required: number;
    reserved: number;
    available: number;
    inbound: number;
    physical_shortage: number;
    shortage: number;
    lines: {
        product_id: number;
        name: string;
        sku: string | null;
        required: number;
        reserved: number;
        available: number;
        inbound: number;
        physical_shortage: number;
        shortage: number;
    }[];
};
type Order = DocumentBase & {
    fulfillment_status: string;
    billing_status: string;
    progress: ProgressLine[];
    stock: StockStatus;
    purchases: {
        id: number;
        number: string;
        type: string;
        status: string;
        procurement_batch_id: string | null;
        supplier: string;
    }[];
};
type Dispatch = DocumentBase & { has_stock: boolean; has_serials: boolean };
type Billing = DocumentBase & { progress: ProgressLine[] };
type Warehouse = { id: number; code: string; name: string };
type Supplier = { id: number; legal_name: string; trade_name: string | null };

export default function SalesWorkflow({
    orders,
    dispatches,
    billing,
    warehouses,
    suppliers,
    summary,
}: {
    orders: Order[];
    dispatches: Dispatch[];
    billing: Billing[];
    warehouses: Warehouse[];
    suppliers: Supplier[];
    summary: {
        orders: number;
        dispatches: number;
        billing: number;
        shortages: number;
    };
}) {
    const { locale } = useLocale();

    return (
        <>
            <Head title={translate('Sales workflow', locale)} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <header className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold">
                            Sales workflow
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Prepare, dispatch and invoice customer orders.
                        </p>
                    </div>
                    <Button variant="outline" asChild>
                        <Link href="/sales">
                            <FileText /> Documents
                        </Link>
                    </Button>
                </header>

                <section className="grid border-y sm:grid-cols-2 xl:grid-cols-4">
                    <Metric
                        icon={Boxes}
                        label="Open orders"
                        value={summary.orders}
                    />
                    <Metric
                        icon={Truck}
                        label="Ready to dispatch"
                        value={summary.dispatches}
                    />
                    <Metric
                        icon={ReceiptText}
                        label="Ready to invoice"
                        value={summary.billing}
                    />
                    <Metric
                        icon={TriangleAlert}
                        label="Stock shortages"
                        value={summary.shortages}
                        alert={summary.shortages > 0}
                    />
                </section>

                <QueueSection
                    title="Orders to prepare"
                    description="Reserve materials and create the next delivery or invoice."
                    empty="No sales orders require action."
                >
                    {orders.map((order) => (
                        <OrderRow
                            key={order.id}
                            order={order}
                            warehouses={warehouses}
                            suppliers={suppliers}
                        />
                    ))}
                </QueueSection>

                <QueueSection
                    title="Delivery notes to dispatch"
                    description="Confirm services directly or open stock dispatches to select warehouse and serial numbers."
                    empty="No delivery notes are waiting for dispatch."
                >
                    {dispatches.map((document) => (
                        <div
                            key={document.id}
                            className="grid gap-4 border-b py-4 lg:grid-cols-[minmax(16rem,1fr)_minmax(12rem,.7fr)_auto] lg:items-center"
                        >
                            <DocumentIdentity document={document} />
                            <div className="text-sm text-muted-foreground">
                                {document.has_stock ? (
                                    <span>
                                        {document.has_serials
                                            ? 'Warehouse and serial selection required'
                                            : 'Warehouse selection required'}
                                    </span>
                                ) : (
                                    <span>No stock movement required</span>
                                )}
                            </div>
                            {document.has_stock ? (
                                <Button asChild>
                                    <Link href={`/sales/${document.id}`}>
                                        Prepare dispatch <ArrowRight />
                                    </Link>
                                </Button>
                            ) : (
                                <Form
                                    action={`/sales/${document.id}/dispatch`}
                                    method="post"
                                >
                                    <Button>
                                        <PackageCheck /> Confirm delivery
                                    </Button>
                                </Form>
                            )}
                        </div>
                    ))}
                </QueueSection>

                <QueueSection
                    title="Delivered and pending invoice"
                    description="Generate draft invoices from confirmed delivery quantities."
                    empty="No delivered quantities are waiting to be invoiced."
                >
                    {billing.map((document) => (
                        <div
                            key={document.id}
                            className="grid gap-4 border-b py-4 lg:grid-cols-[minmax(16rem,1fr)_minmax(12rem,.7fr)_auto] lg:items-center"
                        >
                            <DocumentIdentity document={document} />
                            <ProgressSummary
                                lines={document.progress}
                                compact
                            />
                            <ConversionForm
                                documentId={document.id}
                                type="invoice"
                                lines={document.progress}
                                label="Create invoice"
                            />
                        </div>
                    ))}
                </QueueSection>
            </div>
        </>
    );
}

function OrderRow({
    order,
    warehouses,
    suppliers,
}: {
    order: Order;
    warehouses: Warehouse[];
    suppliers: Supplier[];
}) {
    const needsReservation = order.stock.required > order.stock.reserved;

    return (
        <div className="grid gap-4 border-b py-5 xl:grid-cols-[minmax(15rem,.8fr)_minmax(14rem,.8fr)_minmax(18rem,1.1fr)]">
            <div className="grid content-start gap-3">
                <DocumentIdentity document={order} />
                <div className="flex flex-wrap gap-1">
                    <Badge variant="outline">
                        Delivery {order.fulfillment_status}
                    </Badge>
                    <Badge variant="outline">
                        Billing {order.billing_status}
                    </Badge>
                </div>
                <ProgressSummary lines={order.progress} />
            </div>

            <div className="grid content-start gap-3 border-t pt-4 xl:border-t-0 xl:border-l xl:pt-0 xl:pl-5">
                <div>
                    <h3 className="text-sm font-medium">Stock coverage</h3>
                    {order.stock.required > 0 ? (
                        <p className="text-sm text-muted-foreground">
                            {number(order.stock.reserved)} reserved of{' '}
                            {number(order.stock.required)} required
                            {order.stock.inbound > 0
                                ? ` · ${number(order.stock.inbound)} requested`
                                : ''}
                        </p>
                    ) : (
                        <p className="text-sm text-muted-foreground">
                            No stock-tracked products pending.
                        </p>
                    )}
                </div>
                {order.stock.lines.map((line) => (
                    <div
                        key={line.product_id}
                        className="flex items-center justify-between gap-3 text-sm"
                    >
                        <span className="truncate">{line.name}</span>
                        <span
                            className={
                                line.shortage > 0
                                    ? 'font-medium text-destructive'
                                    : 'text-muted-foreground'
                            }
                        >
                            {line.shortage > 0
                                ? `${number(line.shortage)} short`
                                : line.physical_shortage > 0
                                  ? `${number(line.inbound)} requested`
                                  : line.reserved >= line.required
                                    ? `${number(line.reserved)} reserved`
                                    : `${number(line.available)} free`}
                        </span>
                    </div>
                ))}
                {needsReservation && order.stock.required > 0 && (
                    <Form
                        action={`/sales-workflow/${order.id}/reserve`}
                        method="post"
                        className="grid grid-cols-[minmax(0,1fr)_auto] gap-2"
                    >
                        <select
                            name="warehouse_id"
                            required
                            defaultValue={warehouses[0]?.id}
                            className="h-9 min-w-0 rounded-md border bg-background px-3 text-sm"
                        >
                            {warehouses.map((warehouse) => (
                                <option key={warehouse.id} value={warehouse.id}>
                                    {warehouse.name} ({warehouse.code})
                                </option>
                            ))}
                        </select>
                        <Button
                            variant="outline"
                            disabled={
                                warehouses.length === 0 ||
                                order.stock.physical_shortage > 0
                            }
                        >
                            <Boxes /> Reserve
                        </Button>
                    </Form>
                )}
                {order.stock.shortage > 0 && (
                    <SupplierRequestDialog
                        orderId={order.id}
                        suppliers={suppliers}
                    />
                )}
                {order.stock.shortage > 0 && suppliers.length === 0 && (
                    <p className="text-xs text-destructive">
                        Add an active supplier before requesting replenishment.
                    </p>
                )}
                {order.stock.physical_shortage > 0 &&
                    order.stock.shortage === 0 && (
                        <p className="text-xs text-muted-foreground">
                            Replenishment is already requested. Receive it
                            before dispatching the full quantity.
                        </p>
                    )}
                {order.purchases.length > 0 && (
                    <div className="divide-y border-y text-sm">
                        {order.purchases
                            .filter(
                                (purchase, index, purchases) =>
                                    purchase.type !== 'purchase_rfq' ||
                                    !purchase.procurement_batch_id ||
                                    index ===
                                        purchases.findIndex(
                                            (candidate) =>
                                                candidate.type ===
                                                    'purchase_rfq' &&
                                                candidate.procurement_batch_id ===
                                                    purchase.procurement_batch_id,
                                        ),
                            )
                            .map((purchase) => {
                                const comparisonCount =
                                    purchase.type === 'purchase_rfq' &&
                                    purchase.procurement_batch_id
                                        ? order.purchases.filter(
                                              (candidate) =>
                                                  candidate.type ===
                                                      'purchase_rfq' &&
                                                  candidate.procurement_batch_id ===
                                                      purchase.procurement_batch_id,
                                          ).length
                                        : 0;

                                return (
                                    <Link
                                        key={purchase.id}
                                        href={
                                            comparisonCount > 0
                                                ? `/purchase-comparisons/${purchase.procurement_batch_id}`
                                                : `/purchases/${purchase.id}`
                                        }
                                        className="flex items-center justify-between gap-3 py-2 hover:text-primary"
                                    >
                                        <span>
                                            {comparisonCount > 0
                                                ? 'Supplier comparison'
                                                : purchase.number}
                                        </span>
                                        <span className="text-xs text-muted-foreground">
                                            {comparisonCount > 0
                                                ? `${comparisonCount} suppliers`
                                                : purchase.status}
                                        </span>
                                    </Link>
                                );
                            })}
                    </div>
                )}
            </div>

            <div className="grid content-start gap-3 border-t pt-4 xl:border-t-0 xl:border-l xl:pt-0 xl:pl-5">
                <h3 className="text-sm font-medium">Next documents</h3>
                <div className="flex flex-wrap gap-2">
                    <ConversionForm
                        documentId={order.id}
                        type="delivery_note"
                        lines={order.progress}
                        label="Create delivery note"
                        disabled={order.stock.physical_shortage > 0}
                    />
                    <ConversionForm
                        documentId={order.id}
                        type="invoice"
                        lines={order.progress}
                        label="Create invoice"
                        variant="outline"
                    />
                    <Button variant="ghost" asChild>
                        <Link href={`/sales/${order.id}`}>
                            Open order <ArrowRight />
                        </Link>
                    </Button>
                </div>
            </div>
        </div>
    );
}

function SupplierRequestDialog({
    orderId,
    suppliers,
}: {
    orderId: number;
    suppliers: Supplier[];
}) {
    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button variant="outline" disabled={suppliers.length === 0}>
                    <ShoppingCart /> Request quotes
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Request supplier quotes</DialogTitle>
                </DialogHeader>
                <Form
                    action={`/sales-workflow/${orderId}/procure`}
                    method="post"
                    className="grid gap-4"
                >
                    <div className="max-h-72 divide-y overflow-y-auto border-y">
                        {suppliers.map((supplier) => (
                            <label
                                key={supplier.id}
                                className="flex cursor-pointer items-center gap-3 py-3 text-sm"
                            >
                                <input
                                    type="checkbox"
                                    name="supplier_ids[]"
                                    value={supplier.id}
                                    className="size-4"
                                />
                                <span>
                                    {supplier.trade_name || supplier.legal_name}
                                </span>
                            </label>
                        ))}
                    </div>
                    <Button>
                        <ShoppingCart /> Create comparison
                    </Button>
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function ConversionForm({
    documentId,
    type,
    lines,
    label,
    variant = 'default',
    disabled = false,
}: {
    documentId: number;
    type: 'delivery_note' | 'invoice';
    lines: ProgressLine[];
    label: string;
    variant?: 'default' | 'outline';
    disabled?: boolean;
}) {
    const key =
        type === 'delivery_note'
            ? 'remaining_delivery_quantity'
            : 'remaining_invoice_quantity';
    const remaining = lines.reduce((total, line) => total + line[key], 0);

    return (
        <Form action={`/sales/${documentId}/convert`} method="post">
            <input type="hidden" name="type" value={type} />
            {lines.map((line) => (
                <input
                    key={line.id}
                    type="hidden"
                    name={`quantities[${line.id}]`}
                    value={line[key]}
                />
            ))}
            <Button variant={variant} disabled={disabled || remaining <= 0}>
                {label}
            </Button>
        </Form>
    );
}

function DocumentIdentity({ document }: { document: DocumentBase }) {
    return (
        <div className="min-w-0">
            <Link
                href={`/sales/${document.id}`}
                className="font-medium underline underline-offset-2"
            >
                {document.number}
            </Link>
            <div className="truncate text-sm text-muted-foreground">
                {document.customer || 'Customer not available'} ·{' '}
                {date(document.issue_date)} · {money(document.amount)}
            </div>
            {document.installation && (
                <div className="truncate text-xs text-muted-foreground">
                    {document.installation.reference ||
                        document.installation.name ||
                        'Installation'}
                </div>
            )}
        </div>
    );
}

function ProgressSummary({
    lines,
    compact = false,
}: {
    lines: ProgressLine[];
    compact?: boolean;
}) {
    const ordered = lines.reduce((total, line) => total + line.quantity, 0);
    const delivered = lines.reduce(
        (total, line) => total + line.delivered_quantity,
        0,
    );
    const allocated = lines.reduce(
        (total, line) => total + line.allocated_delivery_quantity,
        0,
    );
    const invoiced = lines.reduce(
        (total, line) => total + line.invoiced_quantity,
        0,
    );

    return (
        <div className="text-sm text-muted-foreground">
            {compact
                ? `Quantity ${number(ordered)} · invoiced ${number(invoiced)}`
                : `Ordered ${number(ordered)} · allocated ${number(allocated)} · delivered ${number(delivered)} · invoiced ${number(invoiced)}`}
        </div>
    );
}

function QueueSection({
    title,
    description,
    empty,
    children,
}: {
    title: string;
    description: string;
    empty: string;
    children: React.ReactNode;
}) {
    const hasChildren = Array.isArray(children)
        ? children.length > 0
        : Boolean(children);

    return (
        <section>
            <div className="border-b pb-3">
                <h2 className="font-semibold">{title}</h2>
                <p className="text-sm text-muted-foreground">{description}</p>
            </div>
            {hasChildren ? (
                children
            ) : (
                <div className="border-b py-10 text-center text-sm text-muted-foreground">
                    {empty}
                </div>
            )}
        </section>
    );
}

function Metric({
    icon: Icon,
    label,
    value,
    alert = false,
}: {
    icon: typeof Boxes;
    label: string;
    value: number;
    alert?: boolean;
}) {
    return (
        <div className="flex items-center gap-3 border-b p-4 sm:border-r sm:border-b-0 sm:last:border-r-0">
            <Icon
                className={alert ? 'text-destructive' : 'text-muted-foreground'}
            />
            <div>
                <div className="text-xs text-muted-foreground">{label}</div>
                <div className="text-lg font-semibold">{value}</div>
            </div>
        </div>
    );
}

const number = (value: number) =>
    new Intl.NumberFormat(undefined, { maximumFractionDigits: 3 }).format(
        value,
    );
const money = (value: string) =>
    new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: 'EUR',
    }).format(Number(value));
const date = (value: string) =>
    new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(
        new Date(`${value.slice(0, 10)}T12:00:00`),
    );

SalesWorkflow.layout = {
    breadcrumbs: [
        { title: 'Sales', href: '/sales' },
        { title: 'Workflow', href: '/sales-workflow' },
    ],
};

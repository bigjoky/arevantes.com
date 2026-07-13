import { Form, Head, Link } from '@inertiajs/react';
import { ArrowLeft, Check, Clock3, Scale, ShoppingCart } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

type Request = {
    id: number;
    number: string;
    status: string;
    quoted_at: string | null;
    supplier_number: string | null;
    amount: string;
    subtotal: string;
    tax_amount: string;
    shipping_cost: string;
    delivery_days: number | null;
    valid_until: string | null;
    quote_notes: string | null;
    is_selected: boolean;
    supplier: { legal_name: string; trade_name: string | null };
    lines: {
        id: number;
        description: string;
        quantity: string;
        unit_price: string;
        estimated_unit_price: string | null;
        tax_percent: string;
        product: { name: string; sku: string | null };
    }[];
};

export default function PurchaseComparison({
    batch,
    salesOrder,
    requests,
}: {
    batch: string;
    salesOrder: { id: number; number: string } | null;
    requests: Request[];
}) {
    const quoted = requests.filter((request) => request.quoted_at !== null);
    const bestPrice = quoted.length
        ? Math.min(...quoted.map((request) => Number(request.amount)))
        : null;
    const fastest = quoted.length
        ? Math.min(
              ...quoted
                  .map((request) => request.delivery_days)
                  .filter((days): days is number => days !== null),
          )
        : null;

    return (
        <>
            <Head title="Supplier comparison" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <header className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold">
                            Supplier comparison
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {salesOrder
                                ? `Replenishment for ${salesOrder.number}`
                                : `Procurement round ${batch.slice(0, 8)}`}
                        </p>
                    </div>
                    <div className="flex gap-2">
                        {salesOrder && (
                            <Button variant="outline" asChild>
                                <Link href={`/sales/${salesOrder.id}`}>
                                    <ArrowLeft /> Sales order
                                </Link>
                            </Button>
                        )}
                        <Button variant="outline" asChild>
                            <Link href="/purchases">
                                <ShoppingCart /> Purchases
                            </Link>
                        </Button>
                    </div>
                </header>

                <section className="grid border-y sm:grid-cols-3">
                    <Metric
                        icon={Scale}
                        label="Best quoted total"
                        value={bestPrice === null ? '—' : money(bestPrice)}
                    />
                    <Metric
                        icon={Clock3}
                        label="Fastest delivery"
                        value={
                            fastest === null || !Number.isFinite(fastest)
                                ? '—'
                                : `${fastest} days`
                        }
                    />
                    <Metric
                        icon={Check}
                        label="Responses"
                        value={`${quoted.length} / ${requests.length}`}
                    />
                </section>

                <section>
                    <div className="border-b pb-3">
                        <h2 className="font-semibold">Supplier offers</h2>
                        <p className="text-sm text-muted-foreground">
                            Register each response and select the preferred
                            combination of price and delivery time.
                        </p>
                    </div>
                    {requests.map((request) => {
                        const supplier =
                            request.supplier.trade_name ||
                            request.supplier.legal_name;
                        const isBest =
                            bestPrice !== null &&
                            Number(request.amount) === bestPrice &&
                            quoted.includes(request);

                        return (
                            <div
                                key={request.id}
                                className={`grid gap-5 border-b py-5 xl:grid-cols-[minmax(13rem,.65fr)_minmax(0,1.35fr)] ${isBest ? 'border-l-2 border-l-green-600 pl-4 dark:border-l-green-400' : ''}`}
                            >
                                <div className="grid content-start gap-3">
                                    <div>
                                        <div className="flex flex-wrap items-center gap-2">
                                            <Link
                                                href={`/purchases/${request.id}`}
                                                className="font-medium underline underline-offset-2"
                                            >
                                                {supplier}
                                            </Link>
                                            <Badge variant="outline">
                                                {request.status}
                                            </Badge>
                                            {isBest && (
                                                <Badge variant="secondary">
                                                    Best price
                                                </Badge>
                                            )}
                                        </div>
                                        <div className="text-sm text-muted-foreground">
                                            {request.number}
                                            {request.supplier_number
                                                ? ` · ${request.supplier_number}`
                                                : ''}
                                        </div>
                                    </div>
                                    <div className="border-y text-sm">
                                        <Summary
                                            label="Total"
                                            value={money(request.amount)}
                                        />
                                        <Summary
                                            label="Transport"
                                            value={money(request.shipping_cost)}
                                        />
                                        <Summary
                                            label="Delivery"
                                            value={
                                                request.delivery_days === null
                                                    ? '—'
                                                    : `${request.delivery_days} days`
                                            }
                                        />
                                        <Summary
                                            label="Valid until"
                                            value={
                                                request.valid_until
                                                    ? date(request.valid_until)
                                                    : '—'
                                            }
                                        />
                                    </div>
                                    {request.status === 'quoted' && (
                                        <Form
                                            action={`/purchase-comparisons/requests/${request.id}/select`}
                                            method="post"
                                        >
                                            <Button>
                                                <Check /> Select offer
                                            </Button>
                                        </Form>
                                    )}
                                    {request.is_selected && (
                                        <div className="text-sm font-medium text-green-700 dark:text-green-400">
                                            Selected offer
                                        </div>
                                    )}
                                </div>

                                {['draft', 'sent', 'quoted'].includes(
                                    request.status,
                                ) && (
                                    <Form
                                        action={`/purchase-comparisons/requests/${request.id}`}
                                        method="patch"
                                        className="grid content-start gap-4 border-t pt-4 xl:border-t-0 xl:border-l xl:pt-0 xl:pl-5"
                                    >
                                        <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                                            <Field
                                                label="Supplier reference"
                                                name="supplier_number"
                                                value={
                                                    request.supplier_number ||
                                                    ''
                                                }
                                                required={false}
                                            />
                                            <Field
                                                label="Delivery days"
                                                name="delivery_days"
                                                type="number"
                                                value={String(
                                                    request.delivery_days ?? 0,
                                                )}
                                            />
                                            <Field
                                                label="Transport cost"
                                                name="shipping_cost"
                                                type="number"
                                                step="0.01"
                                                value={request.shipping_cost}
                                            />
                                            <Field
                                                label="Valid until"
                                                name="valid_until"
                                                type="date"
                                                value={
                                                    request.valid_until?.slice(
                                                        0,
                                                        10,
                                                    ) || ''
                                                }
                                                required={false}
                                            />
                                        </div>
                                        <div className="overflow-x-auto">
                                            <table className="w-full min-w-[38rem] text-sm">
                                                <thead>
                                                    <tr className="border-b text-left text-muted-foreground">
                                                        <th className="py-2">
                                                            Product
                                                        </th>
                                                        <th>Quantity</th>
                                                        <th>Estimate</th>
                                                        <th className="w-32">
                                                            Quoted price
                                                        </th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    {request.lines.map(
                                                        (line) => (
                                                            <tr
                                                                key={line.id}
                                                                className="border-b"
                                                            >
                                                                <td className="py-3 font-medium">
                                                                    {
                                                                        line.description
                                                                    }
                                                                </td>
                                                                <td>
                                                                    {Number(
                                                                        line.quantity,
                                                                    )}
                                                                </td>
                                                                <td>
                                                                    {money(
                                                                        line.estimated_unit_price ||
                                                                            line.unit_price,
                                                                    )}
                                                                </td>
                                                                <td>
                                                                    <Input
                                                                        name={`prices[${line.id}]`}
                                                                        type="number"
                                                                        min="0"
                                                                        step="0.01"
                                                                        defaultValue={
                                                                            line.unit_price
                                                                        }
                                                                        required
                                                                    />
                                                                </td>
                                                            </tr>
                                                        ),
                                                    )}
                                                </tbody>
                                            </table>
                                        </div>
                                        <div className="grid gap-2">
                                            <label className="text-sm font-medium">
                                                Notes
                                            </label>
                                            <textarea
                                                name="quote_notes"
                                                defaultValue={
                                                    request.quote_notes || ''
                                                }
                                                className="min-h-20 rounded-md border bg-transparent p-3 text-sm"
                                            />
                                        </div>
                                        <Button className="w-fit">
                                            Register response
                                        </Button>
                                    </Form>
                                )}
                            </div>
                        );
                    })}
                </section>
            </div>
        </>
    );
}

function Field({
    label,
    name,
    type = 'text',
    value,
    required = true,
    step,
}: {
    label: string;
    name: string;
    type?: string;
    value: string;
    required?: boolean;
    step?: string;
}) {
    return (
        <div className="grid gap-2">
            <label className="text-sm font-medium">{label}</label>
            <Input
                name={name}
                type={type}
                defaultValue={value}
                required={required}
                min={type === 'number' ? '0' : undefined}
                step={step}
            />
        </div>
    );
}

function Summary({ label, value }: { label: string; value: string }) {
    return (
        <div className="flex justify-between gap-3 border-b py-2 last:border-0">
            <span className="text-muted-foreground">{label}</span>
            <span className="font-medium">{value}</span>
        </div>
    );
}

function Metric({
    icon: Icon,
    label,
    value,
}: {
    icon: typeof Scale;
    label: string;
    value: string;
}) {
    return (
        <div className="flex items-center gap-3 border-b p-4 sm:border-r sm:border-b-0 sm:last:border-r-0">
            <Icon className="text-muted-foreground" />
            <div>
                <div className="text-xs text-muted-foreground">{label}</div>
                <div className="font-semibold">{value}</div>
            </div>
        </div>
    );
}

const money = (value: string | number) =>
    new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: 'EUR',
    }).format(Number(value));
const date = (value: string) =>
    new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(
        new Date(`${value.slice(0, 10)}T12:00:00`),
    );

PurchaseComparison.layout = {
    breadcrumbs: [
        { title: 'Purchases', href: '/purchases' },
        { title: 'Supplier comparison', href: '#' },
    ],
};

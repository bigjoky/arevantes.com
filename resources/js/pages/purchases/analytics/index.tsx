import { Head, Link } from '@inertiajs/react';
import { ChartNoAxesCombined, TrendingDown, TrendingUp } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

type Metrics = {
    period_spend: number;
    open_commitments: number;
    total_exposure: number;
    supplier_concentration: number | null;
    open_orders: number;
};
type Month = { month: string; label: string; amount: number };
type Supplier = {
    id: number;
    name: string;
    spend: number;
    commitment: number;
    exposure: number;
};
type Commitment = {
    id: number;
    number: string;
    supplier_id: number;
    supplier: string;
    status: string;
    eta: string | null;
    amount: number;
};
type PriceAlert = {
    supplier_product_id: number;
    supplier_id: number;
    supplier: string;
    product: string;
    sku: string | null;
    previous_price: number;
    current_price: number;
    change_percent: number;
    recorded_at: string;
};

export default function SupplyAnalyticsIndex({
    metrics,
    monthly_spend,
    suppliers,
    commitments,
    price_alerts,
    months,
}: {
    metrics: Metrics;
    monthly_spend: Month[];
    suppliers: Supplier[];
    commitments: Commitment[];
    price_alerts: PriceAlert[];
    months: number;
}) {
    const maxMonthly = Math.max(
        1,
        ...monthly_spend.map((month) => Math.abs(month.amount)),
    );
    const maxExposure = Math.max(
        1,
        ...suppliers.map((supplier) => supplier.exposure),
    );

    return (
        <>
            <Head title="Supply analytics" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <header className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold">
                            Supply analytics
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Net purchasing spend, uninvoiced commitments and
                            supplier price signals.
                        </p>
                    </div>
                    <div className="flex gap-1">
                        {[3, 6, 12].map((period) => (
                            <Button
                                key={period}
                                size="sm"
                                variant={
                                    months === period ? 'default' : 'outline'
                                }
                                asChild
                            >
                                <Link
                                    href={`/supply-analytics?months=${period}`}
                                >
                                    {period} months
                                </Link>
                            </Button>
                        ))}
                    </div>
                </header>
                <section className="grid border-y sm:grid-cols-2 lg:grid-cols-5">
                    <Metric
                        label={`${months}-month spend`}
                        value={money(metrics.period_spend)}
                    />
                    <Metric
                        label="Open commitments"
                        value={money(metrics.open_commitments)}
                    />
                    <Metric
                        label="Total exposure"
                        value={money(metrics.total_exposure)}
                    />
                    <Metric
                        label="Top supplier share"
                        value={
                            metrics.supplier_concentration === null
                                ? '—'
                                : `${metrics.supplier_concentration}%`
                        }
                    />
                    <Metric
                        label="Orders with commitment"
                        value={String(metrics.open_orders)}
                    />
                </section>
                <section className="border p-4">
                    <h2 className="font-semibold">Monthly net spend</h2>
                    <p className="text-sm text-muted-foreground">
                        Supplier invoices less credit notes.
                    </p>
                    <div
                        className="mt-6 grid h-56 items-end gap-2"
                        style={{
                            gridTemplateColumns: `repeat(${monthly_spend.length}, minmax(2.5rem, 1fr))`,
                        }}
                    >
                        {monthly_spend.map((month) => (
                            <div
                                key={month.month}
                                className="flex h-full min-w-0 flex-col justify-end"
                                title={`${month.label}: ${money(month.amount)}`}
                            >
                                <div className="mb-1 truncate text-center text-xs font-medium">
                                    {compactMoney(month.amount)}
                                </div>
                                <div
                                    className={`min-h-1 ${month.amount < 0 ? 'bg-destructive' : 'bg-primary'}`}
                                    style={{
                                        height: `${Math.max(2, (Math.abs(month.amount) / maxMonthly) * 160)}px`,
                                    }}
                                />
                                <div className="mt-2 truncate text-center text-[10px] text-muted-foreground">
                                    {month.label}
                                </div>
                            </div>
                        ))}
                    </div>
                </section>
                <div className="grid gap-6 xl:grid-cols-2">
                    <section>
                        <h2 className="font-semibold">Supplier exposure</h2>
                        <p className="mb-4 text-sm text-muted-foreground">
                            Period spend plus uninvoiced commitments.
                        </p>
                        <div className="grid gap-4">
                            {suppliers.map((supplier) => (
                                <Link
                                    key={supplier.id}
                                    href={`/supplier-intelligence/${supplier.id}`}
                                    className="block"
                                >
                                    <div className="mb-1 flex justify-between gap-3 text-sm">
                                        <span className="truncate font-medium">
                                            {supplier.name}
                                        </span>
                                        <span>{money(supplier.exposure)}</span>
                                    </div>
                                    <div className="h-2 bg-muted">
                                        <div
                                            className="h-full bg-primary"
                                            style={{
                                                width: `${(supplier.exposure / maxExposure) * 100}%`,
                                            }}
                                        />
                                    </div>
                                    <div className="mt-1 text-xs text-muted-foreground">
                                        {money(supplier.spend)} spend ·{' '}
                                        {money(supplier.commitment)} committed
                                    </div>
                                </Link>
                            ))}
                            {suppliers.length === 0 && (
                                <Empty text="No supplier exposure in this period." />
                            )}
                        </div>
                    </section>
                    <section>
                        <h2 className="font-semibold">Price watch</h2>
                        <p className="mb-4 text-sm text-muted-foreground">
                            Latest supplier price compared with its previous
                            recorded price.
                        </p>
                        <div className="border-y">
                            {price_alerts.map((alert) => (
                                <Link
                                    key={alert.supplier_product_id}
                                    href={`/supplier-intelligence/${alert.supplier_id}`}
                                    className="grid gap-2 border-b py-3 text-sm last:border-0 sm:grid-cols-[1fr_auto]"
                                >
                                    <div>
                                        <div className="font-medium">
                                            {alert.product}
                                        </div>
                                        <div className="text-xs text-muted-foreground">
                                            {alert.supplier}
                                            {alert.sku ? ` · ${alert.sku}` : ''}
                                        </div>
                                    </div>
                                    <div className="text-right">
                                        <Badge
                                            variant={
                                                alert.change_percent > 0
                                                    ? 'destructive'
                                                    : 'secondary'
                                            }
                                        >
                                            {alert.change_percent > 0 ? (
                                                <TrendingUp />
                                            ) : (
                                                <TrendingDown />
                                            )}
                                            {signed(alert.change_percent)}%
                                        </Badge>
                                        <div className="mt-1 text-xs text-muted-foreground">
                                            {money(alert.previous_price)} →{' '}
                                            {money(alert.current_price)}
                                        </div>
                                    </div>
                                </Link>
                            ))}
                            {price_alerts.length === 0 && (
                                <Empty text="Two price observations are needed before changes appear." />
                            )}
                        </div>
                    </section>
                </div>
                <section className="overflow-x-auto">
                    <h2 className="font-semibold">Open commitments</h2>
                    <p className="mb-4 text-sm text-muted-foreground">
                        Ordered value still awaiting supplier invoicing.
                    </p>
                    <table className="w-full min-w-[48rem] text-sm">
                        <thead>
                            <tr className="border-b text-left text-muted-foreground">
                                <th className="py-2">Order</th>
                                <th>Supplier</th>
                                <th>Status</th>
                                <th>Effective ETA</th>
                                <th className="text-right">Committed</th>
                            </tr>
                        </thead>
                        <tbody>
                            {commitments.map((commitment) => (
                                <tr key={commitment.id} className="border-b">
                                    <td className="py-3 font-medium">
                                        <Link
                                            href={`/purchases/${commitment.id}`}
                                            className="underline underline-offset-2"
                                        >
                                            {commitment.number}
                                        </Link>
                                    </td>
                                    <td>{commitment.supplier}</td>
                                    <td>
                                        <Badge variant="outline">
                                            {label(commitment.status)}
                                        </Badge>
                                    </td>
                                    <td>
                                        {commitment.eta
                                            ? date(commitment.eta)
                                            : '—'}
                                    </td>
                                    <td className="text-right font-medium">
                                        {money(commitment.amount)}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    {commitments.length === 0 && (
                        <Empty text="No uninvoiced purchase commitments." />
                    )}
                </section>
            </div>
        </>
    );
}

function Metric({ label, value }: { label: string; value: string }) {
    return (
        <div className="border-r p-4 last:border-0">
            <div className="text-sm text-muted-foreground">{label}</div>
            <div className="mt-1 text-xl font-semibold">{value}</div>
        </div>
    );
}

function Empty({ text }: { text: string }) {
    return (
        <div className="flex items-center justify-center gap-2 py-10 text-sm text-muted-foreground">
            <ChartNoAxesCombined className="size-5" />
            {text}
        </div>
    );
}

const money = (value: number) =>
    new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: 'EUR',
    }).format(value);
const compactMoney = (value: number) =>
    new Intl.NumberFormat(undefined, {
        notation: 'compact',
        maximumFractionDigits: 1,
    }).format(value);
const signed = (value: number) => (value > 0 ? `+${value}` : String(value));
const label = (value: string) =>
    value.replaceAll('_', ' ').replace(/^\w/, (letter) => letter.toUpperCase());
const date = (value: string) =>
    new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(
        new Date(`${value}T12:00:00`),
    );

SupplyAnalyticsIndex.layout = {
    breadcrumbs: [
        { title: 'Supply', href: '/purchases' },
        { title: 'Analytics', href: '/supply-analytics' },
    ],
};

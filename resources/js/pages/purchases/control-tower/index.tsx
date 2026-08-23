import { Form, Head, Link, usePage } from '@inertiajs/react';
import { CalendarClock, CircleAlert, Mail, PackageSearch } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

type Order = {
    id: number;
    number: string;
    status: string;
    issue_date: string;
    expected_delivery_date: string | null;
    supplier_confirmed_delivery_date: string | null;
    supplier_confirmed_at: string | null;
    supplier_confirmation_reference: string | null;
    last_followed_up_at: string | null;
    last_follow_up_note: string | null;
    next_follow_up_date: string | null;
    assigned_to: number | null;
    assigned_at: string | null;
    assignee: { id: number; name: string } | null;
    supplier: {
        legal_name: string;
        trade_name: string | null;
        email: string | null;
    };
    destination_warehouse: {
        id: number;
        code: string;
        name: string;
    } | null;
    ordered_quantity: number;
    received_quantity: number;
    receipt_progress: number;
    open_quality_incidents: number;
    days_late: number;
    days_until_eta: number | null;
    priority:
        | 'late'
        | 'quality'
        | 'follow_up_due'
        | 'unconfirmed'
        | 'due_soon'
        | 'on_track';
};

type MaterialRisk = {
    summary: {
        items: number;
        critical_items: number;
        blocked_work_orders: number;
        at_risk_work_orders: number;
        shortage_exposure: number | null;
        late_inbound_value: number | null;
    };
    scenario: {
        items_with_alternatives: number;
        items_without_alternatives: number;
        covered_on_time: number;
        recommended_net: number | null;
        recommended_cash: number | null;
        cash_within_30_days: number | null;
    };
    rows: {
        work_order_id: number;
        work_order_number: string;
        installation: string | null;
        scheduled_start: string;
        days_until_work: number;
        product_id: number;
        sku: string | null;
        product: string;
        criticality: 'low' | 'standard' | 'critical';
        required_quantity: number;
        reserved_quantity: number;
        free_quantity: number;
        inbound_before_work: number;
        shortage_quantity: number;
        shortage_exposure: number | null;
        late_inbound_value: number | null;
        severity: 'blocked' | 'critical' | 'warning' | 'covered';
        alternatives: SupplyAlternative[];
        recommended: SupplyAlternative | null;
    }[];
};

type SupplyAlternative = {
    supplier_id: number;
    supplier: string;
    supplier_product_id: number;
    is_preferred: boolean;
    lead_time_days: number;
    arrival_date: string;
    arrives_on_time: boolean;
    purchase_quantity: number;
    unit_price: number | null;
    price_source: 'supplier_history' | 'catalog_estimate';
    currency: string;
    net_cost: number | null;
    cash_impact: number | null;
    payment_date: string;
    payment_terms_days: number;
    overall_score: number | null;
    delivery_score: number | null;
    quality_score: number | null;
};

export default function SupplyControlTower({
    orders,
    counts,
    users,
    materialRisk,
}: {
    orders: Order[];
    counts: {
        open: number;
        late: number;
        unconfirmed: number;
        due_soon: number;
        follow_up_due: number;
        unassigned: number;
    };
    users: { id: number; name: string }[];
    materialRisk: MaterialRisk;
}) {
    const canManage = usePage<{
        auth: { abilities: { managePurchases: boolean } };
    }>().props.auth.abilities.managePurchases;

    return (
        <>
            <Head title="Supply order control" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <header>
                    <h1 className="text-xl font-semibold">
                        Supply order control
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Supplier confirmations, promised delivery dates and
                        receiving progress for every open order.
                    </p>
                </header>
                <section className="grid grid-cols-2 border-y lg:grid-cols-6">
                    <Metric label="Open orders" value={counts.open} />
                    <Metric label="Late" value={counts.late} />
                    <Metric label="Unconfirmed" value={counts.unconfirmed} />
                    <Metric
                        label="Follow-up due"
                        value={counts.follow_up_due}
                    />
                    <Metric label="Due within 7 days" value={counts.due_soon} />
                    <Metric label="Unassigned" value={counts.unassigned} />
                </section>
                <MaterialRiskPanel risk={materialRisk} />
                <section className="grid gap-4">
                    {orders.map((order) => (
                        <article key={order.id} className="border p-4">
                            <div className="grid gap-4 lg:grid-cols-[1.2fr_1fr_auto]">
                                <div>
                                    <div className="flex flex-wrap items-center gap-2">
                                        <Link
                                            href={`/purchases/${order.id}`}
                                            className="font-semibold underline underline-offset-2"
                                        >
                                            {order.number}
                                        </Link>
                                        <PriorityBadge
                                            priority={order.priority}
                                            daysLate={order.days_late}
                                        />
                                        {order.open_quality_incidents > 0 && (
                                            <Badge variant="destructive">
                                                {order.open_quality_incidents}{' '}
                                                quality issue
                                                {order.open_quality_incidents ===
                                                1
                                                    ? ''
                                                    : 's'}
                                            </Badge>
                                        )}
                                    </div>
                                    <div className="mt-1 text-sm">
                                        {order.supplier.trade_name ||
                                            order.supplier.legal_name}
                                    </div>
                                    <div className="text-sm text-muted-foreground">
                                        {order.destination_warehouse
                                            ? `${order.destination_warehouse.name} · `
                                            : ''}
                                        Issued {date(order.issue_date)}
                                    </div>
                                    <div className="mt-1 text-xs text-muted-foreground">
                                        Owner:{' '}
                                        <span className="font-medium text-foreground">
                                            {order.assignee?.name ||
                                                'Unassigned'}
                                        </span>
                                    </div>
                                </div>
                                <div className="grid grid-cols-2 gap-3 text-sm">
                                    <div>
                                        <div className="text-muted-foreground">
                                            Requested
                                        </div>
                                        <div className="font-medium">
                                            {order.expected_delivery_date
                                                ? date(
                                                      order.expected_delivery_date,
                                                  )
                                                : 'Not set'}
                                        </div>
                                    </div>
                                    <div>
                                        <div className="text-muted-foreground">
                                            Supplier ETA
                                        </div>
                                        <div className="font-medium">
                                            {order.supplier_confirmed_delivery_date
                                                ? date(
                                                      order.supplier_confirmed_delivery_date,
                                                  )
                                                : 'Unconfirmed'}
                                        </div>
                                    </div>
                                </div>
                                {order.supplier.email && (
                                    <Button variant="outline" size="sm" asChild>
                                        <a
                                            href={`mailto:${order.supplier.email}`}
                                        >
                                            <Mail /> Contact supplier
                                        </a>
                                    </Button>
                                )}
                            </div>
                            <div className="mt-4">
                                <div className="mb-1 flex justify-between text-xs text-muted-foreground">
                                    <span>Receiving progress</span>
                                    <span>
                                        {order.received_quantity}/
                                        {order.ordered_quantity} ·{' '}
                                        {order.receipt_progress}%
                                    </span>
                                </div>
                                <div className="h-2 overflow-hidden bg-muted">
                                    <div
                                        className="h-full bg-primary"
                                        style={{
                                            width: `${Math.min(100, order.receipt_progress)}%`,
                                        }}
                                    />
                                </div>
                            </div>
                            {(order.last_follow_up_note ||
                                order.supplier_confirmation_reference ||
                                order.next_follow_up_date) && (
                                <div className="mt-3 text-sm text-muted-foreground">
                                    {order.supplier_confirmation_reference
                                        ? `Ref. ${order.supplier_confirmation_reference}`
                                        : ''}
                                    {order.supplier_confirmation_reference &&
                                    order.last_follow_up_note
                                        ? ' · '
                                        : ''}
                                    {order.last_follow_up_note}
                                    {order.last_followed_up_at
                                        ? ` · ${dateTime(order.last_followed_up_at)}`
                                        : ''}
                                    {order.next_follow_up_date
                                        ? ` · Next contact ${date(order.next_follow_up_date)}`
                                        : ''}
                                </div>
                            )}
                            {canManage && (
                                <Form
                                    action={`/supply-control/${order.id}/assignment`}
                                    method="patch"
                                    className="mt-4 grid gap-3 border-t pt-3 lg:grid-cols-[14rem_11rem_1fr_auto]"
                                >
                                    <div className="grid gap-1">
                                        <label className="text-xs text-muted-foreground">
                                            Action owner
                                        </label>
                                        <select
                                            name="assigned_to"
                                            defaultValue={
                                                order.assigned_to || ''
                                            }
                                            className="h-9 border bg-transparent px-3 text-sm"
                                        >
                                            <option value="">Unassigned</option>
                                            {users.map((user) => (
                                                <option
                                                    key={user.id}
                                                    value={user.id}
                                                >
                                                    {user.name}
                                                </option>
                                            ))}
                                        </select>
                                    </div>
                                    <div className="grid gap-1">
                                        <label className="text-xs text-muted-foreground">
                                            Action due
                                        </label>
                                        <Input
                                            name="next_follow_up_date"
                                            type="date"
                                            defaultValue={
                                                order.next_follow_up_date || ''
                                            }
                                        />
                                    </div>
                                    <div className="grid gap-1">
                                        <label className="text-xs text-muted-foreground">
                                            Assignment note
                                        </label>
                                        <Input
                                            name="note"
                                            placeholder="Expected action or escalation"
                                        />
                                    </div>
                                    <Button
                                        variant="outline"
                                        className="self-end"
                                    >
                                        Assign action
                                    </Button>
                                </Form>
                            )}
                            {canManage && (
                                <details className="mt-4 border-t pt-3">
                                    <summary className="cursor-pointer text-sm font-medium">
                                        {order.supplier_confirmed_at
                                            ? 'Record supplier follow-up'
                                            : 'Record supplier confirmation'}
                                    </summary>
                                    <Form
                                        action={
                                            order.supplier_confirmed_at
                                                ? `/supply-control/${order.id}/follow-up`
                                                : `/supply-control/${order.id}/confirm`
                                        }
                                        method="post"
                                        className="mt-3 grid gap-3 lg:grid-cols-[12rem_11rem_14rem_1fr_auto]"
                                    >
                                        <div className="grid gap-1">
                                            <label className="text-xs text-muted-foreground">
                                                Confirmed delivery
                                            </label>
                                            <Input
                                                name="confirmed_delivery_date"
                                                type="date"
                                                required={
                                                    !order.supplier_confirmed_at
                                                }
                                                defaultValue={
                                                    order.supplier_confirmed_delivery_date ||
                                                    order.expected_delivery_date ||
                                                    ''
                                                }
                                            />
                                        </div>
                                        <div className="grid gap-1">
                                            <label className="text-xs text-muted-foreground">
                                                Next contact
                                            </label>
                                            <Input
                                                name="next_follow_up_date"
                                                type="date"
                                                defaultValue={
                                                    order.next_follow_up_date ||
                                                    daysFromNow(2)
                                                }
                                            />
                                        </div>
                                        <div className="grid gap-1">
                                            <label className="text-xs text-muted-foreground">
                                                Supplier reference
                                            </label>
                                            <Input
                                                name="confirmation_reference"
                                                defaultValue={
                                                    order.supplier_confirmation_reference ||
                                                    ''
                                                }
                                                placeholder="Email or case reference"
                                            />
                                        </div>
                                        <div className="grid gap-1">
                                            <label className="text-xs text-muted-foreground">
                                                Follow-up note
                                            </label>
                                            <Input
                                                name="note"
                                                required={
                                                    !!order.supplier_confirmed_at
                                                }
                                                placeholder="Supplier response or next action"
                                            />
                                        </div>
                                        <Button className="self-end">
                                            <CalendarClock />
                                            {order.supplier_confirmed_at
                                                ? 'Save follow-up'
                                                : 'Confirm ETA'}
                                        </Button>
                                    </Form>
                                </details>
                            )}
                        </article>
                    ))}
                    {orders.length === 0 && (
                        <div className="flex flex-col items-center gap-2 py-12 text-sm text-muted-foreground">
                            <PackageSearch className="size-6" />
                            No open purchase orders.
                        </div>
                    )}
                </section>
            </div>
        </>
    );
}

function MaterialRiskPanel({ risk }: { risk: MaterialRisk }) {
    return (
        <section className="overflow-hidden rounded-lg border">
            <div className="flex flex-wrap items-start justify-between gap-3 border-b p-4">
                <div>
                    <h2 className="font-semibold">Critical material outlook</h2>
                    <p className="text-xs text-muted-foreground">
                        Scheduled work matched against reservations, free stock
                        and inbound supply before its execution date.
                    </p>
                </div>
                <Badge
                    variant={
                        risk.summary.blocked_work_orders > 0
                            ? 'destructive'
                            : 'secondary'
                    }
                >
                    {risk.summary.blocked_work_orders} blocked work orders
                </Badge>
            </div>
            <div className="grid grid-cols-2 gap-px bg-border lg:grid-cols-4">
                <RiskMetric
                    label="At-risk work"
                    value={risk.summary.at_risk_work_orders}
                />
                <RiskMetric
                    label="Critical spares"
                    value={risk.summary.critical_items}
                />
                <RiskMetric
                    label="Shortage exposure"
                    value={money(risk.summary.shortage_exposure)}
                />
                <RiskMetric
                    label="Late inbound value"
                    value={money(risk.summary.late_inbound_value)}
                />
            </div>
            <div className="grid grid-cols-2 gap-px border-t bg-border lg:grid-cols-4">
                <RiskMetric
                    label="Alternatives found"
                    value={risk.scenario.items_with_alternatives}
                />
                <RiskMetric
                    label="Covered on time"
                    value={risk.scenario.covered_on_time}
                />
                <RiskMetric
                    label="Recommended cash"
                    value={money(risk.scenario.recommended_cash)}
                />
                <RiskMetric
                    label="Cash within 30 days"
                    value={money(risk.scenario.cash_within_30_days)}
                />
            </div>
            {risk.rows.length > 0 ? (
                <div className="divide-y">
                    {risk.rows.map((row) => (
                        <article
                            key={`${row.work_order_id}-${row.product_id}`}
                            className="grid gap-3 p-4 lg:grid-cols-[1.2fr_1fr_auto] lg:items-center"
                        >
                            <div>
                                <div className="flex flex-wrap items-center gap-2">
                                    <Link
                                        href={`/work-orders?work_order=${row.work_order_id}`}
                                        className="font-medium underline underline-offset-2"
                                    >
                                        {row.work_order_number}
                                    </Link>
                                    <Badge
                                        variant={
                                            row.severity === 'blocked' ||
                                            row.severity === 'critical'
                                                ? 'destructive'
                                                : row.severity === 'covered'
                                                  ? 'secondary'
                                                  : 'outline'
                                        }
                                    >
                                        {row.severity}
                                    </Badge>
                                    {row.criticality === 'critical' && (
                                        <Badge variant="outline">
                                            Critical spare
                                        </Badge>
                                    )}
                                </div>
                                <div className="mt-1 text-sm">
                                    {row.product}
                                    {row.sku ? ` · ${row.sku}` : ''}
                                </div>
                                <div className="text-xs text-muted-foreground">
                                    {row.installation || 'Installation'} ·{' '}
                                    {date(row.scheduled_start)}
                                </div>
                            </div>
                            <div className="grid grid-cols-2 gap-3 text-xs">
                                <div>
                                    <span className="text-muted-foreground">
                                        Required
                                    </span>
                                    <div className="font-medium">
                                        {row.required_quantity}
                                    </div>
                                </div>
                                <div>
                                    <span className="text-muted-foreground">
                                        Shortage
                                    </span>
                                    <div className="font-medium text-destructive">
                                        {row.shortage_quantity}
                                    </div>
                                </div>
                                <div>
                                    <span className="text-muted-foreground">
                                        Covered now
                                    </span>
                                    <div className="font-medium">
                                        {row.reserved_quantity +
                                            row.free_quantity}
                                    </div>
                                </div>
                                <div>
                                    <span className="text-muted-foreground">
                                        Inbound in time
                                    </span>
                                    <div className="font-medium">
                                        {row.inbound_before_work}
                                    </div>
                                </div>
                            </div>
                            <div className="text-right">
                                <div className="text-xs text-muted-foreground">
                                    Financial exposure
                                </div>
                                <div className="font-semibold">
                                    {money(row.shortage_exposure)}
                                </div>
                            </div>
                            {row.alternatives.length > 0 ? (
                                <details className="border-t pt-3 lg:col-span-3">
                                    <summary className="cursor-pointer text-sm font-medium">
                                        Compare {row.alternatives.length}{' '}
                                        supplier alternative
                                        {row.alternatives.length === 1
                                            ? ''
                                            : 's'}
                                    </summary>
                                    <div className="mt-3 overflow-x-auto">
                                        <table className="w-full min-w-[52rem] text-xs">
                                            <thead>
                                                <tr className="border-b text-left text-muted-foreground">
                                                    <th className="py-2">
                                                        Supplier
                                                    </th>
                                                    <th>Arrival</th>
                                                    <th>Quantity</th>
                                                    <th>Score</th>
                                                    <th className="text-right">
                                                        Net
                                                    </th>
                                                    <th className="text-right">
                                                        Cash / payment
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {row.alternatives.map(
                                                    (alternative) => (
                                                        <tr
                                                            key={
                                                                alternative.supplier_product_id
                                                            }
                                                            className="border-b last:border-0"
                                                        >
                                                            <td className="py-2 font-medium">
                                                                {
                                                                    alternative.supplier
                                                                }
                                                                {row.recommended
                                                                    ?.supplier_product_id ===
                                                                    alternative.supplier_product_id && (
                                                                    <Badge
                                                                        variant="secondary"
                                                                        className="ml-2"
                                                                    >
                                                                        Recommended
                                                                    </Badge>
                                                                )}
                                                            </td>
                                                            <td>
                                                                {date(
                                                                    alternative.arrival_date,
                                                                )}{' '}
                                                                ·{' '}
                                                                {alternative.arrives_on_time
                                                                    ? 'on time'
                                                                    : 'late'}
                                                            </td>
                                                            <td>
                                                                {
                                                                    alternative.purchase_quantity
                                                                }
                                                            </td>
                                                            <td>
                                                                {alternative.overall_score ===
                                                                null
                                                                    ? 'No history'
                                                                    : `${alternative.overall_score}/100`}
                                                            </td>
                                                            <td className="text-right">
                                                                {money(
                                                                    alternative.net_cost,
                                                                    alternative.currency,
                                                                )}
                                                            </td>
                                                            <td className="text-right">
                                                                {money(
                                                                    alternative.cash_impact,
                                                                )}{' '}
                                                                ·{' '}
                                                                {date(
                                                                    alternative.payment_date,
                                                                )}
                                                            </td>
                                                        </tr>
                                                    ),
                                                )}
                                            </tbody>
                                        </table>
                                    </div>
                                </details>
                            ) : null}
                        </article>
                    ))}
                </div>
            ) : (
                <div className="p-8 text-center text-sm text-muted-foreground">
                    No material risk for scheduled work in the next 45 days.
                </div>
            )}
        </section>
    );
}

function RiskMetric({
    label,
    value,
}: {
    label: string;
    value: string | number;
}) {
    return (
        <div className="bg-background p-4">
            <div className="text-xs text-muted-foreground">{label}</div>
            <div className="mt-1 text-xl font-semibold">{value}</div>
        </div>
    );
}

function Metric({ label, value }: { label: string; value: number }) {
    return (
        <div className="border-r p-4 last:border-0">
            <div className="text-sm text-muted-foreground">{label}</div>
            <div className="mt-1 text-2xl font-semibold">{value}</div>
        </div>
    );
}

function PriorityBadge({
    priority,
    daysLate,
}: {
    priority: Order['priority'];
    daysLate: number;
}) {
    if (priority === 'late') {
        return (
            <Badge variant="destructive">
                <CircleAlert /> {daysLate} day{daysLate === 1 ? '' : 's'} late
            </Badge>
        );
    }

    const labels: Record<Order['priority'], string> = {
        late: 'Late',
        quality: 'Quality attention',
        follow_up_due: 'Follow-up due',
        unconfirmed: 'Awaiting confirmation',
        due_soon: 'Due soon',
        on_track: 'On track',
    };

    return (
        <Badge variant={priority === 'on_track' ? 'secondary' : 'outline'}>
            {labels[priority]}
        </Badge>
    );
}

const date = (value: string) =>
    new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(
        new Date(`${value.slice(0, 10)}T12:00:00`),
    );
const dateTime = (value: string) =>
    new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
const money = (value: number | null, currency = 'EUR') =>
    value === null
        ? 'Restricted'
        : new Intl.NumberFormat(undefined, {
              style: 'currency',
              currency,
          }).format(value);
const daysFromNow = (days: number) => {
    const value = new Date();
    value.setDate(value.getDate() + days);

    return value.toISOString().slice(0, 10);
};

SupplyControlTower.layout = {
    breadcrumbs: [
        { title: 'Supply', href: '/purchases' },
        { title: 'Order control', href: '/supply-control' },
    ],
};

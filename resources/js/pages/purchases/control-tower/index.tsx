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
    priority: 'late' | 'quality' | 'unconfirmed' | 'due_soon' | 'on_track';
};

export default function SupplyControlTower({
    orders,
    counts,
}: {
    orders: Order[];
    counts: {
        open: number;
        late: number;
        unconfirmed: number;
        due_soon: number;
    };
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
                <section className="grid grid-cols-2 border-y lg:grid-cols-4">
                    <Metric label="Open orders" value={counts.open} />
                    <Metric label="Late" value={counts.late} />
                    <Metric label="Unconfirmed" value={counts.unconfirmed} />
                    <Metric label="Due within 7 days" value={counts.due_soon} />
                </section>
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
                                order.supplier_confirmation_reference) && (
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
                                </div>
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
                                        className="mt-3 grid gap-3 lg:grid-cols-[12rem_14rem_1fr_auto]"
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

SupplyControlTower.layout = {
    breadcrumbs: [
        { title: 'Supply', href: '/purchases' },
        { title: 'Order control', href: '/supply-control' },
    ],
};

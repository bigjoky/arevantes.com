import { Form, Head, Link } from '@inertiajs/react';
import { ClipboardList, Plus } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

type Warehouse = { id: number; code: string; name: string };
type Count = {
    id: number;
    number: string;
    mode: 'quantity' | 'serial';
    status: 'draft' | 'posted';
    notes: string | null;
    started_at: string;
    posted_at: string | null;
    lines_count: number;
    discrepancy_lines_count: number;
    warehouse: Warehouse;
    creator: { id: number; name: string } | null;
    poster: { id: number; name: string } | null;
    movement: { id: number; number: string } | null;
};

export default function StockCountsIndex({
    counts,
    warehouses,
}: {
    counts: Count[];
    warehouses: Warehouse[];
}) {
    const open = counts.filter((count) => count.status === 'draft').length;
    const posted = counts.filter((count) => count.status === 'posted').length;

    return (
        <>
            <Head title="Stock counts" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <header>
                    <h1 className="text-xl font-semibold">Stock counts</h1>
                    <p className="text-sm text-muted-foreground">
                        Reconcile physical quantities with the immutable stock
                        ledger.
                    </p>
                </header>
                <section className="grid border-y sm:grid-cols-3">
                    <Metric label="Open counts" value={open} />
                    <Metric label="Posted counts" value={posted} />
                    <Metric
                        label="Discrepancies posted"
                        value={counts.reduce(
                            (total, count) =>
                                total + count.discrepancy_lines_count,
                            0,
                        )}
                    />
                </section>
                <details className="border p-4">
                    <summary className="cursor-pointer font-medium">
                        Start stock count
                    </summary>
                    <Form
                        action="/inventory/counts"
                        method="post"
                        className="mt-4 grid gap-4 sm:grid-cols-[1fr_1fr_2fr_auto]"
                    >
                        <div className="grid gap-2">
                            <label className="text-sm font-medium">
                                Warehouse
                            </label>
                            <select
                                name="warehouse_id"
                                required
                                className="h-9 border bg-transparent px-3 text-sm"
                            >
                                {warehouses.map((warehouse) => (
                                    <option
                                        key={warehouse.id}
                                        value={warehouse.id}
                                    >
                                        {warehouse.name} ({warehouse.code})
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div className="grid gap-2">
                            <label className="text-sm font-medium">
                                Count type
                            </label>
                            <select
                                name="mode"
                                required
                                defaultValue="quantity"
                                className="h-9 border bg-transparent px-3 text-sm"
                            >
                                <option value="quantity">Quantities</option>
                                <option value="serial">Serial numbers</option>
                            </select>
                        </div>
                        <div className="grid gap-2">
                            <label className="text-sm font-medium">Notes</label>
                            <input
                                name="notes"
                                className="h-9 border bg-transparent px-3 text-sm"
                                placeholder="Area, shelf or count instructions"
                            />
                        </div>
                        <Button className="self-end">
                            <Plus /> Start count
                        </Button>
                    </Form>
                    <p className="mt-3 text-xs text-muted-foreground">
                        Use separate sessions for quantity-tracked and
                        serial-number-tracked products.
                    </p>
                </details>
                <section className="overflow-x-auto">
                    <table className="w-full min-w-[58rem] text-sm">
                        <thead>
                            <tr className="border-b text-left text-muted-foreground">
                                <th className="py-2">Count</th>
                                <th>Warehouse</th>
                                <th>Type</th>
                                <th>Started</th>
                                <th>Status</th>
                                <th>Products</th>
                                <th>Discrepancies</th>
                                <th>Movement</th>
                                <th />
                            </tr>
                        </thead>
                        <tbody>
                            {counts.map((count) => (
                                <tr key={count.id} className="border-b">
                                    <td className="py-3 font-medium">
                                        {count.number}
                                    </td>
                                    <td>
                                        {count.warehouse.name}{' '}
                                        <span className="text-xs text-muted-foreground">
                                            ({count.warehouse.code})
                                        </span>
                                    </td>
                                    <td>
                                        {count.mode === 'serial'
                                            ? 'Serial audit'
                                            : 'Quantity count'}
                                    </td>
                                    <td>{dateTime(count.started_at)}</td>
                                    <td>
                                        <Badge
                                            variant={
                                                count.status === 'posted'
                                                    ? 'secondary'
                                                    : 'outline'
                                            }
                                        >
                                            {count.status === 'posted'
                                                ? 'Posted'
                                                : 'Open'}
                                        </Badge>
                                    </td>
                                    <td>{count.lines_count}</td>
                                    <td>{count.discrepancy_lines_count}</td>
                                    <td>{count.movement?.number || '—'}</td>
                                    <td className="text-right">
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            asChild
                                        >
                                            <Link
                                                href={`/inventory/counts/${count.id}`}
                                            >
                                                {count.status === 'draft'
                                                    ? 'Count'
                                                    : 'Review'}
                                            </Link>
                                        </Button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    {counts.length === 0 && (
                        <div className="flex flex-col items-center gap-2 py-12 text-sm text-muted-foreground">
                            <ClipboardList className="size-6" />
                            No stock counts yet.
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

const dateTime = (value: string) =>
    new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));

StockCountsIndex.layout = {
    breadcrumbs: [
        { title: 'Supply', href: '/inventory' },
        { title: 'Stock counts', href: '/inventory/counts' },
    ],
};

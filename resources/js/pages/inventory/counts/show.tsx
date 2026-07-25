import { Form, Head, Link } from '@inertiajs/react';
import { ArrowLeft, CheckCheck, Save, Trash2 } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

type CountLine = {
    id: number;
    system_quantity: string;
    counted_quantity: string | null;
    difference_quantity: string | null;
    unit_cost: string | null;
    product: {
        id: number;
        sku: string | null;
        name: string;
        cost_price: string;
    };
};
type Count = {
    id: number;
    number: string;
    status: 'draft' | 'posted';
    notes: string | null;
    started_at: string;
    posted_at: string | null;
    warehouse: { id: number; code: string; name: string };
    creator: { id: number; name: string } | null;
    poster: { id: number; name: string } | null;
    movement: { id: number; number: string } | null;
    lines: CountLine[];
};

export default function StockCountShow({ count }: { count: Count }) {
    const differences = count.lines.filter(
        (line) => Math.abs(difference(line)) > 0.0005,
    );
    const complete = count.lines.every(
        (line) => line.counted_quantity !== null,
    );

    return (
        <>
            <Head title={count.number} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <header className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <div className="flex items-center gap-2">
                            <h1 className="text-xl font-semibold">
                                {count.number}
                            </h1>
                            <Badge
                                variant={
                                    count.status === 'posted'
                                        ? 'secondary'
                                        : 'outline'
                                }
                            >
                                {count.status === 'posted' ? 'Posted' : 'Open'}
                            </Badge>
                        </div>
                        <p className="text-sm text-muted-foreground">
                            {count.warehouse.name} ({count.warehouse.code}) ·
                            started {dateTime(count.started_at)}
                        </p>
                    </div>
                    <Button variant="outline" asChild>
                        <Link href="/inventory/counts">
                            <ArrowLeft /> Stock counts
                        </Link>
                    </Button>
                </header>
                <section className="grid border-y sm:grid-cols-4">
                    <Metric
                        label="Products"
                        value={String(count.lines.length)}
                    />
                    <Metric
                        label="Counted"
                        value={String(
                            count.lines.filter(
                                (line) => line.counted_quantity !== null,
                            ).length,
                        )}
                    />
                    <Metric
                        label="Differences"
                        value={String(differences.length)}
                    />
                    <Metric
                        label="Value difference"
                        value={money(
                            count.lines.reduce(
                                (total, line) =>
                                    total +
                                    difference(line) *
                                        Number(
                                            line.unit_cost ||
                                                line.product.cost_price,
                                        ),
                                0,
                            ),
                        )}
                    />
                </section>
                {count.notes && (
                    <p className="border-l-2 pl-3 text-sm text-muted-foreground">
                        {count.notes}
                    </p>
                )}
                <Form
                    action={`/inventory/counts/${count.id}`}
                    method="patch"
                    className="grid gap-4"
                >
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[52rem] text-sm">
                            <thead>
                                <tr className="border-b text-left text-muted-foreground">
                                    <th className="py-2">Product</th>
                                    <th>SKU</th>
                                    <th className="text-right">System</th>
                                    <th className="w-36 text-right">Counted</th>
                                    <th className="text-right">Difference</th>
                                    <th className="text-right">Value</th>
                                </tr>
                            </thead>
                            <tbody>
                                {count.lines.map((line) => {
                                    const variance = difference(line);

                                    return (
                                        <tr key={line.id} className="border-b">
                                            <td className="py-3 font-medium">
                                                {line.product.name}
                                            </td>
                                            <td className="font-mono text-xs">
                                                {line.product.sku || '—'}
                                            </td>
                                            <td className="text-right">
                                                {Number(line.system_quantity)}
                                            </td>
                                            <td className="p-2">
                                                {count.status === 'draft' ? (
                                                    <Input
                                                        name={`quantities[${line.id}]`}
                                                        type="number"
                                                        min="0"
                                                        step="0.001"
                                                        defaultValue={
                                                            line.counted_quantity ??
                                                            ''
                                                        }
                                                        className="text-right"
                                                    />
                                                ) : (
                                                    <div className="text-right">
                                                        {Number(
                                                            line.counted_quantity,
                                                        )}
                                                    </div>
                                                )}
                                            </td>
                                            <td
                                                className={`text-right font-medium ${variance < 0 ? 'text-destructive' : variance > 0 ? 'text-emerald-600' : ''}`}
                                            >
                                                {line.counted_quantity === null
                                                    ? '—'
                                                    : signed(variance)}
                                            </td>
                                            <td className="text-right">
                                                {line.counted_quantity === null
                                                    ? '—'
                                                    : money(
                                                          variance *
                                                              Number(
                                                                  line.unit_cost ||
                                                                      line
                                                                          .product
                                                                          .cost_price,
                                                              ),
                                                      )}
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                    {count.status === 'draft' && (
                        <div className="flex flex-wrap justify-end gap-2 border-t pt-4">
                            <Button variant="outline">
                                <Save /> Save quantities
                            </Button>
                        </div>
                    )}
                </Form>
                {count.status === 'draft' && (
                    <section className="flex flex-wrap items-center justify-between gap-3 border-t pt-4">
                        <p className="text-sm text-muted-foreground">
                            Posting creates one immutable adjustment movement.
                            Stock must not have changed since the count started.
                        </p>
                        <div className="flex gap-2">
                            <Form
                                action={`/inventory/counts/${count.id}`}
                                method="delete"
                            >
                                <Button variant="outline">
                                    <Trash2 /> Discard
                                </Button>
                            </Form>
                            <Form
                                action={`/inventory/counts/${count.id}/post`}
                                method="post"
                            >
                                <Button disabled={!complete}>
                                    <CheckCheck /> Post count
                                </Button>
                            </Form>
                        </div>
                    </section>
                )}
                {count.status === 'posted' && (
                    <section className="border-t pt-4 text-sm">
                        Posted{' '}
                        {count.posted_at ? dateTime(count.posted_at) : ''}
                        {count.poster ? ` by ${count.poster.name}` : ''}
                        {count.movement
                            ? ` · movement ${count.movement.number}`
                            : ' · no adjustment required'}
                    </section>
                )}
            </div>
        </>
    );
}

function Metric({ label, value }: { label: string; value: string }) {
    return (
        <div className="border-r p-4 last:border-0">
            <div className="text-sm text-muted-foreground">{label}</div>
            <div className="mt-1 text-2xl font-semibold">{value}</div>
        </div>
    );
}

const difference = (line: CountLine) =>
    line.difference_quantity !== null
        ? Number(line.difference_quantity)
        : line.counted_quantity !== null
          ? Number(line.counted_quantity) - Number(line.system_quantity)
          : 0;
const signed = (value: number) => (value > 0 ? `+${value}` : String(value));
const money = (value: number) =>
    new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: 'EUR',
    }).format(value);
const dateTime = (value: string) =>
    new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));

StockCountShow.layout = {
    breadcrumbs: [
        { title: 'Supply', href: '/inventory' },
        { title: 'Stock counts', href: '/inventory/counts' },
        { title: 'Count', href: '#' },
    ],
};

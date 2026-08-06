import { Head, Link, router } from '@inertiajs/react';
import { ArrowRight, Barcode, MapPin, Search } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

type Warehouse = { id: number; code: string; name: string };
type Installation = {
    id: number;
    project_reference: string | null;
    site_name: string | null;
};
type Event = {
    id: number;
    event_type: string;
    occurred_at: string;
    from_warehouse: Warehouse | null;
    to_warehouse: Warehouse | null;
    installation: Installation | null;
    movement: {
        number: string;
        type: string;
        reference: string | null;
        creator: { name: string } | null;
        purchase_document: {
            id: number;
            number: string;
            supplier: {
                legal_name: string;
                trade_name: string | null;
            } | null;
        } | null;
        sales_document: { id: number; number: string } | null;
        work_order: { id: number; number: string } | null;
    } | null;
};
type Serial = {
    id: number;
    serial_number: string;
    status: string;
    received_at: string | null;
    issued_at: string | null;
    warranty_starts_on: string | null;
    warranty_ends_on: string | null;
    product: { id: number; sku: string | null; name: string };
    warehouse: Warehouse | null;
    installation: Installation | null;
    events: Event[];
};
type Page<T> = {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
    from: number | null;
    to: number | null;
    total: number;
};

const eventLabels: Record<string, string> = {
    received: 'Received',
    recovered: 'Recovered',
    transferred: 'Transferred',
    installed: 'Installed',
    issued: 'Issued',
    returned: 'Returned',
    missing: 'Marked missing',
};

export default function SerialTraceability({
    serials,
    counts,
    filters,
}: {
    serials: Page<Serial>;
    counts: Record<string, number>;
    filters: { search?: string; status?: string };
}) {
    const [search, setSearch] = useState(filters.search || '');
    const [status, setStatus] = useState(filters.status || 'all');

    const submit = (event: FormEvent) => {
        event.preventDefault();
        router.get(
            '/inventory/serials',
            {
                search: search || undefined,
                status: status === 'all' ? undefined : status,
            },
            { preserveState: true, replace: true },
        );
    };

    return (
        <>
            <Head title="Serial traceability" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <header>
                    <h1 className="text-xl font-semibold">
                        Serial traceability
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Follow every serialized unit from its supplier receipt
                        through transfers to its final installation.
                    </p>
                </header>

                <section className="grid grid-cols-2 border-y sm:grid-cols-5">
                    {[
                        'available',
                        'installed',
                        'issued',
                        'returned',
                        'missing',
                    ].map((item) => (
                        <button
                            key={item}
                            type="button"
                            className="border-r p-4 text-left transition-colors last:border-r-0 hover:bg-muted/40"
                            onClick={() => {
                                setStatus(item);
                                router.get(
                                    '/inventory/serials',
                                    { status: item },
                                    { preserveState: true },
                                );
                            }}
                        >
                            <div className="text-sm text-muted-foreground capitalize">
                                {item}
                            </div>
                            <div className="mt-1 text-2xl font-semibold">
                                {counts[item] || 0}
                            </div>
                        </button>
                    ))}
                </section>

                <form
                    onSubmit={submit}
                    className="flex flex-col gap-3 sm:flex-row"
                >
                    <div className="relative flex-1">
                        <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            className="pl-9"
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder="Search serial, SKU or product"
                        />
                    </div>
                    <Select value={status} onValueChange={setStatus}>
                        <SelectTrigger className="w-full sm:w-48">
                            <SelectValue placeholder="All statuses" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All statuses</SelectItem>
                            {[
                                'available',
                                'installed',
                                'issued',
                                'returned',
                                'missing',
                            ].map((item) => (
                                <SelectItem
                                    key={item}
                                    value={item}
                                    className="capitalize"
                                >
                                    {item}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <Button type="submit">Search</Button>
                </form>

                <section className="overflow-hidden border">
                    {serials.data.map((serial) => (
                        <details
                            key={serial.id}
                            className="group border-b last:border-b-0"
                        >
                            <summary className="grid cursor-pointer list-none gap-3 p-4 transition-colors hover:bg-muted/30 md:grid-cols-[1.2fr_1.4fr_1fr_1.5fr] md:items-center">
                                <div>
                                    <div className="flex items-center gap-2 font-medium">
                                        <Barcode className="size-4 text-muted-foreground" />
                                        {serial.serial_number}
                                    </div>
                                    <Badge
                                        variant="outline"
                                        className="mt-2 capitalize"
                                    >
                                        {serial.status}
                                    </Badge>
                                </div>
                                <div>
                                    <div className="font-medium">
                                        {serial.product.name}
                                    </div>
                                    <div className="text-sm text-muted-foreground">
                                        {serial.product.sku || 'No SKU'}
                                    </div>
                                </div>
                                <Origin
                                    event={serial.events.find(
                                        (item) =>
                                            item.event_type === 'received',
                                    )}
                                />
                                <CurrentLocation serial={serial} />
                            </summary>
                            <div className="border-t bg-muted/20 px-4 py-5">
                                <h2 className="mb-4 text-sm font-medium">
                                    Unit history
                                </h2>
                                {serial.events.length > 0 ? (
                                    <ol className="space-y-4">
                                        {serial.events.map((item, index) => (
                                            <li
                                                key={item.id}
                                                className="grid grid-cols-[1.5rem_1fr] gap-3"
                                            >
                                                <div className="flex flex-col items-center">
                                                    <span className="mt-1 size-2.5 rounded-full bg-primary" />
                                                    {index <
                                                        serial.events.length -
                                                            1 && (
                                                        <span className="mt-1 h-full w-px bg-border" />
                                                    )}
                                                </div>
                                                <div className="pb-2">
                                                    <div className="flex flex-wrap items-center gap-2">
                                                        <span className="font-medium">
                                                            {eventLabels[
                                                                item.event_type
                                                            ] ||
                                                                item.event_type}
                                                        </span>
                                                        <span className="text-sm text-muted-foreground">
                                                            {formatDate(
                                                                item.occurred_at,
                                                            )}
                                                        </span>
                                                        {item.movement && (
                                                            <Badge variant="secondary">
                                                                {
                                                                    item
                                                                        .movement
                                                                        .number
                                                                }
                                                            </Badge>
                                                        )}
                                                    </div>
                                                    <EventDetails
                                                        event={item}
                                                    />
                                                </div>
                                            </li>
                                        ))}
                                    </ol>
                                ) : (
                                    <p className="text-sm text-muted-foreground">
                                        No historical events are available for
                                        this unit.
                                    </p>
                                )}
                            </div>
                        </details>
                    ))}
                    {serials.data.length === 0 && (
                        <div className="flex flex-col items-center gap-2 py-14 text-sm text-muted-foreground">
                            <Barcode className="size-6" />
                            No serialized units match these filters.
                        </div>
                    )}
                </section>

                {serials.links.length > 3 && (
                    <nav className="flex flex-wrap items-center justify-between gap-3 text-sm">
                        <span className="text-muted-foreground">
                            {serials.from || 0}–{serials.to || 0} of{' '}
                            {serials.total}
                        </span>
                        <div className="flex flex-wrap gap-1">
                            {serials.links.map((link, index) =>
                                link.url ? (
                                    <Button
                                        key={index}
                                        size="sm"
                                        variant={
                                            link.active ? 'default' : 'outline'
                                        }
                                        asChild
                                    >
                                        <Link
                                            href={link.url}
                                            dangerouslySetInnerHTML={{
                                                __html: link.label,
                                            }}
                                        />
                                    </Button>
                                ) : null,
                            )}
                        </div>
                    </nav>
                )}
            </div>
        </>
    );
}

function Origin({ event }: { event?: Event }) {
    const purchase = event?.movement?.purchase_document;

    if (!purchase) {
        return (
            <div className="text-sm text-muted-foreground">
                Origin not linked
            </div>
        );
    }

    const supplier =
        purchase.supplier?.trade_name || purchase.supplier?.legal_name;

    return (
        <div className="text-sm">
            <div className="text-muted-foreground">Origin</div>
            <div className="font-medium">{supplier || 'Supplier'}</div>
            <Link
                className="text-primary hover:underline"
                href={`/purchases/${purchase.id}`}
            >
                {purchase.number}
            </Link>
        </div>
    );
}

function CurrentLocation({ serial }: { serial: Serial }) {
    if (serial.installation) {
        return (
            <div className="text-sm">
                <div className="flex items-center gap-1 text-muted-foreground">
                    <MapPin className="size-3.5" /> Installed at
                </div>
                <Link
                    className="font-medium text-primary hover:underline"
                    href={`/installations/${serial.installation.id}`}
                >
                    {installationName(serial.installation)}
                </Link>
            </div>
        );
    }

    if (serial.warehouse) {
        return (
            <div className="text-sm">
                <div className="text-muted-foreground">Current warehouse</div>
                <div className="font-medium">
                    {serial.warehouse.code} · {serial.warehouse.name}
                </div>
            </div>
        );
    }

    return (
        <div className="text-sm text-muted-foreground">No active location</div>
    );
}

function EventDetails({ event }: { event: Event }) {
    const movement = event.movement;
    const purchase = movement?.purchase_document;

    return (
        <div className="mt-1 flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
            {event.from_warehouse && <span>{event.from_warehouse.code}</span>}
            {event.from_warehouse &&
                (event.to_warehouse || event.installation) && (
                    <ArrowRight className="size-3.5" />
                )}
            {event.to_warehouse && (
                <span>
                    {event.to_warehouse.code} · {event.to_warehouse.name}
                </span>
            )}
            {event.installation && (
                <Link
                    className="text-primary hover:underline"
                    href={`/installations/${event.installation.id}`}
                >
                    {installationName(event.installation)}
                </Link>
            )}
            {purchase && (
                <span>
                    from{' '}
                    <Link
                        className="text-primary hover:underline"
                        href={`/purchases/${purchase.id}`}
                    >
                        {purchase.number}
                    </Link>
                    {purchase.supplier
                        ? ` · ${purchase.supplier.trade_name || purchase.supplier.legal_name}`
                        : ''}
                </span>
            )}
            {movement?.sales_document && (
                <span>· Delivery {movement.sales_document.number}</span>
            )}
            {movement?.work_order && (
                <span>· Work order {movement.work_order.number}</span>
            )}
            {movement?.creator && <span>· {movement.creator.name}</span>}
        </div>
    );
}

const installationName = (installation: Installation) =>
    installation.project_reference ||
    installation.site_name ||
    `Installation #${installation.id}`;

const formatDate = (value: string) =>
    new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));

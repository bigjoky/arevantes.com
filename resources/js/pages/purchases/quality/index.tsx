import { Form, Head, Link, usePage } from '@inertiajs/react';
import { CircleAlert, RotateCcw, ShieldCheck } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

type Incident = {
    id: number;
    rejected_quantity: string;
    reason: string;
    severity: 'minor' | 'major' | 'critical';
    notes: string | null;
    status: 'open' | 'resolved';
    resolution: string | null;
    resolution_notes: string | null;
    created_at: string;
    resolved_at: string | null;
    purchase_document: {
        id: number;
        number: string;
        supplier: {
            legal_name: string;
            trade_name: string | null;
        };
    };
    product: { id: number; sku: string | null; name: string };
    warehouse: { id: number; code: string; name: string };
    reporter: { id: number; name: string } | null;
    resolver: { id: number; name: string } | null;
};
type PageLink = {
    url: string | null;
    label: string;
    active: boolean;
};
type Paginated = {
    data: Incident[];
    links: PageLink[];
    from: number | null;
    to: number | null;
    total: number;
};

export default function SupplierQualityIndex({
    incidents,
    filters,
    counts,
}: {
    incidents: Paginated;
    filters: { search?: string; status?: string; severity?: string };
    counts: { open: number; critical: number; resolved: number };
}) {
    const canManage = usePage<{
        auth: { abilities: { managePurchases: boolean } };
    }>().props.auth.abilities.managePurchases;

    return (
        <>
            <Head title="Supplier quality claims" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <header>
                    <h1 className="text-xl font-semibold">
                        Supplier quality claims
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Track rejected deliveries through replacement, credit or
                        final disposition.
                    </p>
                </header>
                <section className="grid border-y sm:grid-cols-3">
                    <Metric label="Open" value={counts.open} />
                    <Metric label="Critical open" value={counts.critical} />
                    <Metric label="Resolved" value={counts.resolved} />
                </section>
                <Form
                    action="/supplier-quality"
                    method="get"
                    className="grid gap-3 sm:grid-cols-[1fr_10rem_10rem_auto]"
                >
                    <Input
                        name="search"
                        defaultValue={filters.search}
                        placeholder="Supplier, order or product"
                    />
                    <select
                        name="status"
                        defaultValue={filters.status || ''}
                        className="h-9 border bg-transparent px-3 text-sm"
                    >
                        <option value="">All statuses</option>
                        <option value="open">Open</option>
                        <option value="resolved">Resolved</option>
                    </select>
                    <select
                        name="severity"
                        defaultValue={filters.severity || ''}
                        className="h-9 border bg-transparent px-3 text-sm"
                    >
                        <option value="">All severities</option>
                        <option value="minor">Minor</option>
                        <option value="major">Major</option>
                        <option value="critical">Critical</option>
                    </select>
                    <Button variant="outline">Filter</Button>
                </Form>
                <section className="grid gap-4">
                    {incidents.data.map((incident) => (
                        <article key={incident.id} className="border p-4">
                            <div className="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <Link
                                        href={`/purchases/${incident.purchase_document.id}`}
                                        className="font-semibold underline underline-offset-2"
                                    >
                                        {incident.purchase_document.number} ·{' '}
                                        {incident.purchase_document.supplier
                                            .trade_name ||
                                            incident.purchase_document.supplier
                                                .legal_name}
                                    </Link>
                                    <div className="mt-1 text-sm">
                                        {incident.product.name} ·{' '}
                                        {incident.rejected_quantity} rejected ·{' '}
                                        {incident.warehouse.name}
                                    </div>
                                    <div className="mt-1 text-sm text-muted-foreground">
                                        {label(incident.reason)}
                                        {incident.notes
                                            ? ` · ${incident.notes}`
                                            : ''}
                                    </div>
                                </div>
                                <div className="flex gap-2">
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
                            {incident.status === 'open' && canManage ? (
                                <Form
                                    action={`/supplier-quality/${incident.id}/resolve`}
                                    method="post"
                                    className="mt-4 grid gap-3 border-t pt-4 md:grid-cols-[12rem_1fr_auto]"
                                >
                                    <select
                                        name="resolution"
                                        required
                                        defaultValue=""
                                        className="h-9 border bg-transparent px-3 text-sm"
                                    >
                                        <option value="" disabled>
                                            Select resolution
                                        </option>
                                        <option value="replacement">
                                            Replacement
                                        </option>
                                        <option value="credit_note">
                                            Credit note
                                        </option>
                                        <option value="refund">Refund</option>
                                        <option value="accepted_as_is">
                                            Accepted as-is
                                        </option>
                                        <option value="discarded">
                                            Discarded
                                        </option>
                                        <option value="other">Other</option>
                                    </select>
                                    <Input
                                        name="resolution_notes"
                                        required
                                        placeholder="Resolution reference and notes"
                                    />
                                    <Button>
                                        <ShieldCheck /> Resolve
                                    </Button>
                                </Form>
                            ) : incident.status === 'resolved' ? (
                                <div className="mt-4 flex flex-wrap items-center justify-between gap-3 border-t pt-4 text-sm">
                                    <div>
                                        <span className="font-medium">
                                            {label(
                                                incident.resolution || 'other',
                                            )}
                                        </span>
                                        {incident.resolution_notes
                                            ? ` · ${incident.resolution_notes}`
                                            : ''}
                                    </div>
                                    {canManage && (
                                        <Form
                                            action={`/supplier-quality/${incident.id}/reopen`}
                                            method="post"
                                        >
                                            <Button size="sm" variant="outline">
                                                <RotateCcw /> Reopen
                                            </Button>
                                        </Form>
                                    )}
                                </div>
                            ) : null}
                        </article>
                    ))}
                    {incidents.data.length === 0 && (
                        <div className="flex flex-col items-center gap-2 py-12 text-sm text-muted-foreground">
                            <CircleAlert className="size-6" />
                            No quality incidents match these filters.
                        </div>
                    )}
                </section>
                {incidents.links.length > 3 && (
                    <nav className="flex flex-wrap items-center justify-between gap-3 text-sm">
                        <span className="text-muted-foreground">
                            {incidents.from ?? 0}–{incidents.to ?? 0} of{' '}
                            {incidents.total}
                        </span>
                        <div className="flex flex-wrap gap-1">
                            {incidents.links.map((link, index) =>
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

function Metric({ label, value }: { label: string; value: number }) {
    return (
        <div className="p-4">
            <div className="text-sm text-muted-foreground">{label}</div>
            <div className="mt-1 text-2xl font-semibold">{value}</div>
        </div>
    );
}

const label = (value: string) =>
    value.replaceAll('_', ' ').replace(/^\w/, (letter) => letter.toUpperCase());

SupplierQualityIndex.layout = {
    breadcrumbs: [
        { title: 'Supply', href: '/purchases' },
        { title: 'Quality claims', href: '/supplier-quality' },
    ],
};

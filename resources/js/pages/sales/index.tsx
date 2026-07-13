import { Head, Link, router } from '@inertiajs/react';
import {
    CircleDollarSign,
    Clock3,
    FileCheck2,
    Plus,
    Search,
    Workflow,
} from 'lucide-react';
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

type Document = {
    id: number;
    type: string;
    number: string;
    status: string;
    fulfillment_status: string | null;
    billing_status: string | null;
    issue_date: string;
    due_date: string | null;
    amount: string;
    paid_amount: string;
    customer: {
        first_name: string;
        last_name: string | null;
        company_name: string | null;
    } | null;
};

type Filters = { search?: string; type?: string; status?: string };
type Summary = { billed: number; outstanding: number; overdue: number };

const typeLabels: Record<string, string> = {
    order: 'Orders',
    delivery_note: 'Delivery notes',
    invoice: 'Invoices',
    credit_note: 'Credit notes',
};

export default function SalesIndex({
    documents,
    counts,
    filters,
    summary,
}: {
    documents: Document[];
    counts: Record<string, number>;
    filters: Filters;
    summary: Summary;
}) {
    const [search, setSearch] = useState(filters.search ?? '');

    function applyFilters(key: keyof Filters, value: string) {
        router.get(
            '/sales',
            {
                ...filters,
                search,
                [key]: value === 'all' || value === '' ? undefined : value,
            },
            { preserveState: true, replace: true },
        );
    }

    return (
        <>
            <Head title="Sales" />
            <div className="flex flex-1 flex-col gap-5 p-4 md:p-6">
                <header className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold">Sales</h1>
                        <p className="text-sm text-muted-foreground">
                            Orders, delivery notes, invoices and customer
                            balances.
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button variant="outline" asChild>
                            <Link href="/sales-workflow">
                                <Workflow /> Workflow
                            </Link>
                        </Button>
                        <Button asChild>
                            <Link href="/sales/create">
                                <Plus /> New document
                            </Link>
                        </Button>
                    </div>
                </header>

                <section className="grid border-y sm:grid-cols-3">
                    <Metric
                        icon={CircleDollarSign}
                        label="Net billed"
                        value={money(summary.billed)}
                    />
                    <Metric
                        icon={Clock3}
                        label="Outstanding"
                        value={money(summary.outstanding)}
                    />
                    <Metric
                        icon={Clock3}
                        label="Overdue"
                        value={money(summary.overdue)}
                        alert={summary.overdue > 0}
                    />
                </section>

                <section className="grid grid-cols-2 border-b sm:grid-cols-4">
                    {Object.entries(typeLabels).map(([type, label]) => (
                        <button
                            key={type}
                            type="button"
                            onClick={() => applyFilters('type', type)}
                            className="border-r p-3 text-left transition-colors last:border-0 hover:bg-muted/50"
                        >
                            <div className="text-xs text-muted-foreground">
                                {label}
                            </div>
                            <div className="mt-1 text-lg font-semibold">
                                {counts[type] ?? 0}
                            </div>
                        </button>
                    ))}
                </section>

                <form
                    className="grid gap-3 md:grid-cols-[minmax(16rem,1fr)_13rem_13rem_auto]"
                    onSubmit={(event) => {
                        event.preventDefault();
                        applyFilters('search', search);
                    }}
                >
                    <Input
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        placeholder="Search number or customer"
                        aria-label="Search sales documents"
                    />
                    <Select
                        value={filters.type ?? 'all'}
                        onValueChange={(value) => applyFilters('type', value)}
                    >
                        <SelectTrigger className="w-full">
                            <SelectValue placeholder="Document type" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All documents</SelectItem>
                            {Object.entries(typeLabels).map(
                                ([value, label]) => (
                                    <SelectItem key={value} value={value}>
                                        {label}
                                    </SelectItem>
                                ),
                            )}
                        </SelectContent>
                    </Select>
                    <Select
                        value={filters.status ?? 'all'}
                        onValueChange={(value) => applyFilters('status', value)}
                    >
                        <SelectTrigger className="w-full">
                            <SelectValue placeholder="Status" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All statuses</SelectItem>
                            {[
                                'draft',
                                'issued',
                                'partial',
                                'paid',
                                'overdue',
                                'delivered',
                            ].map((status) => (
                                <SelectItem key={status} value={status}>
                                    {status.replaceAll('_', ' ')}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <Button type="submit" variant="outline">
                        <Search /> Search
                    </Button>
                </form>

                <section className="overflow-x-auto">
                    <table className="w-full min-w-[52rem] text-sm">
                        <thead>
                            <tr className="border-b text-left text-muted-foreground">
                                <th className="py-2">Number</th>
                                <th>Type</th>
                                <th>Customer</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th className="text-right">Outstanding</th>
                                <th className="text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            {documents.map((document) => {
                                const outstanding =
                                    document.type === 'invoice'
                                        ? Math.max(
                                              0,
                                              Number(document.amount) -
                                                  Number(document.paid_amount),
                                          )
                                        : 0;

                                return (
                                    <tr key={document.id} className="border-b">
                                        <td className="py-3 font-medium">
                                            <Link
                                                className="underline underline-offset-2"
                                                href={`/sales/${document.id}`}
                                            >
                                                {document.number}
                                            </Link>
                                        </td>
                                        <td>{typeLabels[document.type]}</td>
                                        <td>
                                            {document.customer?.company_name ||
                                                `${document.customer?.first_name || ''} ${document.customer?.last_name || ''}`.trim() ||
                                                '—'}
                                        </td>
                                        <td>{date(document.issue_date)}</td>
                                        <td>
                                            <div className="flex flex-wrap gap-1">
                                                <Badge variant="outline">
                                                    {document.status}
                                                </Badge>
                                                {document.fulfillment_status && (
                                                    <Badge variant="secondary">
                                                        Delivery{' '}
                                                        {
                                                            document.fulfillment_status
                                                        }
                                                    </Badge>
                                                )}
                                                {document.billing_status && (
                                                    <Badge variant="secondary">
                                                        Billing{' '}
                                                        {
                                                            document.billing_status
                                                        }
                                                    </Badge>
                                                )}
                                            </div>
                                        </td>
                                        <td className="text-right">
                                            {outstanding > 0
                                                ? money(outstanding)
                                                : '—'}
                                        </td>
                                        <td className="text-right font-medium">
                                            {money(document.amount)}
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                    {documents.length === 0 && (
                        <div className="flex flex-col items-center gap-2 py-12 text-sm text-muted-foreground">
                            <FileCheck2 className="size-6" />
                            No documents match the current filters.
                        </div>
                    )}
                </section>
            </div>
        </>
    );
}

function Metric({
    icon: Icon,
    label,
    value,
    alert = false,
}: {
    icon: typeof CircleDollarSign;
    label: string;
    value: string;
    alert?: boolean;
}) {
    return (
        <div className="flex items-center gap-3 border-r p-4 last:border-0">
            <Icon
                className={`size-5 ${alert ? 'text-destructive' : 'text-muted-foreground'}`}
            />
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
SalesIndex.layout = { breadcrumbs: [{ title: 'Sales', href: '/sales' }] };

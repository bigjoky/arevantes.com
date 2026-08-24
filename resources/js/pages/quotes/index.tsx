import { Head, Link, router } from '@inertiajs/react';
import {
    CheckCircle2,
    CircleDollarSign,
    FileText,
    Plus,
    Search,
    ShieldCheck,
} from 'lucide-react';
import { useState } from 'react';
import {
    PageHeader,
    PageShell,
    PaginationBar,
    TableViewport,
} from '@/components/page-shell';
import type { PaginationLink } from '@/components/page-shell';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

type UserOption = { id: number; name: string };
type Quote = {
    id: number;
    number: string;
    version: number;
    status: string;
    approval_status: string;
    amount: string;
    valid_until: string | null;
    updated_at: string;
    customer: {
        first_name: string;
        last_name: string | null;
        company_name: string | null;
    } | null;
    creator: UserOption | null;
    opportunity: {
        id: number;
        code: string;
        title: string;
        stage: string;
    } | null;
};
type PaginatedQuotes = {
    data: Quote[];
    links: PaginationLink[];
    total: number;
};
type Filters = {
    search?: string;
    status?: string;
    approval?: string;
    owner?: number;
};

const statusLabels: Record<string, string> = {
    draft: 'Borrador',
    sent: 'Enviada',
    accepted: 'Aceptada',
    rejected: 'Rechazada',
    expired: 'Caducada',
};
const approvalLabels: Record<string, string> = {
    not_required: 'Sin aprobación',
    pending: 'Pendiente de aprobación',
    approved: 'Aprobada',
    rejected: 'Aprobación rechazada',
};

export default function QuoteIndex({
    quotes,
    filters,
    summary,
    owners,
    canApproveQuotes,
}: {
    quotes: PaginatedQuotes;
    filters: Filters;
    summary: {
        total: number;
        open_value: number;
        pending_approval: number;
        accepted_value: number;
    };
    owners: UserOption[];
    canApproveQuotes: boolean;
}) {
    const [search, setSearch] = useState(filters.search ?? '');

    function apply(key: keyof Filters, value: string) {
        router.get(
            '/quotes',
            {
                ...filters,
                search: key === 'search' ? value : search || undefined,
                [key]: value === 'all' || value === '' ? undefined : value,
            },
            { preserveState: true, replace: true },
        );
    }

    return (
        <>
            <Head title="Propuestas" />
            <PageShell>
                <PageHeader
                    title="Propuestas"
                    description="Ofertas comerciales, aprobaciones y versiones, separadas de los documentos de venta posteriores."
                    actions={
                        <Button asChild>
                            <Link href="/quotes/create">
                                <Plus /> Nueva propuesta
                            </Link>
                        </Button>
                    }
                />

                <section className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <SummaryCard icon={FileText} label="Propuestas" value={String(summary.total)} />
                    <SummaryCard icon={CircleDollarSign} label="Valor abierto" value={money(summary.open_value)} />
                    <SummaryCard icon={ShieldCheck} label="Pendientes de aprobación" value={String(summary.pending_approval)} alert={summary.pending_approval > 0 && canApproveQuotes} />
                    <SummaryCard icon={CheckCircle2} label="Valor aceptado" value={money(summary.accepted_value)} />
                </section>

                <form
                    className="grid gap-3 rounded-lg border p-3 md:grid-cols-[minmax(14rem,1fr)_12rem_14rem_12rem_auto]"
                    onSubmit={(event) => {
                        event.preventDefault();
                        apply('search', search);
                    }}
                >
                    <div className="relative">
                        <Search className="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder="Número, cliente u oportunidad"
                            aria-label="Buscar propuestas"
                            className="pl-9"
                        />
                    </div>
                    <FilterSelect value={filters.status ?? 'all'} placeholder="Estado" onChange={(value) => apply('status', value)} options={Object.entries(statusLabels)} />
                    <FilterSelect value={filters.approval ?? 'all'} placeholder="Aprobación" onChange={(value) => apply('approval', value)} options={Object.entries(approvalLabels)} />
                    <FilterSelect value={filters.owner ? String(filters.owner) : 'all'} placeholder="Creador" onChange={(value) => apply('owner', value)} options={owners.map((owner) => [String(owner.id), owner.name])} />
                    <Button type="submit" variant="outline"><Search /> Buscar</Button>
                </form>

                <TableViewport>
                    <table className="w-full min-w-[62rem] text-sm">
                        <thead>
                            <tr className="border-b text-left text-xs text-muted-foreground">
                                <th className="py-3">Propuesta</th>
                                <th>Cliente</th>
                                <th>Oportunidad</th>
                                <th>Responsable</th>
                                <th>Estado</th>
                                <th>Aprobación</th>
                                <th>Actualizada</th>
                                <th className="text-right">Importe</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {quotes.data.map((quote) => (
                                <tr key={quote.id} className="hover:bg-muted/30">
                                    <td className="py-3">
                                        <Link href={`/quotes/${quote.id}`} className="font-medium hover:underline">{quote.number}</Link>
                                        <span className="ml-2 text-xs text-muted-foreground">v{quote.version}</span>
                                    </td>
                                    <td>{customerName(quote)}</td>
                                    <td>{quote.opportunity ? <Link href={`/opportunities/${quote.opportunity.id}`} className="hover:underline">{quote.opportunity.code}</Link> : '—'}</td>
                                    <td>{quote.creator?.name || 'Sistema'}</td>
                                    <td><Badge variant="outline">{statusLabels[quote.status] || quote.status}</Badge></td>
                                    <td><Badge variant={quote.approval_status === 'pending' ? 'secondary' : 'outline'}>{approvalLabels[quote.approval_status] || quote.approval_status}</Badge></td>
                                    <td>{dateTime(quote.updated_at)}</td>
                                    <td className="text-right font-medium tabular-nums">{money(quote.amount)}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    {quotes.data.length === 0 ? <div className="py-14 text-center text-sm text-muted-foreground">No hay propuestas que coincidan con los filtros.</div> : null}
                </TableViewport>

                <PaginationBar links={quotes.links} total={quotes.total} noun="propuestas" />
            </PageShell>
        </>
    );
}

function SummaryCard({ icon: Icon, label, value, alert = false }: { icon: typeof FileText; label: string; value: string; alert?: boolean }) {
    return <Card><CardContent className="flex items-start gap-3 p-4"><Icon className={alert ? 'mt-0.5 size-5 text-amber-600' : 'mt-0.5 size-5 text-muted-foreground'} /><div><p className="text-xs text-muted-foreground">{label}</p><p className="mt-1 text-lg font-semibold">{value}</p></div></CardContent></Card>;
}

function FilterSelect({ value, placeholder, onChange, options }: { value: string; placeholder: string; onChange: (value: string) => void; options: string[][] }) {
    return <Select value={value} onValueChange={onChange}><SelectTrigger><SelectValue placeholder={placeholder} /></SelectTrigger><SelectContent><SelectItem value="all">{placeholder}: todos</SelectItem>{options.map(([option, label]) => <SelectItem key={option} value={option}>{label}</SelectItem>)}</SelectContent></Select>;
}

function customerName(quote: Quote) {
    return quote.customer?.company_name || `${quote.customer?.first_name || ''} ${quote.customer?.last_name || ''}`.trim() || '—';
}

function money(value: string | number) {
    return new Intl.NumberFormat('es-ES', { style: 'currency', currency: 'EUR' }).format(Number(value));
}

function dateTime(value: string) {
    return new Intl.DateTimeFormat('es-ES', { dateStyle: 'medium' }).format(new Date(value));
}

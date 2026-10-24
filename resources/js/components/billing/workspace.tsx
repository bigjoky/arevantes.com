import { Link, router } from '@inertiajs/react';
import { Search, FileText } from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { useIsMobile } from '@/hooks/use-mobile';
import '@/../css/billing.css';

export type Capabilities = {
    correct?: boolean;
    customers: boolean;
    suppliers: boolean;
    payments: boolean;
    createCustomer: boolean;
    createSupplier: boolean;
    recordReceipt: boolean;
    recordPayment: boolean;
    contractualNeeds: boolean;
    multiCompany: boolean;
    structuredInvoice: boolean;
    sif: boolean;
    issue?: boolean;
    credit?: boolean;
    edit?: boolean;
};
export type Invoice = {
    id: number;
    kind: 'customer' | 'purchase' | 'expense';
    number: string;
    type: string;
    party: string;
    party_id: number | null;
    project: string | null;
    installation_id: number | null;
    issue_date: string;
    due_date: string | null;
    base: string;
    tax: string;
    withholding: string | null;
    amount: string;
    paid: string;
    credits: string;
    balance: string;
    overpaid: string;
    status: string;
    locked_at: string | null;
    payment_state: string;
    document_state: string;
    href: string;
};
export type Movement = {
    id: number;
    kind: string;
    paid_at: string;
    amount: string;
    method: string;
    reference: string | null;
    number: string;
    party: string;
    href: string;
};
export type Page<T> = {
    data: T[];
    total: number;
    from: number | null;
    to: number | null;
    links: { url: string | null; label: string; active: boolean }[];
};
export type Filters = Record<string, string | number | undefined>;
export const money = (value: string | number | null) =>
    value === null
        ? '—'
        : new Intl.NumberFormat('es-ES', {
              style: 'currency',
              currency: 'EUR',
          }).format(Number(value));
export const date = (value: string | null) =>
    value
        ? new Intl.DateTimeFormat('es-ES').format(
              new Date(value.slice(0, 10) + 'T12:00:00'),
          )
        : '—';
export const labels: Record<string, string> = {
    draft: 'Borrador',
    issued: 'Emitida',
    received: 'Recibida',
    paid: 'Cobrada / pagada',
    pending: 'Pendiente',
    overdue: 'Vencida',
    partial: 'Parcial',
    cancelled: 'Cancelada',
    credit_note: 'Rectificativa',
    receipt: 'Cobro',
    payment: 'Pago',
    bank_transfer: 'Transferencia',
    card: 'Tarjeta',
    cash: 'Efectivo',
    direct_debit: 'Domiciliación',
    employee_funds: 'Fondos de empleado',
    other: 'Otro',
};
export function State({
    state,
    paidLabel,
}: {
    state: string;
    paidLabel?: string;
}) {
    return (
        <span className={`billing-state billing-state-${state}`}>
            {state === 'paid' && paidLabel
                ? paidLabel
                : (labels[state] ?? state)}
        </span>
    );
}
export function Panel({
    title,
    action,
    children,
    className = '',
}: {
    title: string;
    action?: ReactNode;
    children: ReactNode;
    className?: string;
}) {
    return (
        <Card className={`billing-panel ${className}`}>
            <CardHeader>
                <CardTitle>{title}</CardTitle>
                {action}
            </CardHeader>
            <CardContent>{children}</CardContent>
        </Card>
    );
}
export function Empty({
    unavailable = false,
    children,
}: {
    unavailable?: boolean;
    children?: ReactNode;
}) {
    return (
        <div className="billing-empty">
            <FileText aria-hidden="true" />
            <p>
                {children ??
                    (unavailable
                        ? 'Esta información todavía no está disponible.'
                        : 'No hay resultados para esta consulta.')}
            </p>
        </div>
    );
}
export function QueryFilters({
    path,
    filters,
    children,
}: {
    path: string;
    filters: Filters;
    children?: ReactNode;
}) {
    const mobile = useIsMobile();
    const [open, setOpen] = useState(false);
    const [search, setSearch] = useState(String(filters.search ?? ''));
    const submit = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        const params = Object.fromEntries(
            new FormData(event.currentTarget).entries(),
        );
        router.get(
            path,
            { ...filters, ...params, page: 1 },
            {
                preserveState: true,
                preserveScroll: true,
                onSuccess: () => setOpen(false),
            },
        );
    };
    const searchField = (
        <label className="billing-search">
            <Search aria-hidden="true" />
            <Input
                name="search"
                aria-label="Buscar por referencia, tercero o proyecto"
                value={search}
                onChange={(event) => setSearch(event.target.value)}
                placeholder="Buscar por referencia, tercero..."
            />
        </label>
    );

    if (mobile) {
        return (
            <div className="billing-mobile-filters">
                <form onSubmit={submit}>
                    {searchField}
                    <Button variant="outline" type="submit">
                        Buscar
                    </Button>
                </form>
                <Sheet open={open} onOpenChange={setOpen}>
                    <SheetTrigger asChild>
                        <Button variant="outline">Filtros</Button>
                    </SheetTrigger>
                    <SheetContent className="overflow-y-auto">
                        <SheetHeader>
                            <SheetTitle>Filtros</SheetTitle>
                        </SheetHeader>
                        <form
                            className="billing-filters billing-filter-sheet"
                            onSubmit={submit}
                        >
                            <input type="hidden" name="search" value={search} />
                            {children}
                            <Button type="submit">Aplicar filtros</Button>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => {
                                    setOpen(false);
                                    router.get(path);
                                }}
                            >
                                Limpiar
                            </Button>
                        </form>
                    </SheetContent>
                </Sheet>
            </div>
        );
    }

    return (
        <form className="billing-filters" onSubmit={submit}>
            {searchField}
            {children}
            <Button variant="outline" type="submit">
                Aplicar filtros
            </Button>
            <Button
                type="button"
                variant="ghost"
                onClick={() => router.get(path)}
            >
                Limpiar
            </Button>
        </form>
    );
}
export function Dates({ filters }: { filters: Filters }) {
    return (
        <>
            <label>
                Desde
                <Input type="date" name="from" defaultValue={filters.from} />
            </label>
            <label>
                Hasta
                <Input type="date" name="to" defaultValue={filters.to} />
            </label>
        </>
    );
}
export function Pagination<T>({ page, noun }: { page: Page<T>; noun: string }) {
    return (
        <footer className="billing-pagination">
            <span>
                {page.total
                    ? `Mostrando ${page.from}–${page.to} de ${page.total} ${noun}`
                    : `0 ${noun}`}
            </span>
            <nav aria-label="Paginación">
                {page.links.map((link, i) =>
                    link.url ? (
                        <Button
                            key={i}
                            asChild
                            variant={link.active ? 'default' : 'outline'}
                            size="sm"
                        >
                            <Link
                                href={link.url}
                                preserveScroll
                                aria-current={link.active ? 'page' : undefined}
                            >
                                {i === 0
                                    ? 'Anterior'
                                    : i === page.links.length - 1
                                      ? 'Siguiente'
                                      : link.label}
                            </Link>
                        </Button>
                    ) : (
                        <Button key={i} variant="outline" size="sm" disabled>
                            {i === 0
                                ? 'Anterior'
                                : i === page.links.length - 1
                                  ? 'Siguiente'
                                  : link.label}
                        </Button>
                    ),
                )}
            </nav>
        </footer>
    );
}
export function InvoiceTable({
    rows,
    compact = false,
}: {
    rows: Invoice[] | null;
    compact?: boolean;
}) {
    if (rows === null) {
        return <Empty unavailable />;
    }

    if (!rows.length) {
        return <Empty />;
    }

    return (
        <>
            <div className="billing-table-wrap">
                <table className="billing-table">
                    <thead>
                        <tr>
                            <th>Referencia</th>
                            <th>Cliente / proveedor</th>
                            {!compact && <th>Proyecto</th>}
                            <th>Fecha</th>
                            {!compact && <th>Vencimiento</th>}
                            {!compact && (
                                <th className="amount">Base imponible</th>
                            )}
                            <th className="amount">Total</th>
                            {!compact && <th>Documento</th>}
                            <th>Cobro / pago</th>
                            {!compact && <th>Acciones</th>}
                        </tr>
                    </thead>
                    <tbody>
                        {rows.map((r) => (
                            <tr key={`${r.kind}-${r.id}`}>
                                <td>
                                    <Link href={r.href}>{r.number}</Link>
                                </td>
                                <td>{r.party || 'Sin tercero identificado'}</td>
                                {!compact && <td>{r.project || '—'}</td>}
                                <td>
                                    {r.kind === 'customer' &&
                                    r.status === 'draft'
                                        ? 'Sin emitir · borrador'
                                        : date(r.issue_date)}
                                </td>
                                {!compact && <td>{date(r.due_date)}</td>}
                                {!compact && (
                                    <td className="amount">{money(r.base)}</td>
                                )}
                                <td className="amount">{money(r.amount)}</td>
                                {!compact && (
                                    <td>
                                        <State state={r.document_state} />
                                    </td>
                                )}
                                <td>
                                    <State
                                        state={r.payment_state}
                                        paidLabel={
                                            r.kind === 'customer'
                                                ? 'Cobrada'
                                                : 'Pagada'
                                        }
                                    />
                                </td>
                                {!compact && (
                                    <td>
                                        <Link
                                            href={r.href}
                                            aria-label={`Abrir ${r.number}`}
                                        >
                                            •••
                                        </Link>
                                    </td>
                                )}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            <div className="billing-mobile-cards">
                {rows.map((r) => (
                    <Link
                        key={`${r.kind}-${r.id}`}
                        href={r.href}
                        className="billing-mobile-card"
                    >
                        <FileText aria-hidden="true" />
                        <div>
                            <strong>{r.number}</strong>
                            <span>{r.party}</span>
                            <small>
                                {r.kind === 'customer' && r.status === 'draft'
                                    ? 'Sin emitir · borrador'
                                    : date(r.issue_date)}{' '}
                                · {date(r.due_date)}
                            </small>
                        </div>
                        <div>
                            <State
                                state={r.payment_state}
                                paidLabel={
                                    r.kind === 'customer' ? 'Cobrada' : 'Pagada'
                                }
                            />
                            <strong>{money(r.amount)}</strong>
                        </div>
                    </Link>
                ))}
            </div>
        </>
    );
}
export function MovementTable({ rows }: { rows: Movement[] | null }) {
    if (rows === null) {
        return <Empty unavailable />;
    }

    if (!rows.length) {
        return <Empty />;
    }

    return (
        <div className="billing-table-wrap always-scroll">
            <table className="billing-table">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Referencia / tercero</th>
                        <th>Tipo</th>
                        <th>Forma</th>
                        <th className="amount">Importe</th>
                    </tr>
                </thead>
                <tbody>
                    {rows.map((r) => (
                        <tr key={`${r.kind}-${r.id}`}>
                            <td>{date(r.paid_at)}</td>
                            <td>
                                <Link href={r.href}>{r.number}</Link>
                                <small>{r.party}</small>
                            </td>
                            <td>
                                <State state={r.kind} />
                            </td>
                            <td>{labels[r.method] ?? r.method}</td>
                            <td className={`amount billing-${r.kind}`}>
                                {r.kind === 'receipt' ? '+' : '−'}
                                {money(r.amount)}
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

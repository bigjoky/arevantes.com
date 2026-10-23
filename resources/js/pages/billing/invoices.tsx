import { Head, Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import {
    Panel,
    QueryFilters,
    Dates,
    Pagination,
    InvoiceTable,
} from '@/components/billing/workspace';
import type {
    Capabilities,
    Invoice,
    Page,
    Filters,
} from '@/components/billing/workspace';
import { PageHeader, PageShell } from '@/components/page-shell';
import { Button } from '@/components/ui/button';
export default function Invoices({
    side,
    filters,
    invoices,
    parties,
    capabilities,
}: {
    side: 'customers' | 'suppliers';
    filters: Filters;
    invoices: Page<Invoice>;
    parties: { party_id: number; party: string }[];
    capabilities: Capabilities;
}) {
    const customer = side === 'customers';
    const path = customer ? '/sales' : '/billing/suppliers';
    const title = customer ? 'Facturas de clientes' : 'Facturas de proveedores';
    const tabs = customer
        ? [
              ['', 'Todas'],
              ['issued', 'Emitidas'],
              ['paid', 'Cobradas'],
              ['pending', 'Pendientes'],
              ['overdue', 'Vencidas'],
              ['draft', 'Borradores'],
              ['credit_note', 'Rectificativas'],
          ]
        : [
              ['', 'Todas'],
              ['pending', 'Pendientes'],
              ['paid', 'Pagadas'],
              ['partial', 'Parciales'],
              ['cancelled', 'Canceladas'],
              ['credit_note', 'Rectificativas'],
          ];

    return (
        <PageShell className="billing-workspace">
            <Head title={title} />
            <PageHeader
                title={title}
                description={
                    customer
                        ? 'Gestiona las facturas emitidas a tus clientes y su estado de cobro.'
                        : 'Gestiona las facturas recibidas de tus proveedores y su estado de pago.'
                }
                breadcrumbs={[
                    { title: 'Facturación', href: '/billing' },
                    { title, href: path },
                ]}
                actions={
                    (customer
                        ? capabilities.createCustomer
                        : capabilities.createSupplier) && (
                        <Button asChild>
                            <Link
                                href={
                                    customer
                                        ? '/sales/create?type=invoice'
                                        : '/expenses?create=1'
                                }
                            >
                                <Plus />
                                Nueva factura
                            </Link>
                        </Button>
                    )
                }
            />
            <Panel title="">
                <QueryFilters path={path} filters={filters}>
                    <label>
                        Estado
                        <select name="state" defaultValue={filters.state}>
                            {tabs.map(([value, label]) => (
                                <option key={value} value={value}>
                                    {label}
                                </option>
                            ))}
                        </select>
                    </label>
                    <label>
                        {customer ? 'Cliente' : 'Proveedor'}
                        <select name="party" defaultValue={filters.party}>
                            <option value="">Todos</option>
                            {parties.map((p) => (
                                <option
                                    key={`${p.party_id}-${p.party}`}
                                    value={p.party_id}
                                >
                                    {p.party}
                                </option>
                            ))}
                        </select>
                    </label>
                    <Dates filters={filters} />
                    <label>
                        Ordenar
                        <select
                            name="sort"
                            defaultValue={filters.sort ?? 'issue_date'}
                        >
                            <option value="issue_date">Fecha de emisión</option>
                            <option value="due_date">Vencimiento</option>
                            <option value="number">Referencia</option>
                            <option value="amount">Importe</option>
                            <option value="party">Tercero</option>
                        </select>
                    </label>
                    <label>
                        Dirección
                        <select
                            name="direction"
                            defaultValue={filters.direction ?? 'desc'}
                        >
                            <option value="desc">Descendente</option>
                            <option value="asc">Ascendente</option>
                        </select>
                    </label>
                    <label>
                        Por página
                        <select
                            name="per_page"
                            defaultValue={filters.per_page ?? 10}
                        >
                            {[10, 15, 25, 50].map((n) => (
                                <option key={n} value={n}>
                                    {n}
                                </option>
                            ))}
                        </select>
                    </label>
                </QueryFilters>
                <nav
                    className="billing-tabs"
                    aria-label="Estado de las facturas"
                >
                    {tabs.map(([state, label]) => (
                        <Link
                            key={state}
                            href={`${path}?${new URLSearchParams({
                                ...Object.fromEntries(
                                    Object.entries(filters)
                                        .filter(
                                            ([k, v]) =>
                                                k !== 'page' && v != null,
                                        )
                                        .map(([k, v]) => [k, String(v)]),
                                ),
                                state,
                            })}`}
                            aria-current={
                                (filters.state ?? '') === state
                                    ? 'page'
                                    : undefined
                            }
                        >
                            {label}
                        </Link>
                    ))}
                </nav>
                <InvoiceTable rows={invoices.data} />
                <Pagination page={invoices} noun="facturas" />
            </Panel>
        </PageShell>
    );
}

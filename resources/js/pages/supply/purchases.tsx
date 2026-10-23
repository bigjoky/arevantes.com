import { Head, Link, usePage } from '@inertiajs/react';
import type { ComponentProps } from 'react';
import { PageHeader, PageShell } from '@/components/page-shell';
import {
    date,
    Empty,
    Filters,
    money,
    Pagination,
} from '@/components/supply/workspace';
import type { Capabilities, Page } from '@/components/supply/workspace';
import { Button } from '@/components/ui/button';
import { PurchaseDialog } from '@/pages/purchases/index';
export const purchaseStates: Record<string, string> = {
    draft: 'Borrador',
    ordered: 'Pedido',
    pending_approval: 'Pendiente de aprobación',
    partially_received: 'Recepción parcial',
    received: 'Recepcionado',
    cancelled: 'Cancelado',
    closed: 'Cerrado',
    approval_rejected: 'Rechazado',
};
type Order = {
    id: number;
    number: string;
    status: string;
    issue_date: string;
    expected_delivery_date: string | null;
    supplier_confirmed_delivery_date: string | null;
    amount: string | null;
    supplier: { legal_name: string };
    lines_count: number;
    pending_lines_count: number;
};
export default function Purchases({
    orders,
    filters,
    statuses,
    suppliers,
    products,
    capabilities,
}: {
    orders: Page<Order>;
    filters: Record<string, string>;
    statuses: string[];
    suppliers: ComponentProps<typeof PurchaseDialog>['suppliers'];
    products: ComponentProps<typeof PurchaseDialog>['products'];
    capabilities: Capabilities;
}) {
    const { url } = usePage();

    return (
        <>
            <Head title="Compras" />
            <PageShell className="supply-workspace">
                <PageHeader
                    title="Compras"
                    description="Gestiona tus pedidos a proveedores desde la solicitud hasta la recepción."
                    primaryAction={
                        capabilities.managePurchases &&
                        capabilities.finance && (
                            <PurchaseDialog
                                suppliers={suppliers}
                                products={products}
                            />
                        )
                    }
                    secondaryActions={
                        capabilities.finance && (
                            <Button variant="outline" asChild>
                                <Link href="/purchases?view=manage">
                                    Solicitudes y herramientas
                                </Link>
                            </Button>
                        )
                    }
                />
                <nav className="supply-tabs" aria-label="Compras">
                    <Link
                        href="/purchases"
                        aria-current={!filters.status ? 'page' : undefined}
                    >
                        Todas
                    </Link>
                    {statuses.map((s) => (
                        <Link
                            key={s}
                            href={`/purchases?status=${encodeURIComponent(s)}`}
                            aria-current={
                                filters.status === s ? 'page' : undefined
                            }
                        >
                            {purchaseStates[s] ?? s}
                        </Link>
                    ))}
                </nav>
                <Filters
                    url="/purchases"
                    filters={filters}
                    placeholder="Buscar órdenes de compra..."
                >
                    <label>
                        Estado
                        <select
                            name="status"
                            defaultValue={filters.status ?? ''}
                        >
                            <option value="">Todos</option>
                            {statuses.map((s) => (
                                <option key={s} value={s}>
                                    {purchaseStates[s] ?? s}
                                </option>
                            ))}
                        </select>
                    </label>
                    <label>
                        Proveedor
                        <select
                            name="supplier"
                            defaultValue={filters.supplier ?? ''}
                        >
                            <option value="">Todos</option>
                            {suppliers.map((s) => (
                                <option key={s.id} value={s.id}>
                                    {s.legal_name}
                                </option>
                            ))}
                        </select>
                    </label>
                    <label>
                        Desde
                        <input
                            type="date"
                            name="from"
                            defaultValue={filters.from ?? ''}
                        />
                    </label>
                    <label>
                        Hasta
                        <input
                            type="date"
                            name="to"
                            defaultValue={filters.to ?? ''}
                        />
                    </label>
                </Filters>
                <section className="supply-panel">
                    {orders.data.length ? (
                        <table className="supply-table">
                            <thead>
                                <tr>
                                    <th>Referencia</th>
                                    <th>Proveedor</th>
                                    <th>Fecha</th>
                                    {capabilities.finance && <th>Importe</th>}
                                    <th>Estado</th>
                                    <th>Artículos</th>
                                    <th>Entrega prevista</th>
                                    <th>Líneas pendientes</th>
                                </tr>
                            </thead>
                            <tbody>
                                {orders.data.map((r) => (
                                    <tr key={r.id}>
                                        <td data-label="Referencia">
                                            <Link
                                                href={`/purchases/${r.id}?return_to=${encodeURIComponent(url)}`}
                                            >
                                                {r.number}
                                            </Link>
                                        </td>
                                        <td data-label="Proveedor">
                                            {r.supplier.legal_name}
                                        </td>
                                        <td data-label="Fecha">
                                            {date(r.issue_date)}
                                        </td>
                                        {capabilities.finance && (
                                            <td data-label="Importe">
                                                {r.amount === null
                                                    ? '—'
                                                    : money(r.amount)}
                                            </td>
                                        )}
                                        <td data-label="Estado">
                                            <span className="supply-badge">
                                                {purchaseStates[r.status] ??
                                                    r.status}
                                            </span>
                                        </td>
                                        <td data-label="Artículos">
                                            {r.lines_count}
                                        </td>
                                        <td data-label="Entrega prevista">
                                            {date(
                                                r.supplier_confirmed_delivery_date ??
                                                    r.expected_delivery_date,
                                            )}
                                        </td>
                                        <td data-label="Líneas pendientes">
                                            {r.pending_lines_count}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    ) : (
                        <Empty>No hay órdenes de compra.</Empty>
                    )}
                    <Pagination page={orders} />
                </section>
            </PageShell>
        </>
    );
}

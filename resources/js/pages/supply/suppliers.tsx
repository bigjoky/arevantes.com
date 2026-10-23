import { Head, Link, usePage } from '@inertiajs/react';
import { PageHeader, PageShell } from '@/components/page-shell';
import { Empty, Filters, Pagination } from '@/components/supply/workspace';
import type { Capabilities, Page } from '@/components/supply/workspace';
import { SupplierDialog } from '@/pages/catalog/index';
type Supplier = {
    id: number;
    legal_name: string;
    tax_id: string | null;
    email: string | null;
    phone: string | null;
    city: string | null;
    is_active: boolean;
    products_count: number;
    purchase_documents_count: number;
};
export default function Suppliers({
    suppliers,
    filters,
    capabilities,
}: {
    suppliers: Page<Supplier>;
    filters: Record<string, string>;
    capabilities: Capabilities;
}) {
    const { url } = usePage();

    return (
        <>
            <Head title="Proveedores" />
            <PageShell className="supply-workspace">
                <PageHeader
                    title="Proveedores"
                    description="Gestiona tus proveedores y recorre su catálogo y aprovisionamiento."
                    primaryAction={<SupplierDialog />}
                />
                <Filters
                    url="/suppliers"
                    filters={filters}
                    placeholder="Buscar proveedores..."
                >
                    <label>
                        Estado
                        <select
                            name="status"
                            defaultValue={filters.status ?? ''}
                        >
                            <option value="">Todos</option>
                            <option value="active">Activo</option>
                            <option value="inactive">Inactivo</option>
                        </select>
                    </label>
                </Filters>
                <section className="supply-panel">
                    {suppliers.data.length ? (
                        <table className="supply-table">
                            <thead>
                                <tr>
                                    <th>Proveedor</th>
                                    <th>Ubicación</th>
                                    <th>Contacto</th>
                                    <th>Productos</th>
                                    <th>Compras</th>
                                    <th>Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                {suppliers.data.map((r) => (
                                    <tr key={r.id}>
                                        <td data-label="Proveedor">
                                            {capabilities.catalog ? (
                                                <Link
                                                    href={`/suppliers/${r.id}?return_to=${encodeURIComponent(url)}`}
                                                >
                                                    {r.legal_name}
                                                </Link>
                                            ) : (
                                                r.legal_name
                                            )}
                                            <small>{r.tax_id ?? '—'}</small>
                                        </td>
                                        <td data-label="Ubicación">
                                            {r.city ?? '—'}
                                        </td>
                                        <td data-label="Contacto">
                                            {r.email ?? '—'}
                                            <small>{r.phone}</small>
                                        </td>
                                        <td data-label="Productos">
                                            {r.products_count}
                                        </td>
                                        <td data-label="Compras">
                                            {r.purchase_documents_count}
                                        </td>
                                        <td data-label="Estado">
                                            <span className="supply-badge">
                                                {r.is_active
                                                    ? 'Activo'
                                                    : 'Inactivo'}
                                            </span>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    ) : (
                        <Empty>No hay proveedores.</Empty>
                    )}
                    <Pagination page={suppliers} />
                </section>
            </PageShell>
        </>
    );
}

import { Head, Link, usePage } from '@inertiajs/react';
import { Package } from 'lucide-react';
import type { ComponentProps } from 'react';
import { PageHeader, PageShell } from '@/components/page-shell';
import {
    Empty,
    Filters,
    money,
    Pagination,
    qty,
} from '@/components/supply/workspace';
import type { Capabilities, Page } from '@/components/supply/workspace';
import { ProductDialog } from '@/pages/catalog/index';
export type ProductEditor = Omit<
    ComponentProps<typeof ProductDialog>,
    'product'
>;
export type Product = {
    id: number;
    name: string;
    sku: string | null;
    type: string;
    is_active: boolean;
    track_stock: boolean;
    unit: { symbol: string; name: string };
    physical: number;
    reserved: number;
    available: number;
    cost_price: string | null;
    sale_price: string | null;
};
const productType = (type: string) =>
    type === 'service' ? 'Servicio' : 'Producto';
export default function Products({
    products,
    filters,
    suppliers,
    capabilities,
    editor,
}: {
    products: Page<Product>;
    filters: Record<string, string>;
    suppliers: { id: number; legal_name: string }[];
    capabilities: Capabilities;
    editor: ProductEditor | null;
}) {
    const { url } = usePage();

    return (
        <>
            <Head title="Productos" />
            <PageShell className="supply-workspace">
                <PageHeader
                    title="Productos"
                    description="Gestiona tu catálogo de productos y controla su disponibilidad."
                    primaryAction={editor && <ProductDialog {...editor} />}
                />
                <Filters
                    url="/catalog"
                    filters={filters}
                    placeholder="Buscar productos..."
                >
                    <label>
                        Tipo
                        <select name="type" defaultValue={filters.type ?? ''}>
                            <option value="">Todos</option>
                            <option value="product">Producto</option>
                            <option value="service">Servicio</option>
                        </select>
                    </label>
                    <label>
                        Proveedor
                        <select
                            name="supplier"
                            defaultValue={filters.supplier ?? ''}
                        >
                            <option value="">Todos</option>
                            {suppliers.map((r) => (
                                <option key={r.id} value={r.id}>
                                    {r.legal_name}
                                </option>
                            ))}
                        </select>
                    </label>
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
                <p className="supply-note">
                    Existencias globales. Consulta la ficha para ver cada
                    almacén.
                </p>
                <section className="supply-panel">
                    {products.data.length ? (
                        <div className="supply-table-wrap">
                            <table className="supply-table">
                                <thead>
                                    <tr>
                                        <th>Referencia / producto</th>
                                        <th>Tipo</th>
                                        <th>Unidad</th>
                                        <th>Físico</th>
                                        <th>Reservado</th>
                                        <th>Disponible</th>
                                        {capabilities.finance && (
                                            <th>Precio de compra</th>
                                        )}
                                        <th>Estado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {products.data.map((p) => (
                                        <tr key={p.id}>
                                            <td data-label="Producto">
                                                <Link
                                                    href={`/catalog/${p.id}?return_to=${encodeURIComponent(url)}`}
                                                >
                                                    <Package className="mr-2 inline size-4" />
                                                    {p.name}
                                                </Link>
                                                <small>{p.sku ?? '—'}</small>
                                            </td>
                                            <td data-label="Tipo">
                                                {productType(p.type)}
                                            </td>
                                            <td data-label="Unidad">
                                                {p.unit.symbol}
                                            </td>
                                            <td data-label="Físico">
                                                {p.track_stock
                                                    ? qty(p.physical)
                                                    : '—'}
                                            </td>
                                            <td data-label="Reservado">
                                                {p.track_stock
                                                    ? qty(p.reserved)
                                                    : '—'}
                                            </td>
                                            <td data-label="Disponible">
                                                {p.track_stock
                                                    ? qty(p.available)
                                                    : '—'}
                                            </td>
                                            {capabilities.finance && (
                                                <td data-label="Precio de compra">
                                                    {p.cost_price === null
                                                        ? '—'
                                                        : money(p.cost_price)}
                                                </td>
                                            )}
                                            <td data-label="Estado">
                                                <span className="supply-badge">
                                                    {p.is_active
                                                        ? 'Activo'
                                                        : 'Inactivo'}
                                                </span>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    ) : (
                        <Empty>No hay productos.</Empty>
                    )}
                    <Pagination page={products} />
                </section>
            </PageShell>
        </>
    );
}

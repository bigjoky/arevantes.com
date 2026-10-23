import { Head, Link } from '@inertiajs/react';
import type { ComponentProps } from 'react';
import { PageHeader, PageShell } from '@/components/page-shell';
import { useSupplyDetailNavigation } from '@/components/supply/workspace';
import {
    Empty,
    money,
    Movements,
    Pagination,
    Panel,
    StockTable,
} from '@/components/supply/workspace';
import type {
    Capabilities,
    Movement,
    Page,
    Position,
} from '@/components/supply/workspace';
import { Button } from '@/components/ui/button';
import { ProductDialog } from '@/pages/catalog/index';
import type { Product, ProductEditor } from './products';
const tabs = [
    ['summary', 'Resumen'],
    ['stock', 'Stock'],
    ['suppliers', 'Proveedores'],
    ['movements', 'Movimientos'],
] as const;
export default function ProductShow({
    product,
    positions,
    suppliers,
    movements,
    capabilities,
    editor,
    editableProduct,
}: {
    product: Product & {
        description: string | null;
        track_serials: boolean;
        lead_days: number | null;
    };
    positions: Position[];
    suppliers: {
        id: number;
        name: string;
        sku: string | null;
        lead_days: number | null;
        price: string | null;
    }[];
    movements: Page<Movement>;
    capabilities: Capabilities;
    editor: ProductEditor | null;
    editableProduct: ComponentProps<typeof ProductDialog>['product'];
}) {
    const { tab, setTab, back } = useSupplyDetailNavigation('/catalog', tabs);

    return (
        <>
            <Head title={product.name} />
            <PageShell className="supply-workspace">
                <PageHeader
                    title={product.name}
                    description={product.sku ?? undefined}
                    secondaryActions={
                        <Button asChild variant="outline">
                            <Link href={back}>Volver a productos</Link>
                        </Button>
                    }
                    primaryAction={
                        editor &&
                        editableProduct && (
                            <ProductDialog
                                {...editor}
                                product={editableProduct}
                            />
                        )
                    }
                />
                <nav className="supply-tabs" aria-label="Ficha de producto">
                    {tabs.map(([id, label]) => (
                        <a
                            href={`#${id}`}
                            aria-current={tab === id ? 'page' : undefined}
                            key={id}
                            onClick={(event) => {
                                event.preventDefault();
                                setTab(id);
                            }}
                        >
                            {label}
                        </a>
                    ))}
                </nav>
                {tab === 'summary' && (
                    <div className="supply-detail-grid">
                        <Panel title="Información general">
                            <dl>
                                <div>
                                    <dt>Referencia</dt>
                                    <dd>{product.sku ?? '—'}</dd>
                                </div>
                                <div>
                                    <dt>Unidad</dt>
                                    <dd>
                                        {product.unit.name} (
                                        {product.unit.symbol})
                                    </dd>
                                </div>
                                <div>
                                    <dt>Control de stock</dt>
                                    <dd>{product.track_stock ? 'Sí' : 'No'}</dd>
                                </div>
                                <div>
                                    <dt>Números de serie</dt>
                                    <dd>
                                        {product.track_serials ? 'Sí' : 'No'}
                                    </dd>
                                </div>
                                <div>
                                    <dt>Estado</dt>
                                    <dd>
                                        {product.is_active
                                            ? 'Activo'
                                            : 'Inactivo'}
                                    </dd>
                                </div>
                                <div>
                                    <dt>Descripción</dt>
                                    <dd>{product.description ?? '—'}</dd>
                                </div>
                            </dl>
                        </Panel>
                        {capabilities.finance && (
                            <Panel title="Precios">
                                <dl>
                                    <div>
                                        <dt>Precio de compra</dt>
                                        <dd>
                                            {product.cost_price === null
                                                ? '—'
                                                : money(product.cost_price)}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt>Precio de venta</dt>
                                        <dd>
                                            {product.sale_price === null
                                                ? '—'
                                                : money(product.sale_price)}
                                        </dd>
                                    </div>
                                </dl>
                                <p className="supply-note p-4">
                                    Los precios del catálogo no sustituyen el
                                    coste histórico de los movimientos.
                                </p>
                            </Panel>
                        )}
                    </div>
                )}
                {tab === 'stock' && (
                    <Panel title="Existencias por almacén">
                        <StockTable positions={positions} />
                        {product.track_serials && (
                            <Link
                                className="block p-4 text-sm"
                                href={`/inventory/serials?search=${encodeURIComponent(product.sku ?? product.name)}`}
                            >
                                Ver trazabilidad de números de serie ›
                            </Link>
                        )}
                    </Panel>
                )}
                {tab === 'suppliers' && (
                    <Panel title="Proveedores">
                        {suppliers.length ? (
                            <div className="supply-table-wrap">
                                <table className="supply-table">
                                    <thead>
                                        <tr>
                                            <th>Proveedor</th>
                                            <th>Referencia proveedor</th>
                                            <th>Plazo habitual</th>
                                            {capabilities.finance && (
                                                <th>Último precio</th>
                                            )}
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {suppliers.map((r) => (
                                            <tr key={r.id}>
                                                <td data-label="Proveedor">
                                                    <Link
                                                        href={`/suppliers/${r.id}`}
                                                    >
                                                        {r.name}
                                                    </Link>
                                                </td>
                                                <td data-label="Referencia proveedor">
                                                    {r.sku ?? '—'}
                                                </td>
                                                <td data-label="Plazo habitual">
                                                    {r.lead_days ?? '—'}
                                                </td>
                                                {capabilities.finance && (
                                                    <td data-label="Último precio">
                                                        {r.price === null
                                                            ? '—'
                                                            : money(r.price)}
                                                    </td>
                                                )}
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        ) : (
                            <Empty>No hay proveedores asociados.</Empty>
                        )}
                    </Panel>
                )}
                {tab === 'movements' && (
                    <Panel title="Movimientos">
                        <Movements rows={movements.data} />
                        <Pagination page={movements} />
                    </Panel>
                )}
            </PageShell>
        </>
    );
}

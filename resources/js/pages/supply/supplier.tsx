import { Head, Link } from '@inertiajs/react';
import type { ComponentProps } from 'react';
import { PartyAccounting } from '@/components/accounting/party-accounting';
import type { PartyAccountingSummary } from '@/components/accounting/party-accounting';
import { PageHeader, PageShell } from '@/components/page-shell';
import { useSupplyDetailNavigation } from '@/components/supply/workspace';
import { date, Empty, money, Panel } from '@/components/supply/workspace';
import type { Capabilities } from '@/components/supply/workspace';
import { Button } from '@/components/ui/button';
import { SupplierDialog } from '@/pages/catalog/index';
type Supplier = {
    id: number;
    legal_name: string;
    tax_id: string | null;
    email: string | null;
    phone: string | null;
    address_line_1: string | null;
    city: string | null;
    province: string | null;
    country: string;
    payment_terms_days: number;
    is_active: boolean;
};
const tabs = [
    ['summary', 'Resumen'],
    ['products', 'Productos'],
    ['purchases', 'Compras'],
    ['invoices', 'Facturas'],
] as const;
export default function SupplierShow({
    supplier,
    accounting,
    editableSupplier,
    contacts,
    products,
    purchases,
    capabilities,
}: {
    supplier: Supplier;
    accounting?: PartyAccountingSummary | null;
    editableSupplier: ComponentProps<typeof SupplierDialog>['supplier'];
    contacts: {
        id: number;
        first_name: string;
        last_name: string | null;
        email: string | null;
        phone: string | null;
        position: string | null;
    }[];
    products: {
        id: number;
        product: { id: number; name: string; sku: string | null } | null;
        supplier_sku: string | null;
        lead_time_days: number | null;
        price: string | null;
    }[];
    purchases: {
        id: number;
        number: string;
        type: string;
        status: string;
        issue_date: string;
        amount: string | null;
    }[];
    capabilities: Capabilities;
}) {
    const { tab, setTab, back } = useSupplyDetailNavigation('/suppliers', tabs);
    const showProducts = tab === 'products';
    const showInvoices = tab === 'invoices';
    const showPurchases = ['purchases', 'invoices'].includes(tab);
    const permittedTabs = tabs.filter(
        ([id]) =>
            !['purchases', 'invoices'].includes(id) || capabilities.purchases,
    );
    const docs = purchases.filter((r) =>
        tab === 'invoices'
            ? ['supplier_invoice', 'supplier_credit_note'].includes(r.type)
            : ['purchase_order', 'purchase_rfq'].includes(r.type),
    );

    return (
        <>
            <Head title={supplier.legal_name} />
            <PageShell className="supply-workspace">
                <PageHeader
                    title={supplier.legal_name}
                    description={supplier.tax_id ?? undefined}
                    primaryAction={
                        editableSupplier && (
                            <SupplierDialog supplier={editableSupplier} />
                        )
                    }
                    secondaryActions={
                        <>
                            <Button asChild variant="outline">
                                <Link href={back}>Volver a proveedores</Link>
                            </Button>
                            {capabilities.managePurchases &&
                                capabilities.finance && (
                                    <Button asChild variant="outline">
                                        <Link
                                            href={`/supplier-intelligence/${supplier.id}`}
                                        >
                                            Contactos y catálogo
                                        </Link>
                                    </Button>
                                )}
                        </>
                    }
                />
                <PartyAccounting summary={accounting} />
                <nav className="supply-tabs" aria-label="Ficha de proveedor">
                    {permittedTabs.map(([id, label]) => (
                        <a
                            href={`#${id}`}
                            key={id}
                            aria-current={tab === id ? 'page' : undefined}
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
                                    <dt>NIF / CIF</dt>
                                    <dd>{supplier.tax_id ?? '—'}</dd>
                                </div>
                                <div>
                                    <dt>Email</dt>
                                    <dd>{supplier.email ?? '—'}</dd>
                                </div>
                                <div>
                                    <dt>Teléfono</dt>
                                    <dd>{supplier.phone ?? '—'}</dd>
                                </div>
                                <div>
                                    <dt>Dirección</dt>
                                    <dd>
                                        {supplier.address_line_1 ?? '—'}
                                        <br />
                                        {supplier.city} · {supplier.province}
                                    </dd>
                                </div>
                                <div>
                                    <dt>Condiciones de pago</dt>
                                    <dd>{supplier.payment_terms_days} días</dd>
                                </div>
                                <div>
                                    <dt>Estado</dt>
                                    <dd>
                                        {supplier.is_active
                                            ? 'Activo'
                                            : 'Inactivo'}
                                    </dd>
                                </div>
                            </dl>
                        </Panel>
                        <Panel title="Contactos principales">
                            {contacts.length ? (
                                <ul className="p-4">
                                    {contacts.map((r) => (
                                        <li
                                            key={r.id}
                                            className="border-t py-3 text-sm"
                                        >
                                            <strong>
                                                {r.first_name} {r.last_name}
                                            </strong>
                                            <p className="supply-note">
                                                {r.position}
                                                <br />
                                                {r.email} · {r.phone}
                                            </p>
                                        </li>
                                    ))}
                                </ul>
                            ) : (
                                <Empty>No hay contactos registrados.</Empty>
                            )}
                        </Panel>
                    </div>
                )}
                {showProducts && (
                    <Panel title="Productos suministrados">
                        {products.length ? (
                            <table className="supply-table">
                                <thead>
                                    <tr>
                                        <th>Producto</th>
                                        <th>Referencia proveedor</th>
                                        <th>Plazo</th>
                                        {capabilities.finance && (
                                            <th>Último precio</th>
                                        )}
                                    </tr>
                                </thead>
                                <tbody>
                                    {products.map((r) => (
                                        <tr key={r.id}>
                                            <td data-label="Producto">
                                                {r.product && (
                                                    <Link
                                                        href={`/catalog/${r.product.id}`}
                                                    >
                                                        {r.product.name}
                                                    </Link>
                                                )}
                                            </td>
                                            <td data-label="Referencia proveedor">
                                                {r.supplier_sku ?? '—'}
                                            </td>
                                            <td data-label="Plazo">
                                                {r.lead_time_days ?? '—'}
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
                        ) : (
                            <Empty>No hay productos asociados.</Empty>
                        )}
                    </Panel>
                )}
                {showPurchases && (
                    <Panel
                        title={
                            showInvoices
                                ? 'Facturas de proveedores'
                                : 'Compras recientes'
                        }
                    >
                        {docs.length ? (
                            <table className="supply-table">
                                <thead>
                                    <tr>
                                        <th>Referencia</th>
                                        <th>Fecha</th>
                                        <th>Estado</th>
                                        {capabilities.finance && (
                                            <th>Importe</th>
                                        )}
                                    </tr>
                                </thead>
                                <tbody>
                                    {docs.map((r) => (
                                        <tr key={r.id}>
                                            <td data-label="Referencia">
                                                <Link
                                                    href={`/purchases/${r.id}`}
                                                >
                                                    {r.number}
                                                </Link>
                                            </td>
                                            <td data-label="Fecha">
                                                {date(r.issue_date)}
                                            </td>
                                            <td data-label="Estado">
                                                {r.status}
                                            </td>
                                            {capabilities.finance && (
                                                <td data-label="Importe">
                                                    {r.amount === null
                                                        ? '—'
                                                        : money(r.amount)}
                                                </td>
                                            )}
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        ) : (
                            <Empty>No hay documentos registrados.</Empty>
                        )}
                    </Panel>
                )}
            </PageShell>
        </>
    );
}

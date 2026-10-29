import '../../../css/supply-mobile.css';
import { Link, router, usePage } from '@inertiajs/react';
import { Package, Search } from 'lucide-react';
import type { ReactNode, ComponentProps } from 'react';
import { ProjectDialogContent } from '@/components/projects/protected-dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { supplyReturnUrl, supplyEntityUrl } from '@/lib/supply/navigation';
import '@/../css/supply.css';

export type Page<T> = {
    data: T[];
    total: number;
    from: number | null;
    to: number | null;
    links: { url: string | null; label: string; active: boolean }[];
};
export type Capabilities = {
    catalog: boolean;
    purchases: boolean;
    managePurchases: boolean;
    finance: boolean;
};
export type Position = {
    warehouse: { id: number; name: string };
    product: {
        id: number;
        sku: string | null;
        name: string;
        unit?: { symbol: string } | null;
    };
    on_hand: number;
    reserved: number;
    available: number;
    in_transit: number;
};
export type Movement = {
    id: number;
    number: string;
    type: string;
    reference: string | null;
    posted_at: string;
    creator: { name: string } | null;
    recipient?: { name: string } | null;
    material_condition?: string | null;
    sourceMovement?: { id: number; number: string } | null;
    installation: {
        id: number;
        project_reference: string | null;
        site_name: string | null;
    } | null;
    workOrder: { id: number; number: string } | null;
    purchaseDocument: { id: number; number: string } | null;
    lines: {
        id: number;
        quantity: string;
        unit_cost: string | null;
        product: { id: number; name: string; unit: { symbol: string } | null };
        warehouse: { name: string };
    }[];
};
export type Need = {
    id: number;
    description: string;
    planned_quantity: string;
    reserved: string;
    consumed: string;
    available: number;
    shortage: number;
    installation: {
        id: number;
        project_reference: string | null;
        site_name: string | null;
        scheduled_date: string | null;
    };
    product: {
        id: number;
        name: string;
        unit: { symbol: string } | null;
    } | null;
};
const conditionLabels: Record<string, string> = {
    damaged: 'Material dañado',
    usable: 'Material utilizable',
};
export const movementLabels: Record<string, string> = {
    receipt: 'Entrada',
    issue: 'Salida / consumo',
    transfer: 'Transferencia',
    adjustment: 'Ajuste',
    purchase_return: 'Devolución a proveedor',
    delivery: 'Entrega',
    internal_return: 'Devolución interna',
};
export const qty = (value: number | string) =>
    new Intl.NumberFormat('es-ES', { maximumFractionDigits: 3 }).format(
        Number(value),
    );
export const money = (value: number | string) =>
    new Intl.NumberFormat('es-ES', {
        style: 'currency',
        currency: 'EUR',
    }).format(Number(value));
export const date = (value: string | null) =>
    value ? new Intl.DateTimeFormat('es-ES').format(new Date(value)) : '—';
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
        <section className={`supply-panel ${className}`}>
            <header>
                <h2>{title}</h2>
                {action}
            </header>
            {children}
        </section>
    );
}
export function Empty({
    children = 'No hay resultados.',
}: {
    children?: ReactNode;
}) {
    return (
        <div className="supply-empty">
            <Package aria-hidden="true" />
            {children}
        </div>
    );
}
export function Pagination({ page }: { page: Page<unknown> }) {
    return (
        <footer className="supply-pagination">
            <span>
                Mostrando {page.from ?? 0}–{page.to ?? 0} de {page.total}
            </span>
            <nav aria-label="Paginación">
                {page.links.map((link, index) =>
                    link.url ? (
                        <Link
                            key={index}
                            href={link.url}
                            preserveScroll
                            aria-current={link.active ? 'page' : undefined}
                        >
                            {index === 0
                                ? '‹'
                                : index === page.links.length - 1
                                  ? '›'
                                  : link.label}
                        </Link>
                    ) : (
                        <span key={index}>
                            {index === 0
                                ? '‹'
                                : index === page.links.length - 1
                                  ? '›'
                                  : link.label}
                        </span>
                    ),
                )}
            </nav>
        </footer>
    );
}
export function Filters({
    url,
    filters,
    children,
    placeholder,
    clearUrl,
}: {
    url: string;
    clearUrl?: string;
    filters: Record<string, string | number | undefined>;
    children?: ReactNode;
    placeholder: string;
}) {
    return (
        <form
            className="supply-filters"
            onSubmit={(event) => {
                event.preventDefault();
                const form = new FormData(event.currentTarget);
                router.get(
                    url,
                    Object.fromEntries(
                        [...form.entries()].filter(([, value]) => value !== ''),
                    ),
                    { preserveState: true },
                );
            }}
        >
            <label className="supply-search">
                <Search aria-hidden="true" />
                <Input
                    aria-label={placeholder}
                    name="search"
                    key={String(filters.search ?? '')}
                    defaultValue={filters.search ?? ''}
                    placeholder={placeholder}
                />
            </label>
            {children}
            <Button variant="outline" type="submit">
                Filtrar
            </Button>
            <Button variant="ghost" asChild>
                <Link href={clearUrl ?? url}>Limpiar filtros</Link>
            </Button>
        </form>
    );
}
export function StockTable({ positions }: { positions: Position[] }) {
    const { url: context } = usePage();

    return positions.length ? (
        <div className="supply-table-wrap">
            <table className="supply-table">
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th>Almacén</th>
                        <th>Físico</th>
                        <th>Reservado</th>
                        <th>Disponible</th>
                        <th>En tránsito</th>
                    </tr>
                </thead>
                <tbody>
                    {positions.map((row) => (
                        <tr key={`${row.warehouse.id}:${row.product.id}`}>
                            <td data-label="Producto">
                                <Link
                                    href={supplyEntityUrl(
                                        `/catalog/${row.product.id}`,
                                        context,
                                    )}
                                >
                                    {row.product.name}
                                </Link>
                                <small>
                                    {row.product.sku} ·{' '}
                                    {row.product.unit?.symbol}
                                </small>
                            </td>
                            <td data-label="Almacén">{row.warehouse.name}</td>
                            <td data-label="Físico">{qty(row.on_hand)}</td>
                            <td data-label="Reservado">{qty(row.reserved)}</td>
                            <td data-label="Disponible">
                                {qty(row.available)}
                            </td>
                            <td data-label="En tránsito">
                                {qty(row.in_transit)}
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    ) : (
        <Empty>No hay existencias.</Empty>
    );
}
export function NeedTable({ needs }: { needs: Need[] }) {
    const { url: context } = usePage();

    return needs.length ? (
        <div className="supply-table-wrap">
            <table className="supply-table">
                <thead>
                    <tr>
                        <th>Material</th>
                        <th>Proyecto / instalación</th>
                        <th>Requerido</th>
                        <th>Reservado</th>
                        <th>Consumido</th>
                        <th>Sin reservar</th>
                        <th>Disponible global</th>
                        <th>Sin cubrir</th>
                        <th>Fecha prevista</th>
                    </tr>
                </thead>
                <tbody>
                    {needs.map((row) => (
                        <tr key={row.id}>
                            <td data-label="Material">
                                {row.product?.name ?? row.description}
                                <small>{row.product?.unit?.symbol}</small>
                            </td>
                            <td data-label="Proyecto / instalación">
                                <Link
                                    href={supplyEntityUrl(
                                        `/installations/${row.installation.id}`,
                                        context,
                                    )}
                                >
                                    {row.installation.project_reference ??
                                        row.installation.site_name ??
                                        row.installation.id}
                                </Link>
                            </td>
                            <td data-label="Requerido">
                                {qty(row.planned_quantity)}
                            </td>
                            <td data-label="Reservado">{qty(row.reserved)}</td>
                            <td data-label="Consumido">{qty(row.consumed)}</td>
                            <td data-label="Sin reservar">
                                {qty(
                                    Math.max(
                                        0,
                                        Number(row.planned_quantity) -
                                            Number(row.reserved) -
                                            Number(row.consumed),
                                    ),
                                )}
                            </td>
                            <td data-label="Disponible global">
                                {qty(row.available)}
                            </td>
                            <td data-label="Sin cubrir">{qty(row.shortage)}</td>
                            <td data-label="Fecha prevista">
                                {date(row.installation.scheduled_date)}
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    ) : (
        <Empty>No existen necesidades registradas.</Empty>
    );
}
export function Movements({ rows }: { rows: Movement[] }) {
    const { url: context } = usePage();

    return rows.length ? (
        <div className="supply-movements">
            {rows.map((row) => (
                <article key={row.id}>
                    <div>
                        <strong>{row.number}</strong>
                        <span className="supply-badge">
                            {movementLabels[row.type] ?? row.type}
                        </span>
                        <small>
                            {date(row.posted_at)} · {row.creator?.name ?? '—'}
                        </small>
                        {row.purchaseDocument && (
                            <Link
                                href={supplyEntityUrl(
                                    `/purchases/${row.purchaseDocument.id}`,
                                    context,
                                )}
                            >
                                {row.purchaseDocument.number}
                            </Link>
                        )}
                        {row.recipient && (
                            <span>Destinatario: {row.recipient.name}</span>
                        )}
                        {row.material_condition && (
                            <span>
                                {conditionLabels[row.material_condition] ??
                                    row.material_condition}
                            </span>
                        )}
                        {row.sourceMovement && (
                            <Link
                                href={`/inventory?view=deliveries&search=${encodeURIComponent(row.sourceMovement.number)}`}
                            >
                                {row.sourceMovement.number}
                            </Link>
                        )}
                        {row.workOrder && (
                            <Link
                                href={supplyEntityUrl(
                                    `/work-orders/${row.workOrder.id}`,
                                    context,
                                )}
                            >
                                {row.workOrder.number}
                            </Link>
                        )}
                        {row.installation && (
                            <Link
                                href={supplyEntityUrl(
                                    `/installations/${row.installation.id}`,
                                    context,
                                )}
                            >
                                {row.installation.project_reference ??
                                    row.installation.site_name}
                            </Link>
                        )}
                    </div>
                    <ul>
                        {row.lines.map((line) => (
                            <li key={line.id}>
                                <span>
                                    {line.product.name}
                                    <small>{line.warehouse.name}</small>
                                </span>
                                <strong>
                                    {Number(line.quantity) > 0 ? '+' : ''}
                                    {qty(line.quantity)}{' '}
                                    {line.product.unit?.symbol}
                                </strong>
                            </li>
                        ))}
                    </ul>
                </article>
            ))}
        </div>
    ) : (
        <Empty>No hay movimientos registrados.</Empty>
    );
}

/** Keep the active detail tab and list filters in the address, including pagination. */
export function useSupplyDetailNavigation(
    base: string,
    tabs: readonly (readonly [string, string])[],
) {
    const { url } = usePage();
    const parameters = new URLSearchParams(url.split('?')[1] ?? '');
    const requested = parameters.get('tab');
    const tab = tabs.some(([id]) => id === requested) ? requested! : tabs[0][0];
    const returnTo = parameters.get('return_to') ?? base;
    const back = supplyReturnUrl(returnTo, base);
    const setTab = (id: string) => {
        parameters.set('tab', id);
        parameters.delete('page');
        router.get(
            `${url.split('?')[0]}?${parameters}`,
            {},
            { preserveScroll: true, preserveState: true },
        );
    };

    return { tab, setTab, back };
}

export function SupplyDialogContent({
    className,
    ...props
}: ComponentProps<typeof ProjectDialogContent>) {
    return (
        <ProjectDialogContent
            {...props}
            className={'supply-dialog ' + (className ?? '')}
        />
    );
}

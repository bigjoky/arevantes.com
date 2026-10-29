import { Head, Link, usePage } from '@inertiajs/react';
import { PageHeader, PageShell } from '@/components/page-shell';
import ReleaseReservation from '@/components/supply/release-reservation';
import { RequirementDialog } from '@/components/supply/requirement-dialog';
import type { NeedOptions } from '@/components/supply/requirement-dialog';
import {
    Empty,
    Filters,
    Movements,
    NeedTable,
    Pagination,
    qty,
    StockTable,
} from '@/components/supply/workspace';
import type {
    Movement,
    Need,
    Page,
    Position,
} from '@/components/supply/workspace';
import { Button } from '@/components/ui/button';
import {
    supplyViewUrl,
    supplyEntityUrl,
    supplyReturnUrl,
} from '@/lib/supply/navigation';
const tabs = [
    ['stock', 'Existencias'],
    ['needs', 'Necesidades'],
    ['reservations', 'Reservas'],
    ['movements', 'Movimientos'],
    ['receipts', 'Recepciones'],
    ['deliveries', 'Entregas'],
    ['consumption', 'Consumos'],
    ['returns', 'Devoluciones'],
] as const;
type Reservation = {
    id: number;
    quantity: string;
    consumed_quantity: string;
    status: string;
    product: { name: string; sku: string | null };
    warehouse: { name: string };
    installation: {
        id: number;
        project_reference: string | null;
        site_name: string | null;
    } | null;
    work_order: { id: number; number: string } | null;
};
export default function Inventory({
    view,
    filters,
    positions,
    needs,
    reservations,
    movements,
    warehouses,
    needOptions,
}: {
    view: string;
    filters: Record<string, string>;
    positions: Page<Position> | null;
    needs: Page<Need> | null;
    reservations: Page<Reservation> | null;
    movements: Page<Movement> | null;
    warehouses: { id: number; name: string }[];
    needOptions: NeedOptions | null;
}) {
    const { url: context } = usePage();
    const returnTo = new URL(
        context,
        'https://supply.invalid',
    ).searchParams.get('return_to');
    const isDelivery = view === 'deliveries';
    const isReturn = view === 'returns';

    return (
        <>
            <Head title="Inventario" />
            <PageShell className="supply-workspace">
                <PageHeader
                    title="Inventario"
                    description="Consulta el material físico, reservado y disponible, y toda su trazabilidad."
                    secondaryActions={
                        <>
                            {returnTo && (
                                <Button asChild variant="outline">
                                    <Link href={supplyReturnUrl(returnTo)}>
                                        Volver al origen
                                    </Link>
                                </Button>
                            )}
                            <Button asChild variant="outline">
                                <Link href="/inventory/warehouses">
                                    Almacenes
                                </Link>
                            </Button>
                        </>
                    }
                    primaryAction={
                        view === 'needs' && needOptions ? (
                            <RequirementDialog options={needOptions} />
                        ) : (
                            <Button asChild>
                                <Link
                                    href={
                                        isDelivery || isReturn
                                            ? supplyEntityUrl(
                                                  '/inventory/transport',
                                                  context,
                                              )
                                            : view === 'reservations'
                                              ? '/inventory/reservations'
                                              : '/inventory/manage?section=movements'
                                    }
                                >
                                    {view === 'reservations'
                                        ? 'Nueva reserva'
                                        : isDelivery
                                          ? 'Nueva entrega'
                                          : isReturn
                                            ? 'Registrar devolución'
                                            : 'Registrar movimiento'}
                                </Link>
                            </Button>
                        )
                    }
                />
                <nav className="supply-tabs" aria-label="Inventario">
                    {tabs.map(([id, label]) => (
                        <Link
                            key={id}
                            href={supplyViewUrl(context, id)}
                            aria-current={view === id ? 'page' : undefined}
                        >
                            {label}
                        </Link>
                    ))}
                </nav>
                <Filters
                    url="/inventory"
                    clearUrl={`/inventory?view=${view}`}
                    filters={filters}
                    placeholder="Buscar material..."
                >
                    <input type="hidden" name="view" value={view} />
                    {!needs && (
                        <label>
                            Almacén
                            <select
                                name="warehouse"
                                defaultValue={filters.warehouse ?? ''}
                            >
                                <option value="">Todos</option>
                                {warehouses.map((r) => (
                                    <option value={r.id} key={r.id}>
                                        {r.name}
                                    </option>
                                ))}
                            </select>
                        </label>
                    )}
                    {filters.installation && (
                        <input
                            type="hidden"
                            name="installation"
                            value={filters.installation}
                        />
                    )}
                </Filters>
                <section className="supply-panel">
                    {positions && (
                        <>
                            <StockTable positions={positions.data} />
                            <Pagination page={positions} />
                        </>
                    )}
                    {needs && (
                        <>
                            <NeedTable needs={needs.data} />
                            <p className="supply-footnote">
                                La disponibilidad global se comparte entre
                                necesidades. El déficit no descuenta pedidos
                                pendientes.
                            </p>
                            <Pagination page={needs} />
                        </>
                    )}
                    {movements && (
                        <>
                            <Movements rows={movements.data} />
                            <Pagination page={movements} />
                        </>
                    )}
                    {reservations && (
                        <>
                            {reservations.data.length ? (
                                <table className="supply-table">
                                    <thead>
                                        <tr>
                                            <th>Producto</th>
                                            <th>Origen</th>
                                            <th>Almacén</th>
                                            <th>Reservado</th>
                                            <th>Consumido</th>
                                            <th>Estado</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {reservations.data.map((r) => (
                                            <tr key={r.id}>
                                                <td data-label="Producto">
                                                    {r.product.name}
                                                    <small>
                                                        {r.product.sku}
                                                    </small>
                                                </td>
                                                <td data-label="Origen">
                                                    {r.work_order ? (
                                                        <Link
                                                            href={supplyEntityUrl(
                                                                `/work-orders/${r.work_order.id}`,
                                                                context,
                                                            )}
                                                        >
                                                            {
                                                                r.work_order
                                                                    .number
                                                            }
                                                        </Link>
                                                    ) : r.installation ? (
                                                        <Link
                                                            href={`/installations/${r.installation.id}`}
                                                        >
                                                            {r.installation
                                                                .project_reference ??
                                                                r.installation
                                                                    .site_name}
                                                        </Link>
                                                    ) : (
                                                        '—'
                                                    )}
                                                </td>
                                                <td data-label="Almacén">
                                                    {r.warehouse.name}
                                                </td>
                                                <td data-label="Reservado">
                                                    {qty(
                                                        Number(r.quantity) -
                                                            Number(
                                                                r.consumed_quantity,
                                                            ),
                                                    )}
                                                </td>
                                                <td data-label="Consumido">
                                                    {qty(r.consumed_quantity)}
                                                </td>
                                                <td data-label="Estado">
                                                    {r.status}
                                                </td>
                                                <td data-label="Acciones">
                                                    {r.status === 'active' && (
                                                        <ReleaseReservation
                                                            id={r.id}
                                                        />
                                                    )}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            ) : (
                                <Empty>No hay reservas.</Empty>
                            )}
                            <Pagination page={reservations} />
                        </>
                    )}
                </section>
                <p className="supply-note">
                    Reservar compromete disponibilidad. Recibir incorpora
                    existencias. Una entrega conserva el material sin consumir.
                </p>
            </PageShell>
        </>
    );
}

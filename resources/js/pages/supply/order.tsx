import { Head, Link, useForm } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { PageHeader, PageShell } from '@/components/page-shell';
import ReceiptWarehouseCorrection from '@/components/receipt-warehouse-correction';
import { useSupplyConfirmation } from '@/components/supply/confirm-operation';
import { useSupplyDetailNavigation } from '@/components/supply/workspace';
import {
    date,
    Empty,
    money,
    Movements,
    Panel,
    qty,
} from '@/components/supply/workspace';
import type { Capabilities, Movement } from '@/components/supply/workspace';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useOperationsFormProtection } from '@/hooks/use-operations-form-protection';
import { purchaseStates } from './purchases';
type Line = {
    id: number;
    description: string;
    quantity: string;
    received_quantity: string;
    unit_price: string | null;
    total: string | null;
    product: {
        name: string;
        track_stock: boolean;
        track_serials: boolean;
        unit: { symbol: string } | null;
    } | null;
};
type Order = {
    id: number;
    number: string;
    status: string;
    issue_date: string;
    expected_delivery_date: string | null;
    supplier_confirmed_delivery_date: string | null;
    notes: string | null;
    subtotal: string | null;
    tax_amount: string | null;
    amount: string | null;
    supplier: {
        legal_name: string;
        email: string | null;
        phone: string | null;
        address_line_1: string | null;
        city: string | null;
    };
    destination: {
        id: number;
        name: string;
        address: string | null;
        city: string | null;
    } | null;
};
const tabs = [
    ['summary', 'Resumen'],
    ['lines', 'Líneas'],
    ['receipts', 'Recepciones'],
    ['documents', 'Documentos'],
    ['activity', 'Actividad'],
] as const;
export default function PurchaseOrder({
    order,
    lines,
    receipts,
    events,
    related,
    warehouses,
    capabilities,
}: {
    order: Order;
    lines: Line[];
    receipts: {
        id: number;
        number: string;
        warehouse_id: number;
        received_at: string;
        supplier_delivery_note: string | null;
        movement: Movement;
    }[];
    events: {
        id: number;
        event: string;
        created_at: string;
        user: { name: string } | null;
    }[];
    related: { id: number; number: string; type: string; status: string }[];
    warehouses: {
        id: number;
        name: string;
        code: string;
        is_usable?: boolean;
    }[];
    capabilities: Capabilities & { receive: boolean };
}) {
    const { tab, setTab, back } = useSupplyDetailNavigation('/purchases', tabs);
    const showReceipts = tab === 'receipts';
    const form = useForm({
        operation_key: crypto.randomUUID(),
        warehouse_id: String(order.destination?.id ?? warehouses[0]?.id ?? ''),
        supplier_delivery_note: '',
        quantities: Object.fromEntries(lines.map((l) => [l.id, '0'])),
        serials: Object.fromEntries(lines.map((l) => [l.id, ''])),
    });

    const protection = useOperationsFormProtection(() => form);
    const confirmation = useSupplyConfirmation();

    return (
        <>
            {protection.confirmation}
            {confirmation.dialog}
            <Head title={order.number} />
            <PageShell className="supply-workspace">
                <PageHeader
                    title={
                        <>
                            {order.number}
                            <span className="supply-badge">
                                {purchaseStates[order.status] ?? order.status}
                            </span>
                        </>
                    }
                    description={order.supplier.legal_name}
                    secondaryActions={
                        <>
                            <Button asChild variant="outline">
                                <Link href={back}>Volver a compras</Link>
                            </Button>
                            {capabilities.finance && (
                                <Button asChild variant="outline">
                                    <a
                                        href={`/purchases/${order.id}/pdf`}
                                        target="_blank"
                                        rel="noreferrer"
                                    >
                                        Generar PDF
                                    </a>
                                </Button>
                            )}
                            {capabilities.managePurchases &&
                                capabilities.finance && (
                                    <Button asChild variant="outline">
                                        <Link
                                            href={`/purchases/${order.id}?view=manage`}
                                        >
                                            Más acciones
                                        </Link>
                                    </Button>
                                )}
                        </>
                    }
                />
                <nav className="supply-tabs" aria-label="Orden de compra">
                    {tabs.map(([id, label]) => (
                        <a
                            key={id}
                            href={`#${id}`}
                            onClick={(event) => {
                                event.preventDefault();
                                setTab(id);
                            }}
                            aria-current={tab === id ? 'page' : undefined}
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
                                    <dd>{order.number}</dd>
                                </div>
                                <div>
                                    <dt>Estado</dt>
                                    <dd>
                                        {purchaseStates[order.status] ??
                                            order.status}
                                    </dd>
                                </div>
                                <div>
                                    <dt>Fecha</dt>
                                    <dd>{date(order.issue_date)}</dd>
                                </div>
                                <div>
                                    <dt>Entrega prevista</dt>
                                    <dd>
                                        {date(
                                            order.supplier_confirmed_delivery_date ??
                                                order.expected_delivery_date,
                                        )}
                                    </dd>
                                </div>
                                <div>
                                    <dt>Destino</dt>
                                    <dd>
                                        {order.destination?.name ?? '—'}
                                        <br />
                                        {order.destination?.address}
                                    </dd>
                                </div>
                                <div>
                                    <dt>Observaciones</dt>
                                    <dd>{order.notes ?? '—'}</dd>
                                </div>
                            </dl>
                        </Panel>
                        <Panel title="Proveedor">
                            <dl>
                                <div>
                                    <dt>Razón social</dt>
                                    <dd>{order.supplier.legal_name}</dd>
                                </div>
                                <div>
                                    <dt>Email</dt>
                                    <dd>{order.supplier.email ?? '—'}</dd>
                                </div>
                                <div>
                                    <dt>Teléfono</dt>
                                    <dd>{order.supplier.phone ?? '—'}</dd>
                                </div>
                                <div>
                                    <dt>Dirección</dt>
                                    <dd>
                                        {order.supplier.address_line_1 ?? '—'}
                                        <br />
                                        {order.supplier.city}
                                    </dd>
                                </div>
                            </dl>
                        </Panel>
                        {capabilities.finance && (
                            <Panel title="Totales">
                                <dl>
                                    <div>
                                        <dt>Base imponible</dt>
                                        <dd>{money(order.subtotal ?? 0)}</dd>
                                    </div>
                                    <div>
                                        <dt>Impuestos</dt>
                                        <dd>{money(order.tax_amount ?? 0)}</dd>
                                    </div>
                                    <div>
                                        <dt>Total</dt>
                                        <dd>
                                            <strong>
                                                {money(order.amount ?? 0)}
                                            </strong>
                                        </dd>
                                    </div>
                                </dl>
                            </Panel>
                        )}
                    </div>
                )}
                {tab === 'lines' && (
                    <Panel title="Líneas del pedido">
                        <table className="supply-table">
                            <thead>
                                <tr>
                                    <th>Material</th>
                                    <th>Cantidad</th>
                                    <th>Recibido</th>
                                    <th>Pendiente</th>
                                    {capabilities.finance && <th>Importe</th>}
                                </tr>
                            </thead>
                            <tbody>
                                {lines.map((r) => (
                                    <tr key={r.id}>
                                        <td data-label="Material">
                                            {r.description}
                                        </td>
                                        <td data-label="Cantidad">
                                            {qty(r.quantity)}{' '}
                                            {r.product?.unit?.symbol}
                                        </td>
                                        <td data-label="Recibido">
                                            {qty(r.received_quantity)}
                                        </td>
                                        <td data-label="Pendiente">
                                            {qty(
                                                Math.max(
                                                    0,
                                                    Number(r.quantity) -
                                                        Number(
                                                            r.received_quantity,
                                                        ),
                                                ),
                                            )}
                                        </td>
                                        {capabilities.finance && (
                                            <td data-label="Importe">
                                                {r.total === null
                                                    ? '—'
                                                    : money(r.total)}
                                            </td>
                                        )}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </Panel>
                )}
                {showReceipts && (
                    <>
                        <Panel title="Recepciones registradas">
                            {receipts.length ? (
                                receipts.map((r) => (
                                    <div key={r.id} className="p-4">
                                        <strong>{r.number}</strong>
                                        <p className="supply-note">
                                            {date(r.received_at)} ·{' '}
                                            {r.supplier_delivery_note ?? '—'}
                                        </p>
                                        <ReceiptWarehouseCorrection
                                            orderId={order.id}
                                            receiptId={r.id}
                                            warehouseId={r.warehouse_id}
                                            warehouses={warehouses}
                                        />
                                        <Movements rows={[r.movement]} />
                                    </div>
                                ))
                            ) : (
                                <Empty>No existen recepciones.</Empty>
                            )}
                        </Panel>
                        {capabilities.receive &&
                            ['ordered', 'partially_received'].includes(
                                order.status,
                            ) && (
                                <Panel title="Registrar recepción">
                                    <form
                                        onSubmit={(e) => {
                                            e.preventDefault();
                                            confirmation.confirm(() => {
                                                if (!protection.beginSubmit()) {
                                                    return;
                                                }

                                                form.post(
                                                    `/purchases/${order.id}/receive`,
                                                    {
                                                        onSuccess: () => {
                                                            const clean = {
                                                                ...form.data,
                                                                operation_key:
                                                                    crypto.randomUUID(),
                                                                supplier_delivery_note:
                                                                    '',
                                                                quantities:
                                                                    Object.fromEntries(
                                                                        lines.map(
                                                                            (
                                                                                line,
                                                                            ) => [
                                                                                line.id,
                                                                                '0',
                                                                            ],
                                                                        ),
                                                                    ),
                                                                serials:
                                                                    Object.fromEntries(
                                                                        lines.map(
                                                                            (
                                                                                line,
                                                                            ) => [
                                                                                line.id,
                                                                                '',
                                                                            ],
                                                                        ),
                                                                    ),
                                                            };
                                                            form.setDefaults(
                                                                clean,
                                                            );
                                                            protection.saved();
                                                            form.setData(clean);
                                                        },
                                                        onFinish:
                                                            protection.finishSubmit,
                                                    },
                                                );
                                            });
                                        }}
                                        className="p-4"
                                    >
                                        <div className="supply-filters">
                                            <label>
                                                Almacén
                                                <select
                                                    required
                                                    value={
                                                        form.data.warehouse_id
                                                    }
                                                    onChange={(e) =>
                                                        form.setData(
                                                            'warehouse_id',
                                                            e.target.value,
                                                        )
                                                    }
                                                >
                                                    <option value="">
                                                        Seleccionar
                                                    </option>
                                                    {warehouses.map((r) => (
                                                        <option
                                                            key={r.id}
                                                            value={r.id}
                                                        >
                                                            {r.name}
                                                        </option>
                                                    ))}
                                                </select>
                                            </label>
                                            <label>
                                                Albarán proveedor
                                                <Input
                                                    value={
                                                        form.data
                                                            .supplier_delivery_note
                                                    }
                                                    onChange={(e) =>
                                                        form.setData(
                                                            'supplier_delivery_note',
                                                            e.target.value,
                                                        )
                                                    }
                                                />
                                            </label>
                                        </div>
                                        {lines
                                            .filter(
                                                (l) =>
                                                    l.product?.track_stock &&
                                                    Number(l.quantity) >
                                                        Number(
                                                            l.received_quantity,
                                                        ),
                                            )
                                            .map((line) => (
                                                <div
                                                    key={line.id}
                                                    className="supply-filters"
                                                >
                                                    <label>
                                                        {line.description}
                                                        <Input
                                                            type="number"
                                                            min="0"
                                                            step={
                                                                line.product
                                                                    ?.track_serials
                                                                    ? '1'
                                                                    : '0.001'
                                                            }
                                                            max={
                                                                Number(
                                                                    line.quantity,
                                                                ) -
                                                                Number(
                                                                    line.received_quantity,
                                                                )
                                                            }
                                                            value={
                                                                form.data
                                                                    .quantities[
                                                                    line.id
                                                                ]
                                                            }
                                                            onChange={(e) =>
                                                                form.setData(
                                                                    'quantities',
                                                                    {
                                                                        ...form
                                                                            .data
                                                                            .quantities,
                                                                        [line.id]:
                                                                            e
                                                                                .target
                                                                                .value,
                                                                    },
                                                                )
                                                            }
                                                        />
                                                    </label>
                                                    {line.product
                                                        ?.track_serials && (
                                                        <label>
                                                            Números de serie
                                                            <Input
                                                                value={
                                                                    form.data
                                                                        .serials[
                                                                        line.id
                                                                    ]
                                                                }
                                                                onChange={(e) =>
                                                                    form.setData(
                                                                        'serials',
                                                                        {
                                                                            ...form
                                                                                .data
                                                                                .serials,
                                                                            [line.id]:
                                                                                e
                                                                                    .target
                                                                                    .value,
                                                                        },
                                                                    )
                                                                }
                                                            />
                                                        </label>
                                                    )}
                                                </div>
                                            ))}
                                        {Object.entries(form.errors).map(
                                            ([key, message]) => (
                                                <InputError
                                                    key={key}
                                                    message={message}
                                                />
                                            ),
                                        )}
                                        <Button
                                            type="submit"
                                            disabled={form.processing}
                                        >
                                            Registrar recepción
                                        </Button>
                                    </form>
                                </Panel>
                            )}
                    </>
                )}
                {tab === 'documents' && (
                    <Panel title="Documentos relacionados">
                        {related.length ? (
                            <ul className="p-4">
                                {related.map((r) => (
                                    <li key={r.id}>
                                        <Link href={`/purchases/${r.id}`}>
                                            {r.number} · {r.status}
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        ) : (
                            <Empty>No hay documentos relacionados.</Empty>
                        )}
                        {capabilities.finance && (
                            <a
                                className="block p-4 text-sm"
                                href={`/purchases/${order.id}/pdf`}
                                target="_blank"
                                rel="noreferrer"
                            >
                                Orden de compra PDF
                            </a>
                        )}
                    </Panel>
                )}
                {tab === 'activity' && (
                    <Panel title="Actividad">
                        {events.length ? (
                            <ul className="p-4">
                                {events.map((r) => (
                                    <li
                                        key={r.id}
                                        className="border-t py-3 text-sm"
                                    >
                                        {r.event}
                                        <small className="supply-note block">
                                            {date(r.created_at)} ·{' '}
                                            {r.user?.name}
                                        </small>
                                    </li>
                                ))}
                            </ul>
                        ) : (
                            <Empty>No hay actividad registrada.</Empty>
                        )}
                    </Panel>
                )}
            </PageShell>
        </>
    );
}

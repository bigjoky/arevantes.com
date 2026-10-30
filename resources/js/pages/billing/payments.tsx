import { Head, Link, usePage } from '@inertiajs/react';
import {
    Panel,
    QueryFilters,
    Dates,
    Pagination,
    MovementTable,
    InvoiceTable,
} from '@/components/billing/workspace';
import type {
    Capabilities,
    Movement,
    Invoice,
    Page,
    Filters,
} from '@/components/billing/workspace';
import { PageHeader, PageShell } from '@/components/page-shell';
import { Button } from '@/components/ui/button';
import { financeView } from '@/lib/finance/navigation';
export default function Payments({
    movements,
    pending,
    filters,
    capabilities,
}: {
    movements: Page<Movement>;
    pending: Page<Invoice> | null;
    filters: Filters;
    capabilities: Capabilities;
}) {
    const { url } = usePage();
    const tab = filters.tab ?? 'movements';

    return (
        <PageShell className="billing-workspace">
            <Head title="Cobros y pagos" />
            <PageHeader
                title="Cobros y pagos"
                description="Consulta los movimientos de cobro y pago y los saldos de tus facturas."
                breadcrumbs={[
                    { title: 'Facturación', href: '/billing' },
                    { title: 'Cobros y pagos', href: '/billing/payments' },
                ]}
                actions={
                    (capabilities.recordReceipt ||
                        capabilities.recordPayment) && (
                        <Button asChild>
                            <Link href={`/billing/payments?tab=pending`}>
                                Registrar movimiento en una factura
                            </Link>
                        </Button>
                    )
                }
            />
            <Panel title="">
                <nav className="billing-tabs" aria-label="Vista de tesorería">
                    {[
                        ['movements', 'Movimientos'],
                        ['pending', 'Pendientes'],
                        ['due', 'Vencimientos'],
                    ].map(([key, label]) => (
                        <Link
                            key={key}
                            href={financeView(url, { tab: key })}
                            aria-current={tab === key ? 'page' : undefined}
                        >
                            {label}
                        </Link>
                    ))}
                </nav>
                <QueryFilters path="/billing/payments" filters={filters}>
                    <input type="hidden" name="tab" value={tab} />
                    <label>
                        Tipo
                        <select name="kind" defaultValue={filters.kind}>
                            <option value="">Todos</option>
                            <option value="receipt">Cobros</option>
                            <option value="payment">Pagos</option>
                        </select>
                    </label>
                    <Dates filters={filters} />
                </QueryFilters>
                {pending ? (
                    <>
                        <InvoiceTable rows={pending.data} />
                        <Pagination page={pending} noun="facturas con saldo" />
                    </>
                ) : (
                    <>
                        <MovementTable rows={movements.data} />
                        <Pagination page={movements} noun="movimientos" />
                    </>
                )}
            </Panel>
            <p className="billing-footnote">
                Los movimientos proceden de los registros de cobro y pago del
                ERP. La conciliación bancaria se gestiona en{' '}
                <Link href="/banking">Bancos y conciliación</Link>.
            </p>
        </PageShell>
    );
}

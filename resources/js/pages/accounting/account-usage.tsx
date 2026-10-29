import { Head, Link } from '@inertiajs/react';
import { Panel } from '@/components/billing/workspace';
import { PageHeader, PageShell, PaginationBar } from '@/components/page-shell';
import type { PaginationLink } from '@/components/page-shell';
export const usageNames: Record<string, string> = {
    people: 'Clientes y personal',
    suppliers: 'Proveedores',
    products: 'Productos y servicios',
    expense_categories: 'Categorías de gastos',
    tax_rates: 'Tipos de IVA',
    expenses: 'Gastos',
    accounting_template_lines: 'Plantillas contables',
    bank_accounts: 'Bancos',
    supplier_payments: 'Pagos',
    bank_financial_products: 'Productos financieros',
    bank_reconciliation_rules: 'Reglas bancarias',
    journal_lines: 'Apuntes contables',
    accounting_account_assignments: 'Asignaciones contables',
    configuration: 'Configuración contable',
};
export default function AccountUsage({
    account,
    type,
    usages,
    rows,
}: {
    account: { id: number; code: string; name: string };
    type: string;
    usages: { type: string; count: number; href: string }[];
    rows: {
        data: { id: number; label: string | null; href: string | null }[];
        links: PaginationLink[];
        total: number;
    };
}) {
    return (
        <>
            <Head title={account.code} />
            <PageShell mode="full">
                <PageHeader
                    title={`Usos de ${account.code} · ${account.name}`}
                    description="Consulta de relaciones reales, sin modificar movimientos."
                    actions={
                        <Link href={`/accounting/accounts/${account.id}`}>
                            Volver a la cuenta
                        </Link>
                    }
                />
                <nav className="flex flex-wrap gap-3">
                    {usages.map((u) => (
                        <Link href={u.href} key={u.type}>
                            {usageNames[u.type] ?? u.type} · {u.count}
                        </Link>
                    ))}
                </nav>
                <Panel title={usageNames[type] ?? type}>
                    <ul className="divide-y">
                        {rows.data.map((row) => (
                            <li
                                key={row.id}
                                className="flex flex-wrap items-center justify-between gap-3 py-3"
                            >
                                <span>
                                    {row.label || 'Sin denominación registrada'}{' '}
                                    · ID {row.id}
                                </span>
                                {row.href ? (
                                    <Link href={row.href}>
                                        Consultar origen
                                    </Link>
                                ) : (
                                    <span>Origen no disponible</span>
                                )}
                            </li>
                        ))}
                    </ul>
                    <PaginationBar
                        links={rows.links}
                        total={rows.total}
                        noun="usos"
                    />
                </Panel>
            </PageShell>
        </>
    );
}

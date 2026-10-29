import { Head, Link } from '@inertiajs/react';
import { PartyAccounting } from '@/components/accounting/party-accounting';
import type { PartyAccountingSummary } from '@/components/accounting/party-accounting';
import { Pagination, Panel, money } from '@/components/billing/workspace';
import { PageHeader, PageShell } from '@/components/page-shell';
type Line = {
    id: number;
    journal_entry_id: number;
    number: string;
    entry_date: string;
    code: string;
    description: string;
    debit: string;
    credit: string;
};
export default function PartyLedger({
    summary,
    lines,
    unattributed,
}: {
    summary: PartyAccountingSummary;
    lines: Parameters<typeof Pagination<Line>>[0]['page'];
    unattributed: boolean;
}) {
    return (
        <>
            <Head title="Situación contable" />
            <PageShell>
                <PageHeader
                    title={summary.party.name}
                    description="Situación contable"
                />
                <PartyAccounting summary={summary} />
                <Panel
                    title={
                        unattributed
                            ? 'Movimientos pendientes de identificación'
                            : 'Movimientos confirmados'
                    }
                >
                    <div className="space-y-3 p-4">
                        <p className="mb-3 text-sm">
                            Cada movimiento conserva su fecha contable real. La
                            cuenta compartida no prueba por sí misma la
                            identidad del tercero.
                        </p>
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr>
                                        <th>Fecha</th>
                                        <th>Asiento</th>
                                        <th>Cuenta</th>
                                        <th>Concepto</th>
                                        <th>Debe</th>
                                        <th>Haber</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {lines.data.map((l) => (
                                        <tr className="border-t" key={l.id}>
                                            <td className="p-2">
                                                {l.entry_date.slice(0, 10)}
                                            </td>
                                            <td>
                                                <Link
                                                    href={`/accounting/entries/${l.journal_entry_id}`}
                                                >
                                                    {l.number}
                                                </Link>
                                            </td>
                                            <td>{l.code}</td>
                                            <td>{l.description}</td>
                                            <td>{money(l.debit)}</td>
                                            <td>{money(l.credit)}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                        <Pagination page={lines} noun="apuntes" />
                    </div>
                </Panel>
            </PageShell>
        </>
    );
}

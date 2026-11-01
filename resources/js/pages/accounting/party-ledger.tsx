import '@/../css/accounting-mobile07.css';
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
            <PageShell className="accounting-mobile07">
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
                        <div className="hidden overflow-x-auto lg:block">
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
                        <div className="grid gap-3 lg:hidden">
                            {lines.data.map((l) => (
                                <article
                                    key={l.id}
                                    className="grid min-w-0 gap-2 rounded-lg border p-4 text-sm"
                                >
                                    <Link
                                        className="min-h-11 font-semibold"
                                        href={`/accounting/entries/${l.journal_entry_id}`}
                                    >
                                        {l.number} · {l.entry_date.slice(0, 10)}
                                    </Link>
                                    <span>
                                        {l.code} · {l.description}
                                    </span>
                                    <span>
                                        Debe: {money(l.debit)} · Haber:{' '}
                                        {money(l.credit)}
                                    </span>
                                </article>
                            ))}
                        </div>
                        <Pagination page={lines} noun="apuntes" />
                    </div>
                </Panel>
            </PageShell>
        </>
    );
}

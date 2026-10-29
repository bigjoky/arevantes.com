import { Head, Link } from '@inertiajs/react';
import { Panel, money } from '@/components/billing/workspace';
import { PageHeader, PageShell } from '@/components/page-shell';
type Batch = {
    id: string;
    reason: string;
    correction_date: string;
    recorded_at_utc: string;
    results: {
        corrections: {
            entry_id: number;
            correction_id: number;
            adjustment_entry_id: number;
            effects: {
                line_id: number;
                from_account: string;
                to_account: string;
                debit: string;
                credit: string;
            }[];
        }[];
        blocked: { line_id: number; reason: string }[];
    };
};
export default function BatchResult({
    batch,
    actor,
}: {
    batch: Batch;
    actor: string | null;
}) {
    return (
        <>
            <Head title="Resultado de reclasificación" />
            <PageShell>
                <PageHeader
                    title="Resultado de reclasificación"
                    description={batch.id}
                />
                <Panel title="Lote contabilizado">
                    <div className="space-y-3 p-4">
                        <p>{batch.reason}</p>
                        <p>Fecha contable: {batch.correction_date}</p>
                        <p>
                            {actor ?? 'Sin registrar'} ·{' '}
                            {new Intl.DateTimeFormat('es-ES', {
                                dateStyle: 'short',
                                timeStyle: 'long',
                                timeZone: 'Europe/Madrid',
                            }).format(new Date(batch.recorded_at_utc))}
                        </p>
                        <p>
                            Originales conservados. Cada asiento de ajuste está
                            enlazado y auditado.
                        </p>
                        {batch.results.corrections.map((c) => (
                            <section
                                className="my-3 rounded border p-3"
                                key={c.correction_id}
                            >
                                <Link
                                    href={`/accounting/entries/${c.entry_id}`}
                                >
                                    Consultar asiento original
                                </Link>
                                <Link
                                    className="ml-3"
                                    href={`/accounting/entries/${c.adjustment_entry_id}`}
                                >
                                    Consultar ajuste
                                </Link>
                                {c.effects.map((e) => (
                                    <p key={e.line_id}>
                                        {e.from_account} → {e.to_account} · Debe{' '}
                                        {money(e.debit)} · Haber{' '}
                                        {money(e.credit)}
                                    </p>
                                ))}
                            </section>
                        ))}
                    </div>
                </Panel>
                {batch.results.blocked.length > 0 && (
                    <Panel title="Elementos bloqueados, sin cambios">
                        <div className="space-y-3 p-4">
                            {batch.results.blocked.map((b) => (
                                <p key={b.line_id}>
                                    Apunte {b.line_id}: {b.reason}
                                </p>
                            ))}
                        </div>
                    </Panel>
                )}
                <Link href="/accounting/reclassification">
                    Volver a reclasificación
                </Link>
            </PageShell>
        </>
    );
}

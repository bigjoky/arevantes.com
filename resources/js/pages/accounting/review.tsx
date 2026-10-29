import { Form, Head, Link } from '@inertiajs/react';
import { Panel } from '@/components/billing/workspace';
import { PageHeader, PageShell, PaginationBar } from '@/components/page-shell';
import type { PaginationLink } from '@/components/page-shell';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
const labels: Record<string, string> = {
    missing_party: 'Terceros sin cuenta específica',
    generic_accounts: 'Cuentas generales utilizadas',
    duplicate_accounts: 'Posibles subcuentas duplicadas',
    incompatible_assignments: 'Asignaciones incompatibles',
    poor_concepts: 'Conceptos poco informativos',
    missing_references: 'Referencias documentales pendientes',
    pending_classification: 'Movimientos pendientes de clasificación',
    inactive_rules: 'Reglas con cuentas inactivas',
};
export default function Review({
    issue,
    counts,
    rows,
    search,
}: {
    issue: string;
    counts: Record<string, number>;
    rows: {
        data: { id: number; title: string; href: string }[];
        links: PaginationLink[];
        total: number;
    };
    search: string;
}) {
    return (
        <>
            <Head title="Revisión contable" />
            <PageShell mode="full">
                <PageHeader
                    title="Bandeja de revisión contable"
                    description="Diagnóstico informativo. Ninguna incidencia reclasifica ni modifica el histórico automáticamente."
                    actions={
                        <Button variant="outline" asChild>
                            <Link href="/accounting/control">
                                Configuración contable
                            </Link>
                        </Button>
                    }
                />
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    {Object.entries(counts).map(([key, count]) => (
                        <Link
                            className={`rounded border p-4 ${issue === key ? 'border-primary bg-muted' : ''}`}
                            key={key}
                            href={`/accounting/review?issue=${key}`}
                        >
                            <strong>{labels[key]}</strong>
                            <p>{count}</p>
                        </Link>
                    ))}
                </div>
                <Panel title={labels[issue]}>
                    <p className="mb-3 text-sm">
                        Una cuenta general o una coincidencia de denominación no
                        demuestra un error. Revisa su contexto antes de decidir.
                    </p>
                    <Form
                        action="/accounting/review"
                        method="get"
                        className="mb-4 flex gap-3"
                    >
                        <input name="issue" type="hidden" value={issue} />
                        <Input
                            name="search"
                            defaultValue={search}
                            placeholder="Buscar referencia o cuenta"
                        />
                        <Button variant="outline">Buscar</Button>
                    </Form>
                    {rows.data.length ? (
                        <ul className="divide-y">
                            {rows.data.map((r, i) => (
                                <li
                                    className="flex items-center justify-between gap-3 py-3"
                                    key={`${r.id}:${i}`}
                                >
                                    <span>{r.title}</span>
                                    <Link href={r.href}>Revisar origen</Link>
                                </li>
                            ))}
                        </ul>
                    ) : (
                        <p>Sin incidencias registradas para este criterio.</p>
                    )}
                    <PaginationBar
                        links={rows.links}
                        total={rows.total}
                        noun="incidencias"
                    />
                </Panel>
            </PageShell>
        </>
    );
}

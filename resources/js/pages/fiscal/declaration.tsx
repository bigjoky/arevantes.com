import { Head, Link } from '@inertiajs/react';
import { FileCheck2 } from 'lucide-react';
import { Panel, money, date } from '@/components/billing/workspace';
import { PageShell, PageHeader } from '@/components/page-shell';
import { Button } from '@/components/ui/button';
import '@/../css/fiscal.css';
type Props = {
    year: number;
    declaration: {
        id: number;
        model: string;
        period: string;
        from: string;
        to: string;
        status: string;
        amount: string;
        preparedAt: string | null;
        filedOn: string | null;
        reference: string | null;
        evidence: boolean;
        receiptHref: string | null;
    };
    summary: {
        outputVat: string | null;
        inputVat: string | null;
        paid: string | null;
        calculationVersion: string | null;
        hasSnapshot: boolean;
    };
    capabilities: { manageTax: boolean };
    manageHref: string;
};
const states: Record<string, string> = {
    draft: 'Borrador',
    approved: 'Revisado internamente',
    prepared: 'Preparado / contabilizado',
    filed: 'Presentación registrada',
    evidence_pending: 'Evidencia pendiente',
};
export default function Declaration(p: Props) {
    const d = p.declaration;

    return (
        <PageShell className="fiscal-workspace">
            <Head title={`Modelo ${d.model} · ${d.period}`} />
            <PageHeader
                secondaryNavigation={null}
                title={`Modelo ${d.model} · ${d.period}`}
                description="Consulta el cálculo guardado y la evidencia vinculada."
                breadcrumbs={[
                    { title: 'Fiscal', href: '/fiscal' },
                    {
                        title: 'Modelos',
                        href: `/fiscal/modelos?year=${p.year}`,
                    },
                    {
                        title: d.period,
                        href: `/fiscal/declaraciones/${d.model}/${d.id}`,
                    },
                ]}
                actions={
                    <Button asChild variant="outline">
                        <Link href={p.manageHref}>
                            {p.capabilities.manageTax
                                ? 'Revisión y gestión existente'
                                : 'Consultar detalle existente'}
                        </Link>
                    </Button>
                }
            />
            <div className="fiscal-two">
                <Panel title="Información de la declaración">
                    <dl className="fiscal-dl">
                        <div>
                            <dt>Modelo</dt>
                            <dd>{d.model}</dd>
                        </div>
                        <div>
                            <dt>Periodo</dt>
                            <dd>
                                {date(d.from)} – {date(d.to)}
                            </dd>
                        </div>
                        <div>
                            <dt>Estado</dt>
                            <dd>
                                <span className="fiscal-state">
                                    {states[d.status] ?? d.status}
                                </span>
                            </dd>
                        </div>
                        <div>
                            <dt>Preparación</dt>
                            <dd>{date(d.preparedAt)}</dd>
                        </div>
                        <div>
                            <dt>Resultado guardado</dt>
                            <dd>{money(d.amount)}</dd>
                        </div>
                        {p.summary.outputVat !== null && (
                            <>
                                <div>
                                    <dt>IVA repercutido del borrador</dt>
                                    <dd>{money(p.summary.outputVat)}</dd>
                                </div>
                                <div>
                                    <dt>IVA deducible del borrador</dt>
                                    <dd>{money(p.summary.inputVat)}</dd>
                                </div>
                                <div>
                                    <dt>Pagos registrados</dt>
                                    <dd>{money(p.summary.paid)}</dd>
                                </div>
                            </>
                        )}
                    </dl>
                    <p className="fiscal-note">
                        Las cifras guardadas conservan su ámbito de cálculo. No
                        se sustituyen por el resumen documental actual ni se
                        consideran una determinación fiscal completa.
                    </p>
                </Panel>
                <Panel title="Presentación y justificante">
                    <FileCheck2 className="fiscal-icon" />
                    <dl className="fiscal-dl">
                        <div>
                            <dt>Fecha registrada</dt>
                            <dd>{date(d.filedOn)}</dd>
                        </div>
                        <div>
                            <dt>Referencia registrada</dt>
                            <dd>{d.reference ?? 'Sin referencia'}</dd>
                        </div>
                        <div>
                            <dt>Integridad del justificante</dt>
                            <dd>
                                {d.evidence
                                    ? 'Coincide con la huella guardada'
                                    : 'Sin evidencia verificada'}
                            </dd>
                        </div>
                    </dl>
                    {d.receiptHref && (
                        <a href={d.receiptHref}>
                            Descargar justificante protegido →
                        </a>
                    )}
                    <p className="fiscal-note">
                        La huella verifica la integridad del archivo registrado,
                        no su autenticidad ante AEAT. Un documento de trabajo no
                        cambia el estado a presentado.
                    </p>
                </Panel>
            </div>
            <Panel title="Trazabilidad del cálculo">
                <dl className="fiscal-dl">
                    <div>
                        <dt>Snapshot conservado</dt>
                        <dd>
                            {p.summary.hasSnapshot
                                ? 'Disponible en el servicio existente'
                                : 'No disponible'}
                        </dd>
                    </div>
                    <div>
                        <dt>Versión de reglas guardada</dt>
                        <dd>
                            {p.summary.calculationVersion ??
                                'No registrada en este campo'}
                        </dd>
                    </div>
                </dl>
                <Link href={p.manageHref}>
                    Consultar fuentes, diferencias y validaciones existentes →
                </Link>
                <p className="fiscal-note">
                    La preparación, revisión, contabilización, registro de
                    presentación y pagos utilizan sus controles y permisos
                    originales. No se ejecuta ninguna acción al abrir esta
                    ficha.
                </p>
            </Panel>
        </PageShell>
    );
}

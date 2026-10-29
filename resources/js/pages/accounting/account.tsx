import { Head, Link } from '@inertiajs/react';
import { Panel, money } from '@/components/billing/workspace';
import { PageHeader, PageShell } from '@/components/page-shell';
import { Button } from '@/components/ui/button';
import { usageNames } from './account-usage';
import { accountTypes } from './workspace';
import '@/../css/accounting.css';
export default function Account({
    account,
    year,
    canManage,
    canReclassify,
    usage = [],
}: {
    account: {
        code: string;
        name: string;
        parent_code: string | null;
        type: string;
        stored_type?: string;
        source: string;
        source_version: string | null;
        is_active: boolean;
        debit: string;
        credit: string;
        balance: string;
    };
    year: number;
    canManage: boolean;
    canReclassify?: boolean;
    usage?: { type: string; count: number; href: string }[];
}) {
    return (
        <>
            <Head title={account.code} />
            <PageShell mode="full" className="accounting-workspace">
                <PageHeader
                    title={`${account.code} · ${account.name}`}
                    description="Información de la cuenta y acceso a sus apuntes contabilizados."
                    secondaryNavigation={null}
                    actions={
                        <Button variant="outline" asChild>
                            <Link href={`/accounting/accounts?year=${year}`}>
                                Volver al plan contable
                            </Link>
                        </Button>
                    }
                />
                {canReclassify && (
                    <Button asChild variant="outline">
                        <Link
                            href={`/accounting/reclassification?account=${account.code}&year=${year}`}
                        >
                            Reclasificar apuntes de esta cuenta
                        </Link>
                    </Button>
                )}
                <Panel title="Información de la cuenta">
                    <dl className="accounting-details">
                        <div>
                            <dt>Naturaleza</dt>
                            <dd>{accountTypes[account.type]}</dd>
                        </div>
                        <div>
                            <dt>Cuenta padre</dt>
                            <dd>{account.parent_code ?? 'Sin cuenta padre'}</dd>
                        </div>
                        <div>
                            <dt>Estado</dt>
                            <dd>{account.is_active ? 'Activa' : 'Inactiva'}</dd>
                        </div>
                        <div>
                            <dt>Origen</dt>
                            <dd>
                                {account.source} ·{' '}
                                {account.source_version ?? '—'}
                            </dd>
                        </div>
                        <div>
                            <dt>Ejercicio</dt>
                            <dd>{year}</dd>
                        </div>
                        <div>
                            <dt>Saldo según naturaleza</dt>
                            <dd>{money(account.balance)}</dd>
                        </div>
                        <div>
                            <dt>Debe</dt>
                            <dd>{money(account.debit)}</dd>
                        </div>
                        <div>
                            <dt>Haber</dt>
                            <dd>{money(account.credit)}</dd>
                        </div>
                    </dl>
                </Panel>
                <Button asChild>
                    <Link
                        href={`/accounting?area=reports&view=ledger&account=${account.code}&year=${year}`}
                    >
                        Consultar Libro Mayor
                    </Link>
                </Button>
                {canManage && (
                    <Button variant="outline" asChild>
                        <Link
                            href={`/accounting/accounts?mode=manage&search=${account.code}`}
                        >
                            Gestionar cuenta y subcuentas
                        </Link>
                    </Button>
                )}
                <p className="accounting-notice">
                    Saldo calculado desde apuntes contabilizados del ejercicio.
                    El historial específico de cambios y los documentos se
                    consultan desde los asientos relacionados.
                </p>
                {account.stored_type &&
                    account.stored_type !== account.type && (
                        <Panel title="Metadatos heredados pendientes de revisión">
                            <p>
                                Se muestra la naturaleza del PGC sin modificar
                                la clasificación almacenada ni los asientos
                                históricos.
                            </p>
                        </Panel>
                    )}
                <Panel title="Dónde se utiliza">
                    {usage.length ? (
                        usage.map((u) => (
                            <p key={u.type}>
                                <Link href={u.href}>
                                    {usageNames[u.type] ?? u.type} · {u.count}
                                </Link>
                            </p>
                        ))
                    ) : (
                        <p>Sin usos registrados.</p>
                    )}
                </Panel>
            </PageShell>
        </>
    );
}

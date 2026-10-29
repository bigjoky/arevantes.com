import { Form, Link, usePage } from '@inertiajs/react';
import { Panel, money } from '@/components/billing/workspace';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
export type PartyAccountingSummary = {
    period: { year: number; from: string; to: string };
    party: { kind: string; id: number; name: string };
    current_account: string | null;
    historical_accounts: number;
    opening: string;
    debit: string;
    credit: string;
    consolidated_opening: string;
    consolidated_closing: string;
    total_debit: string;
    total_credit: string;
    closing: string;
    advance: string;
    unattributed_count: number;
    href: string;
    accounts: {
        code: string;
        name: string;
        opening: string;
        debit: string;
        credit: string;
        closing: string;
        advance: boolean;
        href: string;
    }[];
};
export function PartyAccounting({
    summary,
}: {
    summary?: PartyAccountingSummary | null;
}) {
    const page = usePage();

    if (!summary) {
        return null;
    }

    const url = new URL(page.url, 'http://localhost');
    const preserved = [...url.searchParams].filter(
        ([k]) => !k.startsWith('ledger_') && k !== 'page',
    );

    return (
        <Panel title="Situación contable">
            <div className="space-y-3 p-4">
                <p className="text-sm text-muted-foreground">
                    Saldo confirmado de apuntes contabilizados. No equivale al
                    importe pendiente de las facturas. Los anticipos se muestran
                    por separado.
                </p>
                <p className="my-2 text-sm">
                    {summary.party.kind === 'customer'
                        ? 'Positivo: saldo contable a cobrar. Negativo: a favor del cliente.'
                        : 'Positivo: saldo contable a pagar. Negativo: a favor de la empresa.'}
                </p>
                <p className="my-2 text-sm">
                    El saldo consolidado descuenta los anticipos, que también se
                    desglosan.
                </p>
                <Form
                    action={url.pathname}
                    method="get"
                    className="my-3 grid gap-3 sm:grid-cols-4"
                >
                    {preserved.map(([k, v]) => (
                        <input key={k} type="hidden" name={k} value={v} />
                    ))}
                    <label>
                        Ejercicio
                        <Input
                            name="ledger_year"
                            type="number"
                            min="2020"
                            max="2100"
                            defaultValue={summary.period.year}
                        />
                    </label>
                    <label>
                        Desde
                        <Input
                            name="ledger_from"
                            type="date"
                            defaultValue={summary.period.from}
                        />
                    </label>
                    <label>
                        Hasta
                        <Input
                            name="ledger_to"
                            type="date"
                            defaultValue={summary.period.to}
                        />
                    </label>
                    <Button variant="outline">Consultar periodo</Button>
                </Form>
                <dl className="grid gap-3 sm:grid-cols-3">
                    <div>
                        <dt>Saldo inicial</dt>
                        <dd>{money(summary.consolidated_opening)}</dd>
                    </div>
                    <div>
                        <dt>Total Debe</dt>
                        <dd>{money(summary.total_debit)}</dd>
                    </div>
                    <div>
                        <dt>Total Haber</dt>
                        <dd>{money(summary.total_credit)}</dd>
                    </div>
                    <div>
                        <dt>Saldo consolidado</dt>
                        <dd className="font-semibold">
                            {money(summary.consolidated_closing)}
                        </dd>
                    </div>
                    <div>
                        <dt>Anticipos</dt>
                        <dd>{money(summary.advance)}</dd>
                    </div>
                    <div>
                        <dt>Cuenta principal actual</dt>
                        <dd>{summary.current_account ?? 'Sin registrar'}</dd>
                    </div>
                </dl>
                <p className="my-3 text-sm">
                    Cuentas históricas vinculadas: {summary.historical_accounts}
                </p>
                {summary.unattributed_count > 0 && (
                    <p role="status" className="my-3 rounded border p-3">
                        Movimientos pendientes de identificación:{' '}
                        {summary.unattributed_count}. No incluidos en el saldo
                        confirmado.
                    </p>
                )}
                <div className="my-3 overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead>
                            <tr>
                                {[
                                    'Cuenta',
                                    'Saldo inicial',
                                    'Debe',
                                    'Haber',
                                    'Saldo final',
                                ].map((t) => (
                                    <th className="p-2 text-left" key={t}>
                                        {t}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {summary.accounts.map((a) => (
                                <tr key={a.code} className="border-t">
                                    <td className="p-2">
                                        <Link
                                            href={`${a.href}&ledger_year=${summary.period.year}&ledger_from=${summary.period.from}&ledger_to=${summary.period.to}`}
                                        >
                                            {a.code} · {a.name}
                                        </Link>
                                        {a.advance && (
                                            <small className="block">
                                                Anticipo
                                            </small>
                                        )}
                                        <small className="block text-muted-foreground">
                                            Atribución por documento
                                            contabilizado y sus ajustes
                                            enlazados.
                                        </small>
                                    </td>
                                    {[
                                        a.opening,
                                        a.debit,
                                        a.credit,
                                        a.closing,
                                    ].map((v, i) => (
                                        <td className="p-2" key={i}>
                                            {money(v)}
                                        </td>
                                    ))}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                <Link
                    href={`${summary.href}?ledger_year=${summary.period.year}&ledger_from=${summary.period.from}&ledger_to=${summary.period.to}`}
                >
                    Consultar mayor del tercero
                </Link>
                {summary.unattributed_count > 0 && (
                    <Link
                        className="ml-3"
                        href={`${summary.href}?unattributed=1&ledger_year=${summary.period.year}&ledger_from=${summary.period.from}&ledger_to=${summary.period.to}`}
                    >
                        Revisar movimientos sin atribución
                    </Link>
                )}
            </div>
        </Panel>
    );
}

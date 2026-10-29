import { Form, Head, Link, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { AccountInput } from '@/components/accounting/account-input';
import { Panel, Pagination, money } from '@/components/billing/workspace';
import { PageHeader, PageShell } from '@/components/page-shell';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
type Line = {
    id: number;
    journal_entry_id: number;
    number: string;
    entry_date: string;
    entry_kind: string;
    status: string;
    code: string;
    description: string;
    debit: string;
    credit: string;
    party_kind: string | null;
    party_id: number | null;
    blocked_reason: string | null;
};
type Effect = {
    line_id: number;
    from_account: string;
    to_account: string;
    debit: string;
    credit: string;
    party_balance_effect: string | null;
};
type Preview = {
    account_totals: { account_code: string; debit: string; credit: string }[];
    preview_revision: string;
    entry_date: string;
    reason: string;
    selected_count: number;
    eligible_count: number;
    adjustment_count: number;
    entries: {
        entry_id: number;
        number: string;
        party: { kind: string; id: number; name: string } | null;
        effects: Effect[];
        posting_lines: {
            account_code: string;
            debit: string;
            credit: string;
        }[];
    }[];
    blocked: { line_id: number; reason: string }[];
};
type Props = {
    lines: Parameters<typeof Pagination<Line>>[0]['page'];
    accounts: { id: number; code: string; name: string; type: string }[];
    filters: Record<string, string>;
    partyOptions: { id: number; name: string }[];
    batches: Parameters<
        typeof Pagination<{
            id: string;
            correction_date: string;
            reason: string;
        }>
    >[0]['page'];
};
export default function Reclassification(props: Props) {
    const { url } = usePage();

    return <ReclassificationScreen key={url} {...props} />;
}
function ReclassificationScreen(p: Props) {
    const [partyKind, setPartyKind] = useState(p.filters.party_kind ?? '');
    const [partyId, setPartyId] = useState(p.filters.party_id ?? '');
    const [selected, setSelected] = useState<Record<number, boolean>>({});
    const [account, setAccount] = useState(p.filters.proposed ?? '');
    const [preview, setPreview] = useState<Preview | null>(null);
    const [error, setError] = useState('');
    const [loading, setLoading] = useState(false);
    const form = useForm({
        operation_key: crypto.randomUUID(),
        entry_date: new Date().toLocaleDateString('sv-SE', {
            timeZone: 'Europe/Madrid',
        }),
        reason: '',
        confirmed: false,
        confirm_partial: false,
    });
    const selection = p.lines.data
        .filter((l) => selected[l.id])
        .map((l) => ({ line_id: l.id, account_code: account }));
    const reset = () => {
        setPreview(null);
        form.setData('confirmed', false);
    };
    async function prepare() {
        form.setData('confirmed', false);
        form.setData('confirm_partial', false);
        form.clearErrors();
        setError('');
        setLoading(true);

        try {
            const r = await fetch('/accounting/reclassification/preview', {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN':
                        document.querySelector<HTMLMetaElement>(
                            'meta[name="csrf-token"]',
                        )?.content ?? '',
                },
                body: JSON.stringify({ ...form.data, items: selection }),
            });
            const data = await r.json();

            if (!r.ok) {
                throw new Error(
                    Object.values(data.errors ?? {})
                        .flat()
                        .join(' ') || 'No se pudo preparar la rectificación.',
                );
            }

            setPreview(data);
        } catch (e) {
            setError(e instanceof Error ? e.message : 'No disponible');
        } finally {
            setLoading(false);
        }
    }

    return (
        <>
            <Head title="Reclasificación contable" />
            <PageShell>
                <PageHeader
                    title="Reclasificación contable"
                    description="Selección supervisada. Los originales se conservan y cada ajuste utiliza su fecha contable real."
                />
                <Panel title="Localizar apuntes">
                    <div className="space-y-3 p-4">
                        <Form
                            action="/accounting/reclassification"
                            method="get"
                            className="grid gap-3 sm:grid-cols-3"
                        >
                            <label>
                                Cuenta actual
                                <Input
                                    name="account"
                                    defaultValue={p.filters.account}
                                />
                            </label>
                            <label>
                                Cuenta propuesta
                                <AccountInput
                                    accounts={p.accounts}
                                    name="proposed"
                                    value={account}
                                    onChange={(v) => {
                                        setAccount(v);
                                        reset();
                                    }}
                                />
                            </label>
                            <label>
                                Ejercicio
                                <Input
                                    name="year"
                                    type="number"
                                    defaultValue={p.filters.year}
                                />
                            </label>
                            <label>
                                Desde
                                <Input
                                    name="from"
                                    type="date"
                                    defaultValue={p.filters.from}
                                />
                            </label>
                            <label>
                                Hasta
                                <Input
                                    name="to"
                                    type="date"
                                    defaultValue={p.filters.to}
                                />
                            </label>
                            <label>
                                Documento
                                <Input
                                    name="document"
                                    defaultValue={p.filters.document}
                                />
                            </label>
                            <label>
                                Concepto o asiento
                                <Input
                                    name="search"
                                    defaultValue={p.filters.search}
                                />
                            </label>
                            <label>
                                Tipo de operación
                                <select
                                    name="kind"
                                    defaultValue={p.filters.kind ?? ''}
                                    className="w-full rounded border p-2"
                                >
                                    <option value="">Todos</option>
                                    <option value="automatic">
                                        Automático
                                    </option>
                                    <option value="manual">Manual</option>
                                    <option value="correction">
                                        Rectificación
                                    </option>
                                    <option value="reversal">Anulación</option>
                                    <option value="opening">Apertura</option>
                                    <option value="closing">Cierre</option>
                                </select>
                            </label>
                            <label>
                                Estado
                                <select
                                    name="status"
                                    defaultValue={p.filters.status ?? 'posted'}
                                    className="w-full rounded border p-2"
                                >
                                    <option value="posted">
                                        Contabilizado
                                    </option>
                                    <option value="draft">Borrador</option>
                                </select>
                            </label>
                            <label>
                                Tipo de tercero
                                <select
                                    name="party_kind"
                                    value={partyKind}
                                    onChange={(e) => {
                                        setPartyKind(e.target.value);
                                        setPartyId('');
                                    }}
                                    className="w-full rounded border p-2"
                                >
                                    <option value="">Todos</option>
                                    <option value="customer">Cliente</option>
                                    <option value="supplier">
                                        Proveedor o acreedor
                                    </option>
                                </select>
                            </label>
                            <label>
                                Buscar tercero
                                <Input
                                    name="party_search"
                                    defaultValue={p.filters.party_search}
                                />
                            </label>
                            <label>
                                Tercero
                                <select
                                    name="party_id"
                                    value={partyId}
                                    onChange={(e) => setPartyId(e.target.value)}
                                    className="w-full rounded border p-2"
                                >
                                    <option value="">
                                        Todos los resultados
                                    </option>
                                    {(partyKind === (p.filters.party_kind ?? '')
                                        ? p.partyOptions
                                        : []
                                    ).map((a) => (
                                        <option key={a.id} value={a.id}>
                                            {a.name}
                                        </option>
                                    ))}
                                </select>
                            </label>
                            <Button variant="outline">Buscar apuntes</Button>
                        </Form>
                    </div>
                </Panel>
                <Panel title="Apuntes encontrados">
                    <div className="space-y-3 p-4">
                        <p className="my-2 text-sm">
                            La selección corresponde a esta página. Los bloqueos
                            se vuelven a validar al confirmar; no se ejecutan
                            líneas protegidas.
                        </p>
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr>
                                        <th>Seleccionar</th>
                                        <th>Asiento</th>
                                        <th>Cuenta actual</th>
                                        <th>Concepto</th>
                                        <th>Debe</th>
                                        <th>Haber</th>
                                        <th>Estado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {p.lines.data.map((l) => (
                                        <tr className="border-t" key={l.id}>
                                            <td className="p-2">
                                                <input
                                                    type="checkbox"
                                                    aria-label="Seleccionar apunte"
                                                    aria-describedby={`line-${l.id}`}
                                                    disabled={
                                                        !!l.blocked_reason
                                                    }
                                                    checked={!!selected[l.id]}
                                                    onChange={(e) => {
                                                        setSelected({
                                                            ...selected,
                                                            [l.id]: e.target
                                                                .checked,
                                                        });
                                                        reset();
                                                    }}
                                                />
                                            </td>
                                            <td id={`line-${l.id}`}>
                                                <Link
                                                    id={`line-${l.id}`}
                                                    href={`/accounting/entries/${l.journal_entry_id}`}
                                                >
                                                    {l.number}
                                                </Link>
                                                <small className="block">
                                                    {l.entry_date.slice(0, 10)}
                                                </small>
                                            </td>
                                            <td>{l.code}</td>
                                            <td>{l.description}</td>
                                            <td>{money(l.debit)}</td>
                                            <td>{money(l.credit)}</td>
                                            <td>
                                                {l.blocked_reason ??
                                                    'Elegible para revisión'}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                        <Pagination page={p.lines} noun="apuntes" />
                    </div>
                </Panel>
                <Panel title="Preparar reclasificación">
                    <div className="space-y-3 p-4">
                        <label className="block">
                            Nueva cuenta
                            <AccountInput
                                accounts={p.accounts}
                                value={account}
                                onChange={(v) => {
                                    setAccount(v);
                                    reset();
                                }}
                                required
                            />
                        </label>
                        <label className="my-3 block">
                            Fecha contable de la rectificación
                            <Input
                                type="date"
                                value={form.data.entry_date}
                                onChange={(e) => {
                                    form.setData('entry_date', e.target.value);
                                    reset();
                                }}
                            />
                        </label>
                        <label className="block">
                            Motivo obligatorio
                            <Textarea
                                value={form.data.reason}
                                onChange={(e) => {
                                    form.setData('reason', e.target.value);
                                    reset();
                                }}
                            />
                        </label>
                        {error && (
                            <p role="alert" className="text-destructive">
                                {error}
                            </p>
                        )}
                        {Object.values(form.errors).map((v, i) => (
                            <p role="alert" key={i}>
                                {v}
                            </p>
                        ))}
                        <Button
                            className="my-3"
                            disabled={
                                loading ||
                                !selection.length ||
                                !account ||
                                form.data.reason.length < 5
                            }
                            onClick={() => void prepare()}
                        >
                            Mostrar propuesta de reclasificación
                        </Button>
                    </div>
                </Panel>
                {preview && (
                    <Panel title="Vista previa obligatoria">
                        <div className="space-y-3 p-4">
                            <p>
                                Apuntes seleccionados: {preview.selected_count}.
                                Elegibles: {preview.eligible_count}. Asientos de
                                ajuste: {preview.adjustment_count}.
                            </p>
                            <p>
                                Fecha contable: {preview.entry_date}. Motivo:{' '}
                                {preview.reason}
                            </p>
                            <h3 className="my-3 font-semibold">
                                Resumen agregado por cuenta
                            </h3>
                            {preview.account_totals.map((a) => (
                                <p key={a.account_code}>
                                    {a.account_code} · Debe {money(a.debit)} ·
                                    Haber {money(a.credit)}
                                </p>
                            ))}
                            {preview.entries.map((g) => (
                                <section
                                    className="my-3 rounded border p-3"
                                    key={g.entry_id}
                                >
                                    <Link
                                        href={`/accounting/entries/${g.entry_id}`}
                                    >
                                        {g.number}
                                    </Link>
                                    <p>
                                        {g.party?.name ??
                                            'Sin atribución confirmada'}
                                    </p>
                                    {g.effects.map((e) => (
                                        <div className="my-2" key={e.line_id}>
                                            <p>
                                                {e.from_account} →{' '}
                                                {e.to_account} · Debe{' '}
                                                {money(e.debit)} · Haber{' '}
                                                {money(e.credit)}
                                            </p>
                                            <p>
                                                Se neutraliza la imputación
                                                anterior y se registra en la
                                                nueva cuenta mediante el ajuste
                                                enlazado.
                                            </p>
                                            <p>
                                                {e.party_balance_effect === null
                                                    ? 'Tercero sin identificar: no se atribuye un efecto confirmado.'
                                                    : 'Efecto sobre el saldo consolidado del tercero: 0,00 €. Cambia el desglose entre cuentas.'}
                                            </p>
                                        </div>
                                    ))}
                                    <details>
                                        <summary>
                                            Asiento de ajuste propuesto
                                        </summary>
                                        {g.posting_lines.map((l, i) => (
                                            <p key={i}>
                                                {l.account_code} · Debe{' '}
                                                {money(l.debit)} · Haber{' '}
                                                {money(l.credit)}
                                            </p>
                                        ))}
                                    </details>
                                </section>
                            ))}
                            {preview.blocked.map((b) => (
                                <p
                                    role="status"
                                    className="my-2 text-destructive"
                                    key={b.line_id}
                                >
                                    Apunte {b.line_id}: {b.reason}
                                </p>
                            ))}
                            {preview.blocked.length > 0 && (
                                <label className="my-3 block">
                                    <input
                                        type="checkbox"
                                        checked={form.data.confirm_partial}
                                        onChange={(e) =>
                                            form.setData(
                                                'confirm_partial',
                                                e.target.checked,
                                            )
                                        }
                                    />{' '}
                                    Confirmo ejecutar solo los elementos
                                    elegibles y conservar los bloqueados sin
                                    cambios.
                                </label>
                            )}
                            <label className="my-3 block">
                                <input
                                    type="checkbox"
                                    checked={form.data.confirmed}
                                    onChange={(e) =>
                                        form.setData(
                                            'confirmed',
                                            e.target.checked,
                                        )
                                    }
                                />{' '}
                                He revisado la propuesta y autorizo estos
                                ajustes.
                            </label>
                            <Button
                                disabled={
                                    form.processing ||
                                    !form.data.confirmed ||
                                    !preview.eligible_count ||
                                    (preview.blocked.length > 0 &&
                                        !form.data.confirm_partial)
                                }
                                onClick={() => {
                                    form.transform((data) => ({
                                        ...data,
                                        items: selection,
                                        preview_revision:
                                            preview.preview_revision,
                                    }));
                                    form.post('/accounting/reclassification');
                                }}
                            >
                                Confirmar reclasificación
                            </Button>
                        </div>
                    </Panel>
                )}
                <Panel title="Historial de lotes">
                    <div className="space-y-3 p-4">
                        {p.batches.data.map((b) => (
                            <p className="my-2" key={b.id}>
                                <Link
                                    href={`/accounting/reclassification/${b.id}`}
                                >
                                    {b.correction_date} · {b.reason}
                                </Link>
                            </p>
                        ))}
                        <Pagination page={p.batches} noun="lotes" />
                    </div>
                </Panel>
            </PageShell>
        </>
    );
}

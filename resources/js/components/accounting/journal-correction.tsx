import { Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { AccountInput } from '@/components/accounting/account-input';
import { Panel, money } from '@/components/billing/workspace';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
type Correction = {
    id: number;
    reason: string;
    description: string | null;
    notes: string | null;
    reference: string | null;
    recorded_at_utc: string;
    correction_entry_id: number | null;
    created_by: number;
    creator?: { name: string } | null;
    line_changes: {
        id: number;
        account_code: string;
        description: string | null;
    }[];
};
type Entry = {
    id: number;
    description: string;
    entry_date: string;
    correction_revision: string;
    can_correct: boolean;
    corrections: Correction[];
    lines: {
        id: number;
        code: string;
        description: string | null;
        debit: string;
        credit: string;
    }[];
};
type PostingLine = {
    account_code: string;
    description: string | null;
    debit: string;
    credit: string;
};
export function JournalCorrectionPanel({
    entry,
    accounts,
}: {
    entry: Entry;
    accounts: { code: string; name: string; id?: number; type?: string }[];
}) {
    const effective = new Map(
        entry.lines.map((l) => [
            l.id,
            { account_code: l.code, description: l.description },
        ]),
    );
    entry.corrections?.forEach((c) =>
        c.line_changes.forEach((l) =>
            effective.set(l.id, {
                account_code: l.account_code,
                description: l.description,
            }),
        ),
    );
    const form = useForm({
        entry_date: entry.entry_date,
        description:
            entry.corrections?.at(-1)?.description ?? entry.description,
        notes: '',
        reference: '',
        reason: '',
        operation_key: crypto.randomUUID(),
        revision: entry.correction_revision,
        lines: entry.lines.map((l) => ({
            id: l.id,
            account_code: effective.get(l.id)!.account_code,
            description: effective.get(l.id)!.description ?? '',
        })),
        confirmed: false,
    });
    const [preview, setPreview] = useState<{
        posting_lines: PostingLine[];
        changes_accounts: boolean;
    } | null>(null);
    const [error, setError] = useState('');
    const [loading, setLoading] = useState(false);
    const clear = () => {
        setPreview(null);
        form.setData('confirmed', false);
    };
    const showPreview = async () => {
        setLoading(true);
        setError('');

        try {
            const token =
                document.querySelector<HTMLMetaElement>(
                    'meta[name="csrf-token"]',
                )?.content ?? '';
            const r = await fetch(
                `/accounting/entries/${entry.id}/correction-preview`,
                {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': token,
                    },
                    body: JSON.stringify(form.data),
                },
            );
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
    };
    const candidates = accounts.map((a, i) => ({
        ...a,
        id: a.id ?? i,
        type: a.type ?? '',
    }));

    return (
        <>
            <Panel title="Anotaciones y rectificaciones">
                <p className="mb-3 text-sm">
                    El concepto y los apuntes originales se conservan. Los
                    cambios descriptivos se muestran como anotaciones; los
                    cambios de cuenta generan un asiento enlazado.
                </p>
                {entry.corrections?.length ? (
                    entry.corrections.map((c) => (
                        <article
                            key={c.id}
                            className="mb-3 space-y-1 rounded border p-3"
                        >
                            <strong>{c.reason}</strong>
                            <p>
                                {new Intl.DateTimeFormat('es-ES', {
                                    day: '2-digit',
                                    month: '2-digit',
                                    year: 'numeric',
                                    hour: '2-digit',
                                    minute: '2-digit',
                                    timeZone: 'Europe/Madrid',
                                    timeZoneName: 'shortOffset',
                                }).format(new Date(c.recorded_at_utc))}{' '}
                                · {c.creator?.name ?? 'Usuario no disponible'}
                            </p>
                            {c.description && (
                                <p>Concepto anotado: {c.description}</p>
                            )}
                            {c.reference && (
                                <p>Referencia justificativa: {c.reference}</p>
                            )}
                            {c.notes && <p>{c.notes}</p>}
                            <ul>
                                {c.line_changes.map((l) => (
                                    <li key={l.id}>
                                        Apunte #{l.id} · {l.account_code} ·{' '}
                                        {l.description || '—'}
                                    </li>
                                ))}
                            </ul>
                            {c.correction_entry_id ? (
                                <Link
                                    href={`/accounting/entries/${c.correction_entry_id}`}
                                >
                                    Consultar asiento de rectificación
                                </Link>
                            ) : (
                                <p>
                                    Sin cambio de importes ni asiento económico.
                                </p>
                            )}
                        </article>
                    ))
                ) : (
                    <p>Sin anotaciones ni rectificaciones registradas.</p>
                )}
            </Panel>
            {entry.can_correct && (
                <Panel title="Rectificar cuentas o conceptos">
                    <form
                        className="space-y-4"
                        onSubmit={(e) => {
                            e.preventDefault();

                            if (preview) {
form.post(
                                    `/accounting/entries/${entry.id}/corrections`,
                                    {
                                        preserveScroll: true,
                                        onSuccess: () => {
                                            setPreview(null);
                                            form.reset(
                                                'reason',
                                                'notes',
                                                'reference',
                                                'confirmed',
                                            );
                                            form.setData(
                                                'operation_key',
                                                crypto.randomUUID(),
                                            );
                                        },
                                    },
                                );
}
                        }}
                    >
                        <p className="text-sm">
                            No permite modificar importes, Debe/Haber, terceros
                            ni documentos fiscales. Requiere ejercicio y periodo
                            abiertos.
                        </p>
                        <label className="block">
                            Fecha del asiento de corrección
                            <Input
                                type="date"
                                value={form.data.entry_date}
                                onChange={(e) => {
                                    clear();
                                    form.setData('entry_date', e.target.value);
                                }}
                                required
                            />
                        </label>
                        <label className="block">
                            Concepto general anotado
                            <Input
                                value={form.data.description}
                                maxLength={255}
                                onChange={(e) => {
                                    clear();
                                    form.setData('description', e.target.value);
                                }}
                            />
                        </label>
                        {form.data.lines.map((l, i) => (
                            <fieldset
                                key={l.id}
                                className="grid gap-3 rounded border p-3 sm:grid-cols-2"
                            >
                                <legend>
                                    Apunte #{l.id} · Debe{' '}
                                    {money(entry.lines[i].debit)} · Haber{' '}
                                    {money(entry.lines[i].credit)}
                                </legend>
                                <label>
                                    Cuenta propuesta
                                    <AccountInput
                                        value={l.account_code}
                                        accounts={candidates}
                                        required
                                        onChange={(v) => {
                                            clear();
                                            form.setData(
                                                'lines',
                                                form.data.lines.map((x, j) =>
                                                    i === j
                                                        ? {
                                                              ...x,
                                                              account_code: v,
                                                          }
                                                        : x,
                                                ),
                                            );
                                        }}
                                    />
                                </label>
                                <label>
                                    Concepto del apunte
                                    <Input
                                        value={l.description}
                                        maxLength={255}
                                        onChange={(e) => {
                                            clear();
                                            form.setData(
                                                'lines',
                                                form.data.lines.map((x, j) =>
                                                    i === j
                                                        ? {
                                                              ...x,
                                                              description:
                                                                  e.target
                                                                      .value,
                                                          }
                                                        : x,
                                                ),
                                            );
                                        }}
                                    />
                                </label>
                            </fieldset>
                        ))}
                        <label className="block">
                            Motivo obligatorio
                            <Textarea
                                value={form.data.reason}
                                minLength={5}
                                required
                                onChange={(e) => {
                                    clear();
                                    form.setData('reason', e.target.value);
                                }}
                            />
                        </label>
                        <label className="block">
                            Observaciones
                            <Textarea
                                value={form.data.notes}
                                onChange={(e) => {
                                    clear();
                                    form.setData('notes', e.target.value);
                                }}
                                maxLength={2000}
                            />
                        </label>
                        <label className="block">
                            Referencia justificativa
                            <Input
                                value={form.data.reference}
                                maxLength={255}
                                onChange={(e) => {
                                    clear();
                                    form.setData('reference', e.target.value);
                                }}
                            />
                        </label>
                        <Button
                            type="button"
                            variant="outline"
                            disabled={loading}
                            onClick={() => void showPreview()}
                        >
                            Mostrar efecto contable
                        </Button>
                        {error && <p role="alert">{error}</p>}
                        {preview && (
                            <div className="space-y-3 rounded border p-3">
                                <strong>
                                    {preview.changes_accounts
                                        ? 'Se registrará este asiento de rectificación:'
                                        : 'Se registrará únicamente una anotación auditada.'}
                                </strong>
                                {preview.posting_lines.map((l, i) => (
                                    <p key={i}>
                                        {l.account_code} · Debe {money(l.debit)}{' '}
                                        · Haber {money(l.credit)} ·{' '}
                                        {l.description || '—'}
                                    </p>
                                ))}
                                <label className="block">
                                    <input
                                        type="checkbox"
                                        checked={form.data.confirmed}
                                        onChange={(e) =>
                                            form.setData(
                                                'confirmed',
                                                e.target.checked,
                                            )
                                        }
                                        required
                                    />{' '}
                                    Confirmo el motivo y el efecto mostrado,
                                    conservando el original.
                                </label>
                                <Button disabled={form.processing}>
                                    Registrar rectificación
                                </Button>
                            </div>
                        )}
                        {Object.entries(form.errors).map(([k, v]) => (
                            <p
                                key={k}
                                role="alert"
                                className="text-destructive"
                            >
                                {v}
                            </p>
                        ))}
                    </form>
                </Panel>
            )}
        </>
    );
}

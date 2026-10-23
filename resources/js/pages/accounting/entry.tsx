import { Form, Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Plus, Save, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { Panel, date, money } from '@/components/billing/workspace';
import { PageHeader, PageShell } from '@/components/page-shell';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { State } from './workspace';
import type { Entry, Line } from './workspace';
import '@/../css/accounting.css';
type Detail = Entry & {
    period: null | { year: number; month: number; status: string };
    created_at: string;
    posted_at: string | null;
    creator: string | null;
    source: null | {
        type: string;
        id: number;
        number: string;
        href: string | null;
    };
    original: null | { id: number; number: string };
    reversal: null | { id: number };
    fiscalYear: null | { year: number; status: string };
    history: {
        id: number;
        event: string;
        user_id: number | null;
        created_at: string;
    }[];
};
export default function JournalEntry({
    entry,
    canManage,
    postingAccounts,
}: {
    entry: Detail | null;
    canManage: boolean;
    postingAccounts: { code: string; name: string }[];
}) {
    const [editing, setEditing] = useState(!entry);
    const form = useForm({
        entry_date: entry?.entry_date ?? new Date().toLocaleDateString('en-CA'),
        description: entry?.description ?? '',
        lines: entry?.lines.map((l) => ({
            account_code: l.code,
            description: l.description ?? '',
            debit: l.debit,
            credit: l.credit,
        })) ?? [
            {
                account_code: '',
                description: '',
                debit: '0.00',
                credit: '0.00',
            },
            {
                account_code: '',
                description: '',
                debit: '0.00',
                credit: '0.00',
            },
        ],
    });
    const update = (i: number, key: string, value: string) =>
        form.setData(
            'lines',
            form.data.lines.map((l, n) =>
                i === n ? { ...l, [key]: value } : l,
            ),
        );
    const save = () =>
        entry
            ? form.patch(`/accounting/drafts/${entry.id}`, {
                  onSuccess: () => setEditing(false),
              })
            : form.post('/accounting/drafts');

    return (
        <>
            <Head title={entry?.number ?? 'Nuevo asiento'} />
            <PageShell mode="full" className="accounting-workspace">
                <PageHeader
                    title={entry?.number ?? 'Nuevo asiento'}
                    description={
                        entry?.description ??
                        'Prepara los apuntes y guarda el borrador antes de contabilizar.'
                    }
                    secondaryNavigation={null}
                    actions={
                        <>
                            <Button asChild variant="outline">
                                <Link href="/accounting?area=entries">
                                    <ArrowLeft />
                                    Volver a asientos
                                </Link>
                            </Button>
                            {entry?.status === 'draft' && canManage && (
                                <Button
                                    variant="outline"
                                    onClick={() => setEditing(!editing)}
                                >
                                    Editar borrador
                                </Button>
                            )}
                        </>
                    }
                />
                {entry && (
                    <>
                        <div>
                            <State status={entry.status} />
                        </div>
                        <Panel title="Información del asiento">
                            <dl className="accounting-details">
                                <div>
                                    <dt>Fecha contable</dt>
                                    <dd>{date(entry.entry_date)}</dd>
                                </div>
                                <div>
                                    <dt>Ejercicio</dt>
                                    <dd>
                                        {entry.fiscalYear?.year ??
                                            'Sin ejercicio'}
                                    </dd>
                                </div>
                                <div>
                                    <dt>Periodo contable</dt>
                                    <dd>
                                        {entry.period
                                            ? `${entry.period.month}/${entry.period.year} · ${entry.period.status === 'closed' ? 'Cerrado' : 'Abierto'}`
                                            : 'Sin periodo registrado'}
                                    </dd>
                                </div>
                                <div>
                                    <dt>Creado por</dt>
                                    <dd>
                                        {entry.creator ??
                                            'Usuario no disponible'}
                                    </dd>
                                </div>
                                <div>
                                    <dt>Fecha de creación</dt>
                                    <dd>{date(entry.created_at)}</dd>
                                </div>
                                <div>
                                    <dt>Contabilizado</dt>
                                    <dd>{date(entry.posted_at)}</dd>
                                </div>
                                <div>
                                    <dt>Documento origen</dt>
                                    <dd>
                                        {entry.source?.href ? (
                                            <Link href={entry.source.href}>
                                                {entry.source.number}
                                            </Link>
                                        ) : (
                                            (entry.source?.number ??
                                            'Asiento manual')
                                        )}
                                    </dd>
                                </div>
                            </dl>
                        </Panel>
                    </>
                )}
                {editing && canManage ? (
                    <Panel title="Borrador de asiento">
                        <form
                            className="accounting-entry-form"
                            onSubmit={(e) => {
                                e.preventDefault();
                                save();
                            }}
                        >
                            <label>
                                Fecha
                                <Input
                                    type="date"
                                    value={form.data.entry_date}
                                    onChange={(e) =>
                                        form.setData(
                                            'entry_date',
                                            e.target.value,
                                        )
                                    }
                                />
                            </label>
                            <label>
                                Concepto
                                <Input
                                    maxLength={255}
                                    value={form.data.description}
                                    onChange={(e) =>
                                        form.setData(
                                            'description',
                                            e.target.value,
                                        )
                                    }
                                />
                            </label>
                            {form.data.lines.map((l, i) => (
                                <div className="accounting-draft-line" key={i}>
                                    <label>
                                        Cuenta
                                        <Input
                                            list="posting-accounts"
                                            required
                                            value={l.account_code}
                                            onChange={(e) =>
                                                update(
                                                    i,
                                                    'account_code',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    </label>
                                    <label>
                                        Descripción
                                        <Input
                                            maxLength={255}
                                            value={l.description}
                                            onChange={(e) =>
                                                update(
                                                    i,
                                                    'description',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    </label>
                                    <label>
                                        Debe
                                        <Input
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            value={l.debit}
                                            onChange={(e) =>
                                                update(
                                                    i,
                                                    'debit',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    </label>
                                    <label>
                                        Haber
                                        <Input
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            value={l.credit}
                                            onChange={(e) =>
                                                update(
                                                    i,
                                                    'credit',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    </label>
                                    {form.data.lines.length > 2 && (
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            aria-label="Quitar apunte"
                                            onClick={() =>
                                                form.setData(
                                                    'lines',
                                                    form.data.lines.filter(
                                                        (_, n) => n !== i,
                                                    ),
                                                )
                                            }
                                        >
                                            <Trash2 />
                                        </Button>
                                    )}
                                </div>
                            ))}
                            <datalist id="posting-accounts">
                                {postingAccounts.map((a) => (
                                    <option key={a.code} value={a.code}>
                                        {a.name}
                                    </option>
                                ))}
                            </datalist>
                            <Button
                                type="button"
                                variant="outline"
                                disabled={form.data.lines.length >= 50}
                                onClick={() =>
                                    form.setData('lines', [
                                        ...form.data.lines,
                                        {
                                            account_code: '',
                                            description: '',
                                            debit: '0.00',
                                            credit: '0.00',
                                        },
                                    ])
                                }
                            >
                                <Plus />
                                Añadir apunte
                            </Button>
                            {Object.entries(form.errors).map(([k, error]) => (
                                <p
                                    key={k}
                                    role="alert"
                                    className="text-destructive"
                                >
                                    {error}
                                </p>
                            ))}
                            <p className="accounting-muted">
                                El borrador no afecta a saldos. Para
                                contabilizar, Debe y Haber deben coincidir y
                                cada apunte debe tener un único lado positivo.
                            </p>
                            <Button disabled={form.processing}>
                                <Save />
                                Guardar borrador
                            </Button>
                        </form>
                    </Panel>
                ) : (
                    entry && (
                        <Panel title="Apuntes del asiento">
                            <LinesTable lines={entry.lines} />
                            <p>
                                Debe: {money(entry.debit)} · Haber:{' '}
                                {money(entry.credit)}
                            </p>
                            {entry.status === 'draft' && canManage && (
                                <Form
                                    action={`/accounting/drafts/${entry.id}/post`}
                                    method="post"
                                >
                                    {({ errors, processing }) => (
                                        <>
                                            <Button disabled={processing}>
                                                Validar y contabilizar
                                            </Button>
                                            {Object.values(errors).map(
                                                (e, i) => (
                                                    <p key={i} role="alert">
                                                        {e}
                                                    </p>
                                                ),
                                            )}
                                        </>
                                    )}
                                </Form>
                            )}
                        </Panel>
                    )
                )}
                {entry && (
                    <>
                        <Panel title="Historial">
                            <div className="accounting-history">
                                {entry.history.map((h) => (
                                    <p key={h.id}>
                                        {date(h.created_at)} ·{' '}
                                        {(
                                            {
                                                created: 'Creado',
                                                updated: 'Actualizado',
                                                deleted: 'Eliminado',
                                            } as Record<string, string>
                                        )[h.event] ?? h.event}{' '}
                                        · Usuario {h.user_id ?? '—'}
                                    </p>
                                ))}
                                {!entry.history.length && (
                                    <p>
                                        No hay eventos registrados para este
                                        asiento.
                                    </p>
                                )}
                            </div>
                        </Panel>
                        {entry.original && (
                            <Link
                                href={`/accounting/entries/${entry.original.id}`}
                            >
                                Asiento original: {entry.original.number}
                            </Link>
                        )}
                        {entry.reversal && (
                            <Link
                                href={`/accounting/entries/${entry.reversal.id}`}
                            >
                                Consultar asiento de reversión
                            </Link>
                        )}
                        {entry.status === 'posted' &&
                            !entry.reversal &&
                            canManage && (
                                <Panel title="Corrección mediante reversión">
                                    <p className="accounting-muted">
                                        Se conserva el asiento original. La
                                        reversión se registra en un periodo
                                        abierto y no puede repetirse.
                                    </p>
                                    <Form
                                        action={`/accounting/entries/${entry.id}/reverse`}
                                        method="post"
                                        className="accounting-entry-form"
                                    >
                                        {({ errors, processing }) => (
                                            <>
                                                <label>
                                                    Fecha de reversión
                                                    <Input
                                                        name="entry_date"
                                                        type="date"
                                                        required
                                                    />
                                                </label>
                                                <label>
                                                    Motivo
                                                    <Input
                                                        name="description"
                                                        required
                                                        maxLength={255}
                                                    />
                                                </label>
                                                <Button
                                                    variant="outline"
                                                    disabled={processing}
                                                >
                                                    Registrar reversión
                                                </Button>
                                                {Object.values(errors).map(
                                                    (e, i) => (
                                                        <p role="alert" key={i}>
                                                            {e}
                                                        </p>
                                                    ),
                                                )}
                                            </>
                                        )}
                                    </Form>
                                </Panel>
                            )}
                    </>
                )}
            </PageShell>
        </>
    );
}
function LinesTable({ lines }: { lines: Line[] }) {
    return (
        <div className="accounting-table accounting-always-table">
            <table>
                <thead>
                    <tr>
                        <th>Cuenta</th>
                        <th>Descripción</th>
                        <th>Debe</th>
                        <th>Haber</th>
                    </tr>
                </thead>
                <tbody>
                    {lines.map((l) => (
                        <tr key={l.id}>
                            <td>
                                {l.code} · {l.name}
                            </td>
                            <td>{l.description ?? '—'}</td>
                            <td>{money(l.debit)}</td>
                            <td>{money(l.credit)}</td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

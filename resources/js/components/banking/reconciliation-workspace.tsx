import { Form, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { Panel, money, date } from '@/components/billing/workspace';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';

type Candidate = {
    value: string;
    number: string | null;
    reference: string | null;
    date: string;
    amount: string;
    remaining: string;
    difference: string;
    account_match: boolean | null;
    signals: string[];
    score: number;
    blocked: string | null;
    href: string;
};
type Match = {
    id: number;
    amount: string;
    reversed_at: string | null;
    reversal_reason: string | null;
    reason: string | null;
    reconciled_at: string | null;
    legacy_reconciled_date: string;
    reconciled_by: string | null;
    reversed_by: string | null;
    payment: string;
    journal_entry_id: number | null;
    reversal_entry_id: number | null;
    generated_operation: boolean;
};
export type ReconciliationDetails = {
    revision: string;
    remaining: string;
    suggestions: Candidate[];
    flags: string[];
    history: Match[];
    reviews: {
        id: number;
        event: string;
        actor: string | null;
        date: string;
        values: { reason?: string; category?: string; review_status?: string };
    }[];
    automatic_confirmation: false;
};
type BankRow = {
    id: number;
    amount: string;
    booked_on: string;
    reference: string | null;
    description: string;
    status: string;
    reconciliation_details?: ReconciliationDetails;
};
type PageLink = { url: string | null; label: string; active: boolean };
export type ReconciliationExtras = {
    unmatchedOperations?: {
        data: {
            id: number;
            source: string;
            date: string;
            description: string;
            reference: string | null;
            amount: string;
            account: string;
        }[];
        links: PageLink[];
    };
};
const terms: Record<string, string> = {
    exact_amount: 'Importe pendiente exacto',
    exact_reference: 'Referencia bancaria exacta',
    document_reference: 'Referencia documental',
    concept_reference: 'Referencia en el concepto',
    near_date: 'Fechas próximas',
    same_account: 'Misma cuenta registrada',
    no_erp_operation: 'Sin operación ERP identificada',
    no_bank_movement: 'Operación ERP sin banco',
    amount_difference: 'Diferencia de importe',
    reference_difference: 'Referencia o concepto diferente',
    possible_duplicate: 'Posible duplicado',
    voided_operation: 'Operación anulada',
    already_reconciled: 'Previamente conciliado',
    closed_period: 'Periodo protegido',
    bank_fee: 'Posible comisión bancaria',
    ambiguous_reference: 'Coincidencia ambigua',
    incompatible_account_or_method: 'Cuenta, moneda o medio incompatible',
};
function formatInstant(value: string) {
    return new Intl.DateTimeFormat('es-ES', {
        timeZone: 'Europe/Madrid',
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        timeZoneName: 'shortOffset',
    }).format(new Date(value));
}
function Pagination({ links }: { links: PageLink[] }) {
    return (
        <nav className="flex flex-wrap gap-2" aria-label="Paginación">
            {links.map((link, index) =>
                link.url ? (
                    <Link
                        className="rounded border px-3 py-1"
                        key={index}
                        href={link.url}
                        aria-current={link.active ? 'page' : undefined}
                    >
                        {link.label
                            .replace(/&laquo;|&raquo;/g, '')
                            .replace(/<[^>]*>/g, '')}
                    </Link>
                ) : null,
            )}
        </nav>
    );
}
function Correction({ match }: { match: Match }) {
    const [key] = useState(() => crypto.randomUUID());

    return (
        <Form action={`/banking/reconciliations/${match.id}`} method="delete">
            {({ processing, errors }) => (
                <div className="space-y-2">
                    <input type="hidden" name="operation_key" value={key} />
                    <label className="block text-sm">
                        Motivo de desconciliación
                        <Textarea
                            name="reason"
                            required
                            minLength={5}
                            maxLength={2000}
                        />
                    </label>
                    <label className="flex gap-2 text-sm">
                        <input
                            type="checkbox"
                            name="confirmed"
                            value="1"
                            required
                        />
                        Confirmo la corrección conservando la relación original.
                    </label>
                    {Object.entries(errors).map(([field, error]) => (
                        <p
                            role="alert"
                            className="text-destructive"
                            key={field}
                        >
                            {error}
                        </p>
                    ))}
                    <Button variant="destructive" disabled={processing}>
                        Desconciliar de forma trazable
                    </Button>
                </div>
            )}
        </Form>
    );
}
function MovementReview({
    row,
    accountId,
    canMatch,
    canCorrect,
}: {
    row: BankRow;
    accountId: number;
    canMatch: boolean;
    canCorrect: boolean;
}) {
    const [open, setOpen] = useState(false);
    const [search, setSearch] = useState('');
    const [kind, setKind] = useState('');
    const [candidates, setCandidates] = useState<Candidate[]>([]);
    const [page, setPage] = useState(1);
    const [lastPage, setLastPage] = useState(1);
    const [lookupError, setLookupError] = useState('');
    const [loading, setLoading] = useState(false);
    const details = row.reconciliation_details;
    const form = useForm({
        payment: '',
        allocated_amount: '',
        account_id: accountId,
        revision: details?.revision || '',
        operation_key: '',
        reason: '',
        confirmed: false,
    });
    const review = useForm({
        category: 'no_erp_operation',
        review_status: 'investigating',
        reason: '',
        operation_key: '',
        confirmed: false,
    });
    const chosen =
        candidates.find((c) => c.value === form.data.payment) ||
        details?.suggestions.find((c) => c.value === form.data.payment);
    async function lookup(target = 1) {
        setLoading(true);
        setLookupError('');

        try {
            const response = await fetch(
                `/banking/transactions/${row.id}/candidates?search=${encodeURIComponent(search)}&page=${target}&include_voided=1${kind ? `&kind=${kind}` : ''}`,
                { headers: { Accept: 'application/json' } },
            );

            if (!response.ok) {
                throw new Error('lookup');
            }

            const result = (await response.json()) as {
                data: Candidate[];
                current_page: number;
                last_page: number;
            };
            form.setData((data) => ({
                ...data,
                payment: '',
                allocated_amount: '',
                confirmed: false,
            }));
            setCandidates(result.data);
            setPage(result.current_page);
            setLastPage(result.last_page);
        } catch {
            setLookupError('No se pudieron consultar las operaciones.');
        } finally {
            setLoading(false);
        }
    }

    return (
        <Dialog
            open={open}
            onOpenChange={(value) => {
                setOpen(value);

                if (value) {
                    form.clearErrors();
                    form.setData({
                        payment: '',
                        allocated_amount: '',
                        account_id: accountId,
                        revision: details?.revision || '',
                        operation_key: crypto.randomUUID(),
                        reason: '',
                        confirmed: false,
                    });
                    review.setData('operation_key', crypto.randomUUID());
                }
            }}
        >
            <DialogTrigger asChild>
                <Button size="sm" variant="outline">
                    Revisar movimiento
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[90dvh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Conciliación y discrepancias</DialogTitle>
                </DialogHeader>
                <p className="break-words">
                    {row.description} · {row.reference || '—'}
                </p>
                <p>
                    {date(row.booked_on)} · {money(row.amount)} · Pendiente:{' '}
                    {money(details?.remaining || null)}
                </p>
                {!details ? (
                    <p>Control de conciliación no disponible.</p>
                ) : (
                    <>
                        <p className="text-sm">
                            Las sugerencias son informativas. No crean cobros,
                            pagos ni ajustes automáticos.
                        </p>
                        {details.suggestions.map((candidate) => (
                            <div
                                className="rounded border p-2 text-sm"
                                key={candidate.value}
                            >
                                <strong>
                                    {candidate.number || candidate.value}
                                </strong>{' '}
                                · {money(candidate.remaining)}
                                <p>
                                    {candidate.signals
                                        .map(
                                            (signal) => terms[signal] || signal,
                                        )
                                        .join(' · ')}
                                </p>
                                <p>
                                    Diferencia: {money(candidate.difference)} ·{' '}
                                    {candidate.account_match === null
                                        ? 'Cuenta ERP sin registrar'
                                        : 'Cuenta comprobada'}
                                </p>
                                {canMatch && (
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() => {
                                            setCandidates((old) =>
                                                old.some(
                                                    (c) =>
                                                        c.value ===
                                                        candidate.value,
                                                )
                                                    ? old
                                                    : [...old, candidate],
                                            );
                                            form.setData(
                                                'payment',
                                                candidate.value,
                                            );
                                        }}
                                    >
                                        Seleccionar sugerencia
                                    </Button>
                                )}
                            </div>
                        ))}
                        {canMatch &&
                            ['pending', 'partial'].includes(row.status) && (
                                <>
                                    <div className="flex flex-wrap gap-2">
                                        <Input
                                            aria-label="Buscar operación ERP"
                                            value={search}
                                            onChange={(event) =>
                                                setSearch(event.target.value)
                                            }
                                        />
                                        <Button
                                            variant="outline"
                                            disabled={loading}
                                            onClick={() => void lookup(1)}
                                        >
                                            Buscar operaciones
                                        </Button>
                                    </div>
                                    <label>
                                        Tipo de operación
                                        <select
                                            className="w-full rounded border p-2"
                                            value={kind}
                                            disabled={loading}
                                            onChange={(event) => {
                                                setKind(event.target.value);
                                                form.setData((data) => ({
                                                    ...data,
                                                    payment: '',
                                                    confirmed: false,
                                                }));
                                            }}
                                        >
                                            <option value="">
                                                Según dirección del movimiento
                                            </option>
                                            <option value="customer">
                                                Cobros de clientes
                                            </option>
                                            <option value="supplier">
                                                Pagos a proveedores
                                            </option>
                                            <option value="tax">
                                                Pagos o devoluciones fiscales
                                            </option>
                                            <option value="transfer">
                                                Transferencia entre cuentas
                                                propias
                                            </option>
                                        </select>
                                    </label>
                                    {lookupError && (
                                        <p role="alert">{lookupError}</p>
                                    )}
                                    <form
                                        className="space-y-3"
                                        onSubmit={(event) => {
                                            event.preventDefault();
                                            form.post(
                                                `/banking/transactions/${row.id}/reconcile`,
                                                {
                                                    onSuccess: () =>
                                                        setOpen(false),
                                                },
                                            );
                                        }}
                                    >
                                        <label className="block">
                                            Operación ERP
                                            <select
                                                className="w-full rounded border p-2"
                                                value={form.data.payment}
                                                onChange={(event) =>
                                                    form.setData(
                                                        'payment',
                                                        event.target.value,
                                                    )
                                                }
                                                required
                                            >
                                                <option value="">
                                                    Selecciona una operación
                                                    registrada
                                                </option>
                                                {candidates.map((candidate) => (
                                                    <option
                                                        key={candidate.value}
                                                        value={candidate.value}
                                                        disabled={Boolean(
                                                            candidate.blocked,
                                                        )}
                                                    >
                                                        {candidate.number ||
                                                            candidate.value}{' '}
                                                        ·{' '}
                                                        {money(
                                                            candidate.remaining,
                                                        )}{' '}
                                                        {candidate.blocked
                                                            ? `· ${terms[candidate.blocked] || candidate.blocked}`
                                                            : ''}
                                                    </option>
                                                ))}
                                            </select>
                                        </label>
                                        <div className="flex items-center gap-2">
                                            <Button
                                                type="button"
                                                variant="outline"
                                                disabled={loading || page <= 1}
                                                onClick={() =>
                                                    void lookup(page - 1)
                                                }
                                            >
                                                Anterior
                                            </Button>
                                            <span>
                                                {page} / {lastPage}
                                            </span>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                disabled={
                                                    loading || page >= lastPage
                                                }
                                                onClick={() =>
                                                    void lookup(page + 1)
                                                }
                                            >
                                                Siguiente
                                            </Button>
                                        </div>
                                        {chosen && (
                                            <p className="text-sm">
                                                Importe ERP:{' '}
                                                {money(chosen.amount)} ·
                                                Pendiente ERP:{' '}
                                                {money(chosen.remaining)} ·
                                                Diferencia:{' '}
                                                {money(chosen.difference)} ·{' '}
                                                <Link
                                                    className="underline"
                                                    href={chosen.href}
                                                >
                                                    Abrir operación
                                                </Link>
                                            </p>
                                        )}
                                        <label className="block">
                                            Importe a conciliar
                                            <Input
                                                type="number"
                                                step="0.01"
                                                min="0.01"
                                                value={
                                                    form.data.allocated_amount
                                                }
                                                onChange={(event) =>
                                                    form.setData(
                                                        'allocated_amount',
                                                        event.target.value,
                                                    )
                                                }
                                                required
                                            />
                                        </label>
                                        <label className="block">
                                            Motivo
                                            <Textarea
                                                minLength={5}
                                                maxLength={2000}
                                                required
                                                value={form.data.reason}
                                                onChange={(event) =>
                                                    form.setData(
                                                        'reason',
                                                        event.target.value,
                                                    )
                                                }
                                            />
                                        </label>
                                        <p className="text-sm">
                                            Si corresponde, se reclasificará el
                                            importe confirmado entre la cuenta
                                            genérica y la cuenta bancaria. No se
                                            ajustarán las diferencias.
                                        </p>
                                        <label className="flex gap-2 text-sm">
                                            <input
                                                type="checkbox"
                                                required
                                                checked={form.data.confirmed}
                                                onChange={(event) =>
                                                    form.setData(
                                                        'confirmed',
                                                        event.target.checked,
                                                    )
                                                }
                                            />
                                            Confirmo cuenta, operación e
                                            importe. Las diferencias quedan
                                            pendientes.
                                        </label>
                                        {Object.entries(form.errors).map(
                                            ([field, error]) => (
                                                <p
                                                    role="alert"
                                                    className="text-destructive"
                                                    key={field}
                                                >
                                                    {error}
                                                </p>
                                            ),
                                        )}
                                        <Button
                                            disabled={
                                                form.processing ||
                                                !form.data.operation_key
                                            }
                                        >
                                            Confirmar conciliación manual
                                        </Button>
                                    </form>
                                    <p className="text-sm">
                                        Si falta la operación, regístrala
                                        mediante el procedimiento autorizado.
                                    </p>
                                    <Link
                                        href="/billing/payments"
                                        className="underline"
                                    >
                                        Abrir cobros y pagos
                                    </Link>
                                </>
                            )}
                        {details.history.map((match) => (
                            <section
                                className="space-y-2 rounded border p-3"
                                key={match.id}
                            >
                                <strong>
                                    {match.reversed_at
                                        ? 'Conciliación corregida'
                                        : 'Conciliación confirmada'}
                                </strong>
                                <p>
                                    {match.payment} · {money(match.amount)} ·{' '}
                                    {match.reconciled_at
                                        ? formatInstant(match.reconciled_at)
                                        : date(
                                              match.legacy_reconciled_date,
                                          )}{' '}
                                    · {match.reconciled_by}
                                </p>
                                <p>
                                    {!match.reconciled_at && (
                                        <small>
                                            Fecha histórica sin instante UTC
                                            registrado.
                                        </small>
                                    )}
                                    {match.reason ||
                                        'Motivo histórico sin registrar'}
                                </p>
                                {match.reversed_at && (
                                    <p>
                                        {match.reversed_at
                                            ? formatInstant(match.reversed_at)
                                            : '—'}{' '}
                                        · {match.reversed_by} ·{' '}
                                        {match.reversal_reason}
                                    </p>
                                )}
                                {match.journal_entry_id && (
                                    <Link
                                        className="underline"
                                        href={`/accounting/entries/${match.journal_entry_id}`}
                                    >
                                        Asiento de conciliación
                                    </Link>
                                )}
                                {match.reversal_entry_id && (
                                    <Link
                                        className="ml-3 underline"
                                        href={`/accounting/entries/${match.reversal_entry_id}`}
                                    >
                                        Contrapartida
                                    </Link>
                                )}
                                {canCorrect &&
                                    !match.reversed_at &&
                                    !match.generated_operation && (
                                        <Correction match={match} />
                                    )}
                                {match.generated_operation && (
                                    <p className="text-sm">
                                        La operación generada desde el banco
                                        requiere una corrección contable
                                        separada; se conserva íntegramente.
                                    </p>
                                )}
                            </section>
                        ))}
                        {canMatch && (
                            <form
                                className="space-y-3 border-t pt-3"
                                onSubmit={(event) => {
                                    event.preventDefault();
                                    review.post(
                                        `/banking/transactions/${row.id}/review`,
                                        { onSuccess: () => setOpen(false) },
                                    );
                                }}
                            >
                                <h3>Documentar investigación o resolución</h3>
                                <select
                                    className="w-full rounded border p-2"
                                    aria-label="Tipo de discrepancia"
                                    value={review.data.category}
                                    onChange={(event) =>
                                        review.setData(
                                            'category',
                                            event.target.value,
                                        )
                                    }
                                >
                                    {Object.entries(terms)
                                        .filter(([key]) =>
                                            [
                                                'no_erp_operation',
                                                'no_bank_movement',
                                                'amount_difference',
                                                'reference_difference',
                                                'possible_duplicate',
                                                'voided_operation',
                                                'already_reconciled',
                                                'closed_period',
                                                'bank_fee',
                                            ].includes(key),
                                        )
                                        .map(([key, label]) => (
                                            <option key={key} value={key}>
                                                {label}
                                            </option>
                                        ))}
                                </select>
                                <select
                                    className="w-full rounded border p-2"
                                    aria-label="Estado de revisión"
                                    value={review.data.review_status}
                                    onChange={(event) =>
                                        review.setData(
                                            'review_status',
                                            event.target.value,
                                        )
                                    }
                                >
                                    <option value="investigating">
                                        En investigación
                                    </option>
                                    <option value="documented">
                                        Revisión documentada, sin ajuste
                                        financiero
                                    </option>
                                    {['reconciled', 'accounted'].includes(
                                        row.status,
                                    ) && (
                                        <option value="resolved">
                                            Resuelta con operación registrada
                                        </option>
                                    )}
                                </select>
                                <label className="block">
                                    Motivo
                                    <Textarea
                                        required
                                        minLength={5}
                                        value={review.data.reason}
                                        onChange={(event) =>
                                            review.setData(
                                                'reason',
                                                event.target.value,
                                            )
                                        }
                                    />
                                </label>
                                <label className="flex gap-2">
                                    <input
                                        type="checkbox"
                                        required
                                        checked={review.data.confirmed}
                                        onChange={(event) =>
                                            review.setData(
                                                'confirmed',
                                                event.target.checked,
                                            )
                                        }
                                    />
                                    Confirmo el registro de revisión.
                                </label>
                                {Object.entries(review.errors).map(
                                    ([field, error]) => (
                                        <p
                                            role="alert"
                                            className="text-destructive"
                                            key={field}
                                        >
                                            {error}
                                        </p>
                                    ),
                                )}
                                <Button disabled={review.processing}>
                                    Guardar revisión
                                </Button>
                            </form>
                        )}
                        {details.reviews.map((item) => (
                            <p className="text-sm break-words" key={item.id}>
                                {formatInstant(item.date)} · {item.actor} ·{' '}
                                {item.values.category
                                    ? terms[item.values.category]
                                    : item.event === 'bank_match_confirmed'
                                      ? 'Conciliación confirmada'
                                      : item.event === 'bank_match_corrected'
                                        ? 'Conciliación corregida'
                                        : 'Revisión registrada'}{' '}
                                · {item.values.reason}
                            </p>
                        ))}
                    </>
                )}
            </DialogContent>
        </Dialog>
    );
}
export function ReconciliationPanel({
    accounts,
    accountId,
    transactions,
    extras,
    canMatch,
    canCorrect,
    filters = {},
}: {
    accounts: { id: number; name: string }[];
    accountId: number | null;
    transactions: { data: BankRow[]; links: PageLink[] };
    extras: ReconciliationExtras;
    filters?: Record<string, string | number>;
    canMatch: boolean;
    canCorrect: boolean;
}) {
    return (
        <div className="space-y-5">
            <Panel title="Conciliación bancaria">
                <Form
                    action="/banking"
                    method="get"
                    className="finance-controls"
                >
                    <input type="hidden" name="tab" value="reconciliation" />
                    <label>
                        Cuenta
                        <select name="account" defaultValue={accountId || ''}>
                            {accounts.map((account) => (
                                <option key={account.id} value={account.id}>
                                    {account.name}
                                </option>
                            ))}
                        </select>
                    </label>
                    <label>
                        Desde
                        <Input
                            type="date"
                            name="from"
                            defaultValue={filters.from}
                        />
                    </label>
                    <label>
                        Hasta
                        <Input
                            type="date"
                            name="to"
                            defaultValue={filters.to}
                        />
                    </label>
                    <label>
                        Referencia o concepto
                        <Input name="search" defaultValue={filters.search} />
                    </label>
                    <label>
                        Importe mínimo
                        <Input
                            defaultValue={filters.amount_min}
                            name="amount_min"
                            type="number"
                            step="0.01"
                        />
                    </label>
                    <label>
                        Importe máximo
                        <Input
                            defaultValue={filters.amount_max}
                            name="amount_max"
                            type="number"
                            step="0.01"
                        />
                    </label>
                    <label>
                        Estado
                        <select name="status" defaultValue={filters.status}>
                            <option value="">Todos</option>
                            <option value="pending">Sin conciliar</option>
                            <option value="partial">
                                Parcialmente conciliado
                            </option>
                            <option value="reconciled">Conciliado</option>
                            <option value="accounted">Contabilizado</option>
                        </select>
                    </label>
                    <Button>Filtrar</Button>
                </Form>
                <p className="text-sm">
                    Una sugerencia no es una conciliación confirmada. Los saldos
                    bancarios proceden de los movimientos registrados.
                </p>
                <div className="billing-table-wrap always-scroll">
                    <table className="billing-table">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Concepto y referencia</th>
                                <th>Importe</th>
                                <th>Pendiente de conciliar</th>
                                <th>Estado e incidencias</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            {transactions.data.map((row) => (
                                <tr key={row.id}>
                                    <td>{date(row.booked_on)}</td>
                                    <td>
                                        {row.description}
                                        <small>{row.reference || '—'}</small>
                                    </td>
                                    <td>{money(row.amount)}</td>
                                    <td>
                                        {money(
                                            row.reconciliation_details
                                                ?.remaining || null,
                                        )}
                                    </td>
                                    <td>
                                        {row.status === 'partial'
                                            ? 'Parcialmente conciliado'
                                            : row.status === 'pending'
                                              ? 'Sin conciliar'
                                              : row.status === 'reconciled'
                                                ? 'Conciliado'
                                                : 'Contabilizado'}
                                        {row.reconciliation_details?.flags.map(
                                            (flag) => (
                                                <small key={flag}>
                                                    {terms[flag] || flag}
                                                </small>
                                            ),
                                        )}
                                    </td>
                                    <td>
                                        {accountId && (
                                            <MovementReview
                                                row={row}
                                                accountId={accountId}
                                                canMatch={canMatch}
                                                canCorrect={canCorrect}
                                            />
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                {transactions.data.length === 0 && (
                    <p>No hay movimientos registrados para estos filtros.</p>
                )}
                <Pagination links={transactions.links} />
            </Panel>
            <Panel title="Operaciones ERP sin conciliar">
                <Form
                    action="/banking"
                    method="get"
                    className="finance-controls"
                >
                    <input type="hidden" name="tab" value="reconciliation" />
                    <input
                        type="hidden"
                        name="account"
                        value={accountId || ''}
                    />
                    <input
                        type="hidden"
                        name="from"
                        value={filters.from || ''}
                    />
                    <input type="hidden" name="to" value={filters.to || ''} />
                    <Input
                        name="erp_search"
                        defaultValue={filters.erp_search}
                        aria-label="Buscar operación ERP pendiente"
                    />
                    <Button>Buscar</Button>
                </Form>
                <p className="text-sm">
                    Sin cuenta ERP registrada no se presupone una cuenta
                    bancaria. Revisa la operación antes de vincularla.
                </p>
                {extras.unmatchedOperations ? (
                    <>
                        <div className="billing-table-wrap always-scroll">
                            <table className="billing-table">
                                <thead>
                                    <tr>
                                        <th>Fecha</th>
                                        <th>Operación y referencia</th>
                                        <th>Importe sin conciliar</th>
                                        <th>Cuenta ERP</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {extras.unmatchedOperations.data.map(
                                        (operation) => (
                                            <tr
                                                key={`${operation.source}-${operation.id}`}
                                            >
                                                <td>{date(operation.date)}</td>
                                                <td>
                                                    <Link
                                                        className="underline"
                                                        href={`/billing/payments?search=${encodeURIComponent(operation.description)}`}
                                                    >
                                                        {operation.description}
                                                    </Link>
                                                    <small>
                                                        {operation.reference ||
                                                            '—'}
                                                    </small>
                                                </td>
                                                <td>
                                                    {money(operation.amount)}
                                                </td>
                                                <td>{operation.account}</td>
                                            </tr>
                                        ),
                                    )}
                                </tbody>
                            </table>
                        </div>
                        <Pagination links={extras.unmatchedOperations.links} />
                    </>
                ) : (
                    <p>Operaciones ERP no disponibles.</p>
                )}
            </Panel>
        </div>
    );
}

import { Form, Head, Link, useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { AccountInput, roleNames } from '@/components/accounting/account-input';
import { Panel } from '@/components/billing/workspace';
import { PageHeader, PageShell } from '@/components/page-shell';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';

type Account = { id: number; code: string; name: string; type: string };
type Rule = {
    role: string;
    account_code: string;
    operation_type: string;
    product_type: string;
    category_id: number | null;
    category_type: string;
    active: boolean;
};
type Props = {
    configuration: {
        revision: number;
        defaults: Record<string, string>;
        rules: Rule[];
        templates: Record<string, { es: string; en: string }>;
    };
    roles: Record<string, [string, string]>;
    entities: string[];
    entityTypes: Record<string, string>;
    categories: { id: number; name: string }[];
    expenseCategories: { id: number; name: string }[];
    accounts: Account[];
    canConfigure: boolean;
    canAssign: boolean;
    canManageAccounts: boolean;
    selectedEntity: { entity?: string; entity_id?: string };
};
const entityNames: Record<string, string> = {
    customer: 'Cliente o personal',
    supplier: 'Proveedor',
    product: 'Producto o servicio',
    financial_product: 'Tarjeta de crédito',
    bank: 'Banco',
    expense_category: 'Categoría de gasto',
    tax_rate: 'Tipo de IVA',
    financial_category: 'Categoría financiera',
    sale: 'Factura emitida',
    purchase: 'Documento de compra',
    expense: 'Gasto',
    receipt: 'Cobro',
    payment: 'Pago',
};
function Errors({ errors }: { errors: Record<string, string> }) {
    return (
        <>
            {Object.entries(errors).map(([k, v]) => (
                <p key={k} role="alert" className="text-sm text-destructive">
                    {v}
                </p>
            ))}
        </>
    );
}
export default function AccountingControl(p: Props) {
    const form = useForm({
        revision: p.configuration.revision,
        defaults: { ...p.configuration.defaults },
        rules: p.configuration.rules,
        templates: p.configuration.templates,
        reason: '',
        confirmed: false,
    });
    const [kind, setKind] = useState(p.selectedEntity.entity ?? 'customer');
    const [search, setSearch] = useState('');
    const [entities, setEntities] = useState<{
        data: { id: number; label: string }[];
        current_page: number;
        last_page: number;
    }>({ data: [], current_page: 1, last_page: 1 });
    const [page, setPage] = useState(1);
    const [entityId, setEntityId] = useState(
        Number(p.selectedEntity.entity_id) || 0,
    );
    const [error, setError] = useState('');
    useEffect(() => {
        let active = true;
        const c = new AbortController();
        fetch(
            `/accounting/control/entities/${kind}?search=${encodeURIComponent(search)}&page=${page}`,
            { headers: { Accept: 'application/json' }, signal: c.signal },
        )
            .then(async (r) => {
                if (!r.ok) {
                    throw new Error('No se pudieron consultar las fichas.');
                }

                return r.json();
            })
            .then((d) => {
                if (active) {
                    setEntities(d);
                    setError('');
                }
            })
            .catch((e) => {
                if (active && e.name !== 'AbortError') {
                    setError(e.message);
                }
            });

        return () => {
            active = false;
            c.abort();
        };
    }, [kind, search, page]);
    const setRule = (i: number, key: keyof Rule, value: string | boolean) =>
        form.setData(
            'rules',
            form.data.rules.map((r, j) =>
                j === i ? { ...r, [key]: value } : r,
            ),
        );

    return (
        <>
            <Head title="Configuración contable" />
            <PageShell mode="full">
                <PageHeader
                    title="Control y configuración contable"
                    description="Las decisiones se aplican a operaciones futuras. El histórico solo se corrige mediante acciones expresas."
                    actions={
                        <Button asChild variant="outline">
                            <Link href="/accounting">
                                Volver a Contabilidad
                            </Link>
                        </Button>
                    }
                />
                <Panel title="Criterio de asignación">
                    <ol className="list-decimal space-y-1 pl-5">
                        <li>Cuenta elegida para la operación.</li>
                        <li>Cuenta del tercero, producto o concepto.</li>
                        <li>
                            Primera regla activa aplicable, en el orden
                            mostrado.
                        </li>
                        <li>Cuenta predeterminada de la categoría.</li>
                        <li>Cuenta general válida del sistema.</li>
                    </ol>
                    <p className="mt-3 text-sm">
                        No se crean subcuentas al guardar una ficha. Las cuentas
                        inactivas, de agrupación o incompatibles bloquean la
                        contabilización.
                    </p>
                    <div className="mt-3 flex flex-wrap gap-3">
                        <Link href="/accounting/accounts?mode=manage">
                            Gestionar plan de cuentas
                        </Link>
                        <Link href="/accounting/review">
                            Bandeja de revisión contable
                        </Link>
                        <Link href="/accounting/templates">
                            Plantillas de nóminas, impuestos y otros asientos
                        </Link>
                    </div>
                </Panel>
                <Panel title="Asignaciones por ficha">
                    <p className="mb-3 text-sm">
                        Existencias e inmovilizado permiten preparar apuntes
                        manuales. No se generan valoraciones de almacén ni
                        amortizaciones automáticas.
                    </p>
                    <div className="grid gap-3 sm:grid-cols-3">
                        <label>
                            Área
                            <select
                                className="w-full rounded border p-2"
                                value={kind}
                                onChange={(e) => {
                                    setKind(e.target.value);
                                    setPage(1);
                                    setEntityId(0);
                                }}
                            >
                                {p.entities.map((k) => (
                                    <option key={k} value={k}>
                                        {entityNames[k] ?? k}
                                    </option>
                                ))}
                            </select>
                        </label>
                        <label>
                            Buscar ficha
                            <Input
                                value={search}
                                onChange={(e) => {
                                    setSearch(e.target.value);
                                    setPage(1);
                                }}
                            />
                        </label>
                        <label>
                            Ficha
                            <select
                                className="w-full rounded border p-2"
                                value={entityId}
                                onChange={(e) =>
                                    setEntityId(Number(e.target.value))
                                }
                            >
                                <option value={0}>Seleccionar ficha</option>
                                {entities.data.map((e) => (
                                    <option key={e.id} value={e.id}>
                                        {e.label}
                                    </option>
                                ))}
                            </select>
                        </label>
                    </div>
                    {error && <p role="alert">{error}</p>}
                    <div className="my-3 flex items-center gap-3">
                        <Button
                            variant="outline"
                            disabled={page <= 1}
                            onClick={() => setPage(page - 1)}
                        >
                            Anterior
                        </Button>
                        <span>
                            {page} / {entities.last_page}
                        </span>
                        <Button
                            variant="outline"
                            disabled={page >= entities.last_page}
                            onClick={() => setPage(page + 1)}
                        >
                            Siguiente
                        </Button>
                    </div>
                    {entityId > 0 && (
                        <EntityAssignments
                            key={`${kind}:${entityId}`}
                            kind={kind}
                            id={entityId}
                            accounts={p.accounts}
                            canConfigure={p.canAssign}
                            roles={p.roles}
                            categories={p.categories}
                        />
                    )}
                </Panel>
                <form
                    className="space-y-5"
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.patch('/accounting/control', {
                            preserveScroll: true,
                        });
                    }}
                >
                    <fieldset disabled={!p.canConfigure} className="space-y-5">
                        <Panel title="Cuentas generales visibles">
                            <p className="mb-3 text-sm">
                                Un campo vacío conserva la cuenta general
                                existente. Elegir otra cuenta requiere que tenga
                                la misma naturaleza.
                            </p>
                            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                {Object.keys(p.roles).map((role) => (
                                    <label key={role}>
                                        {roleNames[role] ?? role}
                                        <AccountInput
                                            value={
                                                form.data.defaults[role] ?? ''
                                            }
                                            accounts={p.accounts}
                                            onChange={(v) =>
                                                form.setData('defaults', {
                                                    ...form.data.defaults,
                                                    [role]: v,
                                                })
                                            }
                                        />
                                        <small>
                                            Prefijo compatible:{' '}
                                            {p.roles[role][0]}
                                        </small>
                                    </label>
                                ))}
                            </div>
                        </Panel>
                        <Panel title="Reglas de contabilización">
                            <p className="mb-3 text-sm">
                                Se aplica la primera coincidencia. Las
                                asignaciones manuales y específicas tienen
                                prioridad.
                            </p>
                            {form.data.rules.map((rule, i) => (
                                <div
                                    key={i}
                                    className="mb-3 grid gap-2 rounded border p-3 sm:grid-cols-2"
                                >
                                    <label>
                                        Prioridad {i + 1}
                                        <select
                                            className="w-full rounded border p-2"
                                            value={rule.role}
                                            onChange={(e) =>
                                                setRule(
                                                    i,
                                                    'role',
                                                    e.target.value,
                                                )
                                            }
                                        >
                                            {Object.keys(p.roles).map((r) => (
                                                <option key={r} value={r}>
                                                    {roleNames[r]}
                                                </option>
                                            ))}
                                        </select>
                                    </label>
                                    <label>
                                        Cuenta
                                        <AccountInput
                                            accounts={p.accounts}
                                            value={rule.account_code}
                                            onChange={(v) =>
                                                setRule(i, 'account_code', v)
                                            }
                                            required
                                        />
                                    </label>
                                    <label>
                                        Operación
                                        <select
                                            className="w-full rounded border p-2"
                                            value={rule.operation_type ?? ''}
                                            onChange={(e) =>
                                                setRule(
                                                    i,
                                                    'operation_type',
                                                    e.target.value,
                                                )
                                            }
                                        >
                                            <option value="">
                                                Cualquier operación compatible
                                            </option>
                                            {Object.entries(p.entityTypes)
                                                .filter(([k]) =>
                                                    [
                                                        'sale',
                                                        'purchase',
                                                        'expense',
                                                        'receipt',
                                                        'payment',
                                                    ].includes(k),
                                                )
                                                .map(([k, v]) => (
                                                    <option key={k} value={v}>
                                                        {entityNames[k]}
                                                    </option>
                                                ))}
                                        </select>
                                    </label>
                                    <label>
                                        Producto o servicio
                                        <select
                                            className="w-full rounded border p-2"
                                            value={rule.product_type ?? ''}
                                            onChange={(e) =>
                                                setRule(
                                                    i,
                                                    'product_type',
                                                    e.target.value,
                                                )
                                            }
                                        >
                                            <option value="">Cualquiera</option>
                                            <option value="product">
                                                Producto
                                            </option>
                                            <option value="service">
                                                Servicio
                                            </option>
                                        </select>
                                    </label>
                                    <label>
                                        Categoría
                                        <select
                                            className="w-full rounded border p-2"
                                            value={
                                                rule.category_id
                                                    ? `${rule.category_type}:${rule.category_id}`
                                                    : ''
                                            }
                                            onChange={(e) => {
                                                const selected = e.target.value;
                                                const split =
                                                    selected.lastIndexOf(':');
                                                form.setData(
                                                    'rules',
                                                    form.data.rules.map(
                                                        (r, j) =>
                                                            j === i
                                                                ? {
                                                                      ...r,
                                                                      category_type:
                                                                          split <
                                                                          0
                                                                              ? ''
                                                                              : selected.slice(
                                                                                    0,
                                                                                    split,
                                                                                ),
                                                                      category_id:
                                                                          split <
                                                                          0
                                                                              ? null
                                                                              : Number(
                                                                                    selected.slice(
                                                                                        split +
                                                                                            1,
                                                                                    ),
                                                                                ),
                                                                  }
                                                                : r,
                                                    ),
                                                );
                                            }}
                                        >
                                            <option value="">Cualquiera</option>
                                            {p.categories.map((c) => (
                                                <option
                                                    key={`f${c.id}`}
                                                    value={`App\\Models\\FinancialCategory:${c.id}`}
                                                >
                                                    {c.name}
                                                </option>
                                            ))}
                                            {p.expenseCategories.map((c) => (
                                                <option
                                                    key={`e${c.id}`}
                                                    value={`App\\Models\\ExpenseCategory:${c.id}`}
                                                >
                                                    {c.name}
                                                </option>
                                            ))}
                                        </select>
                                    </label>
                                    <label>
                                        <input
                                            type="checkbox"
                                            checked={rule.active}
                                            onChange={(e) =>
                                                setRule(
                                                    i,
                                                    'active',
                                                    e.target.checked,
                                                )
                                            }
                                        />{' '}
                                        Regla activa
                                    </label>
                                    <div className="flex flex-wrap gap-2">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            disabled={i === 0}
                                            onClick={() => {
                                                const rows = [
                                                    ...form.data.rules,
                                                ];
                                                [rows[i - 1], rows[i]] = [
                                                    rows[i],
                                                    rows[i - 1],
                                                ];
                                                form.setData('rules', rows);
                                            }}
                                        >
                                            Subir prioridad
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={() =>
                                                form.setData(
                                                    'rules',
                                                    form.data.rules.filter(
                                                        (_, j) => j !== i,
                                                    ),
                                                )
                                            }
                                        >
                                            Quitar regla
                                        </Button>
                                    </div>
                                </div>
                            ))}
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() =>
                                    form.setData('rules', [
                                        ...form.data.rules,
                                        {
                                            role: 'revenue',
                                            account_code: '',
                                            operation_type: '',
                                            product_type: '',
                                            category_id: null,
                                            category_type: '',
                                            active: true,
                                        },
                                    ])
                                }
                            >
                                Añadir regla
                            </Button>
                        </Panel>
                        <Panel title="Plantillas de conceptos">
                            <p className="mb-3 text-sm">
                                Variables permitidas:{' '}
                                {
                                    '{numero}, {cliente}, {proveedor}, {referencia}, {concepto}'
                                }
                                . No se inventan referencias ausentes.
                            </p>
                            {Object.entries(form.data.templates).map(
                                ([key, t]) => (
                                    <fieldset
                                        key={key}
                                        className="mb-4 grid gap-3 rounded border p-3 sm:grid-cols-2"
                                    >
                                        <legend>
                                            {(
                                                {
                                                    sales_credit_note:
                                                        'Factura rectificativa emitida',
                                                    purchase_credit_note:
                                                        'Factura rectificativa recibida',
                                                    advance_allocation:
                                                        'Aplicación de anticipo',
                                                    sales_invoice:
                                                        'Factura emitida',
                                                    purchase_invoice:
                                                        'Factura recibida',
                                                    receipt: 'Cobro',
                                                    payment: 'Pago',
                                                    customer_advance:
                                                        'Anticipo de cliente',
                                                    supplier_advance:
                                                        'Anticipo a proveedor',
                                                    bank_fee:
                                                        'Comisión bancaria',
                                                    reversal: 'Anulación',
                                                    correction: 'Rectificación',
                                                    vat: 'IVA',
                                                } as Record<string, string>
                                            )[key] ?? key}
                                        </legend>
                                        {(['es', 'en'] as const).map((lang) => (
                                            <label key={lang}>
                                                {lang === 'es'
                                                    ? 'Español'
                                                    : 'English'}
                                                <Input
                                                    value={t[lang]}
                                                    maxLength={255}
                                                    required
                                                    onChange={(e) =>
                                                        form.setData(
                                                            'templates',
                                                            {
                                                                ...form.data
                                                                    .templates,
                                                                [key]: {
                                                                    ...t,
                                                                    [lang]: e
                                                                        .target
                                                                        .value,
                                                                },
                                                            },
                                                        )
                                                    }
                                                />
                                            </label>
                                        ))}
                                    </fieldset>
                                ),
                            )}
                        </Panel>
                        {p.canConfigure && (
                            <Panel title="Confirmar configuración">
                                <label>
                                    Motivo
                                    <Textarea
                                        value={form.data.reason}
                                        onChange={(e) =>
                                            form.setData(
                                                'reason',
                                                e.target.value,
                                            )
                                        }
                                        required
                                        minLength={5}
                                    />
                                </label>
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
                                        required
                                    />{' '}
                                    Confirmo las reglas para operaciones
                                    futuras.
                                </label>
                                <Errors errors={form.errors} />
                                <Button disabled={form.processing}>
                                    Guardar configuración contable
                                </Button>
                            </Panel>
                        )}
                    </fieldset>
                </form>
            </PageShell>
        </>
    );
}
type Assignment = {
    posted_entry_id?: number | null;
    category_id?: number | null;
    kind: string;
    id: number;
    label: string;
    revision: string;
    assignments: {
        role: string;
        code: string | null;
        name: string | null;
        origin: string;
        proposal: { code: string; name: string; prefix: string };
    }[];
};
function EntityAssignments({
    kind,
    id,
    accounts,
    canConfigure,
    roles,
    categories,
}: {
    kind: string;
    id: number;
    accounts: Account[];
    canConfigure: boolean;
    roles: Props['roles'];
    categories: Props['categories'];
}) {
    const [data, setData] = useState<Assignment | null>(null);
    const [error, setError] = useState('');
    const form = useForm({
        role: '',
        account_code: '',
        revision: '',
        reason: '',
        confirmed: false,
    });
    const create = useForm({
        code: '',
        name: '',
        type: '',
        parent_code: '',
        is_postable: 1,
        is_active: 1,
    });
    const load = async () => {
        try {
            const r = await fetch(
                `/accounting/control/assignments/${kind}/${id}`,
                { headers: { Accept: 'application/json' } },
            );

            if (!r.ok) {
                throw new Error('No se pudieron consultar las asignaciones.');
            }

            const d = (await r.json()) as Assignment;
            setData(d);
            form.setData((v) => ({ ...v, revision: d.revision }));
        } catch (e) {
            setError(e instanceof Error ? e.message : 'No disponible');
        }
    };
    useEffect(() => {
        let live = true;
        fetch(`/accounting/control/assignments/${kind}/${id}`, {
            headers: { Accept: 'application/json' },
        })
            .then(async (r) => {
                if (!r.ok) {
                    throw new Error(
                        'No se pudieron consultar las asignaciones.',
                    );
                }

                return r.json();
            })
            .then((d: Assignment) => {
                if (live) {
                    setData(d);
                }
            })
            .catch((e) => {
                if (live) {
                    setError(e.message);
                }
            });

        return () => {
            live = false;
        };
    }, [kind, id]);

    if (error) {
        return <p role="alert">{error}</p>;
    }

    if (!data) {
        return <p>Consultando asignaciones…</p>;
    }

    return (
        <div className="space-y-4">
            <h3 className="font-semibold">{data.label}</h3>
            {data.posted_entry_id && (
                <Link href={`/accounting/entries/${data.posted_entry_id}`}>
                    Revisar asiento contabilizado
                </Link>
            )}
            {canConfigure &&
                !data.posted_entry_id &&
                ['sale', 'purchase'].includes(kind) && (
                    <Form
                        action={`/accounting/control/category/${kind}/${id}`}
                        method="patch"
                        className="space-y-3 rounded border p-3"
                        onSuccess={() => void load()}
                    >
                        <input
                            type="hidden"
                            name="revision"
                            value={data.revision}
                        />
                        <label>
                            Categoría contable de la operación
                            <select
                                className="w-full rounded border p-2"
                                name="category_id"
                                defaultValue={data.category_id ?? ''}
                            >
                                <option value="">
                                    Sin categoría específica
                                </option>
                                {categories.map((c) => (
                                    <option key={c.id} value={c.id}>
                                        {c.name}
                                    </option>
                                ))}
                            </select>
                        </label>
                        <label>
                            Motivo
                            <Input name="reason" minLength={5} required />
                        </label>
                        <label className="block">
                            <input
                                name="confirmed"
                                type="checkbox"
                                value="1"
                                required
                            />{' '}
                            Confirmo la categoría para contabilización futura.
                        </label>
                        <Button variant="outline">Guardar categoría</Button>
                    </Form>
                )}
            {data.assignments.map((a) => (
                <div
                    key={a.role}
                    className="flex flex-wrap items-center justify-between gap-3 rounded border p-3"
                >
                    <div>
                        <strong>{roleNames[a.role]}</strong>
                        <p>
                            {a.code
                                ? `${a.code} · ${a.name ?? ''}`
                                : 'Sin cuenta específica registrada'}
                        </p>
                        <small>
                            {a.code
                                ? 'Cuenta registrada para esta ficha.'
                                : `Propuesta sin crear: ${a.proposal.code} · ${a.proposal.name}`}
                        </small>
                    </div>
                    {a.code && (
                        <Link
                            href={`/accounting/entries/create?account_code=${a.code}`}
                        >
                            Preparar apunte manual
                        </Link>
                    )}
                    {canConfigure && !data.posted_entry_id && (
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => {
                                form.setData({
                                    role: a.role,
                                    account_code: a.code ?? '',
                                    revision: data.revision,
                                    reason: '',
                                    confirmed: false,
                                });
                                create.setData({
                                    code: a.proposal.code,
                                    name: a.proposal.name,
                                    type: roles[a.role][1],
                                    parent_code: a.proposal.prefix,
                                    is_postable: 1,
                                    is_active: 1,
                                });
                            }}
                        >
                            Revisar asignación
                        </Button>
                    )}
                </div>
            ))}
            {canConfigure && form.data.role && (
                <div className="grid gap-4 lg:grid-cols-2">
                    <form
                        className="space-y-3 rounded border p-4"
                        onSubmit={(e) => {
                            e.preventDefault();
                            form.patch(
                                `/accounting/control/assignments/${kind}/${id}`,
                                {
                                    preserveScroll: true,
                                    onSuccess: () => {
                                        void load();
                                        form.reset('reason', 'confirmed');
                                    },
                                },
                            );
                        }}
                    >
                        <h4>{roleNames[form.data.role]}</h4>
                        <label>
                            Cuenta autorizada
                            <AccountInput
                                accounts={accounts}
                                value={form.data.account_code}
                                onChange={(v) =>
                                    form.setData('account_code', v)
                                }
                            />
                        </label>
                        <p className="text-sm">
                            Dejar la cuenta vacía delega en reglas y categoría.
                            Esta decisión no cambia asientos ya contabilizados.
                        </p>
                        <label>
                            Motivo
                            <Textarea
                                value={form.data.reason}
                                onChange={(e) =>
                                    form.setData('reason', e.target.value)
                                }
                                minLength={5}
                                required
                            />
                        </label>
                        <label className="block">
                            <input
                                type="checkbox"
                                required
                                checked={form.data.confirmed}
                                onChange={(e) =>
                                    form.setData('confirmed', e.target.checked)
                                }
                            />{' '}
                            Confirmo la cuenta para esta ficha.
                        </label>
                        <Errors errors={form.errors} />
                        <Button disabled={form.processing}>
                            Guardar asignación
                        </Button>
                    </form>
                    <form
                        className="space-y-3 rounded border p-4"
                        onSubmit={(e) => {
                            e.preventDefault();
                            create.post('/accounting/accounts', {
                                preserveScroll: true,
                                onSuccess: () =>
                                    form.setData(
                                        'account_code',
                                        create.data.code,
                                    ),
                            });
                        }}
                    >
                        <h4>Crear subcuenta propuesta</h4>
                        <p className="text-sm">
                            La propuesta se basa en la naturaleza y el
                            identificador de esta ficha. Se crea únicamente al
                            confirmar esta acción; después guarda su asociación.
                        </p>
                        <label>
                            Código
                            <Input
                                value={create.data.code}
                                onChange={(e) =>
                                    create.setData('code', e.target.value)
                                }
                                required
                                pattern="[0-9]{3,20}"
                            />
                        </label>
                        <label>
                            Denominación
                            <Input
                                value={create.data.name}
                                onChange={(e) =>
                                    create.setData('name', e.target.value)
                                }
                                required
                            />
                        </label>
                        <p>Cuenta superior: {create.data.parent_code}</p>
                        <Errors errors={create.errors} />
                        <Button variant="outline" disabled={create.processing}>
                            Crear esta subcuenta
                        </Button>
                    </form>
                </div>
            )}
        </div>
    );
}

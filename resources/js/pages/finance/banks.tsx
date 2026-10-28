import { Form, Head, Link } from '@inertiajs/react';
import type { ComponentProps } from 'react';
import { ReconciliationPanel } from '@/components/banking/reconciliation-workspace';
import type { ReconciliationExtras } from '@/components/banking/reconciliation-workspace';
import { Panel, Empty, money, date } from '@/components/billing/workspace';
import { PageShell, PageHeader } from '@/components/page-shell';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import type LegacyBanking from '../banking/legacy-index';
import {
    AccountDialog,
    StatementImportPanel,
    ReconciliationRules,
} from '../banking/legacy-index';
import { FinanceAccounts } from './workspace';
import type { Account } from './workspace';
import '@/../css/finance.css';
type Props = ComponentProps<typeof LegacyBanking> &
    ReconciliationExtras & {
        financeAccounts: Account[] | null;
        capabilities: {
            manage: boolean;
            accounting: boolean;
            reconcile?: boolean;
            correctReconciliation?: boolean;
        };
        bankTab: string;
    };
export default function BanksWorkspace(p: Props) {
    const tab = p.bankTab;

    return (
        <PageShell className="finance-workspace">
            <Head title="Bancos y conciliación" />
            <PageHeader
                title="Bancos y conciliación"
                description="Gestiona tus cuentas bancarias e identifica y concilia los movimientos."
                breadcrumbs={[
                    { title: 'Finanzas', href: '/finance' },
                    { title: 'Bancos y conciliación', href: '/banking' },
                ]}
                actions={
                    p.capabilities.manage ? (
                        <div className="flex gap-2">
                            <Button variant="outline" asChild>
                                <Link
                                    href={`/banking?account=${p.selectedAccount?.id ?? ''}&tab=import`}
                                >
                                    Importar extracto
                                </Link>
                            </Button>
                            <AccountDialog />
                        </div>
                    ) : undefined
                }
            />
            <nav className="finance-tabs" aria-label="Vistas bancarias">
                {[
                    ['accounts', 'Cuentas bancarias'],
                    ['reconciliation', 'Conciliación'],
                    ['import', 'Importación'],
                    ['rules', 'Reglas de conciliación'],
                ].map(([key, label]) => (
                    <Link
                        key={key}
                        href={`/banking?account=${p.selectedAccount?.id ?? ''}&tab=${key}`}
                        aria-current={tab === key ? 'page' : undefined}
                    >
                        {label}
                    </Link>
                ))}
            </nav>
            {tab === 'accounts' && (
                <>
                    <FinanceAccounts accounts={p.financeAccounts ?? []} />
                    {p.selectedAccount && p.capabilities.manage && (
                        <Dialog>
                            <DialogTrigger asChild>
                                <Button variant="outline">
                                    Editar cuenta seleccionada
                                </Button>
                            </DialogTrigger>
                            <DialogContent>
                                <DialogHeader>
                                    <DialogTitle>
                                        Editar cuenta bancaria
                                    </DialogTitle>
                                </DialogHeader>
                                <Form
                                    action={`/banking/accounts/${p.selectedAccount.id}`}
                                    method="patch"
                                    className="finance-form"
                                >
                                    {({ processing, errors }) => (
                                        <>
                                            <label>
                                                Nombre
                                                <Input
                                                    name="name"
                                                    required
                                                    defaultValue={
                                                        p.selectedAccount?.name
                                                    }
                                                />
                                            </label>
                                            <label>
                                                Entidad
                                                <Input
                                                    name="bank_name"
                                                    defaultValue={
                                                        p.selectedAccount
                                                            ?.bank_name ?? ''
                                                    }
                                                />
                                            </label>
                                            <label>
                                                Estado
                                                <select
                                                    name="is_active"
                                                    defaultValue={
                                                        p.selectedAccount
                                                            ?.is_active
                                                            ? '1'
                                                            : '0'
                                                    }
                                                >
                                                    <option value="1">
                                                        Activa
                                                    </option>
                                                    <option value="0">
                                                        Inactiva
                                                    </option>
                                                </select>
                                            </label>
                                            {Object.entries(errors).map(
                                                ([key, error]) => (
                                                    <p role="alert" key={key}>
                                                        {error}
                                                    </p>
                                                ),
                                            )}
                                            <Button disabled={processing}>
                                                Guardar cuenta
                                            </Button>
                                        </>
                                    )}
                                </Form>
                            </DialogContent>
                        </Dialog>
                    )}
                    <Panel title="Saldo y control bancario">
                        {p.control ? (
                            <div className="finance-four">
                                {[
                                    [
                                        'Último saldo de extracto',
                                        p.control.statementBalance,
                                    ],
                                    [
                                        'Saldo reconstruido al último extracto',
                                        p.control.calculatedBankBalance,
                                    ],
                                    ['Saldo contable', p.control.ledgerBalance],
                                    [
                                        'Diferencia contable',
                                        p.control.accountingDifference,
                                    ],
                                ].map(([label, value]) => (
                                    <div key={String(label)}>
                                        <span>{label}</span>
                                        <strong className="mt-2 block text-xl">
                                            {money(Number(value))}
                                        </strong>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <Empty />
                        )}
                        <p>
                            Saldo registrado no equivale a disponible
                            confirmado. La fecha de control corresponde al
                            último extracto cuando existe.
                        </p>
                    </Panel>
                </>
            )}
            {tab === 'import' &&
                (p.selectedAccount && p.capabilities.manage ? (
                    <Panel
                        title={`Importar extracto · ${p.selectedAccount.name}`}
                    >
                        <StatementImportPanel account={p.selectedAccount} />
                        {p.imports.map((i) => (
                            <div className="finance-movement-card" key={i.id}>
                                <strong>{i.filename}</strong>
                                <span>
                                    {i.created_transactions} movimientos nuevos
                                    · {i.duplicate_transactions} duplicados
                                </span>
                                <small>
                                    {i.rolled_back_at
                                        ? 'Importación revertida'
                                        : 'Registro conservado'}
                                </small>
                            </div>
                        ))}
                    </Panel>
                ) : (
                    <Panel title="Importación de extractos">
                        <p>
                            Selecciona una cuenta y utiliza un usuario
                            autorizado para importar extractos CSV o Norma 43.
                        </p>
                    </Panel>
                ))}
            {tab === 'rules' &&
                (p.selectedAccount &&
                p.rulePreview &&
                p.capabilities.manage &&
                p.capabilities.accounting ? (
                    <ReconciliationRules
                        account={p.selectedAccount}
                        ledgerAccounts={p.ledgerAccounts}
                        rules={p.reconciliationRules}
                        preview={p.rulePreview}
                        runs={p.ruleRuns}
                    />
                ) : (
                    <Panel title="Reglas existentes">
                        {p.reconciliationRules.length ? (
                            p.reconciliationRules.map((r) => (
                                <div key={r.id}>
                                    <strong>{r.name}</strong>
                                    <p>
                                        {r.pattern} · versión{' '}
                                        {r.current_version} ·{' '}
                                        {r.is_active ? 'Activa' : 'Inactiva'}
                                    </p>
                                </div>
                            ))
                        ) : (
                            <Empty />
                        )}
                    </Panel>
                ))}
            {tab === 'reconciliation' && (
                <ReconciliationPanel
                    accounts={p.accounts}
                    accountId={p.selectedAccount?.id || null}
                    transactions={p.transactions}
                    extras={p}
                    filters={p.filters}
                    canMatch={p.capabilities.reconcile === true}
                    canCorrect={p.capabilities.correctReconciliation === true}
                />
            )}
            {tab === 'accounts' && (
                <Panel title="Movimientos bancarios">
                    <Form
                        action="/banking"
                        method="get"
                        className="finance-controls"
                    >
                        <input type="hidden" name="tab" value={tab} />
                        <label>
                            Cuenta
                            <select
                                name="account"
                                defaultValue={p.selectedAccount?.id}
                            >
                                {p.accounts.map((a) => (
                                    <option key={a.id} value={a.id}>
                                        {a.name}
                                    </option>
                                ))}
                            </select>
                        </label>
                        <label>
                            Buscar
                            <Input
                                name="search"
                                defaultValue={p.filters.search}
                                placeholder="Buscar concepto o referencia..."
                            />
                        </label>
                        <label>
                            Estado
                            <select
                                name="status"
                                defaultValue={p.filters.status}
                            >
                                <option value="">Todos los movimientos</option>
                                <option value="pending">Por conciliar</option>
                                <option value="reconciled">Conciliados</option>
                                <option value="accounted">
                                    Contabilizados
                                </option>
                            </select>
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
                        <Button variant="outline">Aplicar filtros</Button>
                    </Form>
                    <div className="finance-table-scroll">
                        <table className="finance-bank-table">
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Concepto original del banco</th>
                                    <th>Importe</th>
                                    <th>Correspondencia</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                {p.transactions.data.map((t) => (
                                    <tr key={t.id}>
                                        <td data-label="Fecha">
                                            {date(t.booked_on)}
                                            <small>
                                                Valor: {date(t.value_on)}
                                            </small>
                                        </td>
                                        <td data-label="Concepto">
                                            {t.description}
                                            <small>{t.reference}</small>
                                        </td>
                                        <td data-label="Importe">
                                            {money(t.amount)}
                                        </td>
                                        <td data-label="Correspondencia">
                                            {t.status === 'pending' &&
                                            t.candidates.length
                                                ? t.candidates.map((c) => (
                                                      <small key={c.value}>
                                                          {c.label} ·{' '}
                                                          {c.confidence}%
                                                      </small>
                                                  ))
                                                : t.status === 'pending'
                                                  ? 'Sin correspondencia'
                                                  : t.reconciliation
                                                    ? 'Pago vinculado'
                                                    : t.transfer
                                                      ? 'Transferencia interna'
                                                      : 'Asiento vinculado'}
                                        </td>
                                        <td data-label="Estado">
                                            {t.status === 'pending'
                                                ? 'Sin conciliar'
                                                : t.status === 'reconciled'
                                                  ? 'Conciliado'
                                                  : 'Contabilizado'}
                                        </td>
                                        <td data-label="Acciones">
                                            {p.capabilities.manage &&
                                            t.status === 'pending' &&
                                            t.candidates.length > 0 ? (
                                                <Button
                                                    variant="outline"
                                                    asChild
                                                >
                                                    <Link
                                                        href={`/banking?account=${p.selectedAccount?.id || ''}&tab=reconciliation`}
                                                    >
                                                        Revisar movimiento
                                                    </Link>
                                                </Button>
                                            ) : p.capabilities.manage &&
                                              t.reconciliation ? (
                                                <Button
                                                    variant="outline"
                                                    asChild
                                                >
                                                    <Link
                                                        href={`/banking?account=${p.selectedAccount?.id || ''}&tab=reconciliation`}
                                                    >
                                                        Revisar movimiento
                                                    </Link>
                                                </Button>
                                            ) : (
                                                <Button
                                                    asChild
                                                    size="sm"
                                                    variant="outline"
                                                >
                                                    <Link
                                                        href={`/banking?view=manage&account=${p.selectedAccount?.id}&search=${encodeURIComponent(t.reference ?? t.description)}`}
                                                    >
                                                        Ver detalle
                                                    </Link>
                                                </Button>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    {p.transactions.data.length === 0 && <Empty />}
                    <nav
                        className="finance-controls"
                        aria-label="Paginación bancaria"
                    >
                        {p.transactions.links.map((l, i) =>
                            l.url ? (
                                <Link
                                    key={i}
                                    href={l.url}
                                    aria-current={l.active ? 'page' : undefined}
                                >
                                    {l.label
                                        .replace(/&[^;]+;/g, '')
                                        .replace(/<[^>]+>/g, '')}
                                </Link>
                            ) : (
                                <span key={i}>
                                    {l.label
                                        .replace(/&[^;]+;/g, '')
                                        .replace(/<[^>]+>/g, '')}
                                </span>
                            ),
                        )}
                    </nav>
                    <p>
                        Las sugerencias requieren revisar importe, fecha y
                        documento. Conciliar un pago ya registrado lo vincula,
                        sin duplicarlo.
                    </p>
                </Panel>
            )}
            <Button variant="outline" asChild>
                <Link
                    href={`/banking?view=manage&account=${p.selectedAccount?.id ?? ''}`}
                >
                    Herramientas bancarias avanzadas
                </Link>
            </Button>
        </PageShell>
    );
}

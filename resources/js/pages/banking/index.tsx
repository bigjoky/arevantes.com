import { Form, Head, Link } from '@inertiajs/react';
import { ArrowLeft, Landmark, Plus, RefreshCw, Upload } from 'lucide-react';
import { useState } from 'react';
import { PaginationBar, TableViewport } from '@/components/page-shell';
import type { PaginationLink } from '@/components/page-shell';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
type Account = {
    id: number;
    name: string;
    bank_name: string | null;
    iban: string | null;
    currency: string;
    opening_balance: string;
    is_active: boolean;
    balance: number;
};
type Candidate = {
    value: string;
    label: string;
    reference_match: boolean;
    creates_payment: boolean;
    confidence: number;
};
type LedgerAccount = { id: number; code: string; name: string; type: string };
type StatementImport = {
    id: number;
    filename: string;
    format: string;
    period_from: string | null;
    period_to: string | null;
    opening_balance: string | null;
    closing_balance: string | null;
    created_transactions: number;
    duplicate_transactions: number;
    control_valid: boolean | null;
    created_at: string;
};
type BankControl = {
    periodTo: string;
    statementBalance: number;
    calculatedBankBalance: number;
    ledgerBalance: number;
    statementDifference: number;
    accountingDifference: number;
    matchesStatement: boolean;
    matchesAccounting: boolean;
};
type Transaction = {
    id: number;
    booked_on: string;
    value_on: string | null;
    amount: string;
    reference: string | null;
    description: string;
    status: string;
    candidates: Candidate[];
    reconciliation: {
        id: number;
        method: string;
        reconciled_at: string;
    } | null;
    posting: {
        id: number;
        reversed_at: string | null;
        counterpart: { code: string; name: string };
    } | null;
    transfer: { id: number; reversed_at: string | null } | null;
};
type TransactionPage = {
    data: Transaction[];
    links: PaginationLink[];
    total: number;
};
export default function BankingIndex({
    accounts,
    selectedAccount,
    transactions,
    imports,
    ledgerAccounts,
    control,
    summary,
    filters,
}: {
    accounts: Account[];
    selectedAccount: Account | null;
    transactions: TransactionPage;
    imports: StatementImport[];
    ledgerAccounts: LedgerAccount[];
    control: BankControl | null;
    summary: { pending: number; reconciled: number; pendingAmount: number };
    filters: { search: string; status: string; from: string; to: string };
}) {
    return (
        <>
            <Head title="Banking" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <datalist id="ledger-account-options">
                    {ledgerAccounts.map((account) => (
                        <option key={account.id} value={account.code}>
                            {account.name}
                        </option>
                    ))}
                </datalist>
                <header className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold">
                            Bank reconciliation
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Import statements and reconcile movements directly
                            with payments or outstanding documents.
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button variant="outline" asChild>
                            <Link href="/finance">
                                <ArrowLeft /> Finance
                            </Link>
                        </Button>
                        <AccountDialog />
                    </div>
                </header>
                <section className="flex gap-2 overflow-x-auto border-y py-3">
                    {accounts.map((account) => (
                        <Link
                            key={account.id}
                            href={`/banking?account=${account.id}`}
                            className={`min-w-56 border-l-2 px-3 py-1 ${selectedAccount?.id === account.id ? 'border-primary bg-muted/40' : 'border-transparent'}`}
                        >
                            <div className="font-medium">{account.name}</div>
                            <div className="text-sm text-muted-foreground">
                                {account.bank_name ||
                                    account.iban ||
                                    account.currency}
                            </div>
                            <div className="mt-1 font-semibold">
                                {money(account.balance, account.currency)}
                            </div>
                        </Link>
                    ))}
                    {accounts.length === 0 && (
                        <div className="py-5 text-sm text-muted-foreground">
                            Create a bank account to begin.
                        </div>
                    )}
                </section>
                {selectedAccount && (
                    <>
                        <section className="grid grid-cols-3 border-y">
                            <Metric
                                label="Pending"
                                value={String(summary.pending)}
                            />
                            <Metric
                                label="Reconciled"
                                value={String(summary.reconciled)}
                            />
                            <Metric
                                label="Pending net amount"
                                value={money(
                                    summary.pendingAmount,
                                    selectedAccount.currency,
                                )}
                            />
                        </section>
                        {control && (
                            <section className="border-y">
                                <div className="grid grid-cols-2 lg:grid-cols-4">
                                    <Metric
                                        label={`Statement · ${date(control.periodTo)}`}
                                        value={money(
                                            control.statementBalance,
                                            selectedAccount.currency,
                                        )}
                                    />
                                    <Metric
                                        label="Imported movements balance"
                                        value={money(
                                            control.calculatedBankBalance,
                                            selectedAccount.currency,
                                        )}
                                    />
                                    <Metric
                                        label="Accounting subaccount"
                                        value={money(
                                            control.ledgerBalance,
                                            selectedAccount.currency,
                                        )}
                                    />
                                    <Metric
                                        label="Accounting difference"
                                        value={money(
                                            control.accountingDifference,
                                            selectedAccount.currency,
                                        )}
                                    />
                                </div>
                                <div className="flex flex-wrap gap-4 border-t px-4 py-2 text-sm">
                                    <span
                                        className={
                                            control.matchesStatement
                                                ? 'text-green-700 dark:text-green-400'
                                                : 'text-destructive'
                                        }
                                    >
                                        {control.matchesStatement
                                            ? 'Statement movements complete'
                                            : `Statement mismatch: ${money(control.statementDifference, selectedAccount.currency)}`}
                                    </span>
                                    <span
                                        className={
                                            control.matchesAccounting
                                                ? 'text-green-700 dark:text-green-400'
                                                : 'text-amber-700 dark:text-amber-400'
                                        }
                                    >
                                        {control.matchesAccounting
                                            ? 'Accounting reconciled'
                                            : `${money(control.accountingDifference, selectedAccount.currency)} pending accounting reconciliation`}
                                    </span>
                                </div>
                            </section>
                        )}
                        <section className="grid gap-6 border-t pt-5 lg:grid-cols-2">
                            <Form
                                action={`/banking/accounts/${selectedAccount.id}/import`}
                                method="post"
                                className="flex flex-wrap items-end gap-3"
                            >
                                <div className="grid min-w-64 flex-1 gap-2">
                                    <Label>Statement CSV or Norma 43</Label>
                                    <Input
                                        name="statement"
                                        type="file"
                                        accept=".csv,.txt,.n43,.aeb,text/csv,text/plain"
                                        required
                                    />
                                </div>
                                <Button>
                                    <Upload /> Import statement
                                </Button>
                            </Form>
                            <Form
                                action={`/banking/accounts/${selectedAccount.id}/auto-reconcile`}
                                method="post"
                                className="flex items-end justify-end"
                            >
                                <Button variant="outline">
                                    <RefreshCw /> Auto reconcile exact matches
                                </Button>
                            </Form>
                        </section>
                        {imports.length > 0 && (
                            <TableViewport>
                                <h2 className="mb-2 text-sm font-semibold">
                                    Recent statement imports
                                </h2>
                                <table className="w-full min-w-[52rem] text-sm">
                                    <thead>
                                        <tr className="border-b text-left text-muted-foreground">
                                            <th className="py-2">File</th>
                                            <th>Format</th>
                                            <th>Period</th>
                                            <th className="text-right">
                                                Created
                                            </th>
                                            <th className="text-right">
                                                Duplicates
                                            </th>
                                            <th>Control</th>
                                            <th className="text-right">
                                                Closing balance
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {imports.map((item) => (
                                            <tr
                                                key={item.id}
                                                className="border-b"
                                            >
                                                <td
                                                    className="max-w-xs truncate py-2"
                                                    title={item.filename}
                                                >
                                                    {item.filename}
                                                </td>
                                                <td>
                                                    <Badge variant="outline">
                                                        {item.format ===
                                                        'norma43'
                                                            ? 'Norma 43'
                                                            : 'CSV'}
                                                    </Badge>
                                                </td>
                                                <td>
                                                    {item.period_from
                                                        ? `${date(item.period_from)} – ${date(item.period_to!)}`
                                                        : '—'}
                                                </td>
                                                <td className="text-right">
                                                    {item.created_transactions}
                                                </td>
                                                <td className="text-right">
                                                    {
                                                        item.duplicate_transactions
                                                    }
                                                </td>
                                                <td>
                                                    {item.control_valid ===
                                                    null ? (
                                                        'Not available'
                                                    ) : item.control_valid ? (
                                                        <span className="text-green-700 dark:text-green-400">
                                                            Valid
                                                        </span>
                                                    ) : (
                                                        <span className="text-destructive">
                                                            Mismatch
                                                        </span>
                                                    )}
                                                </td>
                                                <td className="text-right">
                                                    {item.closing_balance ===
                                                    null
                                                        ? '—'
                                                        : money(
                                                              Number(
                                                                  item.closing_balance,
                                                              ),
                                                              selectedAccount.currency,
                                                          )}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </TableViewport>
                        )}
                        <Form
                            action="/banking"
                            method="get"
                            className="grid gap-3 rounded-lg border bg-muted/20 p-3 sm:grid-cols-2 xl:grid-cols-[minmax(14rem,1fr)_10rem_10rem_10rem_auto]"
                        >
                            <input
                                type="hidden"
                                name="account"
                                value={selectedAccount.id}
                            />
                            <Input
                                name="search"
                                defaultValue={filters.search}
                                placeholder="Search description or reference"
                            />
                            <Select
                                name="status"
                                defaultValue={filters.status || 'all'}
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="All statuses" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        All statuses
                                    </SelectItem>
                                    <SelectItem value="pending">
                                        Pending
                                    </SelectItem>
                                    <SelectItem value="reconciled">
                                        Reconciled
                                    </SelectItem>
                                    <SelectItem value="accounted">
                                        Accounted
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <Input
                                name="from"
                                type="date"
                                defaultValue={filters.from}
                                aria-label="From date"
                            />
                            <Input
                                name="to"
                                type="date"
                                defaultValue={filters.to}
                                aria-label="To date"
                            />
                            <Button variant="outline">Apply filters</Button>
                        </Form>
                        <TableViewport>
                            <table className="w-full min-w-[68rem] text-sm">
                                <thead>
                                    <tr className="border-b text-left text-muted-foreground">
                                        <th className="py-2">Date</th>
                                        <th>Description</th>
                                        <th>Reference</th>
                                        <th className="text-right">Amount</th>
                                        <th>Status</th>
                                        <th className="w-[25rem]">Match</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {transactions.data.map((transaction) => (
                                        <tr
                                            key={transaction.id}
                                            className="border-b align-top"
                                        >
                                            <td className="py-3">
                                                {date(transaction.booked_on)}
                                            </td>
                                            <td>
                                                <div className="max-w-sm font-medium">
                                                    {transaction.description}
                                                </div>
                                            </td>
                                            <td className="font-mono text-xs">
                                                {transaction.reference || '—'}
                                            </td>
                                            <td
                                                className={`text-right font-semibold ${Number(transaction.amount) >= 0 ? 'text-green-700 dark:text-green-400' : 'text-foreground'}`}
                                            >
                                                {money(
                                                    Number(transaction.amount),
                                                    selectedAccount.currency,
                                                )}
                                            </td>
                                            <td>
                                                <Badge variant="outline">
                                                    {transaction.status}
                                                </Badge>
                                            </td>
                                            <td>
                                                {transaction.transfer &&
                                                !transaction.transfer
                                                    .reversed_at ? (
                                                    <Form
                                                        action={`/banking/transfers/${transaction.transfer.id}/reverse`}
                                                        method="post"
                                                    >
                                                        <Button
                                                            size="sm"
                                                            variant="outline"
                                                        >
                                                            Undo own transfer
                                                        </Button>
                                                    </Form>
                                                ) : transaction.posting &&
                                                  !transaction.posting
                                                      .reversed_at ? (
                                                    <Form
                                                        action={`/banking/postings/${transaction.posting.id}/reverse`}
                                                        method="post"
                                                        className="flex items-center gap-2"
                                                    >
                                                        <span className="text-xs">
                                                            <span className="font-mono">
                                                                {
                                                                    transaction
                                                                        .posting
                                                                        .counterpart
                                                                        .code
                                                                }
                                                            </span>{' '}
                                                            {
                                                                transaction
                                                                    .posting
                                                                    .counterpart
                                                                    .name
                                                            }
                                                        </span>
                                                        <Button
                                                            size="sm"
                                                            variant="outline"
                                                        >
                                                            Undo posting
                                                        </Button>
                                                    </Form>
                                                ) : transaction.reconciliation ? (
                                                    <Form
                                                        action={`/banking/reconciliations/${transaction.reconciliation.id}`}
                                                        method="delete"
                                                    >
                                                        <Button
                                                            size="sm"
                                                            variant="outline"
                                                        >
                                                            Undo{' '}
                                                            {
                                                                transaction
                                                                    .reconciliation
                                                                    .method
                                                            }
                                                        </Button>
                                                    </Form>
                                                ) : transaction.candidates
                                                      .length ? (
                                                    <Form
                                                        action={`/banking/transactions/${transaction.id}/reconcile`}
                                                        method="post"
                                                        className="flex gap-2"
                                                    >
                                                        <Select
                                                            name="payment"
                                                            defaultValue={
                                                                transaction
                                                                    .candidates[0]
                                                                    .value
                                                            }
                                                        >
                                                            <SelectTrigger className="min-w-72">
                                                                <SelectValue />
                                                            </SelectTrigger>
                                                            <SelectContent>
                                                                {transaction.candidates.map(
                                                                    (
                                                                        candidate,
                                                                    ) => (
                                                                        <SelectItem
                                                                            key={
                                                                                candidate.value
                                                                            }
                                                                            value={
                                                                                candidate.value
                                                                            }
                                                                        >
                                                                            {candidate.reference_match
                                                                                ? 'Exact ref · '
                                                                                : ''}
                                                                            {candidate.creates_payment
                                                                                ? 'New payment · '
                                                                                : ''}
                                                                            {
                                                                                candidate.confidence
                                                                            }
                                                                            % ·{' '}
                                                                            {
                                                                                candidate.label
                                                                            }
                                                                        </SelectItem>
                                                                    ),
                                                                )}
                                                            </SelectContent>
                                                        </Select>
                                                        <Button size="sm">
                                                            Match
                                                        </Button>
                                                    </Form>
                                                ) : (
                                                    <Form
                                                        action={`/banking/transactions/${transaction.id}/post`}
                                                        method="post"
                                                        className="flex gap-2"
                                                    >
                                                        <Input
                                                            name="counterpart_account"
                                                            list="ledger-account-options"
                                                            className="min-w-64"
                                                            placeholder="Account code or name"
                                                            required
                                                        />
                                                        <Button
                                                            size="sm"
                                                            variant="outline"
                                                        >
                                                            Post
                                                        </Button>
                                                    </Form>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                            {transactions.data.length === 0 && (
                                <div className="py-12 text-center text-sm text-muted-foreground">
                                    No bank transactions imported.
                                </div>
                            )}
                        </TableViewport>
                        <PaginationBar
                            links={transactions.links}
                            total={transactions.total}
                            noun="bank transactions"
                        />
                    </>
                )}
            </div>
        </>
    );
}
function AccountDialog() {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button>
                    <Plus /> Bank account
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>New bank account</DialogTitle>
                    <DialogDescription>
                        Add the account used to import and reconcile bank
                        movements.
                    </DialogDescription>
                </DialogHeader>
                <Form
                    action="/banking/accounts"
                    method="post"
                    onSuccess={() => setOpen(false)}
                    className="grid gap-4"
                >
                    <Field label="Account name" name="name" />
                    <Field label="Bank" name="bank_name" />
                    <Field label="IBAN" name="iban" />
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field label="Currency" name="currency" value="EUR" />
                        <Field
                            label="Opening balance"
                            name="opening_balance"
                            type="number"
                            value="0"
                        />
                    </div>
                    <label className="flex items-center gap-2 text-sm">
                        <input type="hidden" name="is_active" value="0" />
                        <Checkbox name="is_active" value="1" defaultChecked />
                        Active
                    </label>
                    <Button>
                        <Landmark /> Create account
                    </Button>
                </Form>
            </DialogContent>
        </Dialog>
    );
}
function Field({
    label,
    name,
    type = 'text',
    value,
}: {
    label: string;
    name: string;
    type?: string;
    value?: string;
}) {
    return (
        <div className="grid gap-2">
            <Label>{label}</Label>
            <Input
                name={name}
                type={type}
                step={type === 'number' ? '0.01' : undefined}
                defaultValue={value}
            />
        </div>
    );
}
function Metric({ label, value }: { label: string; value: string }) {
    return (
        <div className="border-r p-4 last:border-0">
            <div className="text-sm text-muted-foreground">{label}</div>
            <div className="mt-1 text-xl font-semibold">{value}</div>
        </div>
    );
}
const money = (value: number, currency: string) =>
    new Intl.NumberFormat(undefined, { style: 'currency', currency }).format(
        value,
    );
const date = (value: string) =>
    new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(
        new Date(`${value.slice(0, 10)}T12:00:00`),
    );
BankingIndex.layout = {
    breadcrumbs: [
        { title: 'Finance', href: '/finance' },
        { title: 'Banking', href: '/banking' },
    ],
};

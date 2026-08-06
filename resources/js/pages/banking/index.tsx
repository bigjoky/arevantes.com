import { Form, Head, Link } from '@inertiajs/react';
import {
    ArrowLeft,
    CreditCard,
    Landmark,
    Link2,
    Pencil,
    Plus,
    RefreshCw,
    Upload,
    WalletCards,
} from 'lucide-react';
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
import { Textarea } from '@/components/ui/textarea';
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
type LiabilityAccount = { id: number; code: string; name: string };
type FinancialProduct = {
    id: number;
    bank_account_id: number;
    shared_credit_facility_id: number | null;
    ledger_account_id: number;
    type: 'credit_card' | 'credit_facility';
    name: string;
    provider: string | null;
    currency: string;
    identifier_last_four: string | null;
    contract_reference: string | null;
    credit_limit: string;
    used_amount: string;
    available_amount: number;
    utilization: number;
    annual_interest_rate: string | null;
    annual_fee: string;
    billing_day: number | null;
    payment_day: number | null;
    starts_on: string | null;
    expires_on: string | null;
    notes: string | null;
    is_active: boolean;
    expires_soon: boolean;
    expired: boolean;
    bank_account: { name: string };
    ledger_account: { code: string; name: string } | null;
    shared_credit_facility: {
        id: number;
        name: string;
        credit_limit: string;
        used_amount: string;
        currency: string;
    } | null;
    linked_cards: { id: number; name: string; used_amount: string }[];
};
type ProductSummary = {
    currency: string;
    limit: number;
    used: number;
    available: number;
};
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
    liabilityAccounts,
    products,
    productSummary,
    control,
    summary,
    filters,
}: {
    accounts: Account[];
    selectedAccount: Account | null;
    transactions: TransactionPage;
    imports: StatementImport[];
    ledgerAccounts: LedgerAccount[];
    liabilityAccounts: LiabilityAccount[];
    products: FinancialProduct[];
    productSummary: ProductSummary[];
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
                <FinancialProducts
                    products={products}
                    summaries={productSummary}
                    accounts={accounts}
                    liabilityAccounts={liabilityAccounts}
                />
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

function FinancialProducts({
    products,
    summaries,
    accounts,
    liabilityAccounts,
}: {
    products: FinancialProduct[];
    summaries: ProductSummary[];
    accounts: Account[];
    liabilityAccounts: LiabilityAccount[];
}) {
    return (
        <section className="grid gap-4 rounded-lg border p-4">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 className="font-semibold">
                        Credit cards and facilities
                    </h2>
                    <p className="text-sm text-muted-foreground">
                        Control limits, drawn balances, available credit and
                        contract maturities.
                    </p>
                </div>
                <ProductDialog
                    accounts={accounts}
                    liabilityAccounts={liabilityAccounts}
                    facilities={products.filter(
                        (product) =>
                            product.type === 'credit_facility' &&
                            product.is_active,
                    )}
                />
                <SharedCreditDialog
                    cards={products.filter(
                        (product) =>
                            product.type === 'credit_card' &&
                            product.is_active &&
                            !product.shared_credit_facility_id,
                    )}
                />
            </div>
            {summaries.length > 0 && (
                <div className="grid gap-px overflow-hidden rounded-md border bg-border md:grid-cols-3">
                    {summaries.flatMap((summary) => [
                        <ProductMetric
                            key={`${summary.currency}-limit`}
                            label={`Total limit · ${summary.currency}`}
                            value={money(summary.limit, summary.currency)}
                        />,
                        <ProductMetric
                            key={`${summary.currency}-used`}
                            label={`Drawn / used · ${summary.currency}`}
                            value={money(summary.used, summary.currency)}
                        />,
                        <ProductMetric
                            key={`${summary.currency}-available`}
                            label={`Available · ${summary.currency}`}
                            value={money(summary.available, summary.currency)}
                        />,
                    ])}
                </div>
            )}
            <div className="grid gap-3 lg:grid-cols-2">
                {products.map((product) => (
                    <article
                        key={product.id}
                        className={`rounded-md border p-4 ${product.is_active ? '' : 'opacity-60'}`}
                    >
                        <div className="flex items-start justify-between gap-3">
                            <div>
                                <div className="flex flex-wrap items-center gap-2">
                                    {product.type === 'credit_card' ? (
                                        <CreditCard className="size-4" />
                                    ) : (
                                        <WalletCards className="size-4" />
                                    )}
                                    <h3 className="font-medium">
                                        {product.name}
                                    </h3>
                                    <Badge variant="outline">
                                        {product.type === 'credit_card'
                                            ? 'Credit card'
                                            : 'Credit facility'}
                                    </Badge>
                                    {product.expired && (
                                        <Badge variant="destructive">
                                            Expired
                                        </Badge>
                                    )}
                                    {product.shared_credit_facility && (
                                        <Badge variant="secondary">
                                            Shared ·{' '}
                                            {
                                                product.shared_credit_facility
                                                    .name
                                            }
                                        </Badge>
                                    )}
                                    {!product.expired &&
                                        product.expires_soon && (
                                            <Badge variant="secondary">
                                                Expires soon
                                            </Badge>
                                        )}
                                </div>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    {product.provider ?? 'Bank product'}
                                    {product.identifier_last_four
                                        ? ` · •••• ${product.identifier_last_four}`
                                        : ''}
                                    {product.contract_reference
                                        ? ` · ${product.contract_reference}`
                                        : ''}
                                </p>
                            </div>
                            <ProductDialog
                                product={product}
                                accounts={accounts}
                                liabilityAccounts={liabilityAccounts}
                                facilities={products.filter(
                                    (candidate) =>
                                        candidate.type === 'credit_facility' &&
                                        candidate.is_active &&
                                        candidate.id !== product.id,
                                )}
                            />
                        </div>
                        <div className="mt-4 grid grid-cols-3 gap-3 text-sm">
                            <ProductValue
                                label={
                                    product.shared_credit_facility
                                        ? 'Shared limit'
                                        : 'Limit'
                                }
                                value={money(
                                    Number(
                                        product.shared_credit_facility
                                            ?.credit_limit ??
                                            product.credit_limit,
                                    ),
                                    product.currency,
                                )}
                            />
                            <ProductValue
                                label={
                                    product.shared_credit_facility
                                        ? 'This card'
                                        : 'Used'
                                }
                                value={money(
                                    Number(product.used_amount),
                                    product.currency,
                                )}
                            />
                            <ProductValue
                                label="Available"
                                value={money(
                                    product.available_amount,
                                    product.currency,
                                )}
                            />
                        </div>
                        <div className="mt-3 h-2 overflow-hidden rounded-full bg-muted">
                            <div
                                className={`h-full ${product.utilization >= 90 ? 'bg-destructive' : product.utilization >= 70 ? 'bg-amber-500' : 'bg-primary'}`}
                                style={{
                                    width: `${Math.min(100, product.utilization)}%`,
                                }}
                            />
                        </div>
                        <div className="mt-2 flex flex-wrap justify-between gap-2 text-xs text-muted-foreground">
                            <span>{product.utilization}% utilized</span>
                            <span>
                                Settlement: {product.bank_account.name}
                                {product.ledger_account
                                    ? ` · ${product.ledger_account.code}`
                                    : ''}
                            </span>
                            {product.expires_on && (
                                <span>
                                    Maturity: {date(product.expires_on)}
                                </span>
                            )}
                            {product.linked_cards.length > 0 && (
                                <span>
                                    Shared by:{' '}
                                    {product.linked_cards
                                        .map((card) => card.name)
                                        .join(', ')}
                                </span>
                            )}
                        </div>
                    </article>
                ))}
                {products.length === 0 && (
                    <p className="py-6 text-center text-sm text-muted-foreground lg:col-span-2">
                        Add credit cards and credit facilities to include them
                        in the daily treasury position.
                    </p>
                )}
            </div>
        </section>
    );
}

function SharedCreditDialog({ cards }: { cards: FinancialProduct[] }) {
    const [open, setOpen] = useState(false);
    const [selected, setSelected] = useState<number[]>([]);
    const suggestedLimit = Math.max(
        0,
        ...cards.map((card) => Number(card.credit_limit)),
    );

    const toggle = (cardId: number) =>
        setSelected((current) =>
            current.includes(cardId)
                ? current.filter((id) => id !== cardId)
                : [...current, cardId],
        );

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="outline" disabled={cards.length < 2}>
                    <Link2 /> Create shared limit
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Create shared card credit</DialogTitle>
                    <DialogDescription>
                        Select the cards that draw from the same bank limit. The
                        limit will count only once in treasury.
                    </DialogDescription>
                </DialogHeader>
                <Form
                    action="/banking/shared-credit-facilities"
                    method="post"
                    className="grid gap-4"
                    onSuccess={() => {
                        setOpen(false);
                        setSelected([]);
                    }}
                >
                    {({ errors, processing }) => (
                        <>
                            {selected.map((cardId) => (
                                <input
                                    key={cardId}
                                    type="hidden"
                                    name="card_ids[]"
                                    value={cardId}
                                />
                            ))}
                            <ProductField label="Shared facility name">
                                <Input
                                    name="name"
                                    defaultValue="Shared corporate card credit"
                                    required
                                />
                            </ProductField>
                            <div className="grid gap-3 sm:grid-cols-2">
                                <ProductField label="Single shared limit">
                                    <Input
                                        name="credit_limit"
                                        type="number"
                                        min="0.01"
                                        step="0.01"
                                        defaultValue={suggestedLimit || ''}
                                        required
                                    />
                                </ProductField>
                                <ProductField label="Bank reference">
                                    <Input name="contract_reference" />
                                </ProductField>
                            </div>
                            <div className="grid gap-2">
                                <Label>Select at least two cards</Label>
                                {cards.map((card) => (
                                    <label
                                        key={card.id}
                                        className={`flex cursor-pointer items-center justify-between rounded-md border p-3 text-left ${selected.includes(card.id) ? 'border-primary bg-primary/5' : ''}`}
                                    >
                                        <span className="flex items-center gap-2">
                                            <input
                                                type="checkbox"
                                                checked={selected.includes(
                                                    card.id,
                                                )}
                                                onChange={() => toggle(card.id)}
                                                className="size-4 rounded border-input accent-primary"
                                            />
                                            <span>
                                                <span className="block text-sm font-medium">
                                                    {card.name}
                                                </span>
                                                <span className="block text-xs text-muted-foreground">
                                                    {card.identifier_last_four
                                                        ? `•••• ${card.identifier_last_four} · `
                                                        : ''}
                                                    {money(
                                                        Number(
                                                            card.used_amount,
                                                        ),
                                                        card.currency,
                                                    )}{' '}
                                                    used
                                                </span>
                                            </span>
                                        </span>
                                        <span className="text-sm">
                                            {money(
                                                Number(card.credit_limit),
                                                card.currency,
                                            )}
                                        </span>
                                    </label>
                                ))}
                            </div>
                            {Object.keys(errors).length > 0 && (
                                <p className="text-sm text-destructive">
                                    {Object.values(errors)[0]}
                                </p>
                            )}
                            <Button
                                disabled={processing || selected.length < 2}
                            >
                                <Link2 /> Link selected cards
                            </Button>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function ProductDialog({
    product,
    accounts,
    liabilityAccounts,
    facilities,
}: {
    product?: FinancialProduct;
    accounts: Account[];
    liabilityAccounts: LiabilityAccount[];
    facilities: FinancialProduct[];
}) {
    const [open, setOpen] = useState(false);
    const [type, setType] = useState<string>(product?.type ?? 'credit_card');
    const [sharedFacility, setSharedFacility] = useState<string>(
        product?.shared_credit_facility_id
            ? String(product.shared_credit_facility_id)
            : 'independent',
    );
    const defaultLiability =
        product?.ledger_account_id ??
        liabilityAccounts.find((account) => account.code === '5201')?.id ??
        liabilityAccounts[0]?.id;

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                {product ? (
                    <Button variant="ghost" size="icon" title="Edit product">
                        <Pencil />
                    </Button>
                ) : (
                    <Button disabled={accounts.length === 0}>
                        <Plus /> Financial product
                    </Button>
                )}
            </DialogTrigger>
            <DialogContent className="max-h-[90vh] max-w-2xl overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>
                        {product ? 'Edit' : 'New'} financial product
                    </DialogTitle>
                    <DialogDescription>
                        Store only the last four card digits. Never enter a full
                        card number, PIN or CVV.
                    </DialogDescription>
                </DialogHeader>
                <Form
                    action={
                        product
                            ? `/banking/products/${product.id}`
                            : '/banking/products'
                    }
                    method={product ? 'patch' : 'post'}
                    onSuccess={() => setOpen(false)}
                    className="grid gap-4"
                >
                    {({ errors, processing }) => (
                        <>
                            <div className="grid gap-3 sm:grid-cols-2">
                                <ProductField label="Product type">
                                    <Select
                                        name="type"
                                        value={type}
                                        onValueChange={setType}
                                    >
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="credit_card">
                                                Credit card
                                            </SelectItem>
                                            <SelectItem value="credit_facility">
                                                Credit facility
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </ProductField>
                                <ProductField label="Name">
                                    <Input
                                        name="name"
                                        defaultValue={product?.name}
                                        required
                                    />
                                </ProductField>
                                <ProductField label="Bank / issuer">
                                    <Input
                                        name="provider"
                                        defaultValue={product?.provider ?? ''}
                                    />
                                </ProductField>
                                <ProductField label="Settlement account">
                                    <Select
                                        name="bank_account_id"
                                        defaultValue={String(
                                            product?.bank_account_id ??
                                                accounts[0]?.id,
                                        )}
                                    >
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {accounts.map((account) => (
                                                <SelectItem
                                                    key={account.id}
                                                    value={String(account.id)}
                                                >
                                                    {account.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </ProductField>
                                <ProductField label="Liability ledger account">
                                    <Select
                                        name="ledger_account_id"
                                        defaultValue={String(defaultLiability)}
                                    >
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {liabilityAccounts.map(
                                                (account) => (
                                                    <SelectItem
                                                        key={account.id}
                                                        value={String(
                                                            account.id,
                                                        )}
                                                    >
                                                        {account.code} ·{' '}
                                                        {account.name}
                                                    </SelectItem>
                                                ),
                                            )}
                                        </SelectContent>
                                    </Select>
                                </ProductField>
                                {type === 'credit_card' && (
                                    <ProductField label="Credit arrangement">
                                        <Select
                                            name="shared_credit_facility_id"
                                            value={sharedFacility}
                                            onValueChange={setSharedFacility}
                                        >
                                            <SelectTrigger>
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="independent">
                                                    Independent card limit
                                                </SelectItem>
                                                {facilities.map((facility) => (
                                                    <SelectItem
                                                        key={facility.id}
                                                        value={String(
                                                            facility.id,
                                                        )}
                                                    >
                                                        Shared · {facility.name}{' '}
                                                        ·{' '}
                                                        {money(
                                                            facility.available_amount,
                                                            facility.currency,
                                                        )}{' '}
                                                        available
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </ProductField>
                                )}
                                {type === 'credit_card' ? (
                                    <ProductField label="Last four digits">
                                        <Input
                                            name="identifier_last_four"
                                            inputMode="numeric"
                                            maxLength={4}
                                            pattern="[0-9]{4}"
                                            defaultValue={
                                                product?.identifier_last_four ??
                                                ''
                                            }
                                        />
                                    </ProductField>
                                ) : (
                                    <ProductField label="Contract reference">
                                        <Input
                                            name="contract_reference"
                                            defaultValue={
                                                product?.contract_reference ??
                                                ''
                                            }
                                        />
                                    </ProductField>
                                )}
                                <ProductField label="Currency">
                                    <Input
                                        name="currency"
                                        maxLength={3}
                                        defaultValue={
                                            product?.currency ?? 'EUR'
                                        }
                                        required
                                    />
                                </ProductField>
                                {(type !== 'credit_card' ||
                                    sharedFacility === 'independent') && (
                                    <ProductField label="Credit limit">
                                        <Input
                                            name="credit_limit"
                                            type="number"
                                            min="0.01"
                                            step="0.01"
                                            defaultValue={product?.credit_limit}
                                            required
                                        />
                                    </ProductField>
                                )}
                                <ProductField label="Currently used / drawn">
                                    <Input
                                        name="used_amount"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        defaultValue={
                                            product?.used_amount ?? '0'
                                        }
                                        readOnly={
                                            type === 'credit_facility' &&
                                            Boolean(
                                                product?.linked_cards.length,
                                            )
                                        }
                                        required
                                    />
                                </ProductField>
                                <ProductField label="Annual interest rate %">
                                    <Input
                                        name="annual_interest_rate"
                                        type="number"
                                        min="0"
                                        max="100"
                                        step="0.0001"
                                        defaultValue={
                                            product?.annual_interest_rate ?? ''
                                        }
                                    />
                                </ProductField>
                                <ProductField label="Annual fee">
                                    <Input
                                        name="annual_fee"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        defaultValue={
                                            product?.annual_fee ?? '0'
                                        }
                                    />
                                </ProductField>
                                <ProductField label="Billing day">
                                    <Input
                                        name="billing_day"
                                        type="number"
                                        min="1"
                                        max="31"
                                        defaultValue={
                                            product?.billing_day ?? ''
                                        }
                                    />
                                </ProductField>
                                <ProductField label="Payment day">
                                    <Input
                                        name="payment_day"
                                        type="number"
                                        min="1"
                                        max="31"
                                        defaultValue={
                                            product?.payment_day ?? ''
                                        }
                                    />
                                </ProductField>
                                <ProductField label="Start date">
                                    <Input
                                        name="starts_on"
                                        type="date"
                                        defaultValue={dateInput(
                                            product?.starts_on,
                                        )}
                                    />
                                </ProductField>
                                <ProductField label="Maturity / expiry">
                                    <Input
                                        name="expires_on"
                                        type="date"
                                        defaultValue={dateInput(
                                            product?.expires_on,
                                        )}
                                    />
                                </ProductField>
                            </div>
                            <ProductField label="Notes">
                                <Textarea
                                    name="notes"
                                    defaultValue={product?.notes ?? ''}
                                />
                            </ProductField>
                            <label className="flex items-center gap-2 text-sm">
                                <input
                                    type="hidden"
                                    name="is_active"
                                    value="0"
                                />
                                <Checkbox
                                    name="is_active"
                                    value="1"
                                    defaultChecked={product?.is_active ?? true}
                                />
                                Active
                            </label>
                            {Object.keys(errors).length > 0 && (
                                <p className="text-sm text-destructive">
                                    {Object.values(errors)[0]}
                                </p>
                            )}
                            <Button disabled={processing}>
                                {product ? 'Save product' : 'Create product'}
                            </Button>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function ProductField({
    label,
    children,
}: {
    label: string;
    children: React.ReactNode;
}) {
    return (
        <div className="grid gap-1">
            <Label>{label}</Label>
            {children}
        </div>
    );
}

function ProductMetric({ label, value }: { label: string; value: string }) {
    return (
        <div className="bg-background p-3">
            <p className="text-xs text-muted-foreground">{label}</p>
            <p className="mt-1 font-semibold">{value}</p>
        </div>
    );
}

function ProductValue({ label, value }: { label: string; value: string }) {
    return (
        <div>
            <p className="text-xs text-muted-foreground">{label}</p>
            <p className="font-medium">{value}</p>
        </div>
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
const dateInput = (value?: string | null) => value?.slice(0, 10) ?? '';
BankingIndex.layout = {
    breadcrumbs: [
        { title: 'Finance', href: '/finance' },
        { title: 'Banking', href: '/banking' },
    ],
};

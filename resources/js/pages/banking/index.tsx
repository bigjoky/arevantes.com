import { Form, Head, Link, router } from '@inertiajs/react';
import {
    ArrowLeft,
    CreditCard,
    FileSearch,
    Landmark,
    ListPlus,
    Link2,
    Pencil,
    Plus,
    RefreshCw,
    Trash2,
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
type RuleSuggestion = {
    rule_id: number;
    rule_name: string;
    rule_version: number;
    confidence: number;
    ledger_account: { id: number; code: string; name: string };
    conflict: boolean;
    alternatives: {
        rule_id: number;
        rule_name: string;
        confidence: number;
        account_code: string;
        account_name: string;
    }[];
    explanation: string;
};
type ReconciliationRule = {
    id: number;
    name: string;
    priority: number;
    direction: 'any' | 'in' | 'out';
    match_field: 'description' | 'reference' | 'either';
    match_operator: 'contains' | 'starts_with' | 'exact';
    pattern: string;
    min_amount: string | null;
    max_amount: string | null;
    confidence: number;
    is_active: boolean;
    application_count: number;
    current_version: number;
    ledger_account: { id: number; code: string; name: string };
    versions: {
        id: number;
        version: number;
        snapshot: Record<string, string | number | boolean | null>;
        change_reason: string;
        created_at: string;
        changer: { name: string } | null;
    }[];
};
type RulePreview = {
    pending: number;
    scanned: number;
    truncated: boolean;
    matched: number;
    conflicts: number;
    eligibleByConfidence: Record<string, number>;
    byRule: { rule_id: number; name: string; matched: number }[];
    details: (RuleSuggestion & {
        transaction_id: number;
        booked_on: string;
        description: string;
        reference: string | null;
        amount: number;
    })[];
};
type RuleRun = {
    id: number;
    status: string;
    minimum_confidence: number;
    matched_count: number;
    skipped_count: number;
    failed_count: number;
    completed_at: string | null;
    created_at: string;
    triggered_by: { name: string } | null;
    reversed_by: { name: string } | null;
    reversed_at: string | null;
    reversal_reason: string | null;
    posting_count: number;
    can_reverse: boolean;
};
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
    next_closing_on: string | null;
    next_payment_on: string | null;
    payment_bank_account: { id: number; name: string } | null;
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
    source_account: string | null;
    period_from: string | null;
    period_to: string | null;
    opening_balance: string | null;
    closing_balance: string | null;
    created_transactions: number;
    duplicate_transactions: number;
    control_valid: boolean | null;
    transactions_count: number;
    pending_transactions_count: number;
    processed_transactions_count: number;
    can_rollback: boolean;
    rolled_back_at: string | null;
    rollback_reason: string | null;
    user: { name: string } | null;
    rolled_back_by: { name: string } | null;
    created_at: string;
};
type StatementPreview = {
    filename: string;
    format: 'csv' | 'norma43';
    total: number;
    created: number;
    duplicates: number;
    period_from: string | null;
    period_to: string | null;
    opening_balance: number | null;
    closing_balance: number | null;
    control_valid: boolean | null;
    source_account: {
        entity: string;
        office: string;
        account: string;
    } | null;
    sample: {
        booked_on: string;
        value_on: string | null;
        amount: number;
        reference: string | null;
        description: string;
        status: 'new' | 'duplicate';
    }[];
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
    match_quality: 'ready' | 'review' | 'unmatched' | 'completed';
    recommended_candidate: Candidate | null;
    rule_suggestion: RuleSuggestion | null;
    risk_flags: {
        code: string;
        label: string;
        severity: 'high' | 'medium';
    }[];
    reconciliation: {
        id: number;
        method: string;
        reconciled_at: string;
    } | null;
    posting: {
        id: number;
        reversed_at: string | null;
        counterpart: { code: string; name: string } | null;
        allocation_data:
            | {
                  account_id: number;
                  code: string;
                  name: string;
                  amount: number;
              }[]
            | null;
    } | null;
    transfer: { id: number; reversed_at: string | null } | null;
};
type TransactionPage = {
    data: Transaction[];
    links: PaginationLink[];
    total: number;
    current_page: number;
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
    reconciliationRules,
    rulePreview,
    ruleRuns,
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
    summary: {
        pending: number;
        reconciled: number;
        pendingAmount: number;
        stale: number;
        missingReference: number;
        unusualAmount: number;
        medianAmount: number;
    };
    filters: { search: string; status: string; from: string; to: string };
    reconciliationRules: ReconciliationRule[];
    rulePreview: RulePreview | null;
    ruleRuns: RuleRun[];
}) {
    const selectionScope = [
        selectedAccount?.id ?? 'none',
        transactions.current_page,
        filters.search,
        filters.status,
        filters.from,
        filters.to,
    ].join('|');
    const [selection, setSelection] = useState<{
        scope: string;
        ids: number[];
    }>({ scope: selectionScope, ids: [] });
    const selectedTransactionIds =
        selection.scope === selectionScope ? selection.ids : [];
    const updateSelection = (
        next: number[] | ((current: number[]) => number[]),
    ) =>
        setSelection({
            scope: selectionScope,
            ids:
                typeof next === 'function'
                    ? next(selectedTransactionIds)
                    : next,
        });
    const pendingOnPage = transactions.data.filter(
        (transaction) => transaction.status === 'pending',
    );
    const selectedTransactions = transactions.data.filter((transaction) =>
        selectedTransactionIds.includes(transaction.id),
    );
    const allPendingSelected =
        pendingOnPage.length > 0 &&
        pendingOnPage.every((transaction) =>
            selectedTransactionIds.includes(transaction.id),
        );

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
                        <section className="grid grid-cols-2 border-y md:grid-cols-3 xl:grid-cols-6">
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
                            <Metric
                                label="Pending over 7 days"
                                value={String(summary.stale)}
                            />
                            <Metric
                                label="Missing reference"
                                value={String(summary.missingReference)}
                            />
                            <Metric
                                label="Unusual amount"
                                value={String(summary.unusualAmount)}
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
                            <StatementImportPanel account={selectedAccount} />
                            <Form
                                action={`/banking/accounts/${selectedAccount.id}/auto-reconcile`}
                                method="post"
                                className="grid gap-2 rounded-md border bg-muted/20 p-3 sm:grid-cols-[1fr_12rem_auto] sm:items-end"
                            >
                                <div>
                                    <div className="text-sm font-medium">
                                        Assisted bulk reconciliation
                                    </div>
                                    <div className="text-xs text-muted-foreground">
                                        Only applies the best candidate when it
                                        clears the confidence threshold and is
                                        at least 10 points above the next one.
                                    </div>
                                </div>
                                <div className="grid gap-2">
                                    <Label>Minimum confidence</Label>
                                    <Select
                                        name="minimum_confidence"
                                        defaultValue="95"
                                    >
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="98">
                                                98% · Very strict
                                            </SelectItem>
                                            <SelectItem value="95">
                                                95% · Recommended
                                            </SelectItem>
                                            <SelectItem value="90">
                                                90% · Assisted
                                            </SelectItem>
                                            <SelectItem value="80">
                                                80% · Broad review
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <Button variant="outline">
                                    <RefreshCw /> Reconcile confident matches
                                </Button>
                            </Form>
                        </section>
                        {rulePreview && (
                            <ReconciliationRules
                                account={selectedAccount}
                                ledgerAccounts={ledgerAccounts}
                                rules={reconciliationRules}
                                preview={rulePreview}
                                runs={ruleRuns}
                            />
                        )}
                        {imports.length > 0 && (
                            <details className="group rounded-md border">
                                <summary className="flex cursor-pointer list-none items-center justify-between gap-3 px-4 py-3 text-sm font-semibold">
                                    <span>Recent statement imports</span>
                                    <span className="text-xs font-normal text-muted-foreground">
                                        {imports.length} imports · click to
                                        expand
                                    </span>
                                </summary>
                                <TableViewport className="border-t">
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
                                                    Already imported
                                                </th>
                                                <th>Control</th>
                                                <th className="text-right">
                                                    Closing balance
                                                </th>
                                                <th>Status</th>
                                                <th className="text-right">
                                                    Action
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
                                                        {item.source_account && (
                                                            <span className="block text-xs text-muted-foreground">
                                                                Account{' '}
                                                                {
                                                                    item.source_account
                                                                }
                                                            </span>
                                                        )}
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
                                                        {
                                                            item.created_transactions
                                                        }
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
                                                    <td>
                                                        {item.rolled_back_at ? (
                                                            <span
                                                                className="text-xs text-muted-foreground"
                                                                title={
                                                                    item.rollback_reason ??
                                                                    undefined
                                                                }
                                                            >
                                                                Rolled back by{' '}
                                                                {item
                                                                    .rolled_back_by
                                                                    ?.name ??
                                                                    'user'}
                                                            </span>
                                                        ) : (
                                                            <span className="text-xs">
                                                                {item.user
                                                                    ?.name ??
                                                                    'User'}{' '}
                                                                ·{' '}
                                                                {date(
                                                                    item.created_at,
                                                                )}
                                                            </span>
                                                        )}
                                                    </td>
                                                    <td className="text-right">
                                                        {item.can_rollback ? (
                                                            <ImportRollbackDialog
                                                                statementImport={
                                                                    item
                                                                }
                                                            />
                                                        ) : !item.rolled_back_at &&
                                                          item.created_transactions >
                                                              0 ? (
                                                            <span className="text-xs text-muted-foreground">
                                                                In use
                                                            </span>
                                                        ) : (
                                                            '—'
                                                        )}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </TableViewport>
                            </details>
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
                        {selectedTransactions.length > 0 && selectedAccount && (
                            <div className="flex flex-wrap items-center justify-between gap-3 rounded-md border bg-muted/30 px-3 py-2">
                                <div className="text-sm">
                                    <span className="font-medium">
                                        {selectedTransactions.length} selected
                                    </span>{' '}
                                    <span className="text-muted-foreground">
                                        · gross{' '}
                                        {money(
                                            selectedTransactions.reduce(
                                                (sum, transaction) =>
                                                    sum +
                                                    Math.abs(
                                                        Number(
                                                            transaction.amount,
                                                        ),
                                                    ),
                                                0,
                                            ),
                                            selectedAccount.currency,
                                        )}
                                    </span>
                                </div>
                                <div className="flex gap-2">
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="ghost"
                                        onClick={() => updateSelection([])}
                                    >
                                        Clear selection
                                    </Button>
                                    <BulkPostingDialog
                                        account={selectedAccount}
                                        transactions={selectedTransactions}
                                        ledgerAccounts={ledgerAccounts}
                                        onSuccess={() => updateSelection([])}
                                    />
                                </div>
                            </div>
                        )}
                        <TableViewport>
                            <table className="w-full min-w-[70rem] text-sm">
                                <thead>
                                    <tr className="border-b text-left text-muted-foreground">
                                        <th className="w-10 py-2">
                                            <Checkbox
                                                aria-label="Select all pending movements on this page"
                                                checked={
                                                    allPendingSelected
                                                        ? true
                                                        : selectedTransactionIds.length >
                                                            0
                                                          ? 'indeterminate'
                                                          : false
                                                }
                                                disabled={
                                                    pendingOnPage.length === 0
                                                }
                                                onCheckedChange={(checked) =>
                                                    updateSelection(
                                                        checked === true
                                                            ? pendingOnPage.map(
                                                                  (
                                                                      transaction,
                                                                  ) =>
                                                                      transaction.id,
                                                              )
                                                            : [],
                                                    )
                                                }
                                            />
                                        </th>
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
                                                {transaction.status ===
                                                    'pending' && (
                                                    <Checkbox
                                                        aria-label={`Select ${transaction.description}`}
                                                        checked={selectedTransactionIds.includes(
                                                            transaction.id,
                                                        )}
                                                        onCheckedChange={(
                                                            checked,
                                                        ) =>
                                                            updateSelection(
                                                                (current) =>
                                                                    checked ===
                                                                    true
                                                                        ? [
                                                                              ...current,
                                                                              transaction.id,
                                                                          ]
                                                                        : current.filter(
                                                                              (
                                                                                  id,
                                                                              ) =>
                                                                                  id !==
                                                                                  transaction.id,
                                                                          ),
                                                            )
                                                        }
                                                    />
                                                )}
                                            </td>
                                            <td className="py-3">
                                                {date(transaction.booked_on)}
                                            </td>
                                            <td>
                                                <div className="max-w-sm font-medium">
                                                    {transaction.description}
                                                </div>
                                                {transaction.risk_flags.length >
                                                    0 && (
                                                    <div className="mt-1 flex max-w-sm flex-wrap gap-1">
                                                        {transaction.risk_flags.map(
                                                            (flag) => (
                                                                <Badge
                                                                    key={
                                                                        flag.code
                                                                    }
                                                                    variant={
                                                                        flag.severity ===
                                                                        'high'
                                                                            ? 'destructive'
                                                                            : 'secondary'
                                                                    }
                                                                    title={
                                                                        flag.label
                                                                    }
                                                                >
                                                                    {flag.label}
                                                                </Badge>
                                                            ),
                                                        )}
                                                    </div>
                                                )}
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
                                                <div className="grid justify-items-start gap-1">
                                                    <Badge variant="outline">
                                                        {transaction.status}
                                                    </Badge>
                                                    {transaction.status ===
                                                        'pending' && (
                                                        <Badge
                                                            variant={
                                                                transaction.match_quality ===
                                                                'ready'
                                                                    ? 'secondary'
                                                                    : 'outline'
                                                            }
                                                        >
                                                            {transaction.match_quality ===
                                                            'ready'
                                                                ? `Ready · ${transaction.recommended_candidate?.confidence}%`
                                                                : transaction.match_quality ===
                                                                    'review'
                                                                  ? `Review · ${transaction.recommended_candidate?.confidence}%`
                                                                  : 'No suggested match'}
                                                        </Badge>
                                                    )}
                                                    {transaction.status ===
                                                        'pending' &&
                                                        transaction.rule_suggestion && (
                                                            <Badge
                                                                variant={
                                                                    transaction
                                                                        .rule_suggestion
                                                                        .conflict
                                                                        ? 'destructive'
                                                                        : 'secondary'
                                                                }
                                                                title={
                                                                    transaction
                                                                        .rule_suggestion
                                                                        .explanation
                                                                }
                                                            >
                                                                {transaction
                                                                    .rule_suggestion
                                                                    .conflict
                                                                    ? `Rule v${transaction.rule_suggestion.rule_version} conflict · `
                                                                    : `Rule v${transaction.rule_suggestion.rule_version} · `}
                                                                {
                                                                    transaction
                                                                        .rule_suggestion
                                                                        .confidence
                                                                }
                                                                %
                                                            </Badge>
                                                        )}
                                                </div>
                                            </td>
                                            <td>
                                                {transaction.status ===
                                                    'pending' &&
                                                    transaction.rule_suggestion && (
                                                        <div className="mb-2 rounded-md border border-dashed p-2 text-xs">
                                                            <div className="font-medium">
                                                                {
                                                                    transaction
                                                                        .rule_suggestion
                                                                        .rule_name
                                                                }
                                                                {' · v'}
                                                                {
                                                                    transaction
                                                                        .rule_suggestion
                                                                        .rule_version
                                                                }
                                                            </div>
                                                            <div className="text-muted-foreground">
                                                                {transaction
                                                                    .rule_suggestion
                                                                    .conflict
                                                                    ? 'Blocked: competing accounts include '
                                                                    : 'Post to '}
                                                                <span className="font-mono">
                                                                    {
                                                                        transaction
                                                                            .rule_suggestion
                                                                            .ledger_account
                                                                            .code
                                                                    }
                                                                </span>{' '}
                                                                {
                                                                    transaction
                                                                        .rule_suggestion
                                                                        .ledger_account
                                                                        .name
                                                                }
                                                            </div>
                                                            {transaction
                                                                .rule_suggestion
                                                                .alternatives
                                                                .length > 0 && (
                                                                <div className="mt-1 text-muted-foreground">
                                                                    Also
                                                                    matched:{' '}
                                                                    {transaction.rule_suggestion.alternatives
                                                                        .map(
                                                                            (
                                                                                alternative,
                                                                            ) =>
                                                                                `${alternative.rule_name} → ${alternative.account_code}`,
                                                                        )
                                                                        .join(
                                                                            ', ',
                                                                        )}
                                                                </div>
                                                            )}
                                                        </div>
                                                    )}
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
                                                            {(transaction
                                                                .posting
                                                                .allocation_data
                                                                ?.length ?? 0) >
                                                            1 ? (
                                                                <>
                                                                    Split across{' '}
                                                                    {
                                                                        transaction
                                                                            .posting
                                                                            .allocation_data
                                                                            ?.length
                                                                    }{' '}
                                                                    accounts
                                                                </>
                                                            ) : (
                                                                <>
                                                                    <span className="font-mono">
                                                                        {
                                                                            transaction
                                                                                .posting
                                                                                .counterpart
                                                                                ?.code
                                                                        }
                                                                    </span>{' '}
                                                                    {
                                                                        transaction
                                                                            .posting
                                                                            .counterpart
                                                                            ?.name
                                                                    }
                                                                </>
                                                            )}
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
                                                    <div className="flex flex-wrap gap-2">
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

                                                                                %
                                                                                ·{' '}
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
                                                        <PostingDialog
                                                            transaction={
                                                                transaction
                                                            }
                                                            currency={
                                                                selectedAccount.currency
                                                            }
                                                            ledgerAccounts={
                                                                ledgerAccounts
                                                            }
                                                        />
                                                    </div>
                                                ) : (
                                                    <PostingDialog
                                                        transaction={
                                                            transaction
                                                        }
                                                        currency={
                                                            selectedAccount.currency
                                                        }
                                                        ledgerAccounts={
                                                            ledgerAccounts
                                                        }
                                                    />
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

function BulkPostingDialog({
    account,
    transactions,
    ledgerAccounts,
    onSuccess,
}: {
    account: Account;
    transactions: Transaction[];
    ledgerAccounts: LedgerAccount[];
    onSuccess: () => void;
}) {
    const [open, setOpen] = useState(false);
    const [accountCode, setAccountCode] = useState('');
    const existingAccount = ledgerAccounts.find(
        (ledgerAccount) => ledgerAccount.code === accountCode,
    );
    const incoming = transactions.filter(
        (transaction) => Number(transaction.amount) > 0,
    );
    const outgoing = transactions.filter(
        (transaction) => Number(transaction.amount) < 0,
    );
    const gross = transactions.reduce(
        (sum, transaction) => sum + Math.abs(Number(transaction.amount)),
        0,
    );
    const transactionDates = transactions
        .map((transaction) => transaction.booked_on)
        .sort();

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm">
                    <ListPlus /> Bulk post
                </Button>
            </DialogTrigger>
            <DialogContent className="max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Post selected bank movements</DialogTitle>
                    <DialogDescription>
                        Create one traceable accounting entry per movement,
                        using the same counterpart account. The complete batch
                        is atomic: if one movement fails, none are posted.
                    </DialogDescription>
                </DialogHeader>
                <div className="grid grid-cols-2 rounded-md border sm:grid-cols-4">
                    <Metric
                        label="Movements"
                        value={String(transactions.length)}
                    />
                    <Metric label="Incoming" value={String(incoming.length)} />
                    <Metric label="Outgoing" value={String(outgoing.length)} />
                    <Metric
                        label="Gross amount"
                        value={money(gross, account.currency)}
                    />
                </div>
                <Form
                    action={`/banking/accounts/${account.id}/transactions/bulk-post`}
                    method="post"
                    className="grid gap-4"
                    onSuccess={() => {
                        setOpen(false);
                        setAccountCode('');
                        onSuccess();
                    }}
                >
                    {({ errors, processing }) => (
                        <>
                            {transactions.map((transaction) => (
                                <input
                                    key={transaction.id}
                                    type="hidden"
                                    name="transaction_ids[]"
                                    value={transaction.id}
                                />
                            ))}
                            <div className="grid gap-2">
                                <Label htmlFor="bulk-account-code">
                                    Counterpart accounting code
                                </Label>
                                <Input
                                    id="bulk-account-code"
                                    name="account_code"
                                    value={accountCode}
                                    onChange={(event) =>
                                        setAccountCode(event.target.value)
                                    }
                                    list="ledger-account-options"
                                    inputMode="numeric"
                                    placeholder="626"
                                    pattern="[0-9]{3,12}"
                                    required
                                />
                                {existingAccount && (
                                    <p className="text-xs text-muted-foreground">
                                        {existingAccount.name}
                                    </p>
                                )}
                                {errors.account_code && (
                                    <p className="text-sm text-destructive">
                                        {errors.account_code}
                                    </p>
                                )}
                            </div>
                            {/^\d{3,12}$/.test(accountCode) &&
                                !existingAccount && (
                                    <div className="grid gap-2">
                                        <Label htmlFor="bulk-account-name">
                                            New subaccount name
                                        </Label>
                                        <Input
                                            id="bulk-account-name"
                                            name="account_name"
                                            placeholder="For example: Card commissions"
                                            required
                                        />
                                        {errors.account_name && (
                                            <p className="text-sm text-destructive">
                                                {errors.account_name}
                                            </p>
                                        )}
                                    </div>
                                )}
                            {errors.transaction_ids && (
                                <p className="text-sm text-destructive">
                                    {errors.transaction_ids}
                                </p>
                            )}
                            <div className="rounded-md bg-muted px-3 py-2 text-xs text-muted-foreground">
                                Selected dates: {date(transactionDates[0])} –{' '}
                                {date(transactionDates.at(-1)!)}
                            </div>
                            <Button
                                disabled={
                                    processing ||
                                    !/^\d{3,12}$/.test(accountCode)
                                }
                            >
                                <ListPlus />
                                {processing
                                    ? 'Posting batch…'
                                    : `Post ${transactions.length} movements`}
                            </Button>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function StatementImportPanel({ account }: { account: Account }) {
    const [file, setFile] = useState<File | null>(null);
    const [preview, setPreview] = useState<StatementPreview | null>(null);
    const [previewing, setPreviewing] = useState(false);
    const [importing, setImporting] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [acceptMismatch, setAcceptMismatch] = useState(false);
    const [inputVersion, setInputVersion] = useState(0);

    const previewStatement = async () => {
        if (!file || previewing) {
            return;
        }

        setPreviewing(true);
        setPreview(null);
        setError(null);
        setAcceptMismatch(false);

        try {
            const body = new FormData();
            body.append('statement', file);
            const token = document
                .querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
                ?.getAttribute('content');
            const response = await fetch(
                `/banking/accounts/${account.id}/import/preview`,
                {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        ...(token ? { 'X-CSRF-TOKEN': token } : {}),
                    },
                    body,
                },
            );
            const payload = (await response.json()) as
                | StatementPreview
                | { message?: string; errors?: Record<string, string[]> };

            if (!response.ok) {
                const validation =
                    'errors' in payload
                        ? Object.values(payload.errors ?? {}).flat()[0]
                        : undefined;

                throw new Error(
                    validation ||
                        ('message' in payload ? payload.message : undefined) ||
                        'The statement could not be previewed.',
                );
            }

            setPreview(payload as StatementPreview);
        } catch (reason) {
            setError(
                reason instanceof Error
                    ? reason.message
                    : 'The statement could not be previewed.',
            );
        } finally {
            setPreviewing(false);
        }
    };

    const importStatement = () => {
        if (
            !file ||
            !preview ||
            preview.created === 0 ||
            (preview.control_valid === false && !acceptMismatch)
        ) {
            return;
        }

        router.post(
            `/banking/accounts/${account.id}/import`,
            { statement: file },
            {
                forceFormData: true,
                preserveScroll: true,
                onStart: () => setImporting(true),
                onFinish: () => setImporting(false),
                onSuccess: () => {
                    setFile(null);
                    setPreview(null);
                    setError(null);
                    setAcceptMismatch(false);
                    setInputVersion((current) => current + 1);
                },
                onError: (errors) =>
                    setError(
                        String(
                            errors.statement ??
                                'The statement changed and could not be imported.',
                        ),
                    ),
            },
        );
    };

    return (
        <div className="grid gap-3 rounded-md border bg-muted/20 p-3">
            <div>
                <div className="text-sm font-medium">
                    Preview statement import
                </div>
                <div className="text-xs text-muted-foreground">
                    Validate the file and inspect duplicates before creating any
                    bank movement.
                </div>
            </div>
            <div className="flex flex-wrap items-end gap-3">
                <div className="grid min-w-64 flex-1 gap-2">
                    <Label htmlFor={`statement-${account.id}`}>
                        Statement CSV or Norma 43
                    </Label>
                    <Input
                        key={inputVersion}
                        id={`statement-${account.id}`}
                        type="file"
                        accept=".csv,.txt,.n43,.aeb,text/csv,text/plain"
                        onChange={(event) => {
                            setFile(event.target.files?.[0] ?? null);
                            setPreview(null);
                            setError(null);
                            setAcceptMismatch(false);
                        }}
                    />
                </div>
                <Button
                    type="button"
                    variant="outline"
                    disabled={!file || previewing || importing}
                    onClick={() => void previewStatement()}
                >
                    <FileSearch />
                    {previewing ? 'Checking…' : 'Preview'}
                </Button>
            </div>
            {error && <p className="text-sm text-destructive">{error}</p>}
            {preview && (
                <div className="grid gap-3 border-t pt-3">
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <div className="text-sm font-medium">
                                {preview.filename}
                            </div>
                            <div className="text-xs text-muted-foreground">
                                {preview.format === 'norma43'
                                    ? 'Norma 43'
                                    : 'CSV'}
                                {preview.period_from && preview.period_to
                                    ? ` · ${date(preview.period_from)} – ${date(preview.period_to)}`
                                    : ''}
                                {preview.source_account
                                    ? ` · ${preview.source_account.entity}-${preview.source_account.office}-${preview.source_account.account.slice(-4)}`
                                    : ''}
                            </div>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            <Badge variant="outline">
                                {preview.total} rows
                            </Badge>
                            <Badge>{preview.created} new</Badge>
                            <Badge variant="secondary">
                                {preview.duplicates} already imported
                            </Badge>
                        </div>
                    </div>
                    {preview.opening_balance !== null && (
                        <div className="grid grid-cols-2 rounded-md border sm:grid-cols-3">
                            <Metric
                                label="Opening balance"
                                value={money(
                                    preview.opening_balance,
                                    account.currency,
                                )}
                            />
                            <Metric
                                label="Closing balance"
                                value={money(
                                    preview.closing_balance ?? 0,
                                    account.currency,
                                )}
                            />
                            <Metric
                                label="Norma 43 control"
                                value={
                                    preview.control_valid ? 'Valid' : 'Mismatch'
                                }
                            />
                        </div>
                    )}
                    {preview.sample.length > 0 && (
                        <TableViewport>
                            <table className="w-full min-w-[38rem] text-xs">
                                <thead>
                                    <tr className="border-b text-left text-muted-foreground">
                                        <th className="py-2">Date</th>
                                        <th>Description</th>
                                        <th>Reference</th>
                                        <th className="text-right">Amount</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {preview.sample.map((movement, index) => (
                                        <tr
                                            key={`${movement.booked_on}-${movement.reference}-${index}`}
                                            className="border-b"
                                        >
                                            <td className="py-2">
                                                {date(movement.booked_on)}
                                            </td>
                                            <td className="max-w-64 truncate">
                                                {movement.description}
                                            </td>
                                            <td>{movement.reference || '—'}</td>
                                            <td className="text-right font-mono">
                                                {money(
                                                    movement.amount,
                                                    account.currency,
                                                )}
                                            </td>
                                            <td>
                                                <Badge
                                                    variant={
                                                        movement.status ===
                                                        'new'
                                                            ? 'default'
                                                            : 'secondary'
                                                    }
                                                >
                                                    {movement.status === 'new'
                                                        ? 'New'
                                                        : 'Already imported'}
                                                </Badge>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </TableViewport>
                    )}
                    {preview.control_valid === false && (
                        <label className="flex items-start gap-2 rounded-md border border-destructive/40 bg-destructive/5 p-3 text-sm">
                            <Checkbox
                                checked={acceptMismatch}
                                onCheckedChange={(checked) =>
                                    setAcceptMismatch(checked === true)
                                }
                            />
                            <span>
                                The Norma 43 totals do not match its control
                                record. I have reviewed the discrepancy and want
                                to import it anyway.
                            </span>
                        </label>
                    )}
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <p className="text-xs text-muted-foreground">
                            The final import repeats duplicate and integrity
                            checks in case data changed after this preview.
                        </p>
                        <Button
                            type="button"
                            disabled={
                                importing ||
                                preview.created === 0 ||
                                (preview.control_valid === false &&
                                    !acceptMismatch)
                            }
                            onClick={importStatement}
                        >
                            <Upload />
                            {importing
                                ? 'Importing…'
                                : preview.created === 0
                                  ? 'Nothing new to import'
                                  : `Import ${preview.created} movements`}
                        </Button>
                    </div>
                </div>
            )}
        </div>
    );
}

function ImportRollbackDialog({
    statementImport,
}: {
    statementImport: StatementImport;
}) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm" variant="ghost">
                    <Trash2 /> Roll back
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Roll back statement import</DialogTitle>
                    <DialogDescription>
                        This removes the {statementImport.transactions_count}{' '}
                        pending movements created by {statementImport.filename}.
                        The import record and reason remain in the audit trail.
                    </DialogDescription>
                </DialogHeader>
                <Form
                    action={`/banking/imports/${statementImport.id}`}
                    method="delete"
                    className="grid gap-4"
                    onSuccess={() => setOpen(false)}
                >
                    {({ errors, processing }) => (
                        <>
                            <div className="grid gap-2">
                                <Label
                                    htmlFor={`rollback-${statementImport.id}`}
                                >
                                    Reason
                                </Label>
                                <Textarea
                                    id={`rollback-${statementImport.id}`}
                                    name="reason"
                                    placeholder="For example: wrong bank account selected"
                                    required
                                />
                                {errors.reason && (
                                    <p className="text-sm text-destructive">
                                        {errors.reason}
                                    </p>
                                )}
                            </div>
                            <Button variant="destructive" disabled={processing}>
                                <Trash2 /> Remove pending movements
                            </Button>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

type AllocationDraft = {
    key: number;
    accountCode: string;
    accountName: string;
    amount: string;
};

function PostingDialog({
    transaction,
    currency,
    ledgerAccounts,
}: {
    transaction: Transaction;
    currency: string;
    ledgerAccounts: LedgerAccount[];
}) {
    const total = Math.abs(Number(transaction.amount));
    const initialAllocation = (): AllocationDraft[] => [
        {
            key: Date.now(),
            accountCode: '',
            accountName: '',
            amount: total.toFixed(2),
        },
    ];
    const [open, setOpen] = useState(false);
    const [allocations, setAllocations] =
        useState<AllocationDraft[]>(initialAllocation);
    const allocated = allocations.reduce(
        (sum, allocation) => sum + (Number(allocation.amount) || 0),
        0,
    );
    const remaining = Math.round((total - allocated) * 100) / 100;
    const valid =
        Math.abs(remaining) < 0.005 &&
        allocations.every((allocation) => {
            const existing = ledgerAccounts.some(
                (account) => account.code === allocation.accountCode,
            );

            return (
                /^\d{3,12}$/.test(allocation.accountCode) &&
                Number(allocation.amount) > 0 &&
                (existing || allocation.accountName.trim().length > 0)
            );
        });

    const updateAllocation = (
        key: number,
        field: keyof Omit<AllocationDraft, 'key'>,
        value: string,
    ) =>
        setAllocations((current) =>
            current.map((allocation) =>
                allocation.key === key
                    ? {
                          ...allocation,
                          [field]: value,
                          ...(field === 'accountCode' &&
                          ledgerAccounts.some(
                              (account) => account.code === value,
                          )
                              ? { accountName: '' }
                              : {}),
                      }
                    : allocation,
            ),
        );

    const addAllocation = () =>
        setAllocations((current) => {
            if (current.length === 1 && Math.abs(allocated - total) < 0.005) {
                const firstAmount = Math.round((total / 2) * 100) / 100;

                return [
                    { ...current[0], amount: firstAmount.toFixed(2) },
                    {
                        key: Date.now(),
                        accountCode: '',
                        accountName: '',
                        amount: (total - firstAmount).toFixed(2),
                    },
                ];
            }

            return [
                ...current,
                {
                    key: Date.now(),
                    accountCode: '',
                    accountName: '',
                    amount: '',
                },
            ];
        });

    return (
        <Dialog
            open={open}
            onOpenChange={(nextOpen) => {
                setOpen(nextOpen);

                if (nextOpen) {
                    setAllocations(initialAllocation());
                }
            }}
        >
            <DialogTrigger asChild>
                <Button size="sm" variant="outline">
                    <ListPlus /> Post / split
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[90vh] max-w-3xl overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>Post bank movement</DialogTitle>
                    <DialogDescription>
                        Allocate {money(total, currency)} across one or more
                        ledger accounts. The total must match the bank movement
                        exactly.
                    </DialogDescription>
                </DialogHeader>
                <div className="rounded-md bg-muted px-3 py-2 text-sm">
                    <div className="font-medium">{transaction.description}</div>
                    <div className="text-xs text-muted-foreground">
                        {date(transaction.booked_on)} ·{' '}
                        {Number(transaction.amount) >= 0
                            ? 'Incoming movement'
                            : 'Outgoing movement'}
                    </div>
                </div>
                <Form
                    action={`/banking/transactions/${transaction.id}/post`}
                    method="post"
                    className="grid gap-4"
                    onSuccess={() => setOpen(false)}
                >
                    {({ errors, processing }) => (
                        <>
                            <div className="grid gap-3">
                                {allocations.map((allocation, index) => {
                                    const existingAccount = ledgerAccounts.find(
                                        (account) =>
                                            account.code ===
                                            allocation.accountCode,
                                    );
                                    const creating =
                                        allocation.accountCode.length >= 3 &&
                                        !existingAccount;

                                    return (
                                        <div
                                            key={allocation.key}
                                            className="grid gap-2 rounded-md border p-3 md:grid-cols-[9rem_minmax(12rem,1fr)_9rem_auto] md:items-end"
                                        >
                                            <ProductField label="Account code">
                                                <Input
                                                    name={`allocations[${index}][account_code]`}
                                                    list="ledger-account-options"
                                                    value={
                                                        allocation.accountCode
                                                    }
                                                    onChange={(event) =>
                                                        updateAllocation(
                                                            allocation.key,
                                                            'accountCode',
                                                            event.target.value,
                                                        )
                                                    }
                                                    placeholder="6290001"
                                                    inputMode="numeric"
                                                    required
                                                />
                                            </ProductField>
                                            <ProductField
                                                label={
                                                    creating
                                                        ? 'New subaccount name'
                                                        : 'Account'
                                                }
                                            >
                                                {creating ? (
                                                    <Input
                                                        name={`allocations[${index}][account_name]`}
                                                        value={
                                                            allocation.accountName
                                                        }
                                                        onChange={(event) =>
                                                            updateAllocation(
                                                                allocation.key,
                                                                'accountName',
                                                                event.target
                                                                    .value,
                                                            )
                                                        }
                                                        placeholder="Name for the new subaccount"
                                                        required
                                                    />
                                                ) : (
                                                    <div className="flex h-9 items-center rounded-md border bg-muted/30 px-3 text-sm text-muted-foreground">
                                                        {existingAccount?.name ??
                                                            'Enter an account code'}
                                                    </div>
                                                )}
                                            </ProductField>
                                            <ProductField label="Amount">
                                                <Input
                                                    name={`allocations[${index}][amount]`}
                                                    type="number"
                                                    min="0.01"
                                                    step="0.01"
                                                    value={allocation.amount}
                                                    onChange={(event) =>
                                                        updateAllocation(
                                                            allocation.key,
                                                            'amount',
                                                            event.target.value,
                                                        )
                                                    }
                                                    required
                                                />
                                            </ProductField>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                title="Remove allocation"
                                                disabled={
                                                    allocations.length === 1
                                                }
                                                onClick={() =>
                                                    setAllocations((current) =>
                                                        current.filter(
                                                            (item) =>
                                                                item.key !==
                                                                allocation.key,
                                                        ),
                                                    )
                                                }
                                            >
                                                <Trash2 />
                                            </Button>
                                        </div>
                                    );
                                })}
                            </div>
                            <div className="flex flex-wrap items-center justify-between gap-3">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={addAllocation}
                                    disabled={allocations.length >= 20}
                                >
                                    <Plus /> Add allocation
                                </Button>
                                <div className="text-right text-sm">
                                    <div>
                                        Allocated:{' '}
                                        <strong>
                                            {money(allocated, currency)}
                                        </strong>
                                    </div>
                                    <div
                                        className={
                                            Math.abs(remaining) < 0.005
                                                ? 'text-green-700 dark:text-green-400'
                                                : 'text-destructive'
                                        }
                                    >
                                        Remaining: {money(remaining, currency)}
                                    </div>
                                </div>
                            </div>
                            <p className="text-xs text-muted-foreground">
                                If the code does not exist, enter its name. A
                                custom subaccount will be created under the
                                closest existing parent in the company chart of
                                accounts.
                            </p>
                            {Object.keys(errors).length > 0 && (
                                <p className="text-sm text-destructive">
                                    {Object.values(errors)[0]}
                                </p>
                            )}
                            <Button disabled={processing || !valid}>
                                Post {allocations.length} allocation
                                {allocations.length === 1 ? '' : 's'}
                            </Button>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function ReconciliationRules({
    account,
    ledgerAccounts,
    rules,
    preview,
    runs,
}: {
    account: Account;
    ledgerAccounts: LedgerAccount[];
    rules: ReconciliationRule[];
    preview: RulePreview;
    runs: RuleRun[];
}) {
    const [minimumConfidence, setMinimumConfidence] = useState('95');
    const eligibleMatches =
        preview.eligibleByConfidence[minimumConfidence] ?? 0;

    return (
        <details className="rounded-md border" open={rules.length === 0}>
            <summary className="flex cursor-pointer list-none flex-wrap items-center justify-between gap-3 px-4 py-3">
                <div>
                    <div className="text-sm font-semibold">
                        Reusable accounting rules
                    </div>
                    <div className="text-xs font-normal text-muted-foreground">
                        {rules.filter((rule) => rule.is_active).length} active ·{' '}
                        {preview.matched} of {preview.pending} pending movements
                        can be safely posted
                        {preview.conflicts > 0 &&
                            ` · ${preview.conflicts} conflicts blocked`}
                    </div>
                </div>
                <Badge variant={preview.matched ? 'secondary' : 'outline'}>
                    Preview · {preview.matched} matches
                </Badge>
            </summary>
            <div className="grid gap-5 border-t p-4 xl:grid-cols-[minmax(0,1.4fr)_minmax(19rem,0.6fr)]">
                <div className="grid gap-4">
                    <Form
                        action={`/banking/accounts/${account.id}/rules`}
                        method="post"
                        className="grid gap-3 rounded-md border bg-muted/20 p-3 md:grid-cols-2 xl:grid-cols-4"
                    >
                        <div className="grid gap-2 xl:col-span-2">
                            <Label>Rule name</Label>
                            <Input
                                name="name"
                                placeholder="Monthly bank fees"
                                required
                            />
                        </div>
                        <div className="grid gap-2 xl:col-span-2">
                            <Label>Post to account</Label>
                            <Select name="ledger_account_id" required>
                                <SelectTrigger>
                                    <SelectValue placeholder="Select ledger account" />
                                </SelectTrigger>
                                <SelectContent>
                                    {ledgerAccounts.map((ledger) => (
                                        <SelectItem
                                            key={ledger.id}
                                            value={String(ledger.id)}
                                        >
                                            {ledger.code} · {ledger.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="grid gap-2">
                            <Label>Search in</Label>
                            <Select name="match_field" defaultValue="either">
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="either">
                                        Description or reference
                                    </SelectItem>
                                    <SelectItem value="description">
                                        Description
                                    </SelectItem>
                                    <SelectItem value="reference">
                                        Reference
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="grid gap-2">
                            <Label>Condition</Label>
                            <Select
                                name="match_operator"
                                defaultValue="contains"
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="contains">
                                        Contains
                                    </SelectItem>
                                    <SelectItem value="starts_with">
                                        Starts with
                                    </SelectItem>
                                    <SelectItem value="exact">
                                        Exact text
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="grid gap-2 xl:col-span-2">
                            <Label>Text pattern</Label>
                            <Input
                                name="pattern"
                                placeholder="COMISION MANTENIMIENTO"
                                minLength={3}
                                required
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label>Direction</Label>
                            <Select name="direction" defaultValue="out">
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="out">
                                        Money out
                                    </SelectItem>
                                    <SelectItem value="in">Money in</SelectItem>
                                    <SelectItem value="any">Either</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <Field
                            label="Minimum amount"
                            name="min_amount"
                            type="number"
                        />
                        <Field
                            label="Maximum amount"
                            name="max_amount"
                            type="number"
                        />
                        <Field
                            label="Priority"
                            name="priority"
                            type="number"
                            value="100"
                        />
                        <Field
                            label="Confidence %"
                            name="confidence"
                            type="number"
                            value="95"
                        />
                        <input type="hidden" name="is_active" value="1" />
                        <Button className="self-end xl:col-span-4 xl:justify-self-start">
                            <ListPlus /> Create and preview rule
                        </Button>
                    </Form>

                    <div className="grid gap-2">
                        {rules.map((rule) => {
                            const matched =
                                preview.byRule.find(
                                    (item) => item.rule_id === rule.id,
                                )?.matched ?? 0;

                            return (
                                <div
                                    key={rule.id}
                                    className="grid gap-3 rounded-md border p-3 text-sm"
                                >
                                    <div className="flex flex-wrap items-center justify-between gap-3">
                                        <div className="min-w-0">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <span className="font-medium">
                                                    {rule.name}
                                                </span>
                                                <Badge
                                                    variant={
                                                        rule.is_active
                                                            ? 'secondary'
                                                            : 'outline'
                                                    }
                                                >
                                                    {rule.is_active
                                                        ? `${matched} now`
                                                        : 'Inactive'}
                                                </Badge>
                                                <Badge variant="outline">
                                                    v{rule.current_version}
                                                </Badge>
                                            </div>
                                            <div className="mt-1 text-xs text-muted-foreground">
                                                Priority {rule.priority} ·{' '}
                                                {rule.direction === 'out'
                                                    ? 'outgoing'
                                                    : rule.direction === 'in'
                                                      ? 'incoming'
                                                      : 'any direction'}{' '}
                                                · {rule.match_field}{' '}
                                                {rule.match_operator.replace(
                                                    '_',
                                                    ' ',
                                                )}{' '}
                                                “{rule.pattern}” →{' '}
                                                <span className="font-mono">
                                                    {rule.ledger_account.code}
                                                </span>{' '}
                                                {rule.ledger_account.name} ·{' '}
                                                {rule.confidence}% confidence ·
                                                used {rule.application_count}{' '}
                                                times
                                            </div>
                                        </div>
                                        <RuleGovernance
                                            rule={rule}
                                            ledgerAccounts={ledgerAccounts}
                                        />
                                    </div>
                                </div>
                            );
                        })}
                        {rules.length === 0 && (
                            <div className="rounded-md border border-dashed p-4 text-sm text-muted-foreground">
                                No rules yet. Create a narrow, high-confidence
                                rule and verify its preview before applying it.
                            </div>
                        )}
                    </div>

                    {preview.details.length > 0 && (
                        <details className="rounded-md border">
                            <summary className="cursor-pointer px-3 py-2 text-sm font-medium">
                                Movement-level preview ·{' '}
                                {preview.details.length}
                                {preview.details.length <
                                preview.matched + preview.conflicts
                                    ? ' shown'
                                    : ''}
                            </summary>
                            <TableViewport className="border-t">
                                <table className="w-full min-w-[48rem] text-xs">
                                    <thead>
                                        <tr className="border-b text-left text-muted-foreground">
                                            <th className="py-2">Date</th>
                                            <th>Movement</th>
                                            <th className="text-right">
                                                Amount
                                            </th>
                                            <th>Selected rule</th>
                                            <th>Ledger account</th>
                                            <th>Decision</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {preview.details.map((item) => (
                                            <tr
                                                key={item.transaction_id}
                                                className="border-b align-top"
                                            >
                                                <td className="py-2">
                                                    {date(item.booked_on)}
                                                </td>
                                                <td>
                                                    <div className="max-w-xs font-medium">
                                                        {item.description}
                                                    </div>
                                                    <div className="font-mono text-muted-foreground">
                                                        {item.reference || '—'}
                                                    </div>
                                                </td>
                                                <td className="text-right font-medium">
                                                    {money(
                                                        item.amount,
                                                        account.currency,
                                                    )}
                                                </td>
                                                <td>
                                                    {item.rule_name} ·{' '}
                                                    {item.confidence}%
                                                </td>
                                                <td>
                                                    <span className="font-mono">
                                                        {
                                                            item.ledger_account
                                                                .code
                                                        }
                                                    </span>{' '}
                                                    {item.ledger_account.name}
                                                </td>
                                                <td>
                                                    {item.conflict ? (
                                                        <div>
                                                            <Badge variant="destructive">
                                                                Blocked conflict
                                                            </Badge>
                                                            <div className="mt-1 text-muted-foreground">
                                                                {item.alternatives
                                                                    .map(
                                                                        (
                                                                            alternative,
                                                                        ) =>
                                                                            `${alternative.rule_name} → ${alternative.account_code}`,
                                                                    )
                                                                    .join(', ')}
                                                            </div>
                                                        </div>
                                                    ) : (
                                                        <Badge variant="secondary">
                                                            Ready to post
                                                        </Badge>
                                                    )}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </TableViewport>
                        </details>
                    )}
                </div>

                <div className="grid content-start gap-4">
                    <div className="rounded-md border p-4">
                        <div className="font-medium">Safe execution</div>
                        <p className="mt-1 text-xs text-muted-foreground">
                            Only pending movements above the selected confidence
                            are posted. Every run and resulting journal entry
                            retains the rule and operator trace.
                        </p>
                        {preview.truncated && (
                            <p className="mt-2 text-xs text-amber-700 dark:text-amber-400">
                                Preview limited to {preview.scanned} movements.
                                Apply in batches and review each result.
                            </p>
                        )}
                        {preview.conflicts > 0 && (
                            <p className="mt-2 text-xs text-destructive">
                                {preview.conflicts} movement
                                {preview.conflicts === 1 ? '' : 's'} matched
                                rules pointing to different accounts. They are
                                blocked until the rules are narrowed or removed.
                            </p>
                        )}
                        <Form
                            action={`/banking/accounts/${account.id}/apply-rules`}
                            method="post"
                            className="mt-4 grid gap-3"
                            onSubmit={(event) => {
                                if (
                                    !window.confirm(
                                        `Post ${eligibleMatches} movements at or above ${minimumConfidence}% confidence?`,
                                    )
                                ) {
                                    event.preventDefault();
                                }
                            }}
                        >
                            <input
                                type="hidden"
                                name="confirmation"
                                value={`APPLY-${preview.matched}`}
                            />
                            <div className="grid gap-2">
                                <Label>Minimum confidence</Label>
                                <Select
                                    name="minimum_confidence"
                                    value={minimumConfidence}
                                    onValueChange={setMinimumConfidence}
                                >
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="100">
                                            100%
                                        </SelectItem>
                                        <SelectItem value="98">98%</SelectItem>
                                        <SelectItem value="95">
                                            95% · Recommended
                                        </SelectItem>
                                        <SelectItem value="90">90%</SelectItem>
                                        <SelectItem value="80">80%</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <Button disabled={eligibleMatches === 0}>
                                <RefreshCw /> Apply {eligibleMatches} eligible
                                matches
                            </Button>
                        </Form>
                    </div>

                    {runs.length > 0 && (
                        <div className="rounded-md border p-4">
                            <div className="text-sm font-medium">
                                Recent rule runs
                            </div>
                            <div className="mt-3 grid gap-2">
                                {runs.map((run) => (
                                    <div
                                        key={run.id}
                                        className="grid gap-2 border-t pt-3 text-xs first:border-0 first:pt-0"
                                    >
                                        <div className="flex items-start justify-between gap-3">
                                            <div>
                                                <div>
                                                    Run #{run.id} · {run.status}
                                                </div>
                                                <div className="text-muted-foreground">
                                                    {date(run.created_at)} ·{' '}
                                                    {run.triggered_by?.name ??
                                                        'User'}{' '}
                                                    · minimum{' '}
                                                    {run.minimum_confidence}%
                                                </div>
                                            </div>
                                            <div className="text-right">
                                                <div>
                                                    {run.matched_count} posted
                                                </div>
                                                <div className="text-muted-foreground">
                                                    {run.skipped_count} skipped
                                                    · {run.failed_count} failed
                                                </div>
                                            </div>
                                        </div>
                                        {run.reversed_at && (
                                            <div
                                                className="rounded bg-muted/40 p-2 text-muted-foreground"
                                                title={
                                                    run.reversal_reason ??
                                                    undefined
                                                }
                                            >
                                                Reversed by{' '}
                                                {run.reversed_by?.name ??
                                                    'User'}{' '}
                                                · {date(run.reversed_at)} ·{' '}
                                                {run.reversal_reason}
                                            </div>
                                        )}
                                        {run.can_reverse && (
                                            <Form
                                                action={`/banking/rule-runs/${run.id}/reverse`}
                                                method="post"
                                                className="grid gap-2 rounded border border-dashed p-2"
                                                onSubmit={(event) => {
                                                    if (
                                                        !window.confirm(
                                                            `Reverse all ${run.posting_count} postings from run #${run.id}?`,
                                                        )
                                                    ) {
                                                        event.preventDefault();
                                                    }
                                                }}
                                            >
                                                <input
                                                    type="hidden"
                                                    name="confirmation"
                                                    value={`REVERSE-${run.id}-${run.posting_count}`}
                                                />
                                                <Input
                                                    name="reason"
                                                    minLength={10}
                                                    maxLength={500}
                                                    placeholder="Reason for reversing the complete run"
                                                    required
                                                />
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                >
                                                    Undo complete run
                                                </Button>
                                            </Form>
                                        )}
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </details>
    );
}

function RuleGovernance({
    rule,
    ledgerAccounts,
}: {
    rule: ReconciliationRule;
    ledgerAccounts: LedgerAccount[];
}) {
    return (
        <details className="basis-full rounded-md border border-dashed">
            <summary className="cursor-pointer list-none px-3 py-2 text-xs font-medium">
                Edit, pause or inspect version history
            </summary>
            <div className="grid gap-4 border-t p-3 lg:grid-cols-[minmax(0,1fr)_18rem]">
                <Form
                    action={`/banking/rules/${rule.id}`}
                    method="patch"
                    className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4"
                >
                    <div className="grid gap-2 xl:col-span-2">
                        <Label>Name</Label>
                        <Input name="name" defaultValue={rule.name} required />
                    </div>
                    <div className="grid gap-2 xl:col-span-2">
                        <Label>Ledger account</Label>
                        <Select
                            name="ledger_account_id"
                            defaultValue={String(rule.ledger_account.id)}
                            required
                        >
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {ledgerAccounts.map((ledger) => (
                                    <SelectItem
                                        key={ledger.id}
                                        value={String(ledger.id)}
                                    >
                                        {ledger.code} · {ledger.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="grid gap-2">
                        <Label>Search in</Label>
                        <Select
                            name="match_field"
                            defaultValue={rule.match_field}
                        >
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="either">
                                    Description or reference
                                </SelectItem>
                                <SelectItem value="description">
                                    Description
                                </SelectItem>
                                <SelectItem value="reference">
                                    Reference
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="grid gap-2">
                        <Label>Condition</Label>
                        <Select
                            name="match_operator"
                            defaultValue={rule.match_operator}
                        >
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="contains">
                                    Contains
                                </SelectItem>
                                <SelectItem value="starts_with">
                                    Starts with
                                </SelectItem>
                                <SelectItem value="exact">
                                    Exact text
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="grid gap-2 xl:col-span-2">
                        <Label>Text pattern</Label>
                        <Input
                            name="pattern"
                            defaultValue={rule.pattern}
                            minLength={3}
                            required
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label>Direction</Label>
                        <Select name="direction" defaultValue={rule.direction}>
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="out">Money out</SelectItem>
                                <SelectItem value="in">Money in</SelectItem>
                                <SelectItem value="any">Either</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <Field
                        label="Minimum amount"
                        name="min_amount"
                        type="number"
                        value={rule.min_amount ?? ''}
                    />
                    <Field
                        label="Maximum amount"
                        name="max_amount"
                        type="number"
                        value={rule.max_amount ?? ''}
                    />
                    <Field
                        label="Priority"
                        name="priority"
                        type="number"
                        value={String(rule.priority)}
                    />
                    <Field
                        label="Confidence %"
                        name="confidence"
                        type="number"
                        value={String(rule.confidence)}
                    />
                    <label className="flex items-center gap-2 self-end pb-2 text-sm">
                        <input type="hidden" name="is_active" value="0" />
                        <Checkbox
                            name="is_active"
                            value="1"
                            defaultChecked={rule.is_active}
                        />
                        Active
                    </label>
                    <div className="grid gap-2 sm:col-span-2 xl:col-span-3">
                        <Label>Reason for change</Label>
                        <Input
                            name="change_reason"
                            minLength={10}
                            maxLength={500}
                            placeholder="Explain why this rule is changing"
                            required
                        />
                    </div>
                    <Button className="sm:col-span-2 xl:col-span-4 xl:justify-self-start">
                        <Pencil /> Save as version {rule.current_version + 1}
                    </Button>
                </Form>

                <div className="grid content-start gap-3">
                    <div>
                        <div className="text-xs font-medium">
                            Immutable history
                        </div>
                        <div className="mt-2 grid max-h-48 gap-2 overflow-y-auto">
                            {rule.versions.map((version) => (
                                <div
                                    key={version.id}
                                    className="rounded border p-2 text-xs"
                                >
                                    <div className="font-medium">
                                        Version {version.version} ·{' '}
                                        {version.changer?.name ?? 'System'}
                                    </div>
                                    <div className="text-muted-foreground">
                                        {date(version.created_at)} ·{' '}
                                        {version.change_reason}
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                    {rule.is_active && (
                        <Form
                            action={`/banking/rules/${rule.id}`}
                            method="delete"
                            className="grid gap-2 rounded border border-destructive/30 p-2"
                        >
                            <Label>Reason for pausing</Label>
                            <Input
                                name="reason"
                                minLength={10}
                                maxLength={500}
                                placeholder="Why should future matches stop?"
                                required
                            />
                            <Button size="sm" variant="outline">
                                <Trash2 /> Pause rule
                            </Button>
                        </Form>
                    )}
                </div>
            </div>
        </details>
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
                    accounts={accounts}
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
                                            : product.linked_cards.length > 0
                                              ? 'Shared card credit'
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
                            {!product.shared_credit_facility_id && (
                                <span>
                                    {product.linked_cards.length > 0
                                        ? 'Debit account'
                                        : 'Settlement'}
                                    : {product.bank_account.name}
                                    {product.ledger_account
                                        ? ` · ${product.ledger_account.code}`
                                        : ''}
                                </span>
                            )}
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
                        {product.next_closing_on && product.next_payment_on && (
                            <div className="mt-3 rounded-md bg-muted/60 px-3 py-2 text-xs">
                                <span className="font-medium">
                                    {product.shared_credit_facility_id
                                        ? 'This card feeds the shared receipt'
                                        : `${product.linked_cards.length} cards feed one shared receipt`}
                                </span>
                                <span className="mt-1 block text-muted-foreground">
                                    Closes {date(product.next_closing_on)} ·
                                    charged {date(product.next_payment_on)}
                                    {product.payment_bank_account
                                        ? ` to ${product.payment_bank_account.name}`
                                        : ''}
                                </span>
                            </div>
                        )}
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

function SharedCreditDialog({
    cards,
    accounts,
}: {
    cards: FinancialProduct[];
    accounts: Account[];
}) {
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
                        The cards draw from one limit, close into one monthly
                        receipt and charge it to your bank account on the chosen
                        payment day.
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
                                <ProductField label="Account charged for the receipt">
                                    <Select
                                        name="bank_account_id"
                                        defaultValue={String(accounts[0]?.id)}
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
                                <ProductField label="Receipt charge day">
                                    <Input
                                        name="payment_day"
                                        type="number"
                                        min="1"
                                        max="31"
                                        defaultValue="5"
                                        required
                                    />
                                </ProductField>
                            </div>
                            <input
                                type="hidden"
                                name="billing_day"
                                value="31"
                            />
                            <p className="rounded-md bg-muted px-3 py-2 text-xs text-muted-foreground">
                                Monthly cycle: card charges → month-end close →
                                one receipt → debit from the selected account.
                                The intermediate credit is not treated as a bank
                                account.
                            </p>
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

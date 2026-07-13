import { Form, Head, Link, router, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    Landmark,
    LockKeyhole,
    Plus,
    RefreshCw,
    RotateCcw,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import type React from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
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

type Line = {
    id: number;
    debit: string;
    credit: string;
    running_balance?: number;
    account: { code: string; name: string };
    entry?: { number: string; entry_date: string; description: string };
};
type Entry = {
    id: number;
    number: string;
    entry_date: string;
    description: string;
    event: string;
    entry_kind: string;
    reversal: { id: number } | null;
    lines: Line[];
};
type Account = {
    id: number;
    code: string;
    name: string;
    type: string;
    debit: number;
    credit: number;
    balance: number;
};
type FiscalYear = { id: number; year: number; status: string };
type Reports = {
    profitAndLoss: { income: number; expenses: number; result: number };
    balanceSheet: {
        assets: number;
        liabilities: number;
        equity: number;
        result: number;
    };
};
type ExpenseCategory = {
    id: number;
    code: string;
    name: string;
    expense_account_code: string | null;
    is_active: boolean;
};
type TaxRate = {
    id: number;
    name: string;
    rate: string;
    kind: string;
    output_account_code: string | null;
    input_account_code: string | null;
    is_active: boolean;
};
type PartyBalance = {
    id: number;
    kind: 'customer' | 'supplier';
    name: string;
    accountCode: string;
    ledgerBalance: number;
    outstanding: number;
    current: number;
    days30: number;
    days60: number;
    days90: number;
    older: number;
};
type View =
    | 'journal'
    | 'trial'
    | 'profit'
    | 'balance'
    | 'ledger'
    | 'parties'
    | 'mappings';

export default function AccountingIndex({
    year,
    fiscalYear,
    entries,
    accounts,
    selectedAccount,
    ledger,
    reports,
    summary,
    expenseCategories,
    taxRates,
    thirdParties,
}: {
    year: number;
    fiscalYear: FiscalYear | null;
    entries: Entry[];
    accounts: Account[];
    selectedAccount: Account | null;
    ledger: Line[];
    reports: Reports;
    summary: { entries: number; debit: number; credit: number };
    expenseCategories: ExpenseCategory[];
    taxRates: TaxRate[];
    thirdParties: { customers: PartyBalance[]; suppliers: PartyBalance[] };
}) {
    const [view, setView] = useState<View>(
        selectedAccount ? 'ledger' : 'journal',
    );
    const isAdmin =
        usePage<{ auth: { user: { role: string } } }>().props.auth.user.role ===
        'admin';
    const navigate = (params: Record<string, string | number>) =>
        router.get('/accounting', { year, ...params }, { preserveState: true });

    return (
        <>
            <Head title="Accounting" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <header className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold">Accounting</h1>
                        <p className="text-sm text-muted-foreground">
                            Journal, general ledger and statutory financial
                            reports.
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button variant="outline" asChild>
                            <Link href="/finance">
                                <ArrowLeft /> Finance
                            </Link>
                        </Button>
                        <Button variant="outline" asChild>
                            <Link href="/tax">
                                <Landmark /> VAT
                            </Link>
                        </Button>
                        <Form action="/accounting/sync" method="post">
                            <Button>
                                <RefreshCw /> Sync now
                            </Button>
                        </Form>
                        {fiscalYear?.status === 'open' && (
                            <ManualEntryDialog
                                accounts={accounts}
                                year={year}
                            />
                        )}
                        {isAdmin && fiscalYear?.status === 'open' && (
                            <Form
                                action={`/accounting/years/${fiscalYear.id}/close`}
                                method="post"
                            >
                                <Button variant="outline">
                                    <LockKeyhole /> Close year
                                </Button>
                            </Form>
                        )}
                        {isAdmin && fiscalYear?.status === 'closed' && (
                            <Form
                                action={`/accounting/years/${fiscalYear.id}/opening`}
                                method="post"
                            >
                                <Button variant="outline">
                                    <RefreshCw /> Generate opening
                                </Button>
                            </Form>
                        )}
                        <Select
                            value={String(year)}
                            onValueChange={(value) =>
                                router.get(
                                    '/accounting',
                                    { year: value },
                                    { preserveState: true },
                                )
                            }
                        >
                            <SelectTrigger className="w-28">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {Array.from(
                                    { length: 7 },
                                    (_, index) =>
                                        new Date().getFullYear() - 3 + index,
                                ).map((value) => (
                                    <SelectItem
                                        key={value}
                                        value={String(value)}
                                    >
                                        {value}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                </header>
                <section className="grid grid-cols-3 border-y">
                    <Metric label="Entries" value={String(summary.entries)} />
                    <Metric label="Total debit" value={money(summary.debit)} />
                    <Metric
                        label="Total credit"
                        value={money(summary.credit)}
                    />
                </section>
                <nav
                    className="flex gap-1 overflow-x-auto border-b"
                    aria-label="Accounting views"
                >
                    {(
                        [
                            ['journal', 'Journal'],
                            ['trial', 'Trial balance'],
                            ['profit', 'Profit & loss'],
                            ['balance', 'Balance sheet'],
                            ['ledger', 'General ledger'],
                            ['parties', 'Third parties'],
                            ['mappings', 'Mappings'],
                        ] as [View, string][]
                    ).map(([key, label]) => (
                        <button
                            key={key}
                            type="button"
                            onClick={() => setView(key)}
                            className={`border-b-2 px-3 py-2 text-sm font-medium whitespace-nowrap ${view === key ? 'border-primary text-foreground' : 'border-transparent text-muted-foreground hover:text-foreground'}`}
                        >
                            {label}
                        </button>
                    ))}
                </nav>
                {view === 'journal' && (
                    <Journal
                        entries={entries}
                        canReverse={fiscalYear?.status === 'open'}
                    />
                )}
                {view === 'trial' && (
                    <TrialBalance
                        accounts={accounts}
                        onAccount={(code) => {
                            setView('ledger');
                            navigate({ account: code });
                        }}
                    />
                )}
                {view === 'profit' && (
                    <ProfitAndLoss
                        accounts={accounts}
                        report={reports.profitAndLoss}
                    />
                )}
                {view === 'balance' && (
                    <BalanceSheet
                        accounts={accounts}
                        report={reports.balanceSheet}
                    />
                )}
                {view === 'ledger' && (
                    <GeneralLedger
                        accounts={accounts}
                        selected={selectedAccount}
                        lines={ledger}
                        onAccount={(code) => navigate({ account: code })}
                    />
                )}
                {view === 'parties' && (
                    <ThirdPartyBalances
                        year={year}
                        customers={thirdParties.customers}
                        suppliers={thirdParties.suppliers}
                    />
                )}
                {view === 'mappings' && (
                    <AccountingMappings
                        accounts={accounts}
                        categories={expenseCategories}
                        taxRates={taxRates}
                    />
                )}
            </div>
        </>
    );
}

function ManualEntryDialog({
    accounts,
    year,
}: {
    accounts: Account[];
    year: number;
}) {
    const [lines, setLines] = useState([0, 1]);

    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button>
                    <Plus /> New entry
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[90vh] max-w-4xl overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>New manual journal entry</DialogTitle>
                </DialogHeader>
                <Form
                    action="/accounting/entries"
                    method="post"
                    className="grid gap-4"
                >
                    <div className="grid gap-3 sm:grid-cols-[12rem_1fr]">
                        <div className="grid gap-1">
                            <Label>Date</Label>
                            <Input
                                name="entry_date"
                                type="date"
                                defaultValue={`${year}-01-01`}
                                required
                            />
                        </div>
                        <div className="grid gap-1">
                            <Label>Description</Label>
                            <Input name="description" required />
                        </div>
                    </div>
                    <div className="overflow-x-auto">
                        <div className="min-w-[42rem]">
                            <div className="grid grid-cols-[minmax(15rem,1fr)_1fr_8rem_8rem_2rem] gap-2 border-b pb-2 text-xs text-muted-foreground">
                                <span>Account</span>
                                <span>Line description</span>
                                <span>Debit</span>
                                <span>Credit</span>
                                <span />
                            </div>
                            {lines.map((line, index) => (
                                <div
                                    key={line}
                                    className="grid grid-cols-[minmax(15rem,1fr)_1fr_8rem_8rem_2rem] gap-2 border-b py-2"
                                >
                                    <Select
                                        name={`lines[${index}][account_code]`}
                                        required
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Account" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {accounts
                                                .filter(
                                                    (account) =>
                                                        account.code !== '',
                                                )
                                                .map((account) => (
                                                    <SelectItem
                                                        key={account.id}
                                                        value={account.code}
                                                    >
                                                        {account.code} ·{' '}
                                                        {account.name}
                                                    </SelectItem>
                                                ))}
                                        </SelectContent>
                                    </Select>
                                    <Input
                                        name={`lines[${index}][description]`}
                                    />
                                    <Input
                                        name={`lines[${index}][debit]`}
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        defaultValue="0"
                                        required
                                    />
                                    <Input
                                        name={`lines[${index}][credit]`}
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        defaultValue="0"
                                        required
                                    />
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        disabled={lines.length <= 2}
                                        onClick={() =>
                                            setLines((current) =>
                                                current.filter(
                                                    (value) => value !== line,
                                                ),
                                            )
                                        }
                                        title="Remove line"
                                    >
                                        <Trash2 />
                                    </Button>
                                </div>
                            ))}
                        </div>
                    </div>
                    <div className="flex justify-between">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() =>
                                setLines((current) => [
                                    ...current,
                                    Math.max(...current) + 1,
                                ])
                            }
                        >
                            <Plus /> Add line
                        </Button>
                        <Button>Post entry</Button>
                    </div>
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function Journal({
    entries,
    canReverse,
}: {
    entries: Entry[];
    canReverse: boolean;
}) {
    return (
        <section className="overflow-x-auto">
            <h2 className="mb-3 text-base font-semibold">Journal</h2>
            <table className="w-full min-w-[60rem] text-sm">
                <thead>
                    <tr className="border-b text-left text-muted-foreground">
                        <th className="py-2">Entry</th>
                        <th>Date</th>
                        <th>Description</th>
                        <th>Account</th>
                        <th className="text-right">Debit</th>
                        <th className="text-right">Credit</th>
                        <th />
                    </tr>
                </thead>
                <tbody>
                    {entries.flatMap((entry) =>
                        entry.lines.map((line, index) => (
                            <tr key={line.id} className="border-b align-top">
                                <td className="py-2 font-mono text-xs">
                                    {index === 0 ? entry.number : ''}
                                </td>
                                <td>
                                    {index === 0 ? date(entry.entry_date) : ''}
                                </td>
                                <td>
                                    {index === 0 ? (
                                        <span>
                                            {entry.description}{' '}
                                            <Badge variant="outline">
                                                {entry.entry_kind}
                                            </Badge>
                                        </span>
                                    ) : (
                                        ''
                                    )}
                                </td>
                                <td>
                                    <span className="font-mono">
                                        {line.account.code}
                                    </span>{' '}
                                    {line.account.name}
                                </td>
                                <td className="text-right">
                                    {amount(line.debit)}
                                </td>
                                <td className="text-right">
                                    {amount(line.credit)}
                                </td>
                                <td>
                                    {index === 0 &&
                                        canReverse &&
                                        !entry.reversal &&
                                        entry.entry_kind !== 'reversal' && (
                                            <Form
                                                action={`/accounting/entries/${entry.id}/reverse`}
                                                method="post"
                                                className="flex gap-1"
                                            >
                                                <input
                                                    type="hidden"
                                                    name="entry_date"
                                                    value={new Date()
                                                        .toISOString()
                                                        .slice(0, 10)}
                                                />
                                                <Button
                                                    size="icon"
                                                    variant="ghost"
                                                    title="Post reversal"
                                                >
                                                    <RotateCcw />
                                                </Button>
                                            </Form>
                                        )}
                                </td>
                            </tr>
                        )),
                    )}
                </tbody>
            </table>
            {entries.length === 0 && <Empty />}
        </section>
    );
}

function TrialBalance({
    accounts,
    onAccount,
}: {
    accounts: Account[];
    onAccount: (code: string) => void;
}) {
    const used = accounts.filter((account) => account.debit || account.credit);

    return (
        <ReportTable
            title="Trial balance"
            headers={['Account', 'Name', 'Debit', 'Credit', 'Balance']}
            rows={used.map((account) => [
                <button
                    className="font-mono text-primary hover:underline"
                    onClick={() => onAccount(account.code)}
                >
                    {account.code}
                </button>,
                account.name,
                money(account.debit),
                money(account.credit),
                money(account.balance),
            ])}
            footer={[
                '',
                'Total',
                money(sum(used, 'debit')),
                money(sum(used, 'credit')),
                '',
            ]}
        />
    );
}

function ProfitAndLoss({
    accounts,
    report,
}: {
    accounts: Account[];
    report: Reports['profitAndLoss'];
}) {
    const rows = accounts
        .filter(
            (account) =>
                ['income', 'expense'].includes(account.type) && account.balance,
        )
        .map((account) => [
            account.code,
            account.name,
            account.type === 'income' ? money(account.balance) : '',
            account.type === 'expense' ? money(account.balance) : '',
        ]);

    return (
        <ReportTable
            title="Profit & loss"
            headers={['Account', 'Name', 'Income', 'Expense']}
            rows={rows}
            footer={[
                '',
                report.result >= 0 ? 'Profit' : 'Loss',
                money(report.income),
                money(report.expenses),
            ]}
            note={`Result: ${money(report.result)}`}
        />
    );
}

function BalanceSheet({
    accounts,
    report,
}: {
    accounts: Account[];
    report: Reports['balanceSheet'];
}) {
    const rows = accounts
        .filter(
            (account) =>
                ['asset', 'liability', 'equity'].includes(account.type) &&
                account.balance,
        )
        .map((account) => [
            account.code,
            account.name,
            account.type === 'asset' ? money(account.balance) : '',
            account.type !== 'asset' ? money(account.balance) : '',
        ]);
    const liabilitiesAndEquity =
        report.liabilities + report.equity + report.result;

    return (
        <ReportTable
            title="Balance sheet"
            headers={['Account', 'Name', 'Assets', 'Liabilities & equity']}
            rows={rows}
            footer={[
                '',
                'Total',
                money(report.assets),
                money(liabilitiesAndEquity),
            ]}
            note={`Current result included in equity: ${money(report.result)}`}
        />
    );
}

function GeneralLedger({
    accounts,
    selected,
    lines,
    onAccount,
}: {
    accounts: Account[];
    selected: Account | null;
    lines: Line[];
    onAccount: (code: string) => void;
}) {
    return (
        <section>
            <div className="mb-4 flex flex-wrap items-end gap-3">
                <div className="grid gap-1">
                    <label className="text-xs text-muted-foreground">
                        Account
                    </label>
                    <Select value={selected?.code} onValueChange={onAccount}>
                        <SelectTrigger className="w-80">
                            <SelectValue placeholder="Select an account" />
                        </SelectTrigger>
                        <SelectContent>
                            {accounts.map((account) => (
                                <SelectItem
                                    key={account.id}
                                    value={account.code}
                                >
                                    {account.code} · {account.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>
                {selected && <Badge variant="outline">{selected.type}</Badge>}
            </div>
            {selected ? (
                <div className="overflow-x-auto">
                    <h2 className="mb-3 text-base font-semibold">
                        {selected.code} · {selected.name}
                    </h2>
                    <table className="w-full min-w-[48rem] text-sm">
                        <thead>
                            <tr className="border-b text-left text-muted-foreground">
                                <th className="py-2">Date</th>
                                <th>Entry</th>
                                <th>Description</th>
                                <th className="text-right">Debit</th>
                                <th className="text-right">Credit</th>
                                <th className="text-right">Balance</th>
                            </tr>
                        </thead>
                        <tbody>
                            {lines.map((line) => (
                                <tr key={line.id} className="border-b">
                                    <td className="py-2">
                                        {date(line.entry!.entry_date)}
                                    </td>
                                    <td className="font-mono text-xs">
                                        {line.entry!.number}
                                    </td>
                                    <td>{line.entry!.description}</td>
                                    <td className="text-right">
                                        {amount(line.debit)}
                                    </td>
                                    <td className="text-right">
                                        {amount(line.credit)}
                                    </td>
                                    <td className="text-right font-medium">
                                        {money(line.running_balance || 0)}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    {lines.length === 0 && <Empty />}
                </div>
            ) : (
                <Empty text="Select an account to inspect its movements." />
            )}
        </section>
    );
}

function AccountingMappings({
    accounts,
    categories,
    taxRates,
}: {
    accounts: Account[];
    categories: ExpenseCategory[];
    taxRates: TaxRate[];
}) {
    const expenseAccounts = accounts.filter(
        (account) => account.type === 'expense',
    );

    return (
        <div className="grid gap-8 xl:grid-cols-2">
            <section>
                <h2 className="mb-1 text-base font-semibold">
                    Expense categories
                </h2>
                <p className="mb-3 text-sm text-muted-foreground">
                    Default expense account used when posting receipts and
                    supplier invoices.
                </p>
                <div className="divide-y border-y">
                    {categories.map((category) => (
                        <Form
                            key={category.id}
                            action={`/accounting/mappings/expense-categories/${category.id}`}
                            method="patch"
                            className="grid gap-2 py-3 sm:grid-cols-[minmax(10rem,1fr)_minmax(16rem,1.5fr)_auto]"
                        >
                            <div>
                                <div className="font-medium">
                                    {category.name}
                                </div>
                                <div className="font-mono text-xs text-muted-foreground">
                                    {category.code}
                                </div>
                            </div>
                            <AccountSelect
                                name="expense_account_code"
                                value={category.expense_account_code || '629'}
                                accounts={expenseAccounts}
                            />
                            <Button size="sm" variant="outline">
                                Save
                            </Button>
                        </Form>
                    ))}
                </div>
            </section>
            <section>
                <h2 className="mb-1 text-base font-semibold">VAT rates</h2>
                <p className="mb-3 text-sm text-muted-foreground">
                    Output and input VAT accounts used by automatic tax
                    postings.
                </p>
                <div className="divide-y border-y">
                    {taxRates.map((tax) => (
                        <Form
                            key={tax.id}
                            action={`/accounting/mappings/tax-rates/${tax.id}`}
                            method="patch"
                            className="grid gap-2 py-3"
                        >
                            <div className="flex items-center justify-between">
                                <strong>{tax.name}</strong>
                                <Badge variant="outline">{tax.rate}%</Badge>
                            </div>
                            <div className="grid gap-2 sm:grid-cols-2">
                                <AccountSelect
                                    name="output_account_code"
                                    value={tax.output_account_code || '477'}
                                    accounts={accounts}
                                />
                                <AccountSelect
                                    name="input_account_code"
                                    value={tax.input_account_code || '472'}
                                    accounts={accounts}
                                />
                            </div>
                            <Button
                                size="sm"
                                variant="outline"
                                className="justify-self-end"
                            >
                                Save mapping
                            </Button>
                        </Form>
                    ))}
                </div>
            </section>
        </div>
    );
}

function AccountSelect({
    name,
    value,
    accounts,
}: {
    name: string;
    value: string;
    accounts: Account[];
}) {
    return (
        <Select name={name} defaultValue={value}>
            <SelectTrigger>
                <SelectValue />
            </SelectTrigger>
            <SelectContent>
                {accounts.map((account) => (
                    <SelectItem key={account.id} value={account.code}>
                        {account.code} · {account.name}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}

function ThirdPartyBalances({
    year,
    customers,
    suppliers,
}: {
    year: number;
    customers: PartyBalance[];
    suppliers: PartyBalance[];
}) {
    return (
        <div className="grid gap-8">
            <PartyTable title="Customers" year={year} rows={customers} />
            <PartyTable title="Suppliers" year={year} rows={suppliers} />
        </div>
    );
}
function PartyTable({
    title,
    year,
    rows,
}: {
    title: string;
    year: number;
    rows: PartyBalance[];
}) {
    return (
        <section className="overflow-x-auto">
            <h2 className="mb-3 text-base font-semibold">{title}</h2>
            <table className="w-full min-w-[70rem] text-sm">
                <thead>
                    <tr className="border-b text-left text-muted-foreground">
                        <th className="py-2">Account</th>
                        <th>Third party</th>
                        <th className="text-right">Ledger balance</th>
                        <th className="text-right">Outstanding</th>
                        <th className="text-right">Current</th>
                        <th className="text-right">1–30</th>
                        <th className="text-right">31–60</th>
                        <th className="text-right">61–90</th>
                        <th className="text-right">90+</th>
                        <th className="text-right">Difference</th>
                    </tr>
                </thead>
                <tbody>
                    {rows.map((row) => (
                        <tr key={`${row.kind}-${row.id}`} className="border-b">
                            <td className="py-2">
                                <Link
                                    className="font-mono text-primary hover:underline"
                                    href={`/accounting?year=${year}&account=${row.accountCode}`}
                                >
                                    {row.accountCode}
                                </Link>
                            </td>
                            <td className="font-medium">{row.name}</td>
                            <td className="text-right">
                                {money(row.ledgerBalance)}
                            </td>
                            <td className="text-right font-medium">
                                {money(row.outstanding)}
                            </td>
                            <td className="text-right">{money(row.current)}</td>
                            <td className="text-right">{money(row.days30)}</td>
                            <td className="text-right">{money(row.days60)}</td>
                            <td className="text-right">{money(row.days90)}</td>
                            <td className="text-right">{money(row.older)}</td>
                            <td
                                className={`text-right ${Math.abs(row.ledgerBalance - row.outstanding) >= 0.01 ? 'text-destructive' : 'text-muted-foreground'}`}
                            >
                                {money(row.ledgerBalance - row.outstanding)}
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
            {rows.length === 0 && (
                <Empty
                    text={`No ${title.toLowerCase()} with outstanding balances.`}
                />
            )}
        </section>
    );
}

function ReportTable({
    title,
    headers,
    rows,
    footer,
    note,
}: {
    title: string;
    headers: string[];
    rows: React.ReactNode[][];
    footer: React.ReactNode[];
    note?: string;
}) {
    return (
        <section className="max-w-5xl overflow-x-auto">
            <div className="mb-3 flex items-center justify-between">
                <h2 className="text-base font-semibold">{title}</h2>
                {note && <strong className="text-sm">{note}</strong>}
            </div>
            <table className="w-full min-w-[42rem] text-sm">
                <thead>
                    <tr className="border-b text-left text-muted-foreground">
                        {headers.map((header, index) => (
                            <th
                                key={header}
                                className={`py-2 ${index >= 2 ? 'text-right' : ''}`}
                            >
                                {header}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody>
                    {rows.map((row, index) => (
                        <tr key={index} className="border-b">
                            {row.map((cell, cellIndex) => (
                                <td
                                    key={cellIndex}
                                    className={`py-2 ${cellIndex >= 2 ? 'text-right' : ''}`}
                                >
                                    {cell}
                                </td>
                            ))}
                        </tr>
                    ))}
                </tbody>
                <tfoot>
                    <tr className="border-t-2 font-semibold">
                        {footer.map((cell, index) => (
                            <td
                                key={index}
                                className={`py-3 ${index >= 2 ? 'text-right' : ''}`}
                            >
                                {cell}
                            </td>
                        ))}
                    </tr>
                </tfoot>
            </table>
            {rows.length === 0 && <Empty />}
        </section>
    );
}
function Metric({ label, value }: { label: string; value: string }) {
    return (
        <div className="px-4 py-4">
            <div className="text-xs text-muted-foreground">{label}</div>
            <div className="mt-1 text-lg font-semibold">{value}</div>
        </div>
    );
}
function Empty({
    text = 'No accounting movements for this year.',
}: {
    text?: string;
}) {
    return (
        <p className="border-b py-8 text-center text-sm text-muted-foreground">
            {text}
        </p>
    );
}
function sum(accounts: Account[], field: 'debit' | 'credit') {
    return accounts.reduce((total, account) => total + account[field], 0);
}
function amount(value: string) {
    return Number(value) ? money(Number(value)) : '';
}
function money(value: number) {
    return new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: 'EUR',
    }).format(value);
}
function date(value: string) {
    return new Intl.DateTimeFormat(undefined, { dateStyle: 'short' }).format(
        new Date(`${value.slice(0, 10)}T00:00:00`),
    );
}

AccountingIndex.layout = {
    breadcrumbs: [
        { title: 'Finance', href: '/finance' },
        { title: 'Accounting', href: '/accounting' },
    ],
};

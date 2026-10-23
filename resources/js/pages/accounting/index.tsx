import { Form, Head, Link, router, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    CalendarRange,
    Check,
    Search,
    Download,
    FileText,
    Landmark,
    LockKeyhole,
    Plus,
    RefreshCw,
    RotateCcw,
    Trash2,
    UnlockKeyhole,
} from 'lucide-react';
import { useState } from 'react';
import type React from 'react';
import { PageHeader, PageShell, PageTabs } from '@/components/page-shell';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import { Textarea } from '@/components/ui/textarea';
import type { JournalDetailExtension } from '@/types/accounting-journal';

type Line = {
    id: number;
    debit: string;
    credit: string;
    running_balance?: number;
    description?: string;
    account: { code: string; name: string };
    entry?: { number: string; entry_date: string; description: string };
};
type Entry = JournalDetailExtension & {
    id: number;
    number: string;
    entry_date: string;
    description: string;
    event: string;
    entry_kind: string;
    status: string;
    reversal: { id: number } | null;
    source_href: string | null;
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
type AccountingPeriod = {
    id: number;
    month: number;
    status: 'open' | 'closed';
    checklist: Record<string, boolean | number> | null;
    closure_notes: string | null;
    closed_at: string | null;
    closer: { name: string } | null;
    reopened_at: string | null;
    reopen_reason: string | null;
    reopener: { name: string } | null;
};
type PeriodSummary = {
    journalEntries: number;
    unbalancedEntries: number;
    unpostedItems: number;
    unreconciledBankTransactions: number;
};
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
    kind: 'customer' | 'supplier' | 'supplier-services';
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
    selectedMonth,
    accountingPeriods,
    periodSummary,
    canClosePeriods,
    entries,
    selectedFinancialCategory,
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
    selectedMonth: number;
    accountingPeriods: AccountingPeriod[];
    periodSummary: PeriodSummary;
    canClosePeriods: boolean;
    entries: Entry[];
    selectedFinancialCategory: {
        id: number;
        code: string;
        name: string;
        reporting_group: string;
    } | null;
    accounts: Account[];
    selectedAccount: Account | null;
    ledger: Line[];
    reports: Reports;
    summary: { entries: number; debit: number; credit: number };
    expenseCategories: ExpenseCategory[];
    taxRates: TaxRate[];
    thirdParties: { customers: PartyBalance[]; suppliers: PartyBalance[] };
}) {
    const page = usePage();
    const requestedView = new URL(
        page.url,
        'http://localhost',
    ).searchParams.get('view');
    const views: View[] = [
        'journal',
        'trial',
        'profit',
        'balance',
        'ledger',
        'parties',
        'mappings',
    ];
    const entryManagement =
        new URL(page.url, 'http://localhost').searchParams.get('area') ===
        'entries';
    const view: View = views.includes(requestedView as View)
        ? (requestedView as View)
        : selectedAccount
          ? 'ledger'
          : 'journal';
    const navigate = (params: Record<string, string | number>) =>
        router.get(
            '/accounting',
            {
                year,
                view,
                ...Object.fromEntries(
                    [
                        ...new URL(page.url, 'http://localhost').searchParams,
                    ].filter(([key]) => ['area', 'entry_status'].includes(key)),
                ),
                ...params,
            },
            { preserveState: true },
        );

    const renderHeader = (exportAction?: React.ReactNode) => (
        <PageHeader
            title={
                <>
                    {view === 'journal'
                        ? entryManagement
                            ? 'Asientos'
                            : 'Libro Diario'
                        : ((
                              {
                                  trial: 'Balance de sumas y saldos',
                                  profit: 'Pérdidas y ganancias',
                                  balance: 'Balance de situación',
                                  ledger: 'Libro Mayor',
                                  parties: 'Saldos de terceros',
                                  mappings: 'Mapeos contables',
                              } as Record<string, string>
                          )[view] ?? 'Vista general')}
                </>
            }
            description={
                <>
                    {view === 'journal'
                        ? entryManagement
                            ? 'Gestiona y consulta los movimientos contables de tu empresa.'
                            : 'Registro cronológico de todos los asientos contables.'
                        : 'Journal, general ledger and statutory financial reports.'}
                </>
            }
            primaryAction={
                <>
                    {page.props.auth.abilities.manageAccounting &&
                        fiscalYear?.status === 'open' && (
                            <ManualEntryDialog
                                accounts={accounts}
                                year={year}
                                month={selectedMonth}
                            />
                        )}
                </>
            }
            secondaryActions={
                <>
                    {exportAction}
                    <Dialog>
                        <DialogTrigger asChild>
                            <Button variant="outline">Más opciones ···</Button>
                        </DialogTrigger>
                        <DialogContent className="max-h-[85vh] overflow-y-auto sm:max-w-3xl">
                            <DialogHeader>
                                <DialogTitle>Opciones contables</DialogTitle>
                            </DialogHeader>
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
                                <Button variant="outline" asChild>
                                    <Link href="/company-fiscal">
                                        <CalendarRange /> Fiscal years
                                    </Link>
                                </Button>
                                <Form action="/accounting/sync" method="post">
                                    <Button>
                                        <RefreshCw /> Sync now
                                    </Button>
                                </Form>
                            </div>
                            <PageTabs aria-label="Accounting views">
                                {(
                                    [
                                        ['journal', 'Libro Diario'],
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
                                        onClick={() => {
                                            navigate({ view: key });
                                        }}
                                        className={`border-b-2 px-3 py-2 text-sm font-medium whitespace-nowrap ${view === key ? 'border-primary text-foreground' : 'border-transparent text-muted-foreground hover:text-foreground'}`}
                                    >
                                        {label}
                                    </button>
                                ))}
                            </PageTabs>
                            <p className="text-sm text-muted-foreground">
                                {summary.entries} asientos · Debe{' '}
                                {money(summary.debit)} · Haber{' '}
                                {money(summary.credit)}
                            </p>
                            <MonthlyClose
                                view={view}
                                year={year}
                                month={selectedMonth}
                                periods={accountingPeriods}
                                summary={periodSummary}
                                canManage={canClosePeriods}
                                fiscalYearOpen={fiscalYear?.status === 'open'}
                            />
                        </DialogContent>
                    </Dialog>
                    <Select
                        value={String(year)}
                        onValueChange={(value) =>
                            router.get(
                                '/accounting',
                                {
                                    year: value,
                                    view,
                                    ...Object.fromEntries(
                                        [
                                            ...new URL(
                                                page.url,
                                                'http://localhost',
                                            ).searchParams,
                                        ].filter(([key]) =>
                                            ['area', 'entry_status'].includes(
                                                key,
                                            ),
                                        ),
                                    ),
                                },
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
                                <SelectItem key={value} value={String(value)}>
                                    {value}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </>
            }
        />
    );

    return (
        <>
            <Head
                title={
                    view === 'journal'
                        ? entryManagement
                            ? 'Asientos'
                            : 'Libro Diario'
                        : 'Accounting'
                }
            />
            <PageShell mode="full">
                {view !== 'journal' && renderHeader()}
                {selectedFinancialCategory && (
                    <div className="flex flex-wrap items-center justify-between gap-3 rounded-md border bg-muted/30 px-4 py-3 text-sm">
                        <div>
                            Showing journal evidence for{' '}
                            <span className="font-semibold">
                                {selectedFinancialCategory.name}
                            </span>{' '}
                            <span className="font-mono text-xs text-muted-foreground">
                                {selectedFinancialCategory.code}
                            </span>
                        </div>
                        <Button variant="outline" size="sm" asChild>
                            <Link href={`/accounting?year=${year}`}>
                                Clear taxonomy filter
                            </Link>
                        </Button>
                    </div>
                )}
                {view === 'journal' && (
                    <Journal
                        renderHeader={renderHeader}
                        entries={entries}
                        canReverse={fiscalYear?.status === 'open'}
                        accounts={accounts}
                        year={year}
                    />
                )}
                {view === 'trial' && (
                    <TrialBalance
                        accounts={accounts}
                        onAccount={(code) => {
                            navigate({ account: code, view: 'ledger' });
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
                        onAccount={(code) =>
                            navigate({ account: code, view: 'ledger' })
                        }
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
            </PageShell>
        </>
    );
}

function MonthlyClose({
    view,
    year,
    month,
    periods,
    summary,
    canManage,
    fiscalYearOpen,
}: {
    year: number;
    month: number;
    periods: AccountingPeriod[];
    summary: PeriodSummary;
    canManage: boolean;
    fiscalYearOpen: boolean;
    view: View;
}) {
    const period = periods.find((item) => item.month === month);
    const isClosed = period?.status === 'closed';
    const hasExceptions =
        summary.unbalancedEntries > 0 ||
        summary.unpostedItems > 0 ||
        summary.unreconciledBankTransactions > 0;
    const periodLabel = `${year}-${String(month).padStart(2, '0')}`;

    return (
        <section className="grid gap-4 rounded-lg border p-4">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <div className="flex items-center gap-2">
                        <h2 className="font-semibold">Monthly close</h2>
                        <Badge variant={isClosed ? 'secondary' : 'outline'}>
                            {isClosed ? 'Closed and locked' : 'Open'}
                        </Badge>
                    </div>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Review accounting exceptions and preserve an audited
                        closing trail.
                    </p>
                </div>
                {period?.reopened_at && (
                    <p className="max-w-md text-xs text-muted-foreground">
                        Last reopened by {period.reopener?.name ?? 'Unknown'} on{' '}
                        {dateTime(period.reopened_at)}: {period.reopen_reason}
                    </p>
                )}
            </div>
            <div className="flex flex-wrap gap-1">
                {Array.from({ length: 12 }, (_, index) => index + 1).map(
                    (value) => {
                        const item = periods.find(
                            (candidate) => candidate.month === value,
                        );

                        return (
                            <button
                                key={value}
                                type="button"
                                onClick={() =>
                                    router.get(
                                        '/accounting',
                                        { year, month: value, view },
                                        {
                                            preserveState: true,
                                            preserveScroll: true,
                                        },
                                    )
                                }
                                className={`rounded-md border px-3 py-1.5 text-xs font-medium transition-colors ${month === value ? 'border-primary bg-primary text-primary-foreground' : item?.status === 'closed' ? 'bg-muted text-muted-foreground' : 'hover:bg-muted'}`}
                            >
                                {monthName(value)}
                                {item?.status === 'closed' ? ' · locked' : ''}
                            </button>
                        );
                    },
                )}
            </div>
            <div className="grid gap-px overflow-hidden rounded-md border bg-border sm:grid-cols-2 lg:grid-cols-4">
                <CloseMetric
                    label="Journal entries"
                    value={summary.journalEntries}
                />
                <CloseMetric
                    label="Unposted items"
                    value={summary.unpostedItems}
                    exception={summary.unpostedItems > 0}
                />
                <CloseMetric
                    label="Unbalanced entries"
                    value={summary.unbalancedEntries}
                    exception={summary.unbalancedEntries > 0}
                />
                <CloseMetric
                    label="Bank items pending"
                    value={summary.unreconciledBankTransactions}
                    exception={summary.unreconciledBankTransactions > 0}
                />
            </div>
            {isClosed ? (
                <div className="grid gap-4 lg:grid-cols-2">
                    <div className="rounded-md bg-muted/50 p-4 text-sm">
                        <p className="flex items-center gap-2 font-medium">
                            <LockKeyhole className="size-4" /> {periodLabel} is
                            locked
                        </p>
                        <p className="mt-2 text-muted-foreground">
                            Closed by {period.closer?.name ?? 'Unknown'} on{' '}
                            {period.closed_at
                                ? dateTime(period.closed_at)
                                : 'Unknown'}
                            .
                        </p>
                        {period.closure_notes && (
                            <p className="mt-2">{period.closure_notes}</p>
                        )}
                    </div>
                    {canManage && (
                        <Form
                            action={`/accounting/periods/${year}/${month}/reopen`}
                            method="post"
                            className="grid gap-3"
                        >
                            {({ errors, processing }) => (
                                <>
                                    <div className="grid gap-1">
                                        <Label htmlFor="reopen_reason">
                                            Audited reopening reason
                                        </Label>
                                        <Textarea
                                            id="reopen_reason"
                                            name="reopen_reason"
                                            required
                                            minLength={5}
                                            placeholder="Explain why this period must be changed"
                                        />
                                        {(errors.reopen_reason ||
                                            errors.period) && (
                                            <p className="text-xs text-destructive">
                                                {errors.reopen_reason ??
                                                    errors.period}
                                            </p>
                                        )}
                                    </div>
                                    <Button
                                        variant="outline"
                                        disabled={processing}
                                    >
                                        <UnlockKeyhole /> Reopen period
                                    </Button>
                                </>
                            )}
                        </Form>
                    )}
                </div>
            ) : canManage && fiscalYearOpen ? (
                <Form
                    action={`/accounting/periods/${year}/${month}/close`}
                    method="post"
                    className="grid gap-4"
                >
                    {({ errors, processing }) => (
                        <>
                            <div className="grid gap-2 md:grid-cols-2">
                                <CloseCheck
                                    id="bank_reconciled"
                                    label="Bank reconciliation reviewed"
                                />
                                <CloseCheck
                                    id="receivables_reviewed"
                                    label="Customer receivables reviewed"
                                />
                                <CloseCheck
                                    id="payables_reviewed"
                                    label="Supplier payables reviewed"
                                />
                                <CloseCheck
                                    id="tax_reviewed"
                                    label="Tax accounts reviewed"
                                />
                            </div>
                            <div className="grid gap-3 lg:grid-cols-[12rem_1fr_auto] lg:items-end">
                                <div className="grid gap-1">
                                    <Label htmlFor="confirmation">
                                        Type {periodLabel}
                                    </Label>
                                    <Input
                                        id="confirmation"
                                        name="confirmation"
                                        required
                                        placeholder={periodLabel}
                                    />
                                </div>
                                <div className="grid gap-1">
                                    <Label htmlFor="closure_notes">
                                        Closing notes
                                    </Label>
                                    <Input
                                        id="closure_notes"
                                        name="closure_notes"
                                        placeholder="Optional context for the audit trail"
                                    />
                                </div>
                                <Button
                                    disabled={processing || hasExceptions}
                                    title={
                                        hasExceptions
                                            ? 'Resolve every exception before closing'
                                            : undefined
                                    }
                                >
                                    <LockKeyhole /> Close and lock
                                </Button>
                            </div>
                            {Object.keys(errors).length > 0 && (
                                <p className="text-sm text-destructive">
                                    {Object.values(errors)[0]}
                                </p>
                            )}
                            {hasExceptions && (
                                <p className="text-sm text-destructive">
                                    Resolve every unposted, unbalanced and bank
                                    reconciliation exception before closing.
                                </p>
                            )}
                        </>
                    )}
                </Form>
            ) : (
                <p className="text-sm text-muted-foreground">
                    {fiscalYearOpen
                        ? 'Only administrators can close or reopen accounting periods.'
                        : 'The fiscal year is closed.'}
                </p>
            )}
        </section>
    );
}

function CloseMetric({
    label,
    value,
    exception = false,
}: {
    label: string;
    value: number;
    exception?: boolean;
}) {
    return (
        <div className="bg-background p-3">
            <p className="text-xs text-muted-foreground">{label}</p>
            <p
                className={`mt-1 text-lg font-semibold ${exception ? 'text-destructive' : ''}`}
            >
                {value}
            </p>
        </div>
    );
}

function CloseCheck({ id, label }: { id: string; label: string }) {
    return (
        <label
            htmlFor={id}
            className="flex items-center gap-2 rounded-md border p-3 text-sm"
        >
            <Checkbox id={id} name={id} value="1" />
            {label}
        </label>
    );
}

function ManualEntryDialog({
    accounts,
    year,
    month,
}: {
    accounts: Account[];
    year: number;
    month: number;
}) {
    const page = usePage();
    const createRequested =
        new URL(page.url, 'http://localhost').searchParams.get('create') ===
        '1';
    const [lines, setLines] = useState([0, 1]);

    return (
        <Dialog defaultOpen={createRequested}>
            <DialogTrigger asChild>
                <Button className="shell-new-entry">
                    <Plus /> Nuevo asiento
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
                                defaultValue={`${year}-${String(month).padStart(2, '0')}-01`}
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
    renderHeader,
    entries,
    canReverse,
    accounts,
    year,
}: {
    renderHeader: (exportAction: React.ReactNode) => React.ReactNode;
    entries: Entry[];
    canReverse: boolean;
    accounts: Account[];
    year: number;
}) {
    const page = usePage();
    const requestedEntry = Number(
        new URL(page.url, 'http://localhost').searchParams.get('entry'),
    );
    const [selectedId, setSelectedId] = useState<number | null>(
        requestedEntry > 0 ? requestedEntry : null,
    );
    const [search, setSearch] = useState('');
    const [account, setAccount] = useState('all');
    const [kind, setKind] = useState('all');
    const [journal, setJournal] = useState('all');
    const journals = Array.from(
        new Map(
            entries.flatMap((entry) =>
                entry.journal
                    ? [[entry.journal.id, entry.journal] as const]
                    : [],
            ),
        ).values(),
    );
    const [start, setStart] = useState('');
    const [end, setEnd] = useState('');
    const [tab, setTab] = useState('detail');
    const entryStatus = new URL(page.url, 'http://localhost').searchParams.get(
        'entry_status',
    );
    const filtered = entries.filter(
        (entry) =>
            (entryStatus !== 'draft' || entry.status === 'draft') &&
            (!start || entry.entry_date.slice(0, 10) >= start) &&
            (!end || entry.entry_date.slice(0, 10) <= end) &&
            (kind === 'all' || entry.entry_kind === kind) &&
            (journal === 'all' || entry.journal?.id === journal) &&
            (account === 'all' ||
                entry.lines.some((line) => line.account.code === account)) &&
            `${entry.number} ${entry.description} ${entry.lines.map((line) => `${line.account.code} ${line.account.name} ${line.debit} ${line.credit}`).join(' ')}`
                .toLocaleLowerCase()
                .includes(search.toLocaleLowerCase()),
    );
    const selected =
        filtered.find((entry) => entry.id === selectedId) ?? filtered[0];
    const total = (entry: Entry, field: 'debit' | 'credit') =>
        entry.lines.reduce((value, line) => value + Number(line[field]), 0);
    const status = (entry: Entry) =>
        entry.reversal
            ? 'Revertido'
            : entry.status === 'posted'
              ? 'Contabilizado'
              : entry.status;
    const exportEntries = () => {
        const rows = [
            ['Fecha', 'Asiento', 'Concepto', 'Tipo', 'Debe', 'Haber'],
            ...filtered.map((entry) => [
                entry.entry_date.slice(0, 10),
                entry.number,
                entry.description,
                entry.entry_kind,
                total(entry, 'debit').toFixed(2),
                total(entry, 'credit').toFixed(2),
            ]),
        ];
        const blob = new Blob(
            [
                '\ufeff' +
                    rows
                        .map((row) =>
                            row
                                .map(
                                    (cell) =>
                                        `"${String(cell).replaceAll('"', '""')}"`,
                                )
                                .join(';'),
                        )
                        .join('\r\n'),
            ],
            { type: 'text/csv;charset=utf-8;' },
        );
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = `libro-diario-${year}.csv`;
        link.click();
        URL.revokeObjectURL(url);
    };

    return (
        <section data-journal-design className="space-y-6">
            {renderHeader(
                <Button variant="outline" onClick={exportEntries}>
                    <Download />
                    Exportar
                </Button>,
            )}
            <div className="flex flex-wrap items-center gap-3">
                <div className="flex items-center gap-2 rounded-md border px-3 py-1">
                    <CalendarRange className="size-4 shrink-0" />
                    <Input
                        aria-label="Fecha inicial"
                        type="date"
                        className="w-36 border-0 shadow-none"
                        value={start}
                        onChange={(event) => setStart(event.target.value)}
                    />
                    <span>→</span>
                    <Input
                        aria-label="Fecha final"
                        type="date"
                        className="w-36 border-0 shadow-none"
                        value={end}
                        onChange={(event) => setEnd(event.target.value)}
                    />
                </div>
                <Select
                    value={journal}
                    onValueChange={setJournal}
                    disabled={!journals.length}
                >
                    <SelectTrigger className="w-44" aria-label="Diario">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">Todos los diarios</SelectItem>
                        {journals.map((item) => (
                            <SelectItem key={item.id} value={item.id}>
                                {item.name}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <Select value={account} onValueChange={setAccount}>
                    <SelectTrigger className="w-48">
                        <SelectValue placeholder="Todas las cuentas" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">Todas las cuentas</SelectItem>
                        {accounts.map((item) => (
                            <SelectItem key={item.id} value={item.code}>
                                {item.code} · {item.name}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <Select value={kind} onValueChange={setKind}>
                    <SelectTrigger className="w-40">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">Todos los tipos</SelectItem>
                        {Array.from(
                            new Set(entries.map((entry) => entry.entry_kind)),
                        ).map((value) => (
                            <SelectItem key={value} value={value}>
                                {value}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <div className="relative min-w-48 flex-1">
                    <Search className="absolute top-3 left-3 size-4 text-muted-foreground" />
                    <Input
                        className="pl-9"
                        placeholder="Buscar asientos, cuentas, importes…"
                        aria-label="Buscar asientos"
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                    />
                </div>
            </div>
            <div className="grid border-t xl:grid-cols-[minmax(0,1.08fr)_minmax(0,1fr)]">
                <div className="min-w-0 overflow-x-auto pt-3 xl:pr-5">
                    <table className="w-full min-w-[620px] text-xs">
                        <thead>
                            <tr className="text-left text-[10px] tracking-wide text-muted-foreground uppercase">
                                {[
                                    'Fecha',
                                    'Asiento',
                                    'Concepto',
                                    'Diario',
                                    'Debe',
                                    'Haber',
                                    'Estado',
                                ].map((label) => (
                                    <th
                                        className="px-3 py-3 font-medium"
                                        key={label}
                                    >
                                        {label}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {filtered.map((entry) => (
                                <tr
                                    key={entry.id}
                                    className={`border-b border-border/50 transition-colors ${selected?.id === entry.id ? 'bg-[#e8f3e9] dark:bg-accent' : 'hover:bg-muted/50'}`}
                                >
                                    <td className="px-3 py-4 whitespace-nowrap">
                                        {date(entry.entry_date)}
                                    </td>
                                    <td className="px-3">
                                        <button
                                            className="font-medium hover:underline"
                                            onClick={() => {
                                                setSelectedId(entry.id);
                                                setTab('detail');
                                            }}
                                            aria-pressed={
                                                selected?.id === entry.id
                                            }
                                        >
                                            {entry.number}
                                        </button>
                                    </td>
                                    <td className="max-w-52 px-3">
                                        <button
                                            className="w-full text-left"
                                            onClick={() => {
                                                setSelectedId(entry.id);
                                                setTab('detail');
                                            }}
                                        >
                                            {entry.description}
                                        </button>
                                    </td>
                                    <td className="px-3">
                                        {entry.journal?.name ?? '—'}
                                    </td>
                                    <td className="px-3 text-right whitespace-nowrap tabular-nums">
                                        {money(total(entry, 'debit'))}
                                    </td>
                                    <td className="px-3 text-right whitespace-nowrap tabular-nums">
                                        {money(total(entry, 'credit'))}
                                    </td>
                                    <td className="px-3 text-center">
                                        <span title={status(entry)}>
                                            {entry.status === 'posted' &&
                                            !entry.reversal ? (
                                                <Check className="mx-auto size-4 text-emerald-700" />
                                            ) : (
                                                status(entry)
                                            )}
                                        </span>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    {!filtered.length && (
                        <Empty text="No hay asientos que coincidan con los filtros." />
                    )}
                    <p className="py-4 text-xs text-muted-foreground">
                        {filtered.length} de {entries.length} asientos cargados
                    </p>
                </div>
                <aside className="min-w-0 border-t py-6 xl:border-t-0 xl:border-l xl:pl-7">
                    {selected ? (
                        <>
                            <div className="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <p className="text-xs font-medium tracking-wider text-muted-foreground">
                                        ASIENTO CONTABLE
                                    </p>
                                    <h2 className="mt-1 text-2xl font-semibold tracking-tight">
                                        Asiento {selected.number}
                                    </h2>
                                </div>
                                <Badge
                                    variant="outline"
                                    className="border-emerald-200 bg-emerald-50 text-emerald-800"
                                >
                                    {selected.status === 'posted' && (
                                        <Check className="mr-1 size-3" />
                                    )}
                                    {status(selected)}
                                </Badge>
                            </div>
                            <div className="mt-4 flex justify-end gap-2">
                                {selected.source_href && (
                                    <Button variant="outline" size="sm" asChild>
                                        <Link href={selected.source_href}>
                                            <FileText />
                                            Abrir documento
                                        </Link>
                                    </Button>
                                )}
                                {canReverse &&
                                    !selected.reversal &&
                                    selected.entry_kind !== 'reversal' && (
                                        <Form
                                            action={`/accounting/entries/${selected.id}/reverse`}
                                            method="post"
                                        >
                                            <input
                                                type="hidden"
                                                name="entry_date"
                                                value={new Date().toLocaleDateString(
                                                    'en-CA',
                                                )}
                                            />
                                            <Button size="sm" variant="outline">
                                                <RotateCcw />
                                                Revertir asiento
                                            </Button>
                                        </Form>
                                    )}
                            </div>
                            <div className="mt-3 flex gap-6 border-b">
                                {[
                                    ['detail', 'Detalle'],
                                    [
                                        'documents',
                                        `Documentos${selected.documents ? ` (${selected.documents.length})` : ''}`,
                                    ],
                                    ['history', 'Historial'],
                                ].map(([key, label]) => (
                                    <button
                                        key={key}
                                        onClick={() => setTab(key)}
                                        className={`border-b-2 px-3 py-3 text-sm ${tab === key ? 'border-emerald-700 font-medium' : 'border-transparent text-muted-foreground'}`}
                                    >
                                        {label}
                                    </button>
                                ))}
                            </div>
                            {tab === 'detail' ? (
                                <div className="space-y-6 pt-5">
                                    <section>
                                        <h3 className="mb-3 font-semibold">
                                            Información general
                                        </h3>
                                        <div className="rounded-md border p-4">
                                            <dl className="grid grid-cols-2 gap-4 text-sm sm:grid-cols-3">
                                                <div>
                                                    <dt className="text-xs text-muted-foreground">
                                                        Fecha
                                                    </dt>
                                                    <dd className="mt-1">
                                                        {date(
                                                            selected.entry_date,
                                                        )}
                                                    </dd>
                                                </div>
                                                <div>
                                                    <dt className="text-xs text-muted-foreground">
                                                        Diario
                                                    </dt>
                                                    <dd className="mt-1">
                                                        {selected.journal
                                                            ?.name ?? '—'}
                                                    </dd>
                                                </div>
                                                <div>
                                                    <dt className="text-xs text-muted-foreground">
                                                        Tipo
                                                    </dt>
                                                    <dd className="mt-1">
                                                        {selected.entry_kind}
                                                    </dd>
                                                </div>
                                                <div>
                                                    <dt className="text-xs text-muted-foreground">
                                                        Estado
                                                    </dt>
                                                    <dd className="mt-1">
                                                        {status(selected)}
                                                    </dd>
                                                </div>
                                                <div className="col-span-full">
                                                    <dt className="text-xs text-muted-foreground">
                                                        Concepto
                                                    </dt>
                                                    <dd className="mt-1">
                                                        {selected.description}
                                                    </dd>
                                                </div>
                                            </dl>
                                        </div>
                                    </section>
                                    <section>
                                        <h3 className="mb-3 font-semibold">
                                            Líneas del asiento
                                        </h3>
                                        <div className="overflow-x-auto rounded-md border">
                                            <table className="w-full text-xs">
                                                <thead className="bg-muted/40 text-[10px] uppercase">
                                                    <tr>
                                                        <th className="p-3 text-left">
                                                            Cuenta
                                                        </th>
                                                        <th className="p-3 text-left">
                                                            Descripción
                                                        </th>
                                                        <th className="p-3 text-right">
                                                            Debe
                                                        </th>
                                                        <th className="p-3 text-right">
                                                            Haber
                                                        </th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    {selected.lines.map(
                                                        (line) => (
                                                            <tr
                                                                key={line.id}
                                                                className="border-t"
                                                            >
                                                                <td className="p-3">
                                                                    {
                                                                        line
                                                                            .account
                                                                            .code
                                                                    }
                                                                </td>
                                                                <td className="p-3">
                                                                    {line.description ||
                                                                        line
                                                                            .account
                                                                            .name}
                                                                </td>
                                                                <td className="p-3 text-right whitespace-nowrap">
                                                                    {amount(
                                                                        line.debit,
                                                                    ) || '—'}
                                                                </td>
                                                                <td className="p-3 text-right whitespace-nowrap">
                                                                    {amount(
                                                                        line.credit,
                                                                    ) || '—'}
                                                                </td>
                                                            </tr>
                                                        ),
                                                    )}
                                                </tbody>
                                                <tfoot className="bg-[#e8f3e9] font-semibold dark:bg-accent">
                                                    <tr>
                                                        <td
                                                            colSpan={2}
                                                            className="p-3"
                                                        >
                                                            TOTALES
                                                        </td>
                                                        <td className="p-3 text-right whitespace-nowrap">
                                                            {money(
                                                                total(
                                                                    selected,
                                                                    'debit',
                                                                ),
                                                            )}
                                                        </td>
                                                        <td className="p-3 text-right whitespace-nowrap">
                                                            {money(
                                                                total(
                                                                    selected,
                                                                    'credit',
                                                                ),
                                                            )}
                                                        </td>
                                                    </tr>
                                                </tfoot>
                                            </table>
                                        </div>
                                    </section>
                                    <section>
                                        <h3 className="mb-3 font-semibold">
                                            Observaciones
                                        </h3>
                                        <p className="min-h-16 rounded-md border p-4 text-sm text-muted-foreground">
                                            {selected.notes === undefined
                                                ? 'Observaciones no disponibles.'
                                                : selected.notes ||
                                                  'Sin observaciones.'}
                                        </p>
                                    </section>
                                    <section>
                                        <h3 className="mb-3 font-semibold">
                                            Documentos adjuntos
                                            {selected.documents
                                                ? ` (${selected.documents.length})`
                                                : ''}
                                        </h3>
                                        <JournalDocuments entry={selected} />
                                    </section>
                                </div>
                            ) : tab === 'documents' ? (
                                <div className="py-6">
                                    <JournalDocuments entry={selected} />
                                </div>
                            ) : (
                                <div className="py-6">
                                    {selected.history === undefined ? (
                                        <Empty text="Historial no disponible." />
                                    ) : selected.history.length === 0 ? (
                                        <Empty text="Sin eventos registrados." />
                                    ) : (
                                        <ol className="space-y-4">
                                            {selected.history.map((event) => (
                                                <li
                                                    key={event.id}
                                                    className="border-l-2 border-emerald-200 pl-4"
                                                >
                                                    <p className="text-sm">
                                                        {event.description}
                                                    </p>
                                                    <p className="mt-1 text-xs text-muted-foreground">
                                                        {dateTime(
                                                            event.occurred_at,
                                                        )}{' '}
                                                        ·{' '}
                                                        {event.actor_name ??
                                                            'Sistema'}
                                                    </p>
                                                </li>
                                            ))}
                                        </ol>
                                    )}
                                </div>
                            )}
                        </>
                    ) : (
                        <Empty text="Selecciona un asiento para consultar su detalle." />
                    )}
                </aside>
            </div>
        </section>
    );
}

function JournalDocuments({ entry }: { entry: Entry }) {
    return (
        <div className="space-y-3">
            {entry.documents === undefined ? (
                <p className="text-sm text-muted-foreground">
                    Listado de adjuntos no disponible.
                </p>
            ) : entry.documents.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    Sin documentos adjuntos.
                </p>
            ) : (
                entry.documents.map((document) => (
                    <div
                        key={document.id}
                        className="flex items-center gap-3 rounded-md border p-4"
                    >
                        <FileText className="size-7 shrink-0 text-emerald-700" />
                        <div className="min-w-0 flex-1">
                            <p className="truncate text-sm font-medium">
                                {document.name}
                            </p>
                            <p className="mt-1 text-xs text-muted-foreground">
                                {new Intl.NumberFormat('es-ES', {
                                    maximumFractionDigits: 1,
                                }).format(document.size_bytes / 1024)}{' '}
                                KB · {dateTime(document.attached_at)}
                            </p>
                        </div>
                        {document.preview_href && (
                            <Button variant="ghost" size="sm" asChild>
                                <a
                                    href={document.preview_href}
                                    target="_blank"
                                    rel="noreferrer"
                                    aria-label={`Ver ${document.name}`}
                                >
                                    Ver
                                </a>
                            </Button>
                        )}
                        {document.download_href && (
                            <Button variant="ghost" size="icon" asChild>
                                <a
                                    href={document.download_href}
                                    aria-label={`Descargar ${document.name}`}
                                >
                                    <Download />
                                </a>
                            </Button>
                        )}
                    </div>
                ))
            )}
            {entry.source_href && (
                <Link
                    href={entry.source_href}
                    className="flex items-center gap-3 rounded-md border p-4 text-sm hover:bg-muted"
                >
                    <FileText className="size-6 text-emerald-700" />
                    Consultar documento de origen
                </Link>
            )}
        </div>
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
    return new Intl.NumberFormat('es-ES', {
        style: 'currency',
        currency: 'EUR',
    }).format(value);
}
function date(value: string) {
    return new Intl.DateTimeFormat('es-ES', { dateStyle: 'short' }).format(
        new Date(`${value.slice(0, 10)}T00:00:00`),
    );
}
function dateTime(value: string) {
    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'short',
        timeStyle: 'short',
    }).format(new Date(value));
}
function monthName(month: number) {
    return new Intl.DateTimeFormat(undefined, { month: 'short' }).format(
        new Date(2026, month - 1, 1),
    );
}

AccountingIndex.layout = {
    breadcrumbs: [
        { title: 'Finance', href: '/finance' },
        { title: 'Accounting', href: '/accounting' },
    ],
};

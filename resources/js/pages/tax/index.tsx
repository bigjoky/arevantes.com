import { Form, Head, Link, router, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    CreditCard,
    Download,
    FileCheck2,
    Scale,
} from 'lucide-react';
import { useState } from 'react';
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

type VatRow = {
    date: string;
    number: string;
    party: string;
    tax_id: string | null;
    rate: number;
    base: number;
    vat: number;
    total: number;
};
type Rate = {
    rate: number;
    issuedBase: number;
    outputVat: number;
    receivedBase: number;
    inputVat: number;
};
type Summary = {
    taxableBaseIssued: number;
    outputVat: number;
    taxableBaseReceived: number;
    deductibleVat: number;
    result: number;
};
type Check = {
    outputVat: number;
    inputVat: number;
    outputDifference: number;
    inputDifference: number;
    matches: boolean;
};
type TaxReturn = {
    id: number;
    status: string;
    settlement_status: string;
    year: number;
    quarter: number;
    result: string;
    paid_amount: string;
    prepared_at: string;
    filing_reference: string | null;
    filed_on: string | null;
    journal_entry: { number: string } | null;
    payments: {
        id: number;
        paid_on: string;
        amount: string;
        method: string;
        reference: string | null;
    }[];
};

export default function TaxIndex({
    period,
    issued,
    received,
    summary,
    rates,
    accountingCheck,
    taxReturn,
}: {
    period: { year: number; quarter: number | null; from: string; to: string };
    issued: VatRow[];
    received: VatRow[];
    summary: Summary;
    rates: Rate[];
    accountingCheck: Check;
    taxReturn: TaxReturn | null;
}) {
    const [tab, setTab] = useState<'summary' | 'issued' | 'received'>(
        'summary',
    );
    const isAdmin =
        usePage<{ auth: { user: { role: string } } }>().props.auth.user.role ===
        'admin';
    const navigate = (year: number, quarter: string) =>
        router.get(
            '/tax',
            { year, ...(quarter !== 'annual' ? { quarter } : {}) },
            { preserveState: true },
        );
    const query = `year=${period.year}${period.quarter ? `&quarter=${period.quarter}` : ''}`;

    return (
        <>
            <Head title="VAT" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <header className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold">VAT</h1>
                        <p className="text-sm text-muted-foreground">
                            Issued and received invoice registers and quarterly
                            settlement preview.
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <Button variant="outline" asChild>
                            <Link href="/accounting">
                                <ArrowLeft /> Accounting
                            </Link>
                        </Button>
                        <Select
                            value={String(period.year)}
                            onValueChange={(value) =>
                                navigate(
                                    Number(value),
                                    period.quarter
                                        ? String(period.quarter)
                                        : 'annual',
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
                                ).map((year) => (
                                    <SelectItem key={year} value={String(year)}>
                                        {year}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <Select
                            value={
                                period.quarter
                                    ? String(period.quarter)
                                    : 'annual'
                            }
                            onValueChange={(value) =>
                                navigate(period.year, value)
                            }
                        >
                            <SelectTrigger className="w-32">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="annual">Annual</SelectItem>
                                {[1, 2, 3, 4].map((quarter) => (
                                    <SelectItem
                                        key={quarter}
                                        value={String(quarter)}
                                    >
                                        Quarter {quarter}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                </header>
                <section className="grid grid-cols-2 border-y lg:grid-cols-4">
                    <Metric
                        label="Output VAT"
                        value={money(summary.outputVat)}
                    />
                    <Metric
                        label="Deductible VAT"
                        value={money(summary.deductibleVat)}
                    />
                    <Metric
                        label={
                            summary.result >= 0
                                ? 'Amount payable'
                                : 'Amount creditable'
                        }
                        value={money(Math.abs(summary.result))}
                    />
                    <Metric
                        label="Accounting check"
                        value={accountingCheck.matches ? 'Matched' : 'Review'}
                    />
                </section>
                {period.quarter && (
                    <ReturnStatus
                        taxReturn={taxReturn}
                        period={{ year: period.year, quarter: period.quarter }}
                        isAdmin={isAdmin}
                        canPrepare={
                            accountingCheck.matches &&
                            (issued.length > 0 || received.length > 0)
                        }
                    />
                )}
                <nav className="flex gap-1 border-b">
                    {(
                        [
                            ['summary', 'Settlement'],
                            ['issued', `Issued (${issued.length})`],
                            ['received', `Received (${received.length})`],
                        ] as const
                    ).map(([key, label]) => (
                        <button
                            key={key}
                            type="button"
                            onClick={() => setTab(key)}
                            className={`border-b-2 px-3 py-2 text-sm font-medium ${tab === key ? 'border-primary' : 'border-transparent text-muted-foreground'}`}
                        >
                            {label}
                        </button>
                    ))}
                </nav>
                {tab === 'summary' && (
                    <SummaryView
                        summary={summary}
                        rates={rates}
                        check={accountingCheck}
                        period={period}
                    />
                )}
                {tab === 'issued' && (
                    <Book
                        title="Issued invoice register"
                        rows={issued}
                        exportUrl={`/tax/export?book=issued&${query}`}
                    />
                )}
                {tab === 'received' && (
                    <Book
                        title="Received invoice register"
                        rows={received}
                        exportUrl={`/tax/export?book=received&${query}`}
                    />
                )}
            </div>
        </>
    );
}

function ReturnStatus({
    taxReturn,
    period,
    isAdmin,
    canPrepare,
}: {
    taxReturn: TaxReturn | null;
    period: { year: number; quarter: number };
    isAdmin: boolean;
    canPrepare: boolean;
}) {
    if (!taxReturn) {
        return (
            <section className="flex flex-wrap items-center justify-between gap-3 border-y py-3">
                <div>
                    <strong className="text-sm">Quarter not prepared</strong>
                    <p className="text-xs text-muted-foreground">
                        Review the registers and accounting differences before
                        locking the settlement.
                    </p>
                </div>
                {isAdmin && (
                    <Form action="/tax/returns" method="post">
                        <input type="hidden" name="year" value={period.year} />
                        <input
                            type="hidden"
                            name="quarter"
                            value={period.quarter}
                        />
                        <Button disabled={!canPrepare}>
                            <FileCheck2 /> Prepare return
                        </Button>
                    </Form>
                )}
            </section>
        );
    }

    const outstanding = Math.max(
        0,
        Math.abs(Number(taxReturn.result)) - Number(taxReturn.paid_amount),
    );

    return (
        <section className="flex flex-wrap items-center justify-between gap-3 border-y py-3">
            <div className="flex flex-wrap items-center gap-3">
                <Badge variant="outline">{taxReturn.status}</Badge>
                <Badge variant="outline">{taxReturn.settlement_status}</Badge>
                <div>
                    <strong className="text-sm">
                        VAT {taxReturn.year} Q{taxReturn.quarter}
                    </strong>
                    <p className="text-xs text-muted-foreground">
                        Settlement entry{' '}
                        {taxReturn.journal_entry?.number || '—'} ·{' '}
                        {money(Number(taxReturn.result))} · outstanding{' '}
                        {money(outstanding)}
                    </p>
                </div>
            </div>
            <div className="flex flex-wrap gap-2">
                {taxReturn.status === 'prepared' && isAdmin && (
                    <FilingDialog taxReturn={taxReturn} />
                )}
                {taxReturn.status === 'filed' && isAdmin && outstanding > 0 && (
                    <PaymentDialog
                        taxReturn={taxReturn}
                        outstanding={outstanding}
                    />
                )}
                {taxReturn.status === 'filed' && (
                    <Button variant="outline" asChild>
                        <a href={`/tax/returns/${taxReturn.id}/receipt`}>
                            <Download /> Filing receipt
                        </a>
                    </Button>
                )}
            </div>
        </section>
    );
}

function FilingDialog({ taxReturn }: { taxReturn: TaxReturn }) {
    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button>
                    <FileCheck2 /> Record filing
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Record VAT filing</DialogTitle>
                </DialogHeader>
                <Form
                    action={`/tax/returns/${taxReturn.id}/file`}
                    method="post"
                    className="grid gap-4"
                >
                    <div className="grid gap-1">
                        <Label>Filing reference</Label>
                        <Input name="filing_reference" required />
                    </div>
                    <div className="grid gap-1">
                        <Label>Filed on</Label>
                        <Input
                            name="filed_on"
                            type="date"
                            defaultValue={new Date().toISOString().slice(0, 10)}
                            required
                        />
                    </div>
                    <div className="grid gap-1">
                        <Label>Official receipt (PDF)</Label>
                        <Input
                            name="receipt"
                            type="file"
                            accept="application/pdf,.pdf"
                            required
                        />
                    </div>
                    <Button>Mark as filed</Button>
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function PaymentDialog({
    taxReturn,
    outstanding,
}: {
    taxReturn: TaxReturn;
    outstanding: number;
}) {
    const payable = Number(taxReturn.result) > 0;

    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button>
                    <CreditCard /> Record {payable ? 'payment' : 'refund'}
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {payable
                            ? 'Payment to tax authority'
                            : 'Refund from tax authority'}
                    </DialogTitle>
                </DialogHeader>
                <Form
                    action={`/tax/returns/${taxReturn.id}/payments`}
                    method="post"
                    className="grid gap-4"
                >
                    <div className="grid grid-cols-2 gap-3">
                        <div className="grid gap-1">
                            <Label>Date</Label>
                            <Input
                                name="paid_on"
                                type="date"
                                defaultValue={new Date()
                                    .toISOString()
                                    .slice(0, 10)}
                                required
                            />
                        </div>
                        <div className="grid gap-1">
                            <Label>Amount</Label>
                            <Input
                                name="amount"
                                type="number"
                                min="0.01"
                                max={outstanding}
                                step="0.01"
                                defaultValue={outstanding.toFixed(2)}
                                required
                            />
                        </div>
                    </div>
                    <div className="grid gap-1">
                        <Label>Method</Label>
                        <Select name="method" defaultValue="bank_transfer">
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="bank_transfer">
                                    Bank transfer
                                </SelectItem>
                                <SelectItem value="direct_debit">
                                    Direct debit
                                </SelectItem>
                                <SelectItem value="cash">Cash</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="grid gap-1">
                        <Label>Bank / NRC reference</Label>
                        <Input name="reference" />
                    </div>
                    <Button>Register movement</Button>
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function SummaryView({
    summary,
    rates,
    check,
    period,
}: {
    summary: Summary;
    rates: Rate[];
    check: Check;
    period: { from: string; to: string };
}) {
    return (
        <div className="grid gap-7 xl:grid-cols-[minmax(0,1fr)_22rem]">
            <section className="overflow-x-auto">
                <div className="mb-3">
                    <h2 className="text-base font-semibold">
                        Settlement preview
                    </h2>
                    <p className="text-xs text-muted-foreground">
                        {date(period.from)} – {date(period.to)}
                    </p>
                </div>
                <table className="w-full min-w-[42rem] text-sm">
                    <thead>
                        <tr className="border-b text-left text-muted-foreground">
                            <th className="py-2">VAT rate</th>
                            <th className="text-right">Issued base</th>
                            <th className="text-right">Output VAT</th>
                            <th className="text-right">Received base</th>
                            <th className="text-right">Input VAT</th>
                        </tr>
                    </thead>
                    <tbody>
                        {rates.map((rate) => (
                            <tr key={rate.rate} className="border-b">
                                <td className="py-2">
                                    {rate.rate.toLocaleString()}%
                                </td>
                                <td className="text-right">
                                    {money(rate.issuedBase)}
                                </td>
                                <td className="text-right">
                                    {money(rate.outputVat)}
                                </td>
                                <td className="text-right">
                                    {money(rate.receivedBase)}
                                </td>
                                <td className="text-right">
                                    {money(rate.inputVat)}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                    <tfoot>
                        <tr className="font-semibold">
                            <td className="py-3">Total</td>
                            <td className="text-right">
                                {money(summary.taxableBaseIssued)}
                            </td>
                            <td className="text-right">
                                {money(summary.outputVat)}
                            </td>
                            <td className="text-right">
                                {money(summary.taxableBaseReceived)}
                            </td>
                            <td className="text-right">
                                {money(summary.deductibleVat)}
                            </td>
                        </tr>
                    </tfoot>
                </table>
                <div className="mt-5 border-y py-4 text-right">
                    <span className="text-sm text-muted-foreground">
                        Settlement result{' '}
                    </span>
                    <strong className="ml-3 text-xl">
                        {money(summary.result)}
                    </strong>
                </div>
            </section>
            <aside>
                <h2 className="mb-3 text-base font-semibold">
                    Accounting verification
                </h2>
                <div className="divide-y border-y text-sm">
                    <CheckRow
                        label="Documents output VAT"
                        value={summary.outputVat}
                    />
                    <CheckRow label="Account 477" value={check.outputVat} />
                    <CheckRow
                        label="Output difference"
                        value={check.outputDifference}
                    />
                    <CheckRow
                        label="Documents input VAT"
                        value={summary.deductibleVat}
                    />
                    <CheckRow label="Account 472" value={check.inputVat} />
                    <CheckRow
                        label="Input difference"
                        value={check.inputDifference}
                    />
                </div>
                <Badge
                    className="mt-3"
                    variant={check.matches ? 'outline' : 'destructive'}
                >
                    <Scale />{' '}
                    {check.matches
                        ? 'Accounting matches registers'
                        : 'Differences require review'}
                </Badge>
            </aside>
        </div>
    );
}

function Book({
    title,
    rows,
    exportUrl,
}: {
    title: string;
    rows: VatRow[];
    exportUrl: string;
}) {
    return (
        <section className="overflow-x-auto">
            <div className="mb-3 flex items-center justify-between">
                <h2 className="text-base font-semibold">{title}</h2>
                <Button variant="outline" asChild>
                    <a href={exportUrl}>
                        <Download /> CSV
                    </a>
                </Button>
            </div>
            <table className="w-full min-w-[60rem] text-sm">
                <thead>
                    <tr className="border-b text-left text-muted-foreground">
                        <th className="py-2">Date</th>
                        <th>Invoice</th>
                        <th>Tax ID</th>
                        <th>Party</th>
                        <th className="text-right">Rate</th>
                        <th className="text-right">Base</th>
                        <th className="text-right">VAT</th>
                        <th className="text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    {rows.map((row, index) => (
                        <tr
                            key={`${row.number}-${row.rate}-${index}`}
                            className="border-b"
                        >
                            <td className="py-2">{date(row.date)}</td>
                            <td className="font-mono text-xs">{row.number}</td>
                            <td>{row.tax_id || '—'}</td>
                            <td>{row.party}</td>
                            <td className="text-right">{row.rate}%</td>
                            <td className="text-right">{money(row.base)}</td>
                            <td className="text-right">{money(row.vat)}</td>
                            <td className="text-right font-medium">
                                {money(row.total)}
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
            {rows.length === 0 && (
                <p className="border-b py-8 text-center text-sm text-muted-foreground">
                    No invoices in this period.
                </p>
            )}
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
function CheckRow({ label, value }: { label: string; value: number }) {
    return (
        <div className="flex justify-between gap-3 py-2">
            <span className="text-muted-foreground">{label}</span>
            <strong>{money(value)}</strong>
        </div>
    );
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
TaxIndex.layout = {
    breadcrumbs: [
        { title: 'Finance', href: '/finance' },
        { title: 'Accounting', href: '/accounting' },
        { title: 'VAT', href: '/tax' },
    ],
};

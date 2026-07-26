import { Form, Head, Link, router } from '@inertiajs/react';
import {
    ArrowDownToLine,
    CalendarClock,
    CircleAlert,
    Download,
    Gauge,
    Landmark,
    LibraryBig,
    ListChecks,
    Plus,
    Scale,
    Target,
    TrendingDown,
    TrendingUp,
    Wallet,
    WalletCards,
} from 'lucide-react';
import {
    Bar,
    BarChart,
    CartesianGrid,
    Legend,
    Line,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
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
type Due = {
    id: number;
    kind?: string;
    number: string;
    party: string;
    due_date: string | null;
    days_overdue: number;
    status: string;
    outstanding: number;
    promise_breached?: boolean;
    payment_batch?: {
        id: number;
        number: string;
        status: string;
    } | null;
    collection_action?: {
        status: string;
        resolution_reason: string | null;
        assigned_to: number | null;
        assignee: string | null;
        contacted_on: string | null;
        promised_on: string | null;
        promised_amount: number | null;
        next_action_on: string | null;
        resolved_at: string | null;
        notes: string | null;
        events: {
            id: number;
            event_type: string;
            status_from: string | null;
            status_to: string | null;
            promised_on: string | null;
            promised_amount: number | null;
            next_action_on: string | null;
            notes: string | null;
            occurred_at: string;
            creator: string | null;
        }[];
    } | null;
};
type BudgetPeriod = {
    status: 'draft' | 'approved';
    currentRevision: number;
    approvedBy: string | null;
    approvedAt: string | null;
    reopenedAt: string | null;
    reopenReason: string | null;
    configuredValues: number;
    requiredValues: number;
    versions: {
        id: number;
        revision: number;
        approvedBy: string | null;
        approvedAt: string;
        approvalNotes: string | null;
        operatingResult: number;
        cashNet: number;
    }[];
};
type Profit = {
    installation_id: number;
    reference: string | null;
    revenue: number;
    tracked_cost: number;
    labour_cost: number;
    supplier_cost: number;
    direct_expenses: number;
    total_cost: number;
    margin: number;
    margin_percent: number | null;
};
type Flow = {
    month: string;
    in: number;
    out: number;
    net: number;
    forecastIn: number;
    forecastOut: number;
    budgetIn: number;
    budgetOut: number;
    budgetNet: number;
};
type Performance = {
    month: string;
    revenue: number;
    costs: number;
    result: number;
    budgetRevenue: number;
    budgetCosts: number;
    budgetResult: number;
};
type Aging = {
    current: number;
    days_1_30: number;
    days_31_60: number;
    days_61_90: number;
    over_90: number;
};
type TreasuryWeek = {
    label: string;
    from: string;
    to: string;
    opening: number;
    in: number;
    out: number;
    closing: number;
};
type ForecastItem = {
    id: number;
    direction: 'in' | 'out';
    category: string;
    description: string;
    amount: string;
    due_on: string;
    probability: number;
    recurrence: string | null;
    status: string;
};
type Budget = {
    id: number;
    month: number;
    category: string;
    amount: string;
};
type BudgetAlert = {
    type: 'operating' | 'cash' | 'liquidity';
    month: number | null;
    monthLabel: string | null;
    severity: 'warning' | 'critical';
    variance: number;
    variancePercent: number | null;
    message: string;
};
type PaymentBatch = {
    id: number;
    number: string;
    planned_on: string;
    status: string;
    total: number;
    account: { name: string } | null;
    executor: { name: string } | null;
    executed_at: string | null;
    reconciled_at: string | null;
    item_count: number;
    items: {
        id: number;
        kind: 'invoice' | 'expense';
        payable_id: number;
        number: string;
        party: string;
        amount: number;
        payment_registered: boolean;
        reconciled: boolean;
    }[];
};
type FinanceUser = { id: number; name: string };
export default function FinanceIndex({
    year,
    summary,
    cashFlow,
    performance,
    vat,
    aging,
    receivables,
    payables,
    profitability,
    treasuryForecast,
    forecastItems,
    budgets,
    budgetPeriod,
    budgetSummary,
    budgetAlerts,
    alertSettings,
    comparison,
    kpis,
    paymentBatches,
    bankAccounts,
    financeUsers,
    can,
}: {
    year: number;
    summary: {
        cashIn: number;
        cashOut: number;
        netCash: number;
        receivable: number;
        payable: number;
        overdueReceivable: number;
        overduePayable: number;
        revenue: number;
        costs: number;
        operatingResult: number;
        operatingMargin: number | null;
    };
    cashFlow: Flow[];
    performance: Performance[];
    vat: { output: number; input: number };
    aging: { receivable: Aging; payable: Aging };
    receivables: Due[];
    payables: Due[];
    profitability: Profit[];
    treasuryForecast: {
        scenario: string;
        openingBalance: number;
        lowestBalance: number;
        closingBalance: number;
        weeks: TreasuryWeek[];
    };
    forecastItems: ForecastItem[];
    budgets: Budget[];
    budgetPeriod: BudgetPeriod;
    budgetSummary: {
        revenue: number;
        costs: number;
        result: number;
        actualResult: number;
        variance: number;
        cashIn: number;
        cashOut: number;
        cashNet: number;
        actualCashNet: number;
        cashVariance: number;
    };
    budgetAlerts: BudgetAlert[];
    alertSettings: {
        operatingVariancePercent: number;
        cashVariancePercent: number;
        liquidityBufferAmount: number;
    };
    comparison: {
        previousYear: number;
        revenue: number;
        costs: number;
        result: number;
        revenueChange: number | null;
        resultChange: number | null;
    };
    kpis: {
        dso: number | null;
        dpo: number | null;
        overdueReceivableRatio: number;
        overduePayableRatio: number;
        cashConversion: number | null;
        brokenPromises: number;
    };
    paymentBatches: PaymentBatch[];
    bankAccounts: { id: number; name: string; iban: string | null }[];
    financeUsers: FinanceUser[];
    can: {
        manageTreasury: boolean;
        manageAccounting: boolean;
        approvePayments: boolean;
        approveBudgets: boolean;
    };
}) {
    const vatBalance = vat.output - vat.input;

    return (
        <>
            <Head title="Finance" />
            <div className="flex flex-1 flex-col gap-7 p-4 md:p-6">
                <header className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold">Finance</h1>
                        <p className="text-sm text-muted-foreground">
                            Cash, forecasts, tax position and tracked
                            installation margin.
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button variant="outline" asChild>
                            <Link href="/accounting">
                                <LibraryBig /> Accounting
                            </Link>
                        </Button>
                        <Button variant="outline" asChild>
                            <Link href="/banking">
                                <Landmark /> Banking
                            </Link>
                        </Button>
                        <Select
                            value={String(year)}
                            onValueChange={(value) =>
                                router.get(
                                    '/finance',
                                    {
                                        year: value,
                                        scenario: treasuryForecast.scenario,
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
                                    (_, i) => new Date().getFullYear() - 3 + i,
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
                        <Select
                            value={treasuryForecast.scenario}
                            onValueChange={(value) =>
                                router.get(
                                    '/finance',
                                    { year, scenario: value },
                                    { preserveState: true },
                                )
                            }
                        >
                            <SelectTrigger className="w-36">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="conservative">
                                    Conservative
                                </SelectItem>
                                <SelectItem value="probable">
                                    Probable
                                </SelectItem>
                                <SelectItem value="optimistic">
                                    Optimistic
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <ExportMenu year={year} />
                    </div>
                </header>
                <section>
                    <div className="mb-3 flex items-end justify-between gap-3">
                        <div>
                            <h2 className="font-semibold">
                                Operating performance · {year}
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                Net revenue and costs before VAT.
                            </p>
                        </div>
                        <span
                            className={`text-sm font-medium ${summary.operatingResult < 0 ? 'text-destructive' : 'text-green-700 dark:text-green-400'}`}
                        >
                            {summary.operatingMargin === null
                                ? 'No margin yet'
                                : `${summary.operatingMargin.toLocaleString(undefined, { maximumFractionDigits: 1 })}% margin`}
                        </span>
                    </div>
                    <div className="grid grid-cols-1 border-y sm:grid-cols-3">
                        <Metric
                            label="Net revenue"
                            value={summary.revenue}
                            icon={<TrendingUp />}
                        />
                        <Metric
                            label="Recorded costs"
                            value={summary.costs}
                            icon={<TrendingDown />}
                        />
                        <Metric
                            label="Operating result"
                            value={summary.operatingResult}
                            icon={<Wallet />}
                            tone={
                                summary.operatingResult < 0
                                    ? 'negative'
                                    : 'positive'
                            }
                        />
                    </div>
                </section>
                <section>
                    <h2 className="mb-3 font-semibold">
                        Liquidity and open balances
                    </h2>
                    <div className="grid grid-cols-2 border-y lg:grid-cols-6">
                        <Metric
                            label="Cash in"
                            value={summary.cashIn}
                            icon={<TrendingUp />}
                        />
                        <Metric
                            label="Cash out"
                            value={summary.cashOut}
                            icon={<TrendingDown />}
                        />
                        <Metric
                            label="Net cash"
                            value={summary.netCash}
                            icon={<Wallet />}
                            tone={summary.netCash < 0 ? 'negative' : 'positive'}
                        />
                        <Metric
                            label="To collect"
                            value={summary.receivable}
                            icon={<ArrowDownToLine />}
                            detail={`${money(summary.overdueReceivable)} overdue`}
                        />
                        <Metric
                            label="To pay"
                            value={summary.payable}
                            icon={<Scale />}
                            detail={`${money(summary.overduePayable)} overdue`}
                        />
                    </div>
                </section>
                <section>
                    <div className="mb-3 flex items-center gap-2">
                        <Gauge className="size-4 text-muted-foreground" />
                        <h2 className="font-semibold">Financial indicators</h2>
                    </div>
                    <div className="grid grid-cols-2 border-y lg:grid-cols-5">
                        <Kpi
                            label="Days to collect"
                            value={kpis.dso === null ? '—' : `${kpis.dso} d`}
                            detail="DSO"
                        />
                        <Kpi
                            label="Days to pay"
                            value={kpis.dpo === null ? '—' : `${kpis.dpo} d`}
                            detail="DPO"
                        />
                        <Kpi
                            label="Overdue receivables"
                            value={`${kpis.overdueReceivableRatio}%`}
                        />
                        <Kpi
                            label="Overdue payables"
                            value={`${kpis.overduePayableRatio}%`}
                        />
                        <Kpi
                            label="Cash conversion"
                            value={
                                kpis.cashConversion === null
                                    ? '—'
                                    : `${kpis.cashConversion}%`
                            }
                        />
                        <Kpi
                            label="Broken promises"
                            value={kpis.brokenPromises.toLocaleString()}
                            detail="Needs follow-up"
                        />
                    </div>
                </section>
                {budgetAlerts.length > 0 && (
                    <section id="financial-alerts">
                        <div className="mb-3 flex items-center justify-between gap-3">
                            <div className="flex items-center gap-2">
                                <CircleAlert className="size-4 text-destructive" />
                                <h2 className="font-semibold">
                                    Financial alerts
                                </h2>
                            </div>
                            <Badge variant="destructive">
                                {budgetAlerts.length} active
                            </Badge>
                        </div>
                        <div className="divide-y border-y">
                            {budgetAlerts.map((alert, index) => (
                                <div
                                    key={`${alert.type}-${alert.month ?? 'forecast'}-${index}`}
                                    className="flex flex-wrap items-center justify-between gap-3 py-3 text-sm"
                                >
                                    <div className="flex min-w-0 items-start gap-3">
                                        <Badge
                                            variant={
                                                alert.severity === 'critical'
                                                    ? 'destructive'
                                                    : 'outline'
                                            }
                                        >
                                            {alert.severity}
                                        </Badge>
                                        <div>
                                            <div className="font-medium">
                                                {alert.monthLabel
                                                    ? `${alert.monthLabel} · `
                                                    : ''}
                                                {alert.message}
                                            </div>
                                            <div className="text-xs text-muted-foreground">
                                                {money(alert.variance)}
                                                {alert.variancePercent !== null
                                                    ? ` · ${alert.variancePercent}% unfavorable`
                                                    : ' · against liquidity buffer'}
                                            </div>
                                        </div>
                                    </div>
                                    {alert.month !== null && (
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            asChild
                                        >
                                            <a
                                                href={`#budget-month-${alert.month}`}
                                            >
                                                Review month
                                            </a>
                                        </Button>
                                    )}
                                </div>
                            ))}
                        </div>
                    </section>
                )}
                <section className="grid gap-8 xl:grid-cols-[1.45fr_.55fr] [&>*]:min-w-0">
                    <ChartSection
                        title="13-week treasury forecast"
                        description={`Running bank balance under the ${treasuryForecast.scenario} scenario.`}
                    >
                        <BarChart
                            data={treasuryForecast.weeks}
                            margin={{ left: 8, right: 8 }}
                        >
                            <CartesianGrid
                                strokeDasharray="3 3"
                                stroke="var(--border)"
                            />
                            <XAxis
                                dataKey="label"
                                stroke="var(--muted-foreground)"
                                fontSize={12}
                            />
                            <YAxis
                                stroke="var(--muted-foreground)"
                                fontSize={12}
                            />
                            <Tooltip
                                contentStyle={tooltipStyle}
                                formatter={(value) => money(Number(value))}
                            />
                            <Legend />
                            <Bar
                                dataKey="in"
                                name="Expected in"
                                fill="var(--chart-2)"
                            />
                            <Bar
                                dataKey="out"
                                name="Expected out"
                                fill="var(--chart-5)"
                            />
                            <Line
                                type="monotone"
                                dataKey="closing"
                                name="Closing balance"
                                stroke="var(--foreground)"
                                strokeWidth={2}
                            />
                        </BarChart>
                    </ChartSection>
                    <div>
                        <h2 className="font-semibold">Treasury range</h2>
                        <p className="mb-3 text-sm text-muted-foreground">
                            Forecast from current bank balances and due dates.
                        </p>
                        <div className="border-y text-sm">
                            <Total
                                label="Opening balance"
                                value={treasuryForecast.openingBalance}
                            />
                            <Total
                                label="Lowest balance"
                                value={treasuryForecast.lowestBalance}
                            />
                            <Total
                                label="Closing balance"
                                value={treasuryForecast.closingBalance}
                                strong
                            />
                        </div>
                        {treasuryForecast.lowestBalance < 0 && (
                            <p className="mt-3 text-sm font-medium text-destructive">
                                Projected liquidity shortfall. Review collection
                                dates or planned payments.
                            </p>
                        )}
                    </div>
                </section>
                <section className="grid gap-8 xl:grid-cols-2 [&>*]:min-w-0">
                    <ChartSection
                        title="Cash flow and forecast"
                        description="Recorded payments plus outstanding documents by contractual due date."
                    >
                        <BarChart
                            data={cashFlow}
                            margin={{ left: 8, right: 8 }}
                        >
                            <ChartAxes />
                            <Tooltip
                                contentStyle={tooltipStyle}
                                formatter={(value) => money(Number(value))}
                            />
                            <Legend />
                            <Bar
                                dataKey="in"
                                name="Cash in"
                                fill="var(--chart-2)"
                            />
                            <Bar
                                dataKey="out"
                                name="Cash out"
                                fill="var(--chart-5)"
                            />
                            <Bar
                                dataKey="forecastIn"
                                name="Expected collections"
                                fill="var(--chart-1)"
                            />
                            <Bar
                                dataKey="forecastOut"
                                name="Expected payments"
                                fill="var(--chart-4)"
                            />
                            <Line
                                type="monotone"
                                dataKey="net"
                                name="Net cash"
                                stroke="var(--foreground)"
                                strokeWidth={2}
                            />
                            <Line
                                type="monotone"
                                dataKey="budgetNet"
                                name="Budget cash net"
                                stroke="var(--chart-4)"
                                strokeWidth={2}
                                strokeDasharray="5 5"
                            />
                        </BarChart>
                    </ChartSection>
                    <ChartSection
                        title="Monthly result vs budget"
                        description="Actual revenue and costs compared with the monthly operating budget, excluding VAT."
                    >
                        <BarChart
                            data={performance}
                            margin={{ left: 8, right: 8 }}
                        >
                            <ChartAxes />
                            <Tooltip
                                contentStyle={tooltipStyle}
                                formatter={(value) => money(Number(value))}
                            />
                            <Legend />
                            <Bar
                                dataKey="revenue"
                                name="Revenue"
                                fill="var(--chart-2)"
                            />
                            <Bar
                                dataKey="costs"
                                name="Costs"
                                fill="var(--chart-5)"
                            />
                            <Line
                                type="monotone"
                                dataKey="result"
                                name="Actual result"
                                stroke="var(--foreground)"
                                strokeWidth={2}
                            />
                            <Line
                                type="monotone"
                                dataKey="budgetResult"
                                name="Budget result"
                                stroke="var(--chart-4)"
                                strokeWidth={2}
                                strokeDasharray="5 5"
                            />
                        </BarChart>
                    </ChartSection>
                </section>
                <section className="grid gap-8 border-t pt-5 xl:grid-cols-2 [&>*]:min-w-0">
                    <div>
                        <div className="mb-3 flex items-center justify-between gap-3">
                            <div className="flex items-center gap-2">
                                <Target className="size-4 text-muted-foreground" />
                                <h2 className="font-semibold">
                                    Budget and comparison
                                </h2>
                            </div>
                            <Badge
                                variant={
                                    budgetPeriod.status === 'approved'
                                        ? 'default'
                                        : 'outline'
                                }
                            >
                                {budgetPeriod.status}
                                {budgetPeriod.currentRevision > 0 &&
                                    ` · R${budgetPeriod.currentRevision}`}
                            </Badge>
                        </div>
                        <div className="grid grid-cols-2 border-y text-sm">
                            <Total
                                label="Budget result"
                                value={budgetSummary.result}
                            />
                            <Total
                                label="Actual result"
                                value={budgetSummary.actualResult}
                            />
                            <Total
                                label="Budget variance"
                                value={budgetSummary.variance}
                                strong
                            />
                            <Total
                                label={`${comparison.previousYear} result`}
                                value={comparison.result}
                            />
                            <Total
                                label="Budget cash net"
                                value={budgetSummary.cashNet}
                            />
                            <Total
                                label="Actual cash net"
                                value={budgetSummary.actualCashNet}
                            />
                            <Total
                                label="Cash budget variance"
                                value={budgetSummary.cashVariance}
                                strong
                            />
                        </div>
                        <p className="mt-3 text-sm text-muted-foreground">
                            Revenue vs prior year:{' '}
                            {comparison.revenueChange === null
                                ? 'no baseline'
                                : `${comparison.revenueChange}%`}
                            {' · '}Result change:{' '}
                            {comparison.resultChange === null
                                ? 'no baseline'
                                : `${comparison.resultChange}%`}
                        </p>
                        <BudgetGovernance
                            year={year}
                            period={budgetPeriod}
                            canApprove={can.approveBudgets}
                        />
                        {budgetPeriod.status === 'approved' && (
                            <p className="mt-3 text-sm font-medium text-muted-foreground">
                                This annual budget is locked. Reopen it before
                                changing values.
                            </p>
                        )}
                        {can.manageTreasury &&
                            budgetPeriod.status === 'draft' && (
                                <BudgetForm
                                    year={year}
                                    budgets={budgets}
                                    performance={performance}
                                    cashFlow={cashFlow}
                                    alertSettings={alertSettings}
                                    alertCount={budgetAlerts.length}
                                />
                            )}
                    </div>
                    <div>
                        <div className="mb-3 flex items-center gap-2">
                            <CalendarClock className="size-4 text-muted-foreground" />
                            <h2 className="font-semibold">
                                Planned treasury items
                            </h2>
                        </div>
                        {can.manageTreasury && <ForecastForm />}
                        <div className="mt-4 max-h-72 overflow-auto border-y text-sm">
                            {forecastItems.map((item) => (
                                <div
                                    key={item.id}
                                    className="flex items-center justify-between gap-3 border-b py-3 last:border-0"
                                >
                                    <div className="min-w-0">
                                        <div className="truncate font-medium">
                                            {item.description}
                                        </div>
                                        <div className="text-xs text-muted-foreground">
                                            {date(item.due_on)} ·{' '}
                                            {item.probability}% · {item.status}
                                        </div>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <div
                                            className={
                                                item.direction === 'in'
                                                    ? 'font-medium text-green-700 dark:text-green-400'
                                                    : 'font-medium text-destructive'
                                            }
                                        >
                                            {item.direction === 'in'
                                                ? '+'
                                                : '−'}
                                            {money(Number(item.amount))}
                                        </div>
                                        {can.manageTreasury &&
                                            item.status === 'planned' && (
                                                <Form
                                                    action={`/finance/forecast-items/${item.id}`}
                                                    method="patch"
                                                >
                                                    <input
                                                        type="hidden"
                                                        name="status"
                                                        value="settled"
                                                    />
                                                    <Button
                                                        size="sm"
                                                        variant="ghost"
                                                    >
                                                        Settle
                                                    </Button>
                                                </Form>
                                            )}
                                        {can.manageTreasury && (
                                            <Form
                                                action={`/finance/forecast-items/${item.id}`}
                                                method="delete"
                                            >
                                                <Button
                                                    size="sm"
                                                    variant="ghost"
                                                >
                                                    Remove
                                                </Button>
                                            </Form>
                                        )}
                                    </div>
                                </div>
                            ))}
                            {forecastItems.length === 0 && (
                                <Empty text="No manually planned treasury items." />
                            )}
                        </div>
                    </div>
                </section>
                <section>
                    <div className="mb-3 flex items-center gap-2">
                        <CircleAlert className="size-4 text-muted-foreground" />
                        <h2 className="font-semibold">Current balance aging</h2>
                    </div>
                    <AgingTable
                        receivable={aging.receivable}
                        payable={aging.payable}
                    />
                </section>
                <section className="grid gap-8 xl:grid-cols-2 [&>*]:min-w-0">
                    <DueTable
                        title="Accounts receivable"
                        items={receivables}
                        base="/sales"
                        financeUsers={financeUsers}
                        canManage={can.manageTreasury}
                    />
                    <DueTable
                        title="Accounts payable"
                        items={payables}
                        bankAccounts={bankAccounts}
                        canManage={can.manageTreasury}
                    />
                </section>
                <section>
                    <div className="mb-3 flex items-center gap-2">
                        <ListChecks className="size-4 text-muted-foreground" />
                        <h2 className="font-semibold">Payment batches</h2>
                    </div>
                    <div className="overflow-x-auto border-y">
                        <table className="w-full min-w-[40rem] text-sm">
                            <thead>
                                <tr className="border-b text-left text-muted-foreground">
                                    <th className="py-2">Batch</th>
                                    <th>Planned</th>
                                    <th>Account</th>
                                    <th>Status</th>
                                    <th className="text-right">Total</th>
                                    <th className="text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {paymentBatches.map((batch) => (
                                    <tr
                                        key={batch.id}
                                        className="border-b last:border-0"
                                    >
                                        <td className="py-3 font-medium">
                                            {batch.number}
                                            <details className="mt-1 font-normal">
                                                <summary className="cursor-pointer text-xs text-muted-foreground">
                                                    {batch.item_count}{' '}
                                                    {batch.item_count === 1
                                                        ? 'document'
                                                        : 'documents'}
                                                </summary>
                                                <div className="mt-2 min-w-64 space-y-2 border-l pl-3">
                                                    {batch.items.map((item) => (
                                                        <div
                                                            key={item.id}
                                                            className="flex justify-between gap-3 text-xs"
                                                        >
                                                            <span>
                                                                {item.number} ·{' '}
                                                                {item.party}
                                                                {item.reconciled
                                                                    ? ' · reconciled'
                                                                    : item.payment_registered
                                                                      ? ' · paid'
                                                                      : ''}
                                                            </span>
                                                            <span className="font-medium whitespace-nowrap">
                                                                {money(
                                                                    item.amount,
                                                                )}
                                                            </span>
                                                        </div>
                                                    ))}
                                                </div>
                                            </details>
                                        </td>
                                        <td>{date(batch.planned_on)}</td>
                                        <td>{batch.account?.name || '—'}</td>
                                        <td>
                                            <Badge variant="outline">
                                                {batch.status}
                                            </Badge>
                                            {batch.executor && (
                                                <div className="mt-1 text-xs text-muted-foreground">
                                                    {batch.executor.name}
                                                    {batch.reconciled_at
                                                        ? ` · reconciled ${date(batch.reconciled_at)}`
                                                        : batch.executed_at
                                                          ? ` · paid ${date(batch.executed_at)}`
                                                          : ''}
                                                </div>
                                            )}
                                        </td>
                                        <td className="text-right font-medium">
                                            {money(batch.total)}
                                        </td>
                                        <td className="text-right">
                                            {can.approvePayments &&
                                                batch.status === 'draft' && (
                                                    <Form
                                                        action={`/finance/payment-batches/${batch.id}/approve`}
                                                        method="post"
                                                        className="inline"
                                                    >
                                                        <Button
                                                            size="sm"
                                                            variant="outline"
                                                        >
                                                            Approve
                                                        </Button>
                                                    </Form>
                                                )}
                                            {can.approvePayments &&
                                                [
                                                    'approved',
                                                    'exported',
                                                ].includes(batch.status) && (
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        asChild
                                                    >
                                                        <a
                                                            href={`/finance/payment-batches/${batch.id}/sepa`}
                                                        >
                                                            SEPA
                                                        </a>
                                                    </Button>
                                                )}
                                            {can.approvePayments &&
                                                batch.status === 'exported' && (
                                                    <Form
                                                        action={`/finance/payment-batches/${batch.id}/execute`}
                                                        method="post"
                                                        className="ml-2 inline"
                                                        onSubmit={(event) => {
                                                            if (
                                                                !window.confirm(
                                                                    'Confirm that the bank executed this complete payment batch?',
                                                                )
                                                            ) {
                                                                event.preventDefault();
                                                            }
                                                        }}
                                                    >
                                                        <input
                                                            type="hidden"
                                                            name="paid_on"
                                                            value={new Date()
                                                                .toISOString()
                                                                .slice(0, 10)}
                                                        />
                                                        <Button
                                                            size="sm"
                                                            variant="outline"
                                                        >
                                                            Confirm paid
                                                        </Button>
                                                    </Form>
                                                )}
                                            {can.manageTreasury &&
                                                ['draft', 'approved'].includes(
                                                    batch.status,
                                                ) && (
                                                    <Form
                                                        action={`/finance/payment-batches/${batch.id}/cancel`}
                                                        method="post"
                                                        className="ml-2 inline"
                                                    >
                                                        <Button
                                                            size="sm"
                                                            variant="ghost"
                                                        >
                                                            Cancel
                                                        </Button>
                                                    </Form>
                                                )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                        {paymentBatches.length === 0 && (
                            <Empty text="No payment batches prepared." />
                        )}
                    </div>
                </section>
                <section className="grid gap-8 border-t pt-5 xl:grid-cols-[.7fr_1.3fr] [&>*]:min-w-0">
                    <div>
                        <h2 className="font-semibold">VAT position</h2>
                        <p className="mb-3 text-sm text-muted-foreground">
                            Informative calculation for the selected calendar
                            year.
                        </p>
                        <div className="border-y text-sm">
                            <Total label="Output VAT" value={vat.output} />
                            <Total label="Input VAT" value={vat.input} />
                            <Total
                                label={
                                    vatBalance >= 0
                                        ? 'Estimated payable'
                                        : 'Estimated refundable'
                                }
                                value={Math.abs(vatBalance)}
                                strong
                            />
                        </div>
                    </div>
                    <div>
                        <h2 className="font-semibold">
                            Tracked margin by installation
                        </h2>
                        <p className="mb-3 text-sm text-muted-foreground">
                            Revenue including credit notes, less dispatched
                            material, labour, supplier services and linked
                            direct expenses.
                        </p>
                        <div className="overflow-x-auto border-y">
                            <table className="w-full min-w-[54rem] text-sm">
                                <thead>
                                    <tr className="border-b text-left text-muted-foreground">
                                        <th className="py-2">Installation</th>
                                        <th className="text-right">Revenue</th>
                                        <th className="text-right">Material</th>
                                        <th className="text-right">
                                            Labour & travel
                                        </th>
                                        <th className="text-right">
                                            Supplier services
                                        </th>
                                        <th className="text-right">
                                            Direct expenses
                                        </th>
                                        <th className="text-right">Margin</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {profitability.map((item) => (
                                        <tr
                                            key={item.installation_id}
                                            className="border-b last:border-0"
                                        >
                                            <td className="py-3 font-medium">
                                                <Link
                                                    className="underline underline-offset-2"
                                                    href={`/installations/${item.installation_id}`}
                                                >
                                                    {item.reference ||
                                                        `#${item.installation_id}`}
                                                </Link>
                                            </td>
                                            <td className="text-right">
                                                {money(item.revenue)}
                                            </td>
                                            <td className="text-right">
                                                {money(item.tracked_cost)}
                                            </td>
                                            <td className="text-right">
                                                {money(item.labour_cost)}
                                            </td>
                                            <td className="text-right">
                                                {money(item.supplier_cost)}
                                            </td>
                                            <td className="text-right">
                                                {money(item.direct_expenses)}
                                            </td>
                                            <td className="text-right font-semibold">
                                                {money(item.margin)}
                                                {item.margin_percent !==
                                                    null && (
                                                    <span className="ml-1 text-xs text-muted-foreground">
                                                        ({item.margin_percent}%)
                                                    </span>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                            {profitability.length === 0 && (
                                <Empty text="No invoiced installations with tracked material yet." />
                            )}
                        </div>
                    </div>
                </section>
            </div>
        </>
    );
}
function Metric({
    label,
    value,
    icon,
    detail,
    tone,
}: {
    label: string;
    value: number;
    icon: React.ReactNode;
    detail?: string;
    tone?: 'positive' | 'negative';
}) {
    return (
        <div className="border-r p-4 last:border-0">
            <div className="flex items-center gap-2 text-sm text-muted-foreground [&_svg]:size-4">
                {icon}
                {label}
            </div>
            <div
                className={`mt-1 text-xl font-semibold ${
                    tone === 'positive'
                        ? 'text-green-700 dark:text-green-400'
                        : tone === 'negative'
                          ? 'text-destructive'
                          : ''
                }`}
            >
                {money(value)}
            </div>
            {detail && (
                <div className="mt-1 text-xs text-muted-foreground">
                    {detail}
                </div>
            )}
        </div>
    );
}
function ExportMenu({ year }: { year: number }) {
    const exports = [
        ['cash', 'Cash movements'],
        ['vat', 'VAT register'],
        ['receivables', 'Accounts receivable'],
        ['payables', 'Accounts payable'],
        ['budget', 'Budget values'],
    ];

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="outline">
                    <Download /> Export
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
                {exports.map(([type, label]) => (
                    <DropdownMenuItem key={type} asChild>
                        <a href={`/finance/export?type=${type}&year=${year}`}>
                            {label}
                        </a>
                    </DropdownMenuItem>
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
function ChartSection({
    title,
    description,
    children,
}: {
    title: string;
    description: string;
    children: React.ReactElement;
}) {
    return (
        <div className="min-w-0">
            <h2 className="font-semibold">{title}</h2>
            <p className="mb-3 text-sm text-muted-foreground">{description}</p>
            <div className="h-72 w-full">
                <ResponsiveContainer width="100%" height="100%">
                    {children}
                </ResponsiveContainer>
            </div>
        </div>
    );
}
function ChartAxes() {
    return (
        <>
            <CartesianGrid strokeDasharray="3 3" stroke="var(--border)" />
            <XAxis
                dataKey="month"
                stroke="var(--muted-foreground)"
                fontSize={12}
            />
            <YAxis stroke="var(--muted-foreground)" fontSize={12} />
        </>
    );
}
function AgingTable({
    receivable,
    payable,
}: {
    receivable: Aging;
    payable: Aging;
}) {
    const columns: { key: keyof Aging; label: string }[] = [
        { key: 'current', label: 'Not due' },
        { key: 'days_1_30', label: '1–30 days' },
        { key: 'days_31_60', label: '31–60 days' },
        { key: 'days_61_90', label: '61–90 days' },
        { key: 'over_90', label: 'Over 90 days' },
    ];

    return (
        <div className="overflow-x-auto border-y">
            <table className="w-full min-w-[46rem] text-sm">
                <thead>
                    <tr className="border-b text-left text-muted-foreground">
                        <th className="py-2">Balance</th>
                        {columns.map((column) => (
                            <th key={column.key} className="text-right">
                                {column.label}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody>
                    {[
                        ['To collect', receivable],
                        ['To pay', payable],
                    ].map(([label, values]) => (
                        <tr
                            key={label as string}
                            className="border-b last:border-0"
                        >
                            <td className="py-3 font-medium">
                                {label as string}
                            </td>
                            {columns.map((column) => (
                                <td
                                    key={column.key}
                                    className={`text-right ${(values as Aging)[column.key] > 0 && column.key !== 'current' ? 'font-medium text-amber-700 dark:text-amber-400' : ''}`}
                                >
                                    {money((values as Aging)[column.key])}
                                </td>
                            ))}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
function DueTable({
    title,
    items,
    base,
    financeUsers,
    bankAccounts,
    canManage = false,
}: {
    title: string;
    items: Due[];
    base?: string;
    financeUsers?: FinanceUser[];
    bankAccounts?: { id: number; name: string; iban: string | null }[];
    canManage?: boolean;
}) {
    return (
        <div className="min-w-0">
            <h2 className="mb-3 font-semibold">{title}</h2>
            <div className="overflow-x-auto border-y">
                <table className="w-full min-w-[34rem] text-sm">
                    <thead>
                        <tr className="border-b text-left text-muted-foreground">
                            <th className="py-2">Document</th>
                            <th>Party</th>
                            <th>Due</th>
                            <th>Aging</th>
                            <th>Status</th>
                            <th className="text-right">Outstanding</th>
                            {canManage && (
                                <th className="text-right">Action</th>
                            )}
                        </tr>
                    </thead>
                    <tbody>
                        {items.map((item) => (
                            <tr
                                key={`${item.kind || 'receivable'}-${item.id}`}
                                className="border-b last:border-0"
                            >
                                <td className="py-3 font-medium">
                                    <Link
                                        className="underline underline-offset-2"
                                        href={
                                            base
                                                ? `${base}/${item.id}`
                                                : item.kind === 'expense'
                                                  ? `/expenses/${item.id}`
                                                  : `/purchases/${item.id}`
                                        }
                                    >
                                        {item.number}
                                    </Link>
                                </td>
                                <td>{item.party || '—'}</td>
                                <td>
                                    {item.due_date ? date(item.due_date) : '—'}
                                </td>
                                <td>
                                    {item.days_overdue > 0 ? (
                                        <span className="font-medium text-amber-700 dark:text-amber-400">
                                            {item.days_overdue}d overdue
                                        </span>
                                    ) : (
                                        <span className="text-muted-foreground">
                                            Current
                                        </span>
                                    )}
                                </td>
                                <td>
                                    <Badge variant="outline">
                                        {item.status}
                                    </Badge>
                                    {item.collection_action && (
                                        <div className="mt-1 text-xs text-muted-foreground">
                                            {item.collection_action.status}
                                            {item.collection_action.assignee
                                                ? ` · ${item.collection_action.assignee}`
                                                : ''}
                                        </div>
                                    )}
                                    {item.promise_breached && (
                                        <div className="mt-1 text-xs font-medium text-destructive">
                                            Broken payment promise
                                        </div>
                                    )}
                                    {item.payment_batch && (
                                        <div className="mt-1 text-xs text-muted-foreground">
                                            {item.payment_batch.number} ·{' '}
                                            {item.payment_batch.status}
                                        </div>
                                    )}
                                </td>
                                <td className="text-right font-medium">
                                    {money(item.outstanding)}
                                </td>
                                {canManage && (
                                    <td className="text-right">
                                        {base ? (
                                            <CollectionActionForm
                                                item={item}
                                                users={financeUsers || []}
                                            />
                                        ) : item.payment_batch ? (
                                            <span className="text-xs text-muted-foreground">
                                                Already planned
                                            </span>
                                        ) : (
                                            <PaymentPlanForm
                                                item={item}
                                                accounts={bankAccounts || []}
                                            />
                                        )}
                                    </td>
                                )}
                            </tr>
                        ))}
                    </tbody>
                </table>
                {items.length === 0 && <Empty text="Nothing outstanding." />}
            </div>
        </div>
    );
}
function CollectionActionForm({
    item,
    users,
}: {
    item: Due;
    users: FinanceUser[];
}) {
    return (
        <details className="relative inline-block text-left">
            <summary className="cursor-pointer text-sm font-medium underline underline-offset-2">
                Follow up
            </summary>
            <Form
                action={`/finance/collections/${item.id}`}
                method="put"
                className="absolute right-0 z-20 mt-2 grid w-72 gap-3 border bg-background p-4 shadow-lg"
            >
                <Field label="Status">
                    <select
                        name="status"
                        defaultValue={item.collection_action?.status || 'open'}
                        className={nativeSelectClass}
                    >
                        <option value="open">Open</option>
                        <option value="contacted">Contacted</option>
                        <option value="promised">Promised</option>
                        <option value="disputed">Disputed</option>
                        <option value="resolved">Resolved</option>
                    </select>
                </Field>
                <Field label="Owner">
                    <select
                        name="assigned_to"
                        defaultValue={item.collection_action?.assigned_to || ''}
                        className={nativeSelectClass}
                    >
                        <option value="">Unassigned</option>
                        {users.map((user) => (
                            <option key={user.id} value={user.id}>
                                {user.name}
                            </option>
                        ))}
                    </select>
                </Field>
                <div className="grid grid-cols-2 gap-2">
                    <Field label="Next action">
                        <Input
                            name="next_action_on"
                            type="date"
                            defaultValue={
                                item.collection_action?.next_action_on || ''
                            }
                        />
                    </Field>
                    <Field label="Promise date">
                        <Input
                            name="promised_on"
                            type="date"
                            defaultValue={
                                item.collection_action?.promised_on || ''
                            }
                        />
                    </Field>
                </div>
                <Field label="Promised amount">
                    <Input
                        name="promised_amount"
                        type="number"
                        min="0.01"
                        step="0.01"
                        defaultValue={
                            item.collection_action?.promised_amount || ''
                        }
                    />
                </Field>
                <Field label="Notes">
                    <Textarea
                        name="notes"
                        defaultValue={item.collection_action?.notes || ''}
                        rows={2}
                    />
                </Field>
                <Button size="sm">Save follow-up</Button>
                {(item.collection_action?.events.length || 0) > 0 && (
                    <div className="max-h-44 space-y-2 overflow-y-auto border-t pt-3 text-xs">
                        <div className="font-medium">Recent history</div>
                        {item.collection_action?.events.map((event) => (
                            <div key={event.id} className="border-l pl-2">
                                <div className="font-medium">
                                    {event.event_type}
                                    {event.creator ? ` · ${event.creator}` : ''}
                                </div>
                                <div className="text-muted-foreground">
                                    {dateTime(event.occurred_at)}
                                    {event.status_from &&
                                    event.status_from !== event.status_to
                                        ? ` · ${event.status_from} → ${event.status_to}`
                                        : ''}
                                </div>
                                {event.notes && (
                                    <div className="mt-1 line-clamp-2">
                                        {event.notes}
                                    </div>
                                )}
                            </div>
                        ))}
                    </div>
                )}
            </Form>
        </details>
    );
}
function PaymentPlanForm({
    item,
    accounts,
}: {
    item: Due;
    accounts: { id: number; name: string; iban: string | null }[];
}) {
    return (
        <details className="relative inline-block text-left">
            <summary className="cursor-pointer text-sm font-medium underline underline-offset-2">
                Plan
            </summary>
            <Form
                action="/finance/payment-batches"
                method="post"
                className="absolute right-0 z-20 mt-2 grid w-64 gap-3 border bg-background p-4 shadow-lg"
            >
                <input
                    type="hidden"
                    name="payables[0]"
                    value={`${item.kind === 'expense' ? 'expense' : 'invoice'}:${item.id}`}
                />
                <Field label="Payment date">
                    <Input
                        name="planned_on"
                        type="date"
                        defaultValue={today()}
                        required
                    />
                </Field>
                <Field label="Bank account">
                    <select
                        name="bank_account_id"
                        className={nativeSelectClass}
                    >
                        <option value="">Select later</option>
                        {accounts.map((account) => (
                            <option key={account.id} value={account.id}>
                                {account.name}
                                {account.iban ? '' : ' · no IBAN'}
                            </option>
                        ))}
                    </select>
                </Field>
                <Button size="sm">
                    <WalletCards /> Prepare {money(item.outstanding)}
                </Button>
            </Form>
        </details>
    );
}
function ForecastForm() {
    return (
        <Form
            action="/finance/forecast-items"
            method="post"
            resetOnSuccess
            className="grid gap-3 border-y py-3 sm:grid-cols-2"
        >
            <Field label="Description">
                <Input
                    name="description"
                    placeholder="Payroll, VAT, expected grant…"
                    required
                />
            </Field>
            <Field label="Category">
                <Input name="category" placeholder="Payroll" required />
            </Field>
            <Field label="Direction">
                <select
                    name="direction"
                    className={nativeSelectClass}
                    defaultValue="out"
                >
                    <option value="in">Incoming</option>
                    <option value="out">Outgoing</option>
                </select>
            </Field>
            <Field label="Due date">
                <Input
                    name="due_on"
                    type="date"
                    defaultValue={today()}
                    required
                />
            </Field>
            <Field label="Amount">
                <Input
                    name="amount"
                    type="number"
                    min="0.01"
                    step="0.01"
                    required
                />
            </Field>
            <Field label="Probability">
                <Input
                    name="probability"
                    type="number"
                    min="0"
                    max="100"
                    defaultValue="100"
                    required
                />
            </Field>
            <Field label="Recurrence">
                <select name="recurrence" className={nativeSelectClass}>
                    <option value="">One-off</option>
                    <option value="monthly">Monthly</option>
                    <option value="quarterly">Quarterly</option>
                    <option value="annual">Annual</option>
                </select>
            </Field>
            <Button className="sm:col-span-2">
                <Plus /> Add forecast item
            </Button>
        </Form>
    );
}
function BudgetGovernance({
    year,
    period,
    canApprove,
}: {
    year: number;
    period: BudgetPeriod;
    canApprove: boolean;
}) {
    const complete = period.configuredValues === period.requiredValues;

    return (
        <div className="mt-4 border-y py-3 text-sm">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <span className="font-medium">
                        {period.configuredValues}/{period.requiredValues}{' '}
                        configured values
                    </span>
                    {period.approvedAt && (
                        <span className="ml-2 text-muted-foreground">
                            Last approved {dateTime(period.approvedAt)}
                            {period.approvedBy
                                ? ` by ${period.approvedBy}`
                                : ''}
                        </span>
                    )}
                </div>
                {!complete && period.status === 'draft' && (
                    <Badge variant="outline">
                        {period.requiredValues - period.configuredValues}{' '}
                        missing
                    </Badge>
                )}
            </div>
            {period.reopenedAt && period.reopenReason && (
                <p className="mt-2 text-xs text-muted-foreground">
                    Reopened {dateTime(period.reopenedAt)} ·{' '}
                    {period.reopenReason}
                </p>
            )}
            {period.versions.length > 0 && (
                <details className="mt-3 border-t pt-3">
                    <summary className="cursor-pointer font-medium">
                        Approved revisions ({period.versions.length})
                    </summary>
                    <div className="mt-2 divide-y border-y">
                        {period.versions.map((version) => (
                            <div
                                key={version.id}
                                className="grid gap-2 py-3 sm:grid-cols-[auto_1fr_auto_auto_auto] sm:items-center"
                            >
                                <Badge variant="outline">
                                    R{version.revision}
                                </Badge>
                                <div className="min-w-0">
                                    <p>
                                        {dateTime(version.approvedAt)}
                                        {version.approvedBy
                                            ? ` · ${version.approvedBy}`
                                            : ''}
                                    </p>
                                    {version.approvalNotes && (
                                        <p className="truncate text-xs text-muted-foreground">
                                            {version.approvalNotes}
                                        </p>
                                    )}
                                </div>
                                <span className="text-muted-foreground">
                                    Result {money(version.operatingResult)}
                                </span>
                                <span className="text-muted-foreground">
                                    Cash {money(version.cashNet)}
                                </span>
                                <Button variant="ghost" size="sm" asChild>
                                    <a
                                        href={`/finance/budgets/${year}/versions/${version.id}/export`}
                                    >
                                        <Download /> CSV
                                    </a>
                                </Button>
                            </div>
                        ))}
                    </div>
                </details>
            )}
            {canApprove && period.status === 'draft' && (
                <Form
                    action={`/finance/budgets/${year}/approve`}
                    method="post"
                    className="mt-3 grid gap-3 border-t pt-3 sm:grid-cols-[10rem_1fr_auto]"
                >
                    <Field label={`Confirm ${year}`}>
                        <Input
                            name="confirmation"
                            placeholder={String(year)}
                            required
                        />
                    </Field>
                    <Field label="Approval notes">
                        <Input
                            name="approval_notes"
                            placeholder="Approved assumptions and scope"
                        />
                    </Field>
                    <div className="flex items-end">
                        <Button disabled={!complete}>Approve and lock</Button>
                    </div>
                </Form>
            )}
            {canApprove && period.status === 'approved' && (
                <Form
                    action={`/finance/budgets/${year}/reopen`}
                    method="post"
                    className="mt-3 grid gap-3 border-t pt-3 sm:grid-cols-[1fr_auto]"
                >
                    <Field label="Reason for reopening">
                        <Input
                            name="reopen_reason"
                            minLength={5}
                            placeholder="Explain why the approved budget must change"
                            required
                        />
                    </Field>
                    <div className="flex items-end">
                        <Button variant="outline">Reopen budget</Button>
                    </div>
                </Form>
            )}
        </div>
    );
}
function BudgetForm({
    year,
    budgets,
    performance,
    cashFlow,
    alertSettings,
    alertCount,
}: {
    year: number;
    budgets: Budget[];
    performance: Performance[];
    cashFlow: Flow[];
    alertSettings: {
        operatingVariancePercent: number;
        cashVariancePercent: number;
        liquidityBufferAmount: number;
    };
    alertCount: number;
}) {
    return (
        <div className="mt-4 space-y-3 border-y py-3">
            <Form
                action="/finance/budgets"
                method="post"
                className="grid gap-3 sm:grid-cols-4"
            >
                <input type="hidden" name="year" value={year} />
                <Field label="Month">
                    <select
                        name="month"
                        className={nativeSelectClass}
                        defaultValue={new Date().getMonth() + 1}
                    >
                        {Array.from(
                            { length: 12 },
                            (_, index) => index + 1,
                        ).map((month) => (
                            <option key={month} value={month}>
                                {monthLabel(month)}
                            </option>
                        ))}
                    </select>
                </Field>
                <Field label="Category">
                    <select
                        name="category"
                        className={nativeSelectClass}
                        defaultValue="revenue"
                    >
                        <option value="revenue">Revenue</option>
                        <option value="costs">Costs</option>
                        <option value="cash_in">Cash in</option>
                        <option value="cash_out">Cash out</option>
                    </select>
                </Field>
                <Field label="Amount">
                    <Input
                        name="amount"
                        type="number"
                        min="0"
                        step="0.01"
                        required
                    />
                </Field>
                <div className="flex items-end">
                    <Button className="w-full">
                        <Target /> Save
                    </Button>
                </div>
            </Form>
            <Form
                action="/finance/budgets/copy"
                method="post"
                className="grid gap-3 border-t pt-3 sm:grid-cols-[1fr_1fr_auto]"
            >
                <input type="hidden" name="source_year" value={year - 1} />
                <input type="hidden" name="target_year" value={year} />
                <Field label={`Adjustment from ${year - 1}`}>
                    <Input
                        name="adjustment_percent"
                        type="number"
                        min="-100"
                        max="1000"
                        step="0.1"
                        defaultValue="0"
                        required
                    />
                </Field>
                <label className="flex items-end gap-2 pb-2 text-sm">
                    <input type="checkbox" name="overwrite" value="1" />
                    Overwrite existing values
                </label>
                <div className="flex items-end">
                    <Button variant="outline">Copy previous year</Button>
                </div>
            </Form>
            <Form
                action="/finance/budgets/import"
                method="post"
                encType="multipart/form-data"
                className="grid gap-3 border-t pt-3 sm:grid-cols-[1fr_1fr_auto]"
            >
                <input type="hidden" name="year" value={year} />
                <Field label="Budget CSV">
                    <Input
                        name="budget_file"
                        type="file"
                        accept=".csv,text/csv"
                        required
                    />
                </Field>
                <label className="flex items-end gap-2 pb-2 text-sm">
                    <input type="checkbox" name="overwrite" value="1" />
                    Overwrite existing values
                </label>
                <div className="flex flex-wrap items-end gap-2">
                    <Button variant="outline" asChild>
                        <a
                            href={`/finance/export?type=budget_template&year=${year}`}
                        >
                            <Download /> Template
                        </a>
                    </Button>
                    <Button>Import CSV</Button>
                </div>
            </Form>
            <Form
                action="/finance/alert-settings"
                method="patch"
                className="grid gap-3 border-t pt-3 sm:grid-cols-4"
            >
                <Field label="Operating variance alert (%)">
                    <Input
                        name="operating_variance_alert_percent"
                        type="number"
                        min="0.1"
                        max="1000"
                        step="0.1"
                        defaultValue={alertSettings.operatingVariancePercent}
                        required
                    />
                </Field>
                <Field label="Cash variance alert (%)">
                    <Input
                        name="cash_variance_alert_percent"
                        type="number"
                        min="0.1"
                        max="1000"
                        step="0.1"
                        defaultValue={alertSettings.cashVariancePercent}
                        required
                    />
                </Field>
                <Field label="Minimum liquidity buffer">
                    <Input
                        name="liquidity_buffer_amount"
                        type="number"
                        min="0"
                        step="0.01"
                        defaultValue={alertSettings.liquidityBufferAmount}
                        required
                    />
                </Field>
                <div className="flex items-end">
                    <Button variant="outline" className="w-full">
                        Save alerts
                    </Button>
                </div>
            </Form>
            <details className="border-t pt-3 text-sm" open={alertCount > 0}>
                <summary className="cursor-pointer font-medium">
                    Monthly variance matrix · {budgets.length} configured values
                </summary>
                <div className="mt-3 overflow-x-auto">
                    <table className="w-full min-w-[42rem] text-xs">
                        <thead>
                            <tr className="border-b text-left text-muted-foreground">
                                <th className="py-2">Month</th>
                                <th className="text-right">Budget result</th>
                                <th className="text-right">Actual result</th>
                                <th className="text-right">Variance</th>
                                <th className="text-right">Budget cash</th>
                                <th className="text-right">Actual cash</th>
                                <th className="text-right">Variance</th>
                            </tr>
                        </thead>
                        <tbody>
                            {performance.map((month, index) => (
                                <tr
                                    key={month.month}
                                    id={`budget-month-${index + 1}`}
                                    className="border-b last:border-0"
                                >
                                    <td className="py-2 font-medium">
                                        {month.month}
                                    </td>
                                    <td className="text-right">
                                        {money(month.budgetResult)}
                                    </td>
                                    <td className="text-right">
                                        {money(month.result)}
                                    </td>
                                    <td className="text-right">
                                        {money(
                                            month.result - month.budgetResult,
                                        )}
                                    </td>
                                    <td className="text-right">
                                        {money(cashFlow[index].budgetNet)}
                                    </td>
                                    <td className="text-right">
                                        {money(cashFlow[index].net)}
                                    </td>
                                    <td className="text-right">
                                        {money(
                                            cashFlow[index].net -
                                                cashFlow[index].budgetNet,
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </details>
            {budgets.length > 0 && (
                <details className="border-t pt-3 text-sm">
                    <summary className="cursor-pointer font-medium">
                        Manage configured values
                    </summary>
                    <div className="mt-3 grid gap-2 sm:grid-cols-2">
                        {budgets.map((budget) => (
                            <div
                                key={budget.id}
                                className="flex items-center justify-between gap-3 border-b py-2"
                            >
                                <span>
                                    {monthLabel(budget.month)} ·{' '}
                                    {budget.category.replace('_', ' ')} ·{' '}
                                    {money(Number(budget.amount))}
                                </span>
                                <Form
                                    action={`/finance/budgets/${budget.id}`}
                                    method="delete"
                                >
                                    <Button size="sm" variant="ghost">
                                        Remove
                                    </Button>
                                </Form>
                            </div>
                        ))}
                    </div>
                </details>
            )}
        </div>
    );
}
function Field({
    label,
    children,
}: {
    label: string;
    children: React.ReactNode;
}) {
    return (
        <label className="grid gap-1.5 text-sm">
            <Label>{label}</Label>
            {children}
        </label>
    );
}
function Kpi({
    label,
    value,
    detail,
}: {
    label: string;
    value: string;
    detail?: string;
}) {
    return (
        <div className="border-r p-4 last:border-0">
            <div className="text-xs text-muted-foreground">{label}</div>
            <div className="mt-1 text-xl font-semibold">{value}</div>
            {detail && (
                <div className="text-xs text-muted-foreground">{detail}</div>
            )}
        </div>
    );
}
function Total({
    label,
    value,
    strong = false,
}: {
    label: string;
    value: number;
    strong?: boolean;
}) {
    return (
        <div
            className={`flex justify-between border-b py-3 last:border-0 ${strong ? 'font-semibold' : ''}`}
        >
            <span>{label}</span>
            <span>{money(value)}</span>
        </div>
    );
}
function Empty({ text }: { text: string }) {
    return (
        <div className="py-10 text-center text-sm text-muted-foreground">
            {text}
        </div>
    );
}
const money = (v: number) =>
    new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: 'EUR',
    }).format(v);
const monthLabel = (month: number) =>
    new Intl.DateTimeFormat(undefined, { month: 'short' }).format(
        new Date(2024, month - 1, 1),
    );
const tooltipStyle = {
    background: 'var(--background)',
    border: '1px solid var(--border)',
    borderRadius: 6,
};
const nativeSelectClass =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50';
const today = () => new Date().toISOString().slice(0, 10);
const date = (v: string) =>
    new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(
        new Date(`${v.slice(0, 10)}T12:00:00`),
    );
const dateTime = (v: string) =>
    new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(v));
FinanceIndex.layout = { breadcrumbs: [{ title: 'Finance', href: '/finance' }] };

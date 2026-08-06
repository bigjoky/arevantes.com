import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowDownToLine,
    Download,
    Scale,
    Landmark,
    LibraryBig,
    TrendingDown,
    TrendingUp,
    Wallet,
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
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
type Due = {
    id: number;
    kind?: string;
    number: string;
    party: string;
    due_date: string | null;
    status: string;
    outstanding: number;
};
type Profit = {
    installation_id: number;
    reference: string | null;
    revenue: number;
    tracked_cost: number;
    labour_cost: number;
    margin: number;
};
type Flow = { month: string; in: number; out: number; net: number };
export default function FinanceIndex({
    year,
    summary,
    cashFlow,
    vat,
    receivables,
    payables,
    profitability,
}: {
    year: number;
    summary: {
        cashIn: number;
        cashOut: number;
        receivable: number;
        payable: number;
        sales: number;
    };
    cashFlow: Flow[];
    vat: { output: number; input: number };
    receivables: Due[];
    payables: Due[];
    profitability: Profit[];
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
                        <Button variant="outline" asChild>
                            <a href={`/finance/export?type=cash&year=${year}`}>
                                <Download /> Cash CSV
                            </a>
                        </Button>
                        <Button variant="outline" asChild>
                            <a href={`/finance/export?type=vat&year=${year}`}>
                                <Download /> VAT CSV
                            </a>
                        </Button>
                    </div>
                </header>
                <section className="grid grid-cols-2 border-y lg:grid-cols-5">
                    <Metric
                        label="Sales"
                        value={summary.sales}
                        icon={<Wallet />}
                    />
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
                        label="To collect"
                        value={summary.receivable}
                        icon={<ArrowDownToLine />}
                    />
                    <Metric
                        label="To pay"
                        value={summary.payable}
                        icon={<Scale />}
                    />
                </section>
                <section>
                    <h2 className="font-semibold">Cash flow</h2>
                    <p className="mb-3 text-sm text-muted-foreground">
                        Actual customer and supplier payments by month.
                    </p>
                    <div className="h-72 w-full">
                        <ResponsiveContainer width="100%" height="100%">
                            <BarChart
                                data={cashFlow}
                                margin={{ left: 8, right: 8 }}
                            >
                                <CartesianGrid
                                    strokeDasharray="3 3"
                                    stroke="var(--border)"
                                />
                                <XAxis
                                    dataKey="month"
                                    stroke="var(--muted-foreground)"
                                    fontSize={12}
                                />
                                <YAxis
                                    stroke="var(--muted-foreground)"
                                    fontSize={12}
                                />
                                <Tooltip
                                    contentStyle={{
                                        background: 'var(--background)',
                                        border: '1px solid var(--border)',
                                        borderRadius: 6,
                                    }}
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
                                <Line
                                    type="monotone"
                                    dataKey="net"
                                    stroke="var(--foreground)"
                                />
                            </BarChart>
                        </ResponsiveContainer>
                    </div>
                </section>
                <section className="grid gap-8 xl:grid-cols-2">
                    <DueTable
                        title="Accounts receivable"
                        items={receivables}
                        base="/sales"
                    />
                    <DueTable title="Accounts payable" items={payables} />
                </section>
                <section className="grid gap-8 border-t pt-5 xl:grid-cols-[.7fr_1.3fr]">
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
                            Revenue less dispatched material, recorded labour
                            and travel. Overhead and unlinked expenses are not
                            included.
                        </p>
                        <div className="overflow-x-auto border-y">
                            <table className="w-full min-w-[36rem] text-sm">
                                <thead>
                                    <tr className="border-b text-left text-muted-foreground">
                                        <th className="py-2">Installation</th>
                                        <th className="text-right">Revenue</th>
                                        <th className="text-right">Material</th>
                                        <th className="text-right">
                                            Labour & travel
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
                                            <td className="text-right font-semibold">
                                                {money(item.margin)}
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
}: {
    label: string;
    value: number;
    icon: React.ReactNode;
}) {
    return (
        <div className="border-r p-4 last:border-0">
            <div className="flex items-center gap-2 text-sm text-muted-foreground [&_svg]:size-4">
                {icon}
                {label}
            </div>
            <div className="mt-1 text-xl font-semibold">{money(value)}</div>
        </div>
    );
}
function DueTable({
    title,
    items,
    base,
}: {
    title: string;
    items: Due[];
    base?: string;
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
                            <th>Status</th>
                            <th className="text-right">Outstanding</th>
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
                                    <Badge variant="outline">
                                        {item.status}
                                    </Badge>
                                </td>
                                <td className="text-right font-medium">
                                    {money(item.outstanding)}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
                {items.length === 0 && <Empty text="Nothing outstanding." />}
            </div>
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
const date = (v: string) =>
    new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(
        new Date(`${v.slice(0, 10)}T12:00:00`),
    );
FinanceIndex.layout = { breadcrumbs: [{ title: 'Finance', href: '/finance' }] };

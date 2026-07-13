import { Form, Head } from '@inertiajs/react';
import { FileText, Gauge, Headphones, Wrench } from 'lucide-react';
import { useState } from 'react';
import BrandLogo from '@/components/brand-logo';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
type Monitoring = {
    provider: string | null;
    status: string;
    current_power_kw: string | null;
    today_energy_kwh: string | null;
    synced_at: string | null;
};
type Installation = {
    id: number;
    service_type: string;
    status: string;
    project_reference: string | null;
    site_name: string | null;
    site_city: string | null;
    completed_date: string | null;
    system_power_kw: string | null;
    monitoring: Monitoring;
};
type Quote = {
    id: number;
    number: string;
    status: string;
    issue_date: string;
    valid_until: string | null;
    amount: string;
};
type Document = {
    id: number;
    type: string;
    number: string;
    status: string;
    issue_date: string;
    due_date: string | null;
    amount: string;
    paid_amount: string;
};
type Ticket = {
    id: number;
    installation_id: number;
    number: string;
    kind: string;
    priority: string;
    status: string;
    title: string;
    created_at: string;
    resolved_at: string | null;
};
type Maintenance = {
    id: number;
    installation_id: number;
    name: string;
    next_due_on: string;
    last_completed_on: string | null;
};
type Tab = 'overview' | 'documents' | 'support';
export default function Portal({
    company,
    customer,
    token,
    installations,
    quotes,
    documents,
    tickets,
    maintenance,
}: {
    company: {
        legal_name: string;
        trade_name: string | null;
        email: string | null;
        phone: string | null;
    } | null;
    customer: {
        first_name: string;
        last_name: string | null;
        company_name: string | null;
        email: string | null;
    };
    token: string;
    installations: Installation[];
    quotes: Quote[];
    documents: Document[];
    tickets: Ticket[];
    maintenance: Maintenance[];
}) {
    const [tab, setTab] = useState<Tab>('overview');
    const name =
        customer.company_name ||
        `${customer.first_name} ${customer.last_name || ''}`.trim();

    return (
        <>
            <Head title="Customer portal" />
            <main className="min-h-screen bg-background text-foreground">
                <header className="border-b">
                    <div className="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-5">
                        <div className="flex items-center gap-3">
                            <BrandLogo className="h-14" />
                            <div className="text-sm text-muted-foreground">
                                <span className="sr-only">
                                    {company?.trade_name ||
                                        company?.legal_name ||
                                        'Arevantes'}
                                </span>
                                Portal de clientes
                            </div>
                        </div>
                        <div className="text-right text-sm">
                            <div className="font-medium">{name}</div>
                            <div className="text-muted-foreground">
                                {customer.email}
                            </div>
                        </div>
                    </div>
                </header>
                <div className="mx-auto grid max-w-6xl gap-7 px-4 py-7">
                    <div className="flex gap-1 border-b">
                        <TabButton
                            active={tab === 'overview'}
                            onClick={() => setTab('overview')}
                            icon={<Gauge />}
                        >
                            Overview
                        </TabButton>
                        <TabButton
                            active={tab === 'documents'}
                            onClick={() => setTab('documents')}
                            icon={<FileText />}
                        >
                            Documents
                        </TabButton>
                        <TabButton
                            active={tab === 'support'}
                            onClick={() => setTab('support')}
                            icon={<Headphones />}
                        >
                            Support
                        </TabButton>
                    </div>
                    {tab === 'overview' && (
                        <>
                            <section>
                                <h1 className="text-xl font-semibold">
                                    Your installations
                                </h1>
                                <div className="mt-4 grid gap-5 md:grid-cols-2">
                                    {installations.map((item) => (
                                        <div
                                            key={item.id}
                                            className="border-t pt-4"
                                        >
                                            <div className="flex items-start justify-between gap-3">
                                                <div>
                                                    <h2 className="font-semibold">
                                                        {item.project_reference ||
                                                            item.site_name ||
                                                            `Installation #${item.id}`}
                                                    </h2>
                                                    <p className="text-sm text-muted-foreground capitalize">
                                                        {item.service_type.replaceAll(
                                                            '_',
                                                            ' ',
                                                        )}{' '}
                                                        · {item.site_city}
                                                    </p>
                                                </div>
                                                <Badge variant="outline">
                                                    {item.status}
                                                </Badge>
                                            </div>
                                            <div className="mt-4 grid grid-cols-3 border-y py-3 text-sm">
                                                <Info
                                                    label="System"
                                                    value={
                                                        item.system_power_kw
                                                            ? `${item.system_power_kw} kW`
                                                            : '—'
                                                    }
                                                />
                                                <Info
                                                    label="Now"
                                                    value={
                                                        item.monitoring
                                                            .current_power_kw
                                                            ? `${item.monitoring.current_power_kw} kW`
                                                            : '—'
                                                    }
                                                />
                                                <Info
                                                    label="Today"
                                                    value={
                                                        item.monitoring
                                                            .today_energy_kwh
                                                            ? `${item.monitoring.today_energy_kwh} kWh`
                                                            : '—'
                                                    }
                                                />
                                            </div>
                                            <div className="mt-3 flex items-center gap-2 text-sm">
                                                <span
                                                    className={`size-2 rounded-full ${statusColor(item.monitoring.status)}`}
                                                />
                                                <span className="capitalize">
                                                    {item.monitoring.status.replaceAll(
                                                        '_',
                                                        ' ',
                                                    )}
                                                </span>
                                                {item.monitoring.synced_at && (
                                                    <span className="text-muted-foreground">
                                                        · updated{' '}
                                                        {dateTime(
                                                            item.monitoring
                                                                .synced_at,
                                                        )}
                                                    </span>
                                                )}
                                            </div>
                                        </div>
                                    ))}
                                </div>
                                {installations.length === 0 && (
                                    <Empty text="No installations linked to this customer." />
                                )}
                            </section>
                            <section className="border-t pt-5">
                                <h2 className="font-semibold">
                                    Upcoming maintenance
                                </h2>
                                <div className="mt-3 border-y">
                                    {maintenance.map((plan) => (
                                        <div
                                            key={plan.id}
                                            className="flex justify-between gap-3 border-b py-3 text-sm last:border-0"
                                        >
                                            <span className="font-medium">
                                                {plan.name}
                                            </span>
                                            <span>
                                                {date(plan.next_due_on)}
                                            </span>
                                        </div>
                                    ))}
                                    {maintenance.length === 0 && (
                                        <Empty text="No scheduled maintenance." />
                                    )}
                                </div>
                            </section>
                        </>
                    )}
                    {tab === 'documents' && (
                        <section className="grid gap-7">
                            <DocumentTable
                                title="Quotes"
                                items={quotes.map((q) => ({
                                    id: q.id,
                                    kind: 'quote',
                                    number: q.number,
                                    status: q.status,
                                    date: q.issue_date,
                                    amount: q.amount,
                                }))}
                                token={token}
                            />
                            <DocumentTable
                                title="Sales documents"
                                items={documents.map((d) => ({
                                    id: d.id,
                                    kind: d.type,
                                    number: d.number,
                                    status: d.status,
                                    date: d.issue_date,
                                    amount: d.amount,
                                }))}
                                token={token}
                            />
                        </section>
                    )}
                    {tab === 'support' && (
                        <section className="grid gap-8 lg:grid-cols-[22rem_1fr]">
                            <Form
                                action={`/portal/${token}/tickets`}
                                method="post"
                                resetOnSuccess
                                className="grid content-start gap-4 border-t pt-4"
                            >
                                <div>
                                    <h2 className="font-semibold">
                                        Request assistance
                                    </h2>
                                    <p className="text-sm text-muted-foreground">
                                        Describe the issue and our technical
                                        team will review it.
                                    </p>
                                </div>
                                <div className="grid gap-2">
                                    <Label>Installation</Label>
                                    <select
                                        name="installation_id"
                                        className="h-9 rounded-md border bg-background px-3 text-sm"
                                    >
                                        {installations.map((item) => (
                                            <option
                                                key={item.id}
                                                value={item.id}
                                            >
                                                {item.project_reference ||
                                                    item.site_name ||
                                                    `#${item.id}`}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                                <div className="grid gap-2">
                                    <Label>Subject</Label>
                                    <Input name="title" required />
                                </div>
                                <div className="grid gap-2">
                                    <Label>Priority</Label>
                                    <select
                                        name="priority"
                                        className="h-9 rounded-md border bg-background px-3 text-sm"
                                    >
                                        <option value="normal">Normal</option>
                                        <option value="low">Low</option>
                                        <option value="high">High</option>
                                    </select>
                                </div>
                                <div className="grid gap-2">
                                    <Label>Description</Label>
                                    <textarea
                                        name="description"
                                        required
                                        className="min-h-28 rounded-md border bg-transparent p-3 text-sm"
                                    />
                                </div>
                                <Button disabled={installations.length === 0}>
                                    <Wrench /> Send request
                                </Button>
                            </Form>
                            <div>
                                <h2 className="mb-3 font-semibold">
                                    Your requests
                                </h2>
                                <div className="border-y">
                                    {tickets.map((ticket) => (
                                        <div
                                            key={ticket.id}
                                            className="flex items-start justify-between gap-4 border-b py-3 text-sm last:border-0"
                                        >
                                            <div>
                                                <div className="font-medium">
                                                    {ticket.number} ·{' '}
                                                    {ticket.title}
                                                </div>
                                                <div className="text-xs text-muted-foreground">
                                                    Created{' '}
                                                    {dateTime(
                                                        ticket.created_at,
                                                    )}{' '}
                                                    · {ticket.priority}
                                                </div>
                                            </div>
                                            <Badge variant="outline">
                                                {ticket.status}
                                            </Badge>
                                        </div>
                                    ))}
                                    {tickets.length === 0 && (
                                        <Empty text="No support requests." />
                                    )}
                                </div>
                            </div>
                        </section>
                    )}
                </div>
            </main>
        </>
    );
}
type DocumentRow = {
    id: number;
    kind: string;
    number: string;
    status: string;
    date: string;
    amount: string;
};
function DocumentTable({
    title,
    items,
    token,
}: {
    title: string;
    items: DocumentRow[];
    token: string;
}) {
    return (
        <div>
            <h2 className="mb-3 font-semibold">{title}</h2>
            <div className="overflow-x-auto border-y">
                <table className="w-full min-w-[38rem] text-sm">
                    <thead>
                        <tr className="border-b text-left text-muted-foreground">
                            <th className="py-2">Document</th>
                            <th>Type</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th className="text-right">Total</th>
                            <th className="w-24" />
                        </tr>
                    </thead>
                    <tbody>
                        {items.map((item) => (
                            <tr
                                key={`${item.kind}-${item.id}`}
                                className="border-b last:border-0"
                            >
                                <td className="py-3 font-medium">
                                    {item.number}
                                </td>
                                <td className="capitalize">
                                    {item.kind.replaceAll('_', ' ')}
                                </td>
                                <td>{date(item.date)}</td>
                                <td>
                                    <Badge variant="outline">
                                        {item.status}
                                    </Badge>
                                </td>
                                <td className="text-right">
                                    {money(item.amount)}
                                </td>
                                <td>
                                    <Button size="sm" variant="outline" asChild>
                                        <a
                                            href={`/portal/${token}/documents/${item.kind === 'quote' ? 'quote' : 'sales'}/${item.id}/pdf`}
                                            target="_blank"
                                            rel="noreferrer"
                                        >
                                            PDF
                                        </a>
                                    </Button>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
                {items.length === 0 && <Empty text="No documents available." />}
            </div>
        </div>
    );
}
function TabButton({
    active,
    onClick,
    icon,
    children,
}: {
    active: boolean;
    onClick: () => void;
    icon: React.ReactNode;
    children: React.ReactNode;
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            className={`flex items-center gap-2 border-b-2 px-3 py-2 text-sm [&_svg]:size-4 ${active ? 'border-primary font-medium' : 'border-transparent text-muted-foreground'}`}
        >
            {icon}
            {children}
        </button>
    );
}
function Info({ label, value }: { label: string; value: string }) {
    return (
        <div>
            <div className="text-xs text-muted-foreground">{label}</div>
            <div className="mt-1 font-medium">{value}</div>
        </div>
    );
}
function Empty({ text }: { text: string }) {
    return (
        <div className="py-9 text-center text-sm text-muted-foreground">
            {text}
        </div>
    );
}
const statusColor = (status: string) =>
    status === 'healthy' || status === 'producing'
        ? 'bg-green-600'
        : status === 'fault'
          ? 'bg-red-600'
          : status === 'disconnected'
            ? 'bg-amber-500'
            : 'bg-muted-foreground/40';
const money = (v: string) =>
    new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: 'EUR',
    }).format(Number(v));
const date = (v: string) =>
    new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(
        new Date(`${v.slice(0, 10)}T12:00:00`),
    );
const dateTime = (v: string) =>
    new Intl.DateTimeFormat(undefined, {
        dateStyle: 'short',
        timeStyle: 'short',
    }).format(new Date(v));

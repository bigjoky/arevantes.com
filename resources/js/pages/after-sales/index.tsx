import { Form, Head, Link } from '@inertiajs/react';
import {
    CalendarClock,
    HeartPulse,
    PenLine,
    Plus,
    ShieldCheck,
    Wrench,
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
type Installation = {
    id: number;
    project_reference: string | null;
    site_name: string | null;
    service_type: string;
    status: string;
    customer?: {
        first_name: string;
        last_name: string | null;
        company_name: string | null;
    } | null;
};
type User = { id: number; name: string };
type WorkTemplate = { id: number; name: string; kind: string };
type SlaPolicy = {
    id: number;
    name: string;
    priority: string;
    first_response_minutes: number;
    resolution_minutes: number;
    escalation_minutes: number;
};
type Ticket = {
    id: number;
    number: string;
    kind: string;
    priority: string;
    status: string;
    title: string;
    description: string | null;
    resolution: string | null;
    first_response_due_at: string | null;
    resolution_due_at: string | null;
    escalation_level: number;
    installation: Installation;
    assigned_user: User | null;
    work_order: { id: number; number: string; status: string } | null;
};
type Plan = {
    id: number;
    name: string;
    interval_months: number;
    lead_days: number;
    starts_on: string;
    next_due_on: string;
    last_completed_on: string | null;
    is_active: boolean;
    generate_work_order: boolean;
    scope: string | null;
    installation: Installation;
    assigned_user: User | null;
    template: WorkTemplate | null;
};
type Warranty = {
    id: number;
    installation_id: number;
    serial_number: string;
    status: string;
    warranty_starts_on: string | null;
    warranty_ends_on: string | null;
    product: { name: string; sku: string | null };
};
type Tab = 'tickets' | 'maintenance' | 'warranties';
export default function AfterSalesIndex({
    tickets,
    plans,
    installations,
    users,
    warranties,
    metrics,
    slaPolicies,
    templates,
    canManage,
}: {
    tickets: Ticket[];
    plans: Plan[];
    installations: Installation[];
    users: User[];
    warranties: Warranty[];
    metrics: {
        open: number;
        response_breaches: number;
        resolution_breaches: number;
        average_first_response_minutes: number;
        average_resolution_minutes: number;
        recurrences: number;
        satisfaction: number;
    };
    slaPolicies: SlaPolicy[];
    templates: WorkTemplate[];
    canManage: boolean;
}) {
    const [tab, setTab] = useState<Tab>('tickets');

    return (
        <>
            <Head title="After-sales" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <header className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold">After-sales</h1>
                        <p className="text-sm text-muted-foreground">
                            Incidents, preventive maintenance and installed
                            equipment warranties.
                        </p>
                    </div>
                    {tab === 'tickets' ? (
                        <div className="flex gap-2">
                            {canManage && <SlaDialog policies={slaPolicies} />}
                            <TicketDialog
                                installations={installations}
                                users={users}
                            />
                        </div>
                    ) : tab === 'maintenance' && canManage ? (
                        <PlanDialog
                            installations={installations}
                            users={users}
                            templates={templates}
                        />
                    ) : null}
                </header>
                <section className="grid grid-cols-2 border-y md:grid-cols-4 xl:grid-cols-7">
                    <Metric label="Open tickets" value={metrics.open} />
                    <Metric
                        label="Response breaches"
                        value={metrics.response_breaches}
                    />
                    <Metric
                        label="Resolution breaches"
                        value={metrics.resolution_breaches}
                    />
                    <Metric
                        label="Avg response"
                        value={`${metrics.average_first_response_minutes} min`}
                    />
                    <Metric
                        label="Avg resolution"
                        value={`${metrics.average_resolution_minutes} min`}
                    />
                    <Metric label="Recurrences" value={metrics.recurrences} />
                    <Metric
                        label="Satisfaction"
                        value={`${metrics.satisfaction}/5`}
                    />
                </section>
                <section className="grid grid-cols-2 border-y">
                    <Metric
                        label="Maintenance due"
                        value={
                            plans.filter(
                                (p) =>
                                    p.is_active &&
                                    new Date(p.next_due_on) <= new Date(),
                            ).length
                        }
                    />
                    <Metric
                        label="Warranties expiring"
                        value={
                            warranties.filter(
                                (w) =>
                                    w.warranty_ends_on &&
                                    daysUntil(w.warranty_ends_on) <= 90 &&
                                    daysUntil(w.warranty_ends_on) >= 0,
                            ).length
                        }
                    />
                </section>
                <div className="flex gap-1 border-b">
                    <TabButton
                        active={tab === 'tickets'}
                        onClick={() => setTab('tickets')}
                        icon={<HeartPulse />}
                    >
                        Tickets
                    </TabButton>
                    <TabButton
                        active={tab === 'maintenance'}
                        onClick={() => setTab('maintenance')}
                        icon={<CalendarClock />}
                    >
                        Maintenance
                    </TabButton>
                    <TabButton
                        active={tab === 'warranties'}
                        onClick={() => setTab('warranties')}
                        icon={<ShieldCheck />}
                    >
                        Warranties
                    </TabButton>
                </div>
                {tab === 'tickets' && (
                    <section className="grid gap-4">
                        {tickets.map((ticket) => (
                            <div
                                key={ticket.id}
                                className="grid gap-4 border-t pt-4 lg:grid-cols-[1fr_22rem]"
                            >
                                <div>
                                    <div className="flex flex-wrap items-center gap-2">
                                        <span className="font-medium">
                                            {ticket.number} · {ticket.title}
                                        </span>
                                        <Badge variant="outline">
                                            {ticket.priority}
                                        </Badge>
                                        <Badge variant="outline">
                                            {ticket.status}
                                        </Badge>
                                        {ticket.escalation_level > 0 && (
                                            <Badge variant="destructive">
                                                Escalation{' '}
                                                {ticket.escalation_level}
                                            </Badge>
                                        )}
                                    </div>
                                    <Link
                                        href={`/installations/${ticket.installation.id}`}
                                        className="text-sm text-muted-foreground underline underline-offset-2"
                                    >
                                        {ticket.installation
                                            .project_reference ||
                                            ticket.installation.site_name}
                                    </Link>
                                    {ticket.description && (
                                        <p className="mt-2 text-sm text-muted-foreground">
                                            {ticket.description}
                                        </p>
                                    )}
                                    <div className="mt-2 text-xs text-muted-foreground">
                                        Response due{' '}
                                        {ticket.first_response_due_at
                                            ? dateTime(
                                                  ticket.first_response_due_at,
                                              )
                                            : '—'}{' '}
                                        · Resolution due{' '}
                                        {ticket.resolution_due_at
                                            ? dateTime(ticket.resolution_due_at)
                                            : '—'}
                                    </div>
                                    {ticket.work_order && (
                                        <Button
                                            className="mt-3"
                                            size="sm"
                                            variant="outline"
                                            asChild
                                        >
                                            <Link
                                                href={`/work-orders?work_order=${ticket.work_order.id}`}
                                            >
                                                <Wrench />{' '}
                                                {ticket.work_order.number}
                                            </Link>
                                        </Button>
                                    )}
                                </div>
                                <div>
                                    {!ticket.work_order &&
                                        !['resolved', 'closed'].includes(
                                            ticket.status,
                                        ) && (
                                            <Form
                                                action={`/after-sales/tickets/${ticket.id}/work-order`}
                                                method="post"
                                                className="mb-3"
                                            >
                                                <Button
                                                    className="w-full"
                                                    variant="outline"
                                                >
                                                    <Wrench /> Create work order
                                                </Button>
                                            </Form>
                                        )}
                                    <Form
                                        action={`/after-sales/tickets/${ticket.id}`}
                                        method="patch"
                                        className="grid gap-3"
                                    >
                                        <SelectField
                                            label="Status"
                                            name="status"
                                            value={ticket.status}
                                            options={[
                                                'open',
                                                'assigned',
                                                'in_progress',
                                                'resolved',
                                                'closed',
                                            ].map((s) => [
                                                s,
                                                s.replaceAll('_', ' '),
                                            ])}
                                        />
                                        <SelectField
                                            label="Technician"
                                            name="assigned_user_id"
                                            value={
                                                ticket.assigned_user
                                                    ? String(
                                                          ticket.assigned_user
                                                              .id,
                                                      )
                                                    : undefined
                                            }
                                            options={users.map((u) => [
                                                String(u.id),
                                                u.name,
                                            ])}
                                        />
                                        <div className="grid gap-2">
                                            <Label>Resolution</Label>
                                            <textarea
                                                name="resolution"
                                                defaultValue={
                                                    ticket.resolution || ''
                                                }
                                                className="min-h-16 rounded-md border bg-transparent p-2 text-sm"
                                            />
                                        </div>
                                        <Button size="sm">Update ticket</Button>
                                    </Form>
                                </div>
                            </div>
                        ))}
                        {tickets.length === 0 && (
                            <Empty text="No service tickets." />
                        )}
                    </section>
                )}
                {tab === 'maintenance' && (
                    <section className="overflow-x-auto">
                        <table className="w-full min-w-[50rem] text-sm">
                            <thead>
                                <tr className="border-b text-left text-muted-foreground">
                                    <th className="py-2">Plan</th>
                                    <th>Installation</th>
                                    <th>Frequency</th>
                                    <th>Last completed</th>
                                    <th>Next due</th>
                                    <th>Status</th>
                                    <th className="w-56" />
                                </tr>
                            </thead>
                            <tbody>
                                {plans.map((plan) => (
                                    <tr key={plan.id} className="border-b">
                                        <td className="py-3 font-medium">
                                            {plan.name}
                                        </td>
                                        <td>
                                            {plan.installation
                                                .project_reference ||
                                                plan.installation.site_name}
                                        </td>
                                        <td>{plan.interval_months} months</td>
                                        <td>
                                            {plan.last_completed_on
                                                ? date(plan.last_completed_on)
                                                : '—'}
                                        </td>
                                        <td
                                            className={
                                                new Date(plan.next_due_on) <=
                                                new Date()
                                                    ? 'font-medium text-destructive'
                                                    : ''
                                            }
                                        >
                                            {date(plan.next_due_on)}
                                        </td>
                                        <td>
                                            <Badge variant="outline">
                                                {plan.is_active
                                                    ? 'active'
                                                    : 'paused'}
                                            </Badge>
                                        </td>
                                        <td>
                                            {canManage && (
                                                <div className="flex gap-1">
                                                    <PlanDialog
                                                        installations={
                                                            installations
                                                        }
                                                        users={users}
                                                        templates={templates}
                                                        plan={plan}
                                                    />
                                                    <Form
                                                        action={`/after-sales/plans/${plan.id}`}
                                                        method="patch"
                                                    >
                                                        <input
                                                            type="hidden"
                                                            name="is_active"
                                                            value={
                                                                plan.is_active
                                                                    ? '0'
                                                                    : '1'
                                                            }
                                                        />
                                                        <Button
                                                            size="sm"
                                                            variant="outline"
                                                        >
                                                            {plan.is_active
                                                                ? 'Pause'
                                                                : 'Resume'}
                                                        </Button>
                                                    </Form>
                                                </div>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                        {plans.length === 0 && (
                            <Empty text="No maintenance plans." />
                        )}
                    </section>
                )}
                {tab === 'warranties' && (
                    <section className="overflow-x-auto">
                        <table className="w-full min-w-[54rem] text-sm">
                            <thead>
                                <tr className="border-b text-left text-muted-foreground">
                                    <th className="py-2">Equipment</th>
                                    <th>Serial</th>
                                    <th>Installation</th>
                                    <th>Starts</th>
                                    <th>Ends</th>
                                    <th>Status</th>
                                    <th className="w-64" />
                                </tr>
                            </thead>
                            <tbody>
                                {warranties.map((item) => {
                                    const installation = installations.find(
                                        (i) => i.id === item.installation_id,
                                    );

                                    return (
                                        <tr key={item.id} className="border-b">
                                            <td className="py-3 font-medium">
                                                {item.product.name}
                                            </td>
                                            <td className="font-mono text-xs">
                                                {item.serial_number}
                                            </td>
                                            <td>
                                                {installation?.project_reference ||
                                                    installation?.site_name ||
                                                    `#${item.installation_id}`}
                                            </td>
                                            <td>
                                                {item.warranty_starts_on
                                                    ? date(
                                                          item.warranty_starts_on,
                                                      )
                                                    : '—'}
                                            </td>
                                            <td>
                                                {item.warranty_ends_on
                                                    ? date(
                                                          item.warranty_ends_on,
                                                      )
                                                    : '—'}
                                            </td>
                                            <td>
                                                <Badge variant="outline">
                                                    {item.warranty_ends_on &&
                                                    daysUntil(
                                                        item.warranty_ends_on,
                                                    ) < 0
                                                        ? 'expired'
                                                        : 'active'}
                                                </Badge>
                                            </td>
                                            <td>
                                                {canManage && (
                                                    <Form
                                                        action={`/after-sales/warranties/${item.id}`}
                                                        method="patch"
                                                        className="flex items-end gap-2"
                                                    >
                                                        <Field
                                                            label="Starts"
                                                            name="warranty_starts_on"
                                                            type="date"
                                                            value={item.warranty_starts_on?.slice(
                                                                0,
                                                                10,
                                                            )}
                                                        />
                                                        <Field
                                                            label="Ends"
                                                            name="warranty_ends_on"
                                                            type="date"
                                                            value={item.warranty_ends_on?.slice(
                                                                0,
                                                                10,
                                                            )}
                                                        />
                                                        <Button size="sm">
                                                            Save
                                                        </Button>
                                                    </Form>
                                                )}
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                        {warranties.length === 0 && (
                            <Empty text="No installed serialized equipment." />
                        )}
                    </section>
                )}
            </div>
        </>
    );
}
function TicketDialog({
    installations,
    users,
}: {
    installations: Installation[];
    users: User[];
}) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button>
                    <Plus /> New ticket
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>New service ticket</DialogTitle>
                </DialogHeader>
                <Form
                    action="/after-sales/tickets"
                    method="post"
                    onSuccess={() => setOpen(false)}
                    className="grid gap-4"
                >
                    <SelectField
                        label="Installation"
                        name="installation_id"
                        options={installations.map((i) => [
                            String(i.id),
                            i.project_reference || i.site_name || `#${i.id}`,
                        ])}
                    />
                    <div className="grid gap-4 sm:grid-cols-2">
                        <SelectField
                            label="Kind"
                            name="kind"
                            options={[
                                ['incident', 'Incident'],
                                ['maintenance', 'Maintenance'],
                                ['warranty', 'Warranty'],
                                ['inspection', 'Inspection'],
                            ]}
                        />
                        <SelectField
                            label="Priority"
                            name="priority"
                            options={[
                                ['normal', 'Normal'],
                                ['low', 'Low'],
                                ['high', 'High'],
                                ['urgent', 'Urgent'],
                            ]}
                        />
                    </div>
                    <SelectField
                        label="Technician"
                        name="assigned_user_id"
                        options={users.map((u) => [String(u.id), u.name])}
                    />
                    <Field label="Title" name="title" />
                    <div className="grid gap-2">
                        <Label>Description</Label>
                        <textarea
                            name="description"
                            className="min-h-24 rounded-md border bg-transparent p-3 text-sm"
                        />
                    </div>
                    <Button>Create ticket</Button>
                </Form>
            </DialogContent>
        </Dialog>
    );
}
function PlanDialog({
    installations,
    users,
    templates,
    plan,
}: {
    installations: Installation[];
    users: User[];
    templates: WorkTemplate[];
    plan?: Plan;
}) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button
                    variant={plan ? 'outline' : 'default'}
                    size={plan ? 'sm' : 'default'}
                >
                    {plan ? <PenLine /> : <Plus />} {plan ? 'Edit' : 'New plan'}
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {plan ? `Edit ${plan.name}` : 'New maintenance plan'}
                    </DialogTitle>
                </DialogHeader>
                <Form
                    action={
                        plan
                            ? `/after-sales/plans/${plan.id}`
                            : '/after-sales/plans'
                    }
                    method={plan ? 'patch' : 'post'}
                    onSuccess={() => setOpen(false)}
                    className="grid gap-4"
                >
                    <SelectField
                        label="Installation"
                        name="installation_id"
                        options={installations.map((i) => [
                            String(i.id),
                            i.project_reference || i.site_name || `#${i.id}`,
                        ])}
                        value={plan ? String(plan.installation.id) : undefined}
                    />
                    <Field label="Plan name" name="name" value={plan?.name} />
                    <div className="grid gap-4 sm:grid-cols-2">
                        <SelectField
                            label="Technician"
                            name="assigned_user_id"
                            options={[
                                ['none', 'Unassigned'],
                                ...users.map((user) => [
                                    String(user.id),
                                    user.name,
                                ]),
                            ]}
                            value={
                                plan?.assigned_user
                                    ? String(plan.assigned_user.id)
                                    : 'none'
                            }
                        />
                        <SelectField
                            label="Work template"
                            name="work_order_template_id"
                            options={[
                                ['none', 'Default checklist'],
                                ...templates.map((template) => [
                                    String(template.id),
                                    template.name,
                                ]),
                            ]}
                            value={
                                plan?.template
                                    ? String(plan.template.id)
                                    : 'none'
                            }
                        />
                    </div>
                    <Field
                        label="Frequency (months)"
                        name="interval_months"
                        type="number"
                        value={String(plan?.interval_months ?? 12)}
                    />
                    <Field
                        label="Generation lead time (days)"
                        name="lead_days"
                        type="number"
                        value={String(plan?.lead_days ?? 14)}
                    />
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field
                            label="Starts"
                            name="starts_on"
                            type="date"
                            value={plan?.starts_on?.slice(0, 10) ?? today()}
                        />
                        <Field
                            label="First due"
                            name="next_due_on"
                            type="date"
                            value={plan?.next_due_on?.slice(0, 10) ?? today()}
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label>Maintenance scope</Label>
                        <textarea
                            name="scope"
                            defaultValue={plan?.scope ?? ''}
                            className="min-h-24 rounded-md border bg-transparent p-3 text-sm"
                        />
                    </div>
                    <input type="hidden" name="generate_work_order" value="0" />
                    <label className="flex items-center gap-2 text-sm">
                        <input
                            type="checkbox"
                            name="generate_work_order"
                            value="1"
                            defaultChecked={plan?.generate_work_order ?? true}
                        />
                        Generate work order automatically
                    </label>
                    <Button>{plan ? 'Save changes' : 'Create plan'}</Button>
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function SlaDialog({ policies }: { policies: SlaPolicy[] }) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="outline">
                    <ShieldCheck /> SLA policies
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Service-level policies</DialogTitle>
                </DialogHeader>
                <div className="grid gap-2">
                    {policies.map((policy) => (
                        <div
                            key={policy.id}
                            className="rounded border p-3 text-sm"
                        >
                            <div className="font-medium">
                                {policy.name} · {policy.priority}
                            </div>
                            <div className="text-muted-foreground">
                                Response {policy.first_response_minutes} min ·
                                Resolution {policy.resolution_minutes} min ·
                                Escalate {policy.escalation_minutes} min
                            </div>
                        </div>
                    ))}
                </div>
                <Form
                    action="/after-sales/sla-policies"
                    method="post"
                    onSuccess={() => setOpen(false)}
                    className="grid gap-3 border-t pt-4"
                >
                    <Field label="Policy name" name="name" />
                    <SelectField
                        label="Priority"
                        name="priority"
                        options={['low', 'normal', 'high', 'urgent'].map(
                            (priority) => [priority, priority],
                        )}
                    />
                    <div className="grid grid-cols-3 gap-3">
                        <Field
                            label="Response min"
                            name="first_response_minutes"
                            type="number"
                            value="60"
                        />
                        <Field
                            label="Resolution min"
                            name="resolution_minutes"
                            type="number"
                            value="480"
                        />
                        <Field
                            label="Escalation min"
                            name="escalation_minutes"
                            type="number"
                            value="30"
                        />
                    </div>
                    <Button>Create policy</Button>
                </Form>
            </DialogContent>
        </Dialog>
    );
}
function Metric({ label, value }: { label: string; value: number | string }) {
    return (
        <div className="border-r p-4 last:border-0">
            <div className="text-sm text-muted-foreground">{label}</div>
            <div className="mt-1 text-2xl font-semibold">{value}</div>
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
function SelectField({
    label,
    name,
    options,
    value,
}: {
    label: string;
    name: string;
    options: string[][];
    value?: string;
}) {
    return (
        <div className="grid gap-2">
            <Label>{label}</Label>
            <Select name={name} defaultValue={value || options[0]?.[0]}>
                <SelectTrigger>
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    {options.map(([id, text]) => (
                        <SelectItem key={id} value={id}>
                            {text}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
        </div>
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
            <Label className="text-xs">{label}</Label>
            <Input name={name} type={type} defaultValue={value} />
        </div>
    );
}
function Empty({ text }: { text: string }) {
    return (
        <div className="py-12 text-center text-sm text-muted-foreground">
            {text}
        </div>
    );
}
const date = (v: string) =>
    new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(
        new Date(`${v.slice(0, 10)}T12:00:00`),
    );
const dateTime = (v: string) =>
    new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(v));
const daysUntil = (v: string) =>
    Math.ceil(
        (new Date(`${v.slice(0, 10)}T12:00:00`).getTime() - Date.now()) /
            86400000,
    );
const today = () => new Date().toISOString().slice(0, 10);
AfterSalesIndex.layout = {
    breadcrumbs: [{ title: 'After-sales', href: '/after-sales' }],
};

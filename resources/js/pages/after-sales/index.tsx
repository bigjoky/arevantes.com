import { Form, Head, Link } from '@inertiajs/react';
import {
    CalendarClock,
    CircleDollarSign,
    HeartPulse,
    History,
    PenLine,
    Plus,
    ShieldCheck,
    Wrench,
} from 'lucide-react';
import { Fragment, useState } from 'react';
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
    assets?: Asset[];
};
type Asset = { id: number; asset_tag: string; name: string; category: string };
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
    version: number;
    service_level: string;
    coverage_type: string;
    included_visits: number | null;
    response_minutes: number | null;
    resolution_minutes: number | null;
    interval_months: number;
    lead_days: number;
    starts_on: string;
    contract_ends_on: string | null;
    renewal_notice_days: number;
    billing_frequency_months: number | null;
    billing_amount: string | null;
    next_billing_on: string | null;
    next_due_on: string;
    last_completed_on: string | null;
    is_active: boolean;
    generate_work_order: boolean;
    scope: string | null;
    installation: Installation;
    assigned_user: User | null;
    template: WorkTemplate | null;
    covered_assets: Asset[];
    versions: {
        id: number;
        version: number;
        change_note: string;
        created_at: string;
        creator: User | null;
    }[];
    renewal_opportunity: {
        id: number;
        code: string;
        title: string;
        stage: string;
    } | null;
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
    canManageCustomers,
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
        annual_recurring_value: number;
        renewals_due: number;
    };
    slaPolicies: SlaPolicy[];
    templates: WorkTemplate[];
    canManage: boolean;
    canManageCustomers: boolean;
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
                    <Metric
                        label="Annual recurring value"
                        value={currency(metrics.annual_recurring_value)}
                    />
                    <Metric
                        label="Renewals to prepare"
                        value={metrics.renewals_due}
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
                                    <th>Coverage</th>
                                    <th>Contract</th>
                                    <th>Recurring fee</th>
                                    <th>Status</th>
                                    <th className="w-72" />
                                </tr>
                            </thead>
                            <tbody>
                                {plans.map((plan) => (
                                    <Fragment key={plan.id}>
                                        <tr className="border-b">
                                            <td className="py-3 font-medium">
                                                {plan.name}{' '}
                                                <Badge variant="secondary">
                                                    v{plan.version}
                                                </Badge>
                                            </td>
                                            <td>
                                                {plan.installation
                                                    .project_reference ||
                                                    plan.installation.site_name}
                                            </td>
                                            <td>
                                                <div>
                                                    {label(plan.coverage_type)}{' '}
                                                    ·{' '}
                                                    {label(plan.service_level)}
                                                </div>
                                                <div className="text-xs text-muted-foreground">
                                                    {plan.covered_assets.length}{' '}
                                                    covered assets · every{' '}
                                                    {plan.interval_months}{' '}
                                                    months
                                                </div>
                                            </td>
                                            <td>
                                                <div>
                                                    {plan.contract_ends_on
                                                        ? `Ends ${date(plan.contract_ends_on)}`
                                                        : 'Open-ended'}
                                                </div>
                                                <div
                                                    className={
                                                        new Date(
                                                            plan.next_due_on,
                                                        ) <= new Date()
                                                            ? 'text-xs font-medium text-destructive'
                                                            : 'text-xs text-muted-foreground'
                                                    }
                                                >
                                                    Next visit{' '}
                                                    {date(plan.next_due_on)}
                                                </div>
                                            </td>
                                            <td>
                                                {plan.billing_amount &&
                                                plan.billing_frequency_months ? (
                                                    <>
                                                        <div>
                                                            {currency(
                                                                Number(
                                                                    plan.billing_amount,
                                                                ),
                                                            )}
                                                        </div>
                                                        <div className="text-xs text-muted-foreground">
                                                            every{' '}
                                                            {
                                                                plan.billing_frequency_months
                                                            }{' '}
                                                            months
                                                        </div>
                                                    </>
                                                ) : (
                                                    '—'
                                                )}
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
                                                            templates={
                                                                templates
                                                            }
                                                            plan={plan}
                                                        />
                                                        <Form
                                                            action={`/after-sales/plans/${plan.id}`}
                                                            method="patch"
                                                        >
                                                            <input
                                                                type="hidden"
                                                                name="change_note"
                                                                value={
                                                                    plan.is_active
                                                                        ? 'Plan paused from after-sales.'
                                                                        : 'Plan resumed from after-sales.'
                                                                }
                                                            />
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
                                                        {canManageCustomers &&
                                                            plan.contract_ends_on &&
                                                            (plan.renewal_opportunity ? (
                                                                <Button
                                                                    size="sm"
                                                                    variant="outline"
                                                                    asChild
                                                                >
                                                                    <Link
                                                                        href={`/opportunities/${plan.renewal_opportunity.id}`}
                                                                    >
                                                                        {
                                                                            plan
                                                                                .renewal_opportunity
                                                                                .code
                                                                        }
                                                                    </Link>
                                                                </Button>
                                                            ) : (
                                                                <Form
                                                                    action={`/after-sales/plans/${plan.id}/renewal`}
                                                                    method="post"
                                                                >
                                                                    <Button
                                                                        size="sm"
                                                                        variant="outline"
                                                                    >
                                                                        <CircleDollarSign />{' '}
                                                                        Renewal
                                                                    </Button>
                                                                </Form>
                                                            ))}
                                                    </div>
                                                )}
                                            </td>
                                        </tr>
                                        <tr className="border-b bg-muted/20">
                                            <td
                                                colSpan={7}
                                                className="px-3 py-2"
                                            >
                                                <details className="text-xs">
                                                    <summary className="flex cursor-pointer items-center gap-2 text-muted-foreground">
                                                        <History className="size-3.5" />{' '}
                                                        Version history (
                                                        {plan.versions.length})
                                                    </summary>
                                                    <div className="mt-2 grid gap-1 pl-5">
                                                        {plan.versions.map(
                                                            (version) => (
                                                                <div
                                                                    key={
                                                                        version.id
                                                                    }
                                                                >
                                                                    <span className="font-medium">
                                                                        v
                                                                        {
                                                                            version.version
                                                                        }
                                                                    </span>{' '}
                                                                    ·{' '}
                                                                    {
                                                                        version.change_note
                                                                    }{' '}
                                                                    ·{' '}
                                                                    {version
                                                                        .creator
                                                                        ?.name ||
                                                                        'System'}{' '}
                                                                    ·{' '}
                                                                    {dateTime(
                                                                        version.created_at,
                                                                    )}
                                                                </div>
                                                            ),
                                                        )}
                                                    </div>
                                                </details>
                                            </td>
                                        </tr>
                                    </Fragment>
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
    const [installationId, setInstallationId] = useState(
        String(plan?.installation.id ?? installations[0]?.id ?? ''),
    );
    const installation = installations.find(
        (item) => String(item.id) === installationId,
    );

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
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-3xl">
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
                        onValueChange={setInstallationId}
                    />
                    <Field label="Plan name" name="name" value={plan?.name} />
                    <div className="grid gap-4 sm:grid-cols-2">
                        <SelectField
                            label="Service level"
                            name="service_level"
                            value={plan?.service_level ?? 'standard'}
                            options={[
                                ['standard', 'Standard'],
                                ['priority', 'Priority'],
                                ['critical', 'Critical'],
                            ]}
                        />
                        <SelectField
                            label="Coverage"
                            name="coverage_type"
                            value={plan?.coverage_type ?? 'preventive'}
                            options={[
                                ['preventive', 'Preventive'],
                                ['labour', 'Labour'],
                                ['parts_and_labour', 'Parts and labour'],
                                ['full', 'Full coverage'],
                            ]}
                        />
                    </div>
                    {installation?.assets?.length ? (
                        <fieldset className="grid gap-2 rounded-md border p-3">
                            <legend className="px-1 text-sm font-medium">
                                Covered assets
                            </legend>
                            <div className="grid gap-2 sm:grid-cols-2">
                                {installation.assets.map((asset) => (
                                    <label
                                        key={asset.id}
                                        className="flex items-start gap-2 text-sm"
                                    >
                                        <input
                                            type="checkbox"
                                            name="covered_asset_ids[]"
                                            value={asset.id}
                                            defaultChecked={plan?.covered_assets.some(
                                                (item) => item.id === asset.id,
                                            )}
                                        />
                                        <span>
                                            {asset.asset_tag} · {asset.name}
                                            <span className="block text-xs text-muted-foreground">
                                                {label(asset.category)}
                                            </span>
                                        </span>
                                    </label>
                                ))}
                            </div>
                        </fieldset>
                    ) : null}
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
                    <div className="grid gap-4 sm:grid-cols-4">
                        <Field
                            label="Frequency (months)"
                            name="interval_months"
                            type="number"
                            value={String(plan?.interval_months ?? 12)}
                        />
                        <Field
                            label="Generation lead (days)"
                            name="lead_days"
                            type="number"
                            value={String(plan?.lead_days ?? 14)}
                        />
                        <Field
                            label="Included visits/year"
                            name="included_visits"
                            type="number"
                            value={plan?.included_visits?.toString()}
                        />
                        <Field
                            label="Renewal notice (days)"
                            name="renewal_notice_days"
                            type="number"
                            value={String(plan?.renewal_notice_days ?? 30)}
                        />
                    </div>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field
                            label="Response commitment (minutes)"
                            name="response_minutes"
                            type="number"
                            value={plan?.response_minutes?.toString()}
                        />
                        <Field
                            label="Resolution target (minutes)"
                            name="resolution_minutes"
                            type="number"
                            value={plan?.resolution_minutes?.toString()}
                        />
                    </div>
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
                        <Field
                            label="Contract ends"
                            name="contract_ends_on"
                            type="date"
                            value={plan?.contract_ends_on?.slice(0, 10)}
                        />
                    </div>
                    <div className="grid gap-4 rounded-md border p-3 sm:grid-cols-3">
                        <SelectField
                            label="Billing frequency"
                            name="billing_frequency_months"
                            value={
                                plan?.billing_frequency_months
                                    ? String(plan.billing_frequency_months)
                                    : 'none'
                            }
                            options={[
                                ['none', 'No recurring forecast'],
                                ['1', 'Monthly'],
                                ['3', 'Quarterly'],
                                ['12', 'Annual'],
                            ]}
                        />
                        <Field
                            label="Amount (€)"
                            name="billing_amount"
                            type="number"
                            value={plan?.billing_amount ?? undefined}
                        />
                        <Field
                            label="Next billing forecast"
                            name="next_billing_on"
                            type="date"
                            value={plan?.next_billing_on?.slice(0, 10)}
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
                    {plan ? (
                        <div className="grid gap-2">
                            <Label>Change note</Label>
                            <textarea
                                name="change_note"
                                required
                                minLength={5}
                                className="min-h-20 rounded-md border bg-transparent p-3 text-sm"
                                placeholder="Explain what changed and why…"
                            />
                        </div>
                    ) : null}
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
    onValueChange,
}: {
    label: string;
    name: string;
    options: string[][];
    value?: string;
    onValueChange?: (value: string) => void;
}) {
    return (
        <div className="grid gap-2">
            <Label>{label}</Label>
            <Select
                name={name}
                defaultValue={value || options[0]?.[0]}
                onValueChange={onValueChange}
            >
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
const label = (value: string) => value.replaceAll('_', ' ');
const currency = (value: number) =>
    new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: 'EUR',
    }).format(value);
AfterSalesIndex.layout = {
    breadcrumbs: [{ title: 'After-sales', href: '/after-sales' }],
};

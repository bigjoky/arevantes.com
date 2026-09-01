import { Form, Head, Link } from '@inertiajs/react';
import {
    CalendarClock,
    CircleDollarSign,
    History,
    PackageSearch,
    PenLine,
    Plus,
    ShieldCheck,
    Wrench,
} from 'lucide-react';
import { Fragment, useMemo, useState } from 'react';
import { AfterSalesSectionNav } from '@/components/after-sales/section-nav';
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
type Asset = {
    id: number;
    asset_tag: string;
    name: string;
    category: string;
    status?: string;
    manufacturer?: string | null;
    model?: string | null;
    serial_number?: string | null;
    last_serviced_at?: string | null;
    service_count?: number;
    warranty_ends_on?: string | null;
};
type StockProduct = {
    id: number;
    sku: string | null;
    name: string;
    supply_criticality: string;
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
    billing_frequency_months?: number | null;
    billing_amount?: string | null;
    next_billing_on?: string | null;
    next_due_on: string;
    last_completed_on: string | null;
    is_active: boolean;
    generate_work_order: boolean;
    scope: string | null;
    installation: Installation;
    assigned_user: User | null;
    template: WorkTemplate | null;
    covered_assets: Asset[];
    preventive_materials: {
        id: number;
        quantity_per_visit: string;
        expected_usage_percent: string;
        minimum_stock: string;
        notes: string | null;
        product: StockProduct;
    }[];
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
    cycle_commitments: ServiceCycleCommitment[];
};
type ServiceCycleCommitment = {
    id: number;
    maintenance_plan_version: number;
    period_starts_on: string;
    period_ends_on: string;
    status: string;
    planned_minutes: number;
    planned_revenue?: string;
    planned_labor_cost?: string;
    planned_material_cost?: string;
    planned_travel_cost?: string;
    approved_at: string;
    schedule_status: string;
    materialized_at: string | null;
    schedule_confirmed_at: string | null;
    completed_at: string | null;
    approver: User | null;
    completer: User | null;
    completion_summary: {
        completed_at: string;
        checklist_completed: number;
        checklist_required: number;
        evidence_total: number;
        pending_documents: number;
        consumed_quantity: number;
        minutes: number;
        travel_km: number;
        assets_serviced: number;
        next_due_on: string | null;
    } | null;
    service_ticket: { id: number; number: string; status: string } | null;
    work_order: {
        id: number;
        number: string;
        status: string;
        scheduled_start: string | null;
        scheduled_end: string | null;
    } | null;
    schedule_revisions: {
        id: number;
        version: number;
        status: string;
        scheduled_start: string;
        scheduled_end: string;
        reason: string;
    }[];
    billing: {
        id: number;
        status: string;
        net_amount: string;
        gross_amount: string;
        due_on: string;
        preparer: User | null;
        invoice: {
            id: number;
            number: string;
            status: string;
            amount: string;
            paid_amount: string;
            due_date: string;
        } | null;
    } | null;
    communications: {
        id: number;
        type: string;
        channel: string;
        status: string;
        subject: string;
        message: string;
        scheduled_for: string;
        confirmed_at: string | null;
        sent_at: string | null;
        delivery_note: string | null;
        confirmer: User | null;
        sender: User | null;
    }[];
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
type ServiceCapacity = {
    summary: {
        due_plans: number;
        unassigned_plans: number;
        critical_unassigned: number;
        uncommitted_service_minutes: number;
        projected_overloaded_weeks: number;
    };
    weeks: {
        week_start: string;
        week_end: string;
        remaining_minutes: number;
        uncommitted_service_minutes: number;
        projected_remaining_minutes: number;
        projected_overload_minutes: number;
        due_plans: number;
        unassigned_plans: number;
        critical_unassigned: number;
    }[];
    disclaimer: string;
};
type ServiceEconomics = {
    summary: {
        cycles: number;
        planned_revenue: number;
        planned_margin: number;
        settled_revenue: number;
        actual_cost: number;
        actual_margin: number;
        unsettled_cycles: number;
    } | null;
    cycles: {
        id: number;
        plan: string;
        plan_version: number;
        installation: string | null;
        business_line: string | null;
        period_starts_on: string;
        period_ends_on: string;
        status: string;
        planned_revenue: number;
        planned_cost: number;
        planned_margin: number;
        settled_revenue: number;
        actual_cost: number;
        actual_margin: number;
        tickets: number;
        approved_by: string | null;
    }[];
    definition: string;
};
type Section = 'tickets' | 'maintenance' | 'assets' | 'contracts';

const sectionCopy: Record<Section, { title: string; description: string }> = {
    tickets: {
        title: 'After-sales',
        description: 'Incidents, response commitments and customer support.',
    },
    maintenance: {
        title: 'Maintenance',
        description:
            'Preventive and corrective plans, capacity and upcoming visits.',
    },
    assets: {
        title: 'Assets',
        description:
            'Installed equipment, service history and warranty coverage.',
    },
    contracts: {
        title: 'Contracts & services',
        description:
            'Recurring coverage, renewals, billing commitments and service economics.',
    },
};

export default function AfterSalesIndex({
    initialSection,
    tickets,
    plans,
    installations,
    users,
    warranties,
    metrics,
    slaPolicies,
    templates,
    stockProducts,
    canManage,
    canManageCustomers,
    serviceCapacity,
    serviceEconomics,
    canViewFinancials,
    canManageBilling,
}: {
    initialSection: Section;
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
        annual_recurring_value: number | null;
        renewals_due: number;
        installed_assets: number;
        warranties_expiring: number;
    };
    slaPolicies: SlaPolicy[];
    templates: WorkTemplate[];
    stockProducts: StockProduct[];
    canManage: boolean;
    canManageCustomers: boolean;
    serviceCapacity: ServiceCapacity | null;
    serviceEconomics: ServiceEconomics | null;
    canViewFinancials: boolean;
    canManageBilling: boolean;
}) {
    const copy = sectionCopy[initialSection];
    const showsPlans =
        initialSection === 'maintenance' || initialSection === 'contracts';
    const installedAssets = useMemo(
        () =>
            initialSection === 'assets'
                ? installations.flatMap((installation) =>
                      (installation.assets || []).map((asset) => ({
                          asset,
                          installation,
                      })),
                  )
                : [],
        [initialSection, installations],
    );
    const installationsById = useMemo(
        () =>
            initialSection === 'assets'
                ? new Map(
                      installations.map((installation) => [
                          installation.id,
                          installation,
                      ]),
                  )
                : new Map<number, Installation>(),
        [initialSection, installations],
    );

    return (
        <>
            <Head title={copy.title} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <header className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold">{copy.title}</h1>
                        <p className="text-sm text-muted-foreground">
                            {copy.description}
                        </p>
                    </div>
                    {initialSection === 'tickets' ? (
                        <div className="flex gap-2">
                            {canManage && <SlaDialog policies={slaPolicies} />}
                            <TicketDialog
                                installations={installations}
                                users={users}
                            />
                        </div>
                    ) : showsPlans && canManage ? (
                        <PlanDialog
                            installations={installations}
                            users={users}
                            templates={templates}
                        />
                    ) : null}
                </header>
                <AfterSalesSectionNav />
                {initialSection === 'tickets' ? (
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
                        <Metric
                            label="Recurrences"
                            value={metrics.recurrences}
                        />
                        <Metric
                            label="Satisfaction"
                            value={`${metrics.satisfaction}/5`}
                        />
                    </section>
                ) : null}
                {initialSection === 'maintenance' ? (
                    <section className="grid grid-cols-2 border-y">
                        <Metric
                            label="Maintenance due"
                            value={
                                plans.filter(
                                    (plan) =>
                                        plan.is_active &&
                                        new Date(plan.next_due_on) <=
                                            new Date(),
                                ).length
                            }
                        />
                        <Metric label="Active plans" value={plans.length} />
                    </section>
                ) : null}
                {initialSection === 'contracts' ? (
                    <section className="grid grid-cols-2 border-y">
                        {canViewFinancials ? (
                            <Metric
                                label="Annual recurring value"
                                value={currency(
                                    metrics.annual_recurring_value || 0,
                                )}
                            />
                        ) : null}
                        <Metric
                            label="Renewals to prepare"
                            value={metrics.renewals_due}
                        />
                    </section>
                ) : null}
                {initialSection === 'assets' ? (
                    <section className="grid grid-cols-2 border-y">
                        <Metric
                            label="Installed assets"
                            value={metrics.installed_assets}
                        />
                        <Metric
                            label="Warranties expiring"
                            value={metrics.warranties_expiring}
                        />
                    </section>
                ) : null}
                {serviceCapacity ? (
                    <ServiceCapacityPanel capacity={serviceCapacity} />
                ) : null}
                {serviceEconomics && canViewFinancials ? (
                    <ServiceEconomicsPanel economics={serviceEconomics} />
                ) : null}
                {initialSection === 'tickets' && (
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
                {showsPlans && (
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
                                                {canViewFinancials &&
                                                plan.billing_amount &&
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
                                                ) : canViewFinancials ? (
                                                    '—'
                                                ) : (
                                                    'Restricted'
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
                                                        <PreventiveMaterialsDialog
                                                            plan={plan}
                                                            products={
                                                                stockProducts
                                                            }
                                                        />
                                                        <ServiceCommitmentDialog
                                                            plan={plan}
                                                            canManageBilling={
                                                                canManageBilling
                                                            }
                                                            canManageCustomers={
                                                                canManageCustomers
                                                            }
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
                {initialSection === 'assets' ? (
                    <div className="grid gap-8">
                        <section className="grid gap-3">
                            <div>
                                <h2 className="font-semibold">
                                    Installed equipment
                                </h2>
                                <p className="text-sm text-muted-foreground">
                                    Operational status and service continuity by
                                    installation.
                                </p>
                            </div>
                            <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                                {installedAssets.map(
                                    ({ asset, installation }) => (
                                        <article
                                            key={asset.id}
                                            className="grid gap-3 rounded-lg border p-4"
                                        >
                                            <div className="flex items-start justify-between gap-3">
                                                <div>
                                                    <div className="font-medium">
                                                        {asset.name}
                                                    </div>
                                                    <div className="font-mono text-xs text-muted-foreground">
                                                        {asset.asset_tag}
                                                    </div>
                                                </div>
                                                <Badge variant="outline">
                                                    {asset.status || 'active'}
                                                </Badge>
                                            </div>
                                            <dl className="grid grid-cols-2 gap-2 text-sm">
                                                <AssetDetail
                                                    label="Category"
                                                    value={label(
                                                        asset.category,
                                                    )}
                                                />
                                                <AssetDetail
                                                    label="Model"
                                                    value={
                                                        [
                                                            asset.manufacturer,
                                                            asset.model,
                                                        ]
                                                            .filter(Boolean)
                                                            .join(' ') || '—'
                                                    }
                                                />
                                                <AssetDetail
                                                    label="Serial"
                                                    value={
                                                        asset.serial_number ||
                                                        '—'
                                                    }
                                                />
                                                <AssetDetail
                                                    label="Services"
                                                    value={String(
                                                        asset.service_count ||
                                                            0,
                                                    )}
                                                />
                                                <AssetDetail
                                                    label="Last service"
                                                    value={
                                                        asset.last_serviced_at
                                                            ? date(
                                                                  asset.last_serviced_at,
                                                              )
                                                            : '—'
                                                    }
                                                />
                                                <AssetDetail
                                                    label="Warranty until"
                                                    value={
                                                        asset.warranty_ends_on
                                                            ? date(
                                                                  asset.warranty_ends_on,
                                                              )
                                                            : '—'
                                                    }
                                                />
                                            </dl>
                                            <Link
                                                href={`/installations/${installation.id}`}
                                                className="text-sm font-medium underline underline-offset-4"
                                            >
                                                {installation.project_reference ||
                                                    installation.site_name ||
                                                    `#${installation.id}`}
                                            </Link>
                                        </article>
                                    ),
                                )}
                            </div>
                            {installedAssets.length === 0 ? (
                                <Empty text="No installed assets registered." />
                            ) : null}
                        </section>
                        <section className="grid gap-3">
                            <div>
                                <h2 className="font-semibold">
                                    Serialized warranties
                                </h2>
                                <p className="text-sm text-muted-foreground">
                                    Coverage dates for traceable installed
                                    products.
                                </p>
                            </div>
                            <div className="grid gap-3 md:grid-cols-2">
                                {warranties.map((item) => {
                                    const installation = installationsById.get(
                                        item.installation_id,
                                    );

                                    return (
                                        <article
                                            key={item.id}
                                            className="grid gap-3 rounded-lg border p-4"
                                        >
                                            <div className="flex items-start justify-between gap-3">
                                                <div>
                                                    <div className="font-medium">
                                                        {item.product.name}
                                                    </div>
                                                    <div className="font-mono text-xs text-muted-foreground">
                                                        {item.serial_number}
                                                    </div>
                                                </div>
                                                <Badge variant="outline">
                                                    {item.warranty_ends_on &&
                                                    daysUntil(
                                                        item.warranty_ends_on,
                                                    ) < 0
                                                        ? 'expired'
                                                        : 'active'}
                                                </Badge>
                                            </div>
                                            <div className="text-sm text-muted-foreground">
                                                {installation?.project_reference ||
                                                    installation?.site_name ||
                                                    `#${item.installation_id}`}
                                            </div>
                                            {canManage ? (
                                                <Form
                                                    action={`/after-sales/warranties/${item.id}`}
                                                    method="patch"
                                                    className="grid gap-3 sm:grid-cols-[1fr_1fr_auto] sm:items-end"
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
                                            ) : (
                                                <div className="text-sm">
                                                    {item.warranty_starts_on
                                                        ? date(
                                                              item.warranty_starts_on,
                                                          )
                                                        : '—'}{' '}
                                                    →{' '}
                                                    {item.warranty_ends_on
                                                        ? date(
                                                              item.warranty_ends_on,
                                                          )
                                                        : '—'}
                                                </div>
                                            )}
                                        </article>
                                    );
                                })}
                            </div>
                            {warranties.length === 0 ? (
                                <Empty text="No installed serialized equipment." />
                            ) : null}
                        </section>
                    </div>
                ) : null}
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

function PreventiveMaterialsDialog({
    plan,
    products,
}: {
    plan: Plan;
    products: StockProduct[];
}) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm" variant="outline">
                    <PackageSearch /> Spares
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Preventive demand · {plan.name}</DialogTitle>
                </DialogHeader>
                <p className="text-sm text-muted-foreground">
                    Forecast only. These quantities do not reserve stock or
                    create purchase documents.
                </p>
                <div className="grid gap-2">
                    {plan.preventive_materials.map((material) => (
                        <div
                            key={material.id}
                            className="flex flex-wrap items-center justify-between gap-3 rounded-md border p-3 text-sm"
                        >
                            <div>
                                <div className="font-medium">
                                    {material.product.name}
                                    {material.product.sku
                                        ? ` · ${material.product.sku}`
                                        : ''}
                                </div>
                                <div className="text-xs text-muted-foreground">
                                    {material.quantity_per_visit} per visit ·{' '}
                                    {material.expected_usage_percent}% expected
                                    · minimum stock {material.minimum_stock}
                                </div>
                            </div>
                            <Form
                                action={`/after-sales/plans/${plan.id}/materials/${material.id}`}
                                method="delete"
                            >
                                <input
                                    type="hidden"
                                    name="change_note"
                                    value={`Removed preventive material ${material.product.name} from plan forecast.`}
                                />
                                <Button size="sm" variant="ghost">
                                    Remove
                                </Button>
                            </Form>
                        </div>
                    ))}
                    {plan.preventive_materials.length === 0 ? (
                        <Empty text="No preventive materials configured." />
                    ) : null}
                </div>
                <Form
                    action={`/after-sales/plans/${plan.id}/materials`}
                    method="post"
                    className="grid gap-4 border-t pt-4"
                >
                    <SelectField
                        label="Stock product"
                        name="product_id"
                        options={products.map((product) => [
                            String(product.id),
                            `${product.name}${product.sku ? ` · ${product.sku}` : ''} · ${label(product.supply_criticality)}`,
                        ])}
                    />
                    <div className="grid gap-4 sm:grid-cols-3">
                        <Field
                            label="Quantity per visit"
                            name="quantity_per_visit"
                            type="number"
                            step="0.001"
                            value="1"
                        />
                        <Field
                            label="Expected usage (%)"
                            name="expected_usage_percent"
                            type="number"
                            step="0.01"
                            value="100"
                        />
                        <Field
                            label="Minimum free stock"
                            name="minimum_stock"
                            type="number"
                            step="0.001"
                            value="0"
                        />
                    </div>
                    <Field label="Operational notes" name="notes" />
                    <div className="grid gap-2">
                        <Label>Change note</Label>
                        <textarea
                            name="change_note"
                            required
                            minLength={5}
                            className="min-h-20 rounded-md border bg-transparent p-3 text-sm"
                            placeholder="Why is this spare expected for the plan?"
                        />
                    </div>
                    <Button disabled={products.length === 0}>
                        Save preventive forecast
                    </Button>
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

function ServiceCapacityPanel({ capacity }: { capacity: ServiceCapacity }) {
    return (
        <details className="rounded-lg border">
            <summary className="flex cursor-pointer flex-wrap items-center justify-between gap-3 p-4">
                <span>
                    <span className="font-medium">
                        Recurring service capacity · next 6 weeks
                    </span>
                    <span className="mt-1 block text-xs text-muted-foreground">
                        {capacity.disclaimer}
                    </span>
                </span>
                <span className="flex gap-2">
                    <Badge
                        variant={
                            capacity.summary.projected_overloaded_weeks > 0
                                ? 'destructive'
                                : 'secondary'
                        }
                    >
                        {capacity.summary.projected_overloaded_weeks} overloaded
                        weeks
                    </Badge>
                    <Badge variant="outline">
                        {capacity.summary.unassigned_plans} unassigned plans
                    </Badge>
                </span>
            </summary>
            <div className="overflow-x-auto border-t">
                <table className="w-full min-w-[46rem] text-sm">
                    <thead className="text-left text-xs text-muted-foreground">
                        <tr>
                            <th className="px-4 py-3">Week</th>
                            <th className="px-3 py-3 text-right">Due plans</th>
                            <th className="px-3 py-3 text-right">
                                Forecast demand
                            </th>
                            <th className="px-3 py-3 text-right">
                                Remaining capacity
                            </th>
                            <th className="px-4 py-3 text-right">Risk</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y">
                        {capacity.weeks.map((week) => (
                            <tr key={week.week_start}>
                                <td className="px-4 py-3 font-medium">
                                    {date(week.week_start)}
                                </td>
                                <td className="px-3 py-3 text-right">
                                    {week.due_plans} · {week.unassigned_plans}{' '}
                                    unassigned
                                </td>
                                <td className="px-3 py-3 text-right">
                                    {minutes(week.uncommitted_service_minutes)}
                                </td>
                                <td className="px-3 py-3 text-right">
                                    {minutes(week.projected_remaining_minutes)}
                                </td>
                                <td className="px-4 py-3 text-right">
                                    {week.projected_overload_minutes > 0 ? (
                                        <Badge variant="destructive">
                                            {minutes(
                                                week.projected_overload_minutes,
                                            )}{' '}
                                            overload
                                        </Badge>
                                    ) : week.critical_unassigned > 0 ? (
                                        <Badge variant="outline">
                                            {week.critical_unassigned} critical
                                            unassigned
                                        </Badge>
                                    ) : (
                                        <Badge variant="secondary">
                                            Capacity available
                                        </Badge>
                                    )}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </details>
    );
}

function ServiceCommitmentDialog({
    plan,
    canManageBilling,
    canManageCustomers,
}: {
    plan: Plan;
    canManageBilling: boolean;
    canManageCustomers: boolean;
}) {
    const currentCommitment = plan.cycle_commitments.find(
        (commitment) =>
            commitment.status === 'approved' &&
            commitment.maintenance_plan_version === plan.version &&
            commitment.period_starts_on.slice(0, 10) ===
                plan.next_due_on.slice(0, 10),
    );
    const completedCommitment =
        plan.cycle_commitments.reduce<ServiceCycleCommitment | null>(
            (latest, commitment) =>
                commitment.status === 'completed' &&
                (!latest || commitment.id > latest.id)
                    ? commitment
                    : latest,
            null,
        );

    if (currentCommitment) {
        return (
            <div className="flex flex-wrap items-center gap-1">
                <Badge variant="secondary">
                    Capacity approved · v
                    {currentCommitment.maintenance_plan_version}
                </Badge>
                {currentCommitment.work_order ? (
                    <Button size="sm" variant="outline" asChild>
                        <Link
                            href={`/work-orders?work_order=${currentCommitment.work_order.id}`}
                        >
                            {currentCommitment.work_order.number}
                        </Link>
                    </Button>
                ) : (
                    <MaterializeServiceCycleDialog
                        commitment={currentCommitment}
                    />
                )}
                {currentCommitment.work_order &&
                currentCommitment.schedule_status !== 'confirmed' ? (
                    <Form
                        action={`/after-sales/commitments/${currentCommitment.id}/schedule/confirm`}
                        method="post"
                    >
                        <Button size="sm">
                            <CalendarClock /> Confirm schedule
                        </Button>
                    </Form>
                ) : currentCommitment.schedule_status === 'confirmed' ? (
                    <Badge variant="outline">Schedule confirmed</Badge>
                ) : null}
                {canManageBilling && currentCommitment.billing?.invoice ? (
                    <Button size="sm" variant="outline" asChild>
                        <Link
                            href={`/sales/${currentCommitment.billing.invoice.id}`}
                        >
                            {currentCommitment.billing.invoice.number}
                        </Link>
                    </Button>
                ) : canManageBilling ? (
                    <RecurringBillingDialog commitment={currentCommitment} />
                ) : null}
                {canManageCustomers ? (
                    <ServiceCommunicationDialog
                        commitment={currentCommitment}
                        plan={plan}
                    />
                ) : null}
            </div>
        );
    }

    return (
        <div className="flex flex-wrap items-center gap-1">
            {completedCommitment?.completion_summary ? (
                <Badge variant="outline">
                    Last service ·{' '}
                    {dateTime(
                        completedCommitment.completion_summary.completed_at,
                    )}{' '}
                    · {completedCommitment.completion_summary.minutes} min ·{' '}
                    {completedCommitment.completion_summary.assets_serviced}{' '}
                    assets
                </Badge>
            ) : null}
            {completedCommitment && canManageBilling ? (
                completedCommitment.billing?.invoice ? (
                    <Button size="sm" variant="outline" asChild>
                        <Link
                            href={`/sales/${completedCommitment.billing.invoice.id}`}
                        >
                            {completedCommitment.billing.invoice.number}
                        </Link>
                    </Button>
                ) : (
                    <RecurringBillingDialog commitment={completedCommitment} />
                )
            ) : null}
            {completedCommitment && canManageCustomers ? (
                <ServiceCommunicationDialog
                    commitment={completedCommitment}
                    plan={plan}
                />
            ) : null}
            <Dialog>
                <DialogTrigger asChild>
                    <Button size="sm" variant="outline">
                        <ShieldCheck /> Approve cycle
                    </Button>
                </DialogTrigger>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Approve service cycle</DialogTitle>
                    </DialogHeader>
                    <p className="text-sm text-muted-foreground">
                        This freezes capacity and planned economics for{' '}
                        {plan.name} v{plan.version}. Approval does not create a
                        work order, reserve stock or communicate a date to the
                        customer.
                    </p>
                    <Form
                        action={`/after-sales/plans/${plan.id}/commitments`}
                        method="post"
                        className="grid gap-4"
                    >
                        <input
                            type="hidden"
                            name="plan_version"
                            value={plan.version}
                        />
                        <Field
                            label="Planned travel (km)"
                            name="planned_travel_km"
                            type="number"
                            step="0.01"
                            value="0"
                        />
                        <div className="grid gap-2">
                            <Label>Approval note</Label>
                            <textarea
                                name="approval_note"
                                required
                                minLength={5}
                                className="min-h-24 rounded-md border bg-transparent p-3 text-sm"
                                placeholder="Capacity, responsible technician and assumptions reviewed."
                            />
                        </div>
                        <Button>Approve capacity and economics</Button>
                    </Form>
                </DialogContent>
            </Dialog>
        </div>
    );
}

function MaterializeServiceCycleDialog({
    commitment,
}: {
    commitment: ServiceCycleCommitment;
}) {
    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button size="sm" variant="outline">
                    <Wrench /> Prepare service
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Prepare recurring service work</DialogTitle>
                </DialogHeader>
                <p className="text-sm text-muted-foreground">
                    Creates one ticket and one work order from the frozen plan
                    version. The date remains internal until it is confirmed.
                </p>
                <Form
                    action={`/after-sales/commitments/${commitment.id}/materialize`}
                    method="post"
                    className="grid gap-4"
                >
                    <Field
                        label="Planned start"
                        name="scheduled_start"
                        type="datetime-local"
                        value={`${commitment.period_starts_on.slice(0, 10)}T09:00`}
                    />
                    <div className="grid gap-2">
                        <Label>Planning note</Label>
                        <textarea
                            name="planning_note"
                            required
                            minLength={5}
                            className="min-h-24 rounded-md border bg-transparent p-3 text-sm"
                            defaultValue="Frozen technical version, technician capacity and visit window reviewed."
                        />
                    </div>
                    <Button>Prepare ticket and work order</Button>
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function RecurringBillingDialog({
    commitment,
}: {
    commitment: ServiceCycleCommitment;
}) {
    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button size="sm" variant="outline">
                    <CircleDollarSign /> Prepare invoice
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Prepare recurring invoice</DialogTitle>
                </DialogHeader>
                <p className="text-sm text-muted-foreground">
                    Creates one draft for this exact contract version and cycle.
                    A finance user must still review and issue it.
                </p>
                <Form
                    action={`/after-sales/commitments/${commitment.id}/billing`}
                    method="post"
                    className="grid gap-4"
                >
                    <Field
                        label="VAT (%)"
                        name="tax_percent"
                        type="number"
                        step="0.01"
                        value="21"
                    />
                    <Field
                        label="Payment term (days)"
                        name="due_days"
                        type="number"
                        value="30"
                    />
                    <div className="grid gap-2">
                        <Label>Generation note</Label>
                        <textarea
                            name="generation_note"
                            required
                            minLength={5}
                            className="min-h-24 rounded-md border bg-transparent p-3 text-sm"
                            defaultValue="Approved recurring cycle reviewed before invoice preparation."
                        />
                    </div>
                    <Button>Prepare draft for review</Button>
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function ServiceCommunicationDialog({
    commitment,
    plan,
}: {
    commitment: ServiceCycleCommitment;
    plan: Plan;
}) {
    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button size="sm" variant="outline">
                    <CalendarClock /> Customer notice
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Governed customer notice</DialogTitle>
                </DialogHeader>
                <p className="text-sm text-muted-foreground">
                    The channel is taken from the customer preference. Drafts
                    remain internal until an authorised user confirms them.
                </p>
                <Form
                    action={`/after-sales/commitments/${commitment.id}/communications`}
                    method="post"
                    className="grid gap-4"
                >
                    <div className="grid gap-2">
                        <Label>Notice type</Label>
                        <select
                            name="type"
                            className="h-9 rounded-md border bg-background px-3 text-sm"
                            defaultValue="maintenance_notice"
                        >
                            <option value="maintenance_notice">
                                Maintenance visit
                            </option>
                            <option value="billing_notice">
                                Billing notice
                            </option>
                        </select>
                    </div>
                    <Field
                        label="Confirmed date and time"
                        name="scheduled_for"
                        type="datetime-local"
                        value={`${commitment.period_starts_on.slice(0, 10)}T09:00`}
                    />
                    <Field
                        label="Subject"
                        name="subject"
                        value={`Upcoming service · ${plan.name}`}
                    />
                    <div className="grid gap-2">
                        <Label>Message</Label>
                        <textarea
                            name="message"
                            required
                            minLength={5}
                            className="min-h-24 rounded-md border bg-transparent p-3 text-sm"
                            defaultValue={`We have prepared the next service cycle for ${plan.name}. The date shown will only become visible after confirmation.`}
                        />
                    </div>
                    <Button>Prepare internal draft</Button>
                </Form>
                {commitment.communications.length > 0 ? (
                    <div className="grid gap-2 border-t pt-4">
                        {commitment.communications.map((communication) => (
                            <div
                                key={communication.id}
                                className="flex items-center justify-between gap-3 text-sm"
                            >
                                <span>
                                    {communication.subject} ·{' '}
                                    {communication.channel} ·{' '}
                                    {dateTime(communication.scheduled_for)}
                                </span>
                                {communication.status === 'draft' ? (
                                    <Form
                                        action={`/after-sales/commitments/${commitment.id}/communications/${communication.id}/confirm`}
                                        method="post"
                                    >
                                        <Button size="sm">Confirm</Button>
                                    </Form>
                                ) : communication.status === 'confirmed' ? (
                                    <Form
                                        action={`/after-sales/commitments/${commitment.id}/communications/${communication.id}/sent`}
                                        method="post"
                                        className="flex items-center gap-2"
                                    >
                                        <Input
                                            name="delivery_note"
                                            required
                                            minLength={5}
                                            aria-label="Delivery note"
                                            placeholder="Delivery evidence"
                                            className="h-8 w-40"
                                        />
                                        <Button size="sm">Mark sent</Button>
                                    </Form>
                                ) : (
                                    <Badge variant="secondary">Sent</Badge>
                                )}
                            </div>
                        ))}
                    </div>
                ) : null}
            </DialogContent>
        </Dialog>
    );
}

function ServiceEconomicsPanel({ economics }: { economics: ServiceEconomics }) {
    if (!economics.summary) {
        return null;
    }

    return (
        <details className="rounded-lg border">
            <summary className="flex cursor-pointer flex-wrap items-center justify-between gap-3 p-4">
                <span>
                    <span className="font-medium">
                        Recurring service cycle economics
                    </span>
                    <span className="mt-1 block text-xs text-muted-foreground">
                        {economics.definition}
                    </span>
                </span>
                <span className="flex gap-2">
                    <Badge variant="secondary">
                        {economics.summary.cycles} approved cycles
                    </Badge>
                    <Badge
                        variant={
                            economics.summary.actual_margin < 0
                                ? 'destructive'
                                : 'outline'
                        }
                    >
                        Actual margin{' '}
                        {currency(economics.summary.actual_margin)}
                    </Badge>
                </span>
            </summary>
            <div className="grid grid-cols-2 gap-px border-t bg-border lg:grid-cols-5">
                <Metric
                    label="Planned revenue"
                    value={currency(economics.summary.planned_revenue)}
                />
                <Metric
                    label="Planned margin"
                    value={currency(economics.summary.planned_margin)}
                />
                <Metric
                    label="Settled revenue"
                    value={currency(economics.summary.settled_revenue)}
                />
                <Metric
                    label="Actual cost"
                    value={currency(economics.summary.actual_cost)}
                />
                <Metric
                    label="Unsettled cycles"
                    value={economics.summary.unsettled_cycles}
                />
            </div>
            {economics.cycles.length > 0 ? (
                <div className="overflow-x-auto border-t">
                    <table className="w-full min-w-[56rem] text-sm">
                        <thead className="text-left text-xs text-muted-foreground">
                            <tr>
                                <th className="px-4 py-3">Contract cycle</th>
                                <th className="px-3 py-3 text-right">
                                    Planned
                                </th>
                                <th className="px-3 py-3 text-right">Actual</th>
                                <th className="px-3 py-3 text-right">Margin</th>
                                <th className="px-4 py-3">Approval</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {economics.cycles.map((cycle) => (
                                <tr key={cycle.id}>
                                    <td className="px-4 py-3">
                                        <div className="font-medium">
                                            {cycle.plan} · v{cycle.plan_version}
                                        </div>
                                        <div className="text-xs text-muted-foreground">
                                            {cycle.installation ||
                                                'Installation'}{' '}
                                            · {date(cycle.period_starts_on)}–
                                            {date(cycle.period_ends_on)}
                                        </div>
                                    </td>
                                    <td className="px-3 py-3 text-right">
                                        {currency(cycle.planned_revenue)}{' '}
                                        revenue
                                        <div className="text-xs text-muted-foreground">
                                            {currency(cycle.planned_cost)} cost
                                        </div>
                                    </td>
                                    <td className="px-3 py-3 text-right">
                                        {currency(cycle.settled_revenue)}{' '}
                                        settled
                                        <div className="text-xs text-muted-foreground">
                                            {currency(cycle.actual_cost)} cost ·{' '}
                                            {cycle.tickets} tickets
                                        </div>
                                    </td>
                                    <td
                                        className={`px-3 py-3 text-right font-medium ${cycle.actual_margin < 0 ? 'text-destructive' : ''}`}
                                    >
                                        {currency(cycle.actual_margin)}
                                        <div className="text-xs font-normal text-muted-foreground">
                                            planned{' '}
                                            {currency(cycle.planned_margin)}
                                        </div>
                                    </td>
                                    <td className="px-4 py-3 text-xs">
                                        {cycle.approved_by || 'System'}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            ) : null}
        </details>
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
function AssetDetail({ label, value }: { label: string; value: string }) {
    return (
        <div>
            <dt className="text-xs text-muted-foreground">{label}</dt>
            <dd>{value}</dd>
        </div>
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
    step,
    value,
}: {
    label: string;
    name: string;
    type?: string;
    step?: string;
    value?: string;
}) {
    return (
        <div className="grid gap-2">
            <Label className="text-xs">{label}</Label>
            <Input name={name} type={type} step={step} defaultValue={value} />
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
const minutes = (value: number) => `${Math.round((value / 60) * 10) / 10} h`;
AfterSalesIndex.layout = {
    breadcrumbs: [{ title: 'After-sales', href: '/after-sales' }],
};

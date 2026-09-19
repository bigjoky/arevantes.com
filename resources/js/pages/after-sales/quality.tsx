import { Form, Head, Link } from '@inertiajs/react';
import {
    CalendarClock,
    CheckCircle2,
    ChevronRight,
    History,
    RefreshCw,
    ShieldCheck,
    UserRoundCheck,
} from 'lucide-react';
import { useState } from 'react';
import { CorrectivePlanBoard } from '@/components/after-sales/corrective-plan-board';
import type { CorrectivePlan } from '@/components/after-sales/corrective-plan-board';
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
import { Textarea } from '@/components/ui/textarea';
import { useLocale } from '@/hooks/use-locale';
import { translate } from '@/i18n';

type User = { id: number; name: string };
type Event = {
    id: number;
    event: string;
    from_status: string | null;
    to_status: string | null;
    note: string | null;
    created_at: string;
    actor: User | null;
};
type Deviation = {
    id: number;
    reference: string;
    business_line: string;
    category: string;
    severity: 'medium' | 'high' | 'critical';
    status: 'open' | 'confirmed' | 'dismissed' | 'resolved';
    planned_value: string | null;
    actual_value: string | null;
    variance_percent: string | null;
    tolerance_percent: string | null;
    unit: string | null;
    summary: string;
    detection_context: Record<string, unknown> | null;
    review_due_on: string;
    detected_at: string;
    resolution_note: string | null;
    assignee: User | null;
    assets: { id: number; asset_tag: string; name: string }[];
    events: Event[];
    corrective_plans: { id: number; code: string; status: string }[];
    cycle: {
        id: number;
        maintenance_plan_id: number;
        period_starts_on: string;
        period_ends_on: string;
        completed_at: string | null;
        plan: string;
        installation: {
            id: number;
            project_reference: string | null;
            site_name: string | null;
            service_type: string;
        };
        ticket: { id: number; number: string; reopened_count: number } | null;
        work_order: { id: number; number: string } | null;
    };
};
type Props = {
    deviations: Deviation[];
    correctivePlans: CorrectivePlan[];
    filters: {
        status?: string;
        category?: string;
        business_line?: string;
        assigned_to?: number;
    };
    summary: {
        open: number;
        confirmed: number;
        overdue: number;
        affected_assets: number;
        quality_score: number | null;
        completed_cycles: number;
        active_corrective_plans: number;
        overdue_corrective_actions: number;
        pending_improvement_decisions: number;
        conflicting_improvement_proposals: number;
    };
    businessLines: string[];
    users: User[];
    canViewFinancials: boolean;
};

const categories = [
    'duration',
    'travel',
    'materials',
    'recurrence',
    'evidence',
    'documents',
];
const statuses = ['open', 'confirmed', 'dismissed', 'resolved'];

export default function RecurringServiceQuality({
    deviations,
    correctivePlans,
    filters,
    summary,
    businessLines,
    users,
    canViewFinancials,
}: Props) {
    const { locale } = useLocale();

    return (
        <>
            <Head title={translate('Recurring service quality', locale)} />
            <div className="flex min-w-0 flex-1 flex-col gap-6 p-4 md:p-6">
                <header className="flex flex-wrap items-start justify-between gap-4 rounded-xl border bg-card p-5 md:p-6">
                    <div>
                        <h1 className="text-xl font-semibold">
                            Recurring service quality
                        </h1>
                        <p className="max-w-3xl text-sm text-muted-foreground">
                            Compare approved scope with actual execution and
                            govern every exception before it influences assets,
                            replenishment, profitability or renewals.
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button asChild variant="outline">
                            <Link href="/improvement-decisions">
                                Review improvement decisions
                            </Link>
                        </Button>
                        <Form
                            action="/after-sales/quality/analyse"
                            method="post"
                        >
                            <Button variant="outline">
                                <RefreshCw /> Analyse completed cycles
                            </Button>
                        </Form>
                    </div>
                </header>

                <section className="grid grid-cols-2 border-y md:grid-cols-4 xl:grid-cols-10">
                    <Metric
                        label="Quality score"
                        value={
                            summary.quality_score === null
                                ? '—'
                                : `${summary.quality_score}%`
                        }
                    />
                    <Metric
                        label="Completed cycles"
                        value={summary.completed_cycles}
                    />
                    <Metric
                        label="Open reviews"
                        value={summary.open}
                        attention={summary.open > 0}
                    />
                    <Metric
                        label="Confirmed deviations"
                        value={summary.confirmed}
                        attention={summary.confirmed > 0}
                    />
                    <Metric
                        label="Overdue reviews"
                        value={summary.overdue}
                        attention={summary.overdue > 0}
                    />
                    <Metric
                        label="Affected assets"
                        value={summary.affected_assets}
                    />
                    <Metric
                        label="Active corrective plans"
                        value={summary.active_corrective_plans}
                        attention={summary.active_corrective_plans > 0}
                    />
                    <Metric
                        label="Overdue corrective actions"
                        value={summary.overdue_corrective_actions}
                        attention={summary.overdue_corrective_actions > 0}
                    />
                    <Metric
                        label="Pending improvement decisions"
                        value={summary.pending_improvement_decisions}
                        attention={summary.pending_improvement_decisions > 0}
                    />
                    <Metric
                        label="Conflicting proposals"
                        value={summary.conflicting_improvement_proposals}
                        attention={
                            summary.conflicting_improvement_proposals > 0
                        }
                    />
                </section>

                <section className="rounded-lg border p-4">
                    <Form
                        action="/after-sales/quality"
                        method="get"
                        className="grid gap-3 md:grid-cols-5"
                    >
                        <FilterSelect
                            label="Status"
                            name="status"
                            value={filters.status}
                            options={statuses}
                        />
                        <FilterSelect
                            label="Deviation type"
                            name="category"
                            value={filters.category}
                            options={categories}
                        />
                        <FilterSelect
                            label="Business line"
                            name="business_line"
                            value={filters.business_line}
                            options={businessLines}
                        />
                        <div className="grid gap-2">
                            <Label htmlFor="quality-assignee">
                                Responsible
                            </Label>
                            <select
                                id="quality-assignee"
                                name="assigned_to"
                                defaultValue={filters.assigned_to ?? ''}
                                className="h-9 rounded-md border bg-background px-3 text-sm"
                            >
                                <option value="">All</option>
                                {users.map((user) => (
                                    <option key={user.id} value={user.id}>
                                        {user.name}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div className="flex items-end gap-2">
                            <Button>Apply filters</Button>
                            <Button asChild variant="ghost">
                                <Link href="/quality/service">Clear</Link>
                            </Button>
                        </div>
                    </Form>
                </section>

                <CorrectivePlanBoard
                    plans={correctivePlans}
                    users={users}
                    deviations={deviations.map((deviation) => ({
                        id: deviation.id,
                        reference: deviation.reference,
                        summary: deviation.summary,
                        category: deviation.category,
                        severity: deviation.severity,
                        status: deviation.status,
                        maintenancePlanId: deviation.cycle.maintenance_plan_id,
                    }))}
                />

                <section className="grid gap-4">
                    {deviations.length === 0 ? (
                        <div className="rounded-lg border border-dashed py-16 text-center">
                            <CheckCircle2 className="mx-auto mb-3 size-8 text-muted-foreground" />
                            <p className="font-medium">
                                No deviations match these filters.
                            </p>
                            <p className="text-sm text-muted-foreground">
                                Analyse completed cycles to reconcile any
                                historical work.
                            </p>
                        </div>
                    ) : (
                        deviations.map((deviation) => (
                            <DeviationCard
                                key={deviation.id}
                                deviation={deviation}
                                users={users}
                                canViewFinancials={canViewFinancials}
                            />
                        ))
                    )}
                </section>

                <aside className="rounded-lg border bg-muted/30 p-4 text-sm text-muted-foreground">
                    <div className="mb-1 flex items-center gap-2 font-medium text-foreground">
                        <ShieldCheck className="size-4" />
                        Governed impact
                    </div>
                    Confirmed deviations inform asset health, replenishment
                    review, contractual profitability and renewal preparation.
                    They never modify a plan, create a purchase, contact a
                    customer or issue a proposal without a separate human
                    decision.
                </aside>
            </div>
        </>
    );
}

function DeviationCard({
    deviation,
    users,
    canViewFinancials,
}: {
    deviation: Deviation;
    users: User[];
    canViewFinancials: boolean;
}) {
    const overdue =
        ['open', 'confirmed'].includes(deviation.status) &&
        deviation.review_due_on < new Date().toISOString().slice(0, 10);

    return (
        <article className="rounded-lg border">
            <div className="grid gap-4 p-4 lg:grid-cols-[1fr_auto]">
                <div className="grid gap-3">
                    <div className="flex flex-wrap items-center gap-2">
                        <span className="font-mono text-xs text-muted-foreground">
                            {deviation.reference}
                        </span>
                        <Badge
                            variant={
                                deviation.severity === 'critical'
                                    ? 'destructive'
                                    : deviation.severity === 'high'
                                      ? 'secondary'
                                      : 'outline'
                            }
                        >
                            {humanize(deviation.severity)}
                        </Badge>
                        <Badge
                            variant={
                                deviation.status === 'open'
                                    ? 'secondary'
                                    : 'outline'
                            }
                        >
                            {humanize(deviation.status)}
                        </Badge>
                        <Badge variant="outline">
                            {humanize(deviation.category)}
                        </Badge>
                        <Badge variant="outline">
                            {humanize(deviation.business_line)}
                        </Badge>
                    </div>
                    <div>
                        <h2 className="font-medium">{deviation.summary}</h2>
                        <p className="text-sm text-muted-foreground">
                            {deviation.cycle.plan} ·{' '}
                            {deviation.cycle.installation.project_reference ||
                                deviation.cycle.installation.site_name ||
                                `#${deviation.cycle.installation.id}`}
                        </p>
                    </div>
                    <div className="grid gap-2 text-sm sm:grid-cols-2 xl:grid-cols-4">
                        <Datum
                            label="Planned"
                            value={measurement(
                                deviation.planned_value,
                                deviation.unit,
                                deviation.category,
                                canViewFinancials,
                            )}
                        />
                        <Datum
                            label="Actual"
                            value={measurement(
                                deviation.actual_value,
                                deviation.unit,
                                deviation.category,
                                canViewFinancials,
                            )}
                        />
                        <Datum
                            label="Variance"
                            value={
                                deviation.variance_percent === null
                                    ? '—'
                                    : `${deviation.variance_percent}%`
                            }
                        />
                        <Datum
                            label="Tolerance"
                            value={
                                deviation.tolerance_percent === null
                                    ? '—'
                                    : `${deviation.tolerance_percent}%`
                            }
                        />
                    </div>
                    <div className="flex flex-wrap gap-x-5 gap-y-1 text-xs text-muted-foreground">
                        <span
                            className={
                                overdue ? 'font-medium text-destructive' : ''
                            }
                        >
                            <CalendarClock className="mr-1 inline size-3" />
                            Review by {date(deviation.review_due_on)}
                        </span>
                        <span>
                            <UserRoundCheck className="mr-1 inline size-3" />
                            {deviation.assignee?.name || 'Unassigned'}
                        </span>
                        {deviation.cycle.work_order ? (
                            <Link
                                className="hover:underline"
                                href={`/work-orders?order=${deviation.cycle.work_order.id}`}
                            >
                                Work order {deviation.cycle.work_order.number}
                            </Link>
                        ) : null}
                        {deviation.assets.length > 0 ? (
                            <span>
                                {deviation.assets.length} affected assets
                            </span>
                        ) : null}
                    </div>
                </div>
                <div className="flex items-start gap-2">
                    <HistoryDialog deviation={deviation} />
                    <ReviewDialog deviation={deviation} users={users} />
                </div>
            </div>
            {deviation.resolution_note ? (
                <div className="border-t px-4 py-3 text-sm">
                    <span className="font-medium">Review note:</span>{' '}
                    {deviation.resolution_note}
                </div>
            ) : null}
        </article>
    );
}

function ReviewDialog({
    deviation,
    users,
}: {
    deviation: Deviation;
    users: User[];
}) {
    const [open, setOpen] = useState(false);
    const actions =
        deviation.status === 'open'
            ? ['assign', 'confirm', 'dismiss']
            : deviation.status === 'confirmed'
              ? ['assign', 'resolve']
              : ['reopen'];

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm">
                    Review <ChevronRight />
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Review {deviation.reference}</DialogTitle>
                </DialogHeader>
                <Form
                    action={`/after-sales/quality/deviations/${deviation.id}`}
                    method="patch"
                    onSuccess={() => setOpen(false)}
                    className="grid gap-4"
                >
                    <div className="grid gap-2">
                        <Label htmlFor={`action-${deviation.id}`}>
                            Decision
                        </Label>
                        <select
                            id={`action-${deviation.id}`}
                            name="action"
                            defaultValue={actions[0]}
                            className="h-9 rounded-md border bg-background px-3 text-sm"
                        >
                            {actions.map((action) => (
                                <option key={action} value={action}>
                                    {humanize(action)}
                                </option>
                            ))}
                        </select>
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor={`assigned-${deviation.id}`}>
                            Responsible
                        </Label>
                        <select
                            id={`assigned-${deviation.id}`}
                            name="assigned_to"
                            defaultValue={deviation.assignee?.id ?? ''}
                            className="h-9 rounded-md border bg-background px-3 text-sm"
                        >
                            <option value="">Keep current</option>
                            {users.map((user) => (
                                <option key={user.id} value={user.id}>
                                    {user.name}
                                </option>
                            ))}
                        </select>
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor={`due-${deviation.id}`}>
                            Review due date
                        </Label>
                        <Input
                            id={`due-${deviation.id}`}
                            name="review_due_on"
                            type="date"
                            min={new Date().toISOString().slice(0, 10)}
                            defaultValue={deviation.review_due_on}
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor={`note-${deviation.id}`}>
                            Decision note
                        </Label>
                        <Textarea
                            id={`note-${deviation.id}`}
                            name="note"
                            placeholder="Explain the evidence reviewed and the decision taken."
                        />
                    </div>
                    <Button>Record review</Button>
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function HistoryDialog({ deviation }: { deviation: Deviation }) {
    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button size="sm" variant="outline">
                    <History />
                    History
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{deviation.reference} history</DialogTitle>
                </DialogHeader>
                <div className="grid max-h-[60vh] gap-3 overflow-y-auto">
                    {deviation.events.map((event) => (
                        <div key={event.id} className="border-l-2 pl-3 text-sm">
                            <div className="font-medium">
                                {humanize(event.event)}
                                {event.from_status && event.to_status
                                    ? ` · ${humanize(event.from_status)} → ${humanize(event.to_status)}`
                                    : ''}
                            </div>
                            <div className="text-xs text-muted-foreground">
                                {dateTime(event.created_at)} ·{' '}
                                {event.actor?.name || 'System'}
                            </div>
                            {event.note ? (
                                <p className="mt-1">{event.note}</p>
                            ) : null}
                        </div>
                    ))}
                </div>
            </DialogContent>
        </Dialog>
    );
}

function FilterSelect({
    label,
    name,
    value,
    options,
}: {
    label: string;
    name: string;
    value?: string;
    options: string[];
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={`filter-${name}`}>{label}</Label>
            <select
                id={`filter-${name}`}
                name={name}
                defaultValue={value ?? ''}
                className="h-9 rounded-md border bg-background px-3 text-sm"
            >
                <option value="">All</option>
                {options.map((option) => (
                    <option key={option} value={option}>
                        {humanize(option)}
                    </option>
                ))}
            </select>
        </div>
    );
}

function Metric({
    label,
    value,
    attention = false,
}: {
    label: string;
    value: string | number;
    attention?: boolean;
}) {
    return (
        <div className="border-x px-4 py-3">
            <div className="text-xs text-muted-foreground">{label}</div>
            <div
                className={
                    attention
                        ? 'text-xl font-semibold text-destructive'
                        : 'text-xl font-semibold'
                }
            >
                {value}
            </div>
        </div>
    );
}
function Datum({ label, value }: { label: string; value: string }) {
    return (
        <div>
            <div className="text-xs text-muted-foreground">{label}</div>
            <div className="font-medium">{value}</div>
        </div>
    );
}
const humanize = (value: string) =>
    value.replaceAll('_', ' ').replace(/^./, (letter) => letter.toUpperCase());
const date = (value: string) =>
    new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(
        new Date(`${value.slice(0, 10)}T12:00:00`),
    );
const dateTime = (value: string) =>
    new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
const measurement = (
    value: string | null,
    unit: string | null,
    category: string,
    canViewFinancials: boolean,
) => {
    if (category === 'materials' && !canViewFinancials) {
        return 'Restricted';
    }

    if (value === null) {
        return '—';
    }

    if (unit === 'EUR') {
        return new Intl.NumberFormat(undefined, {
            style: 'currency',
            currency: 'EUR',
        }).format(Number(value));
    }

    return `${Number(value).toLocaleString()}${unit ? ` ${unit}` : ''}`;
};

RecurringServiceQuality.layout = {
    breadcrumbs: [{ title: 'Service quality', href: '/quality/service' }],
};

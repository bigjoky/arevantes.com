import { Form } from '@inertiajs/react';
import { ClipboardCheck, History, Plus } from 'lucide-react';
import { useMemo, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

type User = { id: number; name: string };
export type CorrectiveDeviationOption = {
    id: number;
    reference: string;
    summary: string;
    category: string;
    severity: string;
    status: string;
    maintenancePlanId: number;
};
type Evaluation = {
    ready: boolean;
    required_cycles: number;
    observed_cycles: number;
    remaining_cycles: number;
    recommended_result: 'effective' | 'ineffective' | null;
    volume_change_percent: number | null;
    before: {
        deviation_rate: number;
        mean_excess_percent: number;
        cycles: number;
    };
    after: {
        deviation_rate: number;
        mean_excess_percent: number;
        cycles: number;
    };
};
export type ImprovementProposal = {
    id: number;
    type: string;
    title: string;
    rationale: string;
    payload: { proposed_change?: string } | null;
    status: string;
    created_at: string;
    creator: User | null;
    target_type: string | null;
    target_version: string | null;
    conflict: {
        has_conflict: boolean;
        reason: string | null;
        prepared_version: string | null;
        current_version: string;
    } | null;
    decision_note: string | null;
    decider: User | null;
    decided_at: string | null;
    resubmission_count: number;
    can_decide: boolean;
};
export type CorrectivePlan = {
    id: number;
    code: string;
    title: string;
    root_cause_category: string;
    root_cause_analysis: string;
    status: string;
    target_date: string;
    verification_cycles: number;
    owner: User | null;
    effectiveness: Evaluation | null;
    effectiveness_result: string | null;
    effectiveness_note: string | null;
    maintenance_plan: {
        id: number;
        name: string;
        installation: {
            id: number;
            project_reference: string | null;
            site_name: string | null;
        };
    };
    deviations: Array<CorrectiveDeviationOption>;
    actions: Array<{
        id: number;
        type: string;
        title: string;
        details: string | null;
        status: string;
        due_on: string;
        owner: User | null;
        completion_note: string | null;
        completed_at: string | null;
    }>;
    proposals: ImprovementProposal[];
    events: Array<{
        id: number;
        event: string;
        from_status: string | null;
        to_status: string | null;
        note: string | null;
        created_at: string;
        actor: User | null;
    }>;
};

export function CorrectivePlanBoard({
    plans,
    deviations,
    users,
}: {
    plans: CorrectivePlan[];
    deviations: CorrectiveDeviationOption[];
    users: User[];
}) {
    return (
        <section className="grid gap-4 border-y py-5">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 className="font-semibold">
                        Corrective action and learning
                    </h2>
                    <p className="max-w-3xl text-sm text-muted-foreground">
                        Group confirmed deviations by root cause, complete
                        corrective and preventive actions, then verify
                        improvement against comparable later cycles.
                    </p>
                </div>
                <CreatePlanDialog deviations={deviations} users={users} />
            </div>
            {plans.length === 0 ? (
                <div className="rounded-lg border border-dashed py-10 text-center text-sm text-muted-foreground">
                    No corrective action plans have been prepared.
                </div>
            ) : (
                <div className="grid gap-4">
                    {plans.map((plan) => (
                        <PlanCard key={plan.id} plan={plan} users={users} />
                    ))}
                </div>
            )}
        </section>
    );
}

function PlanCard({ plan, users }: { plan: CorrectivePlan; users: User[] }) {
    const overdue =
        ['draft', 'in_progress'].includes(plan.status) &&
        plan.target_date < today();

    return (
        <article className="rounded-lg border">
            <div className="grid gap-4 p-4 xl:grid-cols-[minmax(0,1fr)_auto]">
                <div className="grid gap-3">
                    <div className="flex flex-wrap items-center gap-2">
                        <span className="font-mono text-xs text-muted-foreground">
                            {plan.code}
                        </span>
                        <Badge
                            variant={
                                plan.status === 'ineffective'
                                    ? 'destructive'
                                    : plan.status === 'effective'
                                      ? 'default'
                                      : 'outline'
                            }
                        >
                            {humanize(plan.status)}
                        </Badge>
                        <Badge variant="outline">
                            {humanize(plan.root_cause_category)}
                        </Badge>
                    </div>
                    <div>
                        <h3 className="font-medium">{plan.title}</h3>
                        <p className="text-sm text-muted-foreground">
                            {plan.maintenance_plan.name} ·{' '}
                            {plan.maintenance_plan.installation
                                .project_reference ||
                                plan.maintenance_plan.installation.site_name ||
                                `#${plan.maintenance_plan.installation.id}`}
                        </p>
                    </div>
                    <p className="text-sm">
                        <span className="font-medium">Root cause:</span>{' '}
                        {plan.root_cause_analysis}
                    </p>
                    <div className="flex flex-wrap gap-x-5 gap-y-1 text-xs text-muted-foreground">
                        <span>{plan.owner?.name || 'Unassigned'}</span>
                        <span
                            className={
                                overdue ? 'font-medium text-destructive' : ''
                            }
                        >
                            Target {date(plan.target_date)}
                        </span>
                        <span>{plan.deviations.length} linked deviations</span>
                        <span>
                            {
                                plan.actions.filter(
                                    (action) => action.status === 'completed',
                                ).length
                            }
                            /
                            {
                                plan.actions.filter(
                                    (action) => action.status !== 'cancelled',
                                ).length
                            }{' '}
                            actions completed
                        </span>
                    </div>
                </div>
                <div className="flex flex-wrap items-start gap-2">
                    <PlanHistory plan={plan} />
                    {!['effective', 'ineffective', 'closed'].includes(
                        plan.status,
                    ) && <AddActionDialog plan={plan} users={users} />}
                    {!['effective', 'ineffective', 'closed'].includes(
                        plan.status,
                    ) && <ProposalDialog plan={plan} />}
                    <TransitionDialog plan={plan} />
                </div>
            </div>
            <div className="grid gap-5 border-t p-4 xl:grid-cols-2">
                <div>
                    <h4 className="mb-2 text-sm font-medium">Actions</h4>
                    <div className="grid gap-2">
                        {plan.actions.map((action) => (
                            <ActionRow
                                key={action.id}
                                action={action}
                                locked={
                                    !['draft', 'in_progress'].includes(
                                        plan.status,
                                    )
                                }
                            />
                        ))}
                    </div>
                </div>
                <div className="grid content-start gap-4">
                    <Effectiveness
                        evaluation={plan.effectiveness}
                        result={plan.effectiveness_result}
                    />
                    <div>
                        <h4 className="mb-2 text-sm font-medium">
                            Improvement proposals
                        </h4>
                        {plan.proposals.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No downstream changes prepared.
                            </p>
                        ) : (
                            <div className="grid gap-2">
                                {plan.proposals.map((proposal) => (
                                    <div
                                        key={proposal.id}
                                        className="rounded-md border p-3 text-sm"
                                    >
                                        <div className="flex justify-between gap-2">
                                            <span className="font-medium">
                                                {proposal.title}
                                            </span>
                                            <Badge variant="outline">
                                                {humanize(proposal.type)} ·{' '}
                                                {humanize(proposal.status)}
                                            </Badge>
                                        </div>
                                        <p className="mt-1 text-muted-foreground">
                                            {proposal.rationale}
                                        </p>
                                        {proposal.payload?.proposed_change ? (
                                            <p className="mt-2">
                                                {
                                                    proposal.payload
                                                        .proposed_change
                                                }
                                            </p>
                                        ) : null}
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </article>
    );
}

function ActionRow({
    action,
    locked,
}: {
    action: CorrectivePlan['actions'][number];
    locked: boolean;
}) {
    return (
        <div className="rounded-md border p-3 text-sm">
            <div className="flex flex-wrap justify-between gap-2">
                <div>
                    <span className="font-medium">{action.title}</span>
                    <span className="ml-2 text-xs text-muted-foreground">
                        {humanize(action.type)} ·{' '}
                        {action.owner?.name || 'Unassigned'} ·{' '}
                        {date(action.due_on)}
                    </span>
                </div>
                <Badge variant="outline">{humanize(action.status)}</Badge>
            </div>
            {action.details ? (
                <p className="mt-1 text-muted-foreground">{action.details}</p>
            ) : null}
            {action.completion_note ? (
                <p className="mt-2">
                    <span className="font-medium">Evidence:</span>{' '}
                    {action.completion_note}
                </p>
            ) : null}
            {!locked ? (
                <Form
                    action={`/after-sales/quality/corrective-actions/${action.id}`}
                    method="patch"
                    className="mt-3 grid gap-2 sm:grid-cols-[10rem_1fr_auto]"
                >
                    <select
                        name="status"
                        defaultValue={
                            action.status === 'completed'
                                ? 'in_progress'
                                : action.status === 'cancelled'
                                  ? 'open'
                                  : 'completed'
                        }
                        className="h-9 rounded-md border bg-background px-3 text-sm"
                    >
                        {action.status !== 'completed' && (
                            <option value="completed">Complete</option>
                        )}
                        {action.status !== 'in_progress' && (
                            <option value="in_progress">In progress</option>
                        )}
                        {action.status !== 'open' && (
                            <option value="open">Reopen</option>
                        )}
                        {!['completed', 'cancelled'].includes(
                            action.status,
                        ) && <option value="cancelled">Cancel</option>}
                    </select>
                    <Input
                        name="completion_note"
                        placeholder="Completion evidence (required when completing)"
                    />
                    <Button size="sm" variant="outline">
                        Update
                    </Button>
                </Form>
            ) : null}
        </div>
    );
}

function Effectiveness({
    evaluation,
    result,
}: {
    evaluation: Evaluation | null;
    result: string | null;
}) {
    if (!evaluation) {
        return (
            <div>
                <h4 className="text-sm font-medium">Effectiveness</h4>
                <p className="text-sm text-muted-foreground">
                    Verification has not started.
                </p>
            </div>
        );
    }

    return (
        <div className="rounded-md border p-3 text-sm">
            <div className="flex justify-between gap-2">
                <h4 className="font-medium">Effectiveness</h4>
                {result ? (
                    <Badge>{humanize(result)}</Badge>
                ) : (
                    <Badge variant="outline">
                        {evaluation.ready
                            ? `Suggested: ${humanize(evaluation.recommended_result || '')}`
                            : `${evaluation.remaining_cycles} cycles remaining`}
                    </Badge>
                )}
            </div>
            <div className="mt-3 grid grid-cols-2 gap-3">
                <Comparison
                    label="Before"
                    value={`${percent(evaluation.before.deviation_rate)} · ${evaluation.before.mean_excess_percent}% excess`}
                    detail={`${evaluation.before.cycles} cycles`}
                />
                <Comparison
                    label="After"
                    value={`${percent(evaluation.after.deviation_rate)} · ${evaluation.after.mean_excess_percent}% excess`}
                    detail={`${evaluation.after.cycles} cycles`}
                />
            </div>
            {evaluation.volume_change_percent !== null ? (
                <p className="mt-2 text-xs text-muted-foreground">
                    Planned workload per cycle changed{' '}
                    {evaluation.volume_change_percent}% and is shown separately
                    from quality improvement.
                </p>
            ) : null}
        </div>
    );
}

function CreatePlanDialog({
    deviations,
    users,
}: {
    deviations: CorrectiveDeviationOption[];
    users: User[];
}) {
    const eligible = deviations.filter((deviation) =>
        ['confirmed', 'resolved'].includes(deviation.status),
    );
    const [open, setOpen] = useState(false);
    const [primaryId, setPrimaryId] = useState(String(eligible[0]?.id ?? ''));
    const primary = eligible.find((item) => String(item.id) === primaryId);
    const related = useMemo(
        () =>
            eligible.filter(
                (item) => item.maintenancePlanId === primary?.maintenancePlanId,
            ),
        [eligible, primary?.maintenancePlanId],
    );

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button disabled={eligible.length === 0}>
                    <Plus /> Prepare corrective plan
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Prepare corrective action plan</DialogTitle>
                    <DialogDescription>
                        The baseline is frozen from confirmed deviations
                        belonging to one maintenance plan.
                    </DialogDescription>
                </DialogHeader>
                {primary ? (
                    <Form
                        key={primary.id}
                        action={`/after-sales/quality/deviations/${primary.id}/corrective-plans`}
                        method="post"
                        onSuccess={() => setOpen(false)}
                        className="grid gap-4"
                    >
                        <SelectField
                            label="Originating deviation"
                            name="primary_display"
                            value={primaryId}
                            onChange={setPrimaryId}
                            options={eligible.map((item) => ({
                                value: String(item.id),
                                label: `${item.reference} · ${item.summary}`,
                            }))}
                            omitName
                        />
                        <input
                            type="hidden"
                            name="deviation_ids[]"
                            value={primary.id}
                        />
                        <div className="grid gap-2">
                            <Label>Related confirmed deviations</Label>
                            <div className="grid gap-2 rounded-md border p-3">
                                {related
                                    .filter((item) => item.id !== primary.id)
                                    .map((item) => (
                                        <label
                                            key={item.id}
                                            className="flex items-start gap-2 text-sm"
                                        >
                                            <input
                                                type="checkbox"
                                                name="deviation_ids[]"
                                                value={item.id}
                                                className="mt-1"
                                            />
                                            <span>
                                                {item.reference} ·{' '}
                                                {humanize(item.category)} ·{' '}
                                                {item.summary}
                                            </span>
                                        </label>
                                    ))}
                            </div>
                        </div>
                        <InputField label="Plan title" name="title" />
                        <SelectField
                            label="Root-cause category"
                            name="root_cause_category"
                            options={[
                                'process',
                                'training',
                                'materials',
                                'template',
                                'scope',
                                'scheduling',
                                'external',
                                'unknown',
                            ].map(option)}
                        />
                        <TextField
                            label="Root-cause analysis"
                            name="root_cause_analysis"
                            placeholder="Explain why the deviation occurred, based on evidence rather than symptoms."
                        />
                        <div className="grid gap-4 sm:grid-cols-3">
                            <UserField
                                label="Plan owner"
                                name="owner_id"
                                users={users}
                            />
                            <InputField
                                label="Target date"
                                name="target_date"
                                type="date"
                                min={today()}
                            />
                            <InputField
                                label="Cycles to verify"
                                name="verification_cycles"
                                type="number"
                                min="1"
                                max="12"
                                defaultValue="2"
                            />
                        </div>
                        <div className="grid gap-4 rounded-md border p-3">
                            <h3 className="font-medium">First action</h3>
                            <SelectField
                                label="Action type"
                                name="action_type"
                                options={['corrective', 'preventive'].map(
                                    option,
                                )}
                            />
                            <InputField
                                label="Action title"
                                name="action_title"
                            />
                            <TextField
                                label="Action detail"
                                name="action_details"
                            />
                            <div className="grid gap-4 sm:grid-cols-2">
                                <UserField
                                    label="Action owner"
                                    name="action_owner_id"
                                    users={users}
                                />
                                <InputField
                                    label="Due date"
                                    name="action_due_on"
                                    type="date"
                                    min={today()}
                                />
                            </div>
                        </div>
                        <Button>
                            <ClipboardCheck /> Freeze baseline and prepare
                        </Button>
                    </Form>
                ) : (
                    <p className="text-sm text-muted-foreground">
                        Confirm at least one deviation before preparing a
                        corrective plan.
                    </p>
                )}
            </DialogContent>
        </Dialog>
    );
}

function AddActionDialog({
    plan,
    users,
}: {
    plan: CorrectivePlan;
    users: User[];
}) {
    return (
        <SimpleDialog trigger="Add action" title={`Add action to ${plan.code}`}>
            <Form
                action={`/after-sales/quality/corrective-plans/${plan.id}/actions`}
                method="post"
                className="grid gap-4"
            >
                <SelectField
                    label="Type"
                    name="type"
                    options={['corrective', 'preventive'].map(option)}
                />
                <InputField label="Title" name="title" />
                <TextField label="Details" name="details" />
                <UserField label="Owner" name="owner_id" users={users} />
                <InputField
                    label="Due date"
                    name="due_on"
                    type="date"
                    min={today()}
                />
                <Button>Add action</Button>
            </Form>
        </SimpleDialog>
    );
}

function ProposalDialog({ plan }: { plan: CorrectivePlan }) {
    return (
        <SimpleDialog
            trigger="Prepare change"
            title={`Prepare downstream change from ${plan.code}`}
            description="This records a proposal only. It does not alter templates, stock, contracts or opportunities."
        >
            <Form
                action={`/after-sales/quality/corrective-plans/${plan.id}/proposals`}
                method="post"
                className="grid gap-4"
            >
                <SelectField
                    label="Destination"
                    name="type"
                    options={[
                        'work_order_template',
                        'preventive_stock',
                        'maintenance_scope',
                        'renewal',
                    ].map(option)}
                />
                <InputField label="Title" name="title" />
                <TextField label="Rationale" name="rationale" />
                <TextField label="Proposed change" name="proposed_change" />
                <Button>Prepare without applying</Button>
            </Form>
        </SimpleDialog>
    );
}

function TransitionDialog({ plan }: { plan: CorrectivePlan }) {
    const actions =
        plan.status === 'draft'
            ? ['activate']
            : plan.status === 'in_progress'
              ? ['start_verification']
              : plan.status === 'verification'
                ? ['verify_effective', 'verify_ineffective']
                : ['effective', 'ineffective'].includes(plan.status)
                  ? ['close', 'reopen']
                  : ['reopen'];

    return (
        <SimpleDialog trigger="Decision" title={`Update ${plan.code}`}>
            <Form
                action={`/after-sales/quality/corrective-plans/${plan.id}`}
                method="patch"
                className="grid gap-4"
            >
                <SelectField
                    label="Decision"
                    name="action"
                    options={actions.map(option)}
                />
                <TextField
                    label="Decision note"
                    name="note"
                    placeholder="Explain the evidence and decision."
                />
                <Button>Record decision</Button>
            </Form>
        </SimpleDialog>
    );
}

function PlanHistory({ plan }: { plan: CorrectivePlan }) {
    return (
        <SimpleDialog
            trigger="History"
            title={`${plan.code} history`}
            icon={<History />}
        >
            <div className="grid max-h-[60vh] gap-3 overflow-y-auto">
                {plan.events.map((event) => (
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
        </SimpleDialog>
    );
}

function SimpleDialog({
    trigger,
    title,
    description,
    icon,
    children,
}: {
    trigger: string;
    title: string;
    description?: string;
    icon?: React.ReactNode;
    children: React.ReactNode;
}) {
    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button size="sm" variant="outline">
                    {icon}
                    {trigger}
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    {description ? (
                        <DialogDescription>{description}</DialogDescription>
                    ) : null}
                </DialogHeader>
                {children}
            </DialogContent>
        </Dialog>
    );
}

function InputField({
    label,
    name,
    type = 'text',
    ...props
}: {
    label: string;
    name: string;
    type?: string;
    [key: string]: unknown;
}) {
    return (
        <div className="grid gap-2">
            <Label>{label}</Label>
            <Input name={name} type={type} required {...props} />
        </div>
    );
}
function TextField({
    label,
    name,
    placeholder,
}: {
    label: string;
    name: string;
    placeholder?: string;
}) {
    return (
        <div className="grid gap-2">
            <Label>{label}</Label>
            <Textarea name={name} required placeholder={placeholder} />
        </div>
    );
}
function UserField({
    label,
    name,
    users,
}: {
    label: string;
    name: string;
    users: User[];
}) {
    return (
        <SelectField
            label={label}
            name={name}
            options={users.map((user) => ({
                value: String(user.id),
                label: user.name,
            }))}
        />
    );
}
function SelectField({
    label,
    name,
    options,
    value,
    onChange,
    omitName = false,
}: {
    label: string;
    name: string;
    options: Array<{ value: string; label: string }>;
    value?: string;
    onChange?: (value: string) => void;
    omitName?: boolean;
}) {
    return (
        <div className="grid gap-2">
            <Label>{label}</Label>
            <select
                name={omitName ? undefined : name}
                required
                value={value}
                defaultValue={
                    value === undefined ? options[0]?.value : undefined
                }
                onChange={
                    onChange
                        ? (event) => onChange(event.target.value)
                        : undefined
                }
                className="h-9 rounded-md border bg-background px-3 text-sm"
            >
                {options.map((item) => (
                    <option key={item.value} value={item.value}>
                        {item.label}
                    </option>
                ))}
            </select>
        </div>
    );
}
function Comparison({
    label,
    value,
    detail,
}: {
    label: string;
    value: string;
    detail: string;
}) {
    return (
        <div>
            <div className="text-xs text-muted-foreground">{label}</div>
            <div className="font-medium">{value}</div>
            <div className="text-xs text-muted-foreground">{detail}</div>
        </div>
    );
}
const option = (value: string) => ({ value, label: humanize(value) });
const humanize = (value: string) =>
    value.replaceAll('_', ' ').replace(/^./, (letter) => letter.toUpperCase());
const today = () => new Date().toISOString().slice(0, 10);
const date = (value: string) =>
    new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(
        new Date(`${value.slice(0, 10)}T12:00:00`),
    );
const dateTime = (value: string) =>
    new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
const percent = (value: number) => `${Math.round(value * 1000) / 10}%`;

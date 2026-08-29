import { Form, Link } from '@inertiajs/react';
import {
    Activity,
    AlertTriangle,
    ArrowRightLeft,
    CheckCircle2,
    RotateCcw,
    XCircle,
} from 'lucide-react';
import { useState } from 'react';
import type { ImprovementProposal } from '@/components/after-sales/corrective-plan-board';
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
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

export type ImprovementInboxProposal = ImprovementProposal & {
    plan: {
        id: number;
        code: string;
        title: string;
        maintenance_plan: {
            name: string;
            installation: {
                project_reference: string | null;
                site_name: string | null;
            } | null;
        };
    };
};

type SuccessorCandidate = {
    id: number;
    plan_id: number;
    type: string;
    title: string;
    applied_at: string;
};

export function ImprovementDecisionInbox({
    proposals,
    successorCandidates,
}: {
    proposals: ImprovementInboxProposal[];
    successorCandidates: SuccessorCandidate[];
}) {
    if (proposals.length === 0) {
        return (
            <div className="rounded-lg border border-dashed py-12 text-center text-sm text-muted-foreground">
                No improvement proposals match this view.
            </div>
        );
    }

    return (
        <section className="grid gap-3">
            {proposals.map((proposal) => (
                <ProposalCard
                    key={proposal.id}
                    proposal={proposal}
                    successorCandidates={successorCandidates.filter(
                        (candidate) =>
                            candidate.id !== proposal.id &&
                            candidate.plan_id === proposal.plan.id &&
                            candidate.type === proposal.type &&
                            (candidate.applied_at >
                                (proposal.applied_at || '') ||
                                (candidate.applied_at === proposal.applied_at &&
                                    candidate.id > proposal.id)),
                    )}
                />
            ))}
        </section>
    );
}

function ProposalCard({
    proposal,
    successorCandidates,
}: {
    proposal: ImprovementInboxProposal;
    successorCandidates: SuccessorCandidate[];
}) {
    const conflict = proposal.conflict?.has_conflict === true;
    const installation = proposal.plan.maintenance_plan.installation;

    return (
        <article
            id={`proposal-${proposal.id}`}
            className="grid scroll-mt-24 gap-3 rounded-lg border p-4 lg:grid-cols-[minmax(0,1fr)_auto]"
        >
            <div className="grid min-w-0 gap-2">
                <div className="flex flex-wrap items-center gap-2">
                    <span className="font-medium">{proposal.title}</span>
                    <Badge variant="outline">{humanize(proposal.type)}</Badge>
                    <StatusBadge status={proposal.status} />
                    {conflict ? (
                        <Badge variant="destructive">
                            <AlertTriangle /> Configuration changed
                        </Badge>
                    ) : null}
                </div>
                <p className="text-sm text-muted-foreground">
                    {proposal.plan.code} · {proposal.plan.title} ·{' '}
                    {proposal.plan.maintenance_plan.name}
                    {installation
                        ? ` · ${installation.project_reference || installation.site_name || 'Installation'}`
                        : ''}
                </p>
                <p className="text-sm">{proposal.rationale}</p>
                {proposal.payload?.proposed_change ? (
                    <div className="rounded-md bg-muted/50 p-3 text-sm">
                        <span className="font-medium">Proposed change: </span>
                        {proposal.payload.proposed_change}
                    </div>
                ) : null}
                {conflict ? (
                    <p className="text-sm text-destructive">
                        {proposal.status === 'approved'
                            ? 'The destination changed after approval.'
                            : `Prepared against ${proposal.conflict?.prepared_version || '—'}; the current version is ${proposal.conflict?.current_version || '—'}.`}{' '}
                        Return and resubmit the proposal before continuing.
                    </p>
                ) : null}
                {proposal.status === 'approved' &&
                proposal.application_preview &&
                !proposal.application_preview.can_apply &&
                !conflict ? (
                    <p className="text-sm text-destructive">
                        No eligible controlled destination is currently
                        available. Return the proposal and revise its scope
                        before applying it.
                    </p>
                ) : null}
                {proposal.decision_note ? (
                    <p className="text-sm text-muted-foreground">
                        Decision by {proposal.decider?.name || 'Unknown user'}:{' '}
                        {proposal.decision_note}
                    </p>
                ) : null}
                <p className="text-xs text-muted-foreground">
                    Responsible area: {ownerArea(proposal.type)} · Target:{' '}
                    {humanize(proposal.target_type || 'not assigned')} ·
                    Version: {proposal.target_version || '—'}
                    {proposal.resubmission_count > 0
                        ? ` · Resubmissions: ${proposal.resubmission_count}`
                        : ''}
                </p>
                {proposal.status === 'applied' ? (
                    <>
                        <div className="rounded-md border border-emerald-600/30 bg-emerald-500/5 p-3 text-sm">
                            <p className="font-medium">
                                Controlled application completed
                            </p>
                            <p className="text-muted-foreground">
                                Applied by{' '}
                                {proposal.applier?.name || 'Unknown user'}.
                                {proposal.application_note
                                    ? ` ${proposal.application_note}`
                                    : ''}
                            </p>
                            <ApplicationResult
                                result={proposal.application_result}
                            />
                        </div>
                        {proposal.monitoring ? (
                            <MonitoringSummary proposal={proposal} />
                        ) : null}
                    </>
                ) : null}
            </div>
            <div className="flex flex-wrap content-start gap-2 lg:justify-end">
                {proposal.status === 'prepared' && proposal.can_decide ? (
                    <>
                        <DecisionDialog
                            proposal={proposal}
                            action="approve"
                            disabled={conflict}
                        />
                        <DecisionDialog proposal={proposal} action="return" />
                        <DecisionDialog proposal={proposal} action="reject" />
                    </>
                ) : null}
                {proposal.status === 'returned' && proposal.can_decide ? (
                    <DecisionDialog proposal={proposal} action="resubmit" />
                ) : null}
                {proposal.status === 'approved' && proposal.can_decide ? (
                    proposal.application_preview?.can_apply ? (
                        <ApplicationDialog proposal={proposal} />
                    ) : (
                        <DecisionDialog proposal={proposal} action="return" />
                    )
                ) : null}
                {proposal.status === 'applied' &&
                proposal.can_decide &&
                !proposal.superseded_at &&
                successorCandidates.length > 0 ? (
                    <SupersedeDialog
                        proposal={proposal}
                        candidates={successorCandidates}
                    />
                ) : null}
            </div>
        </article>
    );
}

function MonitoringSummary({
    proposal,
}: {
    proposal: ImprovementInboxProposal;
}) {
    const monitoring = proposal.monitoring;

    if (!monitoring) {
        return null;
    }

    return (
        <div className="rounded-md border bg-muted/20 p-3 text-sm">
            <div className="flex flex-wrap items-center gap-2">
                <Activity className="size-4" />
                <span className="font-medium">Implementation monitoring</span>
                <MonitoringBadge state={monitoring.state} />
            </div>
            <p className="mt-2 text-muted-foreground">
                {monitoring.adoption_count} consuming record(s) ·{' '}
                {monitoring.completed_count} completed comparable cycle(s) of{' '}
                {monitoring.required_cycles} required.
            </p>
            {monitoring.observed_deviation_rate !== null ? (
                <p className="mt-1 text-xs text-muted-foreground">
                    Confirmed deviation rate: baseline{' '}
                    {(monitoring.baseline_deviation_rate * 100).toFixed(1)}% ·
                    observed{' '}
                    {(monitoring.observed_deviation_rate * 100).toFixed(1)}%.
                </p>
            ) : null}
            {proposal.superseded_at ? (
                <p className="mt-2 text-xs text-muted-foreground">
                    Monitoring transferred by{' '}
                    {proposal.superseder?.name || 'Unknown user'} to{' '}
                    {proposal.successor?.title || 'a governed successor'}.
                    {proposal.supersession_note
                        ? ` ${proposal.supersession_note}`
                        : ''}
                </p>
            ) : null}
            {monitoring.consumers.length > 0 ? (
                <div className="mt-3 flex flex-wrap gap-2">
                    {monitoring.consumers.map((consumer) => (
                        <Button
                            key={`${consumer.type}-${consumer.id}`}
                            asChild
                            size="sm"
                            variant="outline"
                        >
                            <Link href={consumer.href}>
                                {consumer.label} · {humanize(consumer.status)}
                            </Link>
                        </Button>
                    ))}
                </div>
            ) : null}
        </div>
    );
}

function MonitoringBadge({
    state,
}: {
    state: NonNullable<ImprovementProposal['monitoring']>['state'];
}) {
    if (state === 'effective') {
        return <Badge className="bg-emerald-700">Effective in use</Badge>;
    }

    if (state === 'ineffective') {
        return <Badge variant="destructive">Used without effectiveness</Badge>;
    }

    if (state === 'implementation_failed') {
        return <Badge variant="destructive">Implementation failed</Badge>;
    }

    if (state === 'superseded') {
        return <Badge variant="secondary">Superseded</Badge>;
    }

    if (state === 'not_used') {
        return <Badge variant="secondary">Not used yet</Badge>;
    }

    return <Badge variant="outline">Monitoring result</Badge>;
}

function SupersedeDialog({
    proposal,
    candidates,
}: {
    proposal: ImprovementInboxProposal;
    candidates: SuccessorCandidate[];
}) {
    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button size="sm" variant="outline">
                    <ArrowRightLeft /> Replace tracking
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        Transfer monitoring to a successor
                    </DialogTitle>
                    <DialogDescription>
                        Historical consumers remain attached to this version.
                        The selected later applied version becomes the active
                        governed improvement.
                    </DialogDescription>
                </DialogHeader>
                <Form
                    action={`/improvement-decisions/${proposal.id}/supersede`}
                    method="patch"
                    className="grid gap-4"
                >
                    {({ errors, processing }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor={`successor-${proposal.id}`}>
                                    Governed successor
                                </Label>
                                <select
                                    id={`successor-${proposal.id}`}
                                    name="successor_proposal_id"
                                    className="h-9 rounded-md border bg-background px-3 text-sm"
                                    required
                                >
                                    <option value="">
                                        Select a later applied version
                                    </option>
                                    {candidates.map((candidate) => (
                                        <option
                                            key={candidate.id}
                                            value={candidate.id}
                                        >
                                            {candidate.title}
                                        </option>
                                    ))}
                                </select>
                                <FieldError
                                    message={errors.successor_proposal_id}
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label
                                    htmlFor={`supersession-note-${proposal.id}`}
                                >
                                    Supersession evidence and reason
                                </Label>
                                <Textarea
                                    id={`supersession-note-${proposal.id}`}
                                    name="note"
                                    placeholder="Explain why the later governed version replaces this improvement."
                                    required
                                />
                                <FieldError
                                    message={errors.note || errors.monitoring}
                                />
                            </div>
                            <Button disabled={processing}>
                                Transfer monitoring
                            </Button>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function ApplicationDialog({
    proposal,
}: {
    proposal: ImprovementInboxProposal;
}) {
    const preview = proposal.application_preview;
    const products =
        preview?.current.products?.filter((product) => product.eligible) ?? [];
    const [productId, setProductId] = useState(
        products[0]?.id.toString() ?? '',
    );
    const selectedProduct = products.find(
        (product) => product.id.toString() === productId,
    );

    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button size="sm">
                    <CheckCircle2 /> Apply
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[85vh] overflow-y-auto sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>Apply approved improvement</DialogTitle>
                    <DialogDescription>
                        Review the exact destination and confirm a second time.
                        The controlled adapter will record its result in the
                        CAPA history.
                    </DialogDescription>
                </DialogHeader>
                <div className="rounded-md bg-muted/50 p-3 text-sm">
                    <p>
                        <span className="font-medium">Destination: </span>
                        {preview?.target?.name || 'Unavailable'} · version{' '}
                        {preview?.current_version || '—'}
                    </p>
                    <p className="mt-1">
                        <span className="font-medium">Approved change: </span>
                        {preview?.proposed_change || proposal.title}
                    </p>
                </div>
                <Form
                    action={`/improvement-decisions/${proposal.id}/apply`}
                    method="post"
                    className="grid gap-4"
                >
                    {({ errors, processing }) => (
                        <>
                            {proposal.type === 'work_order_template' ? (
                                <div className="grid gap-2">
                                    <Label
                                        htmlFor={`application-${proposal.id}-checklist`}
                                    >
                                        Required checklist item
                                    </Label>
                                    <Textarea
                                        id={`application-${proposal.id}-checklist`}
                                        name="checklist_label"
                                        defaultValue={(
                                            preview?.proposed_change || ''
                                        ).slice(0, 255)}
                                        required
                                    />
                                    <FieldError
                                        message={errors.checklist_label}
                                    />
                                </div>
                            ) : null}
                            {proposal.type === 'maintenance_scope' ? (
                                <div className="grid gap-2">
                                    <Label
                                        htmlFor={`application-${proposal.id}-scope`}
                                    >
                                        Text to add to the maintenance scope
                                    </Label>
                                    <Textarea
                                        id={`application-${proposal.id}-scope`}
                                        name="scope_addition"
                                        defaultValue={
                                            preview?.proposed_change || ''
                                        }
                                        required
                                    />
                                    <FieldError
                                        message={errors.scope_addition}
                                    />
                                </div>
                            ) : null}
                            {proposal.type === 'preventive_stock' ? (
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label
                                            htmlFor={`application-${proposal.id}-product`}
                                        >
                                            Preventive product
                                        </Label>
                                        <select
                                            id={`application-${proposal.id}-product`}
                                            name="product_id"
                                            value={productId}
                                            onChange={(event) =>
                                                setProductId(event.target.value)
                                            }
                                            className="h-9 rounded-md border bg-background px-3 text-sm"
                                            required
                                        >
                                            {products.map((product) => (
                                                <option
                                                    key={product.id}
                                                    value={product.id}
                                                >
                                                    {product.sku
                                                        ? `${product.sku} · `
                                                        : ''}
                                                    {product.name}
                                                </option>
                                            ))}
                                        </select>
                                        <FieldError
                                            message={errors.product_id}
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label
                                            htmlFor={`application-${proposal.id}-quantity`}
                                        >
                                            Replenishment objective
                                        </Label>
                                        <input
                                            key={productId}
                                            id={`application-${proposal.id}-quantity`}
                                            name="target_quantity"
                                            type="number"
                                            min="0.001"
                                            step="0.001"
                                            defaultValue={
                                                selectedProduct?.forecast_shortage ??
                                                ''
                                            }
                                            className="h-9 rounded-md border bg-background px-3 text-sm"
                                            required
                                        />
                                        <FieldError
                                            message={errors.target_quantity}
                                        />
                                    </div>
                                </div>
                            ) : null}
                            <div className="grid gap-2">
                                <Label
                                    htmlFor={`application-${proposal.id}-note`}
                                >
                                    Application evidence and reason
                                </Label>
                                <Textarea
                                    id={`application-${proposal.id}-note`}
                                    name="application_note"
                                    placeholder="Explain why this exact change is safe to apply now."
                                    required
                                />
                                <FieldError
                                    message={
                                        errors.application_note ||
                                        errors.application
                                    }
                                />
                            </div>
                            <label className="flex items-start gap-2 rounded-md border p-3 text-sm">
                                <input
                                    type="checkbox"
                                    name="confirmed"
                                    value="1"
                                    className="mt-0.5"
                                    required
                                />
                                <span>
                                    I reviewed the destination and confirm this
                                    second, controlled application action.
                                </span>
                            </label>
                            <Button disabled={processing}>
                                Apply controlled improvement
                            </Button>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function ApplicationResult({
    result,
}: {
    result: ImprovementInboxProposal['application_result'];
}) {
    if (!result) {
        return null;
    }

    const reference =
        result.opportunity_code ||
        result.replenishment_target_id ||
        result.maintenance_plan_version ||
        result.checklist_item;

    return reference ? (
        <p className="mt-1 text-xs text-muted-foreground">
            Result: {String(reference)}
        </p>
    ) : null;
}

function DecisionDialog({
    proposal,
    action,
    disabled = false,
}: {
    proposal: ImprovementInboxProposal;
    action: 'approve' | 'reject' | 'return' | 'resubmit';
    disabled?: boolean;
}) {
    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button
                    size="sm"
                    variant={action === 'approve' ? 'default' : 'outline'}
                    disabled={disabled}
                >
                    {actionIcon(action)} {humanize(action)}
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {humanize(action)} {proposal.title}
                    </DialogTitle>
                    <DialogDescription>
                        This decision is recorded in the CAPA history. It does
                        not apply the proposed downstream change.
                    </DialogDescription>
                </DialogHeader>
                <Form
                    action={`/after-sales/quality/corrective-proposals/${proposal.id}`}
                    method="patch"
                    className="grid gap-4"
                >
                    {({ errors, processing }) => (
                        <>
                            <input type="hidden" name="action" value={action} />
                            {action === 'resubmit' ? (
                                <div className="grid gap-2">
                                    <Label
                                        htmlFor={`proposal-${proposal.id}-change`}
                                    >
                                        Revised proposed change
                                    </Label>
                                    <Textarea
                                        id={`proposal-${proposal.id}-change`}
                                        name="proposed_change"
                                        defaultValue={
                                            proposal.payload?.proposed_change ||
                                            ''
                                        }
                                        required
                                    />
                                    <FieldError
                                        message={errors.proposed_change}
                                    />
                                </div>
                            ) : null}
                            <div className="grid gap-2">
                                <Label
                                    htmlFor={`proposal-${proposal.id}-${action}-note`}
                                >
                                    Decision note
                                </Label>
                                <Textarea
                                    id={`proposal-${proposal.id}-${action}-note`}
                                    name="note"
                                    placeholder="Explain the evidence, ownership and next step."
                                    required
                                />
                                <FieldError
                                    message={errors.note || errors.action}
                                />
                            </div>
                            <Button disabled={processing}>
                                Record without applying
                            </Button>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function FieldError({ message }: { message?: string }) {
    return message ? (
        <p className="text-sm text-destructive">{message}</p>
    ) : null;
}

function StatusBadge({ status }: { status: string }) {
    if (status === 'approved') {
        return (
            <Badge className="bg-emerald-600">Approved for application</Badge>
        );
    }

    if (status === 'rejected') {
        return <Badge variant="secondary">Rejected</Badge>;
    }

    if (status === 'applied') {
        return <Badge className="bg-emerald-700">Applied</Badge>;
    }

    if (status === 'returned') {
        return <Badge variant="secondary">Returned for revision</Badge>;
    }

    return <Badge>Pending decision</Badge>;
}

function actionIcon(action: string) {
    if (action === 'approve') {
        return <CheckCircle2 />;
    }

    if (action === 'reject') {
        return <XCircle />;
    }

    return <RotateCcw />;
}

function ownerArea(type: string) {
    if (type === 'preventive_stock') {
        return 'Supply';
    }

    if (type === 'renewal') {
        return 'CRM';
    }

    return 'Operations';
}

function humanize(value: string) {
    return value
        .replaceAll('_', ' ')
        .replace(/^./, (letter) => letter.toUpperCase());
}

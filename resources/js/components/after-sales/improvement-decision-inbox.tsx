import { Form } from '@inertiajs/react';
import { AlertTriangle, CheckCircle2, RotateCcw, XCircle } from 'lucide-react';
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

export function ImprovementDecisionInbox({
    proposals,
}: {
    proposals: ImprovementInboxProposal[];
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
                <ProposalCard key={proposal.id} proposal={proposal} />
            ))}
        </section>
    );
}

function ProposalCard({ proposal }: { proposal: ImprovementInboxProposal }) {
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
                        Prepared against {proposal.conflict?.prepared_version || '—'};
                        the current version is{' '}
                        {proposal.conflict?.current_version || '—'}. Approval is
                        blocked until the proposal is returned and resubmitted.
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
                    {humanize(proposal.target_type || 'not assigned')} · Version:{' '}
                    {proposal.target_version || '—'}
                    {proposal.resubmission_count > 0
                        ? ` · Resubmissions: ${proposal.resubmission_count}`
                        : ''}
                </p>
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
            </div>
        </article>
    );
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
                                    <Label htmlFor={`proposal-${proposal.id}-change`}>
                                        Revised proposed change
                                    </Label>
                                    <Textarea
                                        id={`proposal-${proposal.id}-change`}
                                        name="proposed_change"
                                        defaultValue={proposal.payload?.proposed_change || ''}
                                        required
                                    />
                                    <FieldError message={errors.proposed_change} />
                                </div>
                            ) : null}
                            <div className="grid gap-2">
                                <Label htmlFor={`proposal-${proposal.id}-${action}-note`}>
                                    Decision note
                                </Label>
                                <Textarea
                                    id={`proposal-${proposal.id}-${action}-note`}
                                    name="note"
                                    placeholder="Explain the evidence, ownership and next step."
                                    required
                                />
                                <FieldError message={errors.note || errors.action} />
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
    return message ? <p className="text-sm text-destructive">{message}</p> : null;
}

function StatusBadge({ status }: { status: string }) {
    if (status === 'approved') {
        return <Badge className="bg-emerald-600">Approved for application</Badge>;
    }

    if (status === 'rejected') {
        return <Badge variant="secondary">Rejected</Badge>;
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

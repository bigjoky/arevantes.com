import { Form } from '@inertiajs/react';
import { FormErrorSummary } from '@/components/forms/form-layout';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

type Actor = { id: number; name: string };
export type ScheduleProposal = {
    id: number;
    proposed_on: string;
    estimated_hours: string;
    status: string;
    fingerprint: string;
    assignee: Actor;
    creator: Actor;
    reviewer: Actor | null;
    note: string;
    review_note: string | null;
};

export function TechnicalSchedulePanel({
    dossierId,
    version,
    fingerprint,
    proposals,
    assignees,
    canManage,
}: {
    dossierId: number;
    version: number;
    fingerprint: string;
    proposals: ScheduleProposal[];
    assignees: Actor[];
    canManage: boolean;
}) {
    const latest = proposals[0];
    const canPropose = !latest || latest.status === 'declined';

    return (
        <section className="space-y-3 rounded-lg border p-4">
            <h3 className="font-medium">Scheduling proposal</h3>
            <p className="text-sm text-muted-foreground">
                Approval records the proposed date and effort. Resource
                availability is not reserved yet.
            </p>
            {proposals.map((proposal) => (
                <div
                    key={proposal.id}
                    className="space-y-1 border-b pb-3 text-sm"
                >
                    <p>
                        {proposal.proposed_on} · {proposal.assignee?.name} ·{' '}
                        {proposal.estimated_hours} h
                    </p>
                    <p>
                        {proposal.status === 'pending'
                            ? 'Pending review'
                            : proposal.status === 'approved'
                              ? 'Approved'
                              : 'Declined'}
                    </p>
                    <p className="break-words whitespace-pre-wrap">
                        {proposal.creator?.name} · {proposal.note}
                    </p>
                    {proposal.review_note && (
                        <p className="break-words whitespace-pre-wrap">
                            {proposal.reviewer?.name} · {proposal.review_note}
                        </p>
                    )}
                </div>
            ))}
            {canManage && (canPropose || latest.status === 'pending') && (
                <Form
                    action={`/engineering/${dossierId}/schedule`}
                    method="patch"
                    options={{ preserveScroll: true }}
                >
                    {({ errors, processing }) => (
                        <div className="space-y-3">
                            <FormErrorSummary errors={errors} />
                            <input
                                type="hidden"
                                name="expected_version"
                                value={version}
                            />
                            <input
                                type="hidden"
                                name="fingerprint"
                                value={fingerprint}
                            />
                            <input
                                type="hidden"
                                name="expected_proposal_id"
                                value={latest?.id ?? 0}
                            />
                            <input
                                type="hidden"
                                name="proposal_fingerprint"
                                value={latest?.fingerprint ?? ''}
                            />
                            {canPropose && (
                                <div className="grid gap-3 sm:grid-cols-3">
                                    <div className="space-y-2">
                                        <Label
                                            htmlFor={`schedule-date-${dossierId}`}
                                        >
                                            Proposed date
                                        </Label>
                                        <Input
                                            id={`schedule-date-${dossierId}`}
                                            name="proposed_on"
                                            type="date"
                                            required
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <Label
                                            htmlFor={`schedule-assignee-${dossierId}`}
                                        >
                                            Responsible person
                                        </Label>
                                        <select
                                            id={`schedule-assignee-${dossierId}`}
                                            name="assignee_id"
                                            className="h-9 w-full min-w-0 rounded-md border bg-background px-2 text-sm"
                                            defaultValue=""
                                            required
                                        >
                                            <option value="" disabled>
                                                Select a person
                                            </option>
                                            {assignees.map((actor) => (
                                                <option
                                                    key={actor.id}
                                                    value={actor.id}
                                                >
                                                    {actor.name}
                                                </option>
                                            ))}
                                        </select>
                                    </div>
                                    <div className="space-y-2">
                                        <Label
                                            htmlFor={`schedule-hours-${dossierId}`}
                                        >
                                            Estimated hours
                                        </Label>
                                        <Input
                                            id={`schedule-hours-${dossierId}`}
                                            name="estimated_hours"
                                            type="number"
                                            min="0.25"
                                            max="24"
                                            step="0.25"
                                            required
                                        />
                                    </div>
                                </div>
                            )}
                            <Label htmlFor={`schedule-note-${dossierId}`}>
                                Planning note
                            </Label>
                            <Textarea
                                id={`schedule-note-${dossierId}`}
                                name="note"
                                minLength={10}
                                maxLength={1000}
                                required
                            />
                            <div className="flex flex-wrap gap-2">
                                {canPropose ? (
                                    <Button
                                        name="action"
                                        value="propose"
                                        disabled={processing}
                                    >
                                        Submit scheduling proposal
                                    </Button>
                                ) : (
                                    <>
                                        <Button
                                            name="action"
                                            value="approve"
                                            disabled={processing}
                                        >
                                            Approve scheduling proposal
                                        </Button>
                                        <Button
                                            name="action"
                                            value="decline"
                                            variant="outline"
                                            disabled={processing}
                                        >
                                            Decline scheduling proposal
                                        </Button>
                                    </>
                                )}
                            </div>
                        </div>
                    )}
                </Form>
            )}
        </section>
    );
}

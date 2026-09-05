import { Form } from '@inertiajs/react';
import { FormErrorSummary } from '@/components/forms/form-layout';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

export type TechnicalPlanningRequest = {
    id: number;
    current_version: number;
    planning_status: string | null;
    planning_fingerprint: string | null;
    planning_note: string | null;
    planning_review_note: string | null;
    planning_requester: { name: string } | null;
    planning_reviewer: { name: string } | null;
    planning_requested_at: string | null;
    planning_reviewed_at: string | null;
};

export function TechnicalPlanningPanel({
    dossier,
    fingerprint,
    canRequest = false,
    canReview = false,
}: {
    dossier: TechnicalPlanningRequest;
    fingerprint?: string;
    canRequest?: boolean;
    canReview?: boolean;
}) {
    const actionable =
        (!dossier.planning_status && canRequest) ||
        (dossier.planning_status === 'pending' && canReview);

    return (
        <section className="space-y-3 rounded-lg border p-4">
            <h3 className="font-medium">Technical planning request</h3>
            <p className="text-sm text-muted-foreground">
                Operations reviews the approved technical scope before
                scheduling resources.
            </p>
            {dossier.planning_status && (
                <div className="space-y-2 text-sm">
                    <p>
                        {dossier.planning_status === 'pending'
                            ? 'Awaiting planning review'
                            : dossier.planning_status === 'accepted'
                              ? 'Accepted for planning'
                              : 'Declined for planning'}
                    </p>
                    <p className="break-words whitespace-pre-wrap">
                        {dossier.planning_note}
                    </p>
                    <p>
                        {dossier.planning_requester?.name} ·{' '}
                        {dossier.planning_requested_at}
                    </p>
                    {dossier.planning_review_note && (
                        <p className="break-words whitespace-pre-wrap">
                            {dossier.planning_review_note}
                        </p>
                    )}
                    {dossier.planning_reviewed_at && (
                        <p>
                            {dossier.planning_reviewer?.name} ·{' '}
                            {dossier.planning_reviewed_at}
                        </p>
                    )}
                </div>
            )}
            {actionable && (
                <Form
                    action={`/engineering/${dossier.id}/planning`}
                    method="patch"
                    options={{ preserveScroll: true }}
                >
                    {({ errors, processing }) => (
                        <div className="space-y-3">
                            <FormErrorSummary errors={errors} />
                            <input
                                type="hidden"
                                name="expected_version"
                                value={dossier.current_version}
                            />
                            <input
                                type="hidden"
                                name="fingerprint"
                                value={
                                    fingerprint ??
                                    dossier.planning_fingerprint ??
                                    ''
                                }
                            />
                            <Label htmlFor={`planning-note-${dossier.id}`}>
                                Planning note
                            </Label>
                            <Textarea
                                id={`planning-note-${dossier.id}`}
                                name="note"
                                required
                                minLength={10}
                                maxLength={1000}
                            />
                            <div className="flex flex-wrap gap-2">
                                {!dossier.planning_status ? (
                                    <Button
                                        name="action"
                                        value="request"
                                        disabled={processing}
                                    >
                                        Request planning
                                    </Button>
                                ) : (
                                    <>
                                        <Button
                                            name="action"
                                            value="accept"
                                            disabled={processing}
                                        >
                                            Accept for planning
                                        </Button>
                                        <Button
                                            name="action"
                                            value="decline"
                                            variant="outline"
                                            disabled={processing}
                                        >
                                            Decline planning request
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

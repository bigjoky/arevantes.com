import { Form } from '@inertiajs/react';
import { useState } from 'react';
import { FormErrorSummary } from '@/components/forms/form-layout';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

type Availability = {
    status: string;
    checked_at: string;
    timezone: string;
    start: string;
    end: string;
    issues: string[];
    warnings: string[];
};

export function TechnicalAvailabilityCheck({
    dossierId,
    version,
    fingerprint,
    proposalId,
    proposalFingerprint,
}: {
    dossierId: number;
    version: number;
    fingerprint: string;
    proposalId: number;
    proposalFingerprint: string;
}) {
    const [time, setTime] = useState('09:00');
    const [result, setResult] = useState<Availability | null>(null);
    const [errors, setErrors] = useState<string[]>([]);
    const [busy, setBusy] = useState(false);

    async function check() {
        setBusy(true);
        setResult(null);
        setErrors([]);

        try {
            const query = new URLSearchParams({
                expected_version: String(version),
                fingerprint,
                expected_proposal_id: String(proposalId),
                proposal_fingerprint: proposalFingerprint,
                start_time: time,
            });
            const response = await fetch(
                `/engineering/${dossierId}/availability?${query}`,
                { headers: { Accept: 'application/json' }, cache: 'no-store' },
            );

            if (!response.ok) {
                const body = await response.json();
                setErrors(
                    body.errors
                        ? Object.values(
                              body.errors as Record<string, string[]>,
                          ).flat()
                        : ['Availability could not be checked.'],
                );

                return;
            }

            setResult(await response.json());
        } catch {
            setErrors(['Availability could not be checked.']);
        } finally {
            setBusy(false);
        }
    }

    const resultLabel =
        result?.status === 'blocked'
            ? 'Availability blocked'
            : result?.status === 'warning'
              ? 'Review availability warnings'
              : 'No availability conflicts found';
    const canCreate = result?.status === 'clear';

    return (
        <section className="space-y-3 rounded-md border p-3">
            <h4 className="font-medium">Check resource availability</h4>
            <p className="text-sm text-muted-foreground">
                This check does not reserve time. Availability must be checked
                again when work is created.
            </p>
            <form
                className="flex flex-wrap items-end gap-3"
                onSubmit={(event) => {
                    event.preventDefault();
                    void check();
                }}
            >
                <div className="space-y-2">
                    <Label htmlFor={`availability-time-${proposalId}`}>
                        Start time
                    </Label>
                    <Input
                        id={`availability-time-${proposalId}`}
                        type="time"
                        required
                        value={time}
                        disabled={busy}
                        onChange={(event) => {
                            setTime(event.target.value);
                            setResult(null);
                            setErrors([]);
                        }}
                    />
                </div>
                <Button disabled={busy}>Check availability</Button>
            </form>
            <div aria-live="polite" className="space-y-2 text-sm">
                {errors.map((error, index) => (
                    <p role="alert" key={index}>
                        {error}
                    </p>
                ))}
                {result && (
                    <>
                        <p>{resultLabel}</p>
                        <p>
                            {result.start} — {result.end} · {result.timezone}
                        </p>
                        <p>{result.checked_at}</p>
                        {[...result.issues, ...result.warnings].map(
                            (issue, index) => (
                                <p key={index}>{issue}</p>
                            ),
                        )}
                    </>
                )}
            </div>
            {canCreate && (
                <Form
                    action={`/engineering/${dossierId}/work-order`}
                    method="post"
                    options={{ preserveScroll: true }}
                >
                    {({ errors: creationErrors, processing }) => (
                        <div className="space-y-3 border-t pt-3">
                            <h4 className="font-medium">
                                Create planned work order
                            </h4>
                            <p className="text-sm text-muted-foreground">
                                This creates an assigned order, updates the
                                project schedule and applies the normal material
                                reservation and notification rules.
                            </p>
                            <FormErrorSummary errors={creationErrors} />
                            <input
                                type="hidden"
                                name="expected_version"
                                value={version}
                            />
                            <input
                                type="hidden"
                                name="expected_proposal_id"
                                value={proposalId}
                            />
                            <input
                                type="hidden"
                                name="fingerprint"
                                value={fingerprint}
                            />
                            <input
                                type="hidden"
                                name="proposal_fingerprint"
                                value={proposalFingerprint}
                            />
                            <input
                                type="hidden"
                                name="start_time"
                                value={time}
                            />
                            <Label htmlFor={`execution-note-${proposalId}`}>
                                Planning note
                            </Label>
                            <Textarea
                                id={`execution-note-${proposalId}`}
                                name="note"
                                required
                                minLength={10}
                                maxLength={1000}
                            />
                            <Button disabled={processing || busy}>
                                Create planned work order
                            </Button>
                        </div>
                    )}
                </Form>
            )}
        </section>
    );
}

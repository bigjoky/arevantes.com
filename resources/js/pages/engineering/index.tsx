import { Form, Head, Link } from '@inertiajs/react';
import {
    CheckCircle2,
    ClipboardCheck,
    FileClock,
    Pencil,
    Plus,
    Send,
    Undo2,
} from 'lucide-react';
import { useState } from 'react';
import {
    FormActions,
    FormErrorSummary,
    FormSection,
} from '@/components/forms/form-layout';
import { TechnicalPlanningPanel } from '@/components/technical-planning-request';
import type { TechnicalPlanningRequest } from '@/components/technical-planning-request';
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

type User = { id: number; name: string };
type Opportunity = {
    id: number;
    installation_id: number | null;
    code: string;
    title: string;
    stage: string;
};
type Installation = {
    id: number;
    project_reference: string | null;
    site_name: string | null;
    service_type: string;
    status: string;
};
type Version = {
    id: number;
    version: number;
    fingerprint: string;
    change_note: string;
    created_at: string;
    creator: User;
};
type Event = {
    id: number;
    type: string;
    from_status: string | null;
    to_status: string | null;
    dossier_version: number;
    note: string;
    occurred_at: string;
    actor: User;
};
type Dossier = TechnicalPlanningRequest & {
    id: number;
    code: string;
    title: string;
    business_line: string;
    kind: string;
    status: 'draft' | 'in_review' | 'approved' | 'handed_over';
    current_version: number;
    current_fingerprint: string;
    opportunity_id: number | null;
    installation_id: number | null;
    scope: string;
    site_conditions: string | null;
    calculations: string | null;
    proposed_solution: string | null;
    risks: string | null;
    standards: string | null;
    conclusions: string | null;
    approved_at: string | null;
    handed_over_at: string | null;
    opportunity: Pick<Opportunity, 'id' | 'code' | 'title' | 'stage'> | null;
    installation: Installation | null;
    creator: User;
    updater: User;
    approver: User | null;
    handover_actor: User | null;
    versions: Version[];
    events: Event[];
};

const businessLines = [
    ['solar', 'Solar'],
    ['ev_charger', 'EV charging'],
    ['hvac', 'HVAC'],
    ['electrical', 'Electrical'],
    ['security', 'Security'],
];
const dossierKinds = [
    ['survey', 'Technical survey'],
    ['design', 'Design'],
    ['calculation', 'Calculation'],
    ['technical_report', 'Technical report'],
];

export default function EngineeringIndex({
    dossiers,
    opportunities,
    installations,
    canManage,
}: {
    dossiers: Dossier[];
    opportunities: Opportunity[];
    installations: Installation[];
    canManage: boolean;
}) {
    return (
        <>
            <Head title="Engineering" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <header className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold">Engineering</h1>
                        <p className="max-w-3xl text-sm text-muted-foreground">
                            Versioned studies, calculations and designs with an
                            explicit governed handover to project execution.
                        </p>
                    </div>
                    {canManage ? (
                        <DossierDialog
                            opportunities={opportunities}
                            installations={installations}
                        />
                    ) : null}
                </header>

                <section className="grid grid-cols-2 border-y md:grid-cols-4">
                    <Metric label="Drafts" value={count(dossiers, 'draft')} />
                    <Metric
                        label="In review"
                        value={count(dossiers, 'in_review')}
                    />
                    <Metric
                        label="Approved"
                        value={count(dossiers, 'approved')}
                    />
                    <Metric
                        label="Handed over"
                        value={count(dossiers, 'handed_over')}
                    />
                </section>

                <section className="grid gap-4">
                    {dossiers.map((dossier) => (
                        <DossierCard
                            key={dossier.id}
                            dossier={dossier}
                            opportunities={opportunities}
                            installations={installations}
                            canManage={canManage}
                        />
                    ))}
                    {dossiers.length === 0 ? (
                        <div className="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground">
                            No technical dossiers yet.
                        </div>
                    ) : null}
                </section>
            </div>
        </>
    );
}

function DossierCard({
    dossier,
    opportunities,
    installations,
    canManage,
}: {
    dossier: Dossier;
    opportunities: Opportunity[];
    installations: Installation[];
    canManage: boolean;
}) {
    const showPlanning =
        ['approved', 'handed_over'].includes(dossier.status) ||
        Boolean(dossier.planning_status);

    return (
        <article
            id={`dossier-${dossier.id}`}
            className="grid scroll-mt-20 gap-4 rounded-lg border p-4 [contain-intrinsic-size:auto_30rem] [content-visibility:auto] target:ring-2 target:ring-primary sm:p-5"
        >
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <div className="flex flex-wrap items-center gap-2">
                        <span className="font-mono text-xs text-muted-foreground">
                            {dossier.code}
                        </span>
                        <Badge variant="outline">
                            v{dossier.current_version}
                        </Badge>
                        <Badge>{label(dossier.status)}</Badge>
                    </div>
                    <h2 className="mt-2 text-lg font-semibold">
                        {dossier.title}
                    </h2>
                    <p className="text-sm text-muted-foreground">
                        {label(dossier.kind)} · {label(dossier.business_line)}
                    </p>
                </div>
                {canManage && dossier.status === 'draft' ? (
                    <DossierDialog
                        dossier={dossier}
                        opportunities={opportunities}
                        installations={installations}
                    />
                ) : null}
            </div>

            <div className="flex flex-wrap gap-3 text-sm">
                {dossier.opportunity ? (
                    <Link
                        href={`/opportunities/${dossier.opportunity.id}`}
                        className="font-medium underline underline-offset-4"
                    >
                        {dossier.opportunity.code} · {dossier.opportunity.title}
                    </Link>
                ) : null}
                {dossier.installation ? (
                    <Link
                        href={`/installations/${dossier.installation.id}`}
                        className="font-medium underline underline-offset-4"
                    >
                        {dossier.installation.project_reference ||
                            dossier.installation.site_name ||
                            `Project #${dossier.installation.id}`}
                    </Link>
                ) : null}
            </div>

            <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                <TechnicalField label="Scope" value={dossier.scope} />
                <TechnicalField
                    label="Site conditions"
                    value={dossier.site_conditions}
                />
                <TechnicalField
                    label="Calculations"
                    value={dossier.calculations}
                />
                <TechnicalField
                    label="Proposed solution"
                    value={dossier.proposed_solution}
                />
                <TechnicalField label="Risks" value={dossier.risks} />
                <TechnicalField label="Standards" value={dossier.standards} />
                <TechnicalField
                    label="Conclusions"
                    value={dossier.conclusions}
                />
            </div>

            {canManage ? <WorkflowActions dossier={dossier} /> : null}
            {showPlanning && (
                <TechnicalPlanningPanel
                    dossier={dossier}
                    fingerprint={dossier.current_fingerprint}
                    canRequest={canManage && Boolean(dossier.installation_id)}
                />
            )}

            <div className="grid gap-3 lg:grid-cols-2">
                <details className="rounded-md border p-3 text-sm">
                    <summary className="cursor-pointer font-medium">
                        Version history ({dossier.versions.length})
                    </summary>
                    <div className="mt-3 grid gap-2">
                        {dossier.versions.map((version) => (
                            <div key={version.id} className="border-t pt-2">
                                <div className="font-medium">
                                    v{version.version} · {version.change_note}
                                </div>
                                <div className="text-xs text-muted-foreground">
                                    {version.creator.name} ·{' '}
                                    {dateTime(version.created_at)} ·{' '}
                                    {version.fingerprint.slice(0, 12)}…
                                </div>
                            </div>
                        ))}
                    </div>
                </details>
                <details className="rounded-md border p-3 text-sm">
                    <summary className="cursor-pointer font-medium">
                        Audit trail ({dossier.events.length})
                    </summary>
                    <div className="mt-3 grid gap-2">
                        {dossier.events.map((event) => (
                            <div key={event.id} className="border-t pt-2">
                                <div className="font-medium">
                                    {label(event.type)} · v
                                    {event.dossier_version}
                                </div>
                                <div>{event.note}</div>
                                <div className="text-xs text-muted-foreground">
                                    {event.actor.name} ·{' '}
                                    {dateTime(event.occurred_at)}
                                </div>
                            </div>
                        ))}
                    </div>
                </details>
            </div>
        </article>
    );
}

function WorkflowActions({ dossier }: { dossier: Dossier }) {
    if (dossier.status === 'handed_over') {
        return (
            <div className="rounded-md border border-emerald-500/30 bg-emerald-500/5 p-3 text-sm">
                Handed to project execution by {dossier.handover_actor?.name} on{' '}
                {dossier.handed_over_at
                    ? dateTime(dossier.handed_over_at)
                    : '—'}
            </div>
        );
    }

    const actions =
        dossier.status === 'draft'
            ? [
                  {
                      action: 'submit',
                      label: 'Submit for review',
                      icon: <Send />,
                  },
              ]
            : dossier.status === 'in_review'
              ? [
                    {
                        action: 'return',
                        label: 'Return to draft',
                        icon: <Undo2 />,
                    },
                    {
                        action: 'approve',
                        label: 'Approve version',
                        icon: <CheckCircle2 />,
                    },
                ]
              : [
                    {
                        action: 'handover',
                        label: 'Hand over to execution',
                        icon: <ClipboardCheck />,
                    },
                ];

    return (
        <div className="flex flex-wrap gap-2">
            {actions.map((action) => (
                <TransitionDialog
                    key={action.action}
                    dossier={dossier}
                    action={action.action}
                    label={action.label}
                    icon={action.icon}
                />
            ))}
        </div>
    );
}

function TransitionDialog({
    dossier,
    action,
    label,
    icon,
}: {
    dossier: Dossier;
    action: string;
    label: string;
    icon: React.ReactNode;
}) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="outline" size="sm">
                    {icon} {label}
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{label}</DialogTitle>
                </DialogHeader>
                <Form
                    action={`/engineering/${dossier.id}/transition`}
                    method="patch"
                    onSuccess={() => setOpen(false)}
                    className="grid gap-4"
                >
                    {({ errors, processing }) => (
                        <>
                            <input type="hidden" name="action" value={action} />
                            <input
                                type="hidden"
                                name="expected_version"
                                value={dossier.current_version}
                            />
                            <input
                                type="hidden"
                                name="expected_fingerprint"
                                value={dossier.current_fingerprint}
                            />
                            <FormErrorSummary errors={errors} />
                            <div className="grid gap-2">
                                <Label htmlFor={`note-${dossier.id}-${action}`}>
                                    Decision note
                                </Label>
                                <Textarea
                                    id={`note-${dossier.id}-${action}`}
                                    name="note"
                                    required
                                    minLength={10}
                                    placeholder="Record the technical review and decision evidence."
                                />
                            </div>
                            {action === 'handover' &&
                            !dossier.installation_id ? (
                                <p className="text-sm text-destructive">
                                    Link this dossier to a project before
                                    handover.
                                </p>
                            ) : null}
                            <Button
                                disabled={
                                    processing ||
                                    (action === 'handover' &&
                                        !dossier.installation_id)
                                }
                            >
                                {icon} {processing ? 'Saving...' : label}
                            </Button>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function DossierDialog({
    dossier,
    opportunities,
    installations,
}: {
    dossier?: Dossier;
    opportunities: Opportunity[];
    installations: Installation[];
}) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant={dossier ? 'outline' : 'default'}>
                    {dossier ? <Pencil /> : <Plus />}
                    {dossier ? 'Edit draft' : 'New technical dossier'}
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[92dvh] w-[calc(100%-1rem)] overflow-y-auto sm:max-w-4xl">
                <DialogHeader>
                    <DialogTitle>
                        {dossier
                            ? 'Revise technical dossier'
                            : 'New technical dossier'}
                    </DialogTitle>
                </DialogHeader>
                <Form
                    action={
                        dossier ? `/engineering/${dossier.id}` : '/engineering'
                    }
                    method={dossier ? 'patch' : 'post'}
                    onSuccess={() => setOpen(false)}
                    className="grid gap-4"
                >
                    {({ errors, processing }) => (
                        <>
                            {dossier ? (
                                <input
                                    type="hidden"
                                    name="expected_version"
                                    value={dossier.current_version}
                                />
                            ) : null}
                            <FormErrorSummary errors={errors} />
                            <FormSection
                                title="Identity and scope"
                                description="Link the study to its commercial or project context without committing execution resources."
                            >
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <Field
                                        label="Title"
                                        name="title"
                                        value={dossier?.title}
                                        required
                                        className="sm:col-span-2"
                                    />
                                    <SelectField
                                        label="Business line"
                                        name="business_line"
                                        value={
                                            dossier?.business_line ?? 'solar'
                                        }
                                        options={businessLines}
                                    />
                                    <SelectField
                                        label="Document kind"
                                        name="kind"
                                        value={dossier?.kind ?? 'survey'}
                                        options={dossierKinds}
                                    />
                                    <SelectField
                                        label="Opportunity"
                                        name="opportunity_id"
                                        value={String(
                                            dossier?.opportunity_id ?? 'none',
                                        )}
                                        options={[
                                            ['none', 'No opportunity'],
                                            ...opportunities.map(
                                                (opportunity) => [
                                                    String(opportunity.id),
                                                    `${opportunity.code} · ${opportunity.title}`,
                                                ],
                                            ),
                                        ]}
                                    />
                                    <SelectField
                                        label="Project"
                                        name="installation_id"
                                        value={String(
                                            dossier?.installation_id ?? 'none',
                                        )}
                                        options={[
                                            ['none', 'No project'],
                                            ...installations.map(
                                                (installation) => [
                                                    String(installation.id),
                                                    installation.project_reference ||
                                                        installation.site_name ||
                                                        `#${installation.id}`,
                                                ],
                                            ),
                                        ]}
                                    />
                                </div>
                                <TextField
                                    label="Scope"
                                    name="scope"
                                    value={dossier?.scope}
                                    required
                                />
                            </FormSection>
                            <FormSection
                                title="Technical evidence"
                                description="Record enough evidence for another professional to reproduce the reasoning."
                            >
                                <div className="grid gap-4 md:grid-cols-2">
                                    <TextField
                                        label="Site conditions"
                                        name="site_conditions"
                                        value={dossier?.site_conditions}
                                    />
                                    <TextField
                                        label="Calculations"
                                        name="calculations"
                                        value={dossier?.calculations}
                                    />
                                    <TextField
                                        label="Proposed solution"
                                        name="proposed_solution"
                                        value={dossier?.proposed_solution}
                                    />
                                    <TextField
                                        label="Risks"
                                        name="risks"
                                        value={dossier?.risks}
                                    />
                                    <TextField
                                        label="Standards"
                                        name="standards"
                                        value={dossier?.standards}
                                    />
                                    <TextField
                                        label="Conclusions"
                                        name="conclusions"
                                        value={dossier?.conclusions}
                                    />
                                </div>
                            </FormSection>
                            {dossier ? (
                                <FormSection
                                    title="Revision traceability"
                                    description="Explain what changed and why this version is needed."
                                >
                                    <TextField
                                        label="Change note"
                                        name="change_note"
                                        required
                                        minLength={10}
                                    />
                                </FormSection>
                            ) : null}
                            <FormActions
                                processing={processing}
                                submitLabel={
                                    dossier
                                        ? 'Save new version'
                                        : 'Create technical dossier'
                                }
                                processingLabel="Saving dossier..."
                                icon={<FileClock />}
                                description="Draft content remains editable; review and approved versions are locked."
                            />
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function Field({
    label,
    name,
    value,
    required,
    className,
}: {
    label: string;
    name: string;
    value?: string | null;
    required?: boolean;
    className?: string;
}) {
    return (
        <div className={`grid gap-2 ${className || ''}`}>
            <Label htmlFor={name}>{label}</Label>
            <Input
                id={name}
                name={name}
                defaultValue={value ?? ''}
                required={required}
            />
        </div>
    );
}

function TextField({
    label,
    name,
    value,
    required,
    minLength,
}: {
    label: string;
    name: string;
    value?: string | null;
    required?: boolean;
    minLength?: number;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={name}>{label}</Label>
            <Textarea
                id={name}
                name={name}
                defaultValue={value ?? ''}
                required={required}
                minLength={minLength}
                className="min-h-28"
            />
        </div>
    );
}

function SelectField({
    label,
    name,
    value,
    options,
}: {
    label: string;
    name: string;
    value: string;
    options: string[][];
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={name}>{label}</Label>
            <select
                id={name}
                name={name}
                defaultValue={value}
                className="h-9 rounded-md border bg-transparent px-3 text-sm"
            >
                {options.map(([optionValue, optionLabel]) => (
                    <option key={optionValue} value={optionValue}>
                        {optionLabel}
                    </option>
                ))}
            </select>
        </div>
    );
}

function TechnicalField({
    label,
    value,
}: {
    label: string;
    value: string | null;
}) {
    return (
        <div className="rounded-md bg-muted/40 p-3">
            <div className="text-xs font-medium text-muted-foreground">
                {label}
            </div>
            <div className="mt-1 text-sm whitespace-pre-wrap">
                {value || '—'}
            </div>
        </div>
    );
}

function Metric({ label, value }: { label: string; value: number }) {
    return (
        <div className="border-r p-4 last:border-0">
            <div className="text-sm text-muted-foreground">{label}</div>
            <div className="mt-1 text-2xl font-semibold">{value}</div>
        </div>
    );
}

function count(dossiers: Dossier[], status: Dossier['status']): number {
    return dossiers.filter((dossier) => dossier.status === status).length;
}

function label(value: string): string {
    return value.replaceAll('_', ' ');
}

function dateTime(value: string): string {
    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}

import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    CalendarClock,
    CircleDollarSign,
    Pencil,
    Plus,
    Target,
    Trash2,
    TrendingUp,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
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
import { Textarea } from '@/components/ui/textarea';

const stages = [
    { value: 'new', label: 'New', probability: 10 },
    { value: 'qualified', label: 'Qualified', probability: 25 },
    { value: 'survey', label: 'Survey', probability: 40 },
    { value: 'proposal', label: 'Proposal', probability: 60 },
    { value: 'negotiation', label: 'Negotiation', probability: 80 },
    { value: 'won', label: 'Won', probability: 100 },
    { value: 'lost', label: 'Lost', probability: 0 },
] as const;

type Customer = {
    id: number;
    first_name: string;
    last_name: string | null;
    company_name: string | null;
};
type Installation = {
    id: number;
    person_id: number | null;
    project_reference: string | null;
    site_name: string | null;
};
type UserOption = { id: number; name: string };
type Opportunity = {
    id: number;
    code: string;
    title: string;
    stage: string;
    probability: number;
    estimated_value: string;
    expected_close_on: string | null;
    source: string | null;
    lost_reason: string | null;
    notes: string | null;
    person_id: number;
    installation_id: number | null;
    owner_user_id: number | null;
    customer: Customer | null;
    installation: Installation | null;
    owner: UserOption | null;
    quote: { id: number; number: string; status: string } | null;
};

type FormData = {
    person_id: string;
    installation_id: string;
    owner_user_id: string;
    title: string;
    stage: string;
    probability: string;
    estimated_value: string;
    expected_close_on: string;
    source: string;
    lost_reason: string;
    notes: string;
};

const emptyForm: FormData = {
    person_id: '',
    installation_id: '',
    owner_user_id: '',
    title: '',
    stage: 'new',
    probability: '10',
    estimated_value: '0',
    expected_close_on: '',
    source: '',
    lost_reason: '',
    notes: '',
};

export default function OpportunitiesIndex({
    opportunities,
    customers,
    installations,
    users,
    metrics,
}: {
    opportunities: Opportunity[];
    customers: Customer[];
    installations: Installation[];
    users: UserOption[];
    metrics: {
        pipeline_value: number;
        weighted_forecast: number;
        won_value: number;
        overdue: number;
    };
}) {
    const [open, setOpen] = useState(false);
    const [editing, setEditing] = useState<Opportunity | null>(null);
    const form = useForm<FormData>(emptyForm);
    const availableInstallations = useMemo(
        () =>
            installations.filter(
                (item) => String(item.person_id) === form.data.person_id,
            ),
        [form.data.person_id, installations],
    );

    useEffect(() => {
        const params = new URLSearchParams(window.location.search);
        const selected = opportunities.find(
            (item) => String(item.id) === params.get('opportunity'),
        );

        if (selected) {
            openEditor(selected);
        } else if (params.get('create') === '1') {
            openCreator(params.get('customer') || undefined);
        }
        // The query string is only an entry point; subsequent state is local.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    function openCreator(customerId?: string) {
        setEditing(null);
        form.clearErrors();
        form.setData({ ...emptyForm, person_id: customerId || '' });
        setOpen(true);
    }

    function openEditor(item: Opportunity) {
        setEditing(item);
        form.clearErrors();
        form.setData({
            person_id: String(item.person_id),
            installation_id: item.installation_id
                ? String(item.installation_id)
                : '',
            owner_user_id: item.owner_user_id ? String(item.owner_user_id) : '',
            title: item.title,
            stage: item.stage,
            probability: String(item.probability),
            estimated_value: item.estimated_value,
            expected_close_on: item.expected_close_on || '',
            source: item.source || '',
            lost_reason: item.lost_reason || '',
            notes: item.notes || '',
        });
        setOpen(true);
    }

    function submit(event: React.FormEvent) {
        event.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        };

        if (editing) {
            form.patch(`/opportunities/${editing.id}`, options);
        } else {
            form.post('/opportunities', options);
        }
    }

    function changeStage(item: Opportunity, stage: string) {
        const probability =
            stages.find((candidate) => candidate.value === stage)
                ?.probability ?? item.probability;

        if (stage === 'lost') {
            openEditor({ ...item, stage, probability });

            return;
        }

        router.patch(
            `/opportunities/${item.id}`,
            {
                person_id: item.person_id,
                installation_id: item.installation_id,
                owner_user_id: item.owner_user_id,
                title: item.title,
                stage,
                probability,
                estimated_value: item.estimated_value,
                expected_close_on: item.expected_close_on,
                source: item.source,
                lost_reason: null,
                notes: item.notes,
            },
            { preserveScroll: true },
        );
    }

    return (
        <>
            <Head title="Opportunities" />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <header className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold">Opportunities</h1>
                        <p className="text-sm text-muted-foreground">
                            Commercial pipeline and weighted sales forecast.
                        </p>
                    </div>
                    <Button onClick={() => openCreator()}>
                        <Plus /> New opportunity
                    </Button>
                </header>

                <section className="grid gap-px overflow-hidden border bg-border sm:grid-cols-2 xl:grid-cols-4">
                    <Metric
                        icon={CircleDollarSign}
                        label="Open pipeline"
                        value={money(metrics.pipeline_value)}
                    />
                    <Metric
                        icon={TrendingUp}
                        label="Weighted forecast"
                        value={money(metrics.weighted_forecast)}
                    />
                    <Metric
                        icon={Target}
                        label="Won"
                        value={money(metrics.won_value)}
                    />
                    <Metric
                        icon={CalendarClock}
                        label="Overdue closes"
                        value={String(metrics.overdue)}
                    />
                </section>

                <section className="overflow-x-auto pb-3">
                    <div className="grid min-w-[94rem] grid-cols-7 gap-3">
                        {stages.map((stage) => {
                            const items = opportunities.filter(
                                (item) => item.stage === stage.value,
                            );

                            return (
                                <div
                                    key={stage.value}
                                    className="min-w-0 border-t-2 pt-3"
                                >
                                    <div className="mb-3 flex items-center justify-between px-1">
                                        <div className="text-sm font-semibold">
                                            {stage.label}
                                        </div>
                                        <Badge variant="secondary">
                                            {items.length}
                                        </Badge>
                                    </div>
                                    <div className="grid content-start gap-2">
                                        {items.map((item) => (
                                            <article
                                                key={item.id}
                                                className="rounded-md border bg-card p-3 shadow-xs"
                                            >
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        openEditor(item)
                                                    }
                                                    className="w-full text-left"
                                                >
                                                    <span className="text-xs text-muted-foreground">
                                                        {item.code}
                                                    </span>
                                                    <span className="mt-1 block text-sm leading-snug font-medium">
                                                        {item.title}
                                                    </span>
                                                    {item.customer && (
                                                        <span className="mt-1 block truncate text-xs text-muted-foreground">
                                                            {customerName(
                                                                item.customer,
                                                            )}
                                                        </span>
                                                    )}
                                                </button>
                                                <div className="mt-3 flex items-end justify-between gap-2 border-t pt-2">
                                                    <div>
                                                        <div className="text-sm font-semibold">
                                                            {money(
                                                                Number(
                                                                    item.estimated_value,
                                                                ),
                                                            )}
                                                        </div>
                                                        <div className="text-xs text-muted-foreground">
                                                            {item.probability}%
                                                            ·{' '}
                                                            {date(
                                                                item.expected_close_on,
                                                            )}
                                                        </div>
                                                    </div>
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="icon"
                                                        className="size-8"
                                                        onClick={() =>
                                                            openEditor(item)
                                                        }
                                                        aria-label={`Edit ${item.title}`}
                                                    >
                                                        <Pencil className="size-3.5" />
                                                    </Button>
                                                </div>
                                                <Select
                                                    value={item.stage}
                                                    onValueChange={(value) =>
                                                        changeStage(item, value)
                                                    }
                                                >
                                                    <SelectTrigger className="mt-2 h-8 text-xs">
                                                        <SelectValue />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        {stages.map(
                                                            (option) => (
                                                                <SelectItem
                                                                    key={
                                                                        option.value
                                                                    }
                                                                    value={
                                                                        option.value
                                                                    }
                                                                >
                                                                    {
                                                                        option.label
                                                                    }
                                                                </SelectItem>
                                                            ),
                                                        )}
                                                    </SelectContent>
                                                </Select>
                                            </article>
                                        ))}
                                        {items.length === 0 && (
                                            <div className="rounded-md border border-dashed p-4 text-center text-xs text-muted-foreground">
                                                No opportunities
                                            </div>
                                        )}
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                </section>
            </div>

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                    <form onSubmit={submit} className="grid gap-5">
                        <DialogHeader>
                            <DialogTitle>
                                {editing
                                    ? 'Edit opportunity'
                                    : 'New opportunity'}
                            </DialogTitle>
                            <DialogDescription>
                                {editing?.code ||
                                    'Add a commercial lead to the pipeline.'}
                            </DialogDescription>
                        </DialogHeader>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field
                                label="Title"
                                error={form.errors.title}
                                className="sm:col-span-2"
                            >
                                <Input
                                    value={form.data.title}
                                    onChange={(event) =>
                                        form.setData(
                                            'title',
                                            event.target.value,
                                        )
                                    }
                                />
                            </Field>
                            <SelectField
                                label="Customer"
                                value={form.data.person_id}
                                error={form.errors.person_id}
                                onChange={(value) => {
                                    form.setData('person_id', value);
                                    form.setData('installation_id', '');
                                }}
                                options={customers.map((item) => [
                                    String(item.id),
                                    customerName(item),
                                ])}
                            />
                            <SelectField
                                label="Installation"
                                value={form.data.installation_id || 'none'}
                                error={form.errors.installation_id}
                                onChange={(value) =>
                                    form.setData(
                                        'installation_id',
                                        value === 'none' ? '' : value,
                                    )
                                }
                                options={[
                                    ['none', 'No installation'],
                                    ...availableInstallations.map((item) => [
                                        String(item.id),
                                        installationName(item),
                                    ]),
                                ]}
                            />
                            <SelectField
                                label="Stage"
                                value={form.data.stage}
                                error={form.errors.stage}
                                onChange={(value) => {
                                    form.setData('stage', value);
                                    form.setData(
                                        'probability',
                                        String(
                                            stages.find(
                                                (item) => item.value === value,
                                            )?.probability ?? 0,
                                        ),
                                    );
                                }}
                                options={stages.map((item) => [
                                    item.value,
                                    item.label,
                                ])}
                            />
                            <SelectField
                                label="Owner"
                                value={form.data.owner_user_id || 'none'}
                                error={form.errors.owner_user_id}
                                onChange={(value) =>
                                    form.setData(
                                        'owner_user_id',
                                        value === 'none' ? '' : value,
                                    )
                                }
                                options={[
                                    ['none', 'Unassigned'],
                                    ...users.map((item) => [
                                        String(item.id),
                                        item.name,
                                    ]),
                                ]}
                            />
                            <Field
                                label="Estimated value"
                                error={form.errors.estimated_value}
                            >
                                <Input
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    value={form.data.estimated_value}
                                    onChange={(event) =>
                                        form.setData(
                                            'estimated_value',
                                            event.target.value,
                                        )
                                    }
                                />
                            </Field>
                            <Field
                                label="Probability (%)"
                                error={form.errors.probability}
                            >
                                <Input
                                    type="number"
                                    min="0"
                                    max="100"
                                    value={form.data.probability}
                                    onChange={(event) =>
                                        form.setData(
                                            'probability',
                                            event.target.value,
                                        )
                                    }
                                />
                            </Field>
                            <Field
                                label="Expected close"
                                error={form.errors.expected_close_on}
                            >
                                <Input
                                    type="date"
                                    value={form.data.expected_close_on}
                                    onChange={(event) =>
                                        form.setData(
                                            'expected_close_on',
                                            event.target.value,
                                        )
                                    }
                                />
                            </Field>
                            <Field label="Source" error={form.errors.source}>
                                <Input
                                    value={form.data.source}
                                    placeholder="Referral, web, campaign..."
                                    onChange={(event) =>
                                        form.setData(
                                            'source',
                                            event.target.value,
                                        )
                                    }
                                />
                            </Field>
                            {form.data.stage === 'lost' && (
                                <Field
                                    label="Loss reason"
                                    error={form.errors.lost_reason}
                                    className="sm:col-span-2"
                                >
                                    <Textarea
                                        value={form.data.lost_reason}
                                        onChange={(event) =>
                                            form.setData(
                                                'lost_reason',
                                                event.target.value,
                                            )
                                        }
                                    />
                                </Field>
                            )}
                            <Field
                                label="Notes"
                                error={form.errors.notes}
                                className="sm:col-span-2"
                            >
                                <Textarea
                                    value={form.data.notes}
                                    onChange={(event) =>
                                        form.setData(
                                            'notes',
                                            event.target.value,
                                        )
                                    }
                                />
                            </Field>
                        </div>
                        <DialogFooter className="gap-2 sm:justify-between">
                            <div className="flex gap-2">
                                {editing && !editing.quote && (
                                    <Button
                                        type="button"
                                        variant="destructive"
                                        onClick={() =>
                                            router.delete(
                                                `/opportunities/${editing.id}`,
                                                {
                                                    onSuccess: () =>
                                                        setOpen(false),
                                                },
                                            )
                                        }
                                    >
                                        <Trash2 /> Delete
                                    </Button>
                                )}
                                {editing && (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        asChild
                                    >
                                        <Link
                                            href={
                                                editing.quote
                                                    ? `/quotes/${editing.quote.id}`
                                                    : `/quotes/create?opportunity=${editing.id}`
                                            }
                                        >
                                            {editing.quote
                                                ? `Open ${editing.quote.number}`
                                                : 'Prepare quote'}
                                        </Link>
                                    </Button>
                                )}
                            </div>
                            <div className="flex gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => setOpen(false)}
                                >
                                    Cancel
                                </Button>
                                <Button disabled={form.processing}>
                                    {editing
                                        ? 'Save changes'
                                        : 'Create opportunity'}
                                </Button>
                            </div>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}

function Field({
    label,
    error,
    className = '',
    children,
}: {
    label: string;
    error?: string;
    className?: string;
    children: React.ReactNode;
}) {
    return (
        <div className={`grid gap-2 ${className}`}>
            <Label>{label}</Label>
            {children}
            <InputError message={error} />
        </div>
    );
}

function SelectField({
    label,
    value,
    error,
    onChange,
    options,
}: {
    label: string;
    value: string;
    error?: string;
    onChange: (value: string) => void;
    options: string[][];
}) {
    return (
        <Field label={label} error={error}>
            <Select value={value} onValueChange={onChange}>
                <SelectTrigger>
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    {options.map(([key, text]) => (
                        <SelectItem key={key} value={key}>
                            {text}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
        </Field>
    );
}

function Metric({
    icon: Icon,
    label,
    value,
}: {
    icon: typeof Target;
    label: string;
    value: string;
}) {
    return (
        <div className="flex items-center gap-3 bg-background p-4">
            <Icon className="size-4 text-muted-foreground" />
            <div>
                <div className="text-xs text-muted-foreground">{label}</div>
                <div className="text-lg font-semibold">{value}</div>
            </div>
        </div>
    );
}

function customerName(customer: Customer) {
    return (
        customer.company_name ||
        `${customer.first_name} ${customer.last_name || ''}`.trim()
    );
}
function installationName(item: Installation) {
    return (
        item.project_reference || item.site_name || `Installation #${item.id}`
    );
}
function money(value: number) {
    return new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: 'EUR',
    }).format(value);
}
function date(value: string | null) {
    return value
        ? new Intl.DateTimeFormat(undefined, { dateStyle: 'short' }).format(
              new Date(`${value}T00:00:00`),
          )
        : 'No date';
}

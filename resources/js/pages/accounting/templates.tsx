import { Form, Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    CalendarClock,
    Play,
    Plus,
    RefreshCw,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import { PageHeader, PageShell } from '@/components/page-shell';
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
type Account = { code: string; name: string };
type Line = {
    account_code: string;
    side: 'debit' | 'credit';
    percentage: string;
};
type Template = {
    id: number;
    name: string;
    description: string | null;
    is_system: boolean;
    is_active: boolean;
    recurring_amount: string | null;
    recurrence: 'monthly' | 'quarterly' | 'yearly' | null;
    next_run_on: string | null;
    end_on: string | null;
    reversal_days: number | null;
    last_run_on: string | null;
    lines: (Line & { account: Account })[];
};
type RecurringRun = {
    id: number;
    scheduled_on: string;
    amount: string;
    reversal_due_on: string | null;
    status: string;
    failure_message: string | null;
    template: { name: string };
    journal_entry: { number: string } | null;
    reversal_entry: { number: string } | null;
};
export default function Templates({
    templates,
    accounts,
    recurringRuns,
}: {
    templates: Template[];
    accounts: Account[];
    recurringRuns: RecurringRun[];
}) {
    const page = usePage();
    const recurring =
        new URL(page.url, 'http://localhost').searchParams.get('tab') ===
        'recurring';
    const visibleTemplates = recurring
        ? templates.filter((template) => template.recurrence !== null)
        : templates;

    return (
        <>
            <Head title={recurring ? 'Periódicos' : 'Accounting templates'} />
            <PageShell mode="full">
                <PageHeader
                    title={<>{recurring ? 'Periódicos' : 'Entry templates'}</>}
                    description={
                        <>Post frequent balanced movements with fewer steps.</>
                    }
                    actions={
                        <>
                            <Button variant="outline" asChild>
                                <Link href="/accounting">
                                    <ArrowLeft /> Accounting
                                </Link>
                            </Button>
                            <Form
                                action="/accounting/recurring/run"
                                method="post"
                            >
                                {({ processing }) => (
                                    <Button
                                        variant="outline"
                                        disabled={processing}
                                    >
                                        <RefreshCw /> Process due entries
                                    </Button>
                                )}
                            </Form>
                            <TemplateDialog accounts={accounts} />
                        </>
                    }
                />
                <div className="divide-y border-y">
                    {visibleTemplates.map((t) => (
                        <section
                            key={t.id}
                            className="flex flex-wrap items-center justify-between gap-4 py-4"
                        >
                            <div>
                                <div className="flex items-center gap-2">
                                    <h2 className="font-medium">{t.name}</h2>
                                    {t.is_system && (
                                        <Badge variant="outline">System</Badge>
                                    )}
                                    {t.recurrence && (
                                        <Badge>
                                            {t.recurrence} ·{' '}
                                            {money(Number(t.recurring_amount))}
                                        </Badge>
                                    )}
                                </div>
                                <p className="text-sm text-muted-foreground">
                                    {t.description}
                                </p>
                                <p className="mt-1 text-xs">
                                    {t.lines
                                        .map(
                                            (l) =>
                                                `${l.account_code} ${l.side} ${Number(l.percentage)}%`,
                                        )
                                        .join(' · ')}
                                </p>
                                {t.recurrence && (
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        Next: {shortDate(t.next_run_on!)}
                                        {t.reversal_days
                                            ? ` · reverses after ${t.reversal_days} day${t.reversal_days === 1 ? '' : 's'}`
                                            : ''}
                                        {t.end_on
                                            ? ` · ends ${shortDate(t.end_on)}`
                                            : ''}
                                    </p>
                                )}
                            </div>
                            <div className="flex gap-1">
                                <ScheduleDialog template={t} />
                                <Execute template={t} />
                                {!t.is_system && (
                                    <Form
                                        action={`/accounting/templates/${t.id}`}
                                        method="delete"
                                    >
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            aria-label="Delete template"
                                        >
                                            <Trash2 />
                                        </Button>
                                    </Form>
                                )}
                            </div>
                        </section>
                    ))}
                </div>
                <RecurringHistory runs={recurringRuns} />
            </PageShell>
        </>
    );
}

function ScheduleDialog({ template }: { template: Template }) {
    const [open, setOpen] = useState(false);
    const [recurrence, setRecurrence] = useState<string>(
        template.recurrence ?? 'monthly',
    );

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="outline" title="Recurring schedule">
                    <CalendarClock /> Schedule
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Schedule {template.name}</DialogTitle>
                </DialogHeader>
                <Form
                    action={`/accounting/templates/${template.id}/schedule`}
                    method="patch"
                    className="grid gap-4"
                    onSuccess={() => setOpen(false)}
                >
                    {({ errors, processing }) => (
                        <>
                            <Field label="Frequency">
                                <Select
                                    name="recurrence"
                                    value={recurrence}
                                    onValueChange={setRecurrence}
                                >
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="monthly">
                                            Monthly
                                        </SelectItem>
                                        <SelectItem value="quarterly">
                                            Quarterly
                                        </SelectItem>
                                        <SelectItem value="yearly">
                                            Yearly
                                        </SelectItem>
                                        <SelectItem value="disabled">
                                            Disable schedule
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </Field>
                            {recurrence !== 'disabled' && (
                                <>
                                    <div className="grid gap-3 sm:grid-cols-2">
                                        <Field label="Amount">
                                            <Input
                                                name="recurring_amount"
                                                type="number"
                                                min="0.01"
                                                step="0.01"
                                                defaultValue={
                                                    template.recurring_amount ??
                                                    ''
                                                }
                                                required
                                            />
                                        </Field>
                                        <Field label="Next posting date">
                                            <Input
                                                name="next_run_on"
                                                type="date"
                                                defaultValue={dateInput(
                                                    template.next_run_on,
                                                )}
                                                required
                                            />
                                        </Field>
                                        <Field label="End date (optional)">
                                            <Input
                                                name="end_on"
                                                type="date"
                                                defaultValue={dateInput(
                                                    template.end_on,
                                                )}
                                            />
                                        </Field>
                                        <Field label="Reverse after days">
                                            <Input
                                                name="reversal_days"
                                                type="number"
                                                min="1"
                                                max="365"
                                                defaultValue={
                                                    template.reversal_days ?? ''
                                                }
                                                placeholder="Leave empty if permanent"
                                            />
                                        </Field>
                                    </div>
                                    <p className="text-xs text-muted-foreground">
                                        A reversal turns this schedule into an
                                        accrual: the original entry is cancelled
                                        automatically after the chosen number of
                                        days.
                                    </p>
                                </>
                            )}
                            {Object.keys(errors).length > 0 && (
                                <p className="text-sm text-destructive">
                                    {Object.values(errors)[0]}
                                </p>
                            )}
                            <Button disabled={processing}>Save schedule</Button>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function RecurringHistory({ runs }: { runs: RecurringRun[] }) {
    return (
        <section className="overflow-x-auto">
            <div className="mb-3">
                <h2 className="font-semibold">Recurring execution history</h2>
                <p className="text-sm text-muted-foreground">
                    Every scheduled posting and accrual reversal remains linked
                    to its journal entries.
                </p>
            </div>
            <table className="w-full min-w-[48rem] text-sm">
                <thead>
                    <tr className="border-b text-left text-muted-foreground">
                        <th className="py-2">Scheduled</th>
                        <th>Template</th>
                        <th className="text-right">Amount</th>
                        <th>Journal entry</th>
                        <th>Reversal</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    {runs.map((run) => (
                        <tr key={run.id} className="border-b">
                            <td className="py-2">
                                {shortDate(run.scheduled_on)}
                            </td>
                            <td>{run.template.name}</td>
                            <td className="text-right">
                                {money(Number(run.amount))}
                            </td>
                            <td className="font-mono text-xs">
                                {run.journal_entry?.number ?? '—'}
                            </td>
                            <td className="font-mono text-xs">
                                {run.reversal_entry?.number ??
                                    (run.reversal_due_on
                                        ? `Due ${shortDate(run.reversal_due_on)}`
                                        : '—')}
                            </td>
                            <td>
                                <Badge
                                    variant={
                                        run.status.includes('failed')
                                            ? 'destructive'
                                            : 'outline'
                                    }
                                    title={run.failure_message ?? undefined}
                                >
                                    {run.status.replace('_', ' ')}
                                </Badge>
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
            {runs.length === 0 && (
                <p className="border-b py-8 text-center text-sm text-muted-foreground">
                    No recurring entries have been processed yet.
                </p>
            )}
        </section>
    );
}
function Execute({ template }: { template: Template }) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button>
                    <Play /> Execute
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{template.name}</DialogTitle>
                </DialogHeader>
                <Form
                    action={`/accounting/templates/${template.id}/execute`}
                    method="post"
                    className="grid gap-4"
                    onSuccess={() => setOpen(false)}
                >
                    <Field label="Date">
                        <Input
                            name="entry_date"
                            type="date"
                            defaultValue={new Date().toISOString().slice(0, 10)}
                            required
                        />
                    </Field>
                    <Field label="Base amount">
                        <Input
                            name="amount"
                            type="number"
                            min="0.01"
                            step="0.01"
                            required
                        />
                    </Field>
                    <Field label="Description">
                        <Input
                            name="description"
                            defaultValue={template.name}
                            required
                        />
                    </Field>
                    <Button>Post journal entry</Button>
                </Form>
            </DialogContent>
        </Dialog>
    );
}
function TemplateDialog({ accounts }: { accounts: Account[] }) {
    const [open, setOpen] = useState(false);
    const [lines, setLines] = useState<Line[]>([
        { account_code: '', side: 'debit', percentage: '100' },
        { account_code: '', side: 'credit', percentage: '100' },
    ]);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button>
                    <Plus /> New template
                </Button>
            </DialogTrigger>
            <DialogContent className="max-w-3xl">
                <DialogHeader>
                    <DialogTitle>New accounting template</DialogTitle>
                </DialogHeader>
                <Form
                    action="/accounting/templates"
                    method="post"
                    className="grid gap-4"
                    onSuccess={() => setOpen(false)}
                >
                    <div className="grid gap-3 sm:grid-cols-2">
                        <Field label="Name">
                            <Input name="name" required />
                        </Field>
                        <Field label="Description">
                            <Input name="description" />
                        </Field>
                    </div>
                    <div className="grid gap-2">
                        {lines.map((line, i) => (
                            <div
                                key={i}
                                className="grid grid-cols-[1fr_8rem_7rem_2.5rem] gap-2"
                            >
                                <Select
                                    name={`lines[${i}][account_code]`}
                                    value={line.account_code}
                                    onValueChange={(v) =>
                                        setLines((x) =>
                                            x.map((l, j) =>
                                                j === i
                                                    ? { ...l, account_code: v }
                                                    : l,
                                            ),
                                        )
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Account" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {accounts.map((a) => (
                                            <SelectItem
                                                key={a.code}
                                                value={a.code}
                                            >
                                                {a.code} · {a.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <Select
                                    name={`lines[${i}][side]`}
                                    value={line.side}
                                    onValueChange={(v: 'debit' | 'credit') =>
                                        setLines((x) =>
                                            x.map((l, j) =>
                                                j === i ? { ...l, side: v } : l,
                                            ),
                                        )
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="debit">
                                            Debit
                                        </SelectItem>
                                        <SelectItem value="credit">
                                            Credit
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <Input
                                    name={`lines[${i}][percentage]`}
                                    type="number"
                                    step="0.0001"
                                    value={line.percentage}
                                    onChange={(e) =>
                                        setLines((x) =>
                                            x.map((l, j) =>
                                                j === i
                                                    ? {
                                                          ...l,
                                                          percentage:
                                                              e.target.value,
                                                      }
                                                    : l,
                                            ),
                                        )
                                    }
                                />
                                <Button
                                    type="button"
                                    size="icon"
                                    variant="ghost"
                                    onClick={() =>
                                        setLines((x) =>
                                            x.filter((_, j) => j !== i),
                                        )
                                    }
                                >
                                    <Trash2 />
                                </Button>
                            </div>
                        ))}
                    </div>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() =>
                            setLines((x) => [
                                ...x,
                                {
                                    account_code: '',
                                    side: 'debit',
                                    percentage: '100',
                                },
                            ])
                        }
                    >
                        <Plus /> Add line
                    </Button>
                    <Button>Create template</Button>
                </Form>
            </DialogContent>
        </Dialog>
    );
}
function Field({
    label,
    children,
}: {
    label: string;
    children: React.ReactNode;
}) {
    return (
        <div className="grid gap-1">
            <Label>{label}</Label>
            {children}
        </div>
    );
}
function dateInput(value: string | null) {
    return value?.slice(0, 10) ?? '';
}
function shortDate(value: string) {
    return new Intl.DateTimeFormat(undefined, { dateStyle: 'short' }).format(
        new Date(`${value.slice(0, 10)}T00:00:00`),
    );
}
function money(value: number) {
    return new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: 'EUR',
    }).format(value);
}
Templates.layout = {
    breadcrumbs: [
        { title: 'Finance', href: '/finance' },
        { title: 'Accounting', href: '/accounting' },
        { title: 'Entry templates', href: '/accounting/templates' },
    ],
};

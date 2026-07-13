import { Form, Head, Link } from '@inertiajs/react';
import {
    CalendarDays,
    Check,
    ClipboardList,
    FileText,
    Plus,
    Trash2,
} from 'lucide-react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

type InstallationOption = {
    id: number;
    project_reference: string | null;
    site_name: string | null;
    status: string;
};
type UserOption = { id: number; name: string; role: string };
type Activity = {
    id: number;
    type: string;
    title: string;
    notes: string | null;
    priority: string;
    status: string;
    starts_at: string | null;
    due_at: string | null;
    installation: InstallationOption | null;
    person: { id: number; first_name: string; last_name: string | null } | null;
    assigned_user: UserOption | null;
};
type Quote = {
    id: number;
    number: string;
    status: string;
    amount: string;
    valid_until: string | null;
    installation: InstallationOption | null;
    customer: {
        id: number;
        first_name: string;
        last_name: string | null;
        company_name: string | null;
    } | null;
};

export default function OperationsIndex({
    activities,
    quotes,
    installations,
    users,
    canManageQuotes,
}: {
    activities: Activity[];
    quotes: Quote[];
    installations: InstallationOption[];
    users: UserOption[];
    canManageQuotes: boolean;
}) {
    return (
        <>
            <Head title="Operations" />
            <div className="flex flex-1 flex-col gap-8 p-4">
                <header className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold">Operations</h1>
                        <p className="text-sm text-muted-foreground">
                            Tasks, visits, reminders and quotes.
                        </p>
                    </div>
                </header>

                <section className="overflow-x-auto border-t pt-5">
                    <h2 className="mb-3 font-semibold">
                        Installation pipeline
                    </h2>
                    <div className="grid min-w-[52rem] grid-cols-8 border-y">
                        {[
                            'lead',
                            'survey_booked',
                            'quoted',
                            'approved',
                            'scheduled',
                            'in_progress',
                            'completed',
                            'cancelled',
                        ].map((status) => (
                            <div
                                key={status}
                                className="border-r p-3 last:border-r-0"
                            >
                                <div className="text-xs text-muted-foreground">
                                    {status.replaceAll('_', ' ')}
                                </div>
                                <div className="mt-1 text-xl font-semibold">
                                    {
                                        installations.filter(
                                            (installation) =>
                                                installation.status === status,
                                        ).length
                                    }
                                </div>
                            </div>
                        ))}
                    </div>
                </section>

                <section className="grid gap-6 xl:grid-cols-[minmax(20rem,0.7fr)_minmax(0,1.3fr)]">
                    <Form
                        action="/operations/activities"
                        method="post"
                        resetOnSuccess
                        className="grid content-start gap-4 border-t pt-5"
                    >
                        {({ errors, processing }) => (
                            <>
                                <div className="flex items-center gap-2">
                                    <Plus className="size-4" />
                                    <h2 className="font-semibold">
                                        New activity
                                    </h2>
                                </div>
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <Field
                                        label="Title"
                                        name="title"
                                        error={errors.title}
                                    />
                                    <SelectField
                                        label="Type"
                                        name="type"
                                        defaultValue="task"
                                        options={[
                                            ['task', 'Task'],
                                            ['visit', 'Visit'],
                                            ['reminder', 'Reminder'],
                                            ['call', 'Call'],
                                            ['email', 'Email'],
                                            ['meeting', 'Meeting'],
                                        ]}
                                    />
                                    <SelectField
                                        label="Priority"
                                        name="priority"
                                        defaultValue="normal"
                                        options={[
                                            ['low', 'Low'],
                                            ['normal', 'Normal'],
                                            ['high', 'High'],
                                        ]}
                                    />
                                    <SelectField
                                        label="Installation"
                                        name="installation_id"
                                        options={installations.map((item) => [
                                            String(item.id),
                                            item.project_reference ||
                                                item.site_name ||
                                                `#${item.id}`,
                                        ])}
                                    />
                                    <SelectField
                                        label="Assigned to"
                                        name="assigned_user_id"
                                        options={users.map((user) => [
                                            String(user.id),
                                            user.name,
                                        ])}
                                    />
                                    <Field
                                        label="Starts"
                                        name="starts_at"
                                        type="datetime-local"
                                        error={errors.starts_at}
                                    />
                                    <Field
                                        label="Due"
                                        name="due_at"
                                        type="datetime-local"
                                        error={errors.due_at}
                                    />
                                </div>
                                <Button disabled={processing}>
                                    <ClipboardList /> Create activity
                                </Button>
                            </>
                        )}
                    </Form>

                    <div className="overflow-x-auto border-t pt-5">
                        <div className="mb-3 flex items-center gap-2">
                            <CalendarDays className="size-4" />
                            <h2 className="font-semibold">Agenda</h2>
                        </div>
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b text-left text-muted-foreground">
                                    <th className="py-2">Activity</th>
                                    <th>When</th>
                                    <th>Owner</th>
                                    <th>Status</th>
                                    <th className="w-20" />
                                </tr>
                            </thead>
                            <tbody>
                                {activities.map((activity) => (
                                    <tr
                                        key={activity.id}
                                        className="border-b align-top"
                                    >
                                        <td className="py-3">
                                            <div className="font-medium">
                                                {activity.title}
                                            </div>
                                            <div className="text-xs text-muted-foreground">
                                                {activity.type} ·{' '}
                                                {activity.installation
                                                    ?.project_reference ||
                                                    activity.installation
                                                        ?.site_name ||
                                                    (activity.person
                                                        ? `${activity.person.first_name} ${activity.person.last_name || ''}`.trim()
                                                        : null) ||
                                                    'General'}
                                            </div>
                                        </td>
                                        <td className="py-3">
                                            {formatDate(
                                                activity.starts_at ||
                                                    activity.due_at,
                                            )}
                                        </td>
                                        <td className="py-3">
                                            {activity.assigned_user?.name ||
                                                'Unassigned'}
                                        </td>
                                        <td className="py-3">
                                            <Badge variant="outline">
                                                {activity.status}
                                            </Badge>
                                        </td>
                                        <td className="py-2">
                                            <div className="flex gap-1">
                                                {activity.status !==
                                                'completed' ? (
                                                    <Form
                                                        action={`/operations/activities/${activity.id}`}
                                                        method="patch"
                                                    >
                                                        <input
                                                            type="hidden"
                                                            name="status"
                                                            value="completed"
                                                        />
                                                        <Button
                                                            size="icon"
                                                            variant="ghost"
                                                            title="Complete"
                                                        >
                                                            <Check />
                                                        </Button>
                                                    </Form>
                                                ) : null}
                                                <Form
                                                    action={`/operations/activities/${activity.id}`}
                                                    method="delete"
                                                >
                                                    <Button
                                                        size="icon"
                                                        variant="ghost"
                                                        title="Delete"
                                                    >
                                                        <Trash2 />
                                                    </Button>
                                                </Form>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </section>

                {canManageQuotes && (
                    <section className="border-t pt-5">
                        <div className="mb-3 flex items-center justify-between gap-3">
                            <div>
                                <h2 className="font-semibold">Quotes</h2>
                                <p className="text-sm text-muted-foreground">
                                    Commercial offers with itemized pricing and
                                    tax.
                                </p>
                            </div>
                            {canManageQuotes && (
                                <Button asChild>
                                    <Link href="/quotes/create">
                                        <FileText /> New quote
                                    </Link>
                                </Button>
                            )}
                        </div>
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b text-left text-muted-foreground">
                                        <th className="py-2">Number</th>
                                        <th>Installation</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th />
                                    </tr>
                                </thead>
                                <tbody>
                                    {quotes.map((quote) => (
                                        <tr key={quote.id} className="border-b">
                                            <td className="py-3 font-medium">
                                                <Link
                                                    className="underline underline-offset-2"
                                                    href={`/quotes/${quote.id}`}
                                                >
                                                    {quote.number}
                                                </Link>
                                            </td>
                                            <td>
                                                {quote.installation ? (
                                                    <Link
                                                        className="underline underline-offset-2"
                                                        href={`/installations/${quote.installation.id}`}
                                                    >
                                                        {quote.installation
                                                            .project_reference ||
                                                            quote.installation
                                                                .site_name}
                                                    </Link>
                                                ) : (
                                                    quote.customer
                                                        ?.company_name ||
                                                    `${quote.customer?.first_name || ''} ${quote.customer?.last_name || ''}`.trim() ||
                                                    '—'
                                                )}
                                            </td>
                                            <td>
                                                {Number(
                                                    quote.amount,
                                                ).toLocaleString('es-ES', {
                                                    style: 'currency',
                                                    currency: 'EUR',
                                                })}
                                            </td>
                                            <td>
                                                <Badge variant="outline">
                                                    {quote.status}
                                                </Badge>
                                            </td>
                                            <td>
                                                <Form
                                                    action={`/quotes/${quote.id}/status`}
                                                    method="patch"
                                                >
                                                    <input
                                                        type="hidden"
                                                        name="status"
                                                        value="accepted"
                                                    />
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        disabled={
                                                            quote.status ===
                                                            'accepted'
                                                        }
                                                    >
                                                        Accept
                                                    </Button>
                                                </Form>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </section>
                )}
            </div>
        </>
    );
}

function Field({
    label,
    name,
    type = 'text',
    error,
}: {
    label: string;
    name: string;
    type?: string;
    error?: string;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={name}>{label}</Label>
            <Input id={name} name={name} type={type} />
            <InputError message={error} />
        </div>
    );
}
function SelectField({
    label,
    name,
    options,
    defaultValue,
}: {
    label: string;
    name: string;
    options: string[][];
    defaultValue?: string;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={name}>{label}</Label>
            <Select name={name} defaultValue={defaultValue}>
                <SelectTrigger id={name} className="w-full">
                    <SelectValue
                        placeholder={`Select ${label.toLowerCase()}`}
                    />
                </SelectTrigger>
                <SelectContent>
                    {options.map(([value, text]) => (
                        <SelectItem key={value} value={value}>
                            {text}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
        </div>
    );
}
function formatDate(value: string | null) {
    return value
        ? new Intl.DateTimeFormat('es-ES', {
              dateStyle: 'short',
              timeStyle: 'short',
          }).format(new Date(value))
        : 'No date';
}

OperationsIndex.layout = {
    breadcrumbs: [{ title: 'Operations', href: '/operations' }],
};

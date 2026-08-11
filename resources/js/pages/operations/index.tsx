import { Form, Head, Link, router, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowLeft,
    ArrowRight,
    CalendarDays,
    Check,
    CircleDashed,
    ClipboardList,
    Clock3,
    ListFilter,
    LockKeyhole,
    Pencil,
    Plus,
    Search,
    Settings2,
    Trash2,
    UserRoundX,
    Wrench,
} from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import InputError from '@/components/input-error';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';

type InstallationOption = {
    id: number;
    project_reference: string | null;
    site_name: string | null;
    status: string;
};
type UserOption = {
    id: number;
    name: string;
    role: string;
    field_service_profile?: {
        daily_capacity_minutes: number;
        service_zones: string[];
        skills: string[];
    } | null;
};
type WorkOrderLink = { id: number; number: string; status: string };
type Activity = {
    id: number;
    type: string;
    title: string;
    notes: string | null;
    priority: string;
    status: string;
    blocked_reason: string | null;
    starts_at: string | null;
    due_at: string | null;
    installation: InstallationOption | null;
    person: { id: number; first_name: string; last_name: string | null } | null;
    assigned_user: UserOption | null;
    work_order: WorkOrderLink | null;
};
type WorkOrder = {
    id: number;
    number: string;
    title: string;
    status: string;
    kind: string;
    scheduled_start: string | null;
    scheduled_end: string | null;
    has_schedule_conflict: boolean;
    planning_issues: { type: string; message: string }[];
    installation: InstallationOption;
    assigned_user: UserOption | null;
};
type PaginationLink = { url: string | null; label: string; active: boolean };
type PaginatedActivities = {
    data: Activity[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    links: PaginationLink[];
};
type Filters = {
    q?: string;
    status: string;
    priority?: string;
    type?: string;
    owner?: number;
    view: 'today' | 'board' | 'week' | 'month';
    week: string;
    focus?: number;
};
type Metrics = {
    overdue: number;
    today: number;
    blocked: number;
    unassigned: number;
    unscheduled: number;
    ready_to_plan: number;
};
type OperationalException = {
    id: string;
    kind: 'incident' | 'maintenance' | 'materials';
    title: string;
    detail: string;
    href: string;
    severity: 'critical' | 'warning';
};

const statuses = [
    ['pending', 'Pending'],
    ['in_progress', 'In progress'],
    ['blocked', 'Blocked'],
    ['completed', 'Completed'],
    ['cancelled', 'Cancelled'],
];
const types = [
    ['task', 'Task'],
    ['visit', 'Visit'],
    ['reminder', 'Reminder'],
    ['call', 'Call'],
    ['email', 'Email'],
    ['meeting', 'Meeting'],
];

export default function OperationsIndex({
    activities,
    boardActivities,
    planningOrders,
    weekActivities,
    metrics,
    exceptions,
    installations,
    users,
    filters,
    canManage,
    currentUserId,
}: {
    activities: PaginatedActivities;
    boardActivities: Activity[];
    planningOrders: WorkOrder[];
    weekActivities: Activity[];
    metrics: Metrics;
    exceptions: OperationalException[];
    installations: InstallationOption[];
    users: UserOption[];
    filters: Filters;
    canManage: boolean;
    currentUserId: number;
}) {
    const focusedId = Number(
        new URLSearchParams(usePage().url.split('?')[1] || '').get('focus'),
    );
    const focusRef = useRef<HTMLTableRowElement>(null);
    useEffect(() => {
        if (focusedId && focusRef.current) {
            focusRef.current.scrollIntoView({
                behavior: 'smooth',
                block: 'center',
            });
            focusRef.current.focus();
        }
    }, [focusedId]);

    return (
        <>
            <Head title="Operations" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <header className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold">Operations</h1>
                        <p className="text-sm text-muted-foreground">
                            Daily execution, workload and operational
                            exceptions.
                        </p>
                    </div>
                    <div className="flex gap-2">
                        {canManage && (
                            <Button asChild variant="outline">
                                <Link href="/operations/field-service">
                                    <Settings2 /> Field service
                                </Link>
                            </Button>
                        )}
                        <ActivityDialog
                            installations={installations}
                            users={users}
                            canManage={canManage}
                            currentUserId={currentUserId}
                        />
                    </div>
                </header>

                <section className="grid gap-px overflow-hidden rounded-lg border bg-border sm:grid-cols-3 xl:grid-cols-6">
                    <Metric
                        label="Due today"
                        value={metrics.today}
                        icon={Clock3}
                    />
                    <Metric
                        label="Overdue"
                        value={metrics.overdue}
                        attention
                        icon={AlertTriangle}
                    />
                    <Metric
                        label="Blocked"
                        value={metrics.blocked}
                        attention
                        icon={LockKeyhole}
                    />
                    <Metric
                        label="Unassigned"
                        value={metrics.unassigned}
                        attention
                        icon={UserRoundX}
                    />
                    <Metric
                        label="Unscheduled orders"
                        value={metrics.unscheduled}
                        icon={CalendarDays}
                    />
                    <Metric
                        label="Ready to plan"
                        value={metrics.ready_to_plan}
                        icon={ClipboardList}
                    />
                </section>

                {exceptions.length > 0 && (
                    <OperationalExceptions items={exceptions} />
                )}

                <nav
                    className="flex flex-wrap items-center gap-1 border-b"
                    aria-label="Operations views"
                >
                    {[
                        ['today', 'Today'],
                        ['board', 'Board'],
                        ['week', 'Week'],
                        ['month', 'Month'],
                    ].map(([value, label]) => (
                        <Button
                            key={value}
                            asChild
                            variant="ghost"
                            className={`rounded-none border-b-2 ${filters.view === value ? 'border-primary' : 'border-transparent text-muted-foreground'}`}
                        >
                            <Link
                                href={operationsUrl({
                                    ...filters,
                                    view: value,
                                    page: undefined,
                                })}
                                preserveScroll
                            >
                                {label}
                            </Link>
                        </Button>
                    ))}
                </nav>

                <FiltersBar filters={filters} users={users} />

                {filters.view === 'today' && (
                    <Agenda
                        activities={activities}
                        users={users}
                        installations={installations}
                        canManage={canManage}
                        currentUserId={currentUserId}
                        focusedId={focusedId}
                        focusRef={focusRef}
                    />
                )}
                {filters.view === 'board' && (
                    <Board activities={boardActivities} canManage={canManage} />
                )}
                {filters.view === 'week' && (
                    <Week
                        week={filters.week}
                        filters={filters}
                        activities={weekActivities}
                        orders={planningOrders}
                    />
                )}
                {filters.view === 'month' && (
                    <Month
                        month={filters.week}
                        filters={filters}
                        activities={weekActivities}
                        orders={planningOrders}
                    />
                )}
            </div>
        </>
    );
}

function OperationalExceptions({ items }: { items: OperationalException[] }) {
    const icons = {
        incident: AlertTriangle,
        maintenance: Wrench,
        materials: ClipboardList,
    };

    return (
        <section className="rounded-lg border border-amber-300 bg-amber-50/40 dark:border-amber-900 dark:bg-amber-950/10">
            <div className="border-b border-amber-200 px-4 py-3 dark:border-amber-900">
                <h2 className="font-semibold">Needs attention</h2>
                <p className="text-xs text-muted-foreground">
                    Incidents, maintenance and material readiness affecting
                    execution.
                </p>
            </div>
            <div className="grid gap-px bg-amber-200 sm:grid-cols-2 xl:grid-cols-3 dark:bg-amber-900">
                {items.map((item) => {
                    const Icon = icons[item.kind];

                    return (
                        <Link
                            key={item.id}
                            href={item.href}
                            className="flex gap-3 bg-background p-3 hover:bg-muted/70"
                        >
                            <Icon
                                className={`mt-0.5 size-4 shrink-0 ${item.severity === 'critical' ? 'text-destructive' : 'text-amber-600'}`}
                            />
                            <div className="min-w-0">
                                <div className="truncate text-sm font-medium">
                                    {item.title}
                                </div>
                                <div className="truncate text-xs text-muted-foreground">
                                    {item.detail}
                                </div>
                            </div>
                        </Link>
                    );
                })}
            </div>
        </section>
    );
}

function Metric({
    label,
    value,
    attention = false,
    icon: Icon,
}: {
    label: string;
    value: number;
    attention?: boolean;
    icon: typeof Clock3;
}) {
    return (
        <div className="bg-background p-4">
            <div className="flex items-center gap-2 text-xs text-muted-foreground">
                <Icon className="size-3.5" />
                {label}
            </div>
            <div
                className={`mt-1 text-2xl font-semibold ${attention && value > 0 ? 'text-destructive' : ''}`}
            >
                {value}
            </div>
        </div>
    );
}

function FiltersBar({
    filters,
    users,
}: {
    filters: Filters;
    users: UserOption[];
}) {
    return (
        <Form
            method="get"
            action="/operations"
            className="grid gap-3 rounded-lg border bg-muted/20 p-3 lg:grid-cols-[minmax(12rem,1fr)_repeat(4,minmax(9rem,.35fr))_auto]"
        >
            <input type="hidden" name="view" value={filters.view} />
            <input type="hidden" name="week" value={filters.week} />
            <div className="relative">
                <Search className="absolute top-2.5 left-3 size-4 text-muted-foreground" />
                <Input
                    name="q"
                    defaultValue={filters.q || ''}
                    placeholder="Search title or notes"
                    className="pl-9"
                />
            </div>
            <NativeSelect
                name="status"
                defaultValue={filters.status}
                options={[
                    ['active', 'Active'],
                    ...statuses,
                    ['all', 'All statuses'],
                ]}
            />
            <NativeSelect
                name="priority"
                defaultValue={filters.priority || ''}
                options={[
                    ['', 'All priorities'],
                    ['low', 'Low'],
                    ['normal', 'Normal'],
                    ['high', 'High'],
                ]}
            />
            <NativeSelect
                name="type"
                defaultValue={filters.type || ''}
                options={[['', 'All types'], ...types]}
            />
            <NativeSelect
                name="owner"
                defaultValue={filters.owner ? String(filters.owner) : ''}
                options={[
                    ['', 'All owners'],
                    ...users.map((user) => [String(user.id), user.name]),
                ]}
            />
            <Button type="submit" variant="outline">
                <ListFilter /> Apply
            </Button>
        </Form>
    );
}

function Agenda({
    activities,
    users,
    installations,
    canManage,
    currentUserId,
    focusedId,
    focusRef,
}: {
    activities: PaginatedActivities;
    users: UserOption[];
    installations: InstallationOption[];
    canManage: boolean;
    currentUserId: number;
    focusedId: number;
    focusRef: React.RefObject<HTMLTableRowElement | null>;
}) {
    return (
        <section className="overflow-hidden rounded-lg border">
            <div className="flex items-center justify-between border-b px-4 py-3">
                <div>
                    <h2 className="font-semibold">Activity agenda</h2>
                    <p className="text-xs text-muted-foreground">
                        {activities.total} matching activities
                    </p>
                </div>
            </div>
            {activities.data.length === 0 ? (
                <EmptyState />
            ) : (
                <div className="overflow-x-auto">
                    <table className="w-full min-w-[58rem] text-sm">
                        <thead>
                            <tr className="border-b bg-muted/30 text-left text-xs text-muted-foreground">
                                <th className="px-4 py-2">Activity</th>
                                <th>Schedule</th>
                                <th>Owner</th>
                                <th>Priority</th>
                                <th>Status</th>
                                <th className="w-28" />
                            </tr>
                        </thead>
                        <tbody>
                            {activities.data.map((activity) => (
                                <ActivityRow
                                    key={activity.id}
                                    activity={activity}
                                    users={users}
                                    installations={installations}
                                    canManage={canManage}
                                    currentUserId={currentUserId}
                                    focused={focusedId === activity.id}
                                    focusRef={
                                        focusedId === activity.id
                                            ? focusRef
                                            : undefined
                                    }
                                />
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
            <Pagination pagination={activities} />
        </section>
    );
}

function ActivityRow({
    activity,
    users,
    installations,
    canManage,
    currentUserId,
    focused,
    focusRef,
}: {
    activity: Activity;
    users: UserOption[];
    installations: InstallationOption[];
    canManage: boolean;
    currentUserId: number;
    focused: boolean;
    focusRef?: React.RefObject<HTMLTableRowElement | null>;
}) {
    const editable = canManage || activity.assigned_user?.id === currentUserId;

    return (
        <tr
            ref={focusRef}
            tabIndex={focused ? -1 : undefined}
            className={`border-b align-top outline-none last:border-0 ${focused ? 'bg-primary/10 ring-2 ring-primary/40 ring-inset' : ''}`}
        >
            <td className="px-4 py-3">
                <div className="flex items-center gap-2">
                    <span className="font-medium">{activity.title}</span>
                    {activity.work_order && (
                        <Badge variant="secondary">
                            {activity.work_order.number}
                        </Badge>
                    )}
                </div>
                <div className="mt-0.5 text-xs text-muted-foreground">
                    {labelFor(types, activity.type)} ·{' '}
                    {activity.installation?.project_reference ||
                        activity.installation?.site_name ||
                        personName(activity) ||
                        'General'}
                </div>
                {activity.blocked_reason && (
                    <div className="mt-1 max-w-xl text-xs text-destructive">
                        Blocked: {activity.blocked_reason}
                    </div>
                )}
            </td>
            <td className="py-3">
                <div>{formatDate(activity.starts_at || activity.due_at)}</div>
                {activity.starts_at && activity.due_at && (
                    <div className="text-xs text-muted-foreground">
                        until {formatTime(activity.due_at)}
                    </div>
                )}
            </td>
            <td className="py-3">
                {activity.assigned_user?.name || (
                    <span className="text-destructive">Unassigned</span>
                )}
            </td>
            <td className="py-3">
                <PriorityBadge priority={activity.priority} />
            </td>
            <td className="py-3">
                <StatusBadge status={activity.status} />
            </td>
            <td className="py-2 pr-3">
                <div className="flex justify-end gap-1">
                    {activity.work_order ? (
                        <Button
                            size="icon"
                            variant="ghost"
                            asChild
                            title="Open work order"
                        >
                            <Link
                                href={`/work-orders?work_order=${activity.work_order.id}`}
                            >
                                <Wrench />
                            </Link>
                        </Button>
                    ) : editable ? (
                        <ActivityDialog
                            activity={activity}
                            installations={installations}
                            users={users}
                            canManage={canManage}
                            currentUserId={currentUserId}
                        />
                    ) : null}
                    {!activity.work_order &&
                        activity.status !== 'completed' &&
                        editable && (
                            <StatusAction
                                activity={activity}
                                status="completed"
                                icon={Check}
                                label="Complete"
                            />
                        )}
                    {canManage && !activity.work_order && (
                        <Form
                            action={`/operations/activities/${activity.id}`}
                            method="delete"
                            onSubmit={(event) => {
                                if (
                                    !window.confirm(
                                        'Delete this activity permanently?',
                                    )
                                ) {
                                    event.preventDefault();
                                }
                            }}
                        >
                            <Button
                                size="icon"
                                variant="ghost"
                                title="Delete"
                                className="text-destructive"
                            >
                                <Trash2 />
                            </Button>
                        </Form>
                    )}
                </div>
            </td>
        </tr>
    );
}

function Board({
    activities,
    canManage,
}: {
    activities: Activity[];
    canManage: boolean;
}) {
    const columns = [
        ['pending', 'Pending'],
        ['in_progress', 'In progress'],
        ['blocked', 'Blocked'],
    ];

    return (
        <section className="grid gap-4 xl:grid-cols-3">
            {columns.map(([status, label]) => {
                const items = activities.filter(
                    (activity) => activity.status === status,
                );

                return (
                    <div key={status} className="rounded-lg border bg-muted/20">
                        <div className="flex items-center justify-between border-b px-4 py-3">
                            <h2 className="font-semibold">{label}</h2>
                            <Badge variant="secondary">{items.length}</Badge>
                        </div>
                        <div className="grid gap-2 p-3">
                            {items.length === 0 && (
                                <p className="py-8 text-center text-sm text-muted-foreground">
                                    Nothing here
                                </p>
                            )}
                            {items.map((activity) => (
                                <ActivityCard
                                    key={activity.id}
                                    activity={activity}
                                    canManage={canManage}
                                />
                            ))}
                        </div>
                    </div>
                );
            })}
        </section>
    );
}

function ActivityCard({
    activity,
    canManage,
}: {
    activity: Activity;
    canManage: boolean;
}) {
    return (
        <article className="rounded-md border bg-background p-3 shadow-xs">
            <div className="flex items-start justify-between gap-2">
                <div className="font-medium">{activity.title}</div>
                <PriorityBadge priority={activity.priority} />
            </div>
            <div className="mt-1 text-xs text-muted-foreground">
                {activity.installation?.project_reference ||
                    activity.installation?.site_name ||
                    'General'}{' '}
                · {activity.assigned_user?.name || 'Unassigned'}
            </div>
            <div
                className={`mt-2 text-xs ${isOverdue(activity.due_at) ? 'font-medium text-destructive' : 'text-muted-foreground'}`}
            >
                {formatDate(activity.starts_at || activity.due_at)}
            </div>
            {activity.blocked_reason && (
                <p className="mt-2 text-xs text-destructive">
                    {activity.blocked_reason}
                </p>
            )}
            <div className="mt-3 flex flex-wrap gap-1">
                {activity.work_order ? (
                    <Button size="sm" variant="outline" asChild>
                        <Link
                            href={`/work-orders?work_order=${activity.work_order.id}`}
                        >
                            <Wrench /> {activity.work_order.number}
                        </Link>
                    </Button>
                ) : (
                    <>
                        {activity.status !== 'pending' && (
                            <StatusAction
                                activity={activity}
                                status="pending"
                                label="Pending"
                            />
                        )}
                        {activity.status !== 'in_progress' && (
                            <StatusAction
                                activity={activity}
                                status="in_progress"
                                label="Start"
                            />
                        )}
                        {activity.status !== 'blocked' && (
                            <StatusAction
                                activity={activity}
                                status="blocked"
                                label="Block"
                                needsReason
                            />
                        )}
                        {canManage && activity.installation && (
                            <ConvertDialog activity={activity} />
                        )}
                    </>
                )}
            </div>
        </article>
    );
}

function Week({
    week,
    filters,
    activities,
    orders,
}: {
    week: string;
    filters: Filters;
    activities: Activity[];
    orders: WorkOrder[];
}) {
    const days = useMemo(
        () => Array.from({ length: 7 }, (_, index) => addDays(week, index)),
        [week],
    );

    return (
        <section className="overflow-hidden rounded-lg border">
            <div className="flex flex-wrap items-center justify-between gap-3 border-b px-4 py-3">
                <div>
                    <h2 className="font-semibold">Weekly planning</h2>
                    <p className="text-xs text-muted-foreground">
                        Activities and work orders by scheduled start
                    </p>
                </div>
                <div className="flex gap-1">
                    <Button asChild size="sm" variant="outline">
                        <Link
                            href={operationsUrl({
                                ...filters,
                                week: addDays(week, -7),
                                view: 'week',
                            })}
                        >
                            <ArrowLeft /> Previous
                        </Link>
                    </Button>
                    <Button asChild size="sm" variant="outline">
                        <Link
                            href={operationsUrl({
                                ...filters,
                                week: addDays(week, 7),
                                view: 'week',
                            })}
                        >
                            Next <ArrowRight />
                        </Link>
                    </Button>
                </div>
            </div>
            <div className="grid min-w-[70rem] grid-cols-7 divide-x overflow-x-auto">
                {days.map((day) => {
                    const dayActivities = activities.filter(
                        (activity) =>
                            dateKey(activity.starts_at || activity.due_at) ===
                            day,
                    );
                    const dayOrders = orders.filter(
                        (order) => dateKey(order.scheduled_start) === day,
                    );

                    return (
                        <div
                            key={day}
                            className="min-h-80 p-2"
                            onDragOver={(event) => event.preventDefault()}
                            onDrop={(event) => dropOrder(event, day)}
                        >
                            <div className="mb-2 border-b pb-2 text-sm font-medium">
                                {new Intl.DateTimeFormat('en', {
                                    weekday: 'short',
                                    day: 'numeric',
                                    month: 'short',
                                }).format(new Date(`${day}T12:00:00`))}
                            </div>
                            <div className="grid gap-2">
                                {dayOrders.map((order) => (
                                    <Link
                                        key={`order-${order.id}`}
                                        draggable
                                        onDragStart={(event) =>
                                            event.dataTransfer.setData(
                                                'application/x-work-order',
                                                JSON.stringify(order),
                                            )
                                        }
                                        href={`/work-orders?work_order=${order.id}`}
                                        className={`rounded border p-2 text-xs hover:bg-muted ${order.has_schedule_conflict ? 'border-destructive bg-destructive/5' : 'border-blue-300 bg-blue-50 dark:bg-blue-950/20'}`}
                                    >
                                        <div className="font-medium">
                                            {order.number}
                                        </div>
                                        <div>
                                            {formatTime(order.scheduled_start)}{' '}
                                            · {order.title}
                                        </div>
                                        <div className="mt-1 text-muted-foreground">
                                            {order.assigned_user?.name ||
                                                'Unassigned'}
                                        </div>
                                        {order.planning_issues?.map((issue) => (
                                            <div
                                                key={`${issue.type}-${issue.message}`}
                                                className="mt-1 text-destructive"
                                            >
                                                {issue.message}
                                            </div>
                                        ))}
                                    </Link>
                                ))}
                                {dayActivities.map((activity) => (
                                    <Link
                                        key={`activity-${activity.id}`}
                                        href={`/operations?view=today&focus=${activity.id}`}
                                        className="rounded border bg-background p-2 text-xs hover:bg-muted"
                                    >
                                        <div className="font-medium">
                                            {activity.title}
                                        </div>
                                        <div>
                                            {formatTime(
                                                activity.starts_at ||
                                                    activity.due_at,
                                            )}{' '}
                                            ·{' '}
                                            {activity.assigned_user?.name ||
                                                'Unassigned'}
                                        </div>
                                    </Link>
                                ))}
                                {dayActivities.length === 0 &&
                                    dayOrders.length === 0 && (
                                        <div className="py-8 text-center text-xs text-muted-foreground">
                                            Available
                                        </div>
                                    )}
                            </div>
                        </div>
                    );
                })}
            </div>
            {orders.some((order) => !order.scheduled_start) && (
                <div className="border-t p-4">
                    <h3 className="mb-2 text-sm font-medium">
                        Unscheduled work orders
                    </h3>
                    <div className="flex flex-wrap gap-2">
                        {orders
                            .filter((order) => !order.scheduled_start)
                            .map((order) => (
                                <Button
                                    key={order.id}
                                    asChild
                                    size="sm"
                                    variant="outline"
                                >
                                    <Link
                                        href={`/work-orders?work_order=${order.id}`}
                                    >
                                        {order.number} · {order.title}
                                    </Link>
                                </Button>
                            ))}
                    </div>
                </div>
            )}
        </section>
    );
}

function Month({
    month,
    filters,
    activities,
    orders,
}: {
    month: string;
    filters: Filters;
    activities: Activity[];
    orders: WorkOrder[];
}) {
    const first = new Date(`${month}T12:00:00`);
    const offset = (first.getDay() + 6) % 7;
    const gridStart = addDays(month, -offset);
    const days = Array.from({ length: 42 }, (_, index) =>
        addDays(gridStart, index),
    );

    return (
        <section className="overflow-hidden rounded-lg border">
            <div className="flex items-center justify-between border-b px-4 py-3">
                <h2 className="font-semibold">
                    {new Intl.DateTimeFormat('en', {
                        month: 'long',
                        year: 'numeric',
                    }).format(first)}
                </h2>
                <div className="flex gap-1">
                    {[-1, 1].map((direction) => (
                        <Button
                            key={direction}
                            asChild
                            size="sm"
                            variant="outline"
                        >
                            <Link
                                href={operationsUrl({
                                    ...filters,
                                    week: addMonths(month, direction),
                                    view: 'month',
                                })}
                            >
                                {direction < 0 ? <ArrowLeft /> : <ArrowRight />}
                            </Link>
                        </Button>
                    ))}
                </div>
            </div>
            <div className="grid min-w-[60rem] grid-cols-7 divide-x divide-y overflow-x-auto">
                {days.map((day) => {
                    const dayOrders = orders.filter(
                        (order) => dateKey(order.scheduled_start) === day,
                    );
                    const count = activities.filter(
                        (activity) =>
                            dateKey(activity.starts_at || activity.due_at) ===
                            day,
                    ).length;

                    return (
                        <div
                            key={day}
                            className="min-h-28 p-2"
                            onDragOver={(event) => event.preventDefault()}
                            onDrop={(event) => dropOrder(event, day)}
                        >
                            <div className="mb-1 text-xs font-medium">
                                {day.slice(8)}
                            </div>
                            {dayOrders.slice(0, 3).map((order) => (
                                <Link
                                    key={order.id}
                                    draggable
                                    onDragStart={(event) =>
                                        event.dataTransfer.setData(
                                            'application/x-work-order',
                                            JSON.stringify(order),
                                        )
                                    }
                                    href={`/work-orders?work_order=${order.id}`}
                                    className="mb-1 block truncate rounded bg-blue-50 px-1 py-0.5 text-xs dark:bg-blue-950/30"
                                >
                                    {formatTime(order.scheduled_start)}{' '}
                                    {order.number}
                                </Link>
                            ))}
                            {count > 0 && (
                                <div className="text-xs text-muted-foreground">
                                    {count} activities
                                </div>
                            )}
                        </div>
                    );
                })}
            </div>
        </section>
    );
}

function dropOrder(event: React.DragEvent, day: string) {
    const raw = event.dataTransfer.getData('application/x-work-order');

    if (!raw) {
        return;
    }

    const order = JSON.parse(raw) as WorkOrder;
    const reason = window.prompt('Reason for rescheduling');

    if (!reason?.trim()) {
        return;
    }

    const localStart = order.scheduled_start
        ? dateTimeInput(order.scheduled_start)
        : null;
    const start = localStart
        ? `${day}T${localStart.slice(11)}:00`
        : `${day}T08:00:00`;
    const duration =
        order.scheduled_start && order.scheduled_end
            ? new Date(order.scheduled_end).getTime() -
              new Date(order.scheduled_start).getTime()
            : 60 * 60 * 1000;
    const end = localIso(new Date(new Date(start).getTime() + duration));
    router.patch(
        `/work-orders/${order.id}/schedule`,
        {
            scheduled_start: start,
            scheduled_end: end,
            reschedule_reason: reason,
        },
        { preserveScroll: true },
    );
}

function ActivityDialog({
    activity,
    installations,
    users,
    canManage,
    currentUserId,
}: {
    activity?: Activity;
    installations: InstallationOption[];
    users: UserOption[];
    canManage: boolean;
    currentUserId: number;
}) {
    const [open, setOpen] = useState(false);
    const editing = Boolean(activity);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button
                    size={editing ? 'icon' : 'default'}
                    variant={editing ? 'ghost' : 'default'}
                    title={editing ? 'Edit activity' : undefined}
                >
                    {editing ? (
                        <Pencil />
                    ) : (
                        <>
                            <Plus /> New activity
                        </>
                    )}
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>
                        {editing ? 'Edit activity' : 'New activity'}
                    </DialogTitle>
                    <DialogDescription>
                        {canManage
                            ? 'Plan, assign and track operational work.'
                            : 'Create a personal operational activity.'}
                    </DialogDescription>
                </DialogHeader>
                <Form
                    action={
                        editing
                            ? `/operations/activities/${activity!.id}`
                            : '/operations/activities'
                    }
                    method={editing ? 'patch' : 'post'}
                    resetOnSuccess={!editing}
                    onSuccess={() => setOpen(false)}
                    className="grid gap-4"
                >
                    {({ errors, processing }) =>
                        canManage ? (
                            <>
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <Field
                                        label="Title"
                                        name="title"
                                        defaultValue={activity?.title}
                                        error={errors.title}
                                    />
                                    <SelectField
                                        label="Type"
                                        name="type"
                                        defaultValue={activity?.type || 'task'}
                                        options={types}
                                    />
                                </div>
                                <div className="grid gap-4 sm:grid-cols-3">
                                    <SelectField
                                        label="Priority"
                                        name="priority"
                                        defaultValue={
                                            activity?.priority || 'normal'
                                        }
                                        options={[
                                            ['low', 'Low'],
                                            ['normal', 'Normal'],
                                            ['high', 'High'],
                                        ]}
                                    />
                                    <SelectField
                                        label="Status"
                                        name="status"
                                        defaultValue={
                                            activity?.status || 'pending'
                                        }
                                        options={statuses}
                                    />
                                    <SelectField
                                        label="Assigned to"
                                        name="assigned_user_id"
                                        defaultValue={
                                            activity?.assigned_user
                                                ? String(
                                                      activity.assigned_user.id,
                                                  )
                                                : 'none'
                                        }
                                        options={[
                                            ['none', 'Unassigned'],
                                            ...users.map((user) => [
                                                String(user.id),
                                                `${user.name} · ${user.role}`,
                                            ]),
                                        ]}
                                    />
                                </div>
                                <SelectField
                                    label="Installation"
                                    name="installation_id"
                                    defaultValue={
                                        activity?.installation
                                            ? String(activity.installation.id)
                                            : 'none'
                                    }
                                    options={[
                                        ['none', 'General activity'],
                                        ...installations.map((item) => [
                                            String(item.id),
                                            item.project_reference ||
                                                item.site_name ||
                                                `#${item.id}`,
                                        ]),
                                    ]}
                                />
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <Field
                                        label="Starts"
                                        name="starts_at"
                                        type="datetime-local"
                                        defaultValue={dateTimeInput(
                                            activity?.starts_at,
                                        )}
                                        error={errors.starts_at}
                                    />
                                    <Field
                                        label="Due"
                                        name="due_at"
                                        type="datetime-local"
                                        defaultValue={dateTimeInput(
                                            activity?.due_at,
                                        )}
                                        error={errors.due_at}
                                    />
                                </div>
                                <TextAreaField
                                    label="Notes / instructions"
                                    name="notes"
                                    defaultValue={activity?.notes || ''}
                                    error={errors.notes}
                                />
                                <TextAreaField
                                    label="Blocked reason"
                                    name="blocked_reason"
                                    defaultValue={
                                        activity?.blocked_reason || ''
                                    }
                                    error={errors.blocked_reason}
                                />
                                <Button disabled={processing}>
                                    {editing
                                        ? 'Save changes'
                                        : 'Create activity'}
                                </Button>
                            </>
                        ) : (
                            <>
                                {!editing && (
                                    <>
                                        <input
                                            type="hidden"
                                            name="assigned_user_id"
                                            value={currentUserId}
                                        />
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
                                                options={types}
                                            />
                                        </div>
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
                                            defaultValue="none"
                                            options={[
                                                ['none', 'General activity'],
                                                ...installations.map((item) => [
                                                    String(item.id),
                                                    item.project_reference ||
                                                        item.site_name ||
                                                        `#${item.id}`,
                                                ]),
                                            ]}
                                        />
                                        <div className="grid gap-4 sm:grid-cols-2">
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
                                    </>
                                )}
                                {editing && (
                                    <SelectField
                                        label="Status"
                                        name="status"
                                        defaultValue={
                                            activity?.status || 'pending'
                                        }
                                        options={statuses}
                                    />
                                )}
                                <TextAreaField
                                    label="Notes"
                                    name="notes"
                                    defaultValue={activity?.notes || ''}
                                    error={errors.notes}
                                />
                                {editing && (
                                    <TextAreaField
                                        label="Blocked reason"
                                        name="blocked_reason"
                                        defaultValue={
                                            activity?.blocked_reason || ''
                                        }
                                        error={errors.blocked_reason}
                                    />
                                )}
                                <Button disabled={processing}>
                                    {editing
                                        ? 'Save changes'
                                        : 'Create activity'}
                                </Button>
                            </>
                        )
                    }
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function ConvertDialog({ activity }: { activity: Activity }) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm" variant="outline">
                    <Wrench /> Convert
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Convert to work order</DialogTitle>
                    <DialogDescription>
                        The activity, schedule, technician and notes will become
                        the execution order.
                    </DialogDescription>
                </DialogHeader>
                <Form
                    action={`/operations/activities/${activity.id}/work-order`}
                    method="post"
                    onSuccess={() => setOpen(false)}
                    className="grid gap-4"
                >
                    {({ errors, processing }) => (
                        <>
                            <SelectField
                                label="Work type"
                                name="kind"
                                defaultValue={
                                    activity.type === 'visit'
                                        ? 'survey'
                                        : 'installation'
                                }
                                options={[
                                    ['survey', 'Survey'],
                                    ['installation', 'Installation'],
                                    ['commissioning', 'Commissioning'],
                                    ['maintenance', 'Maintenance'],
                                    ['repair', 'Repair'],
                                ]}
                            />
                            <Field
                                label="Scheduled end"
                                name="scheduled_end"
                                type="datetime-local"
                                defaultValue={dateTimeInput(activity.due_at)}
                                error={errors.scheduled_end}
                            />
                            <Button disabled={processing}>
                                <Wrench /> Create work order
                            </Button>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function StatusAction({
    activity,
    status,
    label,
    icon: Icon,
    needsReason = false,
}: {
    activity: Activity;
    status: string;
    label: string;
    icon?: typeof Check;
    needsReason?: boolean;
}) {
    if (needsReason) {
        return <BlockDialog activity={activity} />;
    }

    return (
        <Form action={`/operations/activities/${activity.id}`} method="patch">
            <input type="hidden" name="status" value={status} />
            <Button size={Icon ? 'icon' : 'sm'} variant="ghost" title={label}>
                {Icon ? <Icon /> : label}
            </Button>
        </Form>
    );
}

function BlockDialog({ activity }: { activity: Activity }) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm" variant="ghost">
                    Block
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Block activity</DialogTitle>
                    <DialogDescription>
                        Record what must happen before work can continue.
                    </DialogDescription>
                </DialogHeader>
                <Form
                    action={`/operations/activities/${activity.id}`}
                    method="patch"
                    onSuccess={() => setOpen(false)}
                    className="grid gap-4"
                >
                    {({ errors, processing }) => (
                        <>
                            <input
                                type="hidden"
                                name="status"
                                value="blocked"
                            />
                            <TextAreaField
                                label="Blocked reason"
                                name="blocked_reason"
                                defaultValue={activity.blocked_reason || ''}
                                error={errors.blocked_reason}
                            />
                            <Button disabled={processing}>Mark blocked</Button>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function Pagination({ pagination }: { pagination: PaginatedActivities }) {
    if (pagination.last_page <= 1) {
        return null;
    }

    const previous = pagination.links[0];
    const next = pagination.links[pagination.links.length - 1];

    return (
        <div className="flex items-center justify-between border-t px-4 py-3 text-sm">
            <span className="text-muted-foreground">
                Showing {pagination.from}–{pagination.to} of {pagination.total}
            </span>
            <div className="flex gap-1">
                <Button
                    asChild={Boolean(previous.url)}
                    disabled={!previous.url}
                    size="sm"
                    variant="outline"
                >
                    {previous.url ? (
                        <Link href={previous.url} preserveScroll>
                            <ArrowLeft /> Previous
                        </Link>
                    ) : (
                        <span>
                            <ArrowLeft /> Previous
                        </span>
                    )}
                </Button>
                <Button
                    asChild={Boolean(next.url)}
                    disabled={!next.url}
                    size="sm"
                    variant="outline"
                >
                    {next.url ? (
                        <Link href={next.url} preserveScroll>
                            Next <ArrowRight />
                        </Link>
                    ) : (
                        <span>
                            Next <ArrowRight />
                        </span>
                    )}
                </Button>
            </div>
        </div>
    );
}

function EmptyState() {
    return (
        <div className="grid place-items-center gap-2 px-4 py-16 text-center">
            <CircleDashed className="size-8 text-muted-foreground" />
            <div className="font-medium">No matching operational work</div>
            <p className="text-sm text-muted-foreground">
                Adjust the filters or create a new activity.
            </p>
        </div>
    );
}
function NativeSelect({
    name,
    defaultValue,
    options,
}: {
    name: string;
    defaultValue: string;
    options: string[][];
}) {
    return (
        <select
            name={name}
            defaultValue={defaultValue}
            className="h-9 rounded-md border bg-background px-3 text-sm"
        >
            {options.map(([value, label]) => (
                <option key={`${name}-${value}`} value={value}>
                    {label}
                </option>
            ))}
        </select>
    );
}
function Field({
    label,
    name,
    type = 'text',
    error,
    defaultValue,
}: {
    label: string;
    name: string;
    type?: string;
    error?: string;
    defaultValue?: string;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={`${name}-${defaultValue || 'new'}`}>{label}</Label>
            <Input
                id={`${name}-${defaultValue || 'new'}`}
                name={name}
                type={type}
                defaultValue={defaultValue}
            />
            <InputError message={error} />
        </div>
    );
}
function TextAreaField({
    label,
    name,
    error,
    defaultValue,
}: {
    label: string;
    name: string;
    error?: string;
    defaultValue?: string;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={`${name}-${defaultValue || 'new'}`}>{label}</Label>
            <Textarea
                id={`${name}-${defaultValue || 'new'}`}
                name={name}
                defaultValue={defaultValue}
                className="min-h-16"
            />
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
    defaultValue: string;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={`${name}-${defaultValue}`}>{label}</Label>
            <Select name={name} defaultValue={defaultValue}>
                <SelectTrigger
                    id={`${name}-${defaultValue}`}
                    className="w-full"
                >
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    {options.map(([value, text]) => (
                        <SelectItem key={`${name}-${value}`} value={value}>
                            {text}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
        </div>
    );
}
function PriorityBadge({ priority }: { priority: string }) {
    return (
        <Badge variant={priority === 'high' ? 'destructive' : 'outline'}>
            {labelFor(
                [
                    ['low', 'Low'],
                    ['normal', 'Normal'],
                    ['high', 'High'],
                ],
                priority,
            )}
        </Badge>
    );
}
function StatusBadge({ status }: { status: string }) {
    return (
        <Badge
            variant={
                status === 'blocked'
                    ? 'destructive'
                    : status === 'in_progress'
                      ? 'default'
                      : 'outline'
            }
        >
            {labelFor(statuses, status)}
        </Badge>
    );
}
function labelFor(options: string[][], value: string) {
    return (
        options.find(([key]) => key === value)?.[1] ||
        value.replaceAll('_', ' ')
    );
}
function personName(activity: Activity) {
    return activity.person
        ? `${activity.person.first_name} ${activity.person.last_name || ''}`.trim()
        : null;
}
function formatDate(value: string | null) {
    return value
        ? new Intl.DateTimeFormat('en-GB', {
              dateStyle: 'medium',
              timeStyle: 'short',
          }).format(new Date(value))
        : 'Unscheduled';
}
function formatTime(value: string | null) {
    return value
        ? new Intl.DateTimeFormat('en-GB', {
              hour: '2-digit',
              minute: '2-digit',
          }).format(new Date(value))
        : '—';
}
function dateTimeInput(value?: string | null) {
    if (!value) {
        return '';
    }

    const date = new Date(value);
    const offset = date.getTimezoneOffset();

    return new Date(date.getTime() - offset * 60_000)
        .toISOString()
        .slice(0, 16);
}
function localIso(date: Date) {
    const offset = date.getTimezoneOffset();

    return new Date(date.getTime() - offset * 60_000)
        .toISOString()
        .slice(0, 19);
}
function dateKey(value: string | null) {
    if (!value) {
        return null;
    }

    const date = new Date(value);
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
}
function addDays(value: string, amount: number) {
    const date = new Date(`${value}T12:00:00`);
    date.setDate(date.getDate() + amount);

    return date.toISOString().slice(0, 10);
}
function addMonths(value: string, amount: number) {
    const date = new Date(`${value}T12:00:00`);
    date.setDate(1);
    date.setMonth(date.getMonth() + amount);

    return date.toISOString().slice(0, 10);
}
function isOverdue(value: string | null) {
    return Boolean(value && new Date(value) < new Date());
}
function operationsUrl(values: Record<string, unknown>) {
    const params = new URLSearchParams();
    Object.entries(values).forEach(([key, value]) => {
        if (
            value !== undefined &&
            value !== null &&
            value !== '' &&
            key !== 'focus'
        ) {
            params.set(key, String(value));
        }
    });

    return `/operations?${params.toString()}`;
}

OperationsIndex.layout = {
    breadcrumbs: [{ title: 'Operations', href: '/operations' }],
};

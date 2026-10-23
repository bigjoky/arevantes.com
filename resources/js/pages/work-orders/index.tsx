import { Form, Head, Link, router, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    CalendarClock,
    Download,
    ImagePlus,
    PackageCheck,
    Pause,
    PenLine,
    Play,
    Plus,
    ShoppingCart,
    Trash2,
    Wrench,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import InputError from '@/components/input-error';
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
type Installation = {
    id: number;
    project_reference: string | null;
    site_name: string | null;
    status?: string;
};
type User = { id: number; name: string; role?: string };
type Warehouse = { id: number; code: string; name: string };
type Product = {
    id: number;
    sku: string | null;
    name: string;
    track_serials: boolean;
};
type MaterialReservation = {
    id: number;
    quantity: string;
    consumed_quantity: string;
    status: string;
    reserved_until: string | null;
    product: Product;
    warehouse: Warehouse;
};
type MaterialPlanRow = {
    product_id: number;
    sku: string | null;
    description: string;
    planned_quantity: number;
    actual_quantity: number;
    quantity_variance: number;
    planned_cost: number | null;
    actual_cost: number | null;
    cost_variance: number | null;
    remaining_quantity: number;
    available_quantity: number;
    reserved_quantity: number;
    inbound_quantity: number;
    shortage_quantity: number;
    is_unplanned: boolean;
};
type MaterialPlan = {
    planned_cost: number | null;
    actual_cost: number | null;
    variance: number | null;
    shortage_items: number;
    shortage_quantity: number;
    rows: MaterialPlanRow[];
};
type PlanningReadiness = {
    status: 'ready' | 'warning' | 'blocked';
    material_required: boolean;
    material_ready: boolean;
    material_ready_now: boolean;
    material_ready_on: string | null;
    shortage_items: number;
    shortage_quantity: number;
    personnel_ready: boolean;
    required_skills: string[];
    qualified_technicians: { id: number; name: string }[];
    rows: (MaterialPlanRow & {
        ready_on: string | null;
        shortage_by_schedule: number;
        late_inbound_quantity: number;
    })[];
};
type Supplier = {
    id: number;
    legal_name: string;
    trade_name: string | null;
};
type PurchaseDocument = {
    id: number;
    number: string;
    type: string;
    status: string;
    supplier: Supplier;
};
type Order = {
    id: number;
    number: string;
    title: string;
    kind: string;
    status: string;
    estimated_duration_minutes: number | null;
    required_skills: string[] | null;
    service_zone: string | null;
    scheduled_start: string | null;
    scheduled_end: string | null;
    duration_minutes: string | null;
    travel_km: string | null;
    installation: Installation;
    assigned_user: User | null;
    has_schedule_conflict: boolean;
};
type Entry = {
    id: number;
    user_id: number;
    started_at: string;
    ended_at: string | null;
    duration_minutes: number | null;
    hourly_cost?: string;
    travel_km: string;
    mileage_rate?: string;
    notes: string | null;
    cost?: number;
    user: User;
};
type Checklist = {
    id: number;
    label: string;
    is_required: boolean;
    completed_at: string | null;
    notes: string | null;
};
type Attachment = {
    id: number;
    kind: string;
    original_name: string;
    size: number;
    caption: string | null;
    customer_visible: boolean;
    published_at: string | null;
    evidence_key: string | null;
    document_version: number;
    publication_status:
        | 'internal'
        | 'pending_review'
        | 'published'
        | 'replaced';
    reviewed_at: string | null;
    reviewer: Pick<User, 'id' | 'name'> | null;
    publisher: Pick<User, 'id' | 'name'> | null;
};
type EvidenceRequirement = {
    key: string;
    label: string;
    kind: 'photo' | 'document';
    min_count: number;
};
type Selected = Order & {
    instructions: string | null;
    completion_notes: string | null;
    signed_by_name: string | null;
    signed_at: string | null;
    time_entries: Entry[];
    checklist_items: Checklist[];
    attachments: Attachment[];
    required_evidence: EvidenceRequirement[] | null;
    handover_documents: { key: string; label: string }[] | null;
    stock_reservations: MaterialReservation[];
    material_plan: MaterialPlan;
    planning_readiness: PlanningReadiness;
    purchase_documents: PurchaseDocument[];
};
type Running = {
    id: number;
    started_at: string;
    work_order: { id: number; number: string; title: string };
};
export default function WorkOrdersIndex({
    workOrders,
    selected,
    installations,
    users,
    runningEntry,
    canManage,
    canPublishCustomerDocuments,
    currentUserId,
    defaults,
    planningMetrics,
    warehouses,
    products,
    suppliers,
}: {
    workOrders: Order[];
    selected: Selected | null;
    installations: Installation[];
    users: User[];
    runningEntry: Running | null;
    canManage: boolean;
    canPublishCustomerDocuments: boolean;
    currentUserId: number;
    defaults: { hourlyCost: number | null; mileageRate: number | null };
    planningMetrics: {
        scheduled: number;
        unscheduled: number;
        conflicts: number;
        planned_hours: number;
    };
    warehouses: Warehouse[];
    products: Product[];
    suppliers: Supplier[];
}) {
    const [selectedOrderIds, setSelectedOrderIds] = useState<number[]>([]);
    const quickCreate =
        new URLSearchParams(usePage().url.split('?')[1] || '').get('create') ===
        '1';

    return (
        <>
            <Head title="Work orders" />
            <div className="flex min-w-0 flex-1 flex-col gap-6 p-4 md:p-6">
                <header className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold">Work orders</h1>
                        <p className="text-sm text-muted-foreground">
                            Technical planning, time and travel by installation.
                        </p>
                    </div>
                    {canManage && (
                        <div className="flex gap-2">
                            {selectedOrderIds.length > 0 && (
                                <BulkRescheduleDialog
                                    orders={workOrders.filter((order) =>
                                        selectedOrderIds.includes(order.id),
                                    )}
                                    onSuccess={() => setSelectedOrderIds([])}
                                />
                            )}
                            <WorkOrderDialog
                                installations={installations}
                                users={users}
                                initialOpen={quickCreate}
                            />
                        </div>
                    )}
                </header>
                {runningEntry && (
                    <section className="flex flex-wrap items-center justify-between gap-3 border-y bg-muted/30 px-3 py-3">
                        <div>
                            <div className="flex items-center gap-2 font-medium">
                                <span className="size-2 animate-pulse rounded-full bg-green-600" />
                                Timer running · {runningEntry.work_order.number}
                            </div>
                            <div className="text-sm text-muted-foreground">
                                Started {dateTime(runningEntry.started_at)}
                            </div>
                        </div>
                        <StopForm entry={runningEntry} />
                    </section>
                )}
                <section className="grid gap-px overflow-hidden border bg-border sm:grid-cols-2 xl:grid-cols-4">
                    <PlanningMetric
                        label="Scheduled"
                        value={String(planningMetrics.scheduled)}
                    />
                    <PlanningMetric
                        label="Unscheduled"
                        value={String(planningMetrics.unscheduled)}
                    />
                    <PlanningMetric
                        label="Planned hours"
                        value={`${planningMetrics.planned_hours} h`}
                    />
                    <PlanningMetric
                        label="Schedule conflicts"
                        value={String(planningMetrics.conflicts)}
                        attention={planningMetrics.conflicts > 0}
                    />
                </section>
                <WeeklyPlanning orders={workOrders} />
                <div className="grid min-w-0 items-start gap-7 2xl:grid-cols-[minmax(0,.75fr)_minmax(0,1.25fr)]">
                    <section className="min-w-0 overflow-x-auto">
                        <table className="w-full min-w-[38rem] text-sm">
                            <thead>
                                <tr className="border-b text-left text-muted-foreground">
                                    {canManage && <th className="w-10 py-2" />}
                                    <th className="py-2">Work order</th>
                                    <th>Schedule</th>
                                    <th>Technician</th>
                                    <th>Status</th>
                                    <th className="text-right">Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                {workOrders.map((order) => (
                                    <tr
                                        key={order.id}
                                        className={`border-b ${selected?.id === order.id ? 'bg-muted/40' : ''}`}
                                    >
                                        {canManage && (
                                            <td className="py-3">
                                                <input
                                                    type="checkbox"
                                                    aria-label={`Select ${order.number}`}
                                                    checked={selectedOrderIds.includes(
                                                        order.id,
                                                    )}
                                                    onChange={(event) =>
                                                        setSelectedOrderIds(
                                                            (current) =>
                                                                event.target
                                                                    .checked
                                                                    ? [
                                                                          ...current,
                                                                          order.id,
                                                                      ]
                                                                    : current.filter(
                                                                          (
                                                                              id,
                                                                          ) =>
                                                                              id !==
                                                                              order.id,
                                                                      ),
                                                        )
                                                    }
                                                />
                                            </td>
                                        )}
                                        <td className="py-3">
                                            <Link
                                                className="font-medium underline underline-offset-2"
                                                href={`/work-orders?work_order=${order.id}`}
                                            >
                                                {order.number}
                                            </Link>
                                            <div className="text-xs text-muted-foreground">
                                                {order.kind.replaceAll(
                                                    '_',
                                                    ' ',
                                                )}{' '}
                                                · {order.title} ·{' '}
                                                {order.installation
                                                    .project_reference ||
                                                    order.installation
                                                        .site_name}
                                            </div>
                                        </td>
                                        <td>
                                            {order.scheduled_start
                                                ? dateTime(
                                                      order.scheduled_start,
                                                  )
                                                : 'Unscheduled'}
                                        </td>
                                        <td>
                                            {order.assigned_user?.name ||
                                                'Unassigned'}
                                        </td>
                                        <td>
                                            <div className="flex flex-wrap gap-1">
                                                <Badge variant="outline">
                                                    {order.status}
                                                </Badge>
                                                {order.has_schedule_conflict && (
                                                    <Badge variant="destructive">
                                                        Conflict
                                                    </Badge>
                                                )}
                                            </div>
                                        </td>
                                        <td className="text-right">
                                            {minutes(
                                                Number(
                                                    order.duration_minutes || 0,
                                                ),
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                        {workOrders.length === 0 && (
                            <Empty text="No work orders yet." />
                        )}
                    </section>
                    <section className="min-w-0">
                        {selected ? (
                            <WorkOrderDetail
                                order={selected}
                                users={users}
                                running={runningEntry}
                                canManage={canManage}
                                canPublishCustomerDocuments={
                                    canPublishCustomerDocuments
                                }
                                currentUserId={currentUserId}
                                defaults={defaults}
                                warehouses={warehouses}
                                products={products}
                                suppliers={suppliers}
                            />
                        ) : (
                            <div className="flex min-h-60 flex-col items-center justify-center border-y text-sm text-muted-foreground">
                                <Wrench className="mb-2 size-6" />
                                Select a work order to view its details.
                            </div>
                        )}
                    </section>
                </div>
            </div>
        </>
    );
}
function WorkOrderDetail({
    order,
    users,
    running,
    canManage,
    canPublishCustomerDocuments,
    currentUserId,
    defaults,
    warehouses,
    products,
    suppliers,
}: {
    order: Selected;
    users: User[];
    running: Running | null;
    canManage: boolean;
    canPublishCustomerDocuments: boolean;
    currentUserId: number;
    defaults: { hourlyCost: number | null; mileageRate: number | null };
    warehouses: Warehouse[];
    products: Product[];
    suppliers: Supplier[];
}) {
    const own = order.assigned_user?.id === currentUserId || canManage;
    const labourCost = order.time_entries.every((entry) => entry.cost == null)
        ? null
        : order.time_entries.reduce((sum, e) => sum + Number(e.cost), 0);

    return (
        <div className="grid min-w-0 gap-6">
            <header className="flex flex-wrap items-start justify-between gap-3 border-t pt-4">
                <div>
                    <div className="flex flex-wrap items-center gap-2">
                        <h2 className="font-semibold">
                            {order.number} · {order.title}
                        </h2>
                        <Badge variant="outline">{order.status}</Badge>
                    </div>
                    <Link
                        className="text-sm text-muted-foreground underline underline-offset-2"
                        href={`/installations/${order.installation.id}`}
                    >
                        {order.installation.project_reference ||
                            order.installation.site_name}
                    </Link>
                </div>
                {own &&
                    !running &&
                    !['completed', 'cancelled'].includes(order.status) && (
                        <Form
                            action={`/work-orders/${order.id}/timer`}
                            method="post"
                        >
                            <Button>
                                <Play /> Start timer
                            </Button>
                        </Form>
                    )}
            </header>
            <div className="grid gap-4 border-y py-4 text-sm sm:grid-cols-3">
                <Info label="Phase" value={order.kind.replaceAll('_', ' ')} />
                <Info
                    label="Technician"
                    value={order.assigned_user?.name || 'Unassigned'}
                />
                <Info
                    label="Scheduled"
                    value={
                        order.scheduled_start
                            ? dateTime(order.scheduled_start)
                            : 'Unscheduled'
                    }
                />
                <Info label="Labour cost" value={money(labourCost)} />
                <Info
                    label="Planned materials"
                    value={money(order.material_plan.planned_cost)}
                />
                <Info
                    label="Used materials"
                    value={money(order.material_plan.actual_cost)}
                />
            </div>
            {order.has_schedule_conflict && (
                <div className="flex items-center gap-2 border-y border-destructive/40 bg-destructive/5 px-3 py-2 text-sm text-destructive">
                    <AlertTriangle className="size-4" /> This technician has
                    another work order during this period.
                </div>
            )}
            <div className="flex flex-wrap gap-2">
                <WorkOrderDetailsDialog order={order} />
                {canManage && <ScheduleDialog order={order} users={users} />}
            </div>
            {order.instructions && (
                <div>
                    <h3 className="mb-1 text-sm font-medium">Instructions</h3>
                    <p className="text-sm whitespace-pre-wrap text-muted-foreground">
                        {order.instructions}
                    </p>
                </div>
            )}
            <WorkOrderMaterials
                order={order}
                warehouses={warehouses}
                products={products}
                suppliers={suppliers}
                canManage={canManage}
                canConsume={own}
            />
            <div>
                <h3 className="mb-3 font-medium">Time entries</h3>
                <div className="overflow-x-auto border-y">
                    <table className="w-full min-w-[42rem] text-sm">
                        <thead>
                            <tr className="border-b text-left text-muted-foreground">
                                <th className="py-2">Technician</th>
                                <th>Started</th>
                                <th>Duration</th>
                                <th>Travel</th>
                                <th className="text-right">Cost</th>
                                <th className="w-12" />
                            </tr>
                        </thead>
                        <tbody>
                            {order.time_entries.map((entry) => (
                                <tr
                                    key={entry.id}
                                    className="border-b last:border-0"
                                >
                                    <td className="py-3">{entry.user.name}</td>
                                    <td>{dateTime(entry.started_at)}</td>
                                    <td>
                                        {entry.ended_at ? (
                                            minutes(entry.duration_minutes || 0)
                                        ) : (
                                            <Badge>Running</Badge>
                                        )}
                                    </td>
                                    <td>{Number(entry.travel_km)} km</td>
                                    <td className="text-right font-medium">
                                        {money(entry.cost)}
                                    </td>
                                    <td>
                                        {canManage && entry.ended_at && (
                                            <Form
                                                action={`/time-entries/${entry.id}`}
                                                method="delete"
                                            >
                                                <Button
                                                    size="icon"
                                                    variant="ghost"
                                                >
                                                    <Trash2 />
                                                </Button>
                                            </Form>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    {order.time_entries.length === 0 && (
                        <Empty text="No time registered." />
                    )}
                </div>
            </div>
            {canManage && (
                <ManualTimeDialog
                    order={order}
                    users={users}
                    defaults={defaults}
                />
            )}
            <ExecutionEvidence
                order={order}
                canPublishCustomerDocuments={canPublishCustomerDocuments}
            />
            <Form
                action={`/work-orders/${order.id}`}
                method="patch"
                className="grid gap-4 border-t pt-5"
            >
                <div className="grid gap-2">
                    <Label>Status</Label>
                    <Select name="status" defaultValue={order.status}>
                        <SelectTrigger>
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {[
                                'planned',
                                'in_progress',
                                'completed',
                                'cancelled',
                            ].map((s) => (
                                <SelectItem key={s} value={s}>
                                    {s.replaceAll('_', ' ')}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>
                <div className="grid gap-2">
                    <Label>Completion notes</Label>
                    <textarea
                        name="completion_notes"
                        defaultValue={order.completion_notes || ''}
                        className="min-h-20 rounded-md border bg-transparent p-3 text-sm"
                    />
                </div>
                <Button variant="outline">Update work order</Button>
            </Form>
        </div>
    );
}
function WeeklyPlanning({ orders }: { orders: Order[] }) {
    const days = Array.from({ length: 7 }, (_, offset) => {
        const value = new Date();
        value.setHours(0, 0, 0, 0);
        value.setDate(value.getDate() + offset);

        return value;
    });

    return (
        <section className="overflow-x-auto border-y py-4">
            <div className="grid min-w-[62rem] grid-cols-7 divide-x">
                {days.map((day) => {
                    const scheduled = orders.filter(
                        (order) =>
                            order.scheduled_start &&
                            new Date(order.scheduled_start).toDateString() ===
                                day.toDateString(),
                    );

                    return (
                        <div key={day.toISOString()} className="min-h-32 px-2">
                            <div className="mb-2 text-xs font-medium text-muted-foreground">
                                {new Intl.DateTimeFormat(undefined, {
                                    weekday: 'short',
                                    day: 'numeric',
                                    month: 'short',
                                }).format(day)}
                            </div>
                            <div className="grid gap-1.5">
                                {scheduled.map((order) => (
                                    <Link
                                        key={order.id}
                                        href={`/work-orders?work_order=${order.id}`}
                                        className={`border-l-2 px-2 py-1.5 text-xs hover:bg-muted ${order.has_schedule_conflict ? 'border-destructive bg-destructive/10' : 'border-primary bg-muted/50'}`}
                                    >
                                        <div className="font-medium">
                                            {new Date(
                                                order.scheduled_start!,
                                            ).toLocaleTimeString([], {
                                                hour: '2-digit',
                                                minute: '2-digit',
                                            })}{' '}
                                            · {order.number}
                                        </div>
                                        <div className="truncate text-muted-foreground">
                                            {order.assigned_user?.name ||
                                                'Unassigned'}
                                        </div>
                                        {order.has_schedule_conflict && (
                                            <div className="mt-1 font-medium text-destructive">
                                                Schedule conflict
                                            </div>
                                        )}
                                    </Link>
                                ))}
                            </div>
                        </div>
                    );
                })}
            </div>
        </section>
    );
}
function ExecutionEvidence({
    order,
    canPublishCustomerDocuments,
}: {
    order: Selected;
    canPublishCustomerDocuments: boolean;
}) {
    const requirements = order.required_evidence ?? [];
    const handover = order.handover_documents ?? [];
    const evidenceOptions = [...requirements, ...handover];

    return (
        <section className="grid gap-6 border-t pt-5">
            <div>
                <h3 className="font-medium">Technical checklist</h3>
                <p className="mb-3 text-sm text-muted-foreground">
                    Required controls must be complete before closing the work
                    order.
                </p>
                <div className="border-y">
                    {order.checklist_items.map((item) => (
                        <div
                            key={item.id}
                            className="flex items-center justify-between gap-4 border-b py-3 text-sm last:border-0"
                        >
                            <div className="flex items-center gap-2">
                                <span
                                    className={`size-2 rounded-full ${item.completed_at ? 'bg-green-600' : 'bg-muted-foreground/35'}`}
                                />
                                <span
                                    className={
                                        item.completed_at
                                            ? 'text-muted-foreground line-through'
                                            : ''
                                    }
                                >
                                    {item.label}
                                </span>
                                {item.is_required && (
                                    <span className="text-xs text-muted-foreground">
                                        Required
                                    </span>
                                )}
                            </div>
                            <Form
                                action={`/work-orders/${order.id}/checklist/${item.id}`}
                                method="patch"
                            >
                                <input
                                    type="hidden"
                                    name="completed"
                                    value={item.completed_at ? '0' : '1'}
                                />
                                <Button size="sm" variant="outline">
                                    {item.completed_at ? 'Reopen' : 'Complete'}
                                </Button>
                            </Form>
                        </div>
                    ))}
                </div>
            </div>
            <div>
                <h3 className="font-medium">Execution evidence</h3>
                {requirements.length > 0 && (
                    <div className="mt-3 grid gap-2 sm:grid-cols-2">
                        {requirements.map((requirement) => {
                            const count = order.attachments.filter(
                                (file) => file.evidence_key === requirement.key,
                            ).length;
                            const complete = count >= requirement.min_count;

                            return (
                                <div
                                    key={requirement.key}
                                    className="border-l-2 py-1 pl-3 text-sm"
                                >
                                    <div className="font-medium">
                                        {requirement.label}
                                    </div>
                                    <div
                                        className={
                                            complete
                                                ? 'text-green-700'
                                                : 'text-muted-foreground'
                                        }
                                    >
                                        {count}/{requirement.min_count} ·{' '}
                                        {complete ? 'Complete' : 'Pending'}
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                )}
                {handover.length > 0 && (
                    <div className="mt-5">
                        <div className="font-medium">Paquete de entrega</div>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Cada documento se valida antes de aparecer en el
                            portal. Publicar una versión nueva sustituye la
                            anterior sin borrar el histórico.
                        </p>
                        <div className="mt-3 grid gap-2 sm:grid-cols-2">
                            {handover.map((expected) => {
                                const versions = order.attachments.filter(
                                    (file) =>
                                        file.kind === 'document' &&
                                        file.evidence_key === expected.key,
                                );
                                const current =
                                    versions.find(
                                        (file) =>
                                            file.publication_status ===
                                            'published',
                                    ) || versions[0];

                                return (
                                    <div
                                        key={expected.key}
                                        className="flex items-start justify-between gap-3 border-l-2 py-1 pl-3 text-sm"
                                    >
                                        <div>
                                            <div className="font-medium">
                                                {expected.label}
                                            </div>
                                            <div className="text-xs text-muted-foreground">
                                                {current
                                                    ? `v${current.document_version} · ${publicationLabel(current.publication_status)}`
                                                    : 'Documento pendiente'}
                                            </div>
                                        </div>
                                        {current?.publication_status ===
                                            'published' && (
                                            <PackageCheck className="size-4 text-green-700" />
                                        )}
                                    </div>
                                );
                            })}
                        </div>
                    </div>
                )}
                <div className="mt-3 border-y">
                    {order.attachments.map((file) => (
                        <div
                            key={file.id}
                            className="flex items-center justify-between gap-3 border-b py-3 text-sm last:border-0"
                        >
                            <div>
                                <div className="font-medium">
                                    {file.original_name}
                                </div>
                                <div className="text-xs text-muted-foreground">
                                    {file.kind}
                                    {file.kind === 'document' &&
                                        ` v${file.document_version}`}{' '}
                                    · {Math.ceil(file.size / 1024)} KB ·{' '}
                                    {file.evidence_key &&
                                        `${evidenceOptions.find((item) => item.key === file.evidence_key)?.label || file.evidence_key} · `}
                                    {file.kind === 'document'
                                        ? publicationLabel(
                                              file.publication_status,
                                          )
                                        : 'Uso interno'}
                                    {file.reviewer &&
                                        ` · Validado por ${file.reviewer.name}`}
                                </div>
                            </div>
                            <div className="flex items-center gap-1">
                                {canPublishCustomerDocuments &&
                                    file.kind === 'document' &&
                                    ['internal', 'pending_review'].includes(
                                        file.publication_status,
                                    ) && (
                                        <Form
                                            action={`/work-orders/${order.id}/attachments/${file.id}/publication`}
                                            method="patch"
                                        >
                                            <input
                                                type="hidden"
                                                name="action"
                                                value="publish"
                                            />
                                            <Button size="sm" variant="outline">
                                                Publicar
                                            </Button>
                                        </Form>
                                    )}
                                {canPublishCustomerDocuments &&
                                    file.publication_status === 'published' && (
                                        <Form
                                            action={`/work-orders/${order.id}/attachments/${file.id}/publication`}
                                            method="patch"
                                        >
                                            <input
                                                type="hidden"
                                                name="action"
                                                value="retract"
                                            />
                                            <Button size="sm" variant="outline">
                                                Retirar
                                            </Button>
                                        </Form>
                                    )}
                                <Button size="icon" variant="ghost" asChild>
                                    <a
                                        href={`/work-orders/${order.id}/attachments/${file.id}`}
                                    >
                                        <Download />
                                    </a>
                                </Button>
                                {!['published', 'replaced'].includes(
                                    file.publication_status,
                                ) && (
                                    <Form
                                        action={`/work-orders/${order.id}/attachments/${file.id}`}
                                        method="delete"
                                    >
                                        <Button size="icon" variant="ghost">
                                            <Trash2 />
                                        </Button>
                                    </Form>
                                )}
                            </div>
                        </div>
                    ))}
                    {order.attachments.length === 0 && (
                        <Empty text="No execution evidence uploaded." />
                    )}
                </div>
                <Form
                    action={`/work-orders/${order.id}/attachments`}
                    method="post"
                    resetOnSuccess
                    className="mt-3 flex flex-wrap items-end gap-3"
                >
                    <div className="grid min-w-64 flex-1 gap-2">
                        <Label>Photos or documents</Label>
                        <Input
                            name="files[]"
                            type="file"
                            multiple
                            accept="image/jpeg,image/png,image/webp,application/pdf"
                            required
                        />
                        {evidenceOptions.length > 0 && (
                            <select
                                name="evidence_key"
                                className="h-9 rounded-md border bg-background px-3 text-sm"
                            >
                                <option value="">General evidence</option>
                                {evidenceOptions.map((item) => (
                                    <option key={item.key} value={item.key}>
                                        {item.label}
                                    </option>
                                ))}
                            </select>
                        )}
                        <label className="flex items-center gap-2 text-xs text-muted-foreground">
                            <input
                                type="checkbox"
                                name="submit_for_review"
                                value="1"
                            />
                            Enviar los PDF a validación
                        </label>
                    </div>
                    <Button variant="outline">
                        <ImagePlus /> Upload evidence
                    </Button>
                </Form>
            </div>
            <div>
                <h3 className="font-medium">Customer acceptance</h3>
                {order.signed_at ? (
                    <div className="mt-3 flex items-center justify-between gap-4 border-y py-3 text-sm">
                        <div>
                            Signed by <strong>{order.signed_by_name}</strong>
                            <div className="text-xs text-muted-foreground">
                                {dateTime(order.signed_at)}
                            </div>
                        </div>
                        <Button variant="outline" asChild>
                            <a
                                href={`/work-orders/${order.id}/signature`}
                                target="_blank"
                                rel="noreferrer"
                            >
                                <PenLine /> View signature
                            </a>
                        </Button>
                    </div>
                ) : (
                    <SignaturePad orderId={order.id} />
                )}
            </div>
        </section>
    );
}

function publicationLabel(status: Attachment['publication_status']): string {
    return {
        internal: 'Uso interno',
        pending_review: 'Pendiente de validación',
        published: 'Publicado para el cliente',
        replaced: 'Versión sustituida',
    }[status];
}

function WorkOrderMaterials({
    order,
    warehouses,
    products,
    suppliers,
    canManage,
    canConsume,
}: {
    order: Selected;
    warehouses: Warehouse[];
    products: Product[];
    suppliers: Supplier[];
    canManage: boolean;
    canConsume: boolean;
}) {
    const active = order.stock_reservations.filter(
        (reservation) => reservation.status === 'active',
    );
    const remaining = active.reduce(
        (total, reservation) =>
            total +
            Number(reservation.quantity) -
            Number(reservation.consumed_quantity),
        0,
    );

    return (
        <section className="border-t pt-5">
            <div
                className={`mb-4 border-l-4 p-4 text-sm ${order.planning_readiness.status === 'ready' ? 'border-green-600 bg-green-50 dark:bg-green-950/20' : order.planning_readiness.status === 'blocked' ? 'border-destructive bg-destructive/5' : 'border-amber-500 bg-amber-50 dark:bg-amber-950/20'}`}
            >
                <div className="font-medium">
                    {order.planning_readiness.status === 'ready'
                        ? 'Plan ready for the scheduled date'
                        : order.planning_readiness.status === 'blocked'
                          ? 'Planning blocked'
                          : 'Planning requires attention'}
                </div>
                <div className="mt-1 text-muted-foreground">
                    Materials:{' '}
                    {order.planning_readiness.material_ready
                        ? 'ready'
                        : `${number(order.planning_readiness.shortage_quantity)} units unavailable by the scheduled date`}{' '}
                    · Personnel:{' '}
                    {order.planning_readiness.personnel_ready
                        ? 'qualified technician assigned'
                        : `${order.planning_readiness.qualified_technicians.length} qualified technician(s) available`}
                </div>
                {!order.planning_readiness.material_ready &&
                    order.planning_readiness.material_ready_on && (
                        <div className="mt-1">
                            Estimated material readiness:{' '}
                            {dateTime(
                                order.planning_readiness.material_ready_on,
                            )}
                        </div>
                    )}
            </div>
            <div className="mb-3 flex flex-wrap items-start justify-between gap-3">
                <div>
                    <div className="flex items-center gap-2">
                        <PackageCheck className="size-4" />
                        <h3 className="font-medium">Materials</h3>
                    </div>
                    <p className="text-sm text-muted-foreground">
                        Forecast from the accepted quote compared with site
                        consumption.
                    </p>
                </div>
                <div className="flex flex-wrap gap-2">
                    {canManage &&
                        order.planning_readiness.material_required && (
                            <Form
                                action={`/work-orders/${order.id}/materials/reserve-kit`}
                                method="post"
                            >
                                <Button variant="outline">
                                    <PackageCheck /> Auto-reserve kit
                                </Button>
                            </Form>
                        )}
                    {canManage && order.material_plan.shortage_items > 0 && (
                        <ProcurementDialog
                            order={order}
                            suppliers={suppliers}
                        />
                    )}
                </div>
            </div>
            <div className="mb-4 grid grid-cols-2 border-y text-sm sm:grid-cols-4">
                <MaterialMetric
                    label="Planned"
                    value={money(order.material_plan.planned_cost)}
                />
                <MaterialMetric
                    label="Actual"
                    value={money(order.material_plan.actual_cost)}
                />
                <MaterialMetric
                    label="Deviation"
                    value={signedMoney(order.material_plan.variance)}
                    variance={order.material_plan.variance}
                />
                <MaterialMetric
                    label="Shortage"
                    value={`${number(order.material_plan.shortage_quantity)} units`}
                    attention={order.material_plan.shortage_items > 0}
                />
            </div>
            <MaterialForecast plan={order.material_plan} />
            {order.purchase_documents.length > 0 && (
                <div className="mt-3 flex flex-wrap items-center gap-2 text-sm">
                    <span className="text-muted-foreground">Purchasing:</span>
                    {order.purchase_documents.map((document) => (
                        <Link
                            key={document.id}
                            href={`/purchases/${document.id}`}
                            className="underline underline-offset-2"
                        >
                            {document.number} · {document.status}
                        </Link>
                    ))}
                </div>
            )}
            <div className="mt-5 mb-3 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h4 className="font-medium">Reservations</h4>
                    <p className="text-sm text-muted-foreground">
                        {active.length
                            ? `${active.length} active reservations · ${number(remaining)} units pending`
                            : 'No active materials reserved.'}
                    </p>
                </div>
                {canManage && (
                    <ReserveMaterialDialog
                        order={order}
                        warehouses={warehouses}
                        products={products}
                    />
                )}
            </div>
            <div className="overflow-x-auto border-y">
                <table className="w-full min-w-[38rem] text-sm">
                    <thead>
                        <tr className="border-b text-left text-muted-foreground">
                            <th className="px-3 py-2">Material</th>
                            <th className="px-3">Warehouse</th>
                            <th className="px-3 text-right">Reserved</th>
                            <th className="px-3 text-right">Consumed</th>
                            <th className="px-3">Status</th>
                            <th className="w-24 px-3" />
                        </tr>
                    </thead>
                    <tbody>
                        {order.stock_reservations.map((reservation) => {
                            const pending =
                                Number(reservation.quantity) -
                                Number(reservation.consumed_quantity);

                            return (
                                <tr
                                    key={reservation.id}
                                    className="border-b last:border-0"
                                >
                                    <td className="px-3 py-3">
                                        <div className="font-medium">
                                            {reservation.product.name}
                                        </div>
                                        <div className="font-mono text-xs text-muted-foreground">
                                            {reservation.product.sku ||
                                                'No SKU'}
                                        </div>
                                    </td>
                                    <td className="px-3">
                                        {reservation.warehouse.name}{' '}
                                        <span className="text-xs text-muted-foreground">
                                            ({reservation.warehouse.code})
                                        </span>
                                    </td>
                                    <td className="px-3 text-right">
                                        {number(Number(reservation.quantity))}
                                    </td>
                                    <td className="px-3 text-right">
                                        {number(
                                            Number(
                                                reservation.consumed_quantity,
                                            ),
                                        )}
                                    </td>
                                    <td className="px-3">
                                        <Badge variant="outline">
                                            {reservation.status}
                                        </Badge>
                                    </td>
                                    <td className="px-3">
                                        <div className="flex justify-end gap-1">
                                            {reservation.status === 'active' &&
                                                canConsume && (
                                                    <ConsumeMaterialDialog
                                                        order={order}
                                                        reservation={
                                                            reservation
                                                        }
                                                        remaining={pending}
                                                    />
                                                )}
                                            {reservation.status === 'active' &&
                                                canManage && (
                                                    <Form
                                                        action={`/work-orders/${order.id}/materials/${reservation.id}`}
                                                        method="delete"
                                                    >
                                                        <Button
                                                            size="icon"
                                                            variant="ghost"
                                                            aria-label={`Release ${reservation.product.name}`}
                                                        >
                                                            <Trash2 />
                                                        </Button>
                                                    </Form>
                                                )}
                                        </div>
                                    </td>
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
                {order.stock_reservations.length === 0 && (
                    <Empty text="No materials linked to this work order." />
                )}
            </div>
        </section>
    );
}

function MaterialMetric({
    label,
    value,
    variance,
    attention,
}: {
    label: string;
    value: string;
    variance?: number | null;
    attention?: boolean;
}) {
    return (
        <div className="border-r px-3 py-3 last:border-r-0">
            <div className="text-xs text-muted-foreground">{label}</div>
            <div
                className={`font-medium ${
                    attention
                        ? 'text-destructive'
                        : variance === undefined || variance === 0
                          ? ''
                          : (variance ?? 0) > 0
                            ? 'text-destructive'
                            : 'text-emerald-700 dark:text-emerald-400'
                }`}
            >
                {value}
            </div>
        </div>
    );
}

function MaterialForecast({ plan }: { plan: MaterialPlan }) {
    if (plan.rows.length === 0) {
        return (
            <div className="border-y px-3 py-5 text-center text-sm text-muted-foreground">
                No stock-tracked products were found in the accepted quote.
            </div>
        );
    }

    return (
        <div className="overflow-x-auto border-y">
            <table className="w-full min-w-[62rem] text-sm">
                <thead>
                    <tr className="border-b text-left text-muted-foreground">
                        <th className="px-3 py-2">Forecast</th>
                        <th className="px-3 text-right">Planned qty.</th>
                        <th className="px-3 text-right">Used qty.</th>
                        <th className="px-3">Supply coverage</th>
                        <th className="px-3 text-right">Shortage</th>
                        <th className="px-3 text-right">Planned cost</th>
                        <th className="px-3 text-right">Actual cost</th>
                        <th className="px-3 text-right">Deviation</th>
                    </tr>
                </thead>
                <tbody>
                    {plan.rows.map((row) => (
                        <tr
                            key={row.product_id}
                            className="border-b last:border-0"
                        >
                            <td className="px-3 py-3">
                                <div className="flex items-center gap-2 font-medium">
                                    {row.description}
                                    {row.is_unplanned && (
                                        <Badge variant="destructive">
                                            Unplanned
                                        </Badge>
                                    )}
                                </div>
                                <div className="font-mono text-xs text-muted-foreground">
                                    {row.sku || 'No SKU'}
                                </div>
                            </td>
                            <td className="px-3 text-right">
                                {number(row.planned_quantity)}
                            </td>
                            <td className="px-3 text-right">
                                {number(row.actual_quantity)}
                                <div
                                    className={
                                        row.quantity_variance > 0
                                            ? 'text-xs text-destructive'
                                            : 'text-xs text-muted-foreground'
                                    }
                                >
                                    {signedNumber(row.quantity_variance)}
                                </div>
                            </td>
                            <td className="px-3 text-xs">
                                <div>
                                    Available {number(row.available_quantity)}
                                </div>
                                <div className="text-muted-foreground">
                                    Reserved {number(row.reserved_quantity)} ·
                                    Incoming {number(row.inbound_quantity)}
                                </div>
                            </td>
                            <td
                                className={`px-3 text-right font-medium ${row.shortage_quantity > 0 ? 'text-destructive' : ''}`}
                            >
                                {number(row.shortage_quantity)}
                            </td>
                            <td className="px-3 text-right">
                                {money(row.planned_cost)}
                            </td>
                            <td className="px-3 text-right">
                                {money(row.actual_cost)}
                            </td>
                            <td
                                className={`px-3 text-right font-medium ${
                                    (row.cost_variance ?? 0) > 0
                                        ? 'text-destructive'
                                        : (row.cost_variance ?? 0) < 0
                                          ? 'text-emerald-700 dark:text-emerald-400'
                                          : ''
                                }`}
                            >
                                {signedMoney(row.cost_variance)}
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

function ProcurementDialog({
    order,
    suppliers,
}: {
    order: Selected;
    suppliers: Supplier[];
}) {
    const [open, setOpen] = useState(false);
    const shortages = order.material_plan.rows.filter(
        (row) => row.shortage_quantity > 0,
    );

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="outline" disabled={suppliers.length === 0}>
                    <ShoppingCart /> Request shortage
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Request missing materials</DialogTitle>
                </DialogHeader>
                <Form
                    action={`/work-orders/${order.id}/materials/procure`}
                    method="post"
                    onSuccess={() => setOpen(false)}
                    className="grid gap-4"
                >
                    {({ errors, processing }) => (
                        <>
                            <SelectField
                                label="Supplier"
                                name="supplier_id"
                                options={suppliers.map((supplier) => [
                                    String(supplier.id),
                                    supplier.trade_name || supplier.legal_name,
                                ])}
                            />
                            <InputError message={errors.supplier_id} />
                            <div className="border-y text-sm">
                                {shortages.map((row) => (
                                    <div
                                        key={row.product_id}
                                        className="flex items-center justify-between gap-4 border-b px-3 py-3 last:border-0"
                                    >
                                        <div>
                                            <div className="font-medium">
                                                {row.description}
                                            </div>
                                            <div className="font-mono text-xs text-muted-foreground">
                                                {row.sku || 'No SKU'}
                                            </div>
                                        </div>
                                        <div className="font-medium text-destructive">
                                            {number(row.shortage_quantity)}
                                        </div>
                                    </div>
                                ))}
                            </div>
                            <InputError message={errors.materials} />
                            <Button disabled={processing}>
                                <ShoppingCart /> Create purchase request
                            </Button>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function ReserveMaterialDialog({
    order,
    warehouses,
    products,
}: {
    order: Selected;
    warehouses: Warehouse[];
    products: Product[];
}) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="outline">
                    <Plus /> Reserve material
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Reserve material</DialogTitle>
                </DialogHeader>
                <Form
                    action={`/work-orders/${order.id}/materials`}
                    method="post"
                    onSuccess={() => setOpen(false)}
                    className="grid gap-4"
                >
                    {({ errors, processing }) => (
                        <>
                            <SelectField
                                label="Warehouse"
                                name="warehouse_id"
                                options={warehouses.map((warehouse) => [
                                    String(warehouse.id),
                                    `${warehouse.name} (${warehouse.code})`,
                                ])}
                            />
                            <SelectField
                                label="Product"
                                name="product_id"
                                options={products.map((product) => [
                                    String(product.id),
                                    `${product.sku ? `${product.sku} · ` : ''}${product.name}`,
                                ])}
                            />
                            <div className="grid gap-2">
                                <Label>Quantity</Label>
                                <Input
                                    name="quantity"
                                    type="number"
                                    min="0.001"
                                    step="0.001"
                                    defaultValue="1"
                                />
                                <InputError message={errors.quantity} />
                            </div>
                            <div className="grid gap-2">
                                <Label>Reserve until</Label>
                                <Input
                                    name="reserved_until"
                                    type="datetime-local"
                                />
                                <InputError message={errors.reserved_until} />
                            </div>
                            <Button
                                disabled={
                                    processing ||
                                    warehouses.length === 0 ||
                                    products.length === 0
                                }
                            >
                                Reserve stock
                            </Button>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function ConsumeMaterialDialog({
    order,
    reservation,
    remaining,
}: {
    order: Selected;
    reservation: MaterialReservation;
    remaining: number;
}) {
    const [open, setOpen] = useState(false);
    const [operationKey, setOperationKey] = useState(() => crypto.randomUUID());

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm" variant="outline">
                    Consume
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        Consume {reservation.product.name}
                    </DialogTitle>
                </DialogHeader>
                <Form
                    action={`/work-orders/${order.id}/materials/${reservation.id}/consume`}
                    method="post"
                    onSuccess={() => {
                        setOpen(false);
                        setOperationKey(crypto.randomUUID());
                    }}
                    className="grid gap-4"
                >
                    {({ errors, processing }) => (
                        <>
                            <input
                                type="hidden"
                                name="operation_key"
                                value={operationKey}
                            />
                            <div className="text-sm text-muted-foreground">
                                Remaining reservation: {number(remaining)}
                            </div>
                            <div className="grid gap-2">
                                <Label>Consumed quantity</Label>
                                <Input
                                    name="quantity"
                                    type="number"
                                    min="0.001"
                                    max={remaining}
                                    step="0.001"
                                    defaultValue={remaining}
                                />
                                <InputError message={errors.quantity} />
                            </div>
                            {reservation.product.track_serials && (
                                <div className="grid gap-2">
                                    <Label>Serial numbers</Label>
                                    <textarea
                                        name="serials"
                                        className="min-h-24 rounded-md border bg-transparent p-3 text-sm"
                                        placeholder="One serial number per unit"
                                    />
                                    <InputError message={errors.serials} />
                                </div>
                            )}
                            <Button disabled={processing}>
                                Record consumption
                            </Button>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function ScheduleDialog({ order, users }: { order: Selected; users: User[] }) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="outline">
                    <CalendarClock /> Edit schedule
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Schedule {order.number}</DialogTitle>
                </DialogHeader>
                <Form
                    action={`/work-orders/${order.id}/schedule`}
                    method="patch"
                    onSuccess={() => setOpen(false)}
                    className="grid gap-4"
                >
                    {({ errors, processing }) => (
                        <>
                            <div className="grid gap-2">
                                <Label>Technician</Label>
                                <Select
                                    name="assigned_user_id"
                                    defaultValue={
                                        order.assigned_user
                                            ? String(order.assigned_user.id)
                                            : 'none'
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="none">
                                            Unassigned
                                        </SelectItem>
                                        {users.map((user) => (
                                            <SelectItem
                                                key={user.id}
                                                value={String(user.id)}
                                            >
                                                {user.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.assigned_user_id} />
                            </div>
                            <div className="grid gap-2">
                                <Label>Phase</Label>
                                <Select name="kind" defaultValue={order.kind}>
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {[
                                            ['survey', 'Technical survey'],
                                            ['installation', 'Installation'],
                                            ['commissioning', 'Commissioning'],
                                            ['maintenance', 'Maintenance'],
                                            ['repair', 'Repair'],
                                        ].map(([value, label]) => (
                                            <SelectItem
                                                key={value}
                                                value={value}
                                            >
                                                {label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.kind} />
                            </div>
                            <div className="grid gap-2">
                                <Label>Title</Label>
                                <Input
                                    name="title"
                                    defaultValue={order.title}
                                />
                                <InputError message={errors.title} />
                            </div>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label>Scheduled start</Label>
                                    <Input
                                        name="scheduled_start"
                                        type="datetime-local"
                                        defaultValue={localDateTime(
                                            order.scheduled_start,
                                        )}
                                    />
                                    <InputError
                                        message={errors.scheduled_start}
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label>Scheduled end</Label>
                                    <Input
                                        name="scheduled_end"
                                        type="datetime-local"
                                        defaultValue={localDateTime(
                                            order.scheduled_end,
                                        )}
                                    />
                                    <InputError
                                        message={errors.scheduled_end}
                                    />
                                </div>
                            </div>
                            <div className="grid gap-2">
                                <Label>Instructions</Label>
                                <textarea
                                    name="instructions"
                                    defaultValue={order.instructions || ''}
                                    className="min-h-24 rounded-md border bg-transparent p-3 text-sm"
                                />
                                <InputError message={errors.instructions} />
                            </div>
                            <div className="grid gap-2">
                                <Label>Reason for rescheduling</Label>
                                <textarea
                                    name="reschedule_reason"
                                    required
                                    className="min-h-20 rounded-md border bg-transparent p-3 text-sm"
                                />
                                <InputError
                                    message={errors.reschedule_reason}
                                />
                            </div>
                            <Button disabled={processing}>Save schedule</Button>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function WorkOrderDetailsDialog({ order }: { order: Selected }) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="outline">
                    <PenLine /> Edit work order
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Edit {order.number}</DialogTitle>
                </DialogHeader>
                <Form
                    action={`/work-orders/${order.id}/details`}
                    method="patch"
                    onSuccess={() => setOpen(false)}
                    className="grid gap-4"
                >
                    {({ errors, processing }) => (
                        <>
                            <div className="grid gap-2">
                                <Label>Title</Label>
                                <Input
                                    name="title"
                                    defaultValue={order.title}
                                />
                                <InputError message={errors.title} />
                            </div>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label>Phase</Label>
                                    <Select
                                        name="kind"
                                        defaultValue={order.kind}
                                    >
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {[
                                                ['survey', 'Technical survey'],
                                                [
                                                    'installation',
                                                    'Installation',
                                                ],
                                                [
                                                    'commissioning',
                                                    'Commissioning',
                                                ],
                                                ['maintenance', 'Maintenance'],
                                                ['repair', 'Repair'],
                                            ].map(([value, label]) => (
                                                <SelectItem
                                                    key={value}
                                                    value={value}
                                                >
                                                    {label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError message={errors.kind} />
                                </div>
                                <div className="grid gap-2">
                                    <Label>Estimated duration (minutes)</Label>
                                    <Input
                                        name="estimated_duration_minutes"
                                        type="number"
                                        min="1"
                                        max="1440"
                                        defaultValue={
                                            order.estimated_duration_minutes ||
                                            ''
                                        }
                                    />
                                    <InputError
                                        message={
                                            errors.estimated_duration_minutes
                                        }
                                    />
                                </div>
                            </div>
                            <div className="grid gap-2">
                                <Label>Service zone</Label>
                                <Input
                                    name="service_zone"
                                    defaultValue={order.service_zone || ''}
                                />
                                <InputError message={errors.service_zone} />
                            </div>
                            <div className="grid gap-2">
                                <Label>Required skills</Label>
                                <Input
                                    name="required_skills"
                                    defaultValue={(
                                        order.required_skills || []
                                    ).join(', ')}
                                    placeholder="Electrical, commissioning"
                                />
                                <p className="text-xs text-muted-foreground">
                                    Separate skills with commas.
                                </p>
                                <InputError message={errors.required_skills} />
                            </div>
                            <div className="grid gap-2">
                                <Label>Instructions</Label>
                                <textarea
                                    name="instructions"
                                    defaultValue={order.instructions || ''}
                                    className="min-h-28 rounded-md border bg-transparent p-3 text-sm"
                                />
                                <InputError message={errors.instructions} />
                            </div>
                            <Button disabled={processing}>Save changes</Button>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function BulkRescheduleDialog({
    orders,
    onSuccess,
}: {
    orders: Order[];
    onSuccess: () => void;
}) {
    const [open, setOpen] = useState(false);
    const [date, setDate] = useState('');
    const [reason, setReason] = useState('');
    const [processing, setProcessing] = useState(false);

    const submit = () => {
        if (!date || !reason.trim()) {
            return;
        }

        setProcessing(true);
        router.patch(
            '/work-orders/bulk-reschedule',
            {
                reason,
                items: orders.map((order, index) => {
                    const fallbackStart = new Date(`${date}T08:00:00`);
                    fallbackStart.setMinutes(
                        fallbackStart.getMinutes() + index * 60,
                    );
                    const start = order.scheduled_start
                        ? `${date}T${localDateTime(order.scheduled_start).slice(11)}:00`
                        : localIso(fallbackStart);
                    const duration =
                        order.scheduled_start && order.scheduled_end
                            ? new Date(order.scheduled_end).getTime() -
                              new Date(order.scheduled_start).getTime()
                            : 60 * 60 * 1000;

                    return {
                        work_order_id: order.id,
                        assigned_user_id: order.assigned_user?.id || null,
                        scheduled_start: start,
                        scheduled_end: localIso(
                            new Date(new Date(start).getTime() + duration),
                        ),
                    };
                }),
            },
            {
                preserveScroll: true,
                onFinish: () => setProcessing(false),
                onSuccess: () => {
                    setOpen(false);
                    onSuccess();
                },
            },
        );
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="outline">
                    <CalendarClock /> Reschedule {orders.length}
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Bulk rescheduling</DialogTitle>
                </DialogHeader>
                <div className="grid gap-4">
                    <div className="rounded border p-3 text-sm">
                        {orders.map((order) => order.number).join(', ')}
                    </div>
                    <div className="grid gap-2">
                        <Label>New date</Label>
                        <Input
                            type="date"
                            value={date}
                            onChange={(event) => setDate(event.target.value)}
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label>Reason</Label>
                        <textarea
                            value={reason}
                            onChange={(event) => setReason(event.target.value)}
                            className="min-h-24 rounded-md border bg-transparent p-3 text-sm"
                        />
                    </div>
                    <Button
                        type="button"
                        disabled={processing || !date || !reason.trim()}
                        onClick={submit}
                    >
                        Apply changes
                    </Button>
                </div>
            </DialogContent>
        </Dialog>
    );
}

function PlanningMetric({
    label,
    value,
    attention = false,
}: {
    label: string;
    value: string;
    attention?: boolean;
}) {
    return (
        <div className="bg-background p-4">
            <div className="text-xs text-muted-foreground">{label}</div>
            <div
                className={`mt-1 text-lg font-semibold ${attention ? 'text-destructive' : ''}`}
            >
                {value}
            </div>
        </div>
    );
}
function SignaturePad({ orderId }: { orderId: number }) {
    const canvas = useRef<HTMLCanvasElement>(null);
    const [drawing, setDrawing] = useState(false);
    const [signature, setSignature] = useState('');
    useEffect(() => {
        const element = canvas.current;

        if (!element) {
            return;
        }

        const ratio = window.devicePixelRatio || 1;
        element.width = element.clientWidth * ratio;
        element.height = element.clientHeight * ratio;
        const context = element.getContext('2d');
        context?.scale(ratio, ratio);

        if (context) {
            context.lineWidth = 2;
            context.lineCap = 'round';
            context.strokeStyle = '#17221c';
        }
    }, []);
    const point = (event: React.PointerEvent<HTMLCanvasElement>) => {
        const rect = event.currentTarget.getBoundingClientRect();

        return [event.clientX - rect.left, event.clientY - rect.top] as const;
    };
    const start = (event: React.PointerEvent<HTMLCanvasElement>) => {
        const context = event.currentTarget.getContext('2d');
        const [x, y] = point(event);
        context?.beginPath();
        context?.moveTo(x, y);
        event.currentTarget.setPointerCapture(event.pointerId);
        setDrawing(true);
    };
    const move = (event: React.PointerEvent<HTMLCanvasElement>) => {
        if (!drawing) {
            return;
        }

        const [x, y] = point(event);
        const context = event.currentTarget.getContext('2d');
        context?.lineTo(x, y);
        context?.stroke();
    };
    const end = () => {
        setDrawing(false);
        setSignature(canvas.current?.toDataURL('image/png') || '');
    };
    const clear = () => {
        const element = canvas.current;
        element
            ?.getContext('2d')
            ?.clearRect(0, 0, element.width, element.height);
        setSignature('');
    };

    return (
        <Form
            action={`/work-orders/${orderId}/signature`}
            method="post"
            className="mt-3 grid gap-3"
        >
            <Field label="Customer name" name="signed_by_name" />
            <canvas
                ref={canvas}
                className="h-40 w-full touch-none border bg-white"
                onPointerDown={start}
                onPointerMove={move}
                onPointerUp={end}
                onPointerCancel={end}
            />
            <input type="hidden" name="signature" value={signature} />
            <div className="flex justify-end gap-2">
                <Button type="button" variant="outline" onClick={clear}>
                    Clear
                </Button>
                <Button disabled={!signature}>
                    <PenLine /> Save signature
                </Button>
            </div>
        </Form>
    );
}
function WorkOrderDialog({
    installations,
    users,
    initialOpen = false,
}: {
    installations: Installation[];
    users: User[];
    initialOpen?: boolean;
}) {
    const [open, setOpen] = useState(initialOpen);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button>
                    <Plus /> New work order
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Plan work order</DialogTitle>
                </DialogHeader>
                <Form
                    action="/work-orders"
                    method="post"
                    onSuccess={() => setOpen(false)}
                    className="grid gap-4"
                >
                    <SelectField
                        label="Installation"
                        name="installation_id"
                        options={installations.map((i) => [
                            String(i.id),
                            i.project_reference || i.site_name || `#${i.id}`,
                        ])}
                    />
                    <SelectField
                        label="Technician"
                        name="assigned_user_id"
                        options={users.map((u) => [String(u.id), u.name])}
                    />
                    <SelectField
                        label="Phase"
                        name="kind"
                        options={[
                            ['survey', 'Technical survey'],
                            ['installation', 'Installation'],
                            ['commissioning', 'Commissioning'],
                            ['maintenance', 'Maintenance'],
                            ['repair', 'Repair'],
                        ]}
                    />
                    <Field label="Title" name="title" />
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field
                            label="Scheduled start"
                            name="scheduled_start"
                            type="datetime-local"
                        />
                        <Field
                            label="Scheduled end"
                            name="scheduled_end"
                            type="datetime-local"
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label>Instructions</Label>
                        <textarea
                            name="instructions"
                            className="min-h-24 rounded-md border bg-transparent p-3 text-sm"
                        />
                    </div>
                    <Button>Create work order</Button>
                </Form>
            </DialogContent>
        </Dialog>
    );
}
function ManualTimeDialog({
    order,
    users,
    defaults,
}: {
    order: Selected;
    users: User[];
    defaults: { hourlyCost: number | null; mileageRate: number | null };
}) {
    const [open, setOpen] = useState(false);
    const canViewFinance = Boolean(usePage().props.canViewFinance);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="outline">
                    <Plus /> Add manual time
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Add time entry</DialogTitle>
                </DialogHeader>
                <Form
                    action={`/work-orders/${order.id}/time-entries`}
                    method="post"
                    onSuccess={() => setOpen(false)}
                    className="grid gap-4"
                >
                    <SelectField
                        label="Technician"
                        name="user_id"
                        options={users.map((u) => [String(u.id), u.name])}
                    />
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field
                            label="Started"
                            name="started_at"
                            type="datetime-local"
                        />
                        <Field
                            label="Ended"
                            name="ended_at"
                            type="datetime-local"
                        />
                        {canViewFinance && (
                            <Field
                                label="Hourly cost"
                                name="hourly_cost"
                                type="number"
                                value={String(defaults.hourlyCost)}
                            />
                        )}
                        <Field
                            label="Travel km"
                            name="travel_km"
                            type="number"
                            value="0"
                        />
                        {canViewFinance && (
                            <Field
                                label="Mileage rate"
                                name="mileage_rate"
                                type="number"
                                value={String(defaults.mileageRate)}
                            />
                        )}
                    </div>
                    <Button>Add entry</Button>
                </Form>
            </DialogContent>
        </Dialog>
    );
}
function StopForm({ entry }: { entry: Running }) {
    return (
        <Form
            action={`/time-entries/${entry.id}/stop`}
            method="patch"
            resetOnSuccess
            className="flex flex-wrap items-end gap-2"
        >
            <Field label="Travel km" name="travel_km" type="number" value="0" />
            <Button variant="outline">
                <Pause /> Stop timer
            </Button>
        </Form>
    );
}
function SelectField({
    label,
    name,
    options,
}: {
    label: string;
    name: string;
    options: string[][];
}) {
    return (
        <div className="grid gap-2">
            <Label>{label}</Label>
            <Select name={name} defaultValue={options[0]?.[0]}>
                <SelectTrigger>
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    {options.map(([id, text]) => (
                        <SelectItem key={id} value={id}>
                            {text}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
        </div>
    );
}
function Field({
    label,
    name,
    type = 'text',
    value,
}: {
    label: string;
    name: string;
    type?: string;
    value?: string;
}) {
    return (
        <div className="grid gap-2">
            <Label>{label}</Label>
            <Input
                name={name}
                type={type}
                defaultValue={value}
                step={type === 'number' ? '0.01' : undefined}
            />
        </div>
    );
}
function Info({ label, value }: { label: string; value: string }) {
    return (
        <div>
            <div className="text-muted-foreground">{label}</div>
            <div className="mt-1 font-medium">{value}</div>
        </div>
    );
}
function Empty({ text }: { text: string }) {
    return (
        <div className="py-10 text-center text-sm text-muted-foreground">
            {text}
        </div>
    );
}
const dateTime = (v: string) =>
    new Intl.DateTimeFormat(undefined, {
        dateStyle: 'short',
        timeStyle: 'short',
    }).format(new Date(v));
const localDateTime = (value: string | null) => {
    if (!value) {
        return '';
    }

    const date = new Date(value);
    const local = new Date(date.getTime() - date.getTimezoneOffset() * 60000);

    return local.toISOString().slice(0, 16);
};
const localIso = (date: Date) => {
    const local = new Date(date.getTime() - date.getTimezoneOffset() * 60000);

    return local.toISOString().slice(0, 19);
};
const minutes = (v: number) => `${Math.floor(v / 60)}h ${v % 60}m`;
const money = (v: number | null | undefined) =>
    v == null
        ? '—'
        : new Intl.NumberFormat(undefined, {
              style: 'currency',
              currency: 'EUR',
          }).format(v);
const number = (value: number) =>
    new Intl.NumberFormat(undefined, { maximumFractionDigits: 3 }).format(
        value,
    );
const signedNumber = (value: number) =>
    `${value > 0 ? '+' : ''}${number(value)}`;
const signedMoney = (value: number | null) =>
    value == null ? '—' : `${value > 0 ? '+' : ''}${money(value)}`;
WorkOrdersIndex.layout = {
    breadcrumbs: [{ title: 'Work orders', href: '/work-orders' }],
};

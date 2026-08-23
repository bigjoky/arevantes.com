import { Form } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

type RmaStatus =
    | 'requested'
    | 'approved'
    | 'shipped'
    | 'received'
    | 'resolved'
    | 'rejected'
    | 'cancelled';

export type AssetRma = {
    id: number;
    number: string;
    provider: string | null;
    external_reference: string | null;
    status: RmaStatus;
    reason: string;
    resolution: string | null;
    requested_at: string;
    shipped_at: string | null;
    resolved_at: string | null;
    work_order: { id: number; number: string; title: string } | null;
    service_ticket: { id: number; number: string; title: string } | null;
};

type LinkOptions = {
    workOrders: { id: number; number: string; title: string }[];
    tickets: { id: number; number: string; title: string }[];
};

const statusLabels: Record<RmaStatus, string> = {
    requested: 'Requested',
    approved: 'Approved',
    shipped: 'Shipped',
    received: 'Received',
    resolved: 'Resolved',
    rejected: 'Rejected',
    cancelled: 'Cancelled',
};

const transitions: Partial<Record<RmaStatus, RmaStatus[]>> = {
    requested: ['approved', 'rejected', 'cancelled'],
    approved: ['shipped', 'cancelled'],
    shipped: ['received', 'cancelled'],
    received: ['resolved'],
};

export function AssetRmaPanel({
    installationId,
    asset,
    options,
    canManage,
}: {
    installationId: number;
    asset: { id: number; status: string; rmas: AssetRma[] };
    options: LinkOptions;
    canManage: boolean;
}) {
    const activeRma = asset.rmas.find((rma) => transitions[rma.status]);
    const canOpen = !['removed', 'replaced'].includes(asset.status);

    return (
        <div className="mt-3 rounded-md border border-amber-200 bg-amber-50/40 p-3 dark:border-amber-900 dark:bg-amber-950/10">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <div className="text-xs font-semibold tracking-wide uppercase">
                    Warranty / RMA
                </div>
                {!activeRma && canOpen && canManage && (
                    <details>
                        <summary className="cursor-pointer text-xs underline underline-offset-2">
                            Open RMA
                        </summary>
                        <Form
                            action={`/installations/${installationId}/assets/${asset.id}/rmas`}
                            method="post"
                            className="mt-3 grid min-w-72 gap-2"
                        >
                            <Input
                                name="provider"
                                placeholder="Provider or manufacturer"
                            />
                            <Input
                                name="external_reference"
                                placeholder="External reference (optional)"
                            />
                            <textarea
                                name="reason"
                                required
                                rows={3}
                                placeholder="Reason and observed fault"
                                className="rounded-md border bg-background px-3 py-2 text-sm"
                            />
                            {options.workOrders.length > 0 && (
                                <select
                                    name="work_order_id"
                                    className="h-9 rounded-md border bg-background px-3 text-sm"
                                >
                                    <option value="">
                                        No linked work order
                                    </option>
                                    {options.workOrders.map((order) => (
                                        <option key={order.id} value={order.id}>
                                            {order.number} · {order.title}
                                        </option>
                                    ))}
                                </select>
                            )}
                            {options.tickets.length > 0 && (
                                <select
                                    name="service_ticket_id"
                                    className="h-9 rounded-md border bg-background px-3 text-sm"
                                >
                                    <option value="">No linked ticket</option>
                                    {options.tickets.map((ticket) => (
                                        <option
                                            key={ticket.id}
                                            value={ticket.id}
                                        >
                                            {ticket.number} · {ticket.title}
                                        </option>
                                    ))}
                                </select>
                            )}
                            <Button size="sm">Create RMA</Button>
                        </Form>
                    </details>
                )}
            </div>
            {asset.rmas.length === 0 ? (
                <p className="mt-2 text-xs text-muted-foreground">
                    No warranty claims registered.
                </p>
            ) : (
                <div className="mt-2 grid gap-2">
                    {asset.rmas.map((rma) => (
                        <div
                            key={rma.id}
                            className="rounded border bg-background p-2"
                        >
                            <div className="flex flex-wrap items-center gap-2 text-xs">
                                <span className="font-medium">
                                    {rma.number}
                                </span>
                                <Badge variant="outline">
                                    {statusLabels[rma.status]}
                                </Badge>
                                <span className="text-muted-foreground">
                                    {new Date(
                                        rma.requested_at,
                                    ).toLocaleDateString('es-ES')}
                                </span>
                            </div>
                            <p className="mt-1 text-xs">{rma.reason}</p>
                            {(rma.provider || rma.external_reference) && (
                                <p className="mt-1 text-xs text-muted-foreground">
                                    {[rma.provider, rma.external_reference]
                                        .filter(Boolean)
                                        .join(' · ')}
                                </p>
                            )}
                            {(rma.work_order || rma.service_ticket) && (
                                <p className="mt-1 text-xs text-muted-foreground">
                                    {[
                                        rma.work_order?.number,
                                        rma.service_ticket?.number,
                                    ]
                                        .filter(Boolean)
                                        .join(' · ')}
                                </p>
                            )}
                            {rma.resolution && (
                                <p className="mt-1 text-xs">
                                    Resolution: {rma.resolution}
                                </p>
                            )}
                            {canManage && transitions[rma.status] && (
                                <Form
                                    action={`/installations/${installationId}/assets/${asset.id}/rmas/${rma.id}`}
                                    method="patch"
                                    className="mt-2 grid gap-2 sm:grid-cols-[1fr_1fr_auto]"
                                >
                                    <select
                                        name="status"
                                        required
                                        className="h-9 rounded-md border bg-background px-3 text-xs"
                                    >
                                        <option value="">Next status…</option>
                                        {transitions[rma.status]?.map(
                                            (status) => (
                                                <option
                                                    key={status}
                                                    value={status}
                                                >
                                                    {statusLabels[status]}
                                                </option>
                                            ),
                                        )}
                                    </select>
                                    <Input
                                        name="resolution"
                                        placeholder="Resolution / notes"
                                        className="text-xs"
                                    />
                                    <Button size="sm" variant="outline">
                                        Update
                                    </Button>
                                </Form>
                            )}
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}

import { Head, Link, useForm } from '@inertiajs/react';
import {
    AlertTriangle,
    CheckCircle2,
    ClipboardCheck,
    Eye,
    ExternalLink,
    LockKeyhole,
    Play,
    RefreshCw,
    SendToBack,
    ShieldCheck,
} from 'lucide-react';
import InputError from '@/components/input-error';
import { PageHeader, PageShell } from '@/components/page-shell';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useLocale } from '@/hooks/use-locale';
import { translate } from '@/i18n';

type Area = 'operations' | 'inventory' | 'purchases' | 'finance';

type ScenarioRequest = {
    id: number;
    area: Area;
    status: 'draft' | 'confirmed';
    request_fingerprint: string;
    payload: {
        non_committing: boolean;
        capacity?: {
            scenario_demand_hours: number;
            scenario_overload_hours: number;
        };
        opportunities?: unknown[];
        materials?: unknown[];
        expected_material_cost?: number;
        shortages?: unknown[];
        summary?: { weighted_revenue: number; expected_material_cost: number };
        cash_by_month?: unknown[];
    };
    scenario: {
        id: number;
        name: string;
        version: number;
        horizon_starts_on: string;
        horizon_ends_on: string;
    };
    generator: { id: number; name: string };
    confirmer: { id: number; name: string } | null;
    confirmed_at: string | null;
    confirmation_note: string | null;
    materialization_status: 'pending' | 'prepared' | 'authorized';
    materialization_preview: {
        captured_at: string;
        non_committing: boolean;
        state: {
            summary: { items: number; blocking_conflicts: number };
            conflicts: Array<{ code: string; subject: string }>;
        };
    } | null;
    materialization_fingerprint: string | null;
    idempotency_key: string | null;
    preparer: { id: number; name: string } | null;
    prepared_at: string | null;
    authorizer: { id: number; name: string } | null;
    authorized_at: string | null;
    authorization_note: string | null;
    execution_status: 'pending' | 'executed';
    execution_result: {
        kind: string;
        activity_ids?: number[];
        reservation_ids?: number[];
        drafts?: unknown[];
    } | null;
    execution_fingerprint: string | null;
    executor: { id: number; name: string } | null;
    executed_at: string | null;
    promotion_status: 'pending' | 'promoted';
    promotion_result: {
        purchase_document_ids?: number[];
        financial_forecast_item_ids?: number[];
    } | null;
    promoter: { id: number; name: string } | null;
    promoted_at: string | null;
    outcome_monitoring: {
        total: number;
        statuses: Record<string, number>;
        unassigned?: number;
        overdue?: number;
        expired?: number;
        quantity?: number;
        consumed_quantity?: number;
        amount?: number;
        actual_amount?: number;
        direct_url: string | null;
        exceptions: Array<{ label: string; count: number }>;
        comparison?: Array<{
            label: string;
            planned: number;
            current: number;
            unit: 'items' | 'units' | 'currency';
        }>;
    };
};

const areaLabel = (area: Area, locale: 'en' | 'es') =>
    translate(
        area === 'operations'
            ? 'Operations'
            : area === 'inventory'
              ? 'Inventory'
              : area === 'purchases'
                ? 'Purchases'
                : 'Finance',
        locale,
    );

export default function PlanningScenarioRequests({
    requests,
    areas,
}: {
    requests: ScenarioRequest[];
    areas: Area[];
}) {
    const { locale } = useLocale();

    return (
        <>
            <Head title={translate('Planning requests', locale)} />
            <PageShell>
                <PageHeader
                    title={translate('Planning requests', locale)}
                    description={translate(
                        'Each area confirms only its preliminary request. Confirmation records responsibility but does not create operational resources.',
                        locale,
                    )}
                />
                <div className="flex flex-wrap gap-2">
                    {areas.map((area) => (
                        <Badge key={area} variant="outline">
                            {areaLabel(area, locale)}
                        </Badge>
                    ))}
                </div>
                <section className="grid gap-4 xl:grid-cols-2">
                    {requests.map((request) => (
                        <RequestCard
                            key={request.id}
                            request={request}
                            locale={locale}
                        />
                    ))}
                    {requests.length === 0 && (
                        <div className="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground xl:col-span-2">
                            {translate(
                                'There are no planning requests for your areas.',
                                locale,
                            )}
                        </div>
                    )}
                </section>
            </PageShell>
        </>
    );
}

function RequestCard({
    request,
    locale,
}: {
    request: ScenarioRequest;
    locale: 'en' | 'es';
}) {
    const form = useForm({
        fingerprint: request.request_fingerprint,
        confirmation_note: '',
    });
    const errors = form.errors as Record<string, string | undefined>;
    const metrics = requestMetrics(request, locale);

    return (
        <article className="space-y-4 rounded-lg border p-4">
            <header className="flex items-start justify-between gap-3">
                <div>
                    <div className="flex items-center gap-2">
                        <ClipboardCheck className="size-5 text-primary" />
                        <h2 className="font-semibold">
                            {areaLabel(request.area, locale)}
                        </h2>
                    </div>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {request.scenario.name} · v{request.scenario.version} ·{' '}
                        {request.scenario.horizon_starts_on} →{' '}
                        {request.scenario.horizon_ends_on}
                    </p>
                </div>
                <Badge
                    variant={
                        request.status === 'confirmed' ? 'default' : 'secondary'
                    }
                >
                    {translate(
                        request.status === 'confirmed'
                            ? 'Confirmed request'
                            : 'Draft request',
                        locale,
                    )}
                </Badge>
            </header>

            <div className="grid grid-cols-2 gap-2">
                {metrics.map((metric) => (
                    <div key={metric.label} className="rounded-md bg-muted p-3">
                        <div className="text-xs text-muted-foreground">
                            {metric.label}
                        </div>
                        <div className="font-semibold">{metric.value}</div>
                    </div>
                ))}
            </div>

            <div className="rounded-md border border-amber-300 bg-amber-50 p-3 text-xs text-amber-950 dark:bg-amber-950/20 dark:text-amber-100">
                <LockKeyhole className="mr-2 inline size-4" />
                {translate(
                    'This request is non-committing and cannot create schedules, reservations, purchases or forecasts.',
                    locale,
                )}
            </div>

            {request.status === 'confirmed' ? (
                <>
                    <div className="rounded-md border border-emerald-300 bg-emerald-50 p-3 text-sm dark:bg-emerald-950/20">
                        <p className="flex items-center gap-2 font-medium">
                            <CheckCircle2 className="size-4" />
                            {translate('Confirmed by', locale)}{' '}
                            {request.confirmer?.name}
                        </p>
                        <p className="mt-1 text-xs text-muted-foreground">
                            {request.confirmation_note}
                        </p>
                    </div>
                    <MaterializationControl
                        key={request.materialization_fingerprint ?? 'pending'}
                        request={request}
                        locale={locale}
                    />
                </>
            ) : (
                <form
                    className="space-y-3"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.post(
                            `/planning-requests/${request.id}/confirmation`,
                            { preserveScroll: true },
                        );
                    }}
                >
                    <div>
                        <Label htmlFor={`confirmation_note_${request.id}`}>
                            {translate('Confirmation note', locale)}
                        </Label>
                        <Textarea
                            id={`confirmation_note_${request.id}`}
                            value={form.data.confirmation_note}
                            onChange={(event) =>
                                form.setData(
                                    'confirmation_note',
                                    event.target.value,
                                )
                            }
                            minLength={3}
                            maxLength={1000}
                            rows={3}
                            required
                        />
                        <InputError message={form.errors.confirmation_note} />
                        <InputError message={errors.confirmation} />
                    </div>
                    <Button type="submit" disabled={form.processing}>
                        <CheckCircle2 />
                        {translate('Confirm area request', locale)}
                    </Button>
                </form>
            )}
        </article>
    );
}

function MaterializationControl({
    request,
    locale,
}: {
    request: ScenarioRequest;
    locale: 'en' | 'es';
}) {
    const prepareForm = useForm({ fingerprint: request.request_fingerprint });
    const authorizeForm = useForm({
        fingerprint: request.materialization_fingerprint ?? '',
        authorization_note: '',
    });
    const executionForm = useForm({
        fingerprint: request.materialization_fingerprint ?? '',
        idempotency_key: request.idempotency_key ?? '',
    });
    const promotionForm = useForm({
        fingerprint: request.execution_fingerprint ?? '',
    });
    const prepareErrors = prepareForm.errors as Record<
        string,
        string | undefined
    >;
    const authorizationErrors = authorizeForm.errors as Record<
        string,
        string | undefined
    >;
    const preview = request.materialization_preview;
    const conflicts = preview?.state.conflicts ?? [];

    if (request.execution_status === 'executed') {
        const createdCount =
            request.execution_result?.activity_ids?.length ??
            request.execution_result?.reservation_ids?.length ??
            request.execution_result?.drafts?.length ??
            0;

        const requiresPromotion = ['purchases', 'finance'].includes(
            request.area,
        );

        return (
            <div className="space-y-3 rounded-md border border-emerald-300 bg-emerald-50 p-3 text-sm dark:bg-emerald-950/20">
                <p className="flex items-center gap-2 font-medium">
                    <CheckCircle2 className="size-4" />
                    {translate('Executed by', locale)} {request.executor?.name}
                </p>
                <p className="text-xs text-muted-foreground">
                    {translate('Created or prepared items', locale)}:{' '}
                    {createdCount}
                </p>
                <code className="block text-[11px] break-all text-muted-foreground">
                    {translate('Idempotency key', locale)}:{' '}
                    {request.idempotency_key}
                </code>
                <div className="border-t pt-3">
                    <p className="text-xs font-medium">
                        {translate('Live outcome tracking', locale)}
                    </p>
                    <div className="mt-2 flex flex-wrap gap-1.5">
                        {Object.entries(
                            request.outcome_monitoring.statuses,
                        ).map(([status, count]) => (
                            <Badge key={status} variant="outline">
                                {translate(statusLabel(status), locale)}:{' '}
                                {count}
                            </Badge>
                        ))}
                        {request.outcome_monitoring.overdue ? (
                            <Badge variant="destructive">
                                {translate('Overdue', locale)}:{' '}
                                {request.outcome_monitoring.overdue}
                            </Badge>
                        ) : null}
                        {request.outcome_monitoring.expired ? (
                            <Badge variant="destructive">
                                {translate('Expired', locale)}:{' '}
                                {request.outcome_monitoring.expired}
                            </Badge>
                        ) : null}
                    </div>
                </div>
                {request.outcome_monitoring.exceptions.length > 0 && (
                    <div className="space-y-1 border-t pt-3 text-xs text-destructive">
                        {request.outcome_monitoring.exceptions.map(
                            (exception) => (
                                <p
                                    key={exception.label}
                                    className="flex items-center gap-1.5"
                                >
                                    <AlertTriangle className="size-3.5" />
                                    {translate(exception.label, locale)}:{' '}
                                    {exception.count}
                                </p>
                            ),
                        )}
                    </div>
                )}
                {(request.outcome_monitoring.comparison?.length ?? 0) > 0 && (
                    <div className="grid grid-cols-2 gap-2 border-t pt-3 text-xs">
                        {request.outcome_monitoring.comparison?.map((row) => (
                            <div
                                key={row.label}
                                className="rounded bg-background/70 p-2"
                            >
                                <p className="text-muted-foreground">
                                    {translate(row.label, locale)}
                                </p>
                                <p className="mt-1 font-medium">
                                    {formatComparisonValue(
                                        row.current,
                                        row.unit,
                                        locale,
                                    )}{' '}
                                    /{' '}
                                    {formatComparisonValue(
                                        row.planned,
                                        row.unit,
                                        locale,
                                    )}
                                </p>
                                <p className="text-[10px] text-muted-foreground">
                                    {translate('Current / approved', locale)}
                                </p>
                            </div>
                        ))}
                    </div>
                )}
                {request.outcome_monitoring.direct_url && (
                    <Button variant="outline" size="sm" asChild>
                        <Link href={request.outcome_monitoring.direct_url}>
                            <ExternalLink />
                            {translate('Open generated resources', locale)}
                        </Link>
                    </Button>
                )}
                {requiresPromotion &&
                    request.promotion_status === 'pending' && (
                        <form
                            className="border-t pt-3"
                            onSubmit={(event) => {
                                event.preventDefault();
                                promotionForm.post(
                                    `/planning-requests/${request.id}/promotion`,
                                    { preserveScroll: true },
                                );
                            }}
                        >
                            <InputError
                                message={
                                    (
                                        promotionForm.errors as Record<
                                            string,
                                            string | undefined
                                        >
                                    ).promotion
                                }
                            />
                            <Button
                                type="submit"
                                disabled={promotionForm.processing}
                            >
                                <SendToBack />
                                {translate(
                                    'Promote to native workflow',
                                    locale,
                                )}
                            </Button>
                        </form>
                    )}
                {requiresPromotion &&
                    request.promotion_status === 'promoted' && (
                        <p className="border-t pt-3 text-xs">
                            <CheckCircle2 className="mr-1 inline size-4" />
                            {translate('Promoted by', locale)}{' '}
                            {request.promoter?.name} ·{' '}
                            {request.area === 'purchases'
                                ? translate('Purchase RFQs', locale)
                                : translate('Financial forecast items', locale)}
                            :{' '}
                            {request.promotion_result?.purchase_document_ids
                                ?.length ??
                                request.promotion_result
                                    ?.financial_forecast_item_ids?.length ??
                                0}
                        </p>
                    )}
            </div>
        );
    }

    if (request.materialization_status === 'authorized') {
        return (
            <div className="space-y-2 rounded-md border border-primary/30 bg-primary/5 p-3 text-sm">
                <p className="flex items-center gap-2 font-medium">
                    <ShieldCheck className="size-4" />
                    {translate('Execution authorized by', locale)}{' '}
                    {request.authorizer?.name}
                </p>
                <p className="text-xs text-muted-foreground">
                    {request.authorization_note}
                </p>
                <code className="block text-[11px] break-all text-muted-foreground">
                    {translate('Idempotency key', locale)}:{' '}
                    {request.idempotency_key}
                </code>
                <form
                    className="border-t pt-3"
                    onSubmit={(event) => {
                        event.preventDefault();
                        executionForm.post(
                            `/planning-requests/${request.id}/execution`,
                            { preserveScroll: true },
                        );
                    }}
                >
                    <InputError
                        message={
                            (
                                executionForm.errors as Record<
                                    string,
                                    string | undefined
                                >
                            ).execution
                        }
                    />
                    <Button
                        type="submit"
                        variant="destructive"
                        disabled={executionForm.processing}
                    >
                        <Play />
                        {translate('Execute authorized request', locale)}
                    </Button>
                </form>
            </div>
        );
    }

    return (
        <div className="space-y-3 rounded-md border p-3">
            <div>
                <h3 className="flex items-center gap-2 text-sm font-medium">
                    <Eye className="size-4" />
                    {translate('Materialization preview', locale)}
                </h3>
                <p className="text-xs text-muted-foreground">
                    {translate(
                        'Recalculate current capacity, stock, suppliers or accounting periods before a second user authorizes execution.',
                        locale,
                    )}
                </p>
            </div>

            {preview && (
                <div className="space-y-2">
                    <div className="grid grid-cols-2 gap-2 text-sm">
                        <div className="rounded bg-muted p-2">
                            <div className="text-xs text-muted-foreground">
                                {translate('Items reviewed', locale)}
                            </div>
                            <div className="font-semibold">
                                {preview.state.summary.items}
                            </div>
                        </div>
                        <div className="rounded bg-muted p-2">
                            <div className="text-xs text-muted-foreground">
                                {translate('Blocking conflicts', locale)}
                            </div>
                            <div className="font-semibold">
                                {preview.state.summary.blocking_conflicts}
                            </div>
                        </div>
                    </div>
                    {conflicts.map((conflict) => (
                        <p
                            key={`${conflict.code}-${conflict.subject}`}
                            className="text-xs text-destructive"
                        >
                            {conflictLabel(conflict.code, locale)} ·{' '}
                            {conflict.subject}
                        </p>
                    ))}
                    <code className="block text-[11px] break-all text-muted-foreground">
                        {translate('Idempotency key', locale)}:{' '}
                        {request.idempotency_key}
                    </code>
                </div>
            )}

            <form
                onSubmit={(event) => {
                    event.preventDefault();
                    prepareForm.post(
                        `/planning-requests/${request.id}/materialization`,
                        { preserveScroll: true },
                    );
                }}
            >
                <InputError message={prepareErrors.materialization} />
                <Button
                    type="submit"
                    variant="outline"
                    disabled={prepareForm.processing}
                >
                    {preview ? <RefreshCw /> : <Eye />}
                    {translate(
                        preview ? 'Refresh preview' : 'Prepare preview',
                        locale,
                    )}
                </Button>
            </form>

            {preview && preview.state.summary.blocking_conflicts === 0 && (
                <form
                    className="space-y-2 border-t pt-3"
                    onSubmit={(event) => {
                        event.preventDefault();
                        authorizeForm.post(
                            `/planning-requests/${request.id}/materialization/authorization`,
                            { preserveScroll: true },
                        );
                    }}
                >
                    <Label htmlFor={`authorization_note_${request.id}`}>
                        {translate('Authorization note', locale)}
                    </Label>
                    <Textarea
                        id={`authorization_note_${request.id}`}
                        value={authorizeForm.data.authorization_note}
                        onChange={(event) =>
                            authorizeForm.setData(
                                'authorization_note',
                                event.target.value,
                            )
                        }
                        minLength={3}
                        maxLength={1000}
                        rows={3}
                        required
                    />
                    <InputError
                        message={authorizeForm.errors.authorization_note}
                    />
                    <InputError message={authorizationErrors.authorization} />
                    <Button type="submit" disabled={authorizeForm.processing}>
                        <ShieldCheck />
                        {translate('Grant second authorization', locale)}
                    </Button>
                </form>
            )}
        </div>
    );
}

function statusLabel(status: string) {
    return status
        .split('_')
        .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
        .join(' ');
}

function formatComparisonValue(
    value: number,
    unit: 'items' | 'units' | 'currency',
    locale: 'en' | 'es',
) {
    return new Intl.NumberFormat(locale === 'es' ? 'es-ES' : 'en-GB', {
        style: unit === 'currency' ? 'currency' : 'decimal',
        currency: unit === 'currency' ? 'EUR' : undefined,
        maximumFractionDigits: unit === 'items' ? 0 : unit === 'units' ? 3 : 2,
    }).format(value);
}

function conflictLabel(code: string, locale: 'en' | 'es') {
    return translate(
        code === 'capacity_shortage'
            ? 'Insufficient capacity'
            : code === 'stock_shortage'
              ? 'Insufficient stock'
              : code === 'missing_supplier'
                ? 'No active supplier'
                : code === 'missing_installation'
                  ? 'Missing installation'
                  : code === 'missing_warehouse'
                    ? 'Missing active warehouse'
                    : code === 'opportunity_unavailable'
                      ? 'Opportunity is no longer open'
                      : 'Closed accounting period',
        locale,
    );
}

function requestMetrics(
    request: ScenarioRequest,
    locale: 'en' | 'es',
): Array<{ label: string; value: string }> {
    if (request.area === 'operations') {
        return [
            {
                label: translate('Capacity demand', locale),
                value: `${request.payload.capacity?.scenario_demand_hours ?? 0} h`,
            },
            {
                label: translate('Scenario overload', locale),
                value: `${request.payload.capacity?.scenario_overload_hours ?? 0} h`,
            },
        ];
    }

    if (request.area === 'inventory') {
        return [
            {
                label: translate('Expected materials', locale),
                value: String(request.payload.materials?.length ?? 0),
            },
        ];
    }

    if (request.area === 'purchases') {
        return [
            {
                label: translate('Expected material cost', locale),
                value: `${request.payload.expected_material_cost ?? 0} €`,
            },
            {
                label: translate('Products at risk', locale),
                value: String(request.payload.shortages?.length ?? 0),
            },
        ];
    }

    return [
        {
            label: translate('Weighted revenue', locale),
            value: `${request.payload.summary?.weighted_revenue ?? 0} €`,
        },
        {
            label: translate('Expected cash by month', locale),
            value: String(request.payload.cash_by_month?.length ?? 0),
        },
    ];
}

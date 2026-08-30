import { Head, useForm } from '@inertiajs/react';
import { CheckCircle2, ClipboardCheck, LockKeyhole } from 'lucide-react';
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

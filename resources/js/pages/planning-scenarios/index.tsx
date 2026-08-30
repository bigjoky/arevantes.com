import { Head, Link, useForm } from '@inertiajs/react';
import {
    CheckCircle2,
    ClipboardCheck,
    Clock3,
    GitCompareArrows,
    LockKeyhole,
    ShieldCheck,
} from 'lucide-react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import { useLocale } from '@/hooks/use-locale';
import { translate } from '@/i18n';

type Opportunity = {
    id: number;
    code: string;
    title: string;
    stage: string;
    probability: number;
    estimated_value: string;
    expected_close_on: string | null;
    customer: {
        first_name: string;
        last_name: string | null;
        company_name: string | null;
    } | null;
    installation: { service_type: string } | null;
    quote: { id: number; number: string; amount: string } | null;
};

type Scenario = {
    id: number;
    name: string;
    version: number;
    status: string;
    scenario_kind: 'base' | 'risk' | 'alternative';
    baseline_scenario_id: number | null;
    baseline_scenario: { id: number; name: string; version: number } | null;
    horizon_starts_on: string;
    horizon_ends_on: string;
    assumptions: {
        capacity_hours_per_opportunity: number;
        material_cost_percent_without_quote: number;
        collection_delay_days: number;
    };
    result_snapshot: {
        summary: {
            opportunities: number;
            confirmed_value: number;
            weighted_revenue: number;
            capacity_hours: number;
            expected_material_cost: number;
            quoted_opportunities: number;
        };
        materials: Array<{
            product_id: number;
            sku: string | null;
            name: string;
            expected_quantity: number;
        }>;
        cash_by_month: Array<{
            month: string;
            expected_inflow: number;
            expected_material_outflow: number;
        }>;
        non_committing: boolean;
    };
    reference_snapshot: {
        captured_at: string;
        capacity: {
            period: { from: string; to: string; weeks: number };
            available_hours: number;
            planned_hours: number;
            remaining_hours: number;
            scenario_demand_hours: number;
            scenario_overload_hours: number;
        };
        stock: {
            products: Array<{
                product_id: number;
                sku: string | null;
                name: string;
                expected_quantity: number;
                free_quantity: number;
                shortage_quantity: number;
            }>;
            products_at_risk: number;
            expected_units: number;
            shortage_units: number;
        };
    } | null;
    comparison_snapshot: {
        baseline_id: number;
        baseline_name: string;
        baseline_version: number;
        deltas: {
            confirmed_value: number;
            weighted_revenue: number;
            capacity_hours: number;
            expected_material_cost: number;
        };
        capacity: {
            remaining_hours_delta: number;
            overload_hours_delta: number;
        };
        stock: {
            products_at_risk_delta: number;
            shortage_units_delta: number;
        };
        portfolio: {
            added_opportunity_ids: number[];
            removed_opportunity_ids: number[];
        };
    } | null;
    fingerprint: string;
    reference_valid_until: string | null;
    approved_fingerprint: string | null;
    approval_note: string | null;
    approved_at: string | null;
    approver: { id: number; name: string } | null;
    closure_snapshot: OutcomeSnapshot | null;
    closure_fingerprint: string | null;
    closure_note: string | null;
    closed_at: string | null;
    closer: { id: number; name: string } | null;
    reviews: ScenarioReview[];
    approval_eligibility: {
        can_approve: boolean;
        integrity_valid: boolean;
        reference_fresh: boolean;
    };
    action_requests: Array<{
        id: number;
        area: 'operations' | 'inventory' | 'purchases' | 'finance';
        status: 'draft' | 'confirmed';
    }>;
    creator: { id: number; name: string };
    created_at: string;
};

type OutcomeSnapshot = {
    captured_at: string;
    summary: {
        requests: number;
        executed: number;
        resources: number;
        pending_exceptions: number;
    };
};

type ScenarioReview = {
    id: number;
    sequence: number;
    outcome_snapshot: OutcomeSnapshot;
    fingerprint: string;
    explanation: string;
    reviewed_at: string;
    creator: { id: number; name: string };
};

const money = (value: number, locale: 'en' | 'es') =>
    new Intl.NumberFormat(locale === 'es' ? 'es-ES' : 'en-GB', {
        style: 'currency',
        currency: 'EUR',
    }).format(value);

const scenarioKindLabel = (
    kind: Scenario['scenario_kind'],
    locale: 'en' | 'es',
) =>
    translate(
        kind === 'risk'
            ? 'Risk scenario'
            : kind === 'alternative'
              ? 'Alternative scenario'
              : 'Base scenario',
        locale,
    );

const signed = (value: number, suffix = '') =>
    `${value > 0 ? '+' : ''}${value}${suffix}`;

export default function PlanningScenarioIndex({
    scenarios,
    selectedScenario,
    opportunities,
    defaults,
}: {
    scenarios: Scenario[];
    selectedScenario: Scenario | null;
    opportunities: Opportunity[];
    defaults: { horizon_starts_on: string; horizon_ends_on: string };
}) {
    const { locale } = useLocale();
    const form = useForm({
        name: '',
        scenario_kind: 'base' as 'base' | 'risk' | 'alternative',
        baseline_scenario_id: '',
        horizon_starts_on: defaults.horizon_starts_on,
        horizon_ends_on: defaults.horizon_ends_on,
        opportunity_ids: [] as number[],
        capacity_hours_per_opportunity: '40',
        material_cost_percent_without_quote: '35',
        collection_delay_days: '30',
    });
    const baselineScenarios = scenarios.filter(
        (scenario) => scenario.scenario_kind === 'base',
    );
    const isComparisonScenario = form.data.scenario_kind !== 'base';
    const toggle = (id: number) =>
        form.setData(
            'opportunity_ids',
            form.data.opportunity_ids.includes(id)
                ? form.data.opportunity_ids.filter((value) => value !== id)
                : [...form.data.opportunity_ids, id],
        );

    return (
        <>
            <Head title={translate('Planning scenarios', locale)} />
            <div className="flex min-w-0 flex-1 flex-col gap-6 p-4 md:p-6">
                <header>
                    <div className="flex items-center gap-2">
                        <GitCompareArrows className="size-6 text-primary" />
                        <h1 className="text-2xl font-semibold">
                            {translate('Planning scenarios', locale)}
                        </h1>
                    </div>
                    <p className="mt-1 max-w-3xl text-sm text-muted-foreground">
                        {translate(
                            'Freeze a commercial portfolio and compare revenue, capacity, materials and cash without committing resources.',
                            locale,
                        )}
                    </p>
                </header>

                <section className="rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-950 dark:bg-amber-950/20 dark:text-amber-100">
                    <div className="flex gap-3">
                        <LockKeyhole className="mt-0.5 size-5 shrink-0" />
                        <div>
                            <div className="font-medium">
                                {translate('Non-committing analysis', locale)}
                            </div>
                            <p>
                                {translate(
                                    'Saving a scenario never creates work orders, reservations, purchases, payments or customer communications.',
                                    locale,
                                )}
                            </p>
                        </div>
                    </div>
                </section>

                <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(24rem,0.8fr)]">
                    <form
                        className="space-y-5 rounded-lg border p-4"
                        onSubmit={(event) => {
                            event.preventDefault();
                            form.post('/planning-scenarios', {
                                preserveScroll: true,
                            });
                        }}
                    >
                        <div>
                            <h2 className="font-semibold">
                                {translate('Freeze new scenario', locale)}
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                {translate(
                                    'Confirmed data is copied once; assumptions remain visible and auditable.',
                                    locale,
                                )}
                            </p>
                        </div>
                        <div className="grid gap-4 md:grid-cols-3">
                            <div className="md:col-span-3">
                                <Label htmlFor="name">
                                    {translate('Scenario name', locale)}
                                </Label>
                                <Input
                                    id="name"
                                    value={form.data.name}
                                    onChange={(event) =>
                                        form.setData('name', event.target.value)
                                    }
                                />
                                <InputError message={form.errors.name} />
                            </div>
                            <div>
                                <Label>
                                    {translate('Scenario type', locale)}
                                </Label>
                                <Select
                                    value={form.data.scenario_kind}
                                    onValueChange={(value) => {
                                        form.setData(
                                            'scenario_kind',
                                            value as
                                                | 'base'
                                                | 'risk'
                                                | 'alternative',
                                        );

                                        if (value === 'base') {
                                            form.setData(
                                                'baseline_scenario_id',
                                                '',
                                            );
                                        }
                                    }}
                                >
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="base">
                                            {translate('Base scenario', locale)}
                                        </SelectItem>
                                        <SelectItem value="risk">
                                            {translate('Risk scenario', locale)}
                                        </SelectItem>
                                        <SelectItem value="alternative">
                                            {translate(
                                                'Alternative scenario',
                                                locale,
                                            )}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            {isComparisonScenario && (
                                <div className="md:col-span-2">
                                    <Label>
                                        {translate(
                                            'Compare with base scenario',
                                            locale,
                                        )}
                                    </Label>
                                    <Select
                                        value={form.data.baseline_scenario_id}
                                        onValueChange={(value) =>
                                            form.setData(
                                                'baseline_scenario_id',
                                                value,
                                            )
                                        }
                                    >
                                        <SelectTrigger>
                                            <SelectValue
                                                placeholder={translate(
                                                    'Select base scenario',
                                                    locale,
                                                )}
                                            />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {baselineScenarios.map(
                                                (scenario) => (
                                                    <SelectItem
                                                        key={scenario.id}
                                                        value={String(
                                                            scenario.id,
                                                        )}
                                                    >
                                                        {scenario.name} · v
                                                        {scenario.version}
                                                    </SelectItem>
                                                ),
                                            )}
                                        </SelectContent>
                                    </Select>
                                    <InputError
                                        message={
                                            form.errors.baseline_scenario_id
                                        }
                                    />
                                </div>
                            )}
                            <Field
                                label={translate('Horizon start', locale)}
                                type="date"
                                value={form.data.horizon_starts_on}
                                onChange={(value) =>
                                    form.setData('horizon_starts_on', value)
                                }
                            />
                            <Field
                                label={translate('Horizon end', locale)}
                                type="date"
                                value={form.data.horizon_ends_on}
                                onChange={(value) =>
                                    form.setData('horizon_ends_on', value)
                                }
                            />
                            <Field
                                label={translate(
                                    'Collection delay (days)',
                                    locale,
                                )}
                                type="number"
                                value={form.data.collection_delay_days}
                                onChange={(value) =>
                                    form.setData('collection_delay_days', value)
                                }
                            />
                            <Field
                                label={translate(
                                    'Hours per opportunity',
                                    locale,
                                )}
                                type="number"
                                value={form.data.capacity_hours_per_opportunity}
                                onChange={(value) =>
                                    form.setData(
                                        'capacity_hours_per_opportunity',
                                        value,
                                    )
                                }
                            />
                            <Field
                                label={translate(
                                    'Material cost without quote (%)',
                                    locale,
                                )}
                                type="number"
                                value={
                                    form.data
                                        .material_cost_percent_without_quote
                                }
                                onChange={(value) =>
                                    form.setData(
                                        'material_cost_percent_without_quote',
                                        value,
                                    )
                                }
                            />
                        </div>

                        <div>
                            <Label>
                                {translate('Open opportunities', locale)}
                            </Label>
                            <div className="mt-2 max-h-96 space-y-2 overflow-y-auto rounded-md border p-2">
                                {opportunities.map((opportunity) => (
                                    <label
                                        key={opportunity.id}
                                        className="flex cursor-pointer items-start gap-3 rounded-md p-2 hover:bg-muted"
                                    >
                                        <Checkbox
                                            checked={form.data.opportunity_ids.includes(
                                                opportunity.id,
                                            )}
                                            onCheckedChange={() =>
                                                toggle(opportunity.id)
                                            }
                                        />
                                        <span className="min-w-0 flex-1">
                                            <span className="block font-medium">
                                                {opportunity.code} ·{' '}
                                                {opportunity.title}
                                            </span>
                                            <span className="text-xs text-muted-foreground">
                                                {opportunity.probability}% ·{' '}
                                                {money(
                                                    Number(
                                                        opportunity.quote
                                                            ?.amount ??
                                                            opportunity.estimated_value,
                                                    ),
                                                    locale,
                                                )}{' '}
                                                ·{' '}
                                                {opportunity.quote
                                                    ? opportunity.quote.number
                                                    : translate(
                                                          'No confirmed quote',
                                                          locale,
                                                      )}
                                            </span>
                                        </span>
                                    </label>
                                ))}
                                {opportunities.length === 0 && (
                                    <p className="p-3 text-sm text-muted-foreground">
                                        {translate(
                                            'No open opportunities are available.',
                                            locale,
                                        )}
                                    </p>
                                )}
                            </div>
                            <InputError message={form.errors.opportunity_ids} />
                        </div>
                        <Button
                            type="submit"
                            disabled={
                                form.processing || opportunities.length === 0
                            }
                        >
                            <LockKeyhole />
                            {translate('Freeze scenario', locale)}
                        </Button>
                    </form>

                    <div className="space-y-4">
                        <section className="rounded-lg border p-4">
                            <h2 className="font-semibold">
                                {translate('Frozen scenarios', locale)}
                            </h2>
                            <div className="mt-3 space-y-2">
                                {scenarios.map((scenario) => (
                                    <Button
                                        key={scenario.id}
                                        variant={
                                            selectedScenario?.id === scenario.id
                                                ? 'secondary'
                                                : 'ghost'
                                        }
                                        className="h-auto w-full justify-between"
                                        asChild
                                    >
                                        <Link
                                            href={`/planning-scenarios?scenario=${scenario.id}`}
                                        >
                                            <span className="text-left">
                                                <span className="block">
                                                    {scenario.name} · v
                                                    {scenario.version}
                                                </span>
                                                <span className="text-xs text-muted-foreground">
                                                    {scenario.creator.name}
                                                </span>
                                            </span>
                                            <Badge variant="outline">
                                                {scenario.status === 'closed'
                                                    ? translate(
                                                          'Scenario closed',
                                                          locale,
                                                      )
                                                    : scenario.status ===
                                                        'approved'
                                                      ? translate(
                                                            'Scenario approved',
                                                            locale,
                                                        )
                                                      : scenarioKindLabel(
                                                            scenario.scenario_kind,
                                                            locale,
                                                        )}
                                            </Badge>
                                        </Link>
                                    </Button>
                                ))}
                            </div>
                        </section>
                        {selectedScenario && (
                            <ScenarioResult
                                key={selectedScenario.id}
                                scenario={selectedScenario}
                                locale={locale}
                            />
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}

function Field({
    label,
    type,
    value,
    onChange,
}: {
    label: string;
    type: string;
    value: string;
    onChange: (value: string) => void;
}) {
    return (
        <div>
            <Label>{label}</Label>
            <Input
                type={type}
                min={type === 'number' ? 0 : undefined}
                value={value}
                onChange={(event) => onChange(event.target.value)}
            />
        </div>
    );
}

function ScenarioResult({
    scenario,
    locale,
}: {
    scenario: Scenario;
    locale: 'en' | 'es';
}) {
    const summary = scenario.result_snapshot.summary;
    const approvalForm = useForm({
        fingerprint: scenario.fingerprint,
        approval_note: '',
    });
    const approvalErrors = approvalForm.errors as Record<
        string,
        string | undefined
    >;
    const requestForm = useForm({
        fingerprint: scenario.approved_fingerprint ?? '',
    });
    const requestErrors = requestForm.errors as Record<
        string,
        string | undefined
    >;

    return (
        <section className="space-y-4 rounded-lg border p-4">
            <div className="flex items-start justify-between gap-3">
                <div>
                    <h2 className="font-semibold">
                        {scenario.name} · v{scenario.version}
                    </h2>
                    <p className="text-xs text-muted-foreground">
                        {scenario.horizon_starts_on} →{' '}
                        {scenario.horizon_ends_on}
                    </p>
                </div>
                <div className="flex items-center gap-2">
                    <Badge variant="outline">
                        {scenarioKindLabel(scenario.scenario_kind, locale)}
                    </Badge>
                    {scenario.status === 'closed' ? (
                        <LockKeyhole className="size-5 text-slate-600" />
                    ) : scenario.status === 'approved' ? (
                        <ShieldCheck className="size-5 text-emerald-600" />
                    ) : (
                        <CheckCircle2 className="size-5 text-sky-600" />
                    )}
                </div>
            </div>
            <div className="rounded-md border bg-muted/30 p-3 text-sm">
                <div className="flex items-center justify-between gap-3">
                    <span className="font-medium">
                        {scenario.status === 'closed'
                            ? translate('Scenario closed', locale)
                            : scenario.status === 'approved'
                              ? translate('Scenario approved', locale)
                              : translate('Frozen', locale)}
                    </span>
                    {scenario.reference_valid_until && (
                        <span className="flex items-center gap-1 text-xs text-muted-foreground">
                            <Clock3 className="size-3.5" />
                            {translate('References valid until', locale)}{' '}
                            {scenario.reference_valid_until
                                .replace('T', ' ')
                                .slice(0, 16)}
                        </span>
                    )}
                </div>
                {(scenario.status === 'approved' ||
                    scenario.status === 'closed') &&
                scenario.approver ? (
                    <div className="mt-1 space-y-1 text-xs text-muted-foreground">
                        <p>
                            {translate('Approved by', locale)}{' '}
                            {scenario.approver.name} ·{' '}
                            {scenario.approved_at
                                ?.replace('T', ' ')
                                .slice(0, 16)}
                        </p>
                        {scenario.approval_note && (
                            <p>{scenario.approval_note}</p>
                        )}
                        {scenario.approved_fingerprint && (
                            <p className="font-mono break-all">
                                {translate('Approved fingerprint', locale)}:{' '}
                                {scenario.approved_fingerprint}
                            </p>
                        )}
                    </div>
                ) : !scenario.reference_valid_until ? (
                    <p className="mt-1 text-xs text-amber-700 dark:text-amber-300">
                        {translate(
                            'This scenario predates controlled approval. Freeze a new scenario.',
                            locale,
                        )}
                    </p>
                ) : !scenario.approval_eligibility.integrity_valid ? (
                    <p className="mt-1 text-xs text-destructive">
                        {translate('Scenario integrity check failed.', locale)}
                    </p>
                ) : !scenario.approval_eligibility.reference_fresh &&
                  scenario.status === 'frozen' ? (
                    <p className="mt-1 text-xs text-amber-700 dark:text-amber-300">
                        {translate(
                            'Reference expired. Freeze a new scenario before approval.',
                            locale,
                        )}
                    </p>
                ) : null}
            </div>
            <div className="grid grid-cols-2 gap-3">
                <Metric
                    label={translate('Confirmed value', locale)}
                    value={money(summary.confirmed_value, locale)}
                />
                <Metric
                    label={translate('Weighted revenue', locale)}
                    value={money(summary.weighted_revenue, locale)}
                />
                <Metric
                    label={translate('Capacity demand', locale)}
                    value={`${summary.capacity_hours} h`}
                />
                <Metric
                    label={translate('Expected material cost', locale)}
                    value={money(summary.expected_material_cost, locale)}
                />
            </div>
            {scenario.reference_snapshot && (
                <div className="space-y-3 rounded-md border p-3">
                    <div>
                        <h3 className="text-sm font-medium">
                            {translate('Capacity reference', locale)}
                        </h3>
                        <p className="text-xs text-muted-foreground">
                            {translate('Captured at', locale)}{' '}
                            {scenario.reference_snapshot.captured_at
                                .replace('T', ' ')
                                .slice(0, 16)}
                        </p>
                    </div>
                    <div className="grid grid-cols-2 gap-2 text-sm">
                        <Metric
                            label={translate('Remaining capacity', locale)}
                            value={`${scenario.reference_snapshot.capacity.remaining_hours} h`}
                        />
                        <Metric
                            label={translate('Scenario overload', locale)}
                            value={`${scenario.reference_snapshot.capacity.scenario_overload_hours} h`}
                        />
                        <Metric
                            label={translate('Products at risk', locale)}
                            value={String(
                                scenario.reference_snapshot.stock
                                    .products_at_risk,
                            )}
                        />
                        <Metric
                            label={translate('Expected shortage', locale)}
                            value={String(
                                scenario.reference_snapshot.stock
                                    .shortage_units,
                            )}
                        />
                    </div>
                </div>
            )}
            {scenario.comparison_snapshot && (
                <div className="space-y-3 rounded-md border border-primary/30 bg-primary/5 p-3">
                    <div>
                        <h3 className="text-sm font-medium">
                            {translate('Difference from base scenario', locale)}
                        </h3>
                        <p className="text-xs text-muted-foreground">
                            {scenario.comparison_snapshot.baseline_name} · v
                            {scenario.comparison_snapshot.baseline_version}
                        </p>
                    </div>
                    <div className="grid grid-cols-2 gap-2 text-sm">
                        <Metric
                            label={translate('Weighted revenue', locale)}
                            value={money(
                                scenario.comparison_snapshot.deltas
                                    .weighted_revenue,
                                locale,
                            )}
                        />
                        <Metric
                            label={translate('Capacity demand', locale)}
                            value={signed(
                                scenario.comparison_snapshot.deltas
                                    .capacity_hours,
                                ' h',
                            )}
                        />
                        <Metric
                            label={translate('Overload variation', locale)}
                            value={signed(
                                scenario.comparison_snapshot.capacity
                                    .overload_hours_delta,
                                ' h',
                            )}
                        />
                        <Metric
                            label={translate('Shortage variation', locale)}
                            value={signed(
                                scenario.comparison_snapshot.stock
                                    .shortage_units_delta,
                            )}
                        />
                    </div>
                    <p className="text-xs text-muted-foreground">
                        {
                            scenario.comparison_snapshot.portfolio
                                .added_opportunity_ids.length
                        }{' '}
                        {translate('added opportunities', locale)} ·{' '}
                        {
                            scenario.comparison_snapshot.portfolio
                                .removed_opportunity_ids.length
                        }{' '}
                        {translate('removed opportunities', locale)}
                    </p>
                </div>
            )}
            <div>
                <h3 className="text-sm font-medium">
                    {translate('Expected materials', locale)}
                </h3>
                <div className="mt-2 space-y-1 text-sm">
                    {scenario.result_snapshot.materials.map((material) => (
                        <div
                            key={material.product_id}
                            className="flex justify-between gap-3 border-b py-1"
                        >
                            <span>
                                {material.sku ? `${material.sku} · ` : ''}
                                {material.name}
                            </span>
                            <span className="font-medium">
                                {material.expected_quantity}
                            </span>
                        </div>
                    ))}
                    {scenario.result_snapshot.materials.length === 0 && (
                        <p className="text-muted-foreground">
                            {translate(
                                'No confirmed material composition.',
                                locale,
                            )}
                        </p>
                    )}
                </div>
            </div>
            <div>
                <h3 className="text-sm font-medium">
                    {translate('Expected cash by month', locale)}
                </h3>
                <div className="mt-2 space-y-1 text-sm">
                    {scenario.result_snapshot.cash_by_month.map((row) => (
                        <div
                            key={row.month}
                            className="grid grid-cols-3 gap-2 border-b py-1"
                        >
                            <span>{row.month}</span>
                            <span className="text-right text-emerald-700">
                                +{money(row.expected_inflow, locale)}
                            </span>
                            <span className="text-right text-amber-700">
                                -{money(row.expected_material_outflow, locale)}
                            </span>
                        </div>
                    ))}
                </div>
            </div>
            <div className="rounded-md bg-muted p-3 text-xs">
                <div className="font-medium">
                    {translate('Visible assumptions', locale)}
                </div>
                <p>
                    {scenario.assumptions.capacity_hours_per_opportunity}{' '}
                    {translate('hours per opportunity', locale)} ·{' '}
                    {scenario.assumptions.material_cost_percent_without_quote}%{' '}
                    {translate('material without quote', locale)} ·{' '}
                    {scenario.assumptions.collection_delay_days}{' '}
                    {translate('collection days', locale)}
                </p>
            </div>
            {scenario.status === 'frozen' &&
                scenario.approval_eligibility.can_approve && (
                    <form
                        className="space-y-3 rounded-md border border-emerald-300 bg-emerald-50/60 p-3 dark:bg-emerald-950/20"
                        onSubmit={(event) => {
                            event.preventDefault();
                            approvalForm.post(
                                `/planning-scenarios/${scenario.id}/approval`,
                                { preserveScroll: true },
                            );
                        }}
                    >
                        <div>
                            <h3 className="text-sm font-medium">
                                {translate('Approve scenario', locale)}
                            </h3>
                            <p className="text-xs text-muted-foreground">
                                {translate(
                                    'Approval checks the frozen fingerprint and reference freshness without committing resources.',
                                    locale,
                                )}
                            </p>
                        </div>
                        <div>
                            <Label htmlFor="approval_note">
                                {translate('Approval note', locale)}
                            </Label>
                            <Textarea
                                id="approval_note"
                                value={approvalForm.data.approval_note}
                                onChange={(event) =>
                                    approvalForm.setData(
                                        'approval_note',
                                        event.target.value,
                                    )
                                }
                                maxLength={1000}
                                rows={3}
                            />
                            <InputError
                                message={approvalForm.errors.approval_note}
                            />
                            <InputError message={approvalErrors.approval} />
                        </div>
                        <Button
                            type="submit"
                            disabled={approvalForm.processing}
                        >
                            <ShieldCheck />
                            {translate('Approve scenario', locale)}
                        </Button>
                    </form>
                )}
            {scenario.status === 'approved' && (
                <div className="space-y-3 rounded-md border border-primary/30 bg-primary/5 p-3">
                    <div>
                        <h3 className="text-sm font-medium">
                            {translate('Area planning requests', locale)}
                        </h3>
                        <p className="text-xs text-muted-foreground">
                            {translate(
                                'Create separate non-committing drafts for operations, inventory, purchases and finance.',
                                locale,
                            )}
                        </p>
                    </div>
                    {scenario.action_requests.length === 0 ? (
                        <form
                            onSubmit={(event) => {
                                event.preventDefault();
                                requestForm.post(
                                    `/planning-scenarios/${scenario.id}/requests`,
                                    { preserveScroll: true },
                                );
                            }}
                        >
                            <InputError message={requestErrors.requests} />
                            <Button
                                type="submit"
                                disabled={requestForm.processing}
                            >
                                <ClipboardCheck />
                                {translate('Generate area drafts', locale)}
                            </Button>
                        </form>
                    ) : (
                        <div className="flex items-center justify-between gap-3">
                            <p className="text-sm">
                                {scenario.action_requests.length}{' '}
                                {translate('area requests generated', locale)}
                            </p>
                            <Button asChild variant="outline" size="sm">
                                <Link href="/planning-requests">
                                    {translate(
                                        'Open planning requests',
                                        locale,
                                    )}
                                </Link>
                            </Button>
                        </div>
                    )}
                </div>
            )}
            {(scenario.status === 'approved' ||
                scenario.status === 'closed') && (
                <ScenarioLifecycle scenario={scenario} locale={locale} />
            )}
        </section>
    );
}

function ScenarioLifecycle({
    scenario,
    locale,
}: {
    scenario: Scenario;
    locale: 'en' | 'es';
}) {
    const latestReview = scenario.reviews.at(-1) ?? null;
    const reviewForm = useForm({
        approved_fingerprint: scenario.approved_fingerprint ?? '',
        explanation: '',
    });
    const closureForm = useForm({
        approved_fingerprint: scenario.approved_fingerprint ?? '',
        review_fingerprint: latestReview?.fingerprint ?? '',
        closure_note: '',
        acknowledge_exceptions: false,
    });
    const reviewErrors = reviewForm.errors as Record<
        string,
        string | undefined
    >;
    const closureErrors = closureForm.errors as Record<
        string,
        string | undefined
    >;

    return (
        <div className="space-y-4 rounded-md border border-slate-300 p-3 dark:border-slate-700">
            <div>
                <h3 className="text-sm font-medium">
                    {translate('Scenario reviews', locale)}
                </h3>
                <p className="text-xs text-muted-foreground">
                    {translate(
                        'Each milestone freezes the current result and its explanation without rewriting the approved scenario.',
                        locale,
                    )}
                </p>
            </div>

            {scenario.reviews.length > 0 && (
                <div className="space-y-2">
                    {scenario.reviews.map((review) => (
                        <div
                            key={review.id}
                            className="rounded-md bg-muted/50 p-3 text-sm"
                        >
                            <div className="flex flex-wrap items-center justify-between gap-2">
                                <span className="font-medium">
                                    {translate('Review', locale)} #
                                    {review.sequence}
                                </span>
                                <span className="text-xs text-muted-foreground">
                                    {review.creator.name} ·{' '}
                                    {review.reviewed_at
                                        .replace('T', ' ')
                                        .slice(0, 16)}
                                </span>
                            </div>
                            <p className="mt-1">{review.explanation}</p>
                            <OutcomeSummary
                                snapshot={review.outcome_snapshot}
                                locale={locale}
                            />
                            <p className="mt-2 font-mono text-[10px] break-all text-muted-foreground">
                                {review.fingerprint}
                            </p>
                        </div>
                    ))}
                </div>
            )}

            {scenario.status === 'approved' &&
                scenario.action_requests.length > 0 && (
                    <form
                        className="space-y-3"
                        onSubmit={(event) => {
                            event.preventDefault();
                            reviewForm.post(
                                `/planning-scenarios/${scenario.id}/reviews`,
                                {
                                    preserveScroll: true,
                                    onSuccess: () =>
                                        reviewForm.reset('explanation'),
                                },
                            );
                        }}
                    >
                        <Label htmlFor={`review-explanation-${scenario.id}`}>
                            {translate('Review explanation', locale)}
                        </Label>
                        <Textarea
                            id={`review-explanation-${scenario.id}`}
                            value={reviewForm.data.explanation}
                            onChange={(event) =>
                                reviewForm.setData(
                                    'explanation',
                                    event.target.value,
                                )
                            }
                            rows={3}
                            maxLength={3000}
                            required
                        />
                        <InputError message={reviewForm.errors.explanation} />
                        <InputError
                            message={
                                reviewErrors.review ?? reviewErrors.lifecycle
                            }
                        />
                        <Button
                            type="submit"
                            variant="outline"
                            disabled={reviewForm.processing}
                        >
                            <ClipboardCheck />
                            {translate('Record review', locale)}
                        </Button>
                    </form>
                )}

            {scenario.status === 'approved' && latestReview && (
                <form
                    className="space-y-3 border-t pt-4"
                    onSubmit={(event) => {
                        event.preventDefault();
                        closureForm.post(
                            `/planning-scenarios/${scenario.id}/closure`,
                            { preserveScroll: true },
                        );
                    }}
                >
                    <div>
                        <h4 className="text-sm font-medium">
                            {translate('Close scenario horizon', locale)}
                        </h4>
                        <p className="text-xs text-muted-foreground">
                            {translate(
                                'Closure requires all area requests to be executed and preserves their native resources.',
                                locale,
                            )}
                        </p>
                    </div>
                    <Label htmlFor={`closure-note-${scenario.id}`}>
                        {translate('Closure note', locale)}
                    </Label>
                    <Textarea
                        id={`closure-note-${scenario.id}`}
                        value={closureForm.data.closure_note}
                        onChange={(event) =>
                            closureForm.setData(
                                'closure_note',
                                event.target.value,
                            )
                        }
                        rows={3}
                        maxLength={3000}
                        required
                    />
                    <InputError message={closureForm.errors.closure_note} />
                    <label className="flex items-start gap-2 text-sm">
                        <Checkbox
                            checked={closureForm.data.acknowledge_exceptions}
                            onCheckedChange={(checked) =>
                                closureForm.setData(
                                    'acknowledge_exceptions',
                                    checked === true,
                                )
                            }
                        />
                        <span>
                            {translate(
                                'I acknowledge pending exceptions and understand native resources will continue independently.',
                                locale,
                            )}
                        </span>
                    </label>
                    <InputError
                        message={closureForm.errors.acknowledge_exceptions}
                    />
                    <InputError
                        message={
                            closureErrors.closure ?? closureErrors.lifecycle
                        }
                    />
                    <Button type="submit" disabled={closureForm.processing}>
                        <LockKeyhole />
                        {translate('Close scenario', locale)}
                    </Button>
                </form>
            )}

            {scenario.status === 'closed' && scenario.closure_snapshot && (
                <div className="rounded-md border border-emerald-300 bg-emerald-50/60 p-3 text-sm dark:bg-emerald-950/20">
                    <div className="font-medium">
                        {translate('Scenario closed', locale)}
                    </div>
                    <p className="mt-1 text-xs text-muted-foreground">
                        {translate('Closed by', locale)} {scenario.closer?.name}{' '}
                        · {scenario.closed_at?.replace('T', ' ').slice(0, 16)}
                    </p>
                    <p className="mt-2">{scenario.closure_note}</p>
                    <OutcomeSummary
                        snapshot={scenario.closure_snapshot}
                        locale={locale}
                    />
                    <p className="mt-2 font-mono text-[10px] break-all text-muted-foreground">
                        {translate('Closure fingerprint', locale)}:{' '}
                        {scenario.closure_fingerprint}
                    </p>
                </div>
            )}
        </div>
    );
}

function OutcomeSummary({
    snapshot,
    locale,
}: {
    snapshot: OutcomeSnapshot;
    locale: 'en' | 'es';
}) {
    return (
        <div className="mt-2 grid grid-cols-2 gap-2 text-xs md:grid-cols-4">
            <span>
                {translate('Area requests', locale)}:{' '}
                {snapshot.summary.requests}
            </span>
            <span>
                {translate('Requests executed', locale)}:{' '}
                {snapshot.summary.executed}
            </span>
            <span>
                {translate('Resources tracked', locale)}:{' '}
                {snapshot.summary.resources}
            </span>
            <span>
                {translate('Pending exceptions', locale)}:{' '}
                {snapshot.summary.pending_exceptions}
            </span>
        </div>
    );
}

function Metric({ label, value }: { label: string; value: string }) {
    return (
        <div className="rounded-md bg-muted p-3">
            <div className="text-xs text-muted-foreground">{label}</div>
            <div className="font-semibold">{value}</div>
        </div>
    );
}

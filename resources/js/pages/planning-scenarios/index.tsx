import { Head, Link, useForm } from '@inertiajs/react';
import {
    CheckCircle2,
    ClipboardCheck,
    Clock3,
    GitCompareArrows,
    LockKeyhole,
    Sparkles,
    ShieldCheck,
    XCircle,
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
        calibration_reference?: {
            proposal_id: number;
            proposal_version: number;
            business_line: string;
            area: string;
            metric_label: string;
            assumption_key: AssumptionKey;
            previous_value: number;
            applied_value: number;
            source_fingerprint: string;
        };
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
    learning_summary: ScenarioLearning | null;
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

type ScenarioLearning = {
    source: 'immutable_closure';
    closure_fingerprint: string;
    auto_apply: false;
    summary: {
        metrics: number;
        aligned: number;
        watch: number;
        significant: number;
    };
    metrics: Array<{
        area: string;
        label: string;
        unit: 'currency' | 'units' | 'items' | string;
        planned: number;
        current: number;
        delta: number;
        delta_percent: number | null;
        severity: 'aligned' | 'watch' | 'significant';
    }>;
    recommendations: Array<{
        area: string;
        severity: 'watch' | 'significant';
        message: string;
        metric_labels: string[];
    }>;
};

type CalibrationProposal = {
    id: number;
    business_line: string;
    area: string;
    metric_label: string;
    unit: string;
    version: number;
    status: 'pending' | 'accepted' | 'rejected';
    governance_state: 'available' | 'active' | 'superseded' | 'insufficient';
    direction: 'higher' | 'lower';
    sample_size: number;
    evidence_snapshot: {
        minimum_sample_size: number;
        consistent_samples: number;
        direction_consistency: number;
        average_delta_percent: number;
        planned_total: number;
        observed_total: number;
        sources: Array<{
            scenario_id: number;
            scenario_name: string;
            scenario_version: number;
            closure_fingerprint: string;
            delta_percent: number;
        }>;
    };
    source_fingerprint: string;
    recommendation: string;
    assumption_key: AssumptionKey | null;
    accepted_value: number | null;
    decision_note: string | null;
    decided_at: string | null;
    auto_apply: false;
    creator: { id: number; name: string };
    decider: { id: number; name: string } | null;
    superseded_by: { id: number; version: number } | null;
    evaluations: CalibrationEvaluation[];
    governance_reviews: CalibrationGovernanceReview[];
};

type CalibrationGovernanceReview = {
    id: number;
    version: number;
    status: 'pending' | 'decided';
    recommendation: 'retain' | 'retire' | 'insufficient';
    sample_size: number;
    retain_count: number;
    revise_count: number;
    retire_count: number;
    consistency: number;
    decision: 'retain' | 'supersede' | 'insufficient' | null;
    replacement_proposal_id: number | null;
    replacement_proposal: { id: number; version: number } | null;
    decision_note: string | null;
    decided_at: string | null;
    decider: { id: number; name: string } | null;
};

type CalibrationEvaluation = {
    id: number;
    version: number;
    status: 'pending' | 'decided';
    recommendation: 'retain' | 'revise' | 'retire';
    origin_delta_percent: number;
    observed_delta_percent: number;
    improvement_points: number;
    evidence_snapshot: {
        previous_value: number;
        applied_value: number;
        planned: number;
        observed: number;
        scenario_closure_fingerprint: string;
    };
    decision: 'retain' | 'revise' | 'retire' | null;
    decision_note: string | null;
    decided_at: string | null;
    fingerprint: string;
    scenario: { id: number; name: string; version: number };
    creator: { id: number; name: string };
    decider: { id: number; name: string } | null;
};

type AssumptionKey =
    | 'capacity_hours_per_opportunity'
    | 'material_cost_percent_without_quote'
    | 'collection_delay_days';

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

const governanceStateLabel = (
    state: CalibrationProposal['governance_state'],
) =>
    state === 'active'
        ? 'Active reference'
        : state === 'superseded'
          ? 'Superseded reference'
          : state === 'insufficient'
            ? 'Insufficient evidence'
            : 'Available reference';

const learningSeverityLabel = (
    severity: ScenarioLearning['metrics'][number]['severity'],
) =>
    severity === 'significant'
        ? 'Significant'
        : severity === 'watch'
          ? 'Watch'
          : 'Aligned';

const learningSeverityVariant = (
    severity: ScenarioLearning['metrics'][number]['severity'],
) => (severity === 'significant' ? 'destructive' : 'outline');

export default function PlanningScenarioIndex({
    scenarios,
    selectedScenario,
    opportunities,
    defaults,
    calibrationProposals,
}: {
    scenarios: Scenario[];
    selectedScenario: Scenario | null;
    opportunities: Opportunity[];
    defaults: { horizon_starts_on: string; horizon_ends_on: string };
    calibrationProposals: CalibrationProposal[];
}) {
    const { locale } = useLocale();
    const calibrationGeneration = useForm({});
    const calibrationEvaluationGeneration = useForm({});
    const calibrationGovernanceGeneration = useForm({});
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
        calibration_proposal_id: '',
        calibration_previous_value: '',
    });
    const baselineScenarios = scenarios.filter(
        (scenario) => scenario.scenario_kind === 'base',
    );
    const isComparisonScenario = form.data.scenario_kind !== 'base';
    const selectedBusinessLines = opportunities
        .filter((opportunity) =>
            form.data.opportunity_ids.includes(opportunity.id),
        )
        .map(
            (opportunity) =>
                opportunity.installation?.service_type ?? '__unassigned__',
        )
        .filter((line, index, lines) => lines.indexOf(line) === index);
    const toggle = (id: number) => {
        const opportunityIds = form.data.opportunity_ids.includes(id)
            ? form.data.opportunity_ids.filter((value) => value !== id)
            : [...form.data.opportunity_ids, id];
        form.setData({
            ...form.data,
            opportunity_ids: opportunityIds,
            calibration_proposal_id: '',
            calibration_previous_value: '',
        });
    };
    const updateAssumption = (key: AssumptionKey, value: string) =>
        form.setData({
            ...form.data,
            [key]: value,
            calibration_proposal_id: '',
            calibration_previous_value: '',
        });
    const applyCalibration = (proposal: CalibrationProposal) => {
        if (
            proposal.assumption_key === null ||
            proposal.accepted_value === null
        ) {
            return;
        }

        form.setData({
            ...form.data,
            [proposal.assumption_key]: String(proposal.accepted_value),
            calibration_proposal_id: String(proposal.id),
            calibration_previous_value: form.data[proposal.assumption_key],
        });
    };

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

                <CalibrationProposals
                    proposals={calibrationProposals}
                    locale={locale}
                    generating={calibrationGeneration.processing}
                    evaluating={calibrationEvaluationGeneration.processing}
                    governing={calibrationGovernanceGeneration.processing}
                    selectedBusinessLines={selectedBusinessLines}
                    currentAssumptions={{
                        capacity_hours_per_opportunity:
                            form.data.capacity_hours_per_opportunity,
                        material_cost_percent_without_quote:
                            form.data.material_cost_percent_without_quote,
                        collection_delay_days: form.data.collection_delay_days,
                    }}
                    selectedProposalId={form.data.calibration_proposal_id}
                    onUse={applyCalibration}
                    onGenerate={() =>
                        calibrationGeneration.post(
                            '/planning-scenarios/calibrations',
                            { preserveScroll: true },
                        )
                    }
                    onEvaluate={() =>
                        calibrationEvaluationGeneration.post(
                            '/planning-scenarios/calibration-evaluations',
                            { preserveScroll: true },
                        )
                    }
                    onGovern={() =>
                        calibrationGovernanceGeneration.post(
                            '/planning-scenarios/calibration-governance',
                            { preserveScroll: true },
                        )
                    }
                />

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
                                    updateAssumption(
                                        'collection_delay_days',
                                        value,
                                    )
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
                                    updateAssumption(
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
                                    updateAssumption(
                                        'material_cost_percent_without_quote',
                                        value,
                                    )
                                }
                            />
                        </div>
                        {form.data.calibration_proposal_id && (
                            <div className="rounded-md border border-violet-300 bg-violet-50/50 p-3 text-sm dark:bg-violet-950/20">
                                <div className="font-medium">
                                    {translate(
                                        'Accepted calibration reference selected',
                                        locale,
                                    )}
                                </div>
                                <p className="mt-1 text-xs text-muted-foreground">
                                    {translate(
                                        'The proposal, version, source fingerprint, previous value and applied value will be frozen with this scenario.',
                                        locale,
                                    )}
                                </p>
                            </div>
                        )}
                        <InputError
                            message={form.errors.calibration_proposal_id}
                        />

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

function CalibrationProposals({
    proposals,
    locale,
    generating,
    evaluating,
    governing,
    selectedBusinessLines,
    currentAssumptions,
    selectedProposalId,
    onUse,
    onGenerate,
    onEvaluate,
    onGovern,
}: {
    proposals: CalibrationProposal[];
    locale: 'en' | 'es';
    generating: boolean;
    evaluating: boolean;
    governing: boolean;
    selectedBusinessLines: string[];
    currentAssumptions: Record<AssumptionKey, string>;
    selectedProposalId: string;
    onUse: (proposal: CalibrationProposal) => void;
    onGenerate: () => void;
    onEvaluate: () => void;
    onGovern: () => void;
}) {
    return (
        <section className="rounded-lg border p-4">
            <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <div className="flex items-center gap-2">
                        <Sparkles className="size-5 text-violet-600" />
                        <h2 className="font-semibold">
                            {translate('Calibration proposals', locale)}
                        </h2>
                    </div>
                    <p className="mt-1 max-w-3xl text-sm text-muted-foreground">
                        {translate(
                            'Detect repeated deviations across comparable closed scenarios. At least three immutable closures from one business line are required.',
                            locale,
                        )}
                    </p>
                </div>
                <div className="flex flex-col gap-2 sm:flex-row">
                    <Button
                        type="button"
                        variant="outline"
                        disabled={generating}
                        onClick={onGenerate}
                    >
                        <Sparkles />
                        {generating
                            ? translate('Analysing closures...', locale)
                            : translate('Generate proposals', locale)}
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        disabled={governing}
                        onClick={onGovern}
                    >
                        <ShieldCheck />
                        {governing
                            ? translate('Reviewing versions...', locale)
                            : translate('Govern versions', locale)}
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        disabled={evaluating}
                        onClick={onEvaluate}
                    >
                        <GitCompareArrows />
                        {evaluating
                            ? translate('Evaluating results...', locale)
                            : translate('Evaluate applied references', locale)}
                    </Button>
                </div>
            </div>

            {proposals.length > 0 ? (
                <div className="mt-4 grid gap-3 xl:grid-cols-2">
                    {proposals.map((proposal) => (
                        <CalibrationProposalCard
                            key={proposal.id}
                            proposal={proposal}
                            locale={locale}
                            currentValue={
                                proposal.assumption_key
                                    ? currentAssumptions[
                                          proposal.assumption_key
                                      ]
                                    : null
                            }
                            canUse={
                                selectedBusinessLines.length === 1 &&
                                selectedBusinessLines[0] ===
                                    proposal.business_line &&
                                !['superseded', 'insufficient'].includes(
                                    proposal.governance_state,
                                )
                            }
                            selected={
                                selectedProposalId === String(proposal.id)
                            }
                            onUse={() => onUse(proposal)}
                            replacements={proposals.filter(
                                (candidate) =>
                                    candidate.id !== proposal.id &&
                                    candidate.status === 'accepted' &&
                                    candidate.business_line ===
                                        proposal.business_line &&
                                    candidate.version > proposal.version &&
                                    !['superseded', 'insufficient'].includes(
                                        candidate.governance_state,
                                    ),
                            )}
                        />
                    ))}
                </div>
            ) : (
                <p className="mt-4 rounded-md bg-muted/50 p-4 text-sm text-muted-foreground">
                    {translate(
                        'No calibration proposal has been generated yet.',
                        locale,
                    )}
                </p>
            )}
        </section>
    );
}

function CalibrationProposalCard({
    proposal,
    locale,
    currentValue,
    canUse,
    selected,
    onUse,
    replacements,
}: {
    proposal: CalibrationProposal;
    locale: 'en' | 'es';
    currentValue: string | null;
    canUse: boolean;
    selected: boolean;
    onUse: () => void;
    replacements: CalibrationProposal[];
}) {
    const decisionForm = useForm<{
        decision: 'accepted' | 'rejected';
        decision_note: string;
        accepted_value: string;
        calibration: string;
    }>({
        decision: 'accepted',
        decision_note: '',
        accepted_value: '',
        calibration: '',
    });
    const decide = (decision: 'accepted' | 'rejected') => {
        decisionForm.transform((data) => ({ ...data, decision }));
        decisionForm.post(`/planning-calibrations/${proposal.id}/decision`, {
            preserveScroll: true,
        });
    };

    return (
        <article className="min-w-0 rounded-md border bg-muted/20 p-4">
            <div className="flex flex-wrap items-start justify-between gap-2">
                <div>
                    <div className="font-medium">
                        {translate(proposal.metric_label, locale)} · v
                        {proposal.version}
                    </div>
                    <div className="text-xs text-muted-foreground">
                        {translate(proposal.business_line, locale)} ·{' '}
                        {translate(proposal.area, locale)}
                    </div>
                </div>
                <Badge
                    variant={
                        proposal.status === 'rejected'
                            ? 'destructive'
                            : 'outline'
                    }
                >
                    {translate(
                        proposal.status === 'accepted'
                            ? 'Accepted'
                            : proposal.status === 'rejected'
                              ? 'Rejected'
                              : 'Pending decision',
                        locale,
                    )}
                </Badge>
                {proposal.status === 'accepted' && (
                    <Badge variant="secondary">
                        {translate(
                            governanceStateLabel(proposal.governance_state),
                            locale,
                        )}
                    </Badge>
                )}
            </div>
            <div className="mt-3 grid grid-cols-2 gap-2 text-xs sm:grid-cols-4">
                <span>
                    {translate('Sample', locale)}: {proposal.sample_size}
                </span>
                <span>
                    {translate('Consistent', locale)}:{' '}
                    {Math.round(
                        proposal.evidence_snapshot.direction_consistency * 100,
                    )}
                    %
                </span>
                <span>
                    {translate('Average variance', locale)}:{' '}
                    {signed(
                        proposal.evidence_snapshot.average_delta_percent,
                        '%',
                    )}
                </span>
                <span>
                    {translate('Direction', locale)}:{' '}
                    {translate(proposal.direction, locale)}
                </span>
            </div>
            <p className="mt-3 text-sm">
                {translate(proposal.recommendation, locale)}
            </p>
            <p className="mt-2 text-xs text-muted-foreground">
                {translate(
                    'Accepting this proposal records a planning decision but never changes assumptions automatically.',
                    locale,
                )}
            </p>

            {proposal.status === 'pending' ? (
                <div className="mt-4 space-y-2">
                    <div>
                        <Label htmlFor={`calibration-value-${proposal.id}`}>
                            {translate('Accepted reference value', locale)} ·{' '}
                            {proposal.assumption_key
                                ? assumptionLabel(
                                      proposal.assumption_key,
                                      locale,
                                  )
                                : translate('Unlinked metric', locale)}
                        </Label>
                        <Input
                            id={`calibration-value-${proposal.id}`}
                            type="number"
                            min={0}
                            max={
                                proposal.assumption_key ===
                                'material_cost_percent_without_quote'
                                    ? 100
                                    : proposal.assumption_key ===
                                        'collection_delay_days'
                                      ? 365
                                      : 1000
                            }
                            step={
                                proposal.assumption_key ===
                                'collection_delay_days'
                                    ? 1
                                    : 0.1
                            }
                            value={decisionForm.data.accepted_value}
                            onChange={(event) =>
                                decisionForm.setData(
                                    'accepted_value',
                                    event.target.value,
                                )
                            }
                        />
                        <p className="mt-1 text-xs text-muted-foreground">
                            {translate(
                                'Set the optional value that an administrator may later copy into a matching scenario.',
                                locale,
                            )}
                        </p>
                        <InputError
                            message={decisionForm.errors.accepted_value}
                        />
                    </div>
                    <Label htmlFor={`calibration-note-${proposal.id}`}>
                        {translate('Decision note', locale)}
                    </Label>
                    <Textarea
                        id={`calibration-note-${proposal.id}`}
                        value={decisionForm.data.decision_note}
                        onChange={(event) =>
                            decisionForm.setData(
                                'decision_note',
                                event.target.value,
                            )
                        }
                        placeholder={translate(
                            'Explain why this pattern should or should not guide the next scenario.',
                            locale,
                        )}
                    />
                    <InputError
                        message={
                            decisionForm.errors.decision_note ||
                            decisionForm.errors.calibration
                        }
                    />
                    <div className="flex flex-col gap-2 sm:flex-row">
                        <Button
                            type="button"
                            disabled={decisionForm.processing}
                            onClick={() => decide('accepted')}
                        >
                            <CheckCircle2 />
                            {translate('Accept proposal', locale)}
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            disabled={decisionForm.processing}
                            onClick={() => decide('rejected')}
                        >
                            <XCircle />
                            {translate('Reject proposal', locale)}
                        </Button>
                    </div>
                </div>
            ) : (
                <div className="mt-3 space-y-3 rounded-md bg-background/70 p-3 text-xs">
                    <div className="font-medium">
                        {translate('Decided by', locale)}{' '}
                        {proposal.decider?.name}
                    </div>
                    <p className="mt-1 text-muted-foreground">
                        {proposal.decision_note}
                    </p>
                    {proposal.status === 'accepted' &&
                        proposal.assumption_key &&
                        proposal.accepted_value !== null && (
                            <div className="border-t pt-3">
                                <div className="grid gap-1 sm:grid-cols-3">
                                    <span>
                                        {translate('Current value', locale)}:{' '}
                                        {currentValue}
                                    </span>
                                    <span>
                                        {translate('Reference value', locale)}:{' '}
                                        {proposal.accepted_value}
                                    </span>
                                    <span>
                                        {translate('Difference', locale)}:{' '}
                                        {currentValue !== null
                                            ? signed(
                                                  proposal.accepted_value -
                                                      Number(currentValue),
                                              )
                                            : '—'}
                                    </span>
                                </div>
                                <Button
                                    type="button"
                                    size="sm"
                                    className="mt-3"
                                    variant={selected ? 'secondary' : 'outline'}
                                    disabled={!canUse || selected}
                                    onClick={onUse}
                                >
                                    <CheckCircle2 />
                                    {selected
                                        ? translate(
                                              'Reference selected',
                                              locale,
                                          )
                                        : translate(
                                              'Use reference in scenario',
                                              locale,
                                          )}
                                </Button>
                                {!canUse && !selected && (
                                    <p className="mt-2 text-muted-foreground">
                                        {translate(
                                            [
                                                'superseded',
                                                'insufficient',
                                            ].includes(
                                                proposal.governance_state,
                                            )
                                                ? 'This governed reference is no longer available for new scenarios.'
                                                : 'Select opportunities from this business line only to use the reference.',
                                            locale,
                                        )}
                                    </p>
                                )}
                            </div>
                        )}
                </div>
            )}
            {proposal.evaluations.length > 0 && (
                <CalibrationEvaluations
                    evaluations={proposal.evaluations}
                    locale={locale}
                />
            )}
            {proposal.governance_reviews.length > 0 && (
                <CalibrationGovernanceReviews
                    reviews={proposal.governance_reviews}
                    replacements={replacements}
                    locale={locale}
                />
            )}
        </article>
    );
}

function CalibrationEvaluations({
    evaluations,
    locale,
}: {
    evaluations: CalibrationEvaluation[];
    locale: 'en' | 'es';
}) {
    return (
        <div className="mt-4 space-y-3 border-t pt-4">
            <h5 className="font-medium">
                {translate('Applied-reference evaluations', locale)}
            </h5>
            {evaluations.map((evaluation) => (
                <CalibrationEvaluationCard
                    key={evaluation.id}
                    evaluation={evaluation}
                    locale={locale}
                />
            ))}
        </div>
    );
}

function CalibrationEvaluationCard({
    evaluation,
    locale,
}: {
    evaluation: CalibrationEvaluation;
    locale: 'en' | 'es';
}) {
    const form = useForm<{
        decision: 'retain' | 'revise' | 'retire';
        decision_note: string;
        evaluation: string;
    }>({ decision: 'retain', decision_note: '', evaluation: '' });
    const decide = (decision: 'retain' | 'revise' | 'retire') => {
        form.transform((data) => ({ ...data, decision }));
        form.post(
            `/planning-calibration-evaluations/${evaluation.id}/decision`,
            { preserveScroll: true },
        );
    };

    return (
        <div className="rounded-md border bg-background p-3 text-xs">
            <div className="flex flex-wrap items-start justify-between gap-2">
                <div>
                    <div className="font-medium">
                        {evaluation.scenario.name} · v
                        {evaluation.scenario.version}
                    </div>
                    <div className="text-muted-foreground">
                        {translate('Evaluation', locale)} · v
                        {evaluation.version}
                    </div>
                </div>
                <Badge variant="outline">
                    {translate(
                        evaluation.status === 'decided' && evaluation.decision
                            ? evaluation.decision
                            : evaluation.recommendation,
                        locale,
                    )}
                </Badge>
            </div>
            <div className="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-3">
                <span>
                    {translate('Original variance', locale)}:{' '}
                    {signed(evaluation.origin_delta_percent, '%')}
                </span>
                <span>
                    {translate('Observed variance', locale)}:{' '}
                    {signed(evaluation.observed_delta_percent, '%')}
                </span>
                <span>
                    {translate('Improvement', locale)}:{' '}
                    {signed(evaluation.improvement_points, ' pp')}
                </span>
            </div>
            <p className="mt-2 text-muted-foreground">
                {translate('System recommendation', locale)}:{' '}
                {translate(evaluation.recommendation, locale)}.{' '}
                {translate(
                    'This recommendation does not alter the calibration or scenario.',
                    locale,
                )}
            </p>

            {evaluation.status === 'pending' ? (
                <div className="mt-3 space-y-2">
                    <Label htmlFor={`evaluation-note-${evaluation.id}`}>
                        {translate('Evaluation decision note', locale)}
                    </Label>
                    <Textarea
                        id={`evaluation-note-${evaluation.id}`}
                        value={form.data.decision_note}
                        onChange={(event) =>
                            form.setData('decision_note', event.target.value)
                        }
                    />
                    <InputError
                        message={
                            form.errors.decision_note || form.errors.evaluation
                        }
                    />
                    <div className="flex flex-col gap-2 sm:flex-row">
                        {(['retain', 'revise', 'retire'] as const).map(
                            (decision) => (
                                <Button
                                    key={decision}
                                    type="button"
                                    size="sm"
                                    variant={
                                        decision === 'retire'
                                            ? 'destructive'
                                            : 'outline'
                                    }
                                    disabled={form.processing}
                                    onClick={() => decide(decision)}
                                >
                                    {translate(decision, locale)}
                                </Button>
                            ),
                        )}
                    </div>
                </div>
            ) : (
                <div className="mt-3 border-t pt-3">
                    <span className="font-medium">
                        {translate('Decided by', locale)}{' '}
                        {evaluation.decider?.name}
                    </span>
                    <p className="mt-1 text-muted-foreground">
                        {evaluation.decision_note}
                    </p>
                </div>
            )}
        </div>
    );
}

function CalibrationGovernanceReviews({
    reviews,
    replacements,
    locale,
}: {
    reviews: CalibrationGovernanceReview[];
    replacements: CalibrationProposal[];
    locale: 'en' | 'es';
}) {
    return (
        <div className="mt-4 space-y-3 border-t pt-4">
            <h5 className="font-medium">
                {translate('Version governance', locale)}
            </h5>
            {reviews.map((review) => (
                <CalibrationGovernanceReviewCard
                    key={review.id}
                    review={review}
                    replacements={replacements}
                    locale={locale}
                />
            ))}
        </div>
    );
}

function CalibrationGovernanceReviewCard({
    review,
    replacements,
    locale,
}: {
    review: CalibrationGovernanceReview;
    replacements: CalibrationProposal[];
    locale: 'en' | 'es';
}) {
    const form = useForm<{
        decision: 'retain' | 'supersede' | 'insufficient';
        replacement_proposal_id: string;
        decision_note: string;
        governance: string;
    }>({
        decision: 'retain',
        replacement_proposal_id: '',
        decision_note: '',
        governance: '',
    });
    const decide = (decision: 'retain' | 'supersede' | 'insufficient') => {
        form.transform((data) => ({ ...data, decision }));
        form.post(`/planning-calibration-governance/${review.id}/decision`, {
            preserveScroll: true,
        });
    };

    return (
        <div className="rounded-md border bg-background p-3 text-xs">
            <div className="flex flex-wrap items-start justify-between gap-2">
                <div>
                    <div className="font-medium">
                        {translate('Governance review', locale)} · v
                        {review.version}
                    </div>
                    <div className="text-muted-foreground">
                        {translate('Decided evaluations', locale)}:{' '}
                        {review.sample_size}
                    </div>
                </div>
                <Badge variant="outline">
                    {translate(
                        review.status === 'decided' && review.decision
                            ? review.decision
                            : review.recommendation,
                        locale,
                    )}
                </Badge>
            </div>
            <div className="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-4">
                <span>
                    {translate('Retain', locale)}: {review.retain_count}
                </span>
                <span>
                    {translate('Revise', locale)}: {review.revise_count}
                </span>
                <span>
                    {translate('Retire', locale)}: {review.retire_count}
                </span>
                <span>
                    {translate('Consistency', locale)}:{' '}
                    {Math.round(review.consistency * 100)}%
                </span>
            </div>
            <p className="mt-2 text-muted-foreground">
                {translate('Aggregated recommendation', locale)}:{' '}
                {translate(review.recommendation, locale)}.{' '}
                {translate(
                    'No version changes until an administrator records a decision.',
                    locale,
                )}
            </p>

            {review.status === 'pending' ? (
                <div className="mt-3 space-y-2">
                    {replacements.length > 0 && (
                        <div>
                            <Label
                                htmlFor={`governance-replacement-${review.id}`}
                            >
                                {translate('Replacement version', locale)}
                            </Label>
                            <Select
                                value={form.data.replacement_proposal_id}
                                onValueChange={(value) =>
                                    form.setData(
                                        'replacement_proposal_id',
                                        value,
                                    )
                                }
                            >
                                <SelectTrigger
                                    id={`governance-replacement-${review.id}`}
                                >
                                    <SelectValue
                                        placeholder={translate(
                                            'Select a newer accepted version',
                                            locale,
                                        )}
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    {replacements.map((replacement) => (
                                        <SelectItem
                                            key={replacement.id}
                                            value={String(replacement.id)}
                                        >
                                            v{replacement.version} ·{' '}
                                            {replacement.accepted_value}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError
                                message={form.errors.replacement_proposal_id}
                            />
                        </div>
                    )}
                    <Label htmlFor={`governance-note-${review.id}`}>
                        {translate('Governance decision note', locale)}
                    </Label>
                    <Textarea
                        id={`governance-note-${review.id}`}
                        value={form.data.decision_note}
                        onChange={(event) =>
                            form.setData('decision_note', event.target.value)
                        }
                    />
                    <InputError
                        message={
                            form.errors.decision_note || form.errors.governance
                        }
                    />
                    <div className="flex flex-col gap-2 sm:flex-row">
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            disabled={form.processing}
                            onClick={() => decide('retain')}
                        >
                            {translate('Keep active', locale)}
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            disabled={
                                form.processing ||
                                replacements.length === 0 ||
                                form.data.replacement_proposal_id === ''
                            }
                            onClick={() => decide('supersede')}
                        >
                            {translate('Supersede with selected', locale)}
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            variant="destructive"
                            disabled={form.processing}
                            onClick={() => decide('insufficient')}
                        >
                            {translate('Mark evidence insufficient', locale)}
                        </Button>
                    </div>
                </div>
            ) : (
                <div className="mt-3 border-t pt-3">
                    <span className="font-medium">
                        {translate('Decided by', locale)} {review.decider?.name}
                    </span>
                    {review.replacement_proposal && (
                        <span className="ml-2">
                            · {translate('Replacement', locale)} v
                            {review.replacement_proposal.version}
                        </span>
                    )}
                    <p className="mt-1 text-muted-foreground">
                        {review.decision_note}
                    </p>
                </div>
            )}
        </div>
    );
}

function assumptionLabel(key: AssumptionKey, locale: 'en' | 'es') {
    return translate(
        key === 'capacity_hours_per_opportunity'
            ? 'Hours per opportunity'
            : key === 'material_cost_percent_without_quote'
              ? 'Material cost without quote (%)'
              : 'Collection delay (days)',
        locale,
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
                {scenario.assumptions.calibration_reference && (
                    <div className="mt-2 border-t pt-2 text-violet-700 dark:text-violet-300">
                        <div className="font-medium">
                            {translate('Applied calibration reference', locale)}{' '}
                            · v
                            {
                                scenario.assumptions.calibration_reference
                                    .proposal_version
                            }
                        </div>
                        <p>
                            {assumptionLabel(
                                scenario.assumptions.calibration_reference
                                    .assumption_key,
                                locale,
                            )}
                            :{' '}
                            {
                                scenario.assumptions.calibration_reference
                                    .previous_value
                            }{' '}
                            →{' '}
                            {
                                scenario.assumptions.calibration_reference
                                    .applied_value
                            }
                        </p>
                        <p className="mt-1 font-mono text-[10px] break-all text-muted-foreground">
                            {
                                scenario.assumptions.calibration_reference
                                    .source_fingerprint
                            }
                        </p>
                    </div>
                )}
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
                <div className="space-y-4">
                    <div className="rounded-md border border-emerald-300 bg-emerald-50/60 p-3 text-sm dark:bg-emerald-950/20">
                        <div className="font-medium">
                            {translate('Scenario closed', locale)}
                        </div>
                        <p className="mt-1 text-xs text-muted-foreground">
                            {translate('Closed by', locale)}{' '}
                            {scenario.closer?.name} ·{' '}
                            {scenario.closed_at?.replace('T', ' ').slice(0, 16)}
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
                    {scenario.learning_summary && (
                        <ScenarioLearningSummary
                            learning={scenario.learning_summary}
                            locale={locale}
                        />
                    )}
                </div>
            )}
        </div>
    );
}

function ScenarioLearningSummary({
    learning,
    locale,
}: {
    learning: ScenarioLearning;
    locale: 'en' | 'es';
}) {
    const value = (amount: number, unit: string) =>
        unit === 'currency' ? money(amount, locale) : String(amount);

    return (
        <div className="space-y-3 rounded-md border border-violet-300 bg-violet-50/50 p-3 text-sm dark:bg-violet-950/20">
            <div>
                <h4 className="font-medium">
                    {translate('Outcome learning', locale)}
                </h4>
                <p className="text-xs text-muted-foreground">
                    {translate(
                        'Based only on the immutable closure snapshot.',
                        locale,
                    )}
                </p>
            </div>
            <div className="flex flex-wrap gap-2">
                <Badge variant="outline">
                    {translate('Aligned', locale)}: {learning.summary.aligned}
                </Badge>
                <Badge variant="outline">
                    {translate('Watch', locale)}: {learning.summary.watch}
                </Badge>
                <Badge variant="outline">
                    {translate('Significant deviations', locale)}:{' '}
                    {learning.summary.significant}
                </Badge>
            </div>
            <div className="grid gap-2 md:grid-cols-2">
                {learning.metrics.map((metric) => (
                    <div
                        key={`${metric.area}-${metric.label}`}
                        className="rounded-md border bg-background/70 p-3"
                    >
                        <div className="flex items-start justify-between gap-2">
                            <div>
                                <div className="font-medium">
                                    {translate(metric.label, locale)}
                                </div>
                                <div className="text-xs text-muted-foreground capitalize">
                                    {translate(metric.area, locale)}
                                </div>
                            </div>
                            <Badge
                                variant={learningSeverityVariant(
                                    metric.severity,
                                )}
                            >
                                {translate(
                                    learningSeverityLabel(metric.severity),
                                    locale,
                                )}
                            </Badge>
                        </div>
                        <div className="mt-2 grid gap-2 text-xs sm:grid-cols-3">
                            <span>
                                {translate('Planned value', locale)}:{' '}
                                {value(metric.planned, metric.unit)}
                            </span>
                            <span>
                                {translate('Observed value', locale)}:{' '}
                                {value(metric.current, metric.unit)}
                            </span>
                            <span>
                                {translate('Variance', locale)}:{' '}
                                {signed(metric.delta)}
                                {metric.delta_percent !== null
                                    ? ` (${signed(metric.delta_percent, '%')})`
                                    : ''}
                            </span>
                        </div>
                    </div>
                ))}
            </div>
            {learning.recommendations.length > 0 && (
                <div>
                    <h5 className="font-medium">
                        {translate('Recommendations for next scenario', locale)}
                    </h5>
                    <ul className="mt-1 list-disc space-y-1 pl-5 text-sm">
                        {learning.recommendations.map((recommendation) => (
                            <li key={recommendation.area}>
                                {recommendation.message}
                            </li>
                        ))}
                    </ul>
                </div>
            )}
            <p className="text-xs text-muted-foreground">
                {translate(
                    'Recommendations never modify assumptions or master data automatically.',
                    locale,
                )}
            </p>
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

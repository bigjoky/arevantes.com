import { Head, Link, useForm } from '@inertiajs/react';
import { CheckCircle2, GitCompareArrows, LockKeyhole } from 'lucide-react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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
    creator: { id: number; name: string };
    created_at: string;
};

const money = (value: number, locale: 'en' | 'es') =>
    new Intl.NumberFormat(locale === 'es' ? 'es-ES' : 'en-GB', {
        style: 'currency',
        currency: 'EUR',
    }).format(value);

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
        horizon_starts_on: defaults.horizon_starts_on,
        horizon_ends_on: defaults.horizon_ends_on,
        opportunity_ids: [] as number[],
        capacity_hours_per_opportunity: '40',
        material_cost_percent_without_quote: '35',
        collection_delay_days: '30',
    });
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
                                                {translate('Frozen', locale)}
                                            </Badge>
                                        </Link>
                                    </Button>
                                ))}
                            </div>
                        </section>
                        {selectedScenario && (
                            <ScenarioResult
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
                <CheckCircle2 className="size-5 text-emerald-600" />
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
        </section>
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

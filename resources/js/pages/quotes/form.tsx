import { Head, Link, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    ChevronDown,
    ChevronRight,
    PackagePlus,
    Plus,
    Save,
    Trash2,
} from 'lucide-react';
import { Fragment, useMemo, useState } from 'react';
import {
    FormActions,
    FormErrorSummary,
    FormSection,
} from '@/components/forms/form-layout';
import InputError from '@/components/input-error';
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
import { Textarea } from '@/components/ui/textarea';
import { useLocale } from '@/hooks/use-locale';
import { translate } from '@/i18n';
import { serviceLabels } from '@/lib/business-lines';
import type { ServiceType } from '@/lib/business-lines';

type Customer = {
    id: number;
    first_name: string;
    last_name: string | null;
    company_name: string | null;
    payment_terms_days: number | null;
};
type Installation = {
    id: number;
    person_id: number | null;
    project_reference: string | null;
    site_name: string | null;
};
type Product = {
    id: number;
    type: 'product' | 'service';
    name: string;
    description: string | null;
    sale_price: string;
    cost_price: string;
    track_stock: boolean;
    unit_id: number;
    tax_rate_id: number;
    unit: { id?: number; symbol: string };
    tax_rate: { rate: string };
    business_lines: ServiceType[] | null;
    resolved_components: Array<{
        product_id: number;
        unit_id: number;
        kind: Component['kind'];
        fulfillment_kind: Component['fulfillment_kind'];
        visibility: Component['visibility'];
        description: string;
        quantity: number;
        planned_unit_cost: number;
        interval_months: number | null;
    }>;
};
type Component = {
    id?: number;
    client_key: string;
    parent_client_key: string | null;
    parent_component_id?: number | null;
    depth: number;
    product_id: number | null;
    unit_id: number | null;
    kind: 'material' | 'labor' | 'service' | 'document' | 'other';
    fulfillment_kind: 'stock' | 'activity' | 'recurring' | 'none';
    visibility: 'detailed' | 'summary' | 'hidden';
    description: string;
    quantity: string;
    planned_unit_cost: string;
    interval_months: string;
};
type Line = {
    client_key: string;
    depth: number;
    product_id: number | null;
    unit_id: number | null;
    tax_rate_id: number | null;
    description: string;
    quantity: string;
    unit_price: string;
    total_with_tax: string;
    discount_percent: string;
    tax_percent: string;
    selection_mode: 'included' | 'optional' | 'alternative';
    option_group: string;
    is_selected: boolean;
    components: Component[];
};
type Quote = {
    id: number;
    number: string;
    opportunity_id: number | null;
    person_id: number;
    installation_id: number | null;
    service_type: ServiceType | null;
    issue_date: string;
    valid_until: string | null;
    acceptance_payment_percentage: string;
    acceptance_payment_due_days: number;
    notes: string | null;
    lines: (Line & { id: number })[];
};
type OpportunityDraft = {
    id: number;
    code: string;
    installation_id: number | null;
    title: string;
    estimated_value: number;
    notes: string | null;
    tax_rate_id: number | null;
    tax_percent: number;
};

export default function QuoteForm({
    customers,
    installations,
    products,
    quote,
    defaultCustomerId,
    opportunityDraft,
    acceptancePaymentDefaults,
}: {
    customers: Customer[];
    installations: Installation[];
    products: Product[];
    quote?: Quote;
    defaultCustomerId?: number | null;
    opportunityDraft?: OpportunityDraft | null;
    acceptancePaymentDefaults: { percentage: number; due_days: number };
}) {
    const { locale } = useLocale();
    const pageTitle = quote
        ? `${translate('Edit', locale)} ${quote.number}`
        : translate('Nueva propuesta', locale);
    const initialCustomerId = quote?.person_id ?? defaultCustomerId ?? null;
    const initialPaymentTerms =
        customers.find((customer) => customer.id === initialCustomerId)
            ?.payment_terms_days ?? acceptancePaymentDefaults.due_days;
    const form = useForm({
        opportunity_id: String(
            quote?.opportunity_id ?? opportunityDraft?.id ?? '',
        ),
        person_id: String(quote?.person_id ?? defaultCustomerId ?? ''),
        installation_id: String(
            quote?.installation_id ?? opportunityDraft?.installation_id ?? '',
        ),
        service_type: quote?.service_type ?? ('' as ServiceType | ''),
        issue_date:
            quote?.issue_date?.slice(0, 10) ??
            new Intl.DateTimeFormat('en-CA', {
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
            }).format(new Date()),
        valid_until: quote?.valid_until?.slice(0, 10) ?? '',
        acceptance_payment_percentage: String(
            quote?.acceptance_payment_percentage ??
                acceptancePaymentDefaults.percentage,
        ),
        acceptance_payment_due_days: String(
            quote?.acceptance_payment_due_days ?? initialPaymentTerms,
        ),
        notes:
            quote?.notes ??
            opportunityDraft?.notes ??
            (opportunityDraft
                ? `${translate('Prepared from opportunity', locale)} ${opportunityDraft.code}.`
                : ''),
        change_note: '',
        lines:
            quote?.lines.map((line) => ({
                ...line,
                client_key: `line-${line.id}`,
                depth: 1,
                total_with_tax: line.total_with_tax ?? '',
                product_id: line.product_id,
                unit_id: line.unit_id,
                tax_rate_id: line.tax_rate_id,
                selection_mode: line.selection_mode ?? 'included',
                option_group: line.option_group ?? '',
                is_selected: line.is_selected ?? true,
                components: (line.components || []).map((component) => ({
                    ...component,
                    client_key: `component-${component.id}`,
                    parent_client_key: component.parent_component_id
                        ? `component-${component.parent_component_id}`
                        : null,
                    depth: component.depth ?? 2,
                    quantity: String(component.quantity),
                    planned_unit_cost: String(component.planned_unit_cost),
                    interval_months: component.interval_months
                        ? String(component.interval_months)
                        : '',
                })),
            })) ??
            (opportunityDraft
                ? [
                      {
                          ...blankLine(),
                          tax_rate_id: opportunityDraft.tax_rate_id,
                          description: opportunityDraft.title,
                          unit_price: String(opportunityDraft.estimated_value),
                          tax_percent: String(opportunityDraft.tax_percent),
                      },
                  ]
                : [blankLine()]),
    });
    const selectedCustomer = customers.find(
        (customer) => customer.id === Number(form.data.person_id),
    );
    const [expandedLines, setExpandedLines] = useState<Set<string>>(
        new Set(
            form.data.lines[0]?.client_key
                ? [form.data.lines[0].client_key]
                : [],
        ),
    );
    const availableInstallations = useMemo(
        () =>
            installations.filter(
                (item) =>
                    !form.data.person_id ||
                    item.person_id === Number(form.data.person_id),
            ),
        [form.data.person_id, installations],
    );
    const availableProducts = useMemo(
        () =>
            products.filter(
                (product) =>
                    !form.data.service_type ||
                    !product.business_lines?.length ||
                    product.business_lines.includes(form.data.service_type),
            ),
        [form.data.service_type, products],
    );
    const totals = useMemo(() => calculate(form.data.lines), [form.data.lines]);
    const updateLine = (index: number, changes: Partial<Line>) =>
        form.setData(
            'lines',
            form.data.lines.map((line, position) =>
                position === index ? updateLinePrice(line, changes) : line,
            ),
        );
    const selectProduct = (index: number, value: string) => {
        const product = products.find((item) => item.id === Number(value));

        if (product) {
            updateLine(index, {
                product_id: product.id,
                unit_id: product.unit_id,
                tax_rate_id: product.tax_rate_id,
                description: product.description || product.name,
                unit_price: product.sale_price,
                tax_percent: product.tax_rate.rate,
                components:
                    product.resolved_components.length > 0
                        ? product.resolved_components.map((component) => ({
                              ...component,
                              ...componentIdentity(),
                              quantity: String(component.quantity),
                              planned_unit_cost: String(
                                  component.planned_unit_cost,
                              ),
                              interval_months: component.interval_months
                                  ? String(component.interval_months)
                                  : '',
                          }))
                        : product.type === 'service' &&
                            form.data.lines[index].components.length === 0
                          ? [componentFromProduct(product)]
                          : form.data.lines[index].components,
            });
        }
    };
    const removeLine = (index: number) => {
        form.setData(
            'lines',
            form.data.lines.filter((_, position) => position !== index),
        );
    };
    const updateComponent = (
        lineIndex: number,
        componentIndex: number,
        changes: Partial<Component>,
    ) =>
        updateLine(lineIndex, {
            components: form.data.lines[lineIndex].components.map(
                (component, position) =>
                    position === componentIndex
                        ? { ...component, ...changes }
                        : component,
            ),
        });
    const selectComponentProduct = (
        lineIndex: number,
        componentIndex: number,
        value: string,
    ) => {
        const product = products.find((item) => item.id === Number(value));

        if (!product) {
            return;
        }

        updateComponent(lineIndex, componentIndex, {
            product_id: product.id,
            unit_id: product.unit_id,
            kind: product.type === 'service' ? 'service' : 'material',
            fulfillment_kind:
                product.type === 'service'
                    ? 'activity'
                    : product.track_stock
                      ? 'stock'
                      : 'none',
            description: product.description || product.name,
            planned_unit_cost: product.cost_price,
        });
    };
    const addChildComponent = (lineIndex: number, componentIndex: number) => {
        const components = [...form.data.lines[lineIndex].components];
        const parent = components[componentIndex];

        if (parent.depth >= 5) {
            return;
        }

        let insertAt = componentIndex + 1;

        while (
            insertAt < components.length &&
            components[insertAt].depth > parent.depth
        ) {
            insertAt++;
        }

        components.splice(
            insertAt,
            0,
            blankComponent(parent.client_key, parent.depth + 1),
        );
        updateLine(lineIndex, { components });
    };
    const removeComponent = (lineIndex: number, componentIndex: number) => {
        const components = form.data.lines[lineIndex].components;
        const keysToRemove = new Set([components[componentIndex].client_key]);
        let changed = true;

        while (changed) {
            changed = false;

            for (const component of components) {
                if (
                    component.parent_client_key &&
                    keysToRemove.has(component.parent_client_key) &&
                    !keysToRemove.has(component.client_key)
                ) {
                    keysToRemove.add(component.client_key);
                    changed = true;
                }
            }
        }

        updateLine(lineIndex, {
            components: components.filter(
                (component) => !keysToRemove.has(component.client_key),
            ),
        });
    };
    const submit = () =>
        quote ? form.put(`/quotes/${quote.id}`) : form.post('/quotes');

    return (
        <>
            <Head title={pageTitle} />
            <div className="flex min-w-0 flex-1 flex-col gap-6 overflow-x-clip p-4 md:p-6">
                <header className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold">{pageTitle}</h1>
                        <p className="text-sm text-muted-foreground">
                            {opportunityDraft
                                ? `${translate('Prepared from opportunity', locale)} ${opportunityDraft.code}. ${translate('Review prices and taxes before saving.', locale)}`
                                : translate(
                                      'Build the commercial offer from catalog items or free-text lines.',
                                      locale,
                                  )}
                        </p>
                    </div>
                    <Button variant="outline" asChild>
                        <Link
                            href={quote ? `/quotes/${quote.id}` : '/operations'}
                        >
                            <ArrowLeft /> {translate('Back', locale)}
                        </Link>
                    </Button>
                </header>
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        submit();
                    }}
                    className="grid gap-8"
                >
                    <FormErrorSummary errors={form.errors} />

                    <FormSection
                        title={translate('Proposal context', locale)}
                        description={translate(
                            'Choose the customer, business line, installation and validity period.',
                            locale,
                        )}
                    >
                        <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
                            <SelectField
                                label={translate('Customer', locale)}
                                value={form.data.person_id}
                                onChange={(value) => {
                                    const customer = customers.find(
                                        (item) => item.id === Number(value),
                                    );
                                    form.setData((data) => ({
                                        ...data,
                                        person_id: value,
                                        installation_id: '',
                                        acceptance_payment_due_days: String(
                                            customer?.payment_terms_days ??
                                                acceptancePaymentDefaults.due_days,
                                        ),
                                    }));
                                }}
                                options={customers.map((customer) => [
                                    String(customer.id),
                                    customer.company_name ||
                                        `${customer.first_name} ${customer.last_name || ''}`.trim(),
                                ])}
                            />
                            <SelectField
                                label={translate('Business line', locale)}
                                value={form.data.service_type}
                                onChange={(value) =>
                                    form.setData(
                                        'service_type',
                                        value as ServiceType,
                                    )
                                }
                                options={Object.entries(serviceLabels)}
                            />
                            <SelectField
                                label={translate(
                                    'Installation (optional)',
                                    locale,
                                )}
                                value={form.data.installation_id}
                                onChange={(value) =>
                                    form.setData(
                                        'installation_id',
                                        value === 'none' ? '' : value,
                                    )
                                }
                                options={[
                                    [
                                        'none',
                                        translate('No installation', locale),
                                    ],
                                    ...availableInstallations.map((item) => [
                                        String(item.id),
                                        item.project_reference ||
                                            item.site_name ||
                                            `#${item.id}`,
                                    ]),
                                ]}
                            />
                            <Field
                                label={translate('Issue date', locale)}
                                type="date"
                                value={form.data.issue_date}
                                onChange={(value) =>
                                    form.setData('issue_date', value)
                                }
                            />
                            <Field
                                label={translate('Valid until', locale)}
                                type="date"
                                value={form.data.valid_until}
                                onChange={(value) =>
                                    form.setData('valid_until', value)
                                }
                            />
                        </div>
                    </FormSection>
                    <FormSection
                        title={translate('Acceptance and payment', locale)}
                        description={translate(
                            'Define the advance invoice generated when the customer accepts the proposal.',
                            locale,
                        )}
                    >
                        <div className="grid gap-4 md:grid-cols-2">
                            <Field
                                label={translate(
                                    'Acceptance payment (%)',
                                    locale,
                                )}
                                type="number"
                                value={form.data.acceptance_payment_percentage}
                                onChange={(value) =>
                                    form.setData(
                                        'acceptance_payment_percentage',
                                        value,
                                    )
                                }
                            />
                            <Field
                                label={translate(
                                    'Payment term after acceptance (days)',
                                    locale,
                                )}
                                type="number"
                                value={form.data.acceptance_payment_due_days}
                                onChange={(value) =>
                                    form.setData(
                                        'acceptance_payment_due_days',
                                        value,
                                    )
                                }
                            />
                            <p className="text-sm text-muted-foreground md:col-span-2">
                                {translate(
                                    'Acceptance creates a payment milestone. Register its collection first and issue the invoice manually afterwards. Use 0% when no acceptance payment is required.',
                                    locale,
                                )}
                                {selectedCustomer?.payment_terms_days !=
                                null ? (
                                    <span className="mt-1 block font-medium text-foreground">
                                        {translate(
                                            'Customer payment terms',
                                            locale,
                                        )}
                                        : {selectedCustomer.payment_terms_days}{' '}
                                        {translate('days', locale)}.
                                    </span>
                                ) : null}
                            </p>
                        </div>
                    </FormSection>
                    <FormSection
                        title={translate('Proposal lines', locale)}
                        description={translate(
                            'Build the scope with catalog items, custom lines and nested components.',
                            locale,
                        )}
                    >
                        <div className="mb-3 flex items-center justify-between">
                            <div>
                                <h2 className="font-semibold">
                                    {translate('Lines', locale)}
                                </h2>
                                <p className="text-sm text-muted-foreground">
                                    {translate(
                                        'Prices are before tax.',
                                        locale,
                                    )}
                                </p>
                            </div>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() =>
                                    form.setData('lines', [
                                        ...form.data.lines,
                                        blankLine(),
                                    ])
                                }
                            >
                                <Plus /> {translate('Add line', locale)}
                            </Button>
                        </div>
                        <div className="w-full min-w-0 [scrollbar-gutter:stable] overflow-x-auto overscroll-x-contain border-y">
                            <table className="w-full min-w-[58rem] text-sm">
                                <thead>
                                    <tr className="border-b text-left text-muted-foreground">
                                        <th className="py-2">
                                            {translate('Catalog item', locale)}
                                        </th>
                                        <th>
                                            {translate('Description', locale)}
                                        </th>
                                        <th className="w-24">
                                            {translate('Qty.', locale)}
                                        </th>
                                        <th className="w-32">
                                            {translate('Price', locale)}
                                        </th>
                                        <th className="w-24">
                                            {translate('Disc. %', locale)}
                                        </th>
                                        <th className="w-24">
                                            {translate('Tax %', locale)}
                                        </th>
                                        <th className="w-32 text-right">
                                            {translate('Total', locale)}
                                        </th>
                                        <th className="sticky right-0 z-10 w-20 bg-background" />
                                    </tr>
                                </thead>
                                <tbody>
                                    {form.data.lines.map((line, index) => (
                                        <Fragment key={line.client_key}>
                                            <tr className="align-top">
                                                <td className="py-2 pr-2">
                                                    <Select
                                                        value={
                                                            line.product_id
                                                                ? String(
                                                                      line.product_id,
                                                                  )
                                                                : ''
                                                        }
                                                        onValueChange={(
                                                            value,
                                                        ) =>
                                                            selectProduct(
                                                                index,
                                                                value,
                                                            )
                                                        }
                                                    >
                                                        <SelectTrigger>
                                                            <SelectValue
                                                                placeholder={translate(
                                                                    'Custom line',
                                                                    locale,
                                                                )}
                                                            />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            {availableProducts.map(
                                                                (product) => (
                                                                    <SelectItem
                                                                        key={
                                                                            product.id
                                                                        }
                                                                        value={String(
                                                                            product.id,
                                                                        )}
                                                                    >
                                                                        {
                                                                            product.name
                                                                        }
                                                                    </SelectItem>
                                                                ),
                                                            )}
                                                        </SelectContent>
                                                    </Select>
                                                </td>
                                                <td className="p-2">
                                                    <Input
                                                        value={line.description}
                                                        onChange={(event) =>
                                                            updateLine(index, {
                                                                description:
                                                                    event.target
                                                                        .value,
                                                            })
                                                        }
                                                    />
                                                </td>
                                                <td className="p-2">
                                                    <Input
                                                        type="number"
                                                        min="0.001"
                                                        step="0.001"
                                                        value={line.quantity}
                                                        onChange={(event) =>
                                                            updateLine(index, {
                                                                quantity:
                                                                    event.target
                                                                        .value,
                                                            })
                                                        }
                                                    />
                                                </td>
                                                <td className="p-2">
                                                    <Input
                                                        type="number"
                                                        min="0"
                                                        step="0.001"
                                                        value={line.unit_price}
                                                        onChange={(event) =>
                                                            updateLine(index, {
                                                                unit_price:
                                                                    event.target
                                                                        .value,
                                                            })
                                                        }
                                                    />
                                                </td>
                                                <td className="p-2">
                                                    <Input
                                                        type="number"
                                                        min="0"
                                                        max="100"
                                                        step="0.01"
                                                        value={
                                                            line.discount_percent
                                                        }
                                                        onChange={(event) =>
                                                            updateLine(index, {
                                                                discount_percent:
                                                                    event.target
                                                                        .value,
                                                            })
                                                        }
                                                    />
                                                </td>
                                                <td className="p-2">
                                                    <Input
                                                        type="number"
                                                        min="0"
                                                        max="100"
                                                        step="0.01"
                                                        value={line.tax_percent}
                                                        onChange={(event) =>
                                                            updateLine(index, {
                                                                tax_percent:
                                                                    event.target
                                                                        .value,
                                                            })
                                                        }
                                                    />
                                                </td>
                                                <td className="p-2 text-right font-medium">
                                                    <div className="grid gap-1">
                                                        <span>
                                                            {money(
                                                                lineTotal(line),
                                                            )}
                                                        </span>
                                                        <Label
                                                            className="text-xs"
                                                            htmlFor={`gross-${line.client_key}`}
                                                        >
                                                            {locale === 'en'
                                                                ? 'Line total incl. VAT'
                                                                : 'Total de línea con IVA'}
                                                        </Label>
                                                        <Input
                                                            id={`gross-${line.client_key}`}
                                                            type="number"
                                                            min="0"
                                                            step="0.01"
                                                            className="w-36"
                                                            value={
                                                                line.total_with_tax
                                                            }
                                                            placeholder={money(
                                                                lineTotal(line),
                                                            )}
                                                            disabled={
                                                                Number(
                                                                    line.discount_percent,
                                                                ) >= 100
                                                            }
                                                            onChange={(event) =>
                                                                updateLine(
                                                                    index,
                                                                    {
                                                                        total_with_tax:
                                                                            event
                                                                                .target
                                                                                .value,
                                                                    },
                                                                )
                                                            }
                                                        />
                                                        <span className="text-xs text-muted-foreground">
                                                            {locale === 'en'
                                                                ? 'Taxable base'
                                                                : 'Base imponible'}
                                                            :{' '}
                                                            {money(
                                                                lineAmounts(
                                                                    line,
                                                                ).subtotal -
                                                                    lineAmounts(
                                                                        line,
                                                                    ).discount,
                                                            )}
                                                        </span>
                                                    </div>
                                                </td>
                                                <td className="sticky right-0 z-10 bg-background p-2">
                                                    <div className="flex items-center gap-1">
                                                        <Button
                                                            type="button"
                                                            size="icon"
                                                            variant="ghost"
                                                            title={translate(
                                                                'Remove line',
                                                                locale,
                                                            )}
                                                            disabled={
                                                                form.data.lines
                                                                    .length ===
                                                                1
                                                            }
                                                            onClick={() =>
                                                                removeLine(
                                                                    index,
                                                                )
                                                            }
                                                        >
                                                            <Trash2 />
                                                        </Button>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr className="border-b bg-muted/20">
                                                <td
                                                    colSpan={8}
                                                    className="max-w-0 px-3 pt-2 pb-4"
                                                >
                                                    <div className="mb-2 flex flex-wrap items-center justify-between gap-3">
                                                        <Button
                                                            type="button"
                                                            size="sm"
                                                            variant="ghost"
                                                            className="min-w-0 justify-start"
                                                            onClick={() => {
                                                                const next =
                                                                    new Set(
                                                                        expandedLines,
                                                                    );

                                                                if (
                                                                    next.has(
                                                                        line.client_key,
                                                                    )
                                                                ) {
                                                                    next.delete(
                                                                        line.client_key,
                                                                    );
                                                                } else {
                                                                    next.add(
                                                                        line.client_key,
                                                                    );
                                                                }

                                                                setExpandedLines(
                                                                    next,
                                                                );
                                                            }}
                                                        >
                                                            {expandedLines.has(
                                                                line.client_key,
                                                            ) ? (
                                                                <ChevronDown />
                                                            ) : (
                                                                <ChevronRight />
                                                            )}
                                                            <span className="truncate">
                                                                {
                                                                    line
                                                                        .components
                                                                        .length
                                                                }{' '}
                                                                {translate(
                                                                    'components · cost',
                                                                    locale,
                                                                )}{' '}
                                                                {money(
                                                                    componentCost(
                                                                        line,
                                                                    ),
                                                                )}
                                                            </span>
                                                        </Button>
                                                        <div className="flex min-w-0 flex-wrap items-center gap-2">
                                                            <Select
                                                                value={
                                                                    line.selection_mode
                                                                }
                                                                onValueChange={(
                                                                    value: Line['selection_mode'],
                                                                ) =>
                                                                    updateLine(
                                                                        index,
                                                                        {
                                                                            selection_mode:
                                                                                value,
                                                                            option_group:
                                                                                value ===
                                                                                'alternative'
                                                                                    ? line.option_group
                                                                                    : '',
                                                                            is_selected:
                                                                                value ===
                                                                                'included',
                                                                        },
                                                                    )
                                                                }
                                                            >
                                                                <SelectTrigger className="w-36">
                                                                    <SelectValue />
                                                                </SelectTrigger>
                                                                <SelectContent>
                                                                    <SelectItem value="included">
                                                                        {translate(
                                                                            'Included',
                                                                            locale,
                                                                        )}
                                                                    </SelectItem>
                                                                    <SelectItem value="optional">
                                                                        {translate(
                                                                            'Optional',
                                                                            locale,
                                                                        )}
                                                                    </SelectItem>
                                                                    <SelectItem value="alternative">
                                                                        {translate(
                                                                            'Alternative',
                                                                            locale,
                                                                        )}
                                                                    </SelectItem>
                                                                </SelectContent>
                                                            </Select>
                                                            {line.selection_mode ===
                                                                'alternative' && (
                                                                <Input
                                                                    className="w-36"
                                                                    value={
                                                                        line.option_group
                                                                    }
                                                                    placeholder={translate(
                                                                        'Group',
                                                                        locale,
                                                                    )}
                                                                    onChange={(
                                                                        event,
                                                                    ) =>
                                                                        updateLine(
                                                                            index,
                                                                            {
                                                                                option_group:
                                                                                    event
                                                                                        .target
                                                                                        .value,
                                                                            },
                                                                        )
                                                                    }
                                                                />
                                                            )}
                                                            {line.selection_mode !==
                                                                'included' && (
                                                                <Select
                                                                    value={
                                                                        line.is_selected
                                                                            ? 'yes'
                                                                            : 'no'
                                                                    }
                                                                    onValueChange={(
                                                                        value,
                                                                    ) =>
                                                                        updateLine(
                                                                            index,
                                                                            {
                                                                                is_selected:
                                                                                    value ===
                                                                                    'yes',
                                                                            },
                                                                        )
                                                                    }
                                                                >
                                                                    <SelectTrigger className="w-32">
                                                                        <SelectValue />
                                                                    </SelectTrigger>
                                                                    <SelectContent>
                                                                        <SelectItem value="yes">
                                                                            {translate(
                                                                                'Selected',
                                                                                locale,
                                                                            )}
                                                                        </SelectItem>
                                                                        <SelectItem value="no">
                                                                            {translate(
                                                                                'Not included',
                                                                                locale,
                                                                            )}
                                                                        </SelectItem>
                                                                    </SelectContent>
                                                                </Select>
                                                            )}
                                                            <Button
                                                                type="button"
                                                                size="sm"
                                                                variant="outline"
                                                                onClick={() =>
                                                                    updateLine(
                                                                        index,
                                                                        {
                                                                            components:
                                                                                [
                                                                                    ...line.components,
                                                                                    blankComponent(
                                                                                        null,
                                                                                        2,
                                                                                    ),
                                                                                ],
                                                                        },
                                                                    )
                                                                }
                                                            >
                                                                <PackagePlus />{' '}
                                                                {translate(
                                                                    'Add component',
                                                                    locale,
                                                                )}
                                                            </Button>
                                                        </div>
                                                    </div>
                                                    {expandedLines.has(
                                                        line.client_key,
                                                    ) &&
                                                        (line.components
                                                            .length > 0 ? (
                                                            <div className="grid gap-2">
                                                                {line.components.map(
                                                                    (
                                                                        component,
                                                                        componentIndex,
                                                                    ) => (
                                                                        <div
                                                                            key={
                                                                                component.client_key
                                                                            }
                                                                            className="grid min-w-0 gap-2 rounded-md border bg-background p-2 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-[minmax(10rem,1fr)_minmax(12rem,1.4fr)_5.5rem_minmax(10rem,1fr)_minmax(10rem,1fr)_7rem_5.5rem]"
                                                                            style={{
                                                                                marginLeft: `${Math.max(0, component.depth - 2) * 1.25}rem`,
                                                                            }}
                                                                        >
                                                                            <div className="grid min-w-0 gap-1">
                                                                                <span className="text-[11px] font-medium text-muted-foreground">
                                                                                    {translate(
                                                                                        'Level',
                                                                                        locale,
                                                                                    )}{' '}
                                                                                    {
                                                                                        component.depth
                                                                                    }
                                                                                </span>
                                                                                <Select
                                                                                    value={
                                                                                        component.product_id
                                                                                            ? String(
                                                                                                  component.product_id,
                                                                                              )
                                                                                            : ''
                                                                                    }
                                                                                    onValueChange={(
                                                                                        value,
                                                                                    ) =>
                                                                                        selectComponentProduct(
                                                                                            index,
                                                                                            componentIndex,
                                                                                            value,
                                                                                        )
                                                                                    }
                                                                                >
                                                                                    <SelectTrigger className="w-full min-w-0">
                                                                                        <SelectValue
                                                                                            placeholder={translate(
                                                                                                'Custom component',
                                                                                                locale,
                                                                                            )}
                                                                                        />
                                                                                    </SelectTrigger>
                                                                                    <SelectContent>
                                                                                        {availableProducts.map(
                                                                                            (
                                                                                                product,
                                                                                            ) => (
                                                                                                <SelectItem
                                                                                                    key={
                                                                                                        product.id
                                                                                                    }
                                                                                                    value={String(
                                                                                                        product.id,
                                                                                                    )}
                                                                                                >
                                                                                                    {
                                                                                                        product.name
                                                                                                    }
                                                                                                </SelectItem>
                                                                                            ),
                                                                                        )}
                                                                                    </SelectContent>
                                                                                </Select>
                                                                            </div>
                                                                            <Input
                                                                                value={
                                                                                    component.description
                                                                                }
                                                                                placeholder={translate(
                                                                                    'Included description',
                                                                                    locale,
                                                                                )}
                                                                                onChange={(
                                                                                    event,
                                                                                ) =>
                                                                                    updateComponent(
                                                                                        index,
                                                                                        componentIndex,
                                                                                        {
                                                                                            description:
                                                                                                event
                                                                                                    .target
                                                                                                    .value,
                                                                                        },
                                                                                    )
                                                                                }
                                                                            />
                                                                            <Input
                                                                                type="number"
                                                                                min="0.001"
                                                                                step="0.001"
                                                                                value={
                                                                                    component.quantity
                                                                                }
                                                                                aria-label={translate(
                                                                                    'Component quantity',
                                                                                    locale,
                                                                                )}
                                                                                onChange={(
                                                                                    event,
                                                                                ) =>
                                                                                    updateComponent(
                                                                                        index,
                                                                                        componentIndex,
                                                                                        {
                                                                                            quantity:
                                                                                                event
                                                                                                    .target
                                                                                                    .value,
                                                                                        },
                                                                                    )
                                                                                }
                                                                            />
                                                                            <Select
                                                                                value={
                                                                                    component.fulfillment_kind
                                                                                }
                                                                                onValueChange={(
                                                                                    value: Component['fulfillment_kind'],
                                                                                ) =>
                                                                                    updateComponent(
                                                                                        index,
                                                                                        componentIndex,
                                                                                        {
                                                                                            fulfillment_kind:
                                                                                                value,
                                                                                        },
                                                                                    )
                                                                                }
                                                                            >
                                                                                <SelectTrigger className="w-full min-w-0">
                                                                                    <SelectValue />
                                                                                </SelectTrigger>
                                                                                <SelectContent>
                                                                                    <SelectItem value="stock">
                                                                                        {translate(
                                                                                            'Stock control',
                                                                                            locale,
                                                                                        )}
                                                                                    </SelectItem>
                                                                                    <SelectItem value="activity">
                                                                                        {translate(
                                                                                            'Operational tracking',
                                                                                            locale,
                                                                                        )}
                                                                                    </SelectItem>
                                                                                    <SelectItem value="recurring">
                                                                                        {translate(
                                                                                            'Recurring service',
                                                                                            locale,
                                                                                        )}
                                                                                    </SelectItem>
                                                                                    <SelectItem value="none">
                                                                                        {translate(
                                                                                            'Informational',
                                                                                            locale,
                                                                                        )}
                                                                                    </SelectItem>
                                                                                </SelectContent>
                                                                            </Select>
                                                                            <Select
                                                                                value={
                                                                                    component.visibility
                                                                                }
                                                                                onValueChange={(
                                                                                    value: Component['visibility'],
                                                                                ) =>
                                                                                    updateComponent(
                                                                                        index,
                                                                                        componentIndex,
                                                                                        {
                                                                                            visibility:
                                                                                                value,
                                                                                        },
                                                                                    )
                                                                                }
                                                                            >
                                                                                <SelectTrigger className="w-full min-w-0">
                                                                                    <SelectValue />
                                                                                </SelectTrigger>
                                                                                <SelectContent>
                                                                                    <SelectItem value="detailed">
                                                                                        {translate(
                                                                                            'Visible quantity',
                                                                                            locale,
                                                                                        )}
                                                                                    </SelectItem>
                                                                                    <SelectItem value="summary">
                                                                                        {translate(
                                                                                            'Description only',
                                                                                            locale,
                                                                                        )}
                                                                                    </SelectItem>
                                                                                    <SelectItem value="hidden">
                                                                                        {translate(
                                                                                            'Internal only',
                                                                                            locale,
                                                                                        )}
                                                                                    </SelectItem>
                                                                                </SelectContent>
                                                                            </Select>
                                                                            {component.fulfillment_kind ===
                                                                            'recurring' ? (
                                                                                <Input
                                                                                    type="number"
                                                                                    min="1"
                                                                                    max="120"
                                                                                    value={
                                                                                        component.interval_months
                                                                                    }
                                                                                    placeholder={translate(
                                                                                        'Months',
                                                                                        locale,
                                                                                    )}
                                                                                    aria-label={translate(
                                                                                        'Frequency in months',
                                                                                        locale,
                                                                                    )}
                                                                                    onChange={(
                                                                                        event,
                                                                                    ) =>
                                                                                        updateComponent(
                                                                                            index,
                                                                                            componentIndex,
                                                                                            {
                                                                                                interval_months:
                                                                                                    event
                                                                                                        .target
                                                                                                        .value,
                                                                                            },
                                                                                        )
                                                                                    }
                                                                                />
                                                                            ) : (
                                                                                <div className="flex items-center px-2 text-xs text-muted-foreground">
                                                                                    {translate(
                                                                                        'Cost',
                                                                                        locale,
                                                                                    )}{' '}
                                                                                    {money(
                                                                                        Number(
                                                                                            component.planned_unit_cost ||
                                                                                                0,
                                                                                        ),
                                                                                    )}
                                                                                </div>
                                                                            )}
                                                                            <div className="flex items-center justify-end gap-1">
                                                                                <Button
                                                                                    type="button"
                                                                                    size="icon"
                                                                                    variant="ghost"
                                                                                    title={translate(
                                                                                        'Add product or subcomponent',
                                                                                        locale,
                                                                                    )}
                                                                                    disabled={
                                                                                        component.depth >=
                                                                                        5
                                                                                    }
                                                                                    onClick={() =>
                                                                                        addChildComponent(
                                                                                            index,
                                                                                            componentIndex,
                                                                                        )
                                                                                    }
                                                                                >
                                                                                    <Plus />
                                                                                </Button>
                                                                                <Button
                                                                                    type="button"
                                                                                    size="icon"
                                                                                    variant="ghost"
                                                                                    title={translate(
                                                                                        'Remove component and its subcomponents',
                                                                                        locale,
                                                                                    )}
                                                                                    onClick={() =>
                                                                                        removeComponent(
                                                                                            index,
                                                                                            componentIndex,
                                                                                        )
                                                                                    }
                                                                                >
                                                                                    <Trash2 />
                                                                                </Button>
                                                                            </div>
                                                                        </div>
                                                                    ),
                                                                )}
                                                            </div>
                                                        ) : (
                                                            <p className="py-2 text-xs text-muted-foreground">
                                                                {translate(
                                                                    'No technical breakdown. This item will continue to work as a traditional line.',
                                                                    locale,
                                                                )}
                                                            </p>
                                                        ))}
                                                </td>
                                            </tr>
                                        </Fragment>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                        {Object.entries(form.errors)
                            .filter(([key]) => key.startsWith('lines'))
                            .map(([key, error]) => (
                                <p
                                    key={key}
                                    className="mt-2 text-sm text-destructive"
                                >
                                    {error}
                                </p>
                            ))}
                    </FormSection>
                    <FormSection
                        title={translate('Commercial summary', locale)}
                        description={translate(
                            'Review customer-facing notes and calculated totals before saving the draft.',
                            locale,
                        )}
                    >
                        <div className="grid gap-6 lg:grid-cols-[1fr_22rem]">
                            <div className="grid gap-2">
                                <Label htmlFor="notes">
                                    {translate('Commercial notes', locale)}
                                </Label>
                                <textarea
                                    id="notes"
                                    className="min-h-28 rounded-md border bg-transparent p-3 text-sm"
                                    value={form.data.notes}
                                    onChange={(event) =>
                                        form.setData(
                                            'notes',
                                            event.target.value,
                                        )
                                    }
                                />
                            </div>
                            <div className="grid content-start gap-2 text-sm">
                                <Total
                                    label={translate('Subtotal', locale)}
                                    value={totals.subtotal}
                                />
                                <Total
                                    label={translate('Discount', locale)}
                                    value={-totals.discount}
                                />
                                <Total
                                    label={translate('Tax', locale)}
                                    value={totals.tax}
                                />
                                <Total
                                    label={translate('Total', locale)}
                                    value={totals.total}
                                    strong
                                />
                            </div>
                        </div>
                    </FormSection>
                    {quote ? (
                        <FormSection
                            title={translate('Revision traceability', locale)}
                            description={translate(
                                'Explain what changed so this proposal version remains auditable.',
                                locale,
                            )}
                        >
                            <div className="grid gap-2">
                                <Label htmlFor="change_note">
                                    {translate(
                                        'Reason for this revision',
                                        locale,
                                    )}
                                </Label>
                                <Textarea
                                    id="change_note"
                                    required
                                    minLength={5}
                                    value={form.data.change_note}
                                    onChange={(event) =>
                                        form.setData(
                                            'change_note',
                                            event.target.value,
                                        )
                                    }
                                    placeholder={translate(
                                        'What changed and why: scope, price, terms or customer request',
                                        locale,
                                    )}
                                />
                                <InputError message={form.errors.change_note} />
                            </div>
                        </FormSection>
                    ) : null}
                    <FormActions
                        processing={form.processing}
                        submitLabel={translate(
                            quote ? 'Save draft changes' : 'Save as draft',
                            locale,
                        )}
                        description={translate(
                            'The proposal will remain private and editable until you send it definitively from its record.',
                            locale,
                        )}
                        icon={<Save />}
                    />
                </form>
            </div>
        </>
    );
}
let lineSequence = 0;

function blankLine(): Line {
    return {
        client_key: `new-line-${Date.now()}-${++lineSequence}`,
        depth: 1,
        product_id: null,
        unit_id: null,
        tax_rate_id: null,
        description: '',
        quantity: '1',
        unit_price: '0',
        total_with_tax: '',
        discount_percent: '0',
        tax_percent: '21',
        selection_mode: 'included',
        option_group: '',
        is_selected: true,
        components: [],
    };
}
let componentSequence = 0;

function componentIdentity(parentClientKey: string | null = null, depth = 2) {
    return {
        client_key: `new-component-${Date.now()}-${++componentSequence}`,
        parent_client_key: parentClientKey,
        depth,
    };
}
function blankComponent(
    parentClientKey: string | null = null,
    depth = 2,
): Component {
    return {
        ...componentIdentity(parentClientKey, depth),
        product_id: null,
        unit_id: null,
        kind: 'other',
        fulfillment_kind: 'none',
        visibility: 'detailed',
        description: '',
        quantity: '1',
        planned_unit_cost: '0',
        interval_months: '',
    };
}
function componentFromProduct(product: Product): Component {
    return {
        ...blankComponent(),
        product_id: product.id,
        unit_id: product.unit_id,
        kind: product.type === 'service' ? 'service' : 'material',
        fulfillment_kind:
            product.type === 'service'
                ? 'activity'
                : product.track_stock
                  ? 'stock'
                  : 'none',
        description: product.description || product.name,
        planned_unit_cost: product.cost_price,
    };
}
function roundMoney(value: number) {
    return Math.round((value + Number.EPSILON) * 100) / 100;
}
function updateLinePrice(line: Line, changes: Partial<Line>): Line {
    const next = { ...line, ...changes };

    if (
        changes.unit_price !== undefined ||
        Number(next.discount_percent) >= 100
    ) {
        next.total_with_tax = '';
    }

    if (next.total_with_tax !== '' && Number(next.quantity) > 0) {
        const base = roundMoney(
            Number(next.total_with_tax) / (1 + Number(next.tax_percent) / 100),
        );
        const subtotal = roundMoney(
            base / (1 - Number(next.discount_percent) / 100),
        );
        next.unit_price = (subtotal / Number(next.quantity)).toFixed(3);
    }

    return next;
}
function lineAmounts(line: Line) {
    let subtotal = roundMoney(Number(line.quantity) * Number(line.unit_price));
    let discount = roundMoney((subtotal * Number(line.discount_percent)) / 100);
    let tax = roundMoney(
        ((subtotal - discount) * Number(line.tax_percent)) / 100,
    );
    let total = roundMoney(subtotal - discount + tax);

    if (line.total_with_tax !== '' && line.total_with_tax != null) {
        total = roundMoney(Number(line.total_with_tax));
        const base = roundMoney(total / (1 + Number(line.tax_percent) / 100));
        const factor = 1 - Number(line.discount_percent) / 100;
        subtotal = factor > 0 ? roundMoney(base / factor) : 0;
        discount = roundMoney(subtotal - base);
        tax = roundMoney(total - base);
    }

    return { subtotal, discount, tax, total };
}
function lineTotal(line: Line) {
    return lineAmounts(line).total;
}
function calculate(lines: Line[]) {
    return lines.reduce(
        (sum, line) => {
            if (!line.is_selected) {
                return sum;
            }

            const amounts = lineAmounts(line);

            return {
                subtotal: roundMoney(sum.subtotal + amounts.subtotal),
                discount: roundMoney(sum.discount + amounts.discount),
                tax: roundMoney(sum.tax + amounts.tax),
                total: roundMoney(sum.total + amounts.total),
            };
        },
        { subtotal: 0, discount: 0, tax: 0, total: 0 },
    );
}
function componentCost(line: Line) {
    const componentsByKey = new Map(
        line.components.map((component) => [component.client_key, component]),
    );

    return (
        Number(line.quantity) *
        line.components.reduce((sum, component) => {
            let quantity = Number(component.quantity);
            let parentKey = component.parent_client_key;

            while (parentKey) {
                const parent = componentsByKey.get(parentKey);

                if (!parent) {
                    break;
                }

                quantity *= Number(parent.quantity);
                parentKey = parent.parent_client_key;
            }

            return sum + quantity * Number(component.planned_unit_cost || 0);
        }, 0)
    );
}
function money(value: number) {
    return new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: 'EUR',
    }).format(value);
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
        <div className="grid gap-2">
            <Label>{label}</Label>
            <Input
                type={type}
                value={value}
                onChange={(event) => onChange(event.target.value)}
            />
        </div>
    );
}
function SelectField({
    label,
    value,
    onChange,
    options,
}: {
    label: string;
    value: string;
    onChange: (value: string) => void;
    options: string[][];
}) {
    return (
        <div className="grid gap-2">
            <Label>{label}</Label>
            <Select value={value} onValueChange={onChange}>
                <SelectTrigger>
                    <SelectValue
                        placeholder={`Select ${label.toLowerCase()}`}
                    />
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
function Total({
    label,
    value,
    strong = false,
}: {
    label: string;
    value: number;
    strong?: boolean;
}) {
    return (
        <div
            className={`flex justify-between border-b py-2 ${strong ? 'text-base font-semibold' : ''}`}
        >
            <span>{label}</span>
            <span>{money(value)}</span>
        </div>
    );
}
QuoteForm.layout = {
    breadcrumbs: [
        { title: 'Propuestas', href: '/quotes' },
        { title: 'Propuesta', href: '#' },
    ],
};

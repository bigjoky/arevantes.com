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
import { Fragment, useState } from 'react';
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
import { serviceLabels } from '@/lib/business-lines';
import type { ServiceType } from '@/lib/business-lines';

type Customer = {
    id: number;
    first_name: string;
    last_name: string | null;
    company_name: string | null;
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
    product_id: number | null;
    unit_id: number | null;
    tax_rate_id: number | null;
    description: string;
    quantity: string;
    unit_price: string;
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
            new Date().toISOString().slice(0, 10),
        valid_until: quote?.valid_until?.slice(0, 10) ?? '',
        acceptance_payment_percentage: String(
            quote?.acceptance_payment_percentage ??
                acceptancePaymentDefaults.percentage,
        ),
        acceptance_payment_due_days: String(
            quote?.acceptance_payment_due_days ??
                acceptancePaymentDefaults.due_days,
        ),
        notes:
            quote?.notes ??
            opportunityDraft?.notes ??
            (opportunityDraft
                ? `Prepared from opportunity ${opportunityDraft.code}.`
                : ''),
        lines:
            quote?.lines.map((line) => ({
                ...line,
                product_id: line.product_id,
                unit_id: line.unit_id,
                tax_rate_id: line.tax_rate_id,
                selection_mode: line.selection_mode ?? 'included',
                option_group: line.option_group ?? '',
                is_selected: line.is_selected ?? true,
                components: (line.components || []).map((component) => ({
                    ...component,
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
    const [expandedLines, setExpandedLines] = useState<Set<number>>(
        new Set([0]),
    );
    const availableInstallations = installations.filter(
        (item) =>
            !form.data.person_id ||
            item.person_id === Number(form.data.person_id),
    );
    const availableProducts = products.filter(
        (product) =>
            !form.data.service_type ||
            !product.business_lines?.length ||
            product.business_lines.includes(form.data.service_type),
    );
    const totals = calculate(form.data.lines);
    const updateLine = (index: number, changes: Partial<Line>) =>
        form.setData(
            'lines',
            form.data.lines.map((line, position) =>
                position === index ? { ...line, ...changes } : line,
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
    const submit = () =>
        quote ? form.put(`/quotes/${quote.id}`) : form.post('/quotes');

    return (
        <>
            <Head title={quote ? `Edit ${quote.number}` : 'New quote'} />
            <div className="flex min-w-0 flex-1 flex-col gap-6 overflow-x-clip p-4 md:p-6">
                <header className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold">
                            {quote ? `Edit ${quote.number}` : 'New quote'}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {opportunityDraft
                                ? `Prepared from ${opportunityDraft.code}. Review prices and taxes before saving.`
                                : 'Build the commercial offer from catalog items or free-text lines.'}
                        </p>
                    </div>
                    <Button variant="outline" asChild>
                        <Link
                            href={quote ? `/quotes/${quote.id}` : '/operations'}
                        >
                            <ArrowLeft /> Back
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
                    <section className="grid gap-4 border-t pt-5 md:grid-cols-2 xl:grid-cols-5">
                        <SelectField
                            label="Customer"
                            value={form.data.person_id}
                            onChange={(value) => {
                                form.setData((data) => ({
                                    ...data,
                                    person_id: value,
                                    installation_id: '',
                                }));
                            }}
                            options={customers.map((customer) => [
                                String(customer.id),
                                customer.company_name ||
                                    `${customer.first_name} ${customer.last_name || ''}`.trim(),
                            ])}
                        />
                        <SelectField
                            label="Business line"
                            value={form.data.service_type}
                            onChange={(value) =>
                                form.setData('service_type', value as ServiceType)
                            }
                            options={Object.entries(serviceLabels)}
                        />
                        <SelectField
                            label="Installation (optional)"
                            value={form.data.installation_id}
                            onChange={(value) =>
                                form.setData(
                                    'installation_id',
                                    value === 'none' ? '' : value,
                                )
                            }
                            options={[
                                ['none', 'No installation'],
                                ...availableInstallations.map((item) => [
                                    String(item.id),
                                    item.project_reference ||
                                        item.site_name ||
                                        `#${item.id}`,
                                ]),
                            ]}
                        />
                        <Field
                            label="Issue date"
                            type="date"
                            value={form.data.issue_date}
                            onChange={(value) =>
                                form.setData('issue_date', value)
                            }
                        />
                        <Field
                            label="Valid until"
                            type="date"
                            value={form.data.valid_until}
                            onChange={(value) =>
                                form.setData('valid_until', value)
                            }
                        />
                    </section>
                    <section className="grid gap-4 border-t pt-5 md:grid-cols-2">
                        <Field
                            label="Acceptance payment (%)"
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
                            label="Payment term after acceptance (days)"
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
                            When the customer accepts, the platform issues this
                            advance invoice automatically. Use 0% when no
                            acceptance payment is required.
                        </p>
                    </section>
                    <section>
                        <div className="mb-3 flex items-center justify-between">
                            <div>
                                <h2 className="font-semibold">Lines</h2>
                                <p className="text-sm text-muted-foreground">
                                    Prices are before tax.
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
                                <Plus /> Add line
                            </Button>
                        </div>
                        <div className="w-full min-w-0 [scrollbar-gutter:stable] overflow-x-auto overscroll-x-contain border-y">
                            <table className="w-full min-w-[58rem] text-sm">
                                <thead>
                                    <tr className="border-b text-left text-muted-foreground">
                                        <th className="py-2">Catalog item</th>
                                        <th>Description</th>
                                        <th className="w-24">Qty.</th>
                                        <th className="w-32">Price</th>
                                        <th className="w-24">Disc. %</th>
                                        <th className="w-24">Tax %</th>
                                        <th className="w-32 text-right">
                                            Total
                                        </th>
                                        <th className="w-12" />
                                    </tr>
                                </thead>
                                <tbody>
                                    {form.data.lines.map((line, index) => (
                                        <Fragment key={index}>
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
                                                            <SelectValue placeholder="Custom line" />
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
                                                        step="0.01"
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
                                                    {money(lineTotal(line))}
                                                </td>
                                                <td className="p-2">
                                                    <Button
                                                        type="button"
                                                        size="icon"
                                                        variant="ghost"
                                                        title="Remove line"
                                                        disabled={
                                                            form.data.lines
                                                                .length === 1
                                                        }
                                                        onClick={() =>
                                                            form.setData(
                                                                'lines',
                                                                form.data.lines.filter(
                                                                    (
                                                                        _,
                                                                        position,
                                                                    ) =>
                                                                        position !==
                                                                        index,
                                                                ),
                                                            )
                                                        }
                                                    >
                                                        <Trash2 />
                                                    </Button>
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
                                                                        index,
                                                                    )
                                                                ) {
                                                                    next.delete(
                                                                        index,
                                                                    );
                                                                } else {
                                                                    next.add(
                                                                        index,
                                                                    );
                                                                }

                                                                setExpandedLines(
                                                                    next,
                                                                );
                                                            }}
                                                        >
                                                            {expandedLines.has(
                                                                index,
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
                                                                componentes ·
                                                                coste{' '}
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
                                                                        Incluida
                                                                    </SelectItem>
                                                                    <SelectItem value="optional">
                                                                        Opcional
                                                                    </SelectItem>
                                                                    <SelectItem value="alternative">
                                                                        Alternativa
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
                                                                    placeholder="Grupo"
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
                                                                            Seleccionada
                                                                        </SelectItem>
                                                                        <SelectItem value="no">
                                                                            No
                                                                            incluida
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
                                                                                    blankComponent(),
                                                                                ],
                                                                        },
                                                                    )
                                                                }
                                                            >
                                                                <PackagePlus />{' '}
                                                                Añadir
                                                                componente
                                                            </Button>
                                                        </div>
                                                    </div>
                                                    {expandedLines.has(index) &&
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
                                                                                componentIndex
                                                                            }
                                                                            className="grid min-w-0 gap-2 rounded-md border bg-background p-2 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-[minmax(10rem,1fr)_minmax(12rem,1.4fr)_5.5rem_minmax(10rem,1fr)_minmax(10rem,1fr)_7rem_3rem]"
                                                                        >
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
                                                                                    <SelectValue placeholder="Producto o servicio" />
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
                                                                            <Input
                                                                                value={
                                                                                    component.description
                                                                                }
                                                                                placeholder="Descripción incluida"
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
                                                                                aria-label="Cantidad del componente"
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
                                                                                        Control
                                                                                        de
                                                                                        stock
                                                                                    </SelectItem>
                                                                                    <SelectItem value="activity">
                                                                                        Seguimiento
                                                                                        operativo
                                                                                    </SelectItem>
                                                                                    <SelectItem value="recurring">
                                                                                        Servicio
                                                                                        recurrente
                                                                                    </SelectItem>
                                                                                    <SelectItem value="none">
                                                                                        Informativo
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
                                                                                        Cantidad
                                                                                        visible
                                                                                    </SelectItem>
                                                                                    <SelectItem value="summary">
                                                                                        Solo
                                                                                        descripción
                                                                                    </SelectItem>
                                                                                    <SelectItem value="hidden">
                                                                                        Solo
                                                                                        interno
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
                                                                                    placeholder="Meses"
                                                                                    aria-label="Periodicidad en meses"
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
                                                                                    Coste{' '}
                                                                                    {money(
                                                                                        Number(
                                                                                            component.planned_unit_cost ||
                                                                                                0,
                                                                                        ),
                                                                                    )}
                                                                                </div>
                                                                            )}
                                                                            <Button
                                                                                type="button"
                                                                                size="icon"
                                                                                variant="ghost"
                                                                                title="Eliminar componente"
                                                                                onClick={() =>
                                                                                    updateLine(
                                                                                        index,
                                                                                        {
                                                                                            components:
                                                                                                line.components.filter(
                                                                                                    (
                                                                                                        _,
                                                                                                        position,
                                                                                                    ) =>
                                                                                                        position !==
                                                                                                        componentIndex,
                                                                                                ),
                                                                                        },
                                                                                    )
                                                                                }
                                                                            >
                                                                                <Trash2 />
                                                                            </Button>
                                                                        </div>
                                                                    ),
                                                                )}
                                                            </div>
                                                        ) : (
                                                            <p className="py-2 text-xs text-muted-foreground">
                                                                Sin desglose
                                                                técnico. La
                                                                partida seguirá
                                                                funcionando como
                                                                una línea
                                                                tradicional.
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
                    </section>
                    <section className="grid gap-6 border-t pt-5 lg:grid-cols-[1fr_22rem]">
                        <div className="grid gap-2">
                            <Label htmlFor="notes">Commercial notes</Label>
                            <textarea
                                id="notes"
                                className="min-h-28 rounded-md border bg-transparent p-3 text-sm"
                                value={form.data.notes}
                                onChange={(event) =>
                                    form.setData('notes', event.target.value)
                                }
                            />
                        </div>
                        <div className="grid content-start gap-2 text-sm">
                            <Total label="Subtotal" value={totals.subtotal} />
                            <Total label="Discount" value={-totals.discount} />
                            <Total label="Tax" value={totals.tax} />
                            <Total label="Total" value={totals.total} strong />
                        </div>
                    </section>
                    {Object.entries(form.errors)
                        .filter(([key]) => !key.startsWith('lines'))
                        .map(([key, error]) => (
                            <p key={key} className="text-sm text-destructive">
                                {error}
                            </p>
                        ))}
                    <div className="flex justify-end">
                        <Button disabled={form.processing}>
                            <Save /> {quote ? 'Save quote' : 'Create quote'}
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}
function blankLine(): Line {
    return {
        product_id: null,
        unit_id: null,
        tax_rate_id: null,
        description: '',
        quantity: '1',
        unit_price: '0',
        discount_percent: '0',
        tax_percent: '21',
        selection_mode: 'included',
        option_group: '',
        is_selected: true,
        components: [],
    };
}
function blankComponent(): Component {
    return {
        product_id: null,
        unit_id: null,
        kind: 'material',
        fulfillment_kind: 'stock',
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
function lineTotal(line: Line) {
    const base = Number(line.quantity) * Number(line.unit_price);

    return (
        base *
        (1 - Number(line.discount_percent) / 100) *
        (1 + Number(line.tax_percent) / 100)
    );
}
function calculate(lines: Line[]) {
    return lines.reduce(
        (sum, line) => {
            if (!line.is_selected) {
                return sum;
            }

            const subtotal = Number(line.quantity) * Number(line.unit_price);
            const discount = (subtotal * Number(line.discount_percent)) / 100;
            const tax =
                ((subtotal - discount) * Number(line.tax_percent)) / 100;

            return {
                subtotal: sum.subtotal + subtotal,
                discount: sum.discount + discount,
                tax: sum.tax + tax,
                total: sum.total + subtotal - discount + tax,
            };
        },
        { subtotal: 0, discount: 0, tax: 0, total: 0 },
    );
}
function componentCost(line: Line) {
    return (
        Number(line.quantity) *
        line.components.reduce(
            (sum, component) =>
                sum +
                Number(component.quantity) *
                    Number(component.planned_unit_cost || 0),
            0,
        )
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
        { title: 'Operations', href: '/operations' },
        { title: 'Quote', href: '#' },
    ],
};

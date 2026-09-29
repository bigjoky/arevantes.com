import { Form, Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import { ExpenseCalculationPreview } from '@/components/expense-calculation-preview';
import { FormErrorSummary } from '@/components/forms/form-layout';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useLocale } from '@/hooks/use-locale';
import { translate } from '@/i18n';

type TaxLine = {
    kind: string;
    taxable_base: string;
    tax_rate: string;
    tax_amount: string;
    deductible_percent: string;
    exemption_reason: string | null;
};
type Expense = { id: number; number: string; tax_lines: TaxLine[] } & Record<
    string,
    unknown
>;
export default function ExpenseEdit({
    expense,
    revision,
    categories,
    suppliers,
    installations,
    countries,
    canUseGroupedCalculation,
}: {
    expense: Expense;
    revision: string;
    categories: { id: number; name: string }[];
    suppliers: { id: number; legal_name: string }[];
    installations: {
        id: number;
        project_reference: string | null;
        site_name: string | null;
    }[];
    countries: { code: string; name: string }[];
    canUseGroupedCalculation: boolean;
}) {
    const { locale } = useLocale();
    const t = (key: string) => translate(key, locale);
    const snapshot = expense.tax_calculation_snapshot as {
        source_tax_lines?: { calculation_id: string }[];
        input?: { quota_overrides?: { reason: string | null }[] };
    } | null;
    const [lines, setLines] = useState(() =>
        (expense.tax_lines.length
            ? expense.tax_lines
            : [
                  {
                      kind: 'vat',
                      taxable_base: String(expense.subtotal ?? '0'),
                      tax_rate:
                          Number(expense.subtotal) > 0
                              ? String(
                                    (Number(expense.tax_amount) /
                                        Number(expense.subtotal)) *
                                        100,
                                )
                              : '0',
                      tax_amount: String(expense.tax_amount ?? '0'),
                      deductible_percent: '100',
                      exemption_reason: null,
                  },
              ]
        ).map((line, index) => ({
            ...line,
            key:
                snapshot?.source_tax_lines?.[index]?.calculation_id ??
                `existing-${index}`,
        })),
    );
    const fields = [
        ['document_number', 'Issuer invoice number', 'text'],
        ['invoice_series', 'Invoice series (optional)', 'text'],
        ['issuer_legal_name', 'Issuer legal name', 'text'],
        ['issuer_tax_id', 'Tax ID', 'text'],
        ['merchant_name', 'Trade name (optional)', 'text'],
        ['expense_date', 'Issue date', 'date'],
        ['operation_date', 'Operation date', 'date'],
        ['due_date', 'Due date', 'date'],
        ['currency_code', 'Currency', 'text'],
        ['deductible_expense_percent', 'Deductible expense (%)', 'number'],
        ['withholding_rate', 'Withholding rate', 'number'],
        ['withholding_amount', 'Withholding amount', 'number'],
    ];
    const selects: [string, string, [string, string][]][] = [
        [
            'expense_category_id',
            'Category',
            categories.map((item) => [String(item.id), item.name]),
        ],
        [
            'supplier_id',
            'Supplier (optional)',
            [
                ['', t('No linked supplier')],
                ...suppliers.map(
                    (item) =>
                        [String(item.id), item.legal_name] as [string, string],
                ),
            ],
        ],
        [
            'installation_id',
            'Installation (optional)',
            [
                ['', t('No linked installation')],
                ...installations.map(
                    (item) =>
                        [
                            String(item.id),
                            item.project_reference ||
                                item.site_name ||
                                `#${item.id}`,
                        ] as [string, string],
                ),
            ],
        ],
        [
            'type',
            'Fiscal document type',
            [
                ['receipt', t('Receipt')],
                ['simplified_invoice', t('Simplified invoice')],
                ['invoice', t('Full invoice')],
                ['other', t('Other supporting document')],
            ],
        ],
        [
            'tax_regime',
            'Tax regime',
            [
                'standard',
                'exempt',
                'non_subject',
                'reverse_charge',
                'intra_community',
                'import',
            ].map((value) => [value, t(value)]),
        ],
        [
            'issuer_country',
            'Country',
            [
                ['', '—'],
                ...countries.map(
                    (item) => [item.code, t(item.name)] as [string, string],
                ),
            ],
        ],
    ];

    return (
        <div className="mx-auto flex w-full max-w-5xl flex-col gap-5 p-4 md:p-6">
            <Head title={`${t('Edit expense')} · ${expense.number}`} />
            <header className="flex flex-wrap items-center justify-between gap-3">
                <h1 className="text-xl font-semibold">
                    {t('Edit expense')} · {expense.number}
                </h1>
                <Button asChild variant="outline">
                    <Link href={`/expenses/${expense.id}`}>{t('Cancel')}</Link>
                </Button>
            </header>
            <p className="text-sm text-muted-foreground">
                {t(
                    'Corrections retain history and update accounting. Paid expenses and protected periods cannot be edited.',
                )}
            </p>
            <Form
                action={`/expenses/${expense.id}`}
                method="patch"
                className="grid gap-5"
            >
                {({ errors, processing }) => (
                    <>
                        <FormErrorSummary errors={errors} />
                        <ExpenseCalculationPreview
                            available={canUseGroupedCalculation}
                            required={Boolean(expense.tax_calculation_version)}
                            lineIds={lines.map((line) => line.key)}
                            initialReason={
                                snapshot?.input?.quota_overrides?.[0]?.reason ??
                                ''
                            }
                        />
                        <input type="hidden" name="revision" value={revision} />
                        <section className="grid gap-4 rounded-md border p-4 sm:grid-cols-2">
                            {selects.map(([name, label, options]) => (
                                <label
                                    key={name}
                                    className="grid gap-2 text-sm"
                                >
                                    {t(label)}
                                    <select
                                        name={name}
                                        defaultValue={String(
                                            expense[name] ?? '',
                                        )}
                                        className="min-w-0 rounded-md border bg-background p-2"
                                    >
                                        {options.map(([value, text]) => (
                                            <option key={value} value={value}>
                                                {text}
                                            </option>
                                        ))}
                                    </select>
                                </label>
                            ))}
                            {fields.map(([name, label, type]) => (
                                <label
                                    key={name}
                                    className="grid gap-2 text-sm"
                                >
                                    {t(label)}
                                    <Input
                                        name={name}
                                        type={type}
                                        step={
                                            type === 'number'
                                                ? 'any'
                                                : undefined
                                        }
                                        defaultValue={String(
                                            expense[name] ?? '',
                                        ).slice(
                                            0,
                                            type === 'date' ? 10 : undefined,
                                        )}
                                        required={[
                                            'expense_date',
                                            'currency_code',
                                        ].includes(name)}
                                    />
                                </label>
                            ))}
                        </section>
                        <section className="grid gap-4">
                            <h2 className="font-semibold">
                                {t('Tax breakdown')}
                            </h2>
                            {lines.map((line, index) => (
                                <fieldset
                                    key={line.key}
                                    className="grid gap-3 rounded-md border p-4 sm:grid-cols-2 lg:grid-cols-3"
                                >
                                    <legend>
                                        {t('Tax line')} {index + 1}
                                    </legend>
                                    <label className="grid gap-2 text-sm">
                                        {t('Type')}
                                        <select
                                            name={`tax_lines[${index}][kind]`}
                                            defaultValue={line.kind}
                                            className="rounded-md border bg-background p-2"
                                        >
                                            <option value="vat">
                                                {t('VAT')}
                                            </option>
                                            <option value="equivalence_surcharge">
                                                {t('Equivalence surcharge')}
                                            </option>
                                        </select>
                                    </label>
                                    {(
                                        [
                                            'taxable_base',
                                            'tax_rate',
                                            'tax_amount',
                                            'deductible_percent',
                                        ] as const
                                    ).map((field, fieldIndex) => (
                                        <label
                                            key={field}
                                            className="grid gap-2 text-sm"
                                        >
                                            {t(
                                                [
                                                    'Taxable base',
                                                    'Tax rate',
                                                    'Tax amount',
                                                    'Deductible VAT (%)',
                                                ][fieldIndex],
                                            )}
                                            <Input
                                                name={`tax_lines[${index}][${field}]`}
                                                type="number"
                                                min="0"
                                                max={
                                                    field.includes('percent') ||
                                                    field === 'tax_rate'
                                                        ? 100
                                                        : undefined
                                                }
                                                step={
                                                    field === 'tax_amount'
                                                        ? '0.01'
                                                        : 'any'
                                                }
                                                defaultValue={line[field]}
                                                required
                                            />
                                        </label>
                                    ))}
                                    <label className="grid gap-2 text-sm">
                                        {t('Exemption reason')}
                                        <Input
                                            name={`tax_lines[${index}][exemption_reason]`}
                                            defaultValue={
                                                line.exemption_reason ?? ''
                                            }
                                        />
                                    </label>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        disabled={lines.length <= 1}
                                        onClick={() =>
                                            setLines((current) =>
                                                current.filter(
                                                    (item) =>
                                                        item.key !== line.key,
                                                ),
                                            )
                                        }
                                    >
                                        {t('Remove')}
                                    </Button>
                                </fieldset>
                            ))}
                            <Button
                                type="button"
                                variant="outline"
                                className="w-fit"
                                disabled={lines.length >= 20}
                                onClick={() =>
                                    setLines((current) => [
                                        ...current,
                                        {
                                            key: crypto.randomUUID(),
                                            kind: 'vat',
                                            taxable_base: '0',
                                            tax_rate: '21',
                                            tax_amount: '0',
                                            deductible_percent: '100',
                                            exemption_reason: '',
                                        },
                                    ])
                                }
                            >
                                {t('Add tax line')}
                            </Button>
                        </section>
                        <label className="grid gap-2 text-sm">
                            {t('Notes')}
                            <textarea
                                name="notes"
                                defaultValue={String(expense.notes ?? '')}
                                rows={3}
                                className="rounded-md border bg-background p-3"
                            />
                        </label>
                        <label className="grid gap-2 text-sm">
                            {t('Correction reason')}
                            <textarea
                                name="correction_reason"
                                required
                                minLength={5}
                                maxLength={2000}
                                rows={2}
                                className="rounded-md border bg-background p-3"
                            />
                        </label>
                        <Button
                            disabled={
                                processing ||
                                (Boolean(expense.tax_calculation_version) &&
                                    !canUseGroupedCalculation)
                            }
                            className="w-fit"
                        >
                            {t('Save correction')}
                        </Button>
                    </>
                )}
            </Form>
        </div>
    );
}
ExpenseEdit.layout = {
    breadcrumbs: [
        { title: 'Expenses', href: '/expenses' },
        { title: 'Edit expense', href: '#' },
    ],
};

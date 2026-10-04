import { Head, Link, useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { useLocale } from '@/hooks/use-locale';
import { translate } from '@/i18n';

const classes = {
    pending: 'Pending classification',
    domestic_current: 'Domestic current goods and services',
    domestic_investment: 'Domestic investment goods',
};
type OperationClass = keyof typeof classes;
type Props = {
    expense: {
        id: number;
        number: string;
        document_number: string;
        issuer_legal_name: string;
        expense_date: string;
        subtotal: string;
        tax_amount: string;
    };
    classification: {
        operation_class: OperationClass;
        reviewed_by_name: string | null;
        reviewed_at: string;
    } | null;
    revision: string;
    current: boolean;
    issues: string[];
    canReview: boolean;
    taxLines: {
        id: number;
        taxable_base: string;
        tax_rate: string;
        tax_amount: string;
        deductible_percent: string;
        deductible_amount: string;
    }[];
    history: {
        id: number;
        author: string | null;
        date: string;
        operation_class: OperationClass;
        reason: string;
    }[];
};

export default function ExpenseClassification({
    expense,
    classification,
    revision,
    current,
    issues,
    canReview,
    history,
    taxLines,
}: Props) {
    const { locale } = useLocale();
    const t = (key: string) => translate(key, locale);
    const money = new Intl.NumberFormat(locale, {
        style: 'currency',
        currency: 'EUR',
    });
    const percent = new Intl.NumberFormat(locale, { maximumFractionDigits: 3 });
    const form = useForm({
        operation_class: classification?.operation_class ?? 'pending',
        reason: '',
        confirmed: false,
        revision,
    });
    const date = new Date(`${expense.expense_date.slice(0, 10)}T12:00:00`);
    const quarter = Math.floor(date.getMonth() / 3) + 1;

    return (
        <>
            <Head title={t('Expense fiscal classification')} />
            <main className="mx-auto flex w-full max-w-4xl flex-col gap-5 p-4 md:p-6">
                <header className="flex flex-wrap items-center justify-between gap-3">
                    <h1 className="text-xl font-semibold">
                        {t('Expense fiscal classification')}
                    </h1>
                    <Button variant="outline" asChild>
                        <Link
                            href={`/tax/models/303?year=${date.getFullYear()}&quarter=${quarter}`}
                        >
                            {t('Model 303')}
                        </Link>
                    </Button>
                </header>
                <section className="rounded-lg border p-4">
                    <Link
                        className="font-semibold underline"
                        href={`/expenses/${expense.id}`}
                    >
                        {expense.number}
                    </Link>
                    <p>
                        {expense.document_number} · {expense.issuer_legal_name}
                    </p>
                    <p>
                        {expense.expense_date.slice(0, 10)} ·{' '}
                        {t('Taxable base')}:{' '}
                        {money.format(Number(expense.subtotal))} · {t('VAT')}:{' '}
                        {money.format(Number(expense.tax_amount))}
                    </p>
                    <p className="mt-2 text-sm text-muted-foreground">
                        {t(
                            'This review changes fiscal classification only, not invoice amounts, accounts or payments.',
                        )}
                    </p>
                </section>
                <section
                    className="rounded-lg border p-4 text-sm"
                    aria-live="polite"
                >
                    <p>
                        {current
                            ? t(
                                  'Fiscal classification matches current evidence.',
                              )
                            : t(
                                  'Unreviewed or changed evidence: this expense remains pending in the model.',
                              )}
                    </p>
                    {classification ? (
                        <p>
                            {classification.reviewed_by_name ?? '—'} ·{' '}
                            {new Date(
                                classification.reviewed_at,
                            ).toLocaleString(locale)}
                        </p>
                    ) : null}
                    {issues.length > 0 ? (
                        <ul className="mt-2 list-inside list-disc">
                            {issues.map((issue) => (
                                <li key={issue}>{t(issue)}</li>
                            ))}
                        </ul>
                    ) : null}
                </section>
                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-left text-sm">
                        <caption className="p-3 text-left font-medium">
                            {t('Documentary VAT breakdown')}
                        </caption>
                        <thead>
                            <tr>
                                <th className="p-3">{t('Taxable base')}</th>
                                <th className="p-3">{t('Rate')}</th>
                                <th className="p-3">{t('VAT')}</th>
                                <th className="p-3">{t('Deductible VAT')}</th>
                            </tr>
                        </thead>
                        <tbody>
                            {taxLines.map((line) => (
                                <tr key={line.id} className="border-t">
                                    <td className="p-3">
                                        {money.format(
                                            Number(line.taxable_base),
                                        )}
                                    </td>
                                    <td className="p-3">
                                        {percent.format(Number(line.tax_rate))}%
                                    </td>
                                    <td className="p-3">
                                        {money.format(Number(line.tax_amount))}
                                    </td>
                                    <td className="p-3">
                                        {money.format(
                                            Number(line.deductible_amount),
                                        )}{' '}
                                        (
                                        {percent.format(
                                            Number(line.deductible_percent),
                                        )}
                                        %)
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                <form
                    className="grid gap-4 rounded-lg border p-4"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.transform((data) => ({ ...data, revision }));
                        form.post(
                            `/tax/expenses/${expense.id}/classification`,
                            {
                                onSuccess: () =>
                                    form.reset('reason', 'confirmed'),
                            },
                        );
                    }}
                >
                    <label className="grid gap-2">
                        {t('Fiscal operation class')}
                        <select
                            className="h-10 w-full rounded border bg-background px-3"
                            value={form.data.operation_class}
                            onChange={(event) =>
                                form.setData(
                                    'operation_class',
                                    event.target.value as OperationClass,
                                )
                            }
                        >
                            {Object.entries(classes).map(([value, label]) => (
                                <option key={value} value={value}>
                                    {t(label)}
                                </option>
                            ))}
                        </select>
                    </label>
                    <p className="text-sm text-muted-foreground">
                        {t(
                            'Choose investment goods only when they meet the VAT definition, not merely because they are expensive. Mixed invoices and special regimes require separate treatment.',
                        )}
                    </p>
                    <label className="flex items-start gap-2 text-sm">
                        <input
                            className="mt-1"
                            type="checkbox"
                            checked={form.data.confirmed}
                            onChange={(event) =>
                                form.setData('confirmed', event.target.checked)
                            }
                        />
                        <span>
                            {t(
                                'I confirm a domestic VAT transaction without cash-accounting treatment, one invoice class, documentary deductible VAT and deduction in the invoice period. Otherwise leave it pending.',
                            )}
                        </span>
                    </label>
                    <label className="grid gap-2">
                        {t('Review reason')}
                        <textarea
                            required
                            maxLength={1000}
                            className="min-h-24 w-full rounded border bg-background p-3"
                            value={form.data.reason}
                            onChange={(event) =>
                                form.setData('reason', event.target.value)
                            }
                        />
                    </label>
                    {Object.entries(form.errors).map(([field, error]) => (
                        <p
                            key={field}
                            role="alert"
                            className="text-sm text-destructive"
                        >
                            {error}
                        </p>
                    ))}
                    <Button
                        disabled={
                            !canReview ||
                            form.processing ||
                            (form.data.operation_class !== 'pending' &&
                                (issues.length > 0 || !form.data.confirmed))
                        }
                        type="submit"
                    >
                        {t('Save fiscal review')}
                    </Button>
                </form>
                <section className="grid gap-3">
                    <h2 className="font-semibold">
                        {t('Fiscal review history')}
                    </h2>
                    {history.map((item) => (
                        <article
                            className="rounded-lg border p-3 text-sm"
                            key={item.id}
                        >
                            <p className="font-semibold">
                                {t(classes[item.operation_class])}
                            </p>
                            <p>
                                {item.author ?? '—'} ·{' '}
                                {new Date(item.date).toLocaleString(locale)}
                            </p>
                            <p className="mt-2 whitespace-pre-wrap">
                                {item.reason}
                            </p>
                        </article>
                    ))}
                </section>
            </main>
        </>
    );
}

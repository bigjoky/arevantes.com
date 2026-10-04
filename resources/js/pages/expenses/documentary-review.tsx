import { Form, Head, Link } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useLocale } from '@/hooks/use-locale';
import { translate } from '@/i18n';

type ReviewRow = {
    id: number;
    number: string;
    issuer: string | null;
    category: string | null;
    expense_date: string;
    amount: string;
    status: string;
    eligible: boolean;
    reasons: string[];
    can_adopt_evidence: boolean;
    revision: string;
    comparison: {
        subtotal: string;
        calculated_tax: string;
        documentary_tax: string;
        adjustment: string;
        total: string;
        paid: string;
        outstanding: string;
    } | null;
    edit_url: string;
};

type ReviewPage = {
    data: ReviewRow[];
    links: { url: string | null; label: string; active: boolean }[];
    total: number;
};

export default function DocumentaryReview({
    review,
    filter,
}: {
    review: ReviewPage;
    filter: 'all' | 'with_payments' | 'without_payments';
}) {
    const { locale } = useLocale();
    const t = (key: string) => translate(key, locale);

    return (
        <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <Head title={t('Historical documentary review')} />
            <header className="flex flex-wrap items-start justify-between gap-3">
                <div className="max-w-3xl">
                    <h1 className="text-xl font-semibold">
                        {t('Historical documentary review')}
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        {t(
                            'Review compatible expenses one by one. Saving still requires a fresh preview, a reason and all accounting protections.',
                        )}
                    </p>
                </div>
                <Button asChild variant="outline">
                    <Link href="/expenses">{t('Back to expenses')}</Link>
                </Button>
            </header>
            <p className="text-sm">
                {t('Historical expenses awaiting review')}: {review.total}
            </p>
            <nav
                className="flex flex-wrap gap-2"
                aria-label={t('Historical expense filters')}
            >
                {(
                    [
                        ['all', 'All'],
                        ['with_payments', 'With registered payments'],
                        ['without_payments', 'Without registered payments'],
                    ] as const
                ).map(([value, label]) => (
                    <Button
                        key={value}
                        asChild
                        size="sm"
                        variant={filter === value ? 'default' : 'outline'}
                    >
                        <Link
                            href={`/expenses/documentary-review?filter=${value}`}
                            preserveState
                        >
                            {t(label)}
                        </Link>
                    </Button>
                ))}
            </nav>
            <div className="grid gap-3">
                {review.data.map((expense) => (
                    <article
                        key={expense.id}
                        className="grid gap-3 rounded-lg border p-4 md:grid-cols-[1fr_auto]"
                    >
                        <div className="grid gap-2">
                            <div className="flex flex-wrap items-center gap-2">
                                <Link
                                    href={`/expenses/${expense.id}`}
                                    className="font-medium underline underline-offset-2"
                                >
                                    {expense.number}
                                </Link>
                                <Badge variant="outline">
                                    {expense.eligible
                                        ? t('Ready for review')
                                        : t('Protected')}
                                </Badge>
                            </div>
                            <p className="text-sm">
                                {expense.issuer || '—'} ·{' '}
                                {expense.category || '—'}
                                {' · '}
                                {expense.expense_date} · {expense.amount} EUR
                            </p>
                            {expense.reasons.length > 0 ? (
                                <ul className="list-disc pl-5 text-sm text-muted-foreground">
                                    {expense.reasons.map((reason) => (
                                        <li key={reason}>{t(reason)}</li>
                                    ))}
                                </ul>
                            ) : (
                                <p className="text-sm text-muted-foreground">
                                    {t(
                                        'The document can be reviewed without changing it automatically.',
                                    )}
                                </p>
                            )}
                            {expense.comparison ? (
                                <details className="rounded-md bg-muted/40 p-3 text-sm">
                                    <summary className="cursor-pointer font-medium">
                                        {t('View read-only comparison')}
                                    </summary>
                                    <dl className="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                                        <Comparison
                                            label={t('Taxable base')}
                                            value={expense.comparison.subtotal}
                                        />
                                        <Comparison
                                            label={t('Calculated VAT')}
                                            value={
                                                expense.comparison
                                                    .calculated_tax
                                            }
                                        />
                                        <Comparison
                                            label={t('Documentary VAT')}
                                            value={
                                                expense.comparison
                                                    .documentary_tax
                                            }
                                        />
                                        <Comparison
                                            label={t('Rounding adjustment')}
                                            value={
                                                expense.comparison.adjustment
                                            }
                                        />
                                        <Comparison
                                            label={t('Total')}
                                            value={expense.comparison.total}
                                        />
                                        <Comparison
                                            label={t('Paid')}
                                            value={expense.comparison.paid}
                                        />
                                        <Comparison
                                            label={t('Outstanding')}
                                            value={
                                                expense.comparison.outstanding
                                            }
                                        />
                                    </dl>
                                    {!expense.eligible ? (
                                        <p className="mt-3 text-muted-foreground">
                                            {t(
                                                'This comparison does not authorize changes to the protected expense.',
                                            )}
                                        </p>
                                    ) : null}
                                </details>
                            ) : (
                                <p className="text-sm text-muted-foreground">
                                    {t(
                                        'A documentary comparison is not available for this fiscal configuration.',
                                    )}
                                </p>
                            )}
                            {!expense.eligible && expense.can_adopt_evidence ? (
                                <Form
                                    action={`/expenses/${expense.id}/documentary-evidence`}
                                    method="post"
                                    className="grid gap-2 rounded-md border p-3"
                                >
                                    {({ errors, processing }) => (
                                        <>
                                            <input
                                                type="hidden"
                                                name="revision"
                                                value={expense.revision}
                                            />
                                            <label className="grid gap-1 text-sm">
                                                {t('Evidence review reason')}
                                                <Input
                                                    name="documentary_evidence_reason"
                                                    required
                                                    minLength={5}
                                                    maxLength={2000}
                                                    defaultValue={t(
                                                        'Historical documentary evidence review',
                                                    )}
                                                />
                                            </label>
                                            {errors.documentary_evidence_reason ? (
                                                <p className="text-sm text-destructive">
                                                    {
                                                        errors.documentary_evidence_reason
                                                    }
                                                </p>
                                            ) : null}
                                            <p className="text-sm text-muted-foreground">
                                                {t(
                                                    'This action only attaches calculation evidence. It does not change totals, payments, tax lines or accounting entries.',
                                                )}
                                            </p>
                                            <Button
                                                type="submit"
                                                variant="outline"
                                                disabled={processing}
                                                className="w-fit"
                                            >
                                                {t('Attach matching evidence')}
                                            </Button>
                                        </>
                                    )}
                                </Form>
                            ) : null}
                        </div>
                        {expense.eligible ? (
                            <Button asChild className="self-center">
                                <Link href={expense.edit_url}>
                                    {t('Review calculation')}
                                </Link>
                            </Button>
                        ) : null}
                    </article>
                ))}
            </div>
            {review.data.length === 0 ? (
                <p className="rounded-lg border p-6 text-sm text-muted-foreground">
                    {t('There are no historical expenses awaiting review.')}
                </p>
            ) : null}
            <nav className="flex flex-wrap gap-2" aria-label={t('Pagination')}>
                {review.links.map((link) =>
                    link.url ? (
                        <Button
                            key={link.label}
                            asChild
                            size="sm"
                            variant={link.active ? 'default' : 'outline'}
                        >
                            <Link href={link.url} preserveScroll>
                                {link.label.replace(/&[^;]+;/g, '').trim()}
                            </Link>
                        </Button>
                    ) : null,
                )}
            </nav>
        </div>
    );
}

function Comparison({ label, value }: { label: string; value: string }) {
    return (
        <div>
            <dt className="text-muted-foreground">{label}</dt>
            <dd className="font-medium">{value} EUR</dd>
        </div>
    );
}

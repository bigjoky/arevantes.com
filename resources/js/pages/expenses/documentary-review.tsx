import { Head, Link } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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
    edit_url: string;
};

type ReviewPage = {
    data: ReviewRow[];
    links: { url: string | null; label: string; active: boolean }[];
    total: number;
};

export default function DocumentaryReview({ review }: { review: ReviewPage }) {
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

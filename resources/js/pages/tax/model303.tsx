import { Head, Link, router } from '@inertiajs/react';
import { purchaseIssueLabels } from '@/components/tax/purchase-issue-labels';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useLocale } from '@/hooks/use-locale';
import { translate } from '@/i18n';

type Evidence = {
    source_type: string;
    source_id: number;
    number: string;
    date: string;
    party: string;
    base: number;
    vat: number;
    deductible_vat?: number | null;
    fiscal_issues?: string[];
    registered_on?: string | null;
    deduction_year?: number | null;
    deduction_quarter?: number | null;
};
type Group = {
    base_box: string;
    rate_box: string | null;
    vat_box: string;
    rate: number | null;
    base: number | null;
    vat: number | null;
    documents: Evidence[];
};
type Preview = {
    period: { year: number; quarter: number };
    version: string;
    source: string;
    supported: boolean;
    ready: boolean;
    groups: Group[];
    pendingIssued: Evidence[];
    pendingReceived: Evidence[];
    deferredReceived: Evidence[];
    deductions: {
        base_box: string;
        vat_box: string;
        operation_class: string;
        base: number | null;
        vat: number | null;
        documents: Evidence[];
    }[];
};

const deductionBoxes = [
    '32 / 33',
    '34 / 35',
    '36 / 37',
    '38 / 39',
    '40 / 41',
    '42',
    '43',
    '44',
];

const deductionLabels: Record<string, string> = {
    domestic_current: 'Domestic current goods and services',
    domestic_investment: 'Domestic investment goods',
};

const isExpense = (row: Evidence) => row.source_type === 'expense';
const isPurchase = (row: Evidence) => row.source_type === 'purchase_document';

type Reconciliation = {
    matches: boolean;
    pending_rows: number;
    rows: {
        direction: string;
        candidate_vat: number;
        book_vat: number | null;
        ledger_vat: number | null;
        book_difference: number | null;
        ledger_difference: number | null;
        unexplained_difference: number | null;
    }[];
    differences: {
        source_type: string;
        source_id: number;
        number: string;
        difference: number;
        reason: string;
    }[];
};
const reconciliationReasons: Record<string, string> = {
    added_period: 'Included from another invoice period',
    deferred_period: 'Assigned to another deduction period',
    pending_review: 'Not included: fiscal review pending',
    quota_difference: 'Documentary deductible quota difference',
};
const reconciliationDirections: Record<string, string> = {
    output: 'Output VAT',
    input: 'Deductible VAT',
};

export default function Model303({
    preview,
    census,
    reconciliation,
}: {
    preview: Preview;
    reconciliation: Reconciliation;
    census: {
        current: boolean;
        supported: boolean;
        author: string | null;
        version_id: number | null;
    };
}) {
    const { locale } = useLocale();
    const t = (key: string) => translate(key, locale);
    const amount = (value: number | null) =>
        value === null
            ? t('Pending classification')
            : new Intl.NumberFormat(locale, {
                  style: 'currency',
                  currency: 'EUR',
              }).format(value);
    const navigate = (year: number, quarter: number) =>
        router.get('/tax/models/303', { year, quarter });

    return (
        <>
            <Head title={t('Model 303')} />
            <main className="flex flex-col gap-6 p-4 md:p-6">
                <header className="flex flex-wrap items-center justify-between gap-3">
                    <h1 className="text-xl font-semibold">{t('Model 303')}</h1>
                    <Button variant="outline" asChild>
                        <Link
                            href={`/tax?year=${preview.period.year}&quarter=${preview.period.quarter}`}
                        >
                            {t('VAT')}
                        </Link>
                    </Button>
                </header>
                <div className="flex flex-wrap items-end gap-3">
                    <label className="grid gap-1 text-sm">
                        {t('Year')}
                        <Input
                            type="number"
                            min={2020}
                            max={2100}
                            className="w-28"
                            defaultValue={preview.period.year}
                            key={preview.period.year}
                            onBlur={(event) => {
                                const year = Number(event.target.value);

                                if (
                                    Number.isInteger(year) &&
                                    year >= 2020 &&
                                    year <= 2100 &&
                                    year !== preview.period.year
                                ) {
                                    navigate(year, preview.period.quarter);
                                }
                            }}
                        />
                    </label>
                    <label className="grid gap-1 text-sm">
                        {t('Quarter')}
                        <select
                            className="h-9 rounded-md border bg-background px-3"
                            value={preview.period.quarter}
                            onChange={(event) =>
                                navigate(
                                    preview.period.year,
                                    Number(event.target.value),
                                )
                            }
                        >
                            {[1, 2, 3, 4].map((quarter) => (
                                <option key={quarter} value={quarter}>
                                    {quarter}T
                                </option>
                            ))}
                        </select>
                    </label>
                    <a
                        href={preview.source}
                        target="_blank"
                        rel="noreferrer"
                        className="text-sm underline"
                    >
                        {t('AEAT instructions')}
                    </a>
                </div>
                <section className="rounded-lg border border-amber-500 p-4 text-sm">
                    <h2 className="font-semibold">
                        {t('Read-only fiscal working paper')}
                    </h2>
                    <p>
                        {t(
                            'Candidates only: confirm fiscal territory, tax regime, accrual and rectification treatment. This is not a declaration and cannot be submitted.',
                        )}
                    </p>
                    {!preview.supported ? (
                        <p className="mt-2 font-semibold">
                            {t(
                                'This design only covers quarters 2 to 4 of 2026. Other periods require their own validated rules.',
                            )}
                        </p>
                    ) : null}
                    <p className="mt-2">
                        {t(
                            'Fiscal profile, deduction periods and carried-forward credits still require validation. Pending amounts are not zero.',
                        )}
                    </p>
                    <p className="mt-2 font-mono">{preview.version}</p>
                </section>
                <section className="rounded-lg border p-4 text-sm">
                    <h2 className="font-semibold">{t('303 reconciliation')}</h2>
                    <p className="my-2">
                        {t(
                            'Comparison with invoice-date VAT books and VAT ledger balances. Existing settlements still use the books, not this projection. No entries or invoices are changed.',
                        )}
                    </p>
                    <p className="my-2 font-medium">
                        {reconciliation.matches
                            ? t(
                                  'Amounts match; official filing remains unavailable.',
                              )
                            : t(
                                  'Differences or pending evidence require review. This is not filing readiness.',
                              )}
                    </p>
                    <div className="overflow-x-auto">
                        <table className="w-full text-left">
                            <caption className="sr-only">
                                {t('303 reconciliation')}
                            </caption>
                            <thead>
                                <tr>
                                    {[
                                        'VAT',
                                        '303 candidate',
                                        'VAT books',
                                        'VAT ledger',
                                        'Difference vs books',
                                        'Difference vs ledger',
                                        'Unexplained difference',
                                    ].map((label) => (
                                        <th
                                            key={label}
                                            scope="col"
                                            className="p-2"
                                        >
                                            {t(label)}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {reconciliation.rows.map((row) => (
                                    <tr
                                        key={row.direction}
                                        className="border-t"
                                    >
                                        <th scope="row" className="p-2">
                                            {t(
                                                reconciliationDirections[
                                                    row.direction
                                                ],
                                            )}
                                        </th>
                                        {[
                                            row.candidate_vat,
                                            row.book_vat,
                                            row.ledger_vat,
                                            row.book_difference,
                                            row.ledger_difference,
                                            row.unexplained_difference,
                                        ].map((value, index) => (
                                            <td
                                                key={index}
                                                className="p-2 whitespace-nowrap tabular-nums"
                                            >
                                                {value === null
                                                    ? '—'
                                                    : amount(value)}
                                            </td>
                                        ))}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    {reconciliation.differences.length > 0 ? (
                        <ul className="mt-3 space-y-2">
                            {reconciliation.differences.map((row) => (
                                <li
                                    key={`${row.source_type}:${row.source_id}`}
                                    className="flex flex-wrap gap-2"
                                >
                                    <Link
                                        className="underline"
                                        href={
                                            row.source_type === 'expense'
                                                ? `/expenses/${row.source_id}`
                                                : row.source_type ===
                                                    'purchase_document'
                                                  ? `/purchases/${row.source_id}`
                                                  : `/sales/${row.source_id}`
                                        }
                                    >
                                        {row.number}
                                    </Link>
                                    <span>
                                        {t(reconciliationReasons[row.reason])} ·{' '}
                                        {amount(row.difference)}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    ) : null}
                </section>
                <section className="rounded-lg border p-4 text-sm">
                    <h2 className="font-semibold">
                        {t('Company census profile')}
                    </h2>
                    <p>
                        {census.current
                            ? t('Census profile reviewed and current.')
                            : t(
                                  'Census profile unreviewed or company identity changed.',
                              )}
                    </p>
                    <p>
                        {census.supported
                            ? t(
                                  'Profile within the current quarterly general-regime scope.',
                              )
                            : t(
                                  'Profile not yet within the supported model scope.',
                              )}
                    </p>
                    <p>
                        {census.author ?? '—'} · {census.version_id ?? '—'}
                    </p>
                    <Link
                        className="mt-2 inline-block underline"
                        href={`/company-fiscal/census?year=${preview.period.year}`}
                    >
                        {t('Review census profile')}
                    </Link>
                    <p className="mt-2 text-muted-foreground">
                        {t(
                            'Even with a reviewed profile, this projection is not ready for filing: operation classification, deduction periods and carried-forward credits remain incomplete.',
                        )}
                    </p>
                </section>
                <section className="grid gap-3">
                    <h2 className="font-semibold">
                        {t('Output VAT: candidate boxes')}
                    </h2>
                    {preview.groups.map((group) => (
                        <article
                            key={group.base_box}
                            className="rounded-lg border p-4"
                        >
                            <div className="grid gap-3 sm:grid-cols-3">
                                <p>
                                    {t('Taxable base')} [{group.base_box}]<br />
                                    <strong>{amount(group.base)}</strong>
                                </p>
                                <p>
                                    {group.rate_box
                                        ? `${t('Rate')} [${group.rate_box}]`
                                        : t(
                                              'Modifications of taxable bases and VAT',
                                          )}
                                    <br />
                                    <strong>
                                        {group.rate === null
                                            ? '—'
                                            : `${group.rate}%`}
                                    </strong>
                                </p>
                                <p>
                                    {t('VAT')} [{group.vat_box}]<br />
                                    <strong>{amount(group.vat)}</strong>
                                </p>
                            </div>
                            <EvidenceList rows={group.documents} />
                        </article>
                    ))}
                </section>
                <section className="grid gap-3 rounded-lg border p-4">
                    <h2 className="font-semibold">
                        {t('Input VAT: classification required')}
                    </h2>
                    <p className="text-sm text-muted-foreground">
                        {t(
                            'Separate domestic purchases, imports and intra-community acquisitions; current goods, investment goods and deduction adjustments.',
                        )}
                    </p>
                    {preview.deductions.map((group) => (
                        <article
                            className="rounded border p-3"
                            key={group.base_box}
                        >
                            <h3 className="font-medium">
                                {t(deductionLabels[group.operation_class])}
                            </h3>
                            <div className="mt-2 grid gap-2 sm:grid-cols-2">
                                <p>
                                    {t('Taxable base')} [{group.base_box}]:{' '}
                                    <strong>{amount(group.base)}</strong>
                                </p>
                                <p>
                                    {t('Deductible VAT')} [{group.vat_box}]:{' '}
                                    <strong>{amount(group.vat)}</strong>
                                </p>
                            </div>
                            <EvidenceList rows={group.documents} />
                        </article>
                    ))}
                    <div className="flex flex-wrap gap-2">
                        {deductionBoxes.map((box) => (
                            <span
                                className="rounded border p-2 text-sm"
                                key={box}
                            >
                                [{box}] {t('Pending classification')}
                            </span>
                        ))}
                    </div>
                    <EvidenceList rows={preview.pendingReceived} />
                    <h3 className="mt-3 font-medium">
                        {t(
                            'Received invoices assigned to another deduction period',
                        )}
                    </h3>
                    <p className="text-sm text-muted-foreground">
                        {t(
                            'These reviewed received invoices do not contribute to this period. Their invoice dates remain unchanged in the accounting books.',
                        )}
                    </p>
                    <EvidenceList rows={preview.deferredReceived} />
                </section>
                <section className="rounded-lg border p-4">
                    <h2 className="font-semibold">
                        {t('Output transactions awaiting classification')}
                    </h2>
                    <p className="text-sm text-muted-foreground">
                        {t(
                            'Zero-rate, exempt, foreign or unidentified transactions are not automatically assigned to domestic boxes.',
                        )}
                    </p>
                    <EvidenceList rows={preview.pendingIssued} />
                </section>
                <section className="rounded-lg border p-4">
                    <h2 className="font-semibold">
                        {t('Final result and carried-forward credits')}
                    </h2>
                    <p className="text-sm">
                        {t(
                            'Boxes 110, 78, 87 and 71 remain pending. The accounting settlement does not determine the official return result.',
                        )}
                    </p>
                </section>
            </main>
        </>
    );
}

function EvidenceList({ rows }: { rows: Evidence[] }) {
    const { locale } = useLocale();
    const t = (key: string) => translate(key, locale);

    return (
        <details className="mt-3 text-sm">
            <summary className="cursor-pointer">
                {t('Source evidence')} ({rows.length})
            </summary>
            <div className="mt-2 max-h-80 overflow-auto">
                <table className="w-full text-left">
                    <caption className="sr-only">
                        {t('Source evidence')}
                    </caption>
                    <thead>
                        <tr>
                            <th className="p-2">{t('Document')}</th>
                            <th className="p-2">{t('Taxable base')}</th>
                            <th className="p-2">{t('VAT')}</th>
                            <th className="p-2">{t('Deductible VAT')}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.map((row, index) => (
                            <tr
                                className="border-t"
                                key={`${row.source_type}-${row.source_id}-${index}`}
                            >
                                <td className="p-2">
                                    <span className="block">
                                        {row.number} · {row.date}
                                    </span>
                                    <span>{row.party}</span>
                                    {row.deduction_year &&
                                    row.deduction_quarter ? (
                                        <p className="mt-1 text-muted-foreground">
                                            {t('Deduction period')}:{' '}
                                            {`${row.deduction_year} / ${row.deduction_quarter}T`}{' '}
                                            · {t('Fiscal registration date')}:{' '}
                                            {row.registered_on ?? '—'}
                                        </p>
                                    ) : null}
                                    {isExpense(row) ? (
                                        <Link
                                            className="mt-1 block underline"
                                            href={`/tax/expenses/${row.source_id}/classification`}
                                        >
                                            {t('Review fiscal classification')}
                                        </Link>
                                    ) : null}
                                    {isPurchase(row) ? (
                                        <>
                                            <Link
                                                className="mt-1 block underline"
                                                href={`/purchases/${row.source_id}`}
                                            >
                                                {t('View purchase document')}
                                            </Link>
                                            <Link
                                                className="mt-1 block underline"
                                                href={`/tax/purchases/${row.source_id}/classification`}
                                            >
                                                {t(
                                                    'Review fiscal classification',
                                                )}
                                            </Link>
                                            <ul className="mt-2 list-disc space-y-1 pl-4 text-muted-foreground">
                                                {(row.fiscal_issues ?? []).map(
                                                    (issue) => (
                                                        <li key={issue}>
                                                            {t(
                                                                purchaseIssueLabels[
                                                                    issue
                                                                ] ??
                                                                    'Pending classification',
                                                            )}
                                                        </li>
                                                    ),
                                                )}
                                            </ul>
                                        </>
                                    ) : null}
                                </td>
                                <td className="p-2 tabular-nums">
                                    {new Intl.NumberFormat(locale, {
                                        minimumFractionDigits: 2,
                                        maximumFractionDigits: 2,
                                    }).format(row.base)}
                                </td>
                                <td className="p-2 tabular-nums">
                                    {new Intl.NumberFormat(locale, {
                                        minimumFractionDigits: 2,
                                        maximumFractionDigits: 2,
                                    }).format(row.vat)}
                                </td>
                                <td className="p-2 tabular-nums">
                                    {row.deductible_vat === null ||
                                    row.deductible_vat === undefined
                                        ? '—'
                                        : new Intl.NumberFormat(locale, {
                                              minimumFractionDigits: 2,
                                              maximumFractionDigits: 2,
                                          }).format(row.deductible_vat)}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </details>
    );
}

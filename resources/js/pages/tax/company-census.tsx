import { Head, Link, router, useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useLocale } from '@/hooks/use-locale';
import { translate } from '@/i18n';

const fields = {
    model303: {
        label: 'Model 303 obligation',
        options: ['unknown', 'yes', 'no'],
    },
    vat_regime: {
        label: 'VAT regime',
        options: ['unknown', 'general', 'simplified', 'mixed'],
    },
    periodicity: {
        label: 'VAT filing frequency',
        options: ['unknown', 'quarterly', 'monthly'],
    },
    territory: {
        label: 'VAT taxing administration',
        options: ['unknown', 'common', 'foral', 'mixed'],
    },
    cash_accounting: {
        label: 'Cash accounting scheme',
        options: ['unknown', 'yes', 'no'],
    },
    cash_recipient: {
        label: 'Recipient of cash-accounting transactions',
        options: ['unknown', 'yes', 'no'],
    },
    prorrata: {
        label: 'VAT pro rata deduction',
        options: ['unknown', 'none', 'general', 'special'],
    },
    redeme: {
        label: 'Monthly VAT refund register (REDEME)',
        options: ['unknown', 'yes', 'no'],
    },
    sii: {
        label: 'Immediate Supply of Information (SII)',
        options: ['unknown', 'yes', 'no'],
    },
};
const labels: Record<string, string> = {
    unknown: 'Pending confirmation',
    yes: 'Yes',
    no: 'No',
    general: 'General',
    simplified: 'Simplified',
    mixed: 'Mixed',
    quarterly: 'Quarterly',
    monthly: 'Monthly',
    common: 'State tax administration',
    foral: 'Foral tax administration',
    none: 'None',
    special: 'Special',
};
type Settings = Record<keyof typeof fields, string>;
type Check = {
    current: boolean;
    supported: boolean;
    status: string | null;
    settings: Settings | null;
    author: string | null;
    source_reference: string | null;
};
type Version = {
    id: number;
    status: string;
    settings: Settings;
    company_snapshot: { legal_name: string; tax_id: string };
    author_name: string;
    source_reference: string | null;
    reason: string;
    created_at: string;
};

const versionStatusLabel = (status: string) =>
    status === 'reviewed' ? 'Reviewed' : 'Draft';

export default function CompanyCensus({
    year,
    company,
    revision,
    check,
    history,
    protection,
}: {
    year: number;
    company: { legal_name: string; tax_id: string; address: string };
    revision: string;
    check: Check;
    history: Version[];
    protection: string[];
}) {
    const { locale } = useLocale();
    const t = (key: string) => translate(key, locale);
    const defaults = Object.fromEntries(
        Object.keys(fields).map((field) => [field, 'unknown']),
    ) as Settings;
    const form = useForm({
        year,
        revision,
        settings: check.settings ?? defaults,
        reason: '',
        source_reference: check.source_reference ?? '',
        confirmed: false,
        action: 'draft',
    });

    return (
        <>
            <Head title={t('Company census profile')} />
            <main className="mx-auto flex w-full max-w-5xl flex-col gap-5 p-4 md:p-6">
                <header className="flex flex-wrap items-center justify-between gap-3">
                    <h1 className="text-xl font-semibold">
                        {t('Company census profile')}
                    </h1>
                    <Button variant="outline" asChild>
                        <Link href={`/tax/models/303?year=${year}`}>
                            {t('Model 303')}
                        </Link>
                    </Button>
                </header>
                <label className="grid gap-1 text-sm">
                    {t('Year')}
                    <Input
                        type="number"
                        className="w-28"
                        min={2020}
                        max={2100}
                        defaultValue={year}
                        key={year}
                        onBlur={(event) => {
                            const value = Number(event.target.value);

                            if (
                                Number.isInteger(value) &&
                                value >= 2020 &&
                                value <= 2100 &&
                                value !== year
                            ) {
                                router.get('/company-fiscal/census', {
                                    year: value,
                                });
                            }
                        }}
                    />
                </label>
                <section className="rounded-lg border p-4">
                    <h2 className="font-semibold">{company.legal_name}</h2>
                    <p>
                        {company.tax_id} · {company.address}
                    </p>
                    {protection.map((issue) => (
                        <p key={issue} className="mt-2 font-medium">
                            {t(issue)}
                        </p>
                    ))}
                    <Link className="text-sm underline" href="/company-fiscal">
                        {t('Company details')}
                    </Link>
                </section>
                <section className="rounded-lg border border-amber-500 p-4 text-sm">
                    <p>
                        {t(
                            'This is a reviewed company declaration, not verification against AEAT records. Confirm the settings with census documentation or your tax adviser.',
                        )}
                    </p>
                    <p className="mt-2">
                        {t(
                            'This annual version assumes unchanged settings for the whole year. Mid-year changes require period-specific handling and must remain pending.',
                        )}
                    </p>
                    <p className="mt-2 font-medium">
                        {check.current
                            ? t('Census profile reviewed and current.')
                            : t(
                                  'Census profile unreviewed or company identity changed.',
                              )}
                    </p>
                    <p>
                        {check.supported
                            ? t(
                                  'Profile within the current quarterly general-regime scope.',
                              )
                            : t(
                                  'Profile not yet within the supported model scope.',
                              )}
                    </p>
                </section>
                <form
                    className="grid gap-4 rounded-lg border p-4"
                    onSubmit={(event) => {
                        event.preventDefault();
                        const button = (event.nativeEvent as SubmitEvent)
                            .submitter as HTMLButtonElement | null;
                        form.transform((data) => ({
                            ...data,
                            year,
                            revision,
                            action:
                                button?.value === 'review' ? 'review' : 'draft',
                        }));
                        form.post('/company-fiscal/census', {
                            onSuccess: () => form.reset('reason', 'confirmed'),
                        });
                    }}
                >
                    <div className="grid gap-4 sm:grid-cols-2">
                        {Object.entries(fields).map(([field, config]) => (
                            <label className="grid gap-1 text-sm" key={field}>
                                {t(config.label)}
                                <select
                                    className="h-10 w-full rounded border bg-background px-3"
                                    value={
                                        form.data.settings[
                                            field as keyof Settings
                                        ]
                                    }
                                    onChange={(event) =>
                                        form.setData('settings', {
                                            ...form.data.settings,
                                            [field]: event.target.value,
                                        })
                                    }
                                >
                                    {config.options.map((option) => (
                                        <option key={option} value={option}>
                                            {t(labels[option])}
                                        </option>
                                    ))}
                                </select>
                            </label>
                        ))}
                    </div>
                    <label className="grid gap-1 text-sm">
                        {t('Census evidence reference')}
                        <Input
                            maxLength={1000}
                            value={form.data.source_reference}
                            onChange={(event) =>
                                form.setData(
                                    'source_reference',
                                    event.target.value,
                                )
                            }
                        />
                    </label>
                    <label className="grid gap-1 text-sm">
                        {t('Review reason')}
                        <textarea
                            required
                            maxLength={1000}
                            className="min-h-24 rounded border bg-background p-3"
                            value={form.data.reason}
                            onChange={(event) =>
                                form.setData('reason', event.target.value)
                            }
                        />
                    </label>
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
                                'I have checked these selections against the cited census evidence and confirm they apply throughout this fiscal year.',
                            )}
                        </span>
                    </label>
                    {Object.entries(form.errors).map(([key, error]) => (
                        <p
                            className="text-sm text-destructive"
                            role="alert"
                            key={key}
                        >
                            {error}
                        </p>
                    ))}
                    <div className="flex flex-wrap gap-2">
                        <Button
                            type="submit"
                            name="action"
                            value="draft"
                            variant="outline"
                            disabled={form.processing || protection.length > 0}
                        >
                            {t('Save census draft')}
                        </Button>
                        <Button
                            type="submit"
                            name="action"
                            value="review"
                            disabled={
                                form.processing ||
                                !form.data.confirmed ||
                                protection.length > 0
                            }
                        >
                            {t('Save reviewed census version')}
                        </Button>
                    </div>
                </form>
                <section className="grid gap-3">
                    <h2 className="font-semibold">
                        {t('Census version history')}
                    </h2>
                    {history.map((version) => (
                        <article
                            className="rounded-lg border p-4 text-sm"
                            key={version.id}
                        >
                            <p className="font-semibold">
                                #{version.id} ·{' '}
                                {t(versionStatusLabel(version.status))}
                            </p>
                            <p>
                                {version.author_name} ·{' '}
                                {new Date(version.created_at).toLocaleString(
                                    locale,
                                )}
                            </p>
                            <p>
                                {version.company_snapshot.legal_name} ·{' '}
                                {version.company_snapshot.tax_id}
                            </p>
                            <p className="mt-2 whitespace-pre-wrap">
                                {version.reason}
                            </p>
                            <p className="whitespace-pre-wrap">
                                {version.source_reference}
                            </p>
                            <details className="mt-2">
                                <summary className="cursor-pointer">
                                    {t('Census selections')}
                                </summary>
                                <dl className="mt-2 grid gap-2 sm:grid-cols-2">
                                    {Object.entries(fields).map(
                                        ([field, config]) => (
                                            <div key={field}>
                                                <dt>{t(config.label)}</dt>
                                                <dd className="font-medium">
                                                    {t(
                                                        labels[
                                                            version.settings[
                                                                field as keyof Settings
                                                            ]
                                                        ],
                                                    )}
                                                </dd>
                                            </div>
                                        ),
                                    )}
                                </dl>
                            </details>
                        </article>
                    ))}
                </section>
            </main>
        </>
    );
}

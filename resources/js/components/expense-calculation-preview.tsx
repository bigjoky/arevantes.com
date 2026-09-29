import { useEffect, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useLocale } from '@/hooks/use-locale';
import { translate } from '@/i18n';

type Preview = {
    preview_token: string;
    result: {
        totals: {
            subtotal: string;
            tax_amount: string;
            deductible_amount: string;
            total: string;
        };
        tax_groups: {
            group_key: string;
            tax_rate: string;
            taxable_base: string;
            calculated_tax: string;
            tax_amount: string;
            tax_adjustment: string;
        }[];
    };
};

export function ExpenseCalculationPreview({
    available,
    required = false,
    lineIds,
    initialReason = '',
}: {
    available: boolean;
    required?: boolean;
    lineIds: string[];
    initialReason?: string;
}) {
    const { locale } = useLocale();
    const t = (key: string) => translate(key, locale);
    const [enabled, setEnabled] = useState(required);
    const [preview, setPreview] = useState<Preview | null>(null);
    const [error, setError] = useState('');
    const [busy, setBusy] = useState(false);
    const root = useRef<HTMLDivElement>(null);
    const generation = useRef(0);
    const controller = useRef<AbortController | null>(null);
    const lineKey = JSON.stringify(lineIds);

    useEffect(() => {
        const requestGeneration = generation;
        const form = root.current?.closest('form');
        const invalidate = () => {
            requestGeneration.current++;
            controller.current?.abort();
            setPreview(null);
            setBusy(false);
            setError('');
        };
        invalidate();
        form?.addEventListener('input', invalidate);
        form?.addEventListener('change', invalidate);

        return () => {
            requestGeneration.current++;
            controller.current?.abort();
            form?.removeEventListener('input', invalidate);
            form?.removeEventListener('change', invalidate);
        };
    }, [available, enabled, lineKey]);

    if (!available && !required) {
        return null;
    }

    if (!available) {
        return (
            <p role="alert">
                {t(
                    'Grouped expense calculation is not enabled for this operation.',
                )}
            </p>
        );
    }

    const calculate = async () => {
        const form = root.current?.closest('form');

        if (!form) {
            return;
        }

        const current = ++generation.current;
        controller.current?.abort();
        controller.current = new AbortController();
        setBusy(true);
        setError('');
        setPreview(null);
        const payload = new FormData();

        for (const [key, value] of new FormData(form)) {
            if (
                typeof value === 'string' &&
                (key.startsWith('tax_lines[') ||
                    [
                        'currency_code',
                        'tax_regime',
                        'withholding_rate',
                        'withholding_amount',
                        'calculation_reason',
                    ].includes(key))
            ) {
                payload.append(key, value);
            }
        }

        try {
            const response = await fetch('/expenses/calculation-preview', {
                method: 'POST',
                body: payload,
                signal: controller.current.signal,
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN':
                        document.querySelector<HTMLMetaElement>(
                            'meta[name="csrf-token"]',
                        )?.content ?? '',
                },
            });
            const data = await response.json();

            if (current !== generation.current) {
                return;
            }

            if (!response.ok) {
                setError(
                    Object.values(data.errors ?? {})
                        .flat()
                        .join(' ') ||
                        t(
                            'Unable to preview the calculation. Please try again.',
                        ),
                );

                return;
            }

            setPreview(data as Preview);
        } catch {
            if (current === generation.current) {
                setError(
                    t('Unable to preview the calculation. Please try again.'),
                );
            }
        } finally {
            if (current === generation.current) {
                setBusy(false);
            }
        }
    };

    return (
        <div ref={root} className="grid gap-3 rounded-md border p-4">
            <label className="flex items-center gap-2 font-medium">
                <input
                    type="checkbox"
                    checked={enabled}
                    disabled={required}
                    onChange={(event) => setEnabled(event.target.checked)}
                />
                {t('Use grouped documentary calculation')}
            </label>
            {enabled && (
                <>
                    <input
                        type="hidden"
                        name="calculation_mode"
                        value="grouped_v1"
                    />
                    <input
                        type="hidden"
                        name="calculation_preview_token"
                        value={preview?.preview_token ?? ''}
                    />
                    {lineIds.map((id, index) => (
                        <input
                            key={id}
                            type="hidden"
                            name={`tax_lines[${index}][calculation_id]`}
                            value={id}
                        />
                    ))}
                    <p className="text-sm text-muted-foreground">
                        {t(
                            'EUR only, without withholding or surcharges. Enter documentary quotas; the server compares them by VAT group. Preview again after changing the form.',
                        )}
                    </p>
                    <label className="grid gap-2 text-sm">
                        {t('Rounding adjustment reason')}
                        <Input
                            name="calculation_reason"
                            defaultValue={initialReason}
                            minLength={5}
                            maxLength={2000}
                        />
                    </label>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={calculate}
                        disabled={busy}
                        className="w-fit"
                    >
                        {t(
                            busy
                                ? 'Calculating…'
                                : 'Preview grouped calculation',
                        )}
                    </Button>
                    {error && (
                        <p role="alert" className="text-sm text-destructive">
                            {error}
                        </p>
                    )}
                    {preview ? (
                        <div aria-live="polite" className="grid gap-3">
                            {preview.result.tax_groups.map((group) => (
                                <div
                                    key={group.group_key}
                                    className="grid gap-1 border-t pt-2 text-sm sm:grid-cols-2"
                                >
                                    <span>
                                        {t('Tax rate')}: {group.tax_rate}%
                                    </span>
                                    <span>
                                        {t('Taxable base')}:{' '}
                                        {group.taxable_base} EUR
                                    </span>
                                    <span>
                                        {t('Calculated VAT')}:{' '}
                                        {group.calculated_tax} EUR
                                    </span>
                                    <span>
                                        {t('Documentary VAT')}:{' '}
                                        {group.tax_amount} EUR
                                    </span>
                                    <span>
                                        {t('Rounding adjustment')}:{' '}
                                        {group.tax_adjustment} EUR
                                    </span>
                                </div>
                            ))}
                            <p className="font-medium">
                                {t('Total')}: {preview.result.totals.total} EUR
                                · {t('Deductible VAT')}:{' '}
                                {preview.result.totals.deductible_amount} EUR
                            </p>
                            <p className="text-sm">
                                {t(
                                    'Calculation reviewed. Saving will validate these values again.',
                                )}
                            </p>
                        </div>
                    ) : (
                        <p className="text-sm text-muted-foreground">
                            {t(
                                'Preview the current grouped calculation before saving.',
                            )}
                        </p>
                    )}
                </>
            )}
        </div>
    );
}

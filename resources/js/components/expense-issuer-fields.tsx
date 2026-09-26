import { useEffect, useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useLocale } from '@/hooks/use-locale';
import { translate } from '@/i18n';

type Candidate = {
    legal_name: string;
    trade_name: string | null;
    source: 'expense' | 'supplier';
    reference: string | null;
};

export function ExpenseIssuerFields({
    errors,
}: {
    errors: Record<string, string>;
}) {
    const { locale } = useLocale();
    const [fields, setFields] = useState({
        taxId: '',
        legalName: '',
        tradeName: '',
        autoLegal: false,
        autoTrade: false,
    });
    const { taxId, legalName, tradeName } = fields;
    const [candidates, setCandidates] = useState<Candidate[]>([]);
    const [status, setStatus] = useState<
        'idle' | 'loading' | 'ready' | 'error'
    >('idle');

    useEffect(() => {
        const value = taxId.replace(/[ .-]/g, '').toUpperCase();

        if (value.length < 3) {
            return;
        }

        const controller = new AbortController();
        const timeout = window.setTimeout(async () => {
            setStatus('loading');

            try {
                const response = await fetch(
                    `/expenses/issuer-lookup?tax_id=${encodeURIComponent(value)}`,
                    {
                        headers: { Accept: 'application/json' },
                        signal: controller.signal,
                    },
                );

                if (!response.ok) {
                    throw new Error('Issuer lookup failed');
                }

                const result: { candidates: Candidate[] } =
                    await response.json();

                if (controller.signal.aborted) {
                    return;
                }

                setCandidates(result.candidates);
                setStatus('ready');

                if (result.candidates.length === 1) {
                    // Never overwrite anything the user has already entered.
                    const candidate = result.candidates[0];
                    setFields((current) => {
                        if (
                            current.taxId
                                .replace(/[ .-]/g, '')
                                .toUpperCase() !== value ||
                            current.legalName.trim() ||
                            current.tradeName.trim()
                        ) {
                            return current;
                        }

                        return {
                            ...current,
                            legalName: candidate.legal_name,
                            tradeName: candidate.trade_name || '',
                            autoLegal: true,
                            autoTrade: true,
                        };
                    });
                }
            } catch {
                if (!controller.signal.aborted) {
                    setStatus('error');
                }
            }
        }, 400);

        return () => {
            window.clearTimeout(timeout);
            controller.abort();
        };
    }, [taxId]);

    return (
        <div className="grid gap-4 sm:col-span-2 sm:grid-cols-2">
            <div className="grid gap-2">
                <Label htmlFor="expense-issuer-tax-id">
                    {translate('Issuer tax ID / VAT number', locale)}
                </Label>
                <Input
                    id="expense-issuer-tax-id"
                    name="issuer_tax_id"
                    value={taxId}
                    maxLength={32}
                    onChange={(event) => {
                        const value = event.target.value;
                        setFields((current) => ({
                            taxId: value,
                            legalName: current.autoLegal
                                ? ''
                                : current.legalName,
                            tradeName: current.autoTrade
                                ? ''
                                : current.tradeName,
                            autoLegal: false,
                            autoTrade: false,
                        }));
                        setCandidates([]);
                        setStatus('idle');
                    }}
                />
                <InputError message={errors.issuer_tax_id} />
            </div>
            <div className="grid gap-2">
                <Label htmlFor="expense-issuer-name">
                    {translate('Issuer legal name', locale)}
                </Label>
                <Input
                    id="expense-issuer-name"
                    name="issuer_legal_name"
                    value={legalName}
                    maxLength={255}
                    onChange={(event) =>
                        setFields({
                            ...fields,
                            legalName: event.target.value,
                            autoLegal: false,
                        })
                    }
                />
                <InputError message={errors.issuer_legal_name} />
            </div>
            <div className="grid gap-2">
                <Label htmlFor="expense-issuer-trade-name">
                    {translate('Trade name (optional)', locale)}
                </Label>
                <Input
                    id="expense-issuer-trade-name"
                    name="merchant_name"
                    value={tradeName}
                    maxLength={255}
                    onChange={(event) =>
                        setFields({
                            ...fields,
                            tradeName: event.target.value,
                            autoTrade: false,
                        })
                    }
                />
                <InputError message={errors.merchant_name} />
            </div>
            <div
                className="grid gap-2 text-sm sm:col-span-2"
                aria-live="polite"
            >
                <p className="text-muted-foreground">
                    {translate(
                        'Enter a tax ID to reuse names from previous expenses and suppliers. Review the data before saving.',
                        locale,
                    )}
                </p>
                {status === 'loading' ? (
                    <p>{translate('Looking up issuer…', locale)}</p>
                ) : null}
                {status === 'error' ? (
                    <p>
                        {translate(
                            'Issuer lookup is unavailable. You can enter the data manually.',
                            locale,
                        )}
                    </p>
                ) : null}
                {status === 'ready' && candidates.length === 0 ? (
                    <p>
                        {translate(
                            'No previous issuer found. Enter the names for this expense.',
                            locale,
                        )}
                    </p>
                ) : null}
                {candidates.map((candidate, index) => (
                    <Button
                        key={index}
                        type="button"
                        variant="outline"
                        className="h-auto justify-start text-left whitespace-normal"
                        onClick={() => {
                            setFields({
                                ...fields,
                                legalName: candidate.legal_name,
                                tradeName: candidate.trade_name || '',
                                autoLegal: true,
                                autoTrade: true,
                            });
                        }}
                    >
                        {translate('Use issuer details', locale)}:{' '}
                        {candidate.legal_name}
                        {candidate.trade_name
                            ? ` · ${candidate.trade_name}`
                            : ''}{' '}
                        · {candidate.reference || translate('Supplier', locale)}
                    </Button>
                ))}
            </div>
        </div>
    );
}

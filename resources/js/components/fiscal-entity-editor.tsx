import { useForm } from '@inertiajs/react';
import { FormErrorSummary } from '@/components/forms/form-layout';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useLocale } from '@/hooks/use-locale';
import { translate } from '@/i18n';

export type CountryOption = { code: string; name: string };
type Address = {
    id: string | null;
    label: string;
    kind: 'fiscal' | 'registered' | 'operating';
    address_line_1: string;
    address_line_2: string;
    postal_code: string;
    city: string;
    province: string;
    country: string;
};
export type FiscalEntity = {
    id: number;
    country: string;
    tax_id: string;
    legal_name: string;
    trade_names: string[];
    addresses: Address[];
    notes: string | null;
    is_active: boolean;
};

export function FiscalEntityEditor({
    entity,
    countries,
    revision = '',
}: {
    entity?: FiscalEntity;
    countries: CountryOption[];
    revision?: string;
}) {
    const { locale } = useLocale();
    const form = useForm({
        country: entity?.country ?? 'ES',
        tax_id: entity?.tax_id ?? '',
        legal_name: entity?.legal_name ?? '',
        trade_names: entity?.trade_names ?? [],
        addresses: entity?.addresses ?? [],
        notes: entity?.notes ?? '',
        is_active: entity?.is_active ?? true,
        revision,
        change_note: '',
    });
    const updateAddress = (index: number, changes: Partial<Address>) => {
        form.setData(
            'addresses',
            form.data.addresses.map((address, position) =>
                position === index ? { ...address, ...changes } : address,
            ),
        );
    };

    return (
        <form
            className="grid gap-5"
            onSubmit={(event) => {
                event.preventDefault();
                form.transform((data) => ({
                    ...data,
                    trade_names: data.trade_names
                        .map((name) => name.trim())
                        .filter(Boolean),
                }));

                if (entity) {
                    form.patch(`/fiscal-entities/${entity.id}`, {
                        preserveScroll: true,
                    });
                } else {
                    form.post('/fiscal-entities');
                }
            }}
        >
            <FormErrorSummary errors={form.errors} />
            <p className="text-sm text-muted-foreground">
                {translate(
                    'Changes to this directory do not rewrite existing invoices or accounting.',
                    locale,
                )}
            </p>
            <div className="grid gap-4 sm:grid-cols-2">
                <label className="grid gap-2 text-sm">
                    {translate('Country', locale)}
                    <select
                        className="h-9 rounded-md border bg-background px-3"
                        value={form.data.country}
                        disabled={Boolean(entity)}
                        onChange={(event) =>
                            form.setData('country', event.target.value)
                        }
                    >
                        {countries.map((country) => (
                            <option key={country.code} value={country.code}>
                                {translate(country.name, locale)} (
                                {country.code})
                            </option>
                        ))}
                    </select>
                </label>
                <label className="grid gap-2 text-sm">
                    {translate('Issuer tax ID / VAT number', locale)}
                    <Input
                        value={form.data.tax_id}
                        readOnly={Boolean(entity)}
                        maxLength={32}
                        required
                        onChange={(event) =>
                            form.setData('tax_id', event.target.value)
                        }
                    />
                </label>
                <label className="grid gap-2 text-sm sm:col-span-2">
                    {translate('Issuer legal name', locale)}
                    <Input
                        value={form.data.legal_name}
                        maxLength={255}
                        required
                        onChange={(event) =>
                            form.setData('legal_name', event.target.value)
                        }
                    />
                </label>
                <label className="grid gap-2 text-sm sm:col-span-2">
                    {translate('Trade names, one per line', locale)}
                    <textarea
                        className="min-h-24 rounded-md border bg-background p-3"
                        value={form.data.trade_names.join('\n')}
                        onChange={(event) =>
                            form.setData(
                                'trade_names',
                                event.target.value.split('\n'),
                            )
                        }
                    />
                </label>
            </div>
            <section className="grid gap-4">
                <h2 className="font-semibold">
                    {translate('Addresses', locale)}
                </h2>
                {form.data.addresses.map((address, index) => (
                    <fieldset
                        key={address.id ?? index}
                        className="grid gap-4 rounded-md border p-4 sm:grid-cols-2"
                    >
                        <legend className="px-2 text-sm">
                            {address.label || translate('New address', locale)}
                        </legend>
                        <label className="grid gap-2 text-sm">
                            {translate('Address label', locale)}
                            <Input
                                required
                                maxLength={100}
                                value={address.label}
                                onChange={(event) =>
                                    updateAddress(index, {
                                        label: event.target.value,
                                    })
                                }
                            />
                        </label>
                        <label className="grid gap-2 text-sm">
                            {translate('Address type', locale)}
                            <select
                                className="h-9 rounded-md border bg-background px-3"
                                value={address.kind}
                                onChange={(event) =>
                                    updateAddress(index, {
                                        kind: event.target
                                            .value as Address['kind'],
                                    })
                                }
                            >
                                <option value="fiscal">
                                    {translate('Fiscal address', locale)}
                                </option>
                                <option value="registered">
                                    {translate('Registered address', locale)}
                                </option>
                                <option value="operating">
                                    {translate('Establishment address', locale)}
                                </option>
                            </select>
                        </label>
                        {(
                            [
                                'address_line_1',
                                'address_line_2',
                                'postal_code',
                                'city',
                                'province',
                            ] as const
                        ).map((field) => (
                            <label key={field} className="grid gap-2 text-sm">
                                {translate(
                                    {
                                        address_line_1: 'Address line 1',
                                        address_line_2: 'Address line 2',
                                        postal_code: 'Postal code',
                                        city: 'City',
                                        province: 'Province',
                                    }[field],
                                    locale,
                                )}
                                <Input
                                    value={address[field] || ''}
                                    required={
                                        field === 'address_line_1' ||
                                        field === 'city'
                                    }
                                    maxLength={
                                        field === 'postal_code'
                                            ? 20
                                            : field === 'city' ||
                                                field === 'province'
                                              ? 100
                                              : 255
                                    }
                                    onChange={(event) =>
                                        updateAddress(index, {
                                            [field]: event.target.value,
                                        })
                                    }
                                />
                            </label>
                        ))}
                        <label className="grid gap-2 text-sm">
                            {translate('Country', locale)}
                            <select
                                className="h-9 rounded-md border bg-background px-3"
                                value={address.country}
                                onChange={(event) =>
                                    updateAddress(index, {
                                        country: event.target.value,
                                    })
                                }
                            >
                                {countries.map((country) => (
                                    <option
                                        key={country.code}
                                        value={country.code}
                                    >
                                        {translate(country.name, locale)} (
                                        {country.code})
                                    </option>
                                ))}
                            </select>
                        </label>
                        <Button
                            type="button"
                            variant="outline"
                            className="w-fit"
                            onClick={() =>
                                form.setData(
                                    'addresses',
                                    form.data.addresses.filter(
                                        (_, position) => position !== index,
                                    ),
                                )
                            }
                        >
                            {translate('Remove address from form', locale)}
                        </Button>
                    </fieldset>
                ))}
                <Button
                    type="button"
                    variant="outline"
                    className="w-fit"
                    disabled={form.data.addresses.length >= 30}
                    onClick={() =>
                        form.setData('addresses', [
                            ...form.data.addresses,
                            {
                                id: null,
                                label: '',
                                kind: form.data.addresses.some(
                                    (address) => address.kind === 'fiscal',
                                )
                                    ? 'operating'
                                    : 'fiscal',
                                address_line_1: '',
                                address_line_2: '',
                                postal_code: '',
                                city: '',
                                province: '',
                                country: form.data.country,
                            },
                        ])
                    }
                >
                    {translate('Add address', locale)}
                </Button>
            </section>
            <label className="grid gap-2 text-sm">
                {translate('Notes', locale)}
                <textarea
                    className="min-h-20 rounded-md border bg-background p-3"
                    maxLength={10000}
                    value={form.data.notes}
                    onChange={(event) =>
                        form.setData('notes', event.target.value)
                    }
                />
            </label>
            <label className="flex items-center gap-2 text-sm">
                <input
                    type="checkbox"
                    checked={form.data.is_active}
                    onChange={(event) =>
                        form.setData('is_active', event.target.checked)
                    }
                />
                {translate('Use this entity for future autofill', locale)}
            </label>
            {entity ? (
                <label className="grid gap-2 text-sm">
                    {translate('Correction reason', locale)}
                    <textarea
                        className="min-h-20 rounded-md border bg-background p-3"
                        required
                        maxLength={2000}
                        value={form.data.change_note}
                        onChange={(event) =>
                            form.setData('change_note', event.target.value)
                        }
                    />
                </label>
            ) : null}
            <Button className="w-fit" disabled={form.processing}>
                {translate('Save fiscal entity', locale)}
            </Button>
        </form>
    );
}

import { Form, Head, usePage } from '@inertiajs/react';
import { Building2, CalendarPlus, LockKeyhole, Save } from 'lucide-react';
import InputError from '@/components/input-error';
import { PageHeader, PageShell, TableViewport } from '@/components/page-shell';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
type Company = {
    id: number;
    legal_name: string;
    trade_name: string | null;
    tax_id: string | null;
    address: string | null;
    postal_code: string | null;
    city: string | null;
    province: string | null;
    country: string;
    email: string | null;
    phone: string | null;
    website: string | null;
    iban: string | null;
    bic: string | null;
    commercial_registry: string | null;
    invoice_footer: string | null;
    logo_path: string | null;
};
type Series = {
    id: number;
    document_type: string;
    name: string;
    prefix: string;
    next_number: number;
    is_active: boolean;
    last_issued_at: string | null;
};
type FiscalYear = {
    id: number;
    year: number;
    starts_on: string;
    ends_on: string;
    status: string;
    closed_at: string | null;
    series: Series[];
};
export default function FiscalSettings({
    company,
    fiscalYears,
}: {
    company: Company;
    fiscalYears: FiscalYear[];
}) {
    return (
        <>
            <Head title="Company & fiscal settings" />
            <PageShell className="gap-8">
                <PageHeader
                    title="Company & fiscal settings"
                    description="Legal identity, payment details, fiscal years and document numbering."
                />
                <section>
                    <div className="mb-4 flex items-center gap-2">
                        <Building2 className="size-4" />
                        <h2 className="font-semibold">Company details</h2>
                    </div>
                    <Form
                        action="/company-fiscal/company"
                        method="post"
                        encType="multipart/form-data"
                        className="grid gap-5"
                    >
                        {({ errors, processing, recentlySuccessful }) => (
                            <>
                                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                                    <Field
                                        label="Legal name"
                                        name="legal_name"
                                        value={company.legal_name}
                                    />
                                    <Field
                                        label="Trade name"
                                        name="trade_name"
                                        value={company.trade_name}
                                    />
                                    <Field
                                        label="Tax ID"
                                        name="tax_id"
                                        value={company.tax_id}
                                    />
                                    <Field
                                        label="Email"
                                        name="email"
                                        type="email"
                                        value={company.email}
                                    />
                                    <Field
                                        label="Phone"
                                        name="phone"
                                        value={company.phone}
                                    />
                                    <Field
                                        label="Website"
                                        name="website"
                                        type="url"
                                        value={company.website}
                                    />
                                    <Field
                                        label="Address"
                                        name="address"
                                        value={company.address}
                                        className="xl:col-span-2"
                                    />
                                    <Field
                                        label="Postal code"
                                        name="postal_code"
                                        value={company.postal_code}
                                    />
                                    <Field
                                        label="City"
                                        name="city"
                                        value={company.city}
                                    />
                                    <Field
                                        label="Province"
                                        name="province"
                                        value={company.province}
                                    />
                                    <Field
                                        label="Country"
                                        name="country"
                                        value={company.country}
                                    />
                                    <Field
                                        label="IBAN"
                                        name="iban"
                                        value={company.iban}
                                    />
                                    <Field
                                        label="BIC"
                                        name="bic"
                                        value={company.bic}
                                    />
                                    <Field
                                        label="Commercial registry details"
                                        name="commercial_registry"
                                        value={company.commercial_registry}
                                        className="xl:col-span-2"
                                        placeholder="E.g. Commercial Registry of Córdoba, volume…, page…, entry…"
                                        help="Enter the details exactly as they appear in the incorporation deed or registry extract. They will be shown automatically in the legal notice."
                                    />
                                    <div className="grid gap-2 xl:col-span-2">
                                        <Label>Logo</Label>
                                        {company.logo_path && (
                                            <img
                                                src="/company-fiscal/logo"
                                                alt="Company logo"
                                                className="h-16 w-fit object-contain"
                                            />
                                        )}
                                        <Input
                                            name="logo"
                                            type="file"
                                            accept="image/png,image/jpeg,image/webp"
                                        />
                                        <InputError message={errors.logo} />
                                    </div>
                                    <div className="grid gap-2 md:col-span-2 xl:col-span-2">
                                        <Label>Document footer</Label>
                                        <textarea
                                            name="invoice_footer"
                                            defaultValue={
                                                company.invoice_footer || ''
                                            }
                                            className="min-h-24 rounded-md border bg-transparent p-3 text-sm"
                                        />
                                        <InputError
                                            message={errors.invoice_footer}
                                        />
                                    </div>
                                </div>
                                <div className="flex items-center gap-3">
                                    <Button
                                        className="w-fit"
                                        disabled={processing}
                                    >
                                        <Save /> Save company details
                                    </Button>
                                    {recentlySuccessful && (
                                        <span className="text-sm text-green-700 dark:text-green-400">
                                            Saved
                                        </span>
                                    )}
                                </div>
                            </>
                        )}
                    </Form>
                </section>
                <section className="border-t pt-6">
                    <div className="mb-4 flex flex-wrap items-end justify-between gap-3">
                        <div>
                            <h2 className="font-semibold">
                                Fiscal years & series
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                Closing a year permanently blocks further
                                numbering.
                            </p>
                        </div>
                        <Form
                            action="/company-fiscal/years"
                            method="post"
                            className="flex items-end gap-2"
                        >
                            <Field
                                label="New year"
                                name="year"
                                type="number"
                                value={String(new Date().getFullYear() + 1)}
                            />
                            <Button>
                                <CalendarPlus /> Create year
                            </Button>
                        </Form>
                    </div>
                    <div className="grid gap-8">
                        {fiscalYears.map((year) => (
                            <div key={year.id} className="min-w-0">
                                <div className="mb-3 flex flex-wrap items-center justify-between gap-3 border-y py-3">
                                    <div className="flex items-center gap-2">
                                        <h3 className="font-semibold">
                                            Fiscal year {year.year}
                                        </h3>
                                        <Badge variant="outline">
                                            {year.status}
                                        </Badge>
                                    </div>
                                    {year.status === 'open' && (
                                        <Form
                                            action={`/company-fiscal/years/${year.id}/close`}
                                            method="post"
                                            onBefore={() =>
                                                confirm(
                                                    `Close fiscal year ${year.year}? This cannot be undone.`,
                                                )
                                            }
                                        >
                                            <Button
                                                variant="destructive"
                                                size="sm"
                                            >
                                                <LockKeyhole /> Close year
                                            </Button>
                                        </Form>
                                    )}
                                </div>
                                <TableViewport>
                                    <table className="w-full min-w-[58rem] text-sm">
                                        <thead>
                                            <tr className="border-b text-left text-muted-foreground">
                                                <th className="py-2">
                                                    Document
                                                </th>
                                                <th>Name</th>
                                                <th>Prefix</th>
                                                <th>Next number</th>
                                                <th>Last issued</th>
                                                <th>Active</th>
                                                <th className="w-24" />
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {year.series.map((series) => (
                                                <tr
                                                    key={series.id}
                                                    className="border-b"
                                                >
                                                    <td className="py-3 capitalize">
                                                        {series.document_type.replaceAll(
                                                            '_',
                                                            ' ',
                                                        )}
                                                    </td>
                                                    <td colSpan={6}>
                                                        <Form
                                                            action={`/company-fiscal/series/${series.id}`}
                                                            method="patch"
                                                            className="grid grid-cols-[1fr_8rem_9rem_10rem_5rem_4rem] items-center gap-2"
                                                        >
                                                            <Input
                                                                name="name"
                                                                defaultValue={
                                                                    series.name
                                                                }
                                                                disabled={
                                                                    year.status ===
                                                                    'closed'
                                                                }
                                                            />
                                                            <Input
                                                                name="prefix"
                                                                defaultValue={
                                                                    series.prefix
                                                                }
                                                                disabled={
                                                                    year.status ===
                                                                    'closed'
                                                                }
                                                                readOnly={
                                                                    series.next_number >
                                                                    1
                                                                }
                                                            />
                                                            <Input
                                                                name="next_number"
                                                                type="number"
                                                                min={
                                                                    series.next_number
                                                                }
                                                                defaultValue={
                                                                    series.next_number
                                                                }
                                                                disabled={
                                                                    year.status ===
                                                                    'closed'
                                                                }
                                                            />
                                                            <span className="text-xs text-muted-foreground">
                                                                {series.last_issued_at
                                                                    ? dateTime(
                                                                          series.last_issued_at,
                                                                      )
                                                                    : 'Never'}
                                                            </span>
                                                            <label className="flex items-center gap-2">
                                                                <input
                                                                    type="hidden"
                                                                    name="is_active"
                                                                    value="0"
                                                                />
                                                                <Checkbox
                                                                    name="is_active"
                                                                    value="1"
                                                                    defaultChecked={
                                                                        series.is_active
                                                                    }
                                                                    disabled={
                                                                        year.status ===
                                                                        'closed'
                                                                    }
                                                                />
                                                            </label>
                                                            <Button
                                                                size="icon"
                                                                variant="ghost"
                                                                title="Save series"
                                                                disabled={
                                                                    year.status ===
                                                                    'closed'
                                                                }
                                                            >
                                                                <Save />
                                                            </Button>
                                                        </Form>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </TableViewport>
                            </div>
                        ))}
                    </div>
                </section>
            </PageShell>
        </>
    );
}
function Field({
    label,
    name,
    value,
    type = 'text',
    className = '',
    placeholder,
    help,
}: {
    label: string;
    name: string;
    value?: string | null;
    type?: string;
    className?: string;
    placeholder?: string;
    help?: string;
}) {
    const errors = usePage<{ errors: Record<string, string> }>().props.errors;

    return (
        <div className={`grid gap-2 ${className}`}>
            <Label>{label}</Label>
            <Input
                name={name}
                type={type}
                defaultValue={value ?? ''}
                placeholder={placeholder}
            />
            {help && (
                <p className="text-xs leading-5 text-muted-foreground">
                    {help}
                </p>
            )}
            <InputError message={errors[name]} />
        </div>
    );
}
const dateTime = (value: string) =>
    new Intl.DateTimeFormat(undefined, {
        dateStyle: 'short',
        timeStyle: 'short',
    }).format(new Date(value));
FiscalSettings.layout = {
    breadcrumbs: [
        { title: 'Company & fiscal settings', href: '/company-fiscal' },
    ],
};

import { Form, Head } from '@inertiajs/react';
import {
    Boxes,
    Building2,
    Pencil,
    Plus,
    Settings2,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

type Unit = { id: number; name: string; symbol: string };
type TaxRate = { id: number; name: string; rate: string; is_default: boolean };
type AccountingAccount = {
    code: string;
    name: string;
    type: 'income' | 'expense';
};
type Product = {
    id: number;
    type: 'product' | 'service';
    sku: string | null;
    name: string;
    description: string | null;
    sale_price: string;
    cost_price: string;
    track_stock: boolean;
    track_serials: boolean;
    is_active: boolean;
    unit_id: number;
    tax_rate_id: number;
    sale_account_code: string;
    purchase_account_code: string;
    unit: Unit;
    tax_rate: TaxRate;
};
type Supplier = {
    id: number;
    legal_name: string;
    trade_name: string | null;
    tax_id: string | null;
    email: string | null;
    phone: string | null;
    address_line_1: string | null;
    address_line_2: string | null;
    postal_code: string | null;
    city: string | null;
    province: string | null;
    country: string;
    iban: string | null;
    bic: string | null;
    payment_terms_days: number;
    notes: string | null;
    is_active: boolean;
};
type Series = {
    id: number;
    document_type: string;
    name: string;
    prefix: string;
    next_number: number;
    fiscal_year: number | null;
};
type Tab = 'items' | 'suppliers' | 'settings';

export default function CatalogIndex({
    products,
    suppliers,
    units,
    taxRates,
    documentSeries,
    accountingAccounts,
}: {
    products: Product[];
    suppliers: Supplier[];
    units: Unit[];
    taxRates: TaxRate[];
    documentSeries: Series[];
    accountingAccounts: AccountingAccount[];
}) {
    const [tab, setTab] = useState<Tab>('items');

    return (
        <>
            <Head title="Catalog" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <header>
                    <h1 className="text-xl font-semibold">Catalog</h1>
                    <p className="text-sm text-muted-foreground">
                        Products, services, suppliers and document
                        configuration.
                    </p>
                </header>
                <div className="flex gap-1 border-b" role="tablist">
                    <TabButton
                        active={tab === 'items'}
                        onClick={() => setTab('items')}
                        icon={<Boxes />}
                    >
                        Products & services
                    </TabButton>
                    <TabButton
                        active={tab === 'suppliers'}
                        onClick={() => setTab('suppliers')}
                        icon={<Building2 />}
                    >
                        Suppliers
                    </TabButton>
                    <TabButton
                        active={tab === 'settings'}
                        onClick={() => setTab('settings')}
                        icon={<Settings2 />}
                    >
                        Configuration
                    </TabButton>
                </div>

                {tab === 'items' && (
                    <section>
                        <div className="mb-4 flex items-center justify-between gap-3">
                            <div>
                                <h2 className="font-semibold">
                                    Products & services
                                </h2>
                                <p className="text-sm text-muted-foreground">
                                    Commercial and inventory master data.
                                </p>
                            </div>
                            <ProductDialog
                                units={units}
                                taxRates={taxRates}
                                accountingAccounts={accountingAccounts}
                            />
                        </div>
                        <div className="overflow-x-auto border-y">
                            <table className="w-full min-w-[54rem] text-sm">
                                <thead>
                                    <tr className="border-b text-left text-muted-foreground">
                                        <th className="py-2">Code</th>
                                        <th>Item</th>
                                        <th>Type</th>
                                        <th>Unit</th>
                                        <th>Cost</th>
                                        <th>Sale</th>
                                        <th>Tax</th>
                                        <th>Control</th>
                                        <th className="w-24" />
                                    </tr>
                                </thead>
                                <tbody>
                                    {products.map((product) => (
                                        <tr
                                            key={product.id}
                                            className="border-b last:border-0"
                                        >
                                            <td className="py-3 font-mono text-xs">
                                                {product.sku || '—'}
                                            </td>
                                            <td>
                                                <div className="font-medium">
                                                    {product.name}
                                                </div>
                                                {!product.is_active && (
                                                    <span className="text-xs text-muted-foreground">
                                                        Inactive
                                                    </span>
                                                )}
                                            </td>
                                            <td>
                                                <Badge variant="secondary">
                                                    {product.type}
                                                </Badge>
                                            </td>
                                            <td>{product.unit.symbol}</td>
                                            <td>{money(product.cost_price)}</td>
                                            <td className="font-medium">
                                                {money(product.sale_price)}
                                            </td>
                                            <td>{product.tax_rate.rate}%</td>
                                            <td className="text-xs text-muted-foreground">
                                                {product.track_serials
                                                    ? 'Serials'
                                                    : product.track_stock
                                                      ? 'Stock'
                                                      : 'None'}
                                            </td>
                                            <td>
                                                <div className="flex justify-end gap-1">
                                                    <ProductDialog
                                                        product={product}
                                                        units={units}
                                                        taxRates={taxRates}
                                                        accountingAccounts={
                                                            accountingAccounts
                                                        }
                                                    />
                                                    <DeleteButton
                                                        action={`/catalog/products/${product.id}`}
                                                        label={product.name}
                                                    />
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                            {products.length === 0 && (
                                <Empty text="No products or services yet." />
                            )}
                        </div>
                    </section>
                )}

                {tab === 'suppliers' && (
                    <section>
                        <div className="mb-4 flex items-center justify-between gap-3">
                            <div>
                                <h2 className="font-semibold">Suppliers</h2>
                                <p className="text-sm text-muted-foreground">
                                    Purchasing and payment information.
                                </p>
                            </div>
                            <SupplierDialog />
                        </div>
                        <div className="overflow-x-auto border-y">
                            <table className="w-full min-w-[48rem] text-sm">
                                <thead>
                                    <tr className="border-b text-left text-muted-foreground">
                                        <th className="py-2">Supplier</th>
                                        <th>Tax ID</th>
                                        <th>Contact</th>
                                        <th>Location</th>
                                        <th>Payment</th>
                                        <th className="w-24" />
                                    </tr>
                                </thead>
                                <tbody>
                                    {suppliers.map((supplier) => (
                                        <tr
                                            key={supplier.id}
                                            className="border-b last:border-0"
                                        >
                                            <td className="py-3">
                                                <div className="font-medium">
                                                    {supplier.legal_name}
                                                </div>
                                                <div className="text-xs text-muted-foreground">
                                                    {supplier.trade_name ||
                                                        (!supplier.is_active
                                                            ? 'Inactive'
                                                            : '')}
                                                </div>
                                            </td>
                                            <td>{supplier.tax_id || '—'}</td>
                                            <td>
                                                <div>
                                                    {supplier.email || '—'}
                                                </div>
                                                <div className="text-xs text-muted-foreground">
                                                    {supplier.phone}
                                                </div>
                                            </td>
                                            <td>
                                                {[
                                                    supplier.city,
                                                    supplier.province,
                                                ]
                                                    .filter(Boolean)
                                                    .join(', ') || '—'}
                                            </td>
                                            <td>
                                                {supplier.payment_terms_days}{' '}
                                                days
                                            </td>
                                            <td>
                                                <div className="flex justify-end gap-1">
                                                    <SupplierDialog
                                                        supplier={supplier}
                                                    />
                                                    <DeleteButton
                                                        action={`/catalog/suppliers/${supplier.id}`}
                                                        label={
                                                            supplier.legal_name
                                                        }
                                                    />
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                            {suppliers.length === 0 && (
                                <Empty text="No suppliers yet." />
                            )}
                        </div>
                    </section>
                )}

                {tab === 'settings' && (
                    <section className="grid gap-8 xl:grid-cols-3">
                        <ConfigList
                            title="Units"
                            rows={units.map((unit) => [unit.name, unit.symbol])}
                        />
                        <ConfigList
                            title="Tax rates"
                            rows={taxRates.map((tax) => [
                                tax.name,
                                `${tax.rate}%${tax.is_default ? ' · Default' : ''}`,
                            ])}
                        />
                        <ConfigList
                            title="Document series"
                            rows={documentSeries.map((series) => [
                                series.document_type.replaceAll('_', ' '),
                                `${series.prefix}-${series.fiscal_year ?? ''} · next ${series.next_number}`,
                            ])}
                        />
                    </section>
                )}
            </div>
        </>
    );
}

function TabButton({
    active,
    onClick,
    icon,
    children,
}: {
    active: boolean;
    onClick: () => void;
    icon: React.ReactNode;
    children: React.ReactNode;
}) {
    return (
        <button
            type="button"
            role="tab"
            aria-selected={active}
            onClick={onClick}
            className={`flex items-center gap-2 border-b-2 px-3 py-2 text-sm ${active ? 'border-primary font-medium text-foreground' : 'border-transparent text-muted-foreground hover:text-foreground'} [&_svg]:size-4`}
        >
            {icon}
            {children}
        </button>
    );
}

function ProductDialog({
    product,
    units,
    taxRates,
    accountingAccounts,
}: {
    product?: Product;
    units: Unit[];
    taxRates: TaxRate[];
    accountingAccounts: AccountingAccount[];
}) {
    const [open, setOpen] = useState(false);
    const defaultTax =
        product?.tax_rate_id ?? taxRates.find((tax) => tax.is_default)?.id;

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button
                    size={product ? 'icon' : 'default'}
                    variant={product ? 'ghost' : 'default'}
                    title={product ? 'Edit item' : undefined}
                >
                    {product ? (
                        <Pencil />
                    ) : (
                        <>
                            <Plus /> New item
                        </>
                    )}
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>
                        {product ? 'Edit item' : 'New product or service'}
                    </DialogTitle>
                </DialogHeader>
                <Form
                    action={
                        product
                            ? `/catalog/products/${product.id}`
                            : '/catalog/products'
                    }
                    method={product ? 'patch' : 'post'}
                    onSuccess={() => setOpen(false)}
                    className="grid gap-4"
                >
                    {({ errors, processing }) => (
                        <>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <SelectField
                                    label="Type"
                                    name="type"
                                    value={product?.type ?? 'product'}
                                    options={[
                                        ['product', 'Product'],
                                        ['service', 'Service'],
                                    ]}
                                />
                                <Field
                                    label="SKU / code"
                                    name="sku"
                                    value={product?.sku}
                                    error={errors.sku}
                                />
                                <Field
                                    label="Name"
                                    name="name"
                                    value={product?.name}
                                    error={errors.name}
                                    className="sm:col-span-2"
                                />
                                <Field
                                    label="Description"
                                    name="description"
                                    value={product?.description}
                                    className="sm:col-span-2"
                                />
                                <SelectField
                                    label="Unit"
                                    name="unit_id"
                                    value={String(
                                        product?.unit_id ?? units[0]?.id,
                                    )}
                                    options={units.map((unit) => [
                                        String(unit.id),
                                        `${unit.name} (${unit.symbol})`,
                                    ])}
                                />
                                <SelectField
                                    label="Tax"
                                    name="tax_rate_id"
                                    value={String(defaultTax ?? '')}
                                    options={taxRates.map((tax) => [
                                        String(tax.id),
                                        `${tax.name} · ${tax.rate}%`,
                                    ])}
                                />
                                <Field
                                    label="Cost price"
                                    name="cost_price"
                                    value={product?.cost_price ?? '0'}
                                    type="number"
                                    step="0.01"
                                    error={errors.cost_price}
                                />
                                <Field
                                    label="Sale price"
                                    name="sale_price"
                                    value={product?.sale_price ?? '0'}
                                    type="number"
                                    step="0.01"
                                    error={errors.sale_price}
                                />
                                <SelectField
                                    label="Sales account"
                                    name="sale_account_code"
                                    value={
                                        product?.sale_account_code ??
                                        (product?.type === 'service'
                                            ? '705'
                                            : '700')
                                    }
                                    options={accountingAccounts
                                        .filter(
                                            (account) =>
                                                account.type === 'income',
                                        )
                                        .map((account) => [
                                            account.code,
                                            `${account.code} · ${account.name}`,
                                        ])}
                                />
                                <SelectField
                                    label="Purchase account"
                                    name="purchase_account_code"
                                    value={
                                        product?.purchase_account_code ?? '600'
                                    }
                                    options={accountingAccounts
                                        .filter(
                                            (account) =>
                                                account.type === 'expense',
                                        )
                                        .map((account) => [
                                            account.code,
                                            `${account.code} · ${account.name}`,
                                        ])}
                                />
                            </div>
                            <div className="grid gap-3 border-t pt-4 sm:grid-cols-3">
                                <BooleanField
                                    name="track_stock"
                                    label="Track stock"
                                    checked={product?.track_stock ?? false}
                                />
                                <BooleanField
                                    name="track_serials"
                                    label="Track serial numbers"
                                    checked={product?.track_serials ?? false}
                                />
                                <BooleanField
                                    name="is_active"
                                    label="Active"
                                    checked={product?.is_active ?? true}
                                />
                            </div>
                            <Button disabled={processing}>
                                {product ? 'Save changes' : 'Create item'}
                            </Button>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function SupplierDialog({ supplier }: { supplier?: Supplier }) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button
                    size={supplier ? 'icon' : 'default'}
                    variant={supplier ? 'ghost' : 'default'}
                    title={supplier ? 'Edit supplier' : undefined}
                >
                    {supplier ? (
                        <Pencil />
                    ) : (
                        <>
                            <Plus /> New supplier
                        </>
                    )}
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>
                        {supplier ? 'Edit supplier' : 'New supplier'}
                    </DialogTitle>
                </DialogHeader>
                <Form
                    action={
                        supplier
                            ? `/catalog/suppliers/${supplier.id}`
                            : '/catalog/suppliers'
                    }
                    method={supplier ? 'patch' : 'post'}
                    onSuccess={() => setOpen(false)}
                    className="grid gap-4"
                >
                    {({ errors, processing }) => (
                        <>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <Field
                                    label="Legal name"
                                    name="legal_name"
                                    value={supplier?.legal_name}
                                    error={errors.legal_name}
                                />
                                <Field
                                    label="Trade name"
                                    name="trade_name"
                                    value={supplier?.trade_name}
                                />
                                <Field
                                    label="Tax ID"
                                    name="tax_id"
                                    value={supplier?.tax_id}
                                    error={errors.tax_id}
                                />
                                <Field
                                    label="Email"
                                    name="email"
                                    type="email"
                                    value={supplier?.email}
                                    error={errors.email}
                                />
                                <Field
                                    label="Phone"
                                    name="phone"
                                    value={supplier?.phone}
                                />
                                <Field
                                    label="Payment terms (days)"
                                    name="payment_terms_days"
                                    type="number"
                                    value={String(
                                        supplier?.payment_terms_days ?? 30,
                                    )}
                                />
                                <Field
                                    label="IBAN"
                                    name="iban"
                                    value={supplier?.iban}
                                />
                                <Field
                                    label="BIC"
                                    name="bic"
                                    value={supplier?.bic}
                                />
                                <Field
                                    label="Address"
                                    name="address_line_1"
                                    value={supplier?.address_line_1}
                                    className="sm:col-span-2"
                                />
                                <Field
                                    label="Address details"
                                    name="address_line_2"
                                    value={supplier?.address_line_2}
                                    className="sm:col-span-2"
                                />
                                <Field
                                    label="Postal code"
                                    name="postal_code"
                                    value={supplier?.postal_code}
                                />
                                <Field
                                    label="City"
                                    name="city"
                                    value={supplier?.city}
                                />
                                <Field
                                    label="Province"
                                    name="province"
                                    value={supplier?.province}
                                />
                                <Field
                                    label="Country code"
                                    name="country"
                                    value={supplier?.country ?? 'ES'}
                                />
                                <Field
                                    label="Notes"
                                    name="notes"
                                    value={supplier?.notes}
                                    className="sm:col-span-2"
                                />
                            </div>
                            <BooleanField
                                name="is_active"
                                label="Active supplier"
                                checked={supplier?.is_active ?? true}
                            />
                            <Button disabled={processing}>
                                {supplier ? 'Save changes' : 'Create supplier'}
                            </Button>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function Field({
    label,
    name,
    value,
    error,
    type = 'text',
    step,
    className = '',
}: {
    label: string;
    name: string;
    value?: string | null;
    error?: string;
    type?: string;
    step?: string;
    className?: string;
}) {
    return (
        <div className={`grid gap-1.5 ${className}`}>
            <Label htmlFor={name}>{label}</Label>
            <Input
                id={name}
                name={name}
                type={type}
                step={step}
                defaultValue={value ?? ''}
            />
            <InputError message={error} />
        </div>
    );
}
function SelectField({
    label,
    name,
    value,
    options,
}: {
    label: string;
    name: string;
    value?: string;
    options: [string, string][];
}) {
    return (
        <div className="grid gap-1.5">
            <Label>{label}</Label>
            <Select name={name} defaultValue={value}>
                <SelectTrigger>
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    {options.map(([id, text]) => (
                        <SelectItem key={id} value={id}>
                            {text}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
        </div>
    );
}
function BooleanField({
    name,
    label,
    checked,
}: {
    name: string;
    label: string;
    checked: boolean;
}) {
    return (
        <label className="flex items-center gap-2 text-sm">
            <input type="hidden" name={name} value="0" />
            <Checkbox name={name} value="1" defaultChecked={checked} />
            {label}
        </label>
    );
}
function DeleteButton({ action, label }: { action: string; label: string }) {
    return (
        <Form
            action={action}
            method="delete"
            onBefore={() => confirm(`Delete ${label}?`)}
        >
            <Button type="submit" size="icon" variant="ghost" title="Delete">
                <Trash2 />
            </Button>
        </Form>
    );
}
function Empty({ text }: { text: string }) {
    return (
        <div className="py-10 text-center text-sm text-muted-foreground">
            {text}
        </div>
    );
}
function ConfigList({
    title,
    rows,
}: {
    title: string;
    rows: [string, string][];
}) {
    return (
        <div>
            <h2 className="mb-2 font-semibold">{title}</h2>
            <div className="border-y">
                {rows.map(([name, value]) => (
                    <div
                        key={`${name}-${value}`}
                        className="flex items-center justify-between gap-4 border-b py-3 text-sm last:border-0"
                    >
                        <span className="capitalize">{name}</span>
                        <span className="text-right text-muted-foreground">
                            {value}
                        </span>
                    </div>
                ))}
            </div>
        </div>
    );
}
function money(value: string) {
    return new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: 'EUR',
    }).format(Number(value));
}

CatalogIndex.layout = { breadcrumbs: [{ title: 'Catalog', href: '/catalog' }] };

import { Form, Head, Link } from '@inertiajs/react';
import { ArrowLeft, Plus, Star, Trash2 } from 'lucide-react';
import { FormErrorSummary } from '@/components/forms/form-layout';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Product = { id: number; sku: string | null; name: string };
type Price = {
    id: number;
    unit_price: string;
    currency: string;
    recorded_at: string;
    purchase_document_line: {
        document: {
            id: number;
            number: string;
            type: string;
            issue_date: string;
        };
    } | null;
};
type CatalogItem = {
    id: number;
    product_id: number;
    supplier_sku: string | null;
    supplier_description: string | null;
    minimum_order_quantity: string;
    order_multiple: string;
    lead_time_days: number | null;
    last_unit_price: string | null;
    currency: string;
    is_preferred: boolean;
    is_active: boolean;
    product: Product;
    prices: Price[];
};
type Supplier = {
    id: number;
    legal_name: string;
    trade_name: string | null;
    email: string | null;
    contacts: SupplierContact[];
    products: CatalogItem[];
};
type SupplierContact = {
    id: number;
    first_name: string;
    last_name: string | null;
    position: string | null;
    roles: string[] | null;
    document_type: string | null;
    document_number: string | null;
    email: string | null;
    phone: string | null;
    preferred_channel: string | null;
    is_primary: boolean;
    is_authorized_signatory: boolean;
    notes: string | null;
};
type Scorecard = {
    overall_score: number | null;
    delivery_score: number | null;
    quality_score: number | null;
    matching_score: number | null;
    annual_spend: number;
    orders: number;
    average_delay_days: number | null;
    return_rate: number | null;
    rejection_rate: number | null;
    open_quality_incidents: number;
};

export default function SupplierIntelligenceShow({
    supplier,
    scorecard,
    availableProducts,
}: {
    supplier: Supplier;
    scorecard: Scorecard;
    availableProducts: Product[];
}) {
    return (
        <>
            <Head title={supplier.trade_name || supplier.legal_name} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <header className="flex items-start justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold">
                            {supplier.trade_name || supplier.legal_name}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {supplier.legal_name} ·{' '}
                            {supplier.email || 'No email'}
                        </p>
                    </div>
                    <Button variant="outline" asChild>
                        <Link href="/supplier-intelligence">
                            <ArrowLeft /> Suppliers
                        </Link>
                    </Button>
                </header>
                <section className="grid border-y sm:grid-cols-3 lg:grid-cols-8">
                    <Metric
                        label="Overall"
                        value={score(scorecard.overall_score)}
                    />
                    <Metric
                        label="Delivery"
                        value={score(scorecard.delivery_score)}
                    />
                    <Metric
                        label="Quality"
                        value={score(scorecard.quality_score)}
                    />
                    <Metric
                        label="Matching"
                        value={score(scorecard.matching_score)}
                    />
                    <Metric label="Orders" value={String(scorecard.orders)} />
                    <Metric
                        label="Rejected"
                        value={
                            scorecard.rejection_rate === null
                                ? '—'
                                : `${scorecard.rejection_rate}%`
                        }
                    />
                    <Metric
                        label="Open incidents"
                        value={String(scorecard.open_quality_incidents)}
                    />
                    <Metric
                        label="12-month spend"
                        value={money(scorecard.annual_spend)}
                    />
                </section>
                <section className="grid gap-4 border-y py-5 lg:grid-cols-[1.2fr_1fr]">
                    <div className="space-y-3">
                        <div>
                            <h2 className="font-semibold">Supplier contacts</h2>
                            <p className="text-sm text-muted-foreground">
                                Purchasing, billing, logistics and authorized
                                representatives.
                            </p>
                        </div>
                        <div className="divide-y border-y">
                            {supplier.contacts.map((contact) => (
                                <details key={contact.id} className="py-3">
                                    <summary className="cursor-pointer list-none">
                                        <div className="flex flex-wrap items-center gap-2 font-medium">
                                            {contact.first_name}{' '}
                                            {contact.last_name}
                                            {contact.is_primary ? (
                                                <Badge>Primary</Badge>
                                            ) : null}
                                            {contact.is_authorized_signatory ? (
                                                <Badge variant="secondary">
                                                    Authorized signatory
                                                </Badge>
                                            ) : null}
                                        </div>
                                        <div className="text-sm text-muted-foreground">
                                            {[
                                                contact.position,
                                                contact.email,
                                                contact.phone,
                                            ]
                                                .filter(Boolean)
                                                .join(' · ')}
                                        </div>
                                        <div className="mt-2 flex flex-wrap gap-1">
                                            {(contact.roles ?? []).map(
                                                (role) => (
                                                    <Badge
                                                        key={role}
                                                        variant="outline"
                                                    >
                                                        {supplierRoleLabels[
                                                            role
                                                        ] ?? role}
                                                    </Badge>
                                                ),
                                            )}
                                        </div>
                                    </summary>
                                    <Form
                                        action={`/supplier-intelligence/${supplier.id}/contacts/${contact.id}`}
                                        method="patch"
                                        className="mt-4 grid gap-4 bg-muted/30 p-4"
                                    >
                                        {({ processing, errors }) => (
                                            <>
                                                <FormErrorSummary
                                                    errors={errors}
                                                />
                                                <SupplierContactFields
                                                    contact={contact}
                                                    prefix={`supplier-contact-${contact.id}`}
                                                />
                                                <Button disabled={processing}>
                                                    Save contact
                                                </Button>
                                            </>
                                        )}
                                    </Form>
                                    <Form
                                        action={`/supplier-intelligence/${supplier.id}/contacts/${contact.id}`}
                                        method="delete"
                                        className="mt-2 flex justify-end"
                                    >
                                        {({ processing }) => (
                                            <Button
                                                size="sm"
                                                variant="ghost"
                                                disabled={processing}
                                            >
                                                Remove
                                            </Button>
                                        )}
                                    </Form>
                                </details>
                            ))}
                            {supplier.contacts.length === 0 ? (
                                <p className="py-5 text-sm text-muted-foreground">
                                    No supplier contacts yet.
                                </p>
                            ) : null}
                        </div>
                    </div>
                    <Form
                        action={`/supplier-intelligence/${supplier.id}/contacts`}
                        method="post"
                        resetOnSuccess
                        className="grid gap-4 border p-4"
                    >
                        {({ processing, errors }) => (
                            <>
                                <h3 className="font-medium">
                                    Add supplier contact
                                </h3>
                                <FormErrorSummary errors={errors} />
                                <SupplierContactFields prefix="new-supplier-contact" />
                                <Button disabled={processing}>
                                    Add contact
                                </Button>
                            </>
                        )}
                    </Form>
                </section>
                {availableProducts.length > 0 && (
                    <details className="border p-4">
                        <summary className="cursor-pointer font-medium">
                            Add catalog product
                        </summary>
                        <Form
                            action={`/supplier-intelligence/${supplier.id}/products`}
                            method="post"
                            className="mt-4 grid gap-4 md:grid-cols-4"
                        >
                            <Field label="Product">
                                <select
                                    name="product_id"
                                    required
                                    className="h-9 border bg-transparent px-3 text-sm"
                                >
                                    {availableProducts.map((product) => (
                                        <option
                                            key={product.id}
                                            value={product.id}
                                        >
                                            {product.sku
                                                ? `${product.sku} · ${product.name}`
                                                : product.name}
                                        </option>
                                    ))}
                                </select>
                            </Field>
                            <TextField
                                label="Supplier SKU"
                                name="supplier_sku"
                            />
                            <TextField
                                label="Minimum quantity"
                                name="minimum_order_quantity"
                                type="number"
                                value="1"
                            />
                            <TextField
                                label="Order multiple"
                                name="order_multiple"
                                type="number"
                                value="1"
                            />
                            <TextField
                                label="Lead time (days)"
                                name="lead_time_days"
                                type="number"
                            />
                            <TextField
                                label="Current unit price"
                                name="last_unit_price"
                                type="number"
                            />
                            <TextField
                                label="Currency"
                                name="currency"
                                value="EUR"
                            />
                            <Checks />
                            <Button className="self-end">
                                <Plus /> Add product
                            </Button>
                        </Form>
                    </details>
                )}
                <section className="grid gap-5">
                    <h2 className="font-semibold">Supplier catalog</h2>
                    {supplier.products.map((item) => (
                        <article key={item.id} className="border p-4">
                            <div className="mb-4 flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <div className="flex items-center gap-2 font-medium">
                                        {item.product.sku && (
                                            <span>{item.product.sku} ·</span>
                                        )}
                                        {item.product.name}
                                        {item.is_preferred && (
                                            <Badge variant="secondary">
                                                <Star className="size-3" />{' '}
                                                Preferred
                                            </Badge>
                                        )}
                                    </div>
                                    <div className="text-sm text-muted-foreground">
                                        Supplier SKU {item.supplier_sku || '—'}{' '}
                                        · Last price{' '}
                                        {item.last_unit_price
                                            ? `${item.last_unit_price} ${item.currency}`
                                            : '—'}
                                    </div>
                                </div>
                                <Form
                                    action={`/supplier-products/${item.id}`}
                                    method="delete"
                                >
                                    <Button size="icon" variant="ghost">
                                        <Trash2 />
                                    </Button>
                                </Form>
                            </div>
                            <Form
                                action={`/supplier-products/${item.id}`}
                                method="patch"
                                className="grid gap-4 md:grid-cols-4"
                            >
                                <input
                                    type="hidden"
                                    name="product_id"
                                    value={item.product_id}
                                />
                                <TextField
                                    label="Supplier SKU"
                                    name="supplier_sku"
                                    value={item.supplier_sku || ''}
                                />
                                <TextField
                                    label="Description"
                                    name="supplier_description"
                                    value={item.supplier_description || ''}
                                />
                                <TextField
                                    label="Minimum quantity"
                                    name="minimum_order_quantity"
                                    type="number"
                                    value={item.minimum_order_quantity}
                                />
                                <TextField
                                    label="Order multiple"
                                    name="order_multiple"
                                    type="number"
                                    value={item.order_multiple}
                                />
                                <TextField
                                    label="Lead time (days)"
                                    name="lead_time_days"
                                    type="number"
                                    value={
                                        item.lead_time_days?.toString() || ''
                                    }
                                />
                                <TextField
                                    label="Current unit price"
                                    name="last_unit_price"
                                    type="number"
                                    value={item.last_unit_price || ''}
                                />
                                <TextField
                                    label="Currency"
                                    name="currency"
                                    value={item.currency}
                                />
                                <Checks
                                    preferred={item.is_preferred}
                                    active={item.is_active}
                                />
                                <Button variant="outline" className="self-end">
                                    Save catalog item
                                </Button>
                            </Form>
                            {item.prices.length > 0 && (
                                <div className="mt-5 border-t pt-4">
                                    <h3 className="mb-2 text-sm font-medium">
                                        Recent price history
                                    </h3>
                                    <div className="flex flex-wrap gap-2">
                                        {item.prices.map((price) => (
                                            <Badge
                                                key={price.id}
                                                variant="outline"
                                            >
                                                {price.unit_price}{' '}
                                                {price.currency} ·{' '}
                                                {date(price.recorded_at)}
                                                {price.purchase_document_line
                                                    ?.document
                                                    ? ` · ${price.purchase_document_line.document.number}`
                                                    : ''}
                                            </Badge>
                                        ))}
                                    </div>
                                </div>
                            )}
                        </article>
                    ))}
                    {supplier.products.length === 0 && (
                        <p className="py-8 text-center text-sm text-muted-foreground">
                            No catalog items yet. They are also created
                            automatically from orders and supplier invoices.
                        </p>
                    )}
                </section>
            </div>
        </>
    );
}

const supplierRoleLabels: Record<string, string> = {
    legal_representative: 'Legal representative',
    billing: 'Billing',
    purchasing: 'Purchasing',
    technical: 'Technical',
    quality: 'Quality',
    logistics: 'Logistics',
    other: 'Other',
};

function SupplierContactFields({
    contact,
    prefix,
}: {
    contact?: SupplierContact;
    prefix: string;
}) {
    return (
        <>
            <div className="grid gap-3 sm:grid-cols-2">
                <ContactInput
                    id={`${prefix}-first`}
                    name="first_name"
                    label="First name"
                    value={contact?.first_name}
                    required
                />
                <ContactInput
                    id={`${prefix}-last`}
                    name="last_name"
                    label="Last name"
                    value={contact?.last_name}
                />
                <ContactInput
                    id={`${prefix}-position`}
                    name="position"
                    label="Position"
                    value={contact?.position}
                />
                <ContactInput
                    id={`${prefix}-email`}
                    name="email"
                    label="Email"
                    type="email"
                    value={contact?.email}
                />
                <ContactInput
                    id={`${prefix}-phone`}
                    name="phone"
                    label="Phone"
                    value={contact?.phone}
                />
                <label className="grid gap-2 text-sm font-medium">
                    Preferred channel
                    <select
                        name="preferred_channel"
                        defaultValue={contact?.preferred_channel ?? ''}
                        className="h-9 rounded-md border bg-transparent px-3 text-sm"
                    >
                        <option value="">No preference</option>
                        <option value="email">Email</option>
                        <option value="phone">Phone</option>
                        <option value="whatsapp">WhatsApp</option>
                        <option value="sms">SMS</option>
                    </select>
                </label>
            </div>
            <fieldset className="grid gap-2">
                <legend className="text-sm font-medium">
                    Responsibilities
                </legend>
                <div className="grid gap-2 sm:grid-cols-2">
                    {Object.entries(supplierRoleLabels).map(([role, label]) => (
                        <label
                            key={role}
                            className="flex items-center gap-2 rounded-md border px-3 py-2 text-sm"
                        >
                            <input
                                type="checkbox"
                                name="roles[]"
                                value={role}
                                defaultChecked={
                                    contact
                                        ? (contact.roles ?? []).includes(role)
                                        : role === 'purchasing'
                                }
                            />{' '}
                            {label}
                        </label>
                    ))}
                </div>
            </fieldset>
            <div className="grid gap-3 sm:grid-cols-2">
                <label className="grid gap-2 text-sm font-medium">
                    ID type
                    <select
                        name="document_type"
                        defaultValue={contact?.document_type ?? ''}
                        className="h-9 rounded-md border bg-transparent px-3 text-sm"
                    >
                        <option value="">None</option>
                        <option value="passport">Passport</option>
                        <option value="nif">NIF</option>
                        <option value="nie">NIE</option>
                        <option value="other">Other</option>
                    </select>
                </label>
                <ContactInput
                    id={`${prefix}-document`}
                    name="document_number"
                    label="ID number"
                    value={contact?.document_number}
                />
            </div>
            <div className="grid gap-2 sm:grid-cols-2">
                <label className="flex items-center gap-2 rounded-md border px-3 py-2 text-sm">
                    <input type="hidden" name="is_primary" value="0" />
                    <input
                        type="checkbox"
                        name="is_primary"
                        value="1"
                        defaultChecked={contact?.is_primary ?? false}
                    />{' '}
                    Primary contact
                </label>
                <label className="flex items-center gap-2 rounded-md border px-3 py-2 text-sm">
                    <input
                        type="hidden"
                        name="is_authorized_signatory"
                        value="0"
                    />
                    <input
                        type="checkbox"
                        name="is_authorized_signatory"
                        value="1"
                        defaultChecked={
                            contact?.is_authorized_signatory ?? false
                        }
                    />{' '}
                    Authorized signatory
                </label>
            </div>
            <label className="grid gap-2 text-sm font-medium">
                Notes
                <textarea
                    name="notes"
                    defaultValue={contact?.notes ?? ''}
                    className="min-h-20 rounded-md border bg-transparent px-3 py-2 text-sm"
                />
            </label>
        </>
    );
}

function ContactInput({
    id,
    name,
    label,
    value,
    type = 'text',
    required = false,
}: {
    id: string;
    name: string;
    label: string;
    value?: string | null;
    type?: string;
    required?: boolean;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>{label}</Label>
            <Input
                id={id}
                name={name}
                type={type}
                defaultValue={value ?? ''}
                required={required}
            />
        </div>
    );
}

function Metric({ label, value }: { label: string; value: string }) {
    return (
        <div className="border-r p-4 last:border-0">
            <div className="text-xs text-muted-foreground">{label}</div>
            <div className="mt-1 font-semibold">{value}</div>
        </div>
    );
}
function Field({
    label,
    children,
}: {
    label: string;
    children: React.ReactNode;
}) {
    return (
        <label className="grid gap-2 text-sm font-medium">
            {label}
            {children}
        </label>
    );
}
function TextField({
    label,
    name,
    type = 'text',
    value = '',
}: {
    label: string;
    name: string;
    type?: string;
    value?: string;
}) {
    return (
        <Field label={label}>
            <Input
                name={name}
                type={type}
                defaultValue={value}
                min={type === 'number' ? '0' : undefined}
                step={type === 'number' ? '0.001' : undefined}
            />
        </Field>
    );
}
function Checks({
    preferred = false,
    active = true,
}: {
    preferred?: boolean;
    active?: boolean;
}) {
    return (
        <div className="flex items-end gap-4 pb-2 text-sm">
            <label>
                <input type="hidden" name="is_preferred" value="0" />
                <input
                    type="checkbox"
                    name="is_preferred"
                    value="1"
                    defaultChecked={preferred}
                />{' '}
                Preferred
            </label>
            <label>
                <input type="hidden" name="is_active" value="0" />
                <input
                    type="checkbox"
                    name="is_active"
                    value="1"
                    defaultChecked={active}
                />{' '}
                Active
            </label>
        </div>
    );
}
const score = (value: number | null) => (value === null ? '—' : `${value}/100`);
const money = (value: number) =>
    new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: 'EUR',
    }).format(value);
const date = (value: string) =>
    new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(
        new Date(value),
    );

SupplierIntelligenceShow.layout = {
    breadcrumbs: [
        { title: 'Supply', href: '/purchases' },
        { title: 'Supplier intelligence', href: '/supplier-intelligence' },
        { title: 'Supplier', href: '#' },
    ],
};

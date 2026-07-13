import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Plus, Save, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

type Customer = {
    id: number;
    first_name: string;
    last_name: string | null;
    company_name: string | null;
};
type Installation = {
    id: number;
    person_id: number | null;
    project_reference: string | null;
    site_name: string | null;
};
type Product = {
    id: number;
    name: string;
    description: string | null;
    sale_price: string;
    unit_id: number;
    tax_rate_id: number;
    tax_rate: { rate: string };
};
type Line = {
    product_id: number | null;
    unit_id: number | null;
    tax_rate_id: number | null;
    description: string;
    quantity: string;
    unit_price: string;
    discount_percent: string;
    tax_percent: string;
};
type Document = {
    id: number;
    type: string;
    number: string;
    person_id: number;
    installation_id: number | null;
    issue_date: string;
    due_date: string | null;
    notes: string | null;
    lines: Array<Line & { id: number }>;
};

const typeLabels: Record<string, string> = {
    invoice: 'Invoice',
    order: 'Sales order',
    delivery_note: 'Delivery note',
};

export default function SalesForm({
    customers,
    installations,
    products,
    document,
    defaultCustomerId,
    defaultInstallationId,
    defaultType = 'invoice',
}: {
    customers: Customer[];
    installations: Installation[];
    products: Product[];
    document?: Document;
    defaultCustomerId?: number | null;
    defaultInstallationId?: number | null;
    defaultType?: string;
}) {
    const form = useForm({
        type: document?.type ?? defaultType,
        person_id: String(document?.person_id ?? defaultCustomerId ?? ''),
        installation_id: String(
            document?.installation_id ?? defaultInstallationId ?? '',
        ),
        issue_date: document ? localDate(document.issue_date) : localToday(),
        due_date: document?.due_date
            ? localDate(document.due_date)
            : addDays(30),
        notes: document?.notes ?? '',
        lines: document?.lines.map((line) => ({
            product_id: line.product_id,
            unit_id: line.unit_id,
            tax_rate_id: line.tax_rate_id ?? null,
            description: line.description,
            quantity: String(line.quantity),
            unit_price: String(line.unit_price),
            discount_percent: String(line.discount_percent),
            tax_percent: String(line.tax_percent),
        })) ?? [blankLine()],
    });
    const availableInstallations = installations.filter(
        (item) =>
            !form.data.person_id ||
            item.person_id === Number(form.data.person_id),
    );
    const totals = calculate(form.data.lines);

    const updateLine = (index: number, changes: Partial<Line>) =>
        form.setData(
            'lines',
            form.data.lines.map((line, position) =>
                position === index ? { ...line, ...changes } : line,
            ),
        );

    function selectProduct(index: number, value: string) {
        const product = products.find((item) => item.id === Number(value));

        if (!product) {
return;
}

        updateLine(index, {
            product_id: product.id,
            unit_id: product.unit_id,
            tax_rate_id: product.tax_rate_id,
            description: product.description || product.name,
            unit_price: product.sale_price,
            tax_percent: product.tax_rate.rate,
        });
    }

    return (
        <>
            <Head
                title={document ? `Edit ${document.number}` : 'New document'}
            />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <header className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold">
                            {document
                                ? `Edit ${document.number}`
                                : `New ${typeLabels[form.data.type].toLowerCase()}`}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Create a standalone sales document using catalog
                            items or custom lines.
                        </p>
                    </div>
                    <Button variant="outline" asChild>
                        <Link
                            href={document ? `/sales/${document.id}` : '/sales'}
                        >
                            <ArrowLeft /> Back
                        </Link>
                    </Button>
                </header>

                <form
                    onSubmit={(event) => {
                        event.preventDefault();

                        if (document) {
                            form.put(`/sales/${document.id}`);
                        } else {
                            form.post('/sales');
                        }
                    }}
                    className="grid gap-8"
                >
                    <section className="grid gap-4 border-t pt-5 md:grid-cols-2 xl:grid-cols-5">
                        <SelectField
                            label="Document type"
                            value={form.data.type}
                            disabled={Boolean(document)}
                            onChange={(value) => form.setData('type', value)}
                            options={Object.entries(typeLabels).map(
                                ([value, label]) => [value, label],
                            )}
                        />
                        <SelectField
                            label="Customer"
                            value={form.data.person_id}
                            onChange={(value) =>
                                form.setData((data) => ({
                                    ...data,
                                    person_id: value,
                                    installation_id: '',
                                }))
                            }
                            options={customers.map((customer) => [
                                String(customer.id),
                                customer.company_name ||
                                    `${customer.first_name} ${customer.last_name || ''}`.trim(),
                            ])}
                        />
                        <SelectField
                            label="Installation"
                            value={form.data.installation_id || 'none'}
                            onChange={(value) =>
                                form.setData(
                                    'installation_id',
                                    value === 'none' ? '' : value,
                                )
                            }
                            options={[
                                ['none', 'No installation'],
                                ...availableInstallations.map((item) => [
                                    String(item.id),
                                    item.project_reference ||
                                        item.site_name ||
                                        `#${item.id}`,
                                ]),
                            ]}
                        />
                        <Field
                            label="Issue date"
                            type="date"
                            value={form.data.issue_date}
                            onChange={(value) =>
                                form.setData('issue_date', value)
                            }
                        />
                        {form.data.type === 'invoice' && (
                            <Field
                                label="Due date"
                                type="date"
                                value={form.data.due_date}
                                onChange={(value) =>
                                    form.setData('due_date', value)
                                }
                            />
                        )}
                    </section>

                    <section>
                        <div className="mb-3 flex items-center justify-between gap-3">
                            <div>
                                <h2 className="font-semibold">Lines</h2>
                                <p className="text-sm text-muted-foreground">
                                    Prices are before tax.
                                </p>
                            </div>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() =>
                                    form.setData('lines', [
                                        ...form.data.lines,
                                        blankLine(),
                                    ])
                                }
                            >
                                <Plus /> Add line
                            </Button>
                        </div>
                        <div className="overflow-x-auto border-y">
                            <table className="w-full min-w-[58rem] text-sm">
                                <thead>
                                    <tr className="border-b text-left text-muted-foreground">
                                        <th className="py-2">Catalog item</th>
                                        <th>Description</th>
                                        <th className="w-24">Qty.</th>
                                        <th className="w-32">Price</th>
                                        <th className="w-24">Disc. %</th>
                                        <th className="w-24">Tax %</th>
                                        <th className="w-32 text-right">
                                            Total
                                        </th>
                                        <th className="w-12" />
                                    </tr>
                                </thead>
                                <tbody>
                                    {form.data.lines.map((line, index) => (
                                        <tr
                                            key={index}
                                            className="border-b align-top last:border-0"
                                        >
                                            <td className="py-2 pr-2">
                                                <Select
                                                    value={
                                                        line.product_id
                                                            ? String(
                                                                  line.product_id,
                                                              )
                                                            : ''
                                                    }
                                                    onValueChange={(value) =>
                                                        selectProduct(
                                                            index,
                                                            value,
                                                        )
                                                    }
                                                >
                                                    <SelectTrigger>
                                                        <SelectValue placeholder="Custom line" />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        {products.map(
                                                            (product) => (
                                                                <SelectItem
                                                                    key={
                                                                        product.id
                                                                    }
                                                                    value={String(
                                                                        product.id,
                                                                    )}
                                                                >
                                                                    {
                                                                        product.name
                                                                    }
                                                                </SelectItem>
                                                            ),
                                                        )}
                                                    </SelectContent>
                                                </Select>
                                            </td>
                                            <td className="p-2">
                                                <Input
                                                    value={line.description}
                                                    onChange={(event) =>
                                                        updateLine(index, {
                                                            description:
                                                                event.target
                                                                    .value,
                                                        })
                                                    }
                                                />
                                            </td>
                                            <td className="p-2">
                                                <LineNumber
                                                    value={line.quantity}
                                                    min="0.001"
                                                    step="0.001"
                                                    onChange={(quantity) =>
                                                        updateLine(index, {
                                                            quantity,
                                                        })
                                                    }
                                                />
                                            </td>
                                            <td className="p-2">
                                                <LineNumber
                                                    value={line.unit_price}
                                                    min="0"
                                                    step="0.01"
                                                    onChange={(unit_price) =>
                                                        updateLine(index, {
                                                            unit_price,
                                                        })
                                                    }
                                                />
                                            </td>
                                            <td className="p-2">
                                                <LineNumber
                                                    value={
                                                        line.discount_percent
                                                    }
                                                    min="0"
                                                    max="100"
                                                    step="0.01"
                                                    onChange={(
                                                        discount_percent,
                                                    ) =>
                                                        updateLine(index, {
                                                            discount_percent,
                                                        })
                                                    }
                                                />
                                            </td>
                                            <td className="p-2">
                                                <LineNumber
                                                    value={line.tax_percent}
                                                    min="0"
                                                    max="100"
                                                    step="0.01"
                                                    onChange={(tax_percent) =>
                                                        updateLine(index, {
                                                            tax_percent,
                                                        })
                                                    }
                                                />
                                            </td>
                                            <td className="p-2 text-right font-medium">
                                                {money(lineTotal(line))}
                                            </td>
                                            <td className="p-2">
                                                <Button
                                                    type="button"
                                                    size="icon"
                                                    variant="ghost"
                                                    title="Remove line"
                                                    disabled={
                                                        form.data.lines
                                                            .length === 1
                                                    }
                                                    onClick={() =>
                                                        form.setData(
                                                            'lines',
                                                            form.data.lines.filter(
                                                                (_, position) =>
                                                                    position !==
                                                                    index,
                                                            ),
                                                        )
                                                    }
                                                >
                                                    <Trash2 />
                                                </Button>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <section className="grid gap-6 border-t pt-5 lg:grid-cols-[1fr_22rem]">
                        <div className="grid gap-2">
                            <Label htmlFor="notes">Document notes</Label>
                            <textarea
                                id="notes"
                                className="min-h-28 rounded-md border bg-transparent p-3 text-sm"
                                value={form.data.notes}
                                onChange={(event) =>
                                    form.setData('notes', event.target.value)
                                }
                            />
                        </div>
                        <div className="grid content-start gap-2 text-sm">
                            <Total label="Subtotal" value={totals.subtotal} />
                            <Total label="Discount" value={-totals.discount} />
                            <Total label="Tax" value={totals.tax} />
                            <Total label="Total" value={totals.total} strong />
                        </div>
                    </section>

                    {Object.entries(form.errors).map(([key, error]) => (
                        <p key={key} className="text-sm text-destructive">
                            {error}
                        </p>
                    ))}
                    <div className="flex justify-end">
                        <Button disabled={form.processing}>
                            <Save />{' '}
                            {document ? 'Save document' : 'Create document'}
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}

function blankLine(): Line {
    return {
        product_id: null,
        unit_id: null,
        tax_rate_id: null,
        description: '',
        quantity: '1',
        unit_price: '0',
        discount_percent: '0',
        tax_percent: '21',
    };
}
function lineTotal(line: Line) {
    const base = Number(line.quantity) * Number(line.unit_price);

    return (
        base *
        (1 - Number(line.discount_percent) / 100) *
        (1 + Number(line.tax_percent) / 100)
    );
}
function calculate(lines: Line[]) {
    return lines.reduce(
        (sum, line) => {
            const subtotal = Number(line.quantity) * Number(line.unit_price);
            const discount = (subtotal * Number(line.discount_percent)) / 100;
            const tax =
                ((subtotal - discount) * Number(line.tax_percent)) / 100;

            return {
                subtotal: sum.subtotal + subtotal,
                discount: sum.discount + discount,
                tax: sum.tax + tax,
                total: sum.total + subtotal - discount + tax,
            };
        },
        { subtotal: 0, discount: 0, tax: 0, total: 0 },
    );
}
function LineNumber({
    value,
    onChange,
    ...props
}: {
    value: string;
    onChange: (value: string) => void;
    min?: string;
    max?: string;
    step?: string;
}) {
    return (
        <Input
            type="number"
            value={value}
            onChange={(event) => onChange(event.target.value)}
            {...props}
        />
    );
}
function Field({
    label,
    type,
    value,
    onChange,
}: {
    label: string;
    type: string;
    value: string;
    onChange: (value: string) => void;
}) {
    return (
        <div className="grid gap-2">
            <Label>{label}</Label>
            <Input
                type={type}
                value={value}
                onChange={(event) => onChange(event.target.value)}
            />
        </div>
    );
}
function SelectField({
    label,
    value,
    onChange,
    options,
    disabled = false,
}: {
    label: string;
    value: string;
    onChange: (value: string) => void;
    options: string[][];
    disabled?: boolean;
}) {
    return (
        <div className="grid gap-2">
            <Label>{label}</Label>
            <Select value={value} onValueChange={onChange} disabled={disabled}>
                <SelectTrigger className="w-full">
                    <SelectValue
                        placeholder={`Select ${label.toLowerCase()}`}
                    />
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
function Total({
    label,
    value,
    strong = false,
}: {
    label: string;
    value: number;
    strong?: boolean;
}) {
    return (
        <div
            className={`flex justify-between border-b py-2 ${strong ? 'text-base font-semibold' : ''}`}
        >
            <span>{label}</span>
            <span>{money(value)}</span>
        </div>
    );
}
function money(value: number) {
    return new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: 'EUR',
    }).format(value);
}
function localToday() {
    const today = new Date();

    return new Date(today.getTime() - today.getTimezoneOffset() * 60000)
        .toISOString()
        .slice(0, 10);
}
function addDays(days: number) {
    const date = new Date();
    date.setDate(date.getDate() + days);

    return new Date(date.getTime() - date.getTimezoneOffset() * 60000)
        .toISOString()
        .slice(0, 10);
}
function localDate(value: string) {
    const date = new Date(value);

    return new Date(date.getTime() - date.getTimezoneOffset() * 60000)
        .toISOString()
        .slice(0, 10);
}
SalesForm.layout = {
    breadcrumbs: [
        { title: 'Sales', href: '/sales' },
        { title: 'Document', href: '#' },
    ],
};

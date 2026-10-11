import { Head, Link, useForm } from '@inertiajs/react';
import { FormErrorSummary } from '@/components/forms/form-layout';
import { Button } from '@/components/ui/button';

type Line = {
    product_id: string;
    description: string;
    quantity: string;
    unit_price: string;
    discount_percent: string;
    tax_percent: string;
};
type Product = {
    id: number;
    name: string;
    sku: string;
    cost_price: string;
    tax_rate: { rate: string } | null;
};
type Order = {
    id: number;
    number: string;
    supplier_id: number;
    issue_date: string;
    expected_delivery_date: string | null;
    shipping_cost: string;
    notes: string | null;
    lines: Array<Omit<Line, 'product_id'> & { product_id: number | null }>;
};
const emptyLine = (): Line => ({
    product_id: '',
    description: '',
    quantity: '1',
    unit_price: '0',
    discount_percent: '0',
    tax_percent: '21',
});
const money = (value: number) =>
    new Intl.NumberFormat('es-ES', {
        style: 'currency',
        currency: 'EUR',
    }).format(value);
export default function PurchaseEdit({
    document,
    revision,
    suppliers,
    products,
    shippingTaxPercent,
}: {
    document: Order;
    revision: string;
    suppliers: Array<{
        id: number;
        legal_name: string;
        trade_name: string | null;
    }>;
    products: Product[];
    shippingTaxPercent: number;
}) {
    const form = useForm({
        type: 'purchase_order',
        revision,
        reason: '',
        supplier_id: String(document.supplier_id),
        issue_date: document.issue_date.slice(0, 10),
        expected_delivery_date:
            document.expected_delivery_date?.slice(0, 10) || '',
        shipping_cost: document.shipping_cost,
        notes: document.notes || '',
        lines: document.lines.map(
            (line): Line => ({
                product_id: line.product_id ? String(line.product_id) : '',
                description: line.description,
                quantity: line.quantity,
                unit_price: line.unit_price,
                discount_percent: line.discount_percent,
                tax_percent: line.tax_percent,
            }),
        ),
    });
    function setLine(index: number, field: keyof Line, value: string) {
        form.setData(
            'lines',
            form.data.lines.map((line, i) =>
                i === index ? { ...line, [field]: value } : line,
            ),
        );
    }
    function chooseProduct(index: number, value: string) {
        const product = products.find((item) => String(item.id) === value);
        form.setData(
            'lines',
            form.data.lines.map((line, i) =>
                i !== index
                    ? line
                    : {
                          ...line,
                          product_id: value,
                          ...(product
                              ? {
                                    description: product.name,
                                    unit_price: product.cost_price,
                                    tax_percent: product.tax_rate?.rate || '21',
                                }
                              : {}),
                      },
            ),
        );
    }
    const round = (value: number) =>
        Math.round((value + Number.EPSILON) * 100) / 100;
    const total = round(
        form.data.lines.reduce((sum, line) => {
            const base = round(
                Number(line.quantity) *
                    Number(line.unit_price) *
                    (1 - Number(line.discount_percent) / 100),
            );

            return sum + base + round((base * Number(line.tax_percent)) / 100);
        }, 0) +
            Number(form.data.shipping_cost) +
            round((Number(form.data.shipping_cost) * shippingTaxPercent) / 100),
    );

    return (
        <>
            <Head title={`Editar ${document.number}`} />
            <form
                onSubmit={(event) => {
                    event.preventDefault();
                    form.put(`/purchases/${document.id}`);
                }}
                className="mx-auto grid w-full max-w-6xl gap-6 p-4 sm:p-6"
            >
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold">
                            Editar orden de compra {document.number}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Cambia el proveedor y las partidas. El número del
                            pedido se conserva y se registra el motivo del
                            cambio.
                        </p>
                    </div>
                    <Button asChild variant="outline">
                        <Link href={`/purchases/${document.id}`}>
                            Volver al pedido
                        </Link>
                    </Button>
                </div>
                <FormErrorSummary errors={form.errors} />
                <div className="grid gap-4 sm:grid-cols-3">
                    <label className="grid gap-2 text-sm font-medium">
                        Proveedor
                        <select
                            className="h-10 rounded-md border bg-background px-3"
                            value={form.data.supplier_id}
                            onChange={(event) =>
                                form.setData('supplier_id', event.target.value)
                            }
                            required
                        >
                            {suppliers.map((supplier) => (
                                <option key={supplier.id} value={supplier.id}>
                                    {supplier.trade_name || supplier.legal_name}
                                </option>
                            ))}
                        </select>
                    </label>
                    <label className="grid gap-2 text-sm font-medium">
                        Fecha del pedido
                        <input
                            className="h-10 rounded-md border px-3"
                            type="date"
                            value={form.data.issue_date}
                            onChange={(event) =>
                                form.setData('issue_date', event.target.value)
                            }
                            required
                        />
                    </label>
                    <label className="grid gap-2 text-sm font-medium">
                        Entrega prevista
                        <input
                            className="h-10 rounded-md border px-3"
                            type="date"
                            value={form.data.expected_delivery_date}
                            onChange={(event) =>
                                form.setData(
                                    'expected_delivery_date',
                                    event.target.value,
                                )
                            }
                        />
                    </label>
                </div>
                <div className="grid gap-4">
                    {form.data.lines.map((line, index) => (
                        <section
                            key={index}
                            className="grid gap-4 rounded-md border p-4"
                        >
                            <div className="flex items-center justify-between gap-3">
                                <h2 className="font-medium">
                                    Partida {index + 1}
                                </h2>
                                <Button
                                    type="button"
                                    variant="outline"
                                    disabled={form.data.lines.length === 1}
                                    onClick={() =>
                                        form.setData(
                                            'lines',
                                            form.data.lines.filter(
                                                (_, i) => i !== index,
                                            ),
                                        )
                                    }
                                >
                                    Quitar partida
                                </Button>
                            </div>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <label className="grid gap-2 text-sm">
                                    Producto
                                    <select
                                        className="h-10 rounded-md border bg-background px-3"
                                        value={line.product_id}
                                        onChange={(event) =>
                                            chooseProduct(
                                                index,
                                                event.target.value,
                                            )
                                        }
                                    >
                                        <option value="">Concepto libre</option>
                                        {products.map((product) => (
                                            <option
                                                key={product.id}
                                                value={product.id}
                                            >
                                                {product.sku} · {product.name}
                                            </option>
                                        ))}
                                    </select>
                                </label>
                                <label className="grid gap-2 text-sm">
                                    Descripción
                                    <input
                                        className="h-10 rounded-md border px-3"
                                        value={line.description}
                                        onChange={(event) =>
                                            setLine(
                                                index,
                                                'description',
                                                event.target.value,
                                            )
                                        }
                                        required
                                    />
                                </label>
                            </div>
                            <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
                                {(
                                    [
                                        [
                                            'quantity',
                                            'Cantidad',
                                            '0.001',
                                            '0.001',
                                        ],
                                        [
                                            'unit_price',
                                            'Precio unitario sin IVA',
                                            '0.01',
                                            '0',
                                        ],
                                        [
                                            'discount_percent',
                                            'Descuento %',
                                            '0.01',
                                            '0',
                                        ],
                                        ['tax_percent', 'IVA %', '0.01', '0'],
                                    ] as const
                                ).map(([field, label, step, min]) => (
                                    <label
                                        key={field}
                                        className="grid gap-2 text-sm"
                                    >
                                        {label}
                                        <input
                                            className="h-10 w-full rounded-md border px-3"
                                            type="number"
                                            step={step}
                                            min={min}
                                            max={
                                                field.endsWith('percent')
                                                    ? 100
                                                    : undefined
                                            }
                                            value={line[field]}
                                            onChange={(event) =>
                                                setLine(
                                                    index,
                                                    field,
                                                    event.target.value,
                                                )
                                            }
                                            required
                                        />
                                    </label>
                                ))}
                            </div>
                        </section>
                    ))}
                </div>
                <Button
                    type="button"
                    variant="outline"
                    className="w-fit"
                    onClick={() =>
                        form.setData('lines', [...form.data.lines, emptyLine()])
                    }
                >
                    Añadir producto o concepto
                </Button>
                <div className="grid gap-4 sm:grid-cols-2">
                    <label className="grid gap-2 text-sm">
                        Portes sin IVA
                        <input
                            className="h-10 rounded-md border px-3"
                            type="number"
                            min="0"
                            step="0.01"
                            value={form.data.shipping_cost}
                            onChange={(event) =>
                                form.setData(
                                    'shipping_cost',
                                    event.target.value,
                                )
                            }
                            required
                        />
                    </label>
                    <div className="self-end text-lg font-semibold">
                        Total IVA incluido: {money(total)}
                    </div>
                </div>
                <label className="grid gap-2 text-sm">
                    Notas
                    <textarea
                        className="min-h-20 rounded-md border p-3"
                        value={form.data.notes}
                        onChange={(event) =>
                            form.setData('notes', event.target.value)
                        }
                    />
                </label>
                <label className="grid gap-2 text-sm">
                    Motivo del cambio
                    <textarea
                        className="min-h-20 rounded-md border p-3"
                        minLength={5}
                        maxLength={2000}
                        value={form.data.reason}
                        onChange={(event) =>
                            form.setData('reason', event.target.value)
                        }
                        required
                    />
                </label>
                <p className="text-sm text-muted-foreground">
                    Se volverán a comprobar las aprobaciones necesarias. Si ya
                    se había enviado el pedido, podrás reenviar la versión
                    corregida desde su ficha.
                </p>
                <Button disabled={form.processing} className="w-fit">
                    Guardar cambios
                </Button>
            </form>
        </>
    );
}
PurchaseEdit.layout = {
    breadcrumbs: [
        { title: 'Compras', href: '/purchases' },
        { title: 'Editar pedido', href: '#' },
    ],
};

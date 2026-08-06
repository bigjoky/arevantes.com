import { Form, Head, Link } from '@inertiajs/react';
import {
    ArrowRightLeft,
    BookmarkCheck,
    Boxes,
    Plus,
    Trash2,
    Warehouse as WarehouseIcon,
} from 'lucide-react';
import { useState } from 'react';
import { PageTabs } from '@/components/page-shell';
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

type Warehouse = {
    id: number;
    code: string;
    name: string;
    address: string | null;
    postal_code: string | null;
    city: string | null;
    is_default: boolean;
    is_active: boolean;
};
type Product = {
    id: number;
    sku: string | null;
    name: string;
    cost_price: string;
    track_serials: boolean;
};
type Balance = {
    warehouse_id: number;
    product_id: number;
    quantity: string;
    warehouse: { code: string; name: string };
    product: { sku: string | null; name: string; track_serials: boolean };
};
type Movement = {
    id: number;
    number: string;
    type: string;
    reference: string | null;
    posted_at: string;
    lines: {
        id: number;
        quantity: string;
        product: { name: string; sku: string | null };
        warehouse: { code: string; name: string };
    }[];
};
type Serial = {
    id: number;
    serial_number: string;
    warehouse_id: number;
    product: { name: string; sku: string | null };
};
type Reservation = {
    id: number;
    quantity: string;
    consumed_quantity: string;
    reserved_until: string | null;
    installation: {
        project_reference: string | null;
        site_name: string | null;
    } | null;
    warehouse: { code: string; name: string };
    product: { sku: string | null; name: string };
    work_order: { id: number; number: string } | null;
    sales_document: { id: number; number: string } | null;
};
type Installation = {
    id: number;
    project_reference: string | null;
    site_name: string | null;
};
type Line = {
    product_id: string;
    quantity: string;
    unit_cost: string;
    serials: string;
};
type Tab = 'stock' | 'reservations' | 'movements' | 'warehouses';

export default function InventoryIndex({
    warehouses,
    products,
    balances,
    movements,
    serials,
    reservations,
    installations,
}: {
    warehouses: Warehouse[];
    products: Product[];
    balances: Balance[];
    movements: Movement[];
    serials: Serial[];
    reservations: Reservation[];
    installations: Installation[];
}) {
    const [tab, setTab] = useState<Tab>('stock');

    return (
        <>
            <Head title="Inventory" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <header className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold">Inventory</h1>
                        <p className="text-sm text-muted-foreground">
                            Warehouse stock, serial numbers and immutable
                            movements.
                        </p>
                    </div>
                    {tab === 'movements' ? (
                        <MovementDialog
                            warehouses={warehouses.filter((w) => w.is_active)}
                            products={products}
                        />
                    ) : tab === 'reservations' ? (
                        <ReservationDialog
                            warehouses={warehouses.filter((w) => w.is_active)}
                            products={products}
                            installations={installations}
                        />
                    ) : tab === 'warehouses' ? (
                        <WarehouseDialog />
                    ) : null}
                </header>
                <PageTabs aria-label="Inventory views">
                    <TabButton
                        active={tab === 'reservations'}
                        onClick={() => setTab('reservations')}
                        icon={<BookmarkCheck />}
                    >
                        Reservations
                    </TabButton>
                    <TabButton
                        active={tab === 'stock'}
                        onClick={() => setTab('stock')}
                        icon={<Boxes />}
                    >
                        Stock
                    </TabButton>
                    <TabButton
                        active={tab === 'movements'}
                        onClick={() => setTab('movements')}
                        icon={<ArrowRightLeft />}
                    >
                        Movements
                    </TabButton>
                    <TabButton
                        active={tab === 'warehouses'}
                        onClick={() => setTab('warehouses')}
                        icon={<WarehouseIcon />}
                    >
                        Warehouses
                    </TabButton>
                </PageTabs>
                {tab === 'stock' && (
                    <>
                        <section className="overflow-x-auto">
                            <table className="w-full min-w-[42rem] text-sm">
                                <thead>
                                    <tr className="border-b text-left text-muted-foreground">
                                        <th className="py-2">Product</th>
                                        <th>SKU</th>
                                        <th>Warehouse</th>
                                        <th className="text-right">On hand</th>
                                        <th>Tracking</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {balances.map((balance) => (
                                        <tr
                                            key={`${balance.warehouse_id}-${balance.product_id}`}
                                            className="border-b"
                                        >
                                            <td className="py-3 font-medium">
                                                {balance.product.name}
                                            </td>
                                            <td className="font-mono text-xs">
                                                {balance.product.sku || '—'}
                                            </td>
                                            <td>
                                                {balance.warehouse.name}{' '}
                                                <span className="text-xs text-muted-foreground">
                                                    ({balance.warehouse.code})
                                                </span>
                                            </td>
                                            <td className="text-right text-base font-semibold">
                                                {Number(balance.quantity)}
                                            </td>
                                            <td>
                                                {balance.product
                                                    .track_serials ? (
                                                    <Badge variant="outline">
                                                        Serials
                                                    </Badge>
                                                ) : (
                                                    'Quantity'
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                            {balances.length === 0 && (
                                <Empty text="No stock yet. Post a receipt or adjustment to begin." />
                            )}
                        </section>
                        <section>
                            <h2 className="mb-3 font-semibold">
                                Available serial numbers
                            </h2>
                            <div className="overflow-x-auto border-y">
                                <table className="w-full min-w-[36rem] text-sm">
                                    <thead>
                                        <tr className="border-b text-left text-muted-foreground">
                                            <th className="py-2">
                                                Serial number
                                            </th>
                                            <th>Product</th>
                                            <th>Warehouse</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {serials.map((serial) => (
                                            <tr
                                                key={serial.id}
                                                className="border-b last:border-0"
                                            >
                                                <td className="py-3 font-mono">
                                                    {serial.serial_number}
                                                </td>
                                                <td>{serial.product.name}</td>
                                                <td>
                                                    {warehouses.find(
                                                        (w) =>
                                                            w.id ===
                                                            serial.warehouse_id,
                                                    )?.name || '—'}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                                {serials.length === 0 && (
                                    <Empty text="No serialized units available." />
                                )}
                            </div>
                        </section>
                    </>
                )}
                {tab === 'movements' && (
                    <section className="overflow-x-auto">
                        <table className="w-full min-w-[52rem] text-sm">
                            <thead>
                                <tr className="border-b text-left text-muted-foreground">
                                    <th className="py-2">Movement</th>
                                    <th>Type</th>
                                    <th>Date</th>
                                    <th>Reference</th>
                                    <th>Lines</th>
                                </tr>
                            </thead>
                            <tbody>
                                {movements.map((movement) => (
                                    <tr
                                        key={movement.id}
                                        className="border-b align-top"
                                    >
                                        <td className="py-3 font-medium">
                                            {movement.number}
                                        </td>
                                        <td>
                                            <Badge variant="outline">
                                                {movement.type}
                                            </Badge>
                                        </td>
                                        <td>{date(movement.posted_at)}</td>
                                        <td>{movement.reference || '—'}</td>
                                        <td>
                                            {movement.lines.map((line) => (
                                                <div key={line.id}>
                                                    {line.product.name} ·{' '}
                                                    {Number(line.quantity) > 0
                                                        ? '+'
                                                        : ''}
                                                    {Number(line.quantity)} ·{' '}
                                                    {line.warehouse.code}
                                                </div>
                                            ))}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                        {movements.length === 0 && (
                            <Empty text="No stock movements yet." />
                        )}
                    </section>
                )}
                {tab === 'reservations' && (
                    <section className="overflow-x-auto">
                        <table className="w-full min-w-[48rem] text-sm">
                            <thead>
                                <tr className="border-b text-left text-muted-foreground">
                                    <th className="py-2">Installation</th>
                                    <th>Product</th>
                                    <th>Warehouse</th>
                                    <th>Reserved</th>
                                    <th>Pending</th>
                                    <th>Source</th>
                                    <th>Reserved until</th>
                                    <th className="w-20" />
                                </tr>
                            </thead>
                            <tbody>
                                {reservations.map((reservation) => (
                                    <tr
                                        key={reservation.id}
                                        className="border-b"
                                    >
                                        <td className="py-3 font-medium">
                                            {reservation.installation
                                                ?.project_reference ||
                                                reservation.installation
                                                    ?.site_name ||
                                                '—'}
                                        </td>
                                        <td>{reservation.product.name}</td>
                                        <td>{reservation.warehouse.name}</td>
                                        <td>{Number(reservation.quantity)}</td>
                                        <td>
                                            {Number(reservation.quantity) -
                                                Number(
                                                    reservation.consumed_quantity,
                                                )}
                                        </td>
                                        <td>
                                            {reservation.sales_document ? (
                                                <Link
                                                    href={`/sales/${reservation.sales_document.id}`}
                                                    className="underline underline-offset-2"
                                                >
                                                    {
                                                        reservation
                                                            .sales_document
                                                            .number
                                                    }
                                                </Link>
                                            ) : reservation.work_order ? (
                                                <Link
                                                    href={`/work-orders?work_order=${reservation.work_order.id}`}
                                                    className="underline underline-offset-2"
                                                >
                                                    {
                                                        reservation.work_order
                                                            .number
                                                    }
                                                </Link>
                                            ) : (
                                                'Manual reservation'
                                            )}
                                        </td>
                                        <td>
                                            {reservation.reserved_until
                                                ? date(
                                                      reservation.reserved_until,
                                                  )
                                                : 'No expiry'}
                                        </td>
                                        <td>
                                            <Form
                                                action={`/inventory/reservations/${reservation.id}`}
                                                method="delete"
                                            >
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                >
                                                    Release
                                                </Button>
                                            </Form>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                        {reservations.length === 0 && (
                            <Empty text="No active stock reservations." />
                        )}
                    </section>
                )}
                {tab === 'warehouses' && (
                    <section className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        {warehouses.map((warehouse) => (
                            <div key={warehouse.id} className="border-t pt-4">
                                <div className="flex items-start justify-between gap-3">
                                    <div>
                                        <div className="flex items-center gap-2">
                                            <h2 className="font-semibold">
                                                {warehouse.name}
                                            </h2>
                                            {warehouse.is_default && (
                                                <Badge>Default</Badge>
                                            )}
                                        </div>
                                        <p className="text-sm text-muted-foreground">
                                            {warehouse.code} ·{' '}
                                            {[
                                                warehouse.address,
                                                warehouse.postal_code,
                                                warehouse.city,
                                            ]
                                                .filter(Boolean)
                                                .join(', ') || 'No address'}
                                        </p>
                                    </div>
                                    <WarehouseDialog warehouse={warehouse} />
                                </div>
                            </div>
                        ))}
                    </section>
                )}
            </div>
        </>
    );
}
function ReservationDialog({
    warehouses,
    products,
    installations,
}: {
    warehouses: Warehouse[];
    products: Product[];
    installations: Installation[];
}) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button>
                    <Plus /> New reservation
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Reserve stock for installation</DialogTitle>
                </DialogHeader>
                <Form
                    action="/inventory/reservations"
                    method="post"
                    onSuccess={() => setOpen(false)}
                    className="grid gap-4"
                >
                    <SelectField
                        label="Installation"
                        name="installation_id"
                        options={installations.map((item) => [
                            String(item.id),
                            item.project_reference ||
                                item.site_name ||
                                `#${item.id}`,
                        ])}
                    />
                    <SelectField
                        label="Warehouse"
                        name="warehouse_id"
                        options={warehouses.map((item) => [
                            String(item.id),
                            item.name,
                        ])}
                    />
                    <SelectField
                        label="Product"
                        name="product_id"
                        options={products.map((item) => [
                            String(item.id),
                            item.name,
                        ])}
                    />
                    <Field
                        label="Quantity"
                        name="quantity"
                        type="number"
                        value="1"
                    />
                    <Field
                        label="Reserved until"
                        name="reserved_until"
                        type="datetime-local"
                    />
                    <Button>Reserve stock</Button>
                </Form>
            </DialogContent>
        </Dialog>
    );
}
function MovementDialog({
    warehouses,
    products,
}: {
    warehouses: Warehouse[];
    products: Product[];
}) {
    const [open, setOpen] = useState(false);
    const [type, setType] = useState('receipt');
    const [lines, setLines] = useState<Line[]>([blankLine()]);
    const change = (i: number, data: Partial<Line>) =>
        setLines(
            lines.map((line, p) => (p === i ? { ...line, ...data } : line)),
        );

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button>
                    <Plus /> New movement
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-4xl">
                <DialogHeader>
                    <DialogTitle>Post stock movement</DialogTitle>
                </DialogHeader>
                <Form
                    action="/inventory/movements"
                    method="post"
                    onSuccess={() => {
                        setOpen(false);
                        setLines([blankLine()]);
                    }}
                    className="grid gap-5"
                >
                    <input type="hidden" name="type" value={type} />
                    <div className="grid gap-4 sm:grid-cols-3">
                        <SelectField
                            label="Type"
                            value={type}
                            onChange={setType}
                            options={[
                                ['receipt', 'Receipt'],
                                ['issue', 'Issue'],
                                ['transfer', 'Transfer'],
                                ['adjustment', 'Adjustment'],
                            ]}
                        />
                        {type === 'transfer' ? (
                            <>
                                <SelectField
                                    label="From"
                                    name="source_warehouse_id"
                                    options={warehouses.map((w) => [
                                        String(w.id),
                                        w.name,
                                    ])}
                                />
                                <SelectField
                                    label="To"
                                    name="destination_warehouse_id"
                                    options={warehouses.map((w) => [
                                        String(w.id),
                                        w.name,
                                    ])}
                                />
                            </>
                        ) : (
                            <SelectField
                                label="Warehouse"
                                name="warehouse_id"
                                options={warehouses.map((w) => [
                                    String(w.id),
                                    w.name,
                                ])}
                            />
                        )}
                        <Field label="Reference" name="reference" />
                    </div>
                    <div className="overflow-x-auto border-y">
                        <table className="w-full min-w-[46rem] text-sm">
                            <thead>
                                <tr className="border-b text-left text-muted-foreground">
                                    <th className="py-2">Product</th>
                                    <th className="w-28">Quantity</th>
                                    <th className="w-32">Unit cost</th>
                                    <th>Serials</th>
                                    <th className="w-12" />
                                </tr>
                            </thead>
                            <tbody>
                                {lines.map((line, i) => (
                                    <tr
                                        key={i}
                                        className="border-b last:border-0"
                                    >
                                        <td className="py-2 pr-2">
                                            <Select
                                                name={`lines[${i}][product_id]`}
                                                value={line.product_id}
                                                onValueChange={(value) => {
                                                    const p = products.find(
                                                        (x) =>
                                                            x.id ===
                                                            Number(value),
                                                    );
                                                    change(i, {
                                                        product_id: value,
                                                        unit_cost:
                                                            p?.cost_price ||
                                                            '0',
                                                    });
                                                }}
                                            >
                                                <SelectTrigger>
                                                    <SelectValue placeholder="Select product" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {products.map((p) => (
                                                        <SelectItem
                                                            key={p.id}
                                                            value={String(p.id)}
                                                        >
                                                            {p.name}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        </td>
                                        <td className="p-2">
                                            <Input
                                                name={`lines[${i}][quantity]`}
                                                type="number"
                                                step="0.001"
                                                value={line.quantity}
                                                onChange={(e) =>
                                                    change(i, {
                                                        quantity:
                                                            e.target.value,
                                                    })
                                                }
                                            />
                                        </td>
                                        <td className="p-2">
                                            <Input
                                                name={`lines[${i}][unit_cost]`}
                                                type="number"
                                                step="0.01"
                                                min="0"
                                                value={line.unit_cost}
                                                onChange={(e) =>
                                                    change(i, {
                                                        unit_cost:
                                                            e.target.value,
                                                    })
                                                }
                                            />
                                        </td>
                                        <td className="p-2">
                                            <Input
                                                name={`lines[${i}][serials]`}
                                                value={line.serials}
                                                onChange={(e) =>
                                                    change(i, {
                                                        serials: e.target.value,
                                                    })
                                                }
                                                placeholder="Comma-separated when required"
                                            />
                                        </td>
                                        <td className="p-2">
                                            <Button
                                                type="button"
                                                size="icon"
                                                variant="ghost"
                                                disabled={lines.length === 1}
                                                onClick={() =>
                                                    setLines(
                                                        lines.filter(
                                                            (_, p) => p !== i,
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
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => setLines([...lines, blankLine()])}
                    >
                        <Plus /> Add line
                    </Button>
                    <Button>Post movement</Button>
                </Form>
            </DialogContent>
        </Dialog>
    );
}
function WarehouseDialog({ warehouse }: { warehouse?: Warehouse }) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button
                    variant={warehouse ? 'ghost' : 'default'}
                    size={warehouse ? 'sm' : 'default'}
                >
                    {warehouse ? (
                        'Edit'
                    ) : (
                        <>
                            <Plus /> New warehouse
                        </>
                    )}
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {warehouse ? 'Edit warehouse' : 'New warehouse'}
                    </DialogTitle>
                </DialogHeader>
                <Form
                    action={
                        warehouse
                            ? `/inventory/warehouses/${warehouse.id}`
                            : '/inventory/warehouses'
                    }
                    method={warehouse ? 'patch' : 'post'}
                    onSuccess={() => setOpen(false)}
                    className="grid gap-4"
                >
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field
                            label="Code"
                            name="code"
                            value={warehouse?.code}
                        />
                        <Field
                            label="Name"
                            name="name"
                            value={warehouse?.name}
                        />
                        <Field
                            label="Address"
                            name="address"
                            value={warehouse?.address}
                            className="sm:col-span-2"
                        />
                        <Field
                            label="Postal code"
                            name="postal_code"
                            value={warehouse?.postal_code}
                        />
                        <Field
                            label="City"
                            name="city"
                            value={warehouse?.city}
                        />
                    </div>
                    <Bool
                        name="is_default"
                        label="Default warehouse"
                        checked={warehouse?.is_default ?? false}
                    />
                    <Bool
                        name="is_active"
                        label="Active"
                        checked={warehouse?.is_active ?? true}
                    />
                    <Button>
                        {warehouse ? 'Save changes' : 'Create warehouse'}
                    </Button>
                </Form>
            </DialogContent>
        </Dialog>
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
            onClick={onClick}
            className={`flex items-center gap-2 border-b-2 px-3 py-2 text-sm [&_svg]:size-4 ${active ? 'border-primary font-medium' : 'border-transparent text-muted-foreground'}`}
        >
            {icon}
            {children}
        </button>
    );
}
function SelectField({
    label,
    name,
    value,
    onChange,
    options,
}: {
    label: string;
    name?: string;
    value?: string;
    onChange?: (v: string) => void;
    options: string[][];
}) {
    return (
        <div className="grid gap-2">
            <Label>{label}</Label>
            <Select
                name={name}
                value={value}
                onValueChange={onChange}
                defaultValue={value === undefined ? options[0]?.[0] : undefined}
            >
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
function Field({
    label,
    name,
    value,
    type = 'text',
    className = '',
}: {
    label: string;
    name: string;
    value?: string | null;
    type?: string;
    className?: string;
}) {
    return (
        <div className={`grid gap-2 ${className}`}>
            <Label>{label}</Label>
            <Input name={name} type={type} defaultValue={value ?? ''} />
        </div>
    );
}
function Bool({
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
function Empty({ text }: { text: string }) {
    return (
        <div className="py-10 text-center text-sm text-muted-foreground">
            {text}
        </div>
    );
}
const blankLine = (): Line => ({
    product_id: '',
    quantity: '1',
    unit_cost: '0',
    serials: '',
});
const date = (value: string) =>
    new Intl.DateTimeFormat(undefined, {
        dateStyle: 'short',
        timeStyle: 'short',
    }).format(new Date(value));
InventoryIndex.layout = {
    breadcrumbs: [{ title: 'Inventory', href: '/inventory' }],
};

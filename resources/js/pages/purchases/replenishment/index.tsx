import { Form, Head } from '@inertiajs/react';
import { PackagePlus, Plus, Trash2 } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

type Warehouse = { id: number; code: string; name: string };
type Product = { id: number; sku: string | null; name: string };
type Policy = {
    id: number;
    warehouse_id: number;
    product_id: number;
    reorder_point: string;
    target_stock: string;
    is_active: boolean;
    warehouse: Warehouse;
    product: Product;
};
type Recommendation = {
    policy_id: number;
    warehouse: Warehouse;
    product: Product;
    reorder_point: number;
    target_stock: number;
    on_hand: number;
    reserved: number;
    inbound: number;
    projected: number;
    suggested_quantity: number;
    needs_action: boolean;
    supplier: {
        id: number;
        name: string;
        unit_price: number;
        lead_time_days: number | null;
        is_preferred: boolean;
    } | null;
};

export default function ReplenishmentIndex({
    policies,
    recommendations,
    warehouses,
    products,
}: {
    policies: Policy[];
    recommendations: Recommendation[];
    warehouses: Warehouse[];
    products: Product[];
}) {
    const actionable = recommendations.filter(
        (row) => row.needs_action && row.supplier,
    );

    return (
        <>
            <Head title="Replenishment planning" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <header>
                    <h1 className="text-xl font-semibold">
                        Replenishment planning
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Maintain stock targets and create supplier requests from
                        projected shortages.
                    </p>
                </header>
                <section className="grid border-y sm:grid-cols-4">
                    <Metric label="Policies" value={policies.length} />
                    <Metric
                        label="Need action"
                        value={
                            recommendations.filter((row) => row.needs_action)
                                .length
                        }
                    />
                    <Metric
                        label="Ready to procure"
                        value={actionable.length}
                    />
                    <Metric
                        label="Missing supplier"
                        value={
                            recommendations.filter(
                                (row) => row.needs_action && !row.supplier,
                            ).length
                        }
                    />
                </section>
                <Form
                    action="/replenishment/procure"
                    method="post"
                    className="grid gap-4"
                >
                    <div className="flex items-center justify-between gap-3">
                        <div>
                            <h2 className="font-semibold">Recommendations</h2>
                            <p className="text-sm text-muted-foreground">
                                Projected = on hand − reservations + inbound
                                supply.
                            </p>
                        </div>
                        <Button disabled={actionable.length === 0}>
                            <PackagePlus /> Create selected RFQs
                        </Button>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[70rem] text-sm">
                            <thead>
                                <tr className="border-b text-left text-muted-foreground">
                                    <th className="py-2">Select</th>
                                    <th>Warehouse</th>
                                    <th>Product</th>
                                    <th>On hand</th>
                                    <th>Reserved</th>
                                    <th>Inbound</th>
                                    <th>Projected</th>
                                    <th>Reorder / target</th>
                                    <th>Suggested</th>
                                    <th>Supplier</th>
                                </tr>
                            </thead>
                            <tbody>
                                {recommendations.map((row) => (
                                    <tr
                                        key={row.policy_id}
                                        className="border-b"
                                    >
                                        <td className="py-3">
                                            <input
                                                type="checkbox"
                                                name="policies[]"
                                                value={row.policy_id}
                                                defaultChecked={
                                                    row.needs_action &&
                                                    Boolean(row.supplier)
                                                }
                                                disabled={
                                                    !row.needs_action ||
                                                    !row.supplier
                                                }
                                            />
                                        </td>
                                        <td>
                                            {row.warehouse.name}{' '}
                                            <span className="text-muted-foreground">
                                                ({row.warehouse.code})
                                            </span>
                                        </td>
                                        <td>
                                            <div className="font-medium">
                                                {row.product.name}
                                            </div>
                                            <div className="text-xs text-muted-foreground">
                                                {row.product.sku || 'No SKU'}
                                            </div>
                                        </td>
                                        <td>{number(row.on_hand)}</td>
                                        <td>{number(row.reserved)}</td>
                                        <td>{number(row.inbound)}</td>
                                        <td>
                                            <Badge
                                                variant={
                                                    row.needs_action
                                                        ? 'destructive'
                                                        : 'secondary'
                                                }
                                            >
                                                {number(row.projected)}
                                            </Badge>
                                        </td>
                                        <td>
                                            {number(row.reorder_point)} /{' '}
                                            {number(row.target_stock)}
                                        </td>
                                        <td className="font-semibold">
                                            {number(row.suggested_quantity)}
                                        </td>
                                        <td>
                                            {row.supplier ? (
                                                <>
                                                    <div>
                                                        {row.supplier.name}
                                                    </div>
                                                    <div className="text-xs text-muted-foreground">
                                                        {money(
                                                            row.supplier
                                                                .unit_price,
                                                        )}
                                                        {row.supplier
                                                            .lead_time_days !==
                                                            null &&
                                                            ` · ${row.supplier.lead_time_days} days`}
                                                    </div>
                                                </>
                                            ) : (
                                                <Badge variant="destructive">
                                                    Configure supplier
                                                </Badge>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </Form>
                <details className="border p-4">
                    <summary className="cursor-pointer font-medium">
                        Add replenishment policy
                    </summary>
                    <Form
                        action="/replenishment/policies"
                        method="post"
                        className="mt-4 grid gap-4 md:grid-cols-5"
                    >
                        <SelectField
                            label="Warehouse"
                            name="warehouse_id"
                            options={warehouses.map((warehouse) => [
                                warehouse.id,
                                `${warehouse.name} (${warehouse.code})`,
                            ])}
                        />
                        <SelectField
                            label="Product"
                            name="product_id"
                            options={products.map((product) => [
                                product.id,
                                product.sku
                                    ? `${product.sku} · ${product.name}`
                                    : product.name,
                            ])}
                        />
                        <NumberField
                            label="Reorder point"
                            name="reorder_point"
                            value="0"
                        />
                        <NumberField label="Target stock" name="target_stock" />
                        <Button className="self-end">
                            <Plus /> Add policy
                        </Button>
                    </Form>
                </details>
                <section className="grid gap-4">
                    <h2 className="font-semibold">Policies</h2>
                    {policies.map((policy) => (
                        <div
                            key={policy.id}
                            className="flex flex-wrap items-end gap-3 border p-4"
                        >
                            <Form
                                action={`/replenishment/policies/${policy.id}`}
                                method="patch"
                                className="grid flex-1 gap-3 md:grid-cols-5"
                            >
                                <input
                                    type="hidden"
                                    name="warehouse_id"
                                    value={policy.warehouse_id}
                                />
                                <input
                                    type="hidden"
                                    name="product_id"
                                    value={policy.product_id}
                                />
                                <div className="self-center">
                                    <div className="font-medium">
                                        {policy.product.name}
                                    </div>
                                    <div className="text-xs text-muted-foreground">
                                        {policy.warehouse.name}
                                    </div>
                                </div>
                                <NumberField
                                    label="Reorder point"
                                    name="reorder_point"
                                    value={policy.reorder_point}
                                />
                                <NumberField
                                    label="Target stock"
                                    name="target_stock"
                                    value={policy.target_stock}
                                />
                                <label className="flex items-center gap-2 self-end pb-2 text-sm">
                                    <input
                                        type="hidden"
                                        name="is_active"
                                        value="0"
                                    />
                                    <input
                                        type="checkbox"
                                        name="is_active"
                                        value="1"
                                        defaultChecked={policy.is_active}
                                    />
                                    Active
                                </label>
                                <Button variant="outline" className="self-end">
                                    Save
                                </Button>
                            </Form>
                            <Form
                                action={`/replenishment/policies/${policy.id}`}
                                method="delete"
                            >
                                <Button size="icon" variant="ghost">
                                    <Trash2 />
                                </Button>
                            </Form>
                        </div>
                    ))}
                </section>
            </div>
        </>
    );
}

function Metric({ label, value }: { label: string; value: number }) {
    return (
        <div className="border-r p-4 last:border-0">
            <div className="text-sm text-muted-foreground">{label}</div>
            <div className="mt-1 text-2xl font-semibold">{value}</div>
        </div>
    );
}
function SelectField({
    label,
    name,
    options,
}: {
    label: string;
    name: string;
    options: [number, string][];
}) {
    return (
        <label className="grid gap-2 text-sm font-medium">
            {label}
            <select
                name={name}
                required
                className="h-9 border bg-transparent px-3 text-sm"
            >
                {options.map(([value, text]) => (
                    <option key={value} value={value}>
                        {text}
                    </option>
                ))}
            </select>
        </label>
    );
}
function NumberField({
    label,
    name,
    value,
}: {
    label: string;
    name: string;
    value?: string;
}) {
    return (
        <label className="grid gap-2 text-sm font-medium">
            {label}
            <Input
                name={name}
                type="number"
                min="0"
                step="0.001"
                defaultValue={value}
                required
            />
        </label>
    );
}
const number = (value: number) =>
    new Intl.NumberFormat(undefined, { maximumFractionDigits: 3 }).format(
        value,
    );
const money = (value: number) =>
    new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: 'EUR',
    }).format(value);

ReplenishmentIndex.layout = {
    breadcrumbs: [
        { title: 'Supply', href: '/purchases' },
        { title: 'Replenishment', href: '/replenishment' },
    ],
};

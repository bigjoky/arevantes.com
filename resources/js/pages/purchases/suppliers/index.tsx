import { Head, Link } from '@inertiajs/react';
import { Building2 } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

type Scorecard = {
    id: number;
    name: string;
    legal_name: string;
    is_active: boolean;
    catalog_items: number;
    orders: number;
    annual_spend: number;
    delivery_score: number | null;
    quality_score: number | null;
    matching_score: number | null;
    overall_score: number | null;
    on_time_deliveries: number;
    measured_deliveries: number;
    average_delay_days: number | null;
    return_rate: number | null;
    rejection_rate: number | null;
    open_quality_incidents: number;
};

export default function SupplierIntelligenceIndex({
    suppliers,
}: {
    suppliers: Scorecard[];
}) {
    return (
        <>
            <Head title="Supplier intelligence" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <header>
                    <h1 className="text-xl font-semibold">
                        Supplier intelligence
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Delivery, quality, invoice matching and purchasing
                        history.
                    </p>
                </header>
                <section className="overflow-x-auto">
                    <table className="w-full min-w-[64rem] text-sm">
                        <thead>
                            <tr className="border-b text-left text-muted-foreground">
                                <th className="py-2">Supplier</th>
                                <th>Overall</th>
                                <th>Delivery</th>
                                <th>Quality</th>
                                <th>Matching</th>
                                <th>Quality incidents</th>
                                <th>Catalog</th>
                                <th>12-month spend</th>
                                <th />
                            </tr>
                        </thead>
                        <tbody>
                            {suppliers.map((supplier) => (
                                <tr key={supplier.id} className="border-b">
                                    <td className="py-3">
                                        <div className="font-medium">
                                            {supplier.name}
                                        </div>
                                        {supplier.name !==
                                            supplier.legal_name && (
                                            <div className="text-xs text-muted-foreground">
                                                {supplier.legal_name}
                                            </div>
                                        )}
                                    </td>
                                    <td>
                                        <Score value={supplier.overall_score} />
                                    </td>
                                    <td>
                                        <Score
                                            value={supplier.delivery_score}
                                        />
                                        <div className="text-xs text-muted-foreground">
                                            {supplier.on_time_deliveries}/
                                            {supplier.measured_deliveries} on
                                            time
                                        </div>
                                    </td>
                                    <td>
                                        <Score value={supplier.quality_score} />
                                    </td>
                                    <td>
                                        <Score
                                            value={supplier.matching_score}
                                        />
                                    </td>
                                    <td>
                                        <div>
                                            {supplier.open_quality_incidents}{' '}
                                            open
                                        </div>
                                        <div className="text-xs text-muted-foreground">
                                            {supplier.rejection_rate === null
                                                ? 'No inspections'
                                                : `${supplier.rejection_rate}% rejected`}
                                        </div>
                                    </td>
                                    <td>{supplier.catalog_items}</td>
                                    <td>{money(supplier.annual_spend)}</td>
                                    <td className="text-right">
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            asChild
                                        >
                                            <Link
                                                href={`/supplier-intelligence/${supplier.id}`}
                                            >
                                                View
                                            </Link>
                                        </Button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    {suppliers.length === 0 && (
                        <div className="flex flex-col items-center gap-2 py-12 text-sm text-muted-foreground">
                            <Building2 className="size-6" />
                            No suppliers yet.
                        </div>
                    )}
                </section>
            </div>
        </>
    );
}

function Score({ value }: { value: number | null }) {
    if (value === null) {
        return <span>—</span>;
    }

    const variant =
        value >= 90 ? 'secondary' : value >= 70 ? 'outline' : 'destructive';

    return <Badge variant={variant}>{value}/100</Badge>;
}

const money = (value: number) =>
    new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: 'EUR',
    }).format(value);

SupplierIntelligenceIndex.layout = {
    breadcrumbs: [
        { title: 'Supply', href: '/purchases' },
        { title: 'Supplier intelligence', href: '/supplier-intelligence' },
    ],
};

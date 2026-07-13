import { Form, Head } from '@inertiajs/react';
import { Check, Download, X } from 'lucide-react';
import BrandLogo from '@/components/brand-logo';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
type Line = {
    id: number;
    description: string;
    quantity: string;
    unit_price: string;
    discount_percent: string;
    tax_percent: string;
    total: string;
};
type Document = {
    number: string;
    status: string;
    issue_date: string;
    valid_until?: string | null;
    due_date?: string | null;
    subtotal: string;
    discount_amount: string;
    tax_amount: string;
    amount: string;
    notes: string | null;
    customer_snapshot: Record<string, string | null> | null;
    lines: Line[];
};
export default function SharedDocument({
    company,
    document,
    kind,
    token,
    canRespond,
    deliveryStatus,
}: {
    company: {
        legal_name: string;
        trade_name: string | null;
        email: string | null;
        phone: string | null;
    } | null;
    document: Document;
    kind: string;
    token: string;
    canRespond: boolean;
    deliveryStatus: string;
}) {
    const snapshot = document.customer_snapshot || {};
    const customer =
        snapshot.company_name ||
        `${snapshot.first_name || ''} ${snapshot.last_name || ''}`.trim();

    return (
        <>
            <Head title={document.number} />
            <main className="min-h-screen bg-background text-foreground">
                <header className="border-b">
                    <div className="mx-auto flex max-w-5xl items-center justify-between gap-4 px-4 py-5">
                        <div className="flex items-center gap-3">
                            <BrandLogo className="h-14" />
                            <div className="text-sm text-muted-foreground">
                                <span className="sr-only">
                                    {company?.trade_name ||
                                        company?.legal_name ||
                                        'Arevantes'}
                                </span>
                                {company?.email}
                            </div>
                        </div>
                        <Button variant="outline" asChild>
                            <a
                                href={`/documents/shared/${token}/pdf`}
                                target="_blank"
                                rel="noreferrer"
                            >
                                <Download /> PDF
                            </a>
                        </Button>
                    </div>
                </header>
                <div className="mx-auto grid max-w-5xl gap-7 px-4 py-8">
                    <section className="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <p className="text-sm text-muted-foreground capitalize">
                                {kind.replaceAll('_', ' ')}
                            </p>
                            <h1 className="text-2xl font-semibold">
                                {document.number}
                            </h1>
                            <p className="mt-1 text-sm text-muted-foreground">
                                {customer}
                            </p>
                        </div>
                        <Badge variant="outline">{document.status}</Badge>
                    </section>
                    <section className="grid grid-cols-2 gap-4 border-y py-4 text-sm md:grid-cols-4">
                        <Info
                            label="Issue date"
                            value={date(document.issue_date)}
                        />
                        <Info
                            label="Valid until"
                            value={
                                document.valid_until
                                    ? date(document.valid_until)
                                    : '—'
                            }
                        />
                        <Info label="Status" value={deliveryStatus} />
                        <Info label="Total" value={money(document.amount)} />
                    </section>
                    <section className="overflow-x-auto">
                        <table className="w-full min-w-[42rem] text-sm">
                            <thead>
                                <tr className="border-b text-left text-muted-foreground">
                                    <th className="py-2">Description</th>
                                    <th>Quantity</th>
                                    <th>Price</th>
                                    <th>Discount</th>
                                    <th>Tax</th>
                                    <th className="text-right">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                {document.lines.map((line) => (
                                    <tr key={line.id} className="border-b">
                                        <td className="py-3 font-medium">
                                            {line.description}
                                        </td>
                                        <td>{Number(line.quantity)}</td>
                                        <td>{money(line.unit_price)}</td>
                                        <td>{line.discount_percent}%</td>
                                        <td>{line.tax_percent}%</td>
                                        <td className="text-right font-medium">
                                            {money(line.total)}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </section>
                    <section className="ml-auto w-full max-w-sm border-t pt-3 text-sm">
                        <Total label="Subtotal" value={document.subtotal} />
                        <Total
                            label="Discount"
                            value={`-${document.discount_amount}`}
                        />
                        <Total label="Tax" value={document.tax_amount} />
                        <Total label="Total" value={document.amount} strong />
                    </section>
                    {document.notes && (
                        <section className="border-t pt-4">
                            <h2 className="font-medium">Notes</h2>
                            <p className="mt-2 text-sm whitespace-pre-wrap text-muted-foreground">
                                {document.notes}
                            </p>
                        </section>
                    )}
                    {canRespond && (
                        <section className="flex flex-wrap justify-end gap-3 border-t pt-5">
                            <Form
                                action={`/documents/shared/${token}/respond`}
                                method="post"
                            >
                                <input
                                    type="hidden"
                                    name="response"
                                    value="rejected"
                                />
                                <Button variant="outline">
                                    <X /> Reject
                                </Button>
                            </Form>
                            <Form
                                action={`/documents/shared/${token}/respond`}
                                method="post"
                            >
                                <input
                                    type="hidden"
                                    name="response"
                                    value="accepted"
                                />
                                <Button>
                                    <Check /> Accept quote
                                </Button>
                            </Form>
                        </section>
                    )}
                    {!canRespond && kind === 'quote' && (
                        <p className="border-t pt-5 text-right text-sm text-muted-foreground">
                            Response recorded: {deliveryStatus}
                        </p>
                    )}
                </div>
            </main>
        </>
    );
}
function Info({ label, value }: { label: string; value: string }) {
    return (
        <div>
            <div className="text-muted-foreground">{label}</div>
            <div className="mt-1 font-medium capitalize">{value}</div>
        </div>
    );
}
function Total({
    label,
    value,
    strong = false,
}: {
    label: string;
    value: string;
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
const money = (v: string) =>
    new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: 'EUR',
    }).format(Number(v));
const date = (v: string) =>
    new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(
        new Date(`${v.slice(0, 10)}T12:00:00`),
    );

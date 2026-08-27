import { Form, Head } from '@inertiajs/react';
import { Check, Download, X } from 'lucide-react';
import { useState } from 'react';
import BrandLogo from '@/components/brand-logo';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
type Line = {
    id: number;
    parent_line_id: number | null;
    depth: number;
    description: string;
    quantity: string;
    unit_price: string;
    discount_percent: string;
    tax_percent: string;
    total: string;
    selection_mode: 'included' | 'optional' | 'alternative';
    option_group: string | null;
    is_selected: boolean;
    components?: Array<{
        id: number;
        description: string;
        quantity: string;
        visibility: 'detailed' | 'summary' | 'hidden';
        unit: { symbol: string } | null;
    }>;
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
    documentVersion,
    documentHash,
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
    documentVersion: string;
    documentHash: string;
}) {
    const snapshot = document.customer_snapshot || {};
    const customer =
        snapshot.company_name ||
        `${snapshot.first_name || ''} ${snapshot.last_name || ''}`.trim();
    const [selectedLineIds, setSelectedLineIds] = useState<number[]>(
        document.lines
            .filter(
                (line) =>
                    line.selection_mode !== 'included' && line.is_selected,
            )
            .map((line) => line.id),
    );
    const previewTotals = selectionTotals(document.lines, selectedLineIds);

    return (
        <>
            <Head title={document.number} />
            <main className="min-h-screen max-w-full overflow-x-hidden bg-background text-foreground">
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
                <div className="mx-auto grid max-w-5xl min-w-0 gap-7 px-4 py-8">
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
                        <Info
                            label="Total"
                            value={money(previewTotals.total)}
                        />
                    </section>
                    <section className="max-w-full overflow-x-auto overscroll-x-contain">
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
                                        <td className="py-3">
                                            <div
                                                className="font-medium"
                                                style={{
                                                    paddingLeft: `${Math.max(0, (line.depth || 1) - 1) * 1.25}rem`,
                                                }}
                                            >
                                                {line.depth > 1 && (
                                                    <span className="mr-2 text-xs font-normal text-muted-foreground">
                                                        Nivel {line.depth}
                                                    </span>
                                                )}
                                                {line.description}
                                            </div>
                                            {line.selection_mode !==
                                                'included' && (
                                                <label className="mt-2 flex cursor-pointer items-center gap-2 text-xs font-medium">
                                                    <input
                                                        type={
                                                            line.selection_mode ===
                                                            'alternative'
                                                                ? 'radio'
                                                                : 'checkbox'
                                                        }
                                                        name={
                                                            line.selection_mode ===
                                                            'alternative'
                                                                ? `alternative-${line.option_group || line.id}`
                                                                : undefined
                                                        }
                                                        checked={selectedLineIds.includes(
                                                            line.id,
                                                        )}
                                                        onChange={() => {
                                                            if (
                                                                line.selection_mode ===
                                                                'alternative'
                                                            ) {
                                                                const groupIds =
                                                                    document.lines
                                                                        .filter(
                                                                            (
                                                                                item,
                                                                            ) =>
                                                                                item.selection_mode ===
                                                                                    'alternative' &&
                                                                                item.option_group ===
                                                                                    line.option_group,
                                                                        )
                                                                        .map(
                                                                            (
                                                                                item,
                                                                            ) =>
                                                                                item.id,
                                                                        );
                                                                setSelectedLineIds(
                                                                    [
                                                                        ...selectedLineIds.filter(
                                                                            (
                                                                                id,
                                                                            ) =>
                                                                                !groupIds.includes(
                                                                                    id,
                                                                                ),
                                                                        ),
                                                                        line.id,
                                                                    ],
                                                                );
                                                            } else {
                                                                setSelectedLineIds(
                                                                    selectedLineIds.includes(
                                                                        line.id,
                                                                    )
                                                                        ? selectedLineIds.filter(
                                                                              (
                                                                                  id,
                                                                              ) =>
                                                                                  id !==
                                                                                  line.id,
                                                                          )
                                                                        : [
                                                                              ...selectedLineIds,
                                                                              line.id,
                                                                          ],
                                                                );
                                                            }
                                                        }}
                                                    />
                                                    {line.selection_mode ===
                                                    'optional'
                                                        ? 'Añadir esta opción'
                                                        : `Elegir alternativa · ${line.option_group}`}
                                                </label>
                                            )}
                                            {kind === 'quote' &&
                                                line.components?.some(
                                                    (component) =>
                                                        component.visibility !==
                                                        'hidden',
                                                ) && (
                                                    <ul className="mt-2 grid gap-1 border-l-2 pl-3 text-xs text-muted-foreground">
                                                        {line.components
                                                            .filter(
                                                                (component) =>
                                                                    component.visibility !==
                                                                    'hidden',
                                                            )
                                                            .map(
                                                                (component) => (
                                                                    <li
                                                                        key={
                                                                            component.id
                                                                        }
                                                                        className="break-words"
                                                                    >
                                                                        {component.visibility ===
                                                                        'detailed'
                                                                            ? `${Number(component.quantity) * Number(line.quantity)}${component.unit?.symbol ? ` ${component.unit.symbol}` : ''} · `
                                                                            : ''}
                                                                        {
                                                                            component.description
                                                                        }{' '}
                                                                        ·
                                                                        incluido
                                                                    </li>
                                                                ),
                                                            )}
                                                    </ul>
                                                )}
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
                        <Total
                            label="Subtotal"
                            value={previewTotals.subtotal}
                        />
                        <Total
                            label="Discount"
                            value={-previewTotals.discount}
                        />
                        <Total label="Tax" value={previewTotals.tax} />
                        <Total
                            label="Total"
                            value={previewTotals.total}
                            strong
                        />
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
                                <input
                                    type="hidden"
                                    name="expected_version"
                                    value={documentVersion}
                                />
                                <input
                                    type="hidden"
                                    name="expected_hash"
                                    value={documentHash}
                                />
                                <Button variant="outline">
                                    <X /> Reject
                                </Button>
                            </Form>
                            <Form
                                action={`/documents/shared/${token}/respond`}
                                method="post"
                                onSubmit={(event) => {
                                    if (
                                        !window.confirm(
                                            `¿Confirmas la aceptación de ${document.number}, versión ${documentVersion}?`,
                                        )
                                    ) {
                                        event.preventDefault();
                                    }
                                }}
                            >
                                <input
                                    type="hidden"
                                    name="response"
                                    value="accepted"
                                />
                                <input
                                    type="hidden"
                                    name="expected_version"
                                    value={documentVersion}
                                />
                                <input
                                    type="hidden"
                                    name="expected_hash"
                                    value={documentHash}
                                />
                                {selectedLineIds.map((lineId) => (
                                    <input
                                        key={lineId}
                                        type="hidden"
                                        name="selected_line_ids[]"
                                        value={lineId}
                                    />
                                ))}
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
    value: string | number;
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
const money = (v: string | number) =>
    new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: 'EUR',
    }).format(Number(v));
function selectionTotals(lines: Line[], selectedLineIds: number[]) {
    return lines
        .filter(
            (line) =>
                line.selection_mode === 'included' ||
                selectedLineIds.includes(line.id),
        )
        .reduce(
            (sum, line) => {
                const subtotal =
                    Number(line.quantity) * Number(line.unit_price);
                const discount =
                    (subtotal * Number(line.discount_percent)) / 100;
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
const date = (v: string) =>
    new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(
        new Date(`${v.slice(0, 10)}T12:00:00`),
    );

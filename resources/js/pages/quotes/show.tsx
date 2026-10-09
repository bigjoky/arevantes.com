import { Form, Head, Link, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    CircleDollarSign,
    FileDown,
    Pencil,
    Send,
    ShieldCheck,
    Trash2,
    Wrench,
} from 'lucide-react';
import { useState } from 'react';
import { FormErrorSummary } from '@/components/forms/form-layout';
import HistoricalDocumentDate from '@/components/historical-document-date';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
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
import { Textarea } from '@/components/ui/textarea';
import { useLocale } from '@/hooks/use-locale';
import { translate } from '@/i18n';
import type { ServiceType } from '@/lib/business-lines';

type Quote = {
    id: number;
    number: string;
    service_type: ServiceType | null;
    version: number;
    status: string;
    approval_status: string;
    approval_notes: string | null;
    approved_at: string | null;
    acceptance_payment_percentage: string;
    acceptance_payment_due_days: number;
    financial_status: 'not_required' | 'pending' | 'partial' | 'cleared';
    financially_cleared_at: string | null;
    issue_date: string;
    valid_until: string | null;
    subtotal: string;
    discount_amount: string;
    tax_amount: string;
    amount: string;
    expected_margin_amount: string | null;
    actual_margin_amount: string | null;
    notes: string | null;
    customer: {
        first_name: string;
        last_name: string | null;
        company_name: string | null;
        email: string | null;
        billing_email: string | null;
        address_line_1: string | null;
        address_line_2: string | null;
        city: string | null;
        locality: string | null;
        province_state: string | null;
        postal_code: string | null;
        country: string | null;
    } | null;
    installation: {
        id: number;
        project_reference: string | null;
        site_name: string | null;
    } | null;
    opportunity: {
        id: number;
        code: string;
        title: string;
    } | null;
    creator: { id: number; name: string } | null;
    approver: { id: number; name: string } | null;
    lines: {
        id: number;
        parent_line_id: number | null;
        depth: number;
        description: string;
        quantity: string;
        unit_price: string;
        discount_percent: string;
        tax_percent: string;
        subtotal: string;
        total: string;
        selection_mode: 'included' | 'optional' | 'alternative';
        option_group: string | null;
        is_selected: boolean;
        components: Array<{
            id: number;
            parent_component_id: number | null;
            depth: number;
            description: string;
            quantity: string;
            kind: string;
            fulfillment_kind: string;
            visibility: string;
            unit: { symbol: string } | null;
        }>;
    }[];
    deliveries: {
        id: number;
        recipient_email: string;
        status: string;
        sent_at: string | null;
        first_viewed_at: string | null;
        responded_at: string | null;
        revoked_at: string | null;
    }[];
    versions: Array<{
        id: number;
        version: number;
        subtotal: string;
        discount_amount: string;
        tax_amount: string;
        amount: string;
        expected_margin_amount: string | null;
        change_note: string | null;
        created_at: string;
        created_by: { id: number; name: string } | null;
    }>;
    status_history: Array<{
        id: number;
        from_status: string | null;
        to_status: string;
        channel: string;
        reason: string | null;
        changed_at: string;
        changed_by: { id: number; name: string } | null;
    }>;
};

const quoteStatuses = [
    ['draft', 'Borrador'],
    ['sent', 'Enviada'],
    ['accepted', 'Aceptada'],
    ['rejected', 'Rechazada'],
    ['expired', 'Caducada'],
] as const;
type ProjectDefaults = {
    service_type: ServiceType | null;
    site_name: string | null;
    site_address_line_1: string | null;
    site_street_number: string | null;
    site_address_line_2: string | null;
    site_city: string | null;
    site_locality: string | null;
    site_province_state: string | null;
    site_postal_code: string | null;
    site_country: string;
};
type BillingPlan = {
    allocated_percentage: number;
    issued_percentage: number;
    remaining_percentage: number;
    allocated_amount: number;
    issued_amount: number;
    remaining_amount: number;
    documents: Array<{
        id: number;
        number: string;
        status: string;
        billing_percentage: number;
        billing_sequence: number | null;
        billing_label: string | null;
        is_acceptance_invoice: boolean;
        amount: number;
        paid_amount: number;
        issue_date: string;
        due_date: string | null;
    }>;
};
type Profitability = {
    planned_revenue: number;
    actual_revenue: number;
    planned_cost: number;
    actual_cost: number;
    planned_margin: number;
    actual_margin: number;
    margin_variance: number;
    has_actuals: boolean;
};
type MaterialPreview = {
    items: number;
    shortage_items: number;
    shortage_quantity: number;
    rows: {
        product_id: number;
        sku: string | null;
        name: string;
        required_quantity: number;
        available_quantity: number;
        shortage_quantity: number;
    }[];
};
type QuoteCapabilities = {
    edit: boolean;
    changeStatus: boolean;
    send: boolean;
    delete: boolean;
    approve: boolean;
    exportWithoutWatermark: boolean;
    startProject: boolean;
    convert: boolean;
    approvalBlockReason: string | null;
};

export default function QuoteShow({
    quote,
    billing,
    profitability,
    materialPreview,
    technicians,
    projectDefaults,
    capabilities,
    availableStatuses,
}: {
    quote: Quote;
    billing: BillingPlan;
    profitability: Profitability | null;
    materialPreview: MaterialPreview;
    technicians: Array<{ id: number; name: string }>;
    projectDefaults: ProjectDefaults;
    capabilities: QuoteCapabilities;
    availableStatuses: string[];
}) {
    const { locale } = useLocale();
    const [projectOpen, setProjectOpen] = useState(false);
    const [billingOpen, setBillingOpen] = useState(false);
    const [billingPercentage, setBillingPercentage] = useState(
        String(billing.remaining_percentage),
    );
    const [billingAmount, setBillingAmount] = useState(
        String(billing.remaining_amount),
    );
    const [billingLabel, setBillingLabel] = useState('Factura final');
    const projectForm = useForm({
        service_type:
            quote.service_type || projectDefaults.service_type || 'solar',
        technician_user_id: '',
        scheduled_at: tomorrowMorning(),
        site_name: projectDefaults.site_name || '',
        site_address_line_1: projectDefaults.site_address_line_1 || '',
        site_street_number: projectDefaults.site_street_number || '',
        site_address_line_2: projectDefaults.site_address_line_2 || '',
        site_city: projectDefaults.site_city || '',
        site_locality: projectDefaults.site_locality || '',
        site_province_state: projectDefaults.site_province_state || '',
        site_postal_code: projectDefaults.site_postal_code || '',
        site_country: projectDefaults.site_country || 'Spain',
    });
    const customer = quote.customer
        ? quote.customer.company_name ||
          `${quote.customer.first_name} ${quote.customer.last_name || ''}`.trim()
        : 'Legacy quote without customer';

    return (
        <>
            <Head title={quote.number} />
            <div className="flex min-w-0 flex-1 flex-col gap-6 overflow-x-hidden p-4 md:p-6">
                <header className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <div className="flex items-center gap-2">
                            <h1 className="text-xl font-semibold">
                                {quote.number}
                            </h1>
                            <Badge variant="outline">
                                {quoteStatusLabel(quote.status)}
                            </Badge>
                        </div>
                        <p className="text-sm text-muted-foreground">
                            {customer} · version {quote.version}
                        </p>
                        {quote.opportunity && (
                            <Link
                                href={`/opportunities/${quote.opportunity.id}`}
                                className="text-xs text-muted-foreground underline-offset-4 hover:underline"
                            >
                                {quote.opportunity.code} ·{' '}
                                {quote.opportunity.title}
                            </Link>
                        )}
                    </div>
                    <div className="flex flex-wrap justify-end gap-2">
                        <Button variant="outline" asChild>
                            <Link href="/quotes">
                                <ArrowLeft /> Propuestas
                            </Link>
                        </Button>
                        <Button variant="outline" asChild>
                            <a
                                href={`/quotes/${quote.id}/pdf`}
                                target="_blank"
                                rel="noreferrer"
                            >
                                <FileDown /> PDF
                            </a>
                        </Button>
                        {capabilities.exportWithoutWatermark && (
                            <Button variant="outline" asChild>
                                <a
                                    href={`/quotes/${quote.id}/pdf/without-watermark`}
                                >
                                    <ShieldCheck />
                                    {translate('PDF without watermark', locale)}
                                </a>
                            </Button>
                        )}
                        {capabilities.edit && (
                            <Button asChild>
                                <Link href={`/quotes/${quote.id}/edit`}>
                                    <Pencil /> Editar
                                </Link>
                            </Button>
                        )}
                    </div>
                </header>
                {quote.status === 'draft' && (
                    <section className="rounded-lg border border-dashed bg-muted/30 p-4">
                        <div className="font-medium">
                            Propuesta guardada como borrador
                        </div>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Aún no es visible para el cliente. Puedes seguir
                            editándola; cuando esté lista, envíala
                            definitivamente desde el bloque de envío al final de
                            esta ficha.
                        </p>
                    </section>
                )}
                {materialPreview.items > 0 && (
                    <section
                        className={`rounded-lg border-l-4 p-4 ${materialPreview.shortage_items > 0 ? 'border-l-amber-500 bg-amber-50 dark:bg-amber-950/20' : 'border-l-green-600 bg-green-50 dark:bg-green-950/20'}`}
                    >
                        <div className="font-medium">
                            Material feasibility before planning
                        </div>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {materialPreview.shortage_items > 0
                                ? `${materialPreview.shortage_items} catalog item(s) · ${materialPreview.shortage_quantity} units currently unavailable`
                                : 'The complete quoted kit is currently available.'}
                        </p>
                        {materialPreview.shortage_items > 0 && (
                            <div className="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs">
                                {materialPreview.rows
                                    .filter((row) => row.shortage_quantity > 0)
                                    .map((row) => (
                                        <span key={row.product_id}>
                                            {row.sku || row.name}:{' '}
                                            {row.shortage_quantity} short
                                        </span>
                                    ))}
                            </div>
                        )}
                    </section>
                )}
                <section className="rounded-lg border bg-card p-4 shadow-xs">
                    {capabilities.changeStatus ? (
                        <Form
                            action={`/quotes/${quote.id}/status`}
                            method="patch"
                            className="flex flex-wrap items-end justify-between gap-3"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <div className="w-full">
                                        <FormErrorSummary errors={errors} />
                                    </div>
                                    <div className="grid w-full gap-2 sm:max-w-md">
                                        <Label htmlFor="quote_status">
                                            Estado de la oferta
                                        </Label>
                                        <select
                                            id="quote_status"
                                            name="status"
                                            defaultValue={quote.status}
                                            className="h-11 w-full rounded-md border border-input bg-background px-3 text-base font-medium shadow-xs"
                                        >
                                            <option value={quote.status}>
                                                {quoteStatusLabel(quote.status)}
                                            </option>
                                            {quoteStatuses
                                                .filter(([value]) =>
                                                    availableStatuses.includes(
                                                        value,
                                                    ),
                                                )
                                                .map(([value, label]) => (
                                                    <option
                                                        key={value}
                                                        value={value}
                                                    >
                                                        {label}
                                                    </option>
                                                ))}
                                        </select>
                                    </div>
                                    <Button
                                        disabled={processing}
                                        className="w-full sm:w-auto"
                                    >
                                        Actualizar estado
                                    </Button>
                                </>
                            )}
                        </Form>
                    ) : (
                        <div>
                            <Label>Estado de la oferta</Label>
                            <p className="mt-2 text-base font-medium">
                                {quoteStatusLabel(quote.status)}
                            </p>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Este es un estado final y no admite cambios
                                directos.
                            </p>
                        </div>
                    )}
                </section>
                <section className="grid gap-4 border-y py-4 text-sm sm:grid-cols-2 lg:grid-cols-4">
                    <Info label="Customer" value={customer} />
                    <Info
                        label="Installation"
                        value={
                            quote.installation?.project_reference ||
                            quote.installation?.site_name ||
                            'Not linked'
                        }
                    />
                    <Info label="Issue date" value={date(quote.issue_date)} />
                    <Info label="Valid until" value={date(quote.valid_until)} />
                </section>
                {(quote.status === 'accepted' ||
                    quote.financial_status !== 'not_required') && (
                    <section className="grid gap-4 rounded-md border p-4 text-sm md:grid-cols-4">
                        <div className="flex flex-wrap items-center gap-2 md:col-span-4">
                            <h2 className="font-semibold">
                                Financial activation
                            </h2>
                            <Badge variant="outline">
                                {financialStatusLabel(quote.financial_status)}
                            </Badge>
                        </div>
                        <Info
                            label="Acceptance payment"
                            value={`${quote.acceptance_payment_percentage}%`}
                        />
                        <Info
                            label="Payment term"
                            value={`${quote.acceptance_payment_due_days} days`}
                        />
                        <Info
                            label="Acceptance invoice"
                            value={
                                billing.documents.find(
                                    (document) =>
                                        document.is_acceptance_invoice,
                                )?.number || 'Not issued'
                            }
                        />
                        <Info
                            label="Financial clearance"
                            value={
                                quote.financially_cleared_at
                                    ? date(quote.financially_cleared_at)
                                    : 'Pending bank confirmation'
                            }
                        />
                    </section>
                )}
                <section className="grid gap-4 rounded-md border p-4 lg:grid-cols-[1fr_1fr]">
                    <div>
                        <div className="flex flex-wrap items-center gap-2">
                            <h2 className="font-semibold">
                                Commercial control
                            </h2>
                            <Badge variant="outline">
                                Approval: {quote.approval_status}
                            </Badge>
                        </div>
                        <div className="mt-3 grid grid-cols-2 gap-3 text-sm">
                            <Info
                                label="Creada por"
                                value={quote.creator?.name || 'Sistema'}
                            />
                            <Info
                                label="Aprobada por"
                                value={
                                    quote.approver
                                        ? `${quote.approver.name}${quote.approved_at ? ` · ${dateTime(quote.approved_at)}` : ''}`
                                        : 'Sin decisión registrada'
                                }
                            />
                            <Info
                                label="Expected margin"
                                value={
                                    quote.expected_margin_amount
                                        ? money(quote.expected_margin_amount)
                                        : '—'
                                }
                            />
                            <Info
                                label="Margen real actualizado"
                                value={
                                    quote.actual_margin_amount
                                        ? money(quote.actual_margin_amount)
                                        : 'Pendiente de ingresos o costes'
                                }
                            />
                        </div>
                        {profitability && (
                            <div className="mt-3 grid gap-2 rounded-md bg-muted/40 p-3 text-xs sm:grid-cols-3">
                                <Info
                                    label="Ingreso previsto (sin IVA)"
                                    value={money(profitability.planned_revenue)}
                                />
                                <Info
                                    label="Ingreso real facturado"
                                    value={money(profitability.actual_revenue)}
                                />
                                <Info
                                    label="Coste real registrado"
                                    value={money(profitability.actual_cost)}
                                />
                                <Info
                                    label="Coste previsto"
                                    value={money(profitability.planned_cost)}
                                />
                                <Info
                                    label="Desviación de margen"
                                    value={
                                        profitability.has_actuals
                                            ? money(
                                                  profitability.margin_variance,
                                              )
                                            : 'Pendiente'
                                    }
                                />
                                <Info
                                    label="Origen"
                                    value="Facturas, materiales y horas"
                                />
                            </div>
                        )}
                        {quote.approval_status === 'pending' &&
                            capabilities.approve && (
                                <Form
                                    action={`/quotes/${quote.id}/approval`}
                                    method="post"
                                    className="mt-4 grid gap-2"
                                >
                                    <Textarea
                                        name="approval_notes"
                                        placeholder="Approval notes"
                                    />
                                    <div className="flex gap-2">
                                        <Button
                                            name="decision"
                                            value="approved"
                                        >
                                            Approve
                                        </Button>
                                        <Button
                                            name="decision"
                                            value="rejected"
                                            variant="destructive"
                                        >
                                            Reject
                                        </Button>
                                    </div>
                                </Form>
                            )}
                        {quote.approval_status === 'pending' &&
                            !capabilities.approve && (
                                <p className="mt-3 rounded-md border border-amber-300 bg-amber-50 p-3 text-sm text-amber-950 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-100">
                                    {capabilities.approvalBlockReason}
                                </p>
                            )}
                        {quote.approval_notes && (
                            <p className="mt-3 text-xs text-muted-foreground">
                                {quote.approval_notes}
                            </p>
                        )}
                        {quote.status_history.length > 0 && (
                            <div className="mt-4 border-t pt-3">
                                <div className="text-xs font-medium text-muted-foreground">
                                    Historial de estado
                                </div>
                                <div className="mt-2 grid gap-2">
                                    {quote.status_history
                                        .slice(0, 5)
                                        .map((entry) => (
                                            <div
                                                key={entry.id}
                                                className="flex flex-wrap justify-between gap-2 text-xs"
                                            >
                                                <span>
                                                    {entry.from_status
                                                        ? `${quoteStatusLabel(entry.from_status)} → `
                                                        : ''}
                                                    {quoteStatusLabel(
                                                        entry.to_status,
                                                    )}{' '}
                                                    · {entry.channel}
                                                </span>
                                                <span className="text-muted-foreground">
                                                    {entry.changed_by?.name ||
                                                        'Cliente'}{' '}
                                                    ·{' '}
                                                    {dateTime(entry.changed_at)}
                                                </span>
                                            </div>
                                        ))}
                                </div>
                            </div>
                        )}
                    </div>
                    <div>
                        <h2 className="font-semibold">Version comparison</h2>
                        <div className="mt-2 divide-y border-y text-sm">
                            {quote.versions.map((version) => (
                                <div
                                    key={version.id}
                                    className="grid grid-cols-[auto_1fr_auto] gap-3 py-2"
                                >
                                    <Badge variant="outline">
                                        v{version.version}
                                    </Badge>
                                    <div>
                                        <div>
                                            {dateTime(version.created_at)}
                                        </div>
                                        <div className="text-xs text-muted-foreground">
                                            {version.created_by?.name ||
                                                'Sistema'}
                                            {version.change_note
                                                ? ` · ${version.change_note}`
                                                : ''}
                                        </div>
                                    </div>
                                    <span className="font-medium tabular-nums">
                                        {money(version.amount)}
                                    </span>
                                </div>
                            ))}
                        </div>
                    </div>
                </section>
                <section className="max-w-full overflow-x-auto overscroll-x-contain">
                    <table className="w-full min-w-[48rem] text-sm">
                        <thead>
                            <tr className="border-b text-left text-muted-foreground">
                                <th className="py-2">Description</th>
                                <th>Quantity</th>
                                <th>Unit price</th>
                                <th>Discount</th>
                                <th>Tax</th>
                                <th className="text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            {quote.lines.map((line) => (
                                <tr key={line.id} className="border-b">
                                    <td className="py-3">
                                        <div className="font-medium">
                                            {line.description}
                                        </div>
                                        {line.selection_mode !== 'included' && (
                                            <div className="mt-1 flex flex-wrap gap-1">
                                                <Badge variant="secondary">
                                                    {line.selection_mode ===
                                                    'optional'
                                                        ? 'Opcional'
                                                        : `Alternativa · ${line.option_group}`}
                                                </Badge>
                                                <Badge variant="outline">
                                                    {line.is_selected
                                                        ? 'Seleccionada'
                                                        : 'No incluida'}
                                                </Badge>
                                            </div>
                                        )}
                                        {line.components.length > 0 && (
                                            <ul className="mt-2 grid gap-1 border-l-2 pl-3 text-xs text-muted-foreground">
                                                {line.components.map(
                                                    (component) => (
                                                        <li
                                                            key={component.id}
                                                            className="flex flex-wrap items-center gap-2"
                                                            style={{
                                                                marginLeft: `${Math.max(0, component.depth - 2)}rem`,
                                                            }}
                                                        >
                                                            <span className="text-[10px] font-medium uppercase">
                                                                Nivel{' '}
                                                                {
                                                                    component.depth
                                                                }
                                                            </span>
                                                            <span className="min-w-0 break-words">
                                                                {component.visibility ===
                                                                'hidden'
                                                                    ? 'Interno'
                                                                    : component.visibility ===
                                                                        'detailed'
                                                                      ? `${effectiveComponentQuantity(line, component)}${component.unit?.symbol ? ` ${component.unit.symbol}` : ''}`
                                                                      : 'Incluido'}
                                                                {' · '}
                                                                {
                                                                    component.description
                                                                }
                                                            </span>
                                                            <Badge variant="outline">
                                                                {fulfillmentLabel(
                                                                    component.fulfillment_kind,
                                                                )}
                                                            </Badge>
                                                        </li>
                                                    ),
                                                )}
                                            </ul>
                                        )}
                                    </td>
                                    <td>{Number(line.quantity)}</td>
                                    <td>{money(line.unit_price, 3)}</td>
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
                <section className="grid gap-6 border-t pt-5 lg:grid-cols-[1fr_22rem]">
                    <div>
                        <h2 className="mb-2 font-semibold">Notes</h2>
                        <p className="text-sm whitespace-pre-wrap text-muted-foreground">
                            {quote.notes || 'No notes.'}
                        </p>
                    </div>
                    <div className="grid content-start gap-2 text-sm">
                        <Total label="Subtotal" value={quote.subtotal} />
                        <Total
                            label="Discount"
                            value={`-${quote.discount_amount}`}
                        />
                        <Total label="Tax" value={quote.tax_amount} />
                        <Total label="Total" value={quote.amount} strong />
                    </div>
                </section>
                {(quote.status === 'accepted' ||
                    billing.documents.length > 0) && (
                    <section className="grid gap-5 border-t pt-5 lg:grid-cols-[minmax(18rem,.7fr)_minmax(0,1.3fr)]">
                        <div>
                            <div className="flex items-center justify-between gap-3">
                                <div>
                                    <h2 className="font-semibold">
                                        Billing plan
                                    </h2>
                                    <p className="text-sm text-muted-foreground">
                                        Invoice the accepted quote in controlled
                                        milestones.
                                    </p>
                                </div>
                                {billing.remaining_percentage > 0 &&
                                    quote.status === 'accepted' && (
                                        <Button
                                            type="button"
                                            onClick={() => {
                                                setBillingPercentage(
                                                    String(
                                                        billing.remaining_percentage,
                                                    ),
                                                );
                                                setBillingLabel(
                                                    'Final invoice',
                                                );
                                                setBillingOpen(true);
                                            }}
                                        >
                                            <CircleDollarSign /> Add milestone
                                        </Button>
                                    )}
                            </div>
                            <div className="mt-5 h-2 overflow-hidden rounded-sm bg-muted">
                                <div
                                    className="h-full bg-primary transition-[width]"
                                    style={{
                                        width: `${Math.min(100, billing.allocated_percentage)}%`,
                                    }}
                                />
                            </div>
                            <div className="mt-4 grid grid-cols-3 gap-3 text-sm">
                                <Info
                                    label="Allocated"
                                    value={`${billing.allocated_percentage}%`}
                                />
                                <Info
                                    label="Issued"
                                    value={`${billing.issued_percentage}%`}
                                />
                                <Info
                                    label="Remaining"
                                    value={`${billing.remaining_percentage}%`}
                                />
                            </div>
                            <div className="mt-4 grid grid-cols-2 gap-3 border-t pt-3 text-sm">
                                <Info
                                    label="Allocated amount"
                                    value={money(billing.allocated_amount)}
                                />
                                <Info
                                    label="Issued amount"
                                    value={money(billing.issued_amount)}
                                />
                            </div>
                        </div>
                        <div className="border-y">
                            {billing.documents.map((document) => (
                                <Link
                                    key={document.id}
                                    href={`/sales/${document.id}`}
                                    className="flex items-center justify-between gap-4 border-b py-3 text-sm transition-colors last:border-0 hover:bg-muted/40"
                                >
                                    <div className="min-w-0">
                                        <div className="truncate font-medium">
                                            {document.billing_label ||
                                                `Milestone ${document.billing_sequence}`}
                                        </div>
                                        <div className="text-xs text-muted-foreground">
                                            {document.number} ·{' '}
                                            {document.billing_percentage}%
                                            {document.is_acceptance_invoice
                                                ? ' · Acceptance'
                                                : ''}
                                        </div>
                                    </div>
                                    <div className="text-right">
                                        <div className="font-medium">
                                            {money(document.amount)}
                                        </div>
                                        {document.is_acceptance_invoice && (
                                            <div className="text-xs text-muted-foreground">
                                                Paid{' '}
                                                {money(document.paid_amount)}
                                            </div>
                                        )}
                                        <Badge variant="outline">
                                            {document.status}
                                        </Badge>
                                    </div>
                                </Link>
                            ))}
                            {billing.documents.length === 0 && (
                                <div className="py-10 text-center text-sm text-muted-foreground">
                                    No billing milestones yet.
                                </div>
                            )}
                        </div>
                    </section>
                )}
                <section className="grid gap-6 border-t pt-5 lg:grid-cols-[minmax(20rem,.7fr)_minmax(0,1.3fr)]">
                    {capabilities.send ? (
                        <Form
                            action={`/quotes/${quote.id}/send`}
                            method="post"
                            className="grid content-start gap-3"
                        >
                            <div>
                                <h2 className="font-semibold">
                                    Enviar al cliente
                                </h2>
                                <p className="text-sm text-muted-foreground">
                                    Envía el PDF y un enlace seguro de
                                    aceptación. Después del envío, la propuesta
                                    quedará bloqueada para conservar exactamente
                                    la versión recibida por el cliente.
                                </p>
                            </div>
                            <div className="grid gap-2">
                                <label className="text-sm font-medium">
                                    Correo destinatario
                                </label>
                                <input
                                    name="recipient_email"
                                    type="email"
                                    required
                                    defaultValue={
                                        quote.customer?.billing_email ||
                                        quote.customer?.email ||
                                        ''
                                    }
                                    className="h-9 rounded-md border bg-transparent px-3 text-sm"
                                />
                            </div>
                            <Button className="w-fit">
                                <Send /> Enviar definitivamente
                            </Button>
                        </Form>
                    ) : (
                        <div className="text-sm text-muted-foreground">
                            <h2 className="font-medium text-foreground">
                                Envío al cliente
                            </h2>
                            <p className="mt-1">
                                Disponible únicamente mientras la propuesta siga
                                abierta y tenga la aprobación necesaria.
                            </p>
                        </div>
                    )}
                    <DeliveryHistory deliveries={quote.deliveries} />
                </section>
                <footer className="flex flex-wrap items-end justify-between gap-4 border-t pt-5">
                    {(capabilities.startProject || capabilities.convert) && (
                        <div className="flex flex-wrap gap-2">
                            {capabilities.startProject && (
                                <Button
                                    type="button"
                                    onClick={() => setProjectOpen(true)}
                                >
                                    <Wrench /> Iniciar proyecto
                                </Button>
                            )}
                            {capabilities.convert &&
                                [
                                    ['order', 'Crear pedido'],
                                    ['delivery_note', 'Crear albarán'],
                                ].map(([type, label]) => (
                                    <Form
                                        key={type}
                                        action={`/quotes/${quote.id}/convert`}
                                        method="post"
                                        className="grid gap-3"
                                    >
                                        <HistoricalDocumentDate />
                                        <input
                                            type="hidden"
                                            name="type"
                                            value={type}
                                        />
                                        <Button variant="outline">
                                            {label}
                                        </Button>
                                    </Form>
                                ))}
                        </div>
                    )}
                    {capabilities.delete && (
                        <Form
                            action={`/quotes/${quote.id}`}
                            method="delete"
                            onBefore={() =>
                                confirm(`¿Eliminar ${quote.number}?`)
                            }
                        >
                            <Button variant="destructive">
                                <Trash2 /> Eliminar borrador
                            </Button>
                        </Form>
                    )}
                </footer>
            </div>
            <Dialog open={projectOpen} onOpenChange={setProjectOpen}>
                <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                    <form
                        onSubmit={(event) => {
                            event.preventDefault();
                            projectForm.post(`/quotes/${quote.id}/project`, {
                                onSuccess: () => setProjectOpen(false),
                            });
                        }}
                        className="grid gap-5"
                    >
                        <DialogHeader>
                            <DialogTitle>
                                Start installation project
                            </DialogTitle>
                            <DialogDescription>
                                Create the installation, technical visit and
                                initial work order from this accepted quote.
                            </DialogDescription>
                        </DialogHeader>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <ProjectSelect
                                label="Service"
                                value={projectForm.data.service_type}
                                error={projectForm.errors.service_type}
                                onChange={(value) =>
                                    projectForm.setData(
                                        'service_type',
                                        value as ServiceType,
                                    )
                                }
                                options={[
                                    ['solar', 'Solar photovoltaic'],
                                    ['ev_charger', 'EV charging'],
                                    ['hvac', 'Air conditioning / HVAC'],
                                    ['electrical', 'Electrical installation'],
                                    ['security', 'Alarm & security system'],
                                ]}
                            />
                            <ProjectSelect
                                label="Technician"
                                value={
                                    projectForm.data.technician_user_id ||
                                    'none'
                                }
                                error={projectForm.errors.technician_user_id}
                                onChange={(value) =>
                                    projectForm.setData(
                                        'technician_user_id',
                                        value === 'none' ? '' : value,
                                    )
                                }
                                options={[
                                    ['none', 'Unassigned'],
                                    ...technicians.map((item) => [
                                        String(item.id),
                                        item.name,
                                    ]),
                                ]}
                            />
                            <ProjectField
                                label="Technical visit"
                                error={projectForm.errors.scheduled_at}
                            >
                                <Input
                                    type="datetime-local"
                                    value={projectForm.data.scheduled_at}
                                    onChange={(event) =>
                                        projectForm.setData(
                                            'scheduled_at',
                                            event.target.value,
                                        )
                                    }
                                />
                            </ProjectField>
                            <ProjectField
                                label="Site name"
                                error={projectForm.errors.site_name}
                            >
                                <Input
                                    value={projectForm.data.site_name}
                                    onChange={(event) =>
                                        projectForm.setData(
                                            'site_name',
                                            event.target.value,
                                        )
                                    }
                                />
                            </ProjectField>
                            <ProjectField
                                label="Street"
                                error={projectForm.errors.site_address_line_1}
                            >
                                <Input
                                    value={projectForm.data.site_address_line_1}
                                    onChange={(event) =>
                                        projectForm.setData(
                                            'site_address_line_1',
                                            event.target.value,
                                        )
                                    }
                                />
                            </ProjectField>
                            <ProjectField
                                label="Number"
                                error={projectForm.errors.site_street_number}
                            >
                                <Input
                                    value={projectForm.data.site_street_number}
                                    onChange={(event) =>
                                        projectForm.setData(
                                            'site_street_number',
                                            event.target.value,
                                        )
                                    }
                                />
                            </ProjectField>
                            <ProjectField
                                label="Address 2"
                                error={projectForm.errors.site_address_line_2}
                            >
                                <Input
                                    value={projectForm.data.site_address_line_2}
                                    onChange={(event) =>
                                        projectForm.setData(
                                            'site_address_line_2',
                                            event.target.value,
                                        )
                                    }
                                />
                            </ProjectField>
                            <ProjectField
                                label="Municipality"
                                error={projectForm.errors.site_city}
                            >
                                <Input
                                    value={projectForm.data.site_city}
                                    onChange={(event) =>
                                        projectForm.setData(
                                            'site_city',
                                            event.target.value,
                                        )
                                    }
                                />
                            </ProjectField>
                            <ProjectField
                                label="Locality"
                                error={projectForm.errors.site_locality}
                            >
                                <Input
                                    value={projectForm.data.site_locality}
                                    onChange={(event) =>
                                        projectForm.setData(
                                            'site_locality',
                                            event.target.value,
                                        )
                                    }
                                />
                            </ProjectField>
                            <ProjectField
                                label="Province"
                                error={projectForm.errors.site_province_state}
                            >
                                <Input
                                    value={projectForm.data.site_province_state}
                                    onChange={(event) =>
                                        projectForm.setData(
                                            'site_province_state',
                                            event.target.value,
                                        )
                                    }
                                />
                            </ProjectField>
                            <ProjectField
                                label="Postal code"
                                error={projectForm.errors.site_postal_code}
                            >
                                <Input
                                    value={projectForm.data.site_postal_code}
                                    onChange={(event) =>
                                        projectForm.setData(
                                            'site_postal_code',
                                            event.target.value,
                                        )
                                    }
                                />
                            </ProjectField>
                            <ProjectField
                                label="Country"
                                error={projectForm.errors.site_country}
                            >
                                <Input
                                    value={projectForm.data.site_country}
                                    onChange={(event) =>
                                        projectForm.setData(
                                            'site_country',
                                            event.target.value,
                                        )
                                    }
                                />
                            </ProjectField>
                        </div>
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setProjectOpen(false)}
                            >
                                Cancel
                            </Button>
                            <Button disabled={projectForm.processing}>
                                <Wrench /> Create project
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
            <Dialog open={billingOpen} onOpenChange={setBillingOpen}>
                <DialogContent className="sm:max-w-lg">
                    <Form
                        action={`/quotes/${quote.id}/convert`}
                        method="post"
                        onSuccess={() => setBillingOpen(false)}
                    >
                        {({ errors, processing }) => (
                            <div className="grid gap-5">
                                <DialogHeader>
                                    <DialogTitle>
                                        Añadir hito de cobro
                                    </DialogTitle>
                                    <DialogDescription>
                                        Quedan {billing.remaining_percentage}% (
                                        {money(billing.remaining_amount)}) de la
                                        oferta aceptada por facturar.
                                    </DialogDescription>
                                </DialogHeader>
                                <input
                                    type="hidden"
                                    name="type"
                                    value="invoice"
                                />
                                <HistoricalDocumentDate />
                                <div className="grid grid-cols-3 gap-2">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() => {
                                            setBillingValues(30);
                                            setBillingLabel('Anticipo');
                                        }}
                                    >
                                        Anticipo
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() => {
                                            setBillingValues(40);
                                            setBillingLabel('Pago intermedio');
                                        }}
                                    >
                                        Intermedio
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() => {
                                            setBillingValues(
                                                billing.remaining_percentage,
                                            );
                                            setBillingLabel('Factura final');
                                        }}
                                    >
                                        Final
                                    </Button>
                                </div>
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="billing_percentage">
                                            Porcentaje
                                        </Label>
                                        <div className="relative">
                                            <Input
                                                id="billing_percentage"
                                                name="billing_percentage"
                                                type="number"
                                                min="0.01"
                                                max={
                                                    billing.remaining_percentage
                                                }
                                                step="0.01"
                                                value={billingPercentage}
                                                onChange={(event) =>
                                                    setBillingFromPercentage(
                                                        event.target.value,
                                                    )
                                                }
                                                className="pr-8"
                                                required
                                            />
                                            <span className="pointer-events-none absolute inset-y-0 right-3 flex items-center text-sm text-muted-foreground">
                                                %
                                            </span>
                                        </div>
                                        <InputError
                                            message={errors.billing_percentage}
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="billing_amount">
                                            Importe acordado
                                        </Label>
                                        <div className="relative">
                                            <Input
                                                id="billing_amount"
                                                name="billing_amount"
                                                type="number"
                                                min="0.01"
                                                max={billing.remaining_amount}
                                                step="0.01"
                                                value={billingAmount}
                                                onChange={(event) =>
                                                    setBillingFromAmount(
                                                        event.target.value,
                                                    )
                                                }
                                                className="pr-8"
                                                required
                                            />
                                            <span className="pointer-events-none absolute inset-y-0 right-3 flex items-center text-sm text-muted-foreground">
                                                €
                                            </span>
                                        </div>
                                        <InputError
                                            message={errors.billing_amount}
                                        />
                                    </div>
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="billing_label">
                                        Nombre del hito
                                    </Label>
                                    <Input
                                        id="billing_label"
                                        name="billing_label"
                                        value={billingLabel}
                                        onChange={(event) =>
                                            setBillingLabel(event.target.value)
                                        }
                                        required
                                    />
                                    <InputError
                                        message={errors.billing_label}
                                    />
                                </div>
                                <div className="flex items-center justify-between border-y py-3 text-sm">
                                    <span className="text-muted-foreground">
                                        Importe exacto de la cuota
                                    </span>
                                    <span className="font-semibold">
                                        {money(Number(billingAmount || 0))}
                                    </span>
                                </div>
                                <DialogFooter>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() => setBillingOpen(false)}
                                    >
                                        Cancelar
                                    </Button>
                                    <Button disabled={processing}>
                                        <CircleDollarSign /> Crear borrador
                                    </Button>
                                </DialogFooter>
                            </div>
                        )}
                    </Form>
                </DialogContent>
            </Dialog>
        </>
    );

    function setBillingValues(percentage: number) {
        const bounded = Math.min(percentage, billing.remaining_percentage);
        setBillingPercentage(String(bounded));
        setBillingAmount(
            String(
                bounded === billing.remaining_percentage
                    ? billing.remaining_amount
                    : roundCurrency((Number(quote.amount) * bounded) / 100),
            ),
        );
    }

    function setBillingFromPercentage(value: string) {
        setBillingPercentage(value);
        setBillingAmount(
            value === ''
                ? ''
                : String(
                      Math.min(
                          billing.remaining_amount,
                          roundCurrency(
                              (Number(quote.amount) * Number(value)) / 100,
                          ),
                      ),
                  ),
        );
    }

    function setBillingFromAmount(value: string) {
        setBillingAmount(value);
        setBillingPercentage(
            value === '' || Number(quote.amount) === 0
                ? ''
                : String(
                      Math.round(
                          (Number(value) / Number(quote.amount)) * 10000,
                      ) / 100,
                  ),
        );
    }
}

function ProjectField({
    label,
    error,
    children,
}: {
    label: string;
    error?: string;
    children: React.ReactNode;
}) {
    return (
        <div className="grid gap-2">
            <Label>{label}</Label>
            {children}
            <InputError message={error} />
        </div>
    );
}

function ProjectSelect({
    label,
    value,
    error,
    onChange,
    options,
}: {
    label: string;
    value: string;
    error?: string;
    onChange: (value: string) => void;
    options: string[][];
}) {
    return (
        <ProjectField label={label} error={error}>
            <Select value={value} onValueChange={onChange}>
                <SelectTrigger>
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    {options.map(([key, text]) => (
                        <SelectItem key={key} value={key}>
                            {text}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
        </ProjectField>
    );
}

function tomorrowMorning() {
    const date = new Date();
    date.setDate(date.getDate() + 1);
    date.setHours(9, 0, 0, 0);
    const local = new Date(date.getTime() - date.getTimezoneOffset() * 60000);

    return local.toISOString().slice(0, 16);
}

function financialStatusLabel(status: Quote['financial_status']) {
    return {
        not_required: 'Not required',
        pending: 'Acceptance payment pending',
        partial: 'Partially paid',
        cleared: 'Financially cleared',
    }[status];
}
function DeliveryHistory({ deliveries }: { deliveries: Quote['deliveries'] }) {
    return (
        <div>
            <h2 className="mb-3 font-semibold">Delivery history</h2>
            <div className="border-y">
                {deliveries.map((delivery) => (
                    <div
                        key={delivery.id}
                        className="flex items-center justify-between gap-3 border-b py-3 text-sm last:border-0"
                    >
                        <div>
                            <div className="font-medium">
                                {delivery.recipient_email}
                            </div>
                            <div className="text-xs text-muted-foreground">
                                {delivery.sent_at
                                    ? date(delivery.sent_at)
                                    : 'Not sent'}
                                {delivery.first_viewed_at ? ' · viewed' : ''}
                            </div>
                        </div>
                        <div className="flex items-center gap-2">
                            <Badge variant="outline">{delivery.status}</Badge>
                            {!delivery.revoked_at &&
                                !['revoked', 'superseded', 'failed'].includes(
                                    delivery.status,
                                ) && (
                                    <Form
                                        action={`/document-deliveries/${delivery.id}`}
                                        method="delete"
                                    >
                                        <Button size="sm" variant="outline">
                                            Revoke
                                        </Button>
                                    </Form>
                                )}
                        </div>
                    </div>
                ))}
                {deliveries.length === 0 && (
                    <div className="py-8 text-center text-sm text-muted-foreground">
                        Not sent yet.
                    </div>
                )}
            </div>
        </div>
    );
}
function Info({ label, value }: { label: string; value: string }) {
    return (
        <div>
            <div className="text-muted-foreground">{label}</div>
            <div className="mt-1 font-medium">{value}</div>
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
function money(value: string | number, maximumFractionDigits = 2) {
    return new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: 'EUR',
        maximumFractionDigits,
    }).format(Number(value));
}
function effectiveComponentQuantity(
    line: Quote['lines'][number],
    component: Quote['lines'][number]['components'][number],
) {
    const components = new Map(line.components.map((item) => [item.id, item]));
    let quantity = Number(line.quantity) * Number(component.quantity);
    let parentId = component.parent_component_id;

    while (parentId) {
        const parent = components.get(parentId);

        if (!parent) {
            break;
        }

        quantity *= Number(parent.quantity);
        parentId = parent.parent_component_id;
    }

    return quantity;
}
function quoteStatusLabel(status: string) {
    return quoteStatuses.find(([value]) => value === status)?.[1] || status;
}
function fulfillmentLabel(kind: string) {
    return (
        {
            stock: 'Control de stock',
            activity: 'Seguimiento operativo',
            recurring: 'Servicio recurrente',
            none: 'Solo descriptivo',
            material: 'Control de stock',
            task: 'Seguimiento operativo',
            legalization: 'Seguimiento operativo',
            maintenance: 'Servicio recurrente',
        }[kind] || kind
    );
}
function roundCurrency(value: number) {
    return Math.round((value + Number.EPSILON) * 100) / 100;
}
function date(value: string | null) {
    return value
        ? new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(
              new Date(`${value.slice(0, 10)}T12:00:00`),
          )
        : '—';
}
function dateTime(value: string) {
    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}
QuoteShow.layout = {
    breadcrumbs: [
        { title: 'Propuestas', href: '/quotes' },
        { title: 'Propuesta', href: '#' },
    ],
};

import '../../../css/commercial-mobile.css';
import { Form, Head, Link, useForm, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    CircleDollarSign,
    FileDown,
    Pencil,
    Send,
    Trash2,
    Wrench,
} from 'lucide-react';
import { useState } from 'react';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { FormErrorSummary } from '@/components/forms/form-layout';
import HistoricalDocumentDate from '@/components/historical-document-date';
import InputError from '@/components/input-error';
import { PageShell, PaginationBar } from '@/components/page-shell';
import QuotePaperAcceptance from '@/components/quote-paper-acceptance';
import { quoteUrl, quoteMoney, quoteDate } from '@/components/quotes/types';
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
import { useDocumentCreation } from '@/hooks/use-document-creation';
import { useLocale } from '@/hooks/use-locale';
import { translate } from '@/i18n';
import type { ServiceType } from '@/lib/business-lines';

type Quote = {
    id: number;
    person_id: number | null;
    customer_snapshot: Record<string, string | null> | null;
    number: string;
    service_type: ServiceType | null;
    version: number;
    status: string;
    effective_status?: string;
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
        archived_pdf_url: string | null;
        document_version: string | null;
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
        lines: {
            description: string;
            quantity: string;
            unit_price: string;
            total: number;
        }[];
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
    ['sent', 'Enviado'],
    ['accepted', 'Aceptado'],
    ['rejected', 'Rechazado'],
    ['expired', 'Caducado'],
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
        collect_before_invoice: boolean;
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
    classification_warnings?: string[];
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
    sectionLinks,
    quoteActivity,
    relationPermissions,
}: {
    quote: Quote;
    billing: BillingPlan;
    profitability: Profitability | null;
    materialPreview: MaterialPreview;
    technicians: Array<{ id: number; name: string }>;
    projectDefaults: ProjectDefaults;
    capabilities: QuoteCapabilities;
    availableStatuses: string[];
    quoteActivity: {
        data: {
            id: string;
            type: string;
            title: string;
            detail: string | null;
            actor: string | null;
            occurred_at: string;
        }[];
        total: number;
        links: { url: string | null; label: string; active: boolean }[];
    } | null;
    sectionLinks: Record<
        string,
        { url: string | null; label: string; active: boolean }[]
    >;
    relationPermissions: {
        customer: boolean;
        opportunity: boolean;
        installation: boolean;
        billing: boolean;
    };
}) {
    const orderCreation = useDocumentCreation();
    const deliveryCreation = useDocumentCreation();
    const milestoneCreation = useDocumentCreation();
    const { locale } = useLocale();
    const todayKey = new Intl.DateTimeFormat('en-CA').format(new Date());
    const eventKinds = { status: 'status', version: 'version' };
    const page = usePage();
    const params = new URL(page.url, 'http://localhost').searchParams;
    const tab = params.get('tab') ?? 'summary';
    const filters = Object.fromEntries(
        [...params.entries()].filter(
            ([key]) =>
                ![
                    'tab',
                    'relation_page',
                    'version_page',
                    'activity_page',
                    'delivery_page',
                ].includes(key),
        ),
    );
    const tabs = [
        ['summary', 'Resumen'],
        ['versions', 'Versiones'],
        ...(relationPermissions.customer ? [['customer', 'Cliente']] : []),
        ...(relationPermissions.opportunity && quote.opportunity
            ? [['opportunity', 'Oportunidad']]
            : []),
        ...(relationPermissions.installation && quote.installation
            ? [['installation', 'Proyecto / Instalación']]
            : []),
        ...(relationPermissions.billing ? [['billing', 'Facturación']] : []),
        ['documents', 'Documentos'],
        ['activity', 'Actividad'],
        ['workflow', 'Gestión'],
    ];
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
        scheduled_at: '',
        technical_validation_required: true,
        technical_validation_reason: '',
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
    const snapshotName =
        quote.customer_snapshot?.company_name ||
        `${quote.customer_snapshot?.first_name || ''} ${quote.customer_snapshot?.last_name || ''}`.trim();
    const customer =
        snapshotName ||
        (quote.customer
            ? quote.customer.company_name ||
              `${quote.customer.first_name} ${quote.customer.last_name || ''}`.trim()
            : 'Legacy quote without customer');

    return (
        <>
            <Head title={quote.number} />
            <PageShell className="quotes-workspace quotes-detail">
                <header className="shell-page-header quotes-detail-header">
                    <Breadcrumbs
                        breadcrumbs={[
                            { title: 'Comercial', href: '/commercial' },
                            { title: 'Presupuestos', href: quoteUrl(filters) },
                            {
                                title: quote.number,
                                href: quoteUrl(filters, quote.id),
                            },
                        ]}
                    />
                    <Link className="quotes-back" href={quoteUrl(filters)}>
                        <ArrowLeft size={15} />
                        Volver a presupuestos
                    </Link>
                    <div className="quotes-detail-heading">
                        <div>
                            <h1>
                                {quote.number}{' '}
                                <Badge variant="outline">
                                    {quoteStatusLabel(
                                        quote.effective_status ?? quote.status,
                                    )}
                                </Badge>
                            </h1>
                            <p>
                                {customer} · v{quote.version}
                                {quote.opportunity
                                    ? ' · ' + quote.opportunity.title
                                    : ''}
                            </p>
                        </div>
                        <div className="quotes-detail-actions">
                            {capabilities.edit && (
                                <Button variant="outline" asChild>
                                    <Link href={`/quotes/${quote.id}/edit`}>
                                        <Pencil />
                                        Editar
                                    </Link>
                                </Button>
                            )}
                            <Form
                                action={`/quotes/${quote.id}/duplicate`}
                                method="post"
                            >
                                {({ processing }) => (
                                    <Button
                                        variant="outline"
                                        disabled={processing}
                                    >
                                        <FileDown />
                                        Duplicar
                                    </Button>
                                )}
                            </Form>
                            <Button variant="outline" asChild>
                                <a
                                    href={`/quotes/${quote.id}/pdf`}
                                    target="_blank"
                                    rel="noreferrer"
                                >
                                    <FileDown />
                                    Generar PDF
                                </a>
                            </Button>
                            {capabilities.send && (
                                <Button asChild>
                                    <Link
                                        href={quoteUrl(
                                            filters,
                                            quote.id,
                                            'workflow',
                                        )}
                                    >
                                        <Send />
                                        Enviar por email
                                    </Link>
                                </Button>
                            )}
                            <details className="quotes-actions-menu">
                                <summary>Más acciones</summary>
                                <div>
                                    <Link
                                        href={quoteUrl(
                                            filters,
                                            quote.id,
                                            'workflow',
                                        )}
                                    >
                                        Gestión comercial
                                    </Link>
                                    {capabilities.exportWithoutWatermark && (
                                        <a
                                            href={`/quotes/${quote.id}/pdf/without-watermark`}
                                        >
                                            {translate(
                                                'PDF without watermark',
                                                locale,
                                            )}
                                        </a>
                                    )}
                                </div>
                            </details>
                        </div>
                    </div>
                </header>
                <nav
                    className="quotes-detail-tabs"
                    aria-label="Secciones del presupuesto"
                >
                    {tabs.map(([key, label]) => (
                        <Link
                            key={key}
                            href={quoteUrl(filters, quote.id, key)}
                            aria-current={tab === key ? 'page' : undefined}
                        >
                            {label}
                        </Link>
                    ))}
                </nav>
                {quote.status === 'sent' &&
                    quote.valid_until &&
                    quote.valid_until.slice(0, 10) < todayKey && (
                        <p className="quotes-expiry-notice">
                            Validez vencida. El documento enviado se conserva;
                            no puede aceptarse ni reenviarse.
                        </p>
                    )}
                {tab === 'activity' && quoteActivity && (
                    <section className="quote-panel">
                        <h2>Actividad del presupuesto</h2>
                        {quoteActivity.data.map((event) => (
                            <article
                                className="quotes-document-row"
                                key={event.id}
                            >
                                <strong>
                                    {event.type === eventKinds.status
                                        ? quoteStatusLabel(event.title)
                                        : event.type === eventKinds.version
                                          ? `v${event.title}`
                                          : deliveryStatusLabel(event.title)}
                                </strong>
                                <span>{event.detail}</span>
                                <span>{event.actor || '—'}</span>
                                <time>{dateTime(event.occurred_at)}</time>
                            </article>
                        ))}
                        {!quoteActivity.total && (
                            <p>Sin actividad registrada.</p>
                        )}
                        <PaginationBar
                            links={quoteActivity.links}
                            total={quoteActivity.total}
                            noun="eventos"
                        />
                    </section>
                )}
                {tab === 'summary' && (
                    <QuoteOverview
                        quote={quote}
                        customer={customer}
                        billing={billing}
                        relationPermissions={relationPermissions}
                        capabilities={capabilities}
                        onProject={() => setProjectOpen(true)}
                        filters={filters}
                    />
                )}
                {tab === 'customer' && relationPermissions.customer && (
                    <section className="quote-panel">
                        <h2>Cliente</h2>
                        <p>{customer}</p>
                        <p>{quote.customer_snapshot?.tax_id}</p>
                        <p>{quote.customer_snapshot?.email}</p>
                        {quote.person_id && (
                            <Link href={`/people/${quote.person_id}`}>
                                Ver ficha de cliente
                            </Link>
                        )}
                    </section>
                )}
                {tab === 'opportunity' &&
                    relationPermissions.opportunity &&
                    quote.opportunity && (
                        <section className="quote-panel">
                            <h2>Oportunidad</h2>
                            <Link
                                href={`/opportunities/${quote.opportunity.id}`}
                            >
                                {quote.opportunity.code} ·{' '}
                                {quote.opportunity.title}
                            </Link>
                        </section>
                    )}
                {tab === 'installation' &&
                    relationPermissions.installation &&
                    quote.installation && (
                        <section className="quote-panel">
                            <h2>Proyecto / Instalación</h2>
                            <Link
                                href={`/installations/${quote.installation.id}`}
                            >
                                {quote.installation.project_reference ||
                                    quote.installation.site_name}
                            </Link>
                        </section>
                    )}
                {tab === 'documents' && (
                    <section className="quote-panel">
                        <h2>Documentos enviados</h2>
                        {quote.deliveries.map((d) => (
                            <div className="quotes-document-row" key={d.id}>
                                <strong>{d.document_version || '—'}</strong>
                                <span>
                                    {d.recipient_email} · {d.status}
                                </span>
                                <time>{quoteDate(d.sent_at)}</time>
                                {d.archived_pdf_url ? (
                                    <a
                                        href={d.archived_pdf_url}
                                        target="_blank"
                                        rel="noreferrer"
                                    >
                                        PDF original
                                    </a>
                                ) : (
                                    <span>Sin PDF histórico archivado</span>
                                )}
                            </div>
                        ))}
                        {!quote.deliveries.length && (
                            <p>No hay documentos enviados.</p>
                        )}
                        <a
                            href={`/quotes/${quote.id}/pdf`}
                            target="_blank"
                            rel="noreferrer"
                        >
                            PDF de la versión actual
                        </a>
                    </section>
                )}
                {quote.status === 'draft' && (
                    <section
                        hidden={!['workflow'].includes(tab)}
                        data-quote-section="workflow"
                        className="rounded-lg border border-dashed bg-muted/30 p-4"
                    >
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
                {!!materialPreview.classification_warnings?.length && (
                    <section className="rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-950">
                        <h2 className="font-semibold">
                            Revisa la clasificación de materiales antes de
                            aceptar
                        </h2>
                        <p>
                            Estos conceptos no generan por sí solos necesidades
                            de almacén. Si son material, vincula un producto con
                            control de inventario; si son servicios, define sus
                            componentes operativos.
                        </p>
                        <ul className="mt-2 list-inside list-disc">
                            {materialPreview.classification_warnings.map(
                                (warning, index) => (
                                    <li key={index}>{warning}</li>
                                ),
                            )}
                        </ul>
                    </section>
                )}
                {materialPreview.items > 0 && (
                    <section
                        hidden={!['installation'].includes(tab)}
                        data-quote-section="installation"
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
                <section
                    hidden={!['workflow'].includes(tab)}
                    data-quote-section="workflow"
                    className="rounded-lg border bg-card p-4 shadow-xs"
                >
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
                                                {quoteStatusLabel(
                                                    quote.effective_status ??
                                                        quote.status,
                                                )}
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
                                    <div className="grid gap-2">
                                        <Label htmlFor="quote_reason">
                                            Motivo / notas
                                        </Label>
                                        <Textarea
                                            id="quote_reason"
                                            name="reason"
                                            maxLength={2000}
                                        />
                                    </div>
                                    <InputError
                                        message={errors.status || errors.reason}
                                    />
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
                                {quoteStatusLabel(
                                    quote.effective_status ?? quote.status,
                                )}
                            </p>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Este es un estado final y no admite cambios
                                directos.
                            </p>
                        </div>
                    )}
                </section>
                <section
                    hidden={!['never'].includes(tab)}
                    data-quote-section="never"
                    className="grid gap-4 border-y py-4 text-sm sm:grid-cols-2 lg:grid-cols-4"
                >
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
                <QuotePaperAcceptance
                    quoteId={quote.id}
                    canAccept={
                        ['draft', 'sent'].includes(quote.status) &&
                        ['approved', 'not_required'].includes(
                            quote.approval_status,
                        )
                    }
                    history={quote.status_history}
                />
                {(quote.status === 'accepted' ||
                    quote.financial_status !== 'not_required') && (
                    <section
                        hidden={!['billing'].includes(tab)}
                        data-quote-section="billing"
                        className="grid gap-4 rounded-md border p-4 text-sm md:grid-cols-4"
                    >
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
                                )?.status === 'draft'
                                    ? 'Pendiente de facturar'
                                    : billing.documents.find(
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
                <section
                    hidden={!['versions', 'workflow'].includes(tab)}
                    data-quote-section="versions,activity,workflow"
                    className="grid gap-4 rounded-md border p-4 lg:grid-cols-[1fr_1fr]"
                >
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
                                    {quote.status_history.map((entry) => (
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
                                                · {dateTime(entry.changed_at)}
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
                                    <details className="quotes-version-lines">
                                        <summary>
                                            Líneas de esta versión
                                        </summary>
                                        {version.lines?.map((line, index) => (
                                            <div key={index}>
                                                {line.description} ·{' '}
                                                {line.quantity} ×{' '}
                                                {money(line.unit_price)} ·{' '}
                                                {money(line.total)}
                                            </div>
                                        ))}
                                    </details>
                                </div>
                            ))}
                        </div>
                    </div>
                </section>
                <section
                    hidden={!['summary'].includes(tab)}
                    data-quote-section="summary"
                    className="max-w-full overflow-x-auto overscroll-x-contain"
                >
                    <table className="quotes-lines-table w-full min-w-[48rem] text-sm">
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
                                    <td>
                                        {new Intl.NumberFormat('es-ES').format(
                                            Number(line.discount_percent),
                                        )}
                                        %
                                    </td>
                                    <td>
                                        {new Intl.NumberFormat('es-ES').format(
                                            Number(line.tax_percent),
                                        )}
                                        %
                                    </td>
                                    <td className="text-right font-medium">
                                        {money(line.total)}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    <div className="quotes-lines-mobile">
                        {quote.lines.map((line) => (
                            <article key={line.id}>
                                <strong>{line.description}</strong>
                                <span>
                                    {Number(line.quantity)} ×{' '}
                                    {money(line.unit_price)}
                                </span>
                                <span>
                                    IVA{' '}
                                    {new Intl.NumberFormat('es-ES').format(
                                        Number(line.tax_percent),
                                    )}
                                    % · {money(line.total)}
                                </span>
                                <span>Descuento: {Number(line.discount_percent)}%</span>
                                <small>
                                    {line.selection_mode === 'included' ||
                                    line.is_selected
                                        ? 'Seleccionada'
                                        : 'No incluida'}
                                </small>
                            </article>
                        ))}
                    </div>
                </section>
                <section
                    hidden={!['never'].includes(tab)}
                    data-quote-section="never"
                    className="grid gap-6 border-t pt-5 lg:grid-cols-[1fr_22rem]"
                >
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
                    <section
                        hidden={!['billing'].includes(tab)}
                        data-quote-section="billing"
                        className="grid gap-5 border-t pt-5 lg:grid-cols-[minmax(18rem,.7fr)_minmax(0,1.3fr)]"
                    >
                        <div>
                            <div className="flex items-center justify-between gap-3">
                                <div>
                                    <h2 className="font-semibold">
                                        Billing plan
                                    </h2>
                                    <p className="text-sm text-muted-foreground">
                                        Planifica los importes, registra los
                                        cobros y emite cada factura cuando tú lo
                                        indiques.
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
                                <div
                                    key={document.id}
                                    className="border-b last:border-0"
                                >
                                    <Link
                                        href={`/sales/${document.id}`}
                                        className="flex items-center justify-between gap-4 border-b py-3 text-sm transition-colors last:border-0 hover:bg-muted/40"
                                    >
                                        <div className="min-w-0">
                                            <div className="truncate font-medium">
                                                {document.billing_label ||
                                                    `Milestone ${document.billing_sequence}`}
                                            </div>
                                            <div className="text-xs text-muted-foreground">
                                                {document.collect_before_invoice &&
                                                document.status === 'draft'
                                                    ? 'Editar importe · registrar cobro'
                                                    : document.number}{' '}
                                                · {document.billing_percentage}%
                                                {document.is_acceptance_invoice
                                                    ? ' · Acceptance'
                                                    : ''}
                                            </div>
                                        </div>
                                        <div className="text-right">
                                            <div className="font-medium">
                                                {money(document.amount)}
                                            </div>
                                            {(document.is_acceptance_invoice ||
                                                document.collect_before_invoice) && (
                                                <div className="text-xs text-muted-foreground">
                                                    Paid{' '}
                                                    {money(
                                                        document.paid_amount,
                                                    )}
                                                </div>
                                            )}
                                            <Badge variant="outline">
                                                {document.collect_before_invoice &&
                                                document.status === 'draft'
                                                    ? document.paid_amount >=
                                                      document.amount
                                                        ? 'Cobrado · pendiente de facturar'
                                                        : 'Pendiente de cobro'
                                                    : document.status}
                                            </Badge>
                                        </div>
                                    </Link>
                                    {document.collect_before_invoice &&
                                        document.status === 'draft' && (
                                            <Form
                                                action={`/sales/${document.id}/milestone`}
                                                method="put"
                                                className="grid gap-2 pb-3"
                                            >
                                                {({ errors, processing }) => (
                                                    <>
                                                        <FormErrorSummary
                                                            errors={errors}
                                                        />
                                                        <input
                                                            type="hidden"
                                                            name="billing_label"
                                                            value={
                                                                document.billing_label ||
                                                                'Hito de cobro'
                                                            }
                                                        />
                                                        <label
                                                            className="text-xs font-medium"
                                                            htmlFor={`milestone-${document.id}`}
                                                        >
                                                            Importe previsto
                                                            (IVA incluido)
                                                        </label>
                                                        <div className="flex gap-2">
                                                            <Input
                                                                id={`milestone-${document.id}`}
                                                                name="billing_amount"
                                                                type="number"
                                                                step="0.01"
                                                                min={Math.max(
                                                                    0.01,
                                                                    document.paid_amount,
                                                                )}
                                                                defaultValue={
                                                                    document.amount
                                                                }
                                                                required
                                                            />
                                                            <Button
                                                                variant="outline"
                                                                disabled={
                                                                    processing
                                                                }
                                                            >
                                                                Guardar importe
                                                            </Button>
                                                        </div>
                                                    </>
                                                )}
                                            </Form>
                                        )}
                                </div>
                            ))}
                            {billing.documents.length === 0 && (
                                <div className="py-10 text-center text-sm text-muted-foreground">
                                    No billing milestones yet.
                                </div>
                            )}
                        </div>
                    </section>
                )}
                <section
                    hidden={!['workflow'].includes(tab)}
                    data-quote-section="workflow"
                    className="grid gap-6 border-t pt-5 lg:grid-cols-[minmax(20rem,.7fr)_minmax(0,1.3fr)]"
                >
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
                                    type="hidden"
                                    name="expected_version"
                                    value={quote.version}
                                />
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
                <footer
                    hidden={tab !== 'workflow'}
                    className="flex flex-wrap items-end justify-between gap-4 border-t pt-5"
                >
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
                                        transform={
                                            type === 'order'
                                                ? orderCreation.transform
                                                : deliveryCreation.transform
                                        }
                                        onSuccess={
                                            type === 'order'
                                                ? orderCreation.complete
                                                : deliveryCreation.complete
                                        }
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
                {tab !== 'activity' && sectionLinks[tab] && (
                    <PaginationBar
                        links={sectionLinks[tab]}
                        total={1}
                        noun=""
                    />
                )}
            </PageShell>
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
                                Crea el proyecto sin fecha ni técnico. Solo se
                                crea una visita si introduces una fecha; una
                                visita existente no se duplica.
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
                                label="Fecha de visita técnica (opcional)"
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
                            <label className="flex items-center gap-2 text-sm sm:col-span-2">
                                <input
                                    type="checkbox"
                                    checked={
                                        projectForm.data
                                            .technical_validation_required
                                    }
                                    onChange={(e) =>
                                        projectForm.setData(
                                            'technical_validation_required',
                                            e.target.checked,
                                        )
                                    }
                                />
                                Requiere visita o ingeniería validada antes de
                                ejecutar la instalación
                            </label>
                            {!projectForm.data
                                .technical_validation_required && (
                                <ProjectField
                                    label="Motivo de dispensa técnica (solo administración)"
                                    error={
                                        projectForm.errors
                                            .technical_validation_reason
                                    }
                                >
                                    <Input
                                        required
                                        value={
                                            projectForm.data
                                                .technical_validation_reason
                                        }
                                        onChange={(e) =>
                                            projectForm.setData(
                                                'technical_validation_reason',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </ProjectField>
                            )}
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
                        transform={milestoneCreation.transform}
                        onSuccess={() => {
                            milestoneCreation.complete();
                            setBillingOpen(false);
                        }}
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
                            <Badge variant="outline">
                                {deliveryStatusLabel(delivery.status)}
                            </Badge>
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
    return new Intl.NumberFormat('es-ES', {
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
        ? new Intl.DateTimeFormat('es-ES', { dateStyle: 'medium' }).format(
              new Date(`${value.slice(0, 10)}T12:00:00`),
          )
        : '—';
}
function dateTime(value: string) {
    return new Intl.DateTimeFormat('es-ES', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}
QuoteShow.layout = {
    breadcrumbs: [
        { title: 'Comercial', href: '/commercial' },
        { title: 'Presupuestos', href: '/quotes' },
    ],
};

function QuoteOverview({
    quote,
    customer,
    billing,
    relationPermissions,
    capabilities,
    onProject,
    filters,
}: {
    quote: Quote;
    customer: string;
    billing: BillingPlan;
    relationPermissions: {
        customer: boolean;
        opportunity: boolean;
        installation: boolean;
        billing: boolean;
    };
    capabilities: QuoteCapabilities;
    onProject: () => void;
    filters: Record<string, string>;
}) {
    return (
        <div className="quotes-summary-grid">
            <section className="quote-panel">
                <h2>Información del presupuesto</h2>
                <dl className="quotes-info-grid">
                    <Info label="Referencia" value={quote.number} />
                    <Info
                        label="Estado"
                        value={quoteStatusLabel(
                            quote.effective_status ?? quote.status,
                        )}
                    />
                    <Info label="Fecha" value={quoteDate(quote.issue_date)} />
                    <Info
                        label="Validez"
                        value={quoteDate(quote.valid_until)}
                    />
                    <Info
                        label="Enviado"
                        value={quoteDate(
                            quote.deliveries.find((d) => d.sent_at)?.sent_at,
                        )}
                    />
                    <Info label="Autor" value={quote.creator?.name || '—'} />
                    <Info label="Versión" value={String(quote.version)} />
                    <Info
                        label="Importe total"
                        value={quoteMoney(quote.amount)}
                    />
                </dl>
            </section>
            <section className="quote-panel">
                <h2>Cliente</h2>
                <strong>{customer}</strong>
                <p>{quote.customer_snapshot?.tax_id}</p>
                <p>{quote.customer_snapshot?.address_line_1}</p>
                <p>{quote.customer_snapshot?.email}</p>
                <p>{quote.customer_snapshot?.phone}</p>
                {relationPermissions.customer && quote.person_id && (
                    <Link href={`/people/${quote.person_id}`}>
                        Ver ficha de cliente
                    </Link>
                )}
                <h3>Autor</h3>
                <p>{quote.creator?.name || '—'}</p>
                {relationPermissions.opportunity && quote.opportunity && (
                    <Link href={`/opportunities/${quote.opportunity.id}`}>
                        {quote.opportunity.code} · {quote.opportunity.title}
                    </Link>
                )}
            </section>
            <section className="quote-panel">
                <h2>Totales</h2>
                <dl className="quotes-totals">
                    <Info
                        label="Base antes de descuentos"
                        value={quoteMoney(quote.subtotal)}
                    />
                    <Info
                        label="Descuentos"
                        value={quoteMoney(quote.discount_amount)}
                    />
                    <Info
                        label="Base imponible"
                        value={quoteMoney(
                            Number(quote.subtotal) -
                                Number(quote.discount_amount),
                        )}
                    />
                    <Info label="IVA" value={quoteMoney(quote.tax_amount)} />
                    <Info label="Total" value={quoteMoney(quote.amount)} />
                </dl>
            </section>
            {quote.notes && (
                <section className="quote-panel">
                    <h2>Notes</h2>
                    <p className="whitespace-pre-wrap">{quote.notes}</p>
                </section>
            )}
            <section className="quote-panel">
                <h2>Versiones</h2>
                {quote.versions.slice(0, 3).map((v) => (
                    <div className="quotes-state-row" key={v.id}>
                        <strong>v{v.version}</strong>
                        <time>{quoteDate(v.created_at)}</time>
                        <span>{quoteMoney(v.amount)}</span>
                    </div>
                ))}
                <Link href={quoteUrl(filters, quote.id, 'versions')}>
                    Ver todas las versiones
                </Link>
            </section>
            <section className="quote-panel">
                <h2>Conversión</h2>
                {relationPermissions.installation && quote.installation ? (
                    <Link href={`/installations/${quote.installation.id}`}>
                        Ver proyecto
                    </Link>
                ) : capabilities.startProject ? (
                    <Button onClick={onProject}>
                        <Wrench />
                        Crear proyecto
                    </Button>
                ) : (
                    <p>
                        La conversión requiere un presupuesto aceptado y permiso
                        de proyectos.
                    </p>
                )}
                {relationPermissions.billing && (
                    <p>
                        {billing.documents.length} documentos de facturación
                        relacionados.
                    </p>
                )}
            </section>
            <section className="quote-panel">
                <h2>Documentos</h2>
                <a
                    href={`/quotes/${quote.id}/pdf`}
                    target="_blank"
                    rel="noreferrer"
                >
                    PDF de la versión actual
                </a>
                {quote.deliveries
                    .filter((d) => d.archived_pdf_url)
                    .slice(0, 3)
                    .map((d) => (
                        <a
                            className="quotes-document-row"
                            href={d.archived_pdf_url!}
                            key={d.id}
                            target="_blank"
                            rel="noreferrer"
                        >
                            {d.document_version} · {quoteDate(d.sent_at)}
                        </a>
                    ))}
                <Link href={quoteUrl(filters, quote.id, 'documents')}>
                    Ver documentos enviados
                </Link>
            </section>
        </div>
    );
}

function deliveryStatusLabel(status: string) {
    const labels: Record<string, string> = {
        pending: 'Pendiente',
        sent: 'Enviado',
        failed: 'Fallido',
        viewed: 'Visto',
        revoked: 'Revocado',
        superseded: 'Sustituido',
    };

    return labels[status] ?? status;
}

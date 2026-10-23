import { Form, Head, Link } from '@inertiajs/react';
import {
    Pencil,
    FileDown,
    CreditCard,
    Send,
    Copy,
    RotateCcw,
} from 'lucide-react';
import { useState } from 'react';
import {
    Panel,
    Empty,
    State,
    date,
    money,
    labels,
} from '@/components/billing/workspace';
import type { Capabilities, Invoice } from '@/components/billing/workspace';
import { PageHeader, PageShell } from '@/components/page-shell';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
type Detail = {
    collect_before_invoice?: boolean;
    supplier_number?: string;
    document_number?: string;
    notes: string | null;
    issued_at?: string | null;
    created_at: string;
    quote?: { id: number; number: string } | null;
    source_document?: { id: number; number: string; type?: string } | null;
    accounting_entry?: { id: number; number: string; status: string } | null;
    lines?: {
        id: number;
        description: string;
        quantity: string;
        unit_price: string;
        total: string;
        tax_percent: string;
    }[];
    tax_lines?: {
        id: number;
        kind: string;
        tax_rate: string;
        taxable_base: string;
        tax_amount: string;
    }[];
    payments: {
        id: number;
        paid_at: string;
        amount: string;
        method: string;
        reference: string | null;
    }[];
    deliveries?: {
        id: number;
        recipient_email: string;
        status: string;
        sent_at: string | null;
        failure_reason: string | null;
    }[];
    attachments?: { id: number; original_name: string }[];
    events?: {
        id: number;
        type: string;
        occurred_at: string;
        notes: string | null;
    }[];
};
type SourceOptions = Record<string, { id: number; label: string }[]>;
function ActionDialog({
    title,
    trigger,
    action,
    children,
    onSuccess,
}: {
    title: string;
    trigger: React.ReactNode;
    action: string;
    children: React.ReactNode;
    onSuccess?: () => void;
}) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="outline">{trigger}</Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                </DialogHeader>
                <Form
                    action={action}
                    method="post"
                    onSuccess={() => {
                        setOpen(false);
                        onSuccess?.();
                    }}
                >
                    {({ errors, processing }) => (
                        <div className="billing-action-form">
                            {children}
                            {Object.entries(errors).map(([key, error]) => (
                                <p
                                    key={key}
                                    role="alert"
                                    className="text-destructive"
                                >
                                    {error}
                                </p>
                            ))}
                            <Button type="submit" disabled={processing}>
                                {processing ? 'Guardando...' : title}
                            </Button>
                        </div>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
function PaymentAction({
    invoice,
    sources,
    operationKey,
}: {
    invoice: Invoice;
    sources: SourceOptions;
    operationKey: string;
}) {
    const customer = invoice.kind === 'customer';
    const [method, setMethod] = useState('bank_transfer');
    const [key, setKey] = useState(operationKey);
    const sourceType =
        method === 'card'
            ? 'card'
            : method === 'cash' || method === 'other'
              ? 'ledger_account'
              : method === 'employee_funds'
                ? 'employee'
                : 'bank_account';
    const options =
        sources[
            method === 'card'
                ? 'cards'
                : method === 'cash'
                  ? 'cashAccounts'
                  : method === 'other'
                    ? 'otherAccounts'
                    : method === 'employee_funds'
                      ? 'employees'
                      : 'bankAccounts'
        ] ?? [];
    const action = customer
        ? `/sales/${invoice.id}/payments`
        : invoice.kind === 'purchase'
          ? `/supplier-invoices/${invoice.id}/payments`
          : `/expenses/${invoice.id}/payments`;

    return (
        <ActionDialog
            title={customer ? 'Registrar cobro' : 'Registrar pago'}
            trigger={
                <>
                    <CreditCard />
                    {customer ? 'Cobrar' : 'Registrar pago'}
                </>
            }
            action={action}
            onSuccess={() => setKey(crypto.randomUUID())}
        >
            <input type="hidden" name="idempotency_key" value={key} />
            <label>
                Fecha efectiva
                <Input
                    type="date"
                    name="paid_at"
                    required
                    defaultValue={new Date().toLocaleDateString('en-CA')}
                />
            </label>
            <label>
                Importe (saldo: {money(invoice.balance)})
                <Input
                    name="amount"
                    type="number"
                    step="0.01"
                    min="0.01"
                    max={invoice.balance}
                    defaultValue={invoice.balance}
                    required
                />
            </label>
            <label>
                Forma de pago
                <select
                    name="method"
                    value={method}
                    onChange={(e) => setMethod(e.target.value)}
                >
                    {[
                        'bank_transfer',
                        'card',
                        'cash',
                        'direct_debit',
                        ...(!customer ? ['employee_funds'] : []),
                        'other',
                    ].map((v) => (
                        <option key={v} value={v}>
                            {labels[v]}
                        </option>
                    ))}
                </select>
            </label>
            {!customer && (
                <>
                    <input
                        type="hidden"
                        name="funding_source_type"
                        value={sourceType}
                    />
                    <label>
                        Origen de fondos
                        <select
                            name="funding_source_id"
                            required
                            key={method}
                            defaultValue=""
                        >
                            <option value="" disabled>
                                Selecciona una fuente existente
                            </option>
                            {options.map((o) => (
                                <option key={o.id} value={o.id}>
                                    {o.label}
                                </option>
                            ))}
                        </select>
                    </label>
                    {!options.length && (
                        <p>
                            No hay fuentes compatibles configuradas.
                            Configúralas en Finanzas.
                        </p>
                    )}
                </>
            )}
            <label>
                Referencia
                <Input name="reference" maxLength={255} />
            </label>
            <label>
                Observaciones
                <Input name="notes" />
            </label>
        </ActionDialog>
    );
}
export default function InvoiceDetail({
    invoice,
    detail,
    identity,
    capabilities,
    managementHref,
    paymentSources = {},
    originalAvailable = false,
    operationKey,
    auditEvents = [],
    voidedPayments = [],
}: {
    invoice: Invoice;
    detail: Detail;
    identity: Record<string, string | null>;
    issuer: Record<string, unknown> | null;
    capabilities: Capabilities;
    managementHref: string;
    paymentSources?: SourceOptions;
    originalAvailable?: boolean;
    operationKey: string;
    auditEvents?: {
        id: number;
        event: string;
        date: string;
        user: string | null;
    }[];
    voidedPayments?: {
        id: number;
        paid_at: string;
        amount: string;
        voided_at: string;
        void_reason: string;
        reversal_entry_id: number | null;
    }[];
}) {
    const [tab, setTab] = useState('summary');
    const customer = invoice.kind === 'customer';
    const canPay =
        Number(invoice.balance) > 0 &&
        (customer
            ? capabilities.recordReceipt &&
              (invoice.locked_at !== null ||
                  (detail.collect_before_invoice === true &&
                      invoice.document_state === 'draft'))
            : capabilities.recordPayment &&
              invoice.status !== 'review_required');
    const pdf = customer
        ? `/sales/${invoice.id}/pdf`
        : invoice.kind === 'purchase' && originalAvailable
          ? `/supplier-invoices/${invoice.id}/document`
          : null;
    const tabs = [
        ['summary', 'Resumen'],
        ['lines', 'Conceptos'],
        ...(!customer ? [['purchases', 'Compras relacionadas']] : []),
        ['payments', customer ? 'Cobros' : 'Pagos'],
        ['documents', 'Documentos'],
        ['activity', 'Actividad'],
    ];

    return (
        <PageShell className="billing-workspace">
            <Head title={invoice.number} />
            <PageHeader
                title={
                    <>
                        {invoice.number}{' '}
                        <State state={invoice.document_state} />
                    </>
                }
                description={
                    <>
                        {invoice.party}
                        {invoice.project
                            ? ` · Proyecto ${invoice.project}`
                            : ''}
                    </>
                }
                breadcrumbs={[
                    { title: 'Facturación', href: '/billing' },
                    {
                        title: customer
                            ? 'Facturas de clientes'
                            : 'Facturas de proveedores',
                        href: customer ? '/sales' : '/billing/suppliers',
                    },
                    { title: invoice.number, href: invoice.href },
                ]}
                actions={
                    <>
                        {capabilities.correct && (
                            <Button variant="outline" asChild>
                                <Link href={managementHref}>
                                    <Pencil />
                                    Corregir factura
                                </Link>
                            </Button>
                        )}
                        {capabilities.edit && (
                            <Button variant="outline" asChild>
                                <Link
                                    href={
                                        customer
                                            ? `/sales/${invoice.id}/edit`
                                            : `/expenses/${invoice.id}/edit`
                                    }
                                >
                                    <Pencil />
                                    Editar
                                </Link>
                            </Button>
                        )}
                        {canPay && (
                            <PaymentAction
                                invoice={invoice}
                                sources={paymentSources}
                                operationKey={operationKey}
                            />
                        )}{' '}
                        {pdf && (
                            <Button variant="outline" asChild>
                                <a href={pdf} target="_blank" rel="noreferrer">
                                    <FileDown />
                                    {customer ? 'PDF' : 'Original recibido'}
                                </a>
                            </Button>
                        )}
                        {customer && invoice.type === 'invoice' && (
                            <>
                                <ActionDialog
                                    title="Duplicar como borrador"
                                    trigger={
                                        <>
                                            <Copy />
                                            Duplicar
                                        </>
                                    }
                                    action={`/sales/${invoice.id}/duplicate`}
                                >
                                    <p>
                                        Se creará una factura borrador con nueva
                                        identidad, sin número definitivo ni
                                        cobros.
                                    </p>
                                </ActionDialog>
                                {invoice.document_state === 'issued' && (
                                    <ActionDialog
                                        title="Enviar por email"
                                        trigger={
                                            <>
                                                <Send />
                                                Enviar
                                            </>
                                        }
                                        action={`/sales/${invoice.id}/send`}
                                    >
                                        <label>
                                            Email del destinatario
                                            <Input
                                                name="recipient_email"
                                                type="email"
                                                required
                                                defaultValue={
                                                    identity.email ?? ''
                                                }
                                            />
                                        </label>
                                    </ActionDialog>
                                )}
                                {capabilities.credit &&
                                    invoice.type === 'invoice' &&
                                    invoice.locked_at && (
                                        <ActionDialog
                                            title="Preparar rectificativa"
                                            trigger={
                                                <>
                                                    <RotateCcw />
                                                    Rectificar
                                                </>
                                            }
                                            action={`/sales/${invoice.id}/credit-note`}
                                        >
                                            <p>
                                                La rectificativa se prepara como
                                                borrador vinculado a esta
                                                factura. La factura original se
                                                conserva.
                                            </p>
                                        </ActionDialog>
                                    )}
                            </>
                        )}
                        <Button asChild variant="outline">
                            <Link href={managementHref}>Más acciones</Link>
                        </Button>
                    </>
                }
            />
            <nav className="billing-tabs" aria-label="Ficha de factura">
                {tabs.map(([key, label]) => (
                    <button
                        key={key}
                        onClick={() => setTab(key)}
                        aria-current={tab === key ? 'page' : undefined}
                    >
                        {label}
                    </button>
                ))}
            </nav>
            {tab === 'summary' && (
                <>
                    <div className="billing-detail-grid">
                        <Panel title="Información general">
                            <dl className="billing-info">
                                {[
                                    ['Referencia', invoice.number],
                                    [
                                        'Número del proveedor',
                                        detail.supplier_number ||
                                            detail.document_number ||
                                            '—',
                                    ],
                                    ['Cliente / proveedor', invoice.party],
                                    ['Proyecto', invoice.project || '—'],
                                    [
                                        'Fecha de emisión',
                                        date(invoice.issue_date),
                                    ],
                                    [
                                        'Fecha de vencimiento',
                                        date(invoice.due_date),
                                    ],
                                    ['Base imponible', money(invoice.base)],
                                    ['IVA', money(invoice.tax)],
                                    ['Total', money(invoice.amount)],
                                ]
                                    .filter(([label]) =>
                                        customer
                                            ? label !== 'Número del proveedor'
                                            : true,
                                    )
                                    .map(([label, value]) => (
                                        <div key={label}>
                                            <dt>{label}</dt>
                                            <dd>{value}</dd>
                                        </div>
                                    ))}
                                <div>
                                    <dt>Documento</dt>
                                    <dd>
                                        <State state={invoice.document_state} />
                                    </dd>
                                </div>
                                <div>
                                    <dt>Cobro / pago</dt>
                                    <dd>
                                        <State
                                            state={invoice.payment_state}
                                            paidLabel={
                                                customer ? 'Cobrada' : 'Pagada'
                                            }
                                        />
                                    </dd>
                                </div>
                            </dl>
                        </Panel>
                        <Panel title="Conceptos">
                            <LineTable detail={detail} />
                            <Totals invoice={invoice} />
                        </Panel>
                        <div className="billing-detail-aside">
                            <Panel title={customer ? 'Cliente' : 'Proveedor'}>
                                <strong>
                                    {identity.company_name ||
                                        identity.legal_name ||
                                        invoice.party}
                                </strong>
                                <p>{identity.tax_id || identity.nif || '—'}</p>
                                <p>{identity.address_line_1 || '—'}</p>
                                <p>{identity.email || '—'}</p>
                                {invoice.party_id && (
                                    <Link
                                        href={
                                            customer
                                                ? `/people/${invoice.party_id}`
                                                : `/suppliers/${invoice.party_id}`
                                        }
                                    >
                                        Ver ficha →
                                    </Link>
                                )}
                            </Panel>
                            <Panel title="Proyecto / instalación">
                                {invoice.installation_id ? (
                                    <>
                                        <p>
                                            {invoice.project ||
                                                'Instalación vinculada'}
                                        </p>
                                        <Link
                                            href={`/installations/${invoice.installation_id}`}
                                        >
                                            Ver instalación →
                                        </Link>
                                    </>
                                ) : (
                                    <Empty>No hay instalación vinculada.</Empty>
                                )}
                                {detail.quote && (
                                    <Link href={`/quotes/${detail.quote.id}`}>
                                        Presupuesto {detail.quote.number} →
                                    </Link>
                                )}
                                {detail.accounting_entry &&
                                    capabilities.payments && (
                                        <p>
                                            <Link
                                                href={`/accounting?search=${encodeURIComponent(detail.accounting_entry.number)}`}
                                            >
                                                Asiento{' '}
                                                {detail.accounting_entry.number}{' '}
                                                →
                                            </Link>
                                        </p>
                                    )}
                            </Panel>
                            <Panel title="Saldo pendiente">
                                <strong className="billing-balance">
                                    {money(invoice.balance)}
                                </strong>
                                <p>
                                    Registrado: {money(invoice.paid)}
                                    {Number(invoice.credits) !== 0
                                        ? ` · Rectificativas: ${money(invoice.credits)}`
                                        : ''}
                                </p>
                            </Panel>
                        </div>
                    </div>
                    {customer &&
                        invoice.status === 'draft' &&
                        capabilities.issue && (
                            <Panel title="Emisión">
                                <p>
                                    Asigna la numeración de la serie fiscal
                                    existente y bloquea la factura.
                                </p>
                                <ActionDialog
                                    title="Emitir y bloquear"
                                    trigger="Emitir factura"
                                    action={`/sales/${invoice.id}/issue`}
                                >
                                    <label>
                                        Fecha de emisión
                                        <Input
                                            type="date"
                                            name="issue_date"
                                            defaultValue={invoice.issue_date}
                                            required
                                        />
                                    </label>
                                    <label>
                                        Fecha de vencimiento
                                        <Input
                                            type="date"
                                            name="due_date"
                                            defaultValue={
                                                invoice.due_date ?? ''
                                            }
                                        />
                                    </label>
                                </ActionDialog>
                            </Panel>
                        )}
                    {detail.notes && (
                        <Panel title="Observaciones">
                            <p className="whitespace-pre-wrap">
                                {detail.notes}
                            </p>
                        </Panel>
                    )}
                </>
            )}
            {tab === 'lines' && (
                <Panel title="Conceptos">
                    <LineTable detail={detail} />
                    <Totals invoice={invoice} />
                </Panel>
            )}
            {tab === 'payments' && (
                <Panel
                    title={
                        customer ? 'Cobros registrados' : 'Pagos registrados'
                    }
                >
                    {detail.payments.length ? (
                        <div className="billing-table-wrap always-scroll">
                            <table className="billing-table">
                                <thead>
                                    <tr>
                                        <th>Fecha</th>
                                        <th>Forma</th>
                                        <th>Referencia</th>
                                        <th className="amount">Importe</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {detail.payments.map((p) => (
                                        <tr key={p.id}>
                                            <td>{date(p.paid_at)}</td>
                                            <td>
                                                {labels[p.method] ?? p.method}
                                            </td>
                                            <td>{p.reference ?? '—'}</td>
                                            <td className="amount">
                                                {money(p.amount)}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    ) : (
                        <Empty>No hay movimientos registrados.</Empty>
                    )}
                    {voidedPayments.length > 0 && (
                        <div className="billing-table-wrap always-scroll">
                            <h3 className="billing-footnote">
                                Movimientos anulados
                            </h3>
                            <table className="billing-table">
                                <thead>
                                    <tr>
                                        <th>Fecha</th>
                                        <th>Importe</th>
                                        <th>Anulado</th>
                                        <th>Motivo</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {voidedPayments.map((payment) => (
                                        <tr key={payment.id}>
                                            <td>{date(payment.paid_at)}</td>
                                            <td>{money(payment.amount)}</td>
                                            <td>{date(payment.voided_at)}</td>
                                            <td>{payment.void_reason}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                    <Totals invoice={invoice} />
                    <p className="billing-footnote">
                        Las anulaciones requieren revisión contable y
                        trazabilidad. Accede al flujo autorizado desde Más
                        acciones.
                    </p>
                </Panel>
            )}
            {tab === 'purchases' && (
                <Panel title="Compras relacionadas">
                    {detail.source_document ? (
                        <Link href={`/purchases/${detail.source_document.id}`}>
                            {detail.source_document.number} →
                        </Link>
                    ) : (
                        <Empty>
                            No hay pedido de compra vinculado a esta factura.
                        </Empty>
                    )}
                </Panel>
            )}
            {tab === 'documents' && (
                <Panel title="Documentos">
                    {pdf ? (
                        <Button asChild variant="outline">
                            <a href={pdf} target="_blank" rel="noreferrer">
                                {customer
                                    ? 'Descargar PDF'
                                    : 'Abrir factura original recibida'}
                            </a>
                        </Button>
                    ) : detail.attachments?.length ? (
                        <Link href={managementHref}>
                            Ver {detail.attachments.length} documentos adjuntos
                        </Link>
                    ) : (
                        <Empty>No hay documentos adjuntos.</Empty>
                    )}
                </Panel>
            )}
            {tab === 'activity' && (
                <Panel title="Actividad y entregas">
                    {detail.deliveries?.length ? (
                        <div className="billing-table-wrap always-scroll">
                            <table className="billing-table">
                                <thead>
                                    <tr>
                                        <th>Destinatario</th>
                                        <th>Fecha</th>
                                        <th>Resultado</th>
                                        <th>Detalle</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {detail.deliveries.map((d) => (
                                        <tr key={d.id}>
                                            <td>{d.recipient_email}</td>
                                            <td>{date(d.sent_at)}</td>
                                            <td>{d.status}</td>
                                            <td>{d.failure_reason ?? '—'}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    ) : detail.events?.length ? (
                        <ul>
                            {detail.events.map((e) => (
                                <li key={e.id}>
                                    {date(e.occurred_at)} · {e.type} · {e.notes}
                                </li>
                            ))}
                        </ul>
                    ) : (
                        <Empty>
                            No hay actividad registrada en este documento.
                        </Empty>
                    )}
                    {auditEvents.length > 0 && (
                        <ul className="billing-footnote">
                            {auditEvents.map((event) => (
                                <li key={event.id}>
                                    {date(event.date)} · {event.event} ·{' '}
                                    {event.user}
                                </li>
                            ))}
                        </ul>
                    )}
                    <p className="billing-footnote">
                        Creado: {date(detail.created_at)}
                        {detail.issued_at
                            ? ` · Emitido: ${date(detail.issued_at)}`
                            : ''}
                    </p>
                </Panel>
            )}
        </PageShell>
    );
}
function Totals({ invoice }: { invoice: Invoice }) {
    return (
        <dl className="billing-totals">
            <div>
                <dt>Base imponible</dt>
                <dd>{money(invoice.base)}</dd>
            </div>
            <div>
                <dt>IVA</dt>
                <dd>{money(invoice.tax)}</dd>
            </div>
            {invoice.withholding !== null &&
                Number(invoice.withholding) !== 0 && (
                    <div>
                        <dt>Retención</dt>
                        <dd>−{money(invoice.withholding)}</dd>
                    </div>
                )}
            <div>
                <dt>Total</dt>
                <dd>{money(invoice.amount)}</dd>
            </div>
            <div>
                <dt>Saldo pendiente</dt>
                <dd>{money(invoice.balance)}</dd>
            </div>
            {Number(invoice.overpaid) > 0 && (
                <div>
                    <dt>Saldo a favor por aplicar</dt>
                    <dd>{money(invoice.overpaid)}</dd>
                </div>
            )}
        </dl>
    );
}
function LineTable({ detail }: { detail: Detail }) {
    return detail.lines?.length ? (
        <div className="billing-table-wrap always-scroll">
            <table className="billing-table">
                <thead>
                    <tr>
                        <th>Descripción</th>
                        <th>Cantidad</th>
                        <th className="amount">Precio</th>
                        <th className="amount">Importe</th>
                    </tr>
                </thead>
                <tbody>
                    {detail.lines.map((l) => (
                        <tr key={l.id}>
                            <td>{l.description}</td>
                            <td>{l.quantity}</td>
                            <td className="amount">{money(l.unit_price)}</td>
                            <td className="amount">{money(l.total)}</td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    ) : detail.tax_lines?.length ? (
        <div className="billing-table-wrap always-scroll">
            <table className="billing-table">
                <thead>
                    <tr>
                        <th>Concepto fiscal</th>
                        <th className="amount">Base</th>
                        <th>Tipo</th>
                        <th className="amount">Cuota</th>
                    </tr>
                </thead>
                <tbody>
                    {detail.tax_lines.map((line) => (
                        <tr key={line.id}>
                            <td>{line.kind === 'vat' ? 'IVA' : line.kind}</td>
                            <td className="amount">
                                {money(line.taxable_base)}
                            </td>
                            <td>
                                {new Intl.NumberFormat('es-ES', {
                                    maximumFractionDigits: 3,
                                }).format(Number(line.tax_rate))}
                                %
                            </td>
                            <td className="amount">{money(line.tax_amount)}</td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    ) : (
        <Empty>No hay conceptos registrados.</Empty>
    );
}

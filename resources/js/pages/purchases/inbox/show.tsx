import { Form, Head, Link } from '@inertiajs/react';
import {
    ArrowLeft,
    Download,
    FileSearch,
    Mail,
    Save,
    ShieldCheck,
    X,
} from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
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

type Supplier = {
    id: number;
    legal_name: string;
    trade_name: string | null;
    tax_id: string | null;
};
type PurchaseOrder = {
    id: number;
    number: string;
    supplier_id: number;
    issue_date: string;
    amount: string;
    invoicing_status: string | null;
    supplier: Supplier;
};
type InboxDocument = {
    id: number;
    original_name: string;
    mime_type: string;
    size: number;
    classification: string;
    status: string;
    source: string;
    extraction_status: string;
    extraction_error: string | null;
    extracted_data: Record<string, string | number> | null;
    supplier_id: number | null;
    purchase_order_id: number | null;
    notes: string | null;
    purchase_document: { id: number; number: string } | null;
    uploader: { name: string } | null;
    processor: { name: string } | null;
    mailbox_message: {
        subject: string | null;
        from_name: string | null;
        from_email: string | null;
        received_at: string | null;
    } | null;
    created_at: string;
};

export default function PurchaseInboxShow({
    inboxDocument,
    suppliers,
    purchaseOrders,
}: {
    inboxDocument: InboxDocument;
    suppliers: Supplier[];
    purchaseOrders: PurchaseOrder[];
}) {
    const extracted = inboxDocument.extracted_data || {};
    const [classification, setClassification] = useState(
        inboxDocument.classification,
    );
    const [supplierId, setSupplierId] = useState(
        inboxDocument.supplier_id ? String(inboxDocument.supplier_id) : 'none',
    );
    const [orderId, setOrderId] = useState(
        inboxDocument.purchase_order_id
            ? String(inboxDocument.purchase_order_id)
            : 'none',
    );
    const availableOrders = purchaseOrders.filter(
        (order) =>
            supplierId === 'none' || order.supplier_id === Number(supplierId),
    );
    const closed = ['converted', 'rejected'].includes(inboxDocument.status);

    const changeSupplier = (value: string) => {
        setSupplierId(value);
        const selectedOrder = purchaseOrders.find(
            (order) => order.id === Number(orderId),
        );

        if (selectedOrder && selectedOrder.supplier_id !== Number(value)) {
            setOrderId('none');
        }
    };

    return (
        <>
            <Head title={inboxDocument.original_name} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <header className="flex flex-wrap items-start justify-between gap-3">
                    <div className="min-w-0">
                        <div className="flex flex-wrap items-center gap-2">
                            <h1 className="truncate text-xl font-semibold">
                                {inboxDocument.original_name}
                            </h1>
                            <Badge variant="outline">
                                {inboxDocument.status.replaceAll('_', ' ')}
                            </Badge>
                        </div>
                        <p className="text-sm text-muted-foreground">
                            Received {date(inboxDocument.created_at)} via{' '}
                            {inboxDocument.source === 'email'
                                ? 'email'
                                : inboxDocument.uploader?.name || 'staff'}
                        </p>
                    </div>
                    <Button variant="outline" asChild>
                        <Link href="/purchase-inbox">
                            <ArrowLeft /> Document inbox
                        </Link>
                    </Button>
                </header>

                <section className="flex flex-wrap items-center justify-between gap-4 border-y py-4 text-sm">
                    <div>
                        <div className="font-medium">Original document</div>
                        <div className="text-muted-foreground">
                            {inboxDocument.mime_type} ·{' '}
                            {fileSize(inboxDocument.size)} ·{' '}
                            {inboxDocument.extraction_status}
                        </div>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button variant="outline" asChild>
                            <a
                                href={`/purchase-inbox/${inboxDocument.id}/download`}
                            >
                                <Download /> Download
                            </a>
                        </Button>
                        {!closed &&
                            inboxDocument.extraction_status !==
                                'processing' && (
                                <Form
                                    action={`/purchase-inbox/${inboxDocument.id}/extract`}
                                    method="post"
                                >
                                    <Button variant="outline">
                                        <FileSearch />{' '}
                                        {inboxDocument.extraction_status ===
                                        'pending'
                                            ? 'Extract data'
                                            : 'Extract again'}
                                    </Button>
                                </Form>
                            )}
                    </div>
                </section>
                {inboxDocument.mailbox_message && (
                    <section className="flex items-start gap-3 border-b pb-4 text-sm">
                        <Mail className="mt-0.5 size-4 shrink-0" />
                        <div>
                            <div className="font-medium">
                                {inboxDocument.mailbox_message.subject ||
                                    'Email without subject'}
                            </div>
                            <div className="text-muted-foreground">
                                From{' '}
                                {inboxDocument.mailbox_message.from_name ||
                                    inboxDocument.mailbox_message.from_email ||
                                    'unknown sender'}
                                {inboxDocument.mailbox_message.from_name &&
                                    inboxDocument.mailbox_message.from_email &&
                                    ` · ${inboxDocument.mailbox_message.from_email}`}
                            </div>
                        </div>
                    </section>
                )}
                {inboxDocument.extraction_error && (
                    <p className="text-sm text-destructive">
                        {inboxDocument.extraction_error}
                    </p>
                )}

                <section>
                    <h2 className="font-semibold">Detected information</h2>
                    <div className="mt-3 grid border-y text-sm sm:grid-cols-2 lg:grid-cols-4">
                        <Info
                            label="Supplier"
                            value={String(extracted.supplier_name || '—')}
                        />
                        <Info
                            label="Tax ID"
                            value={String(extracted.supplier_tax_id || '—')}
                        />
                        <Info
                            label="Document number"
                            value={String(extracted.invoice_number || '—')}
                        />
                        <Info
                            label="Issue date"
                            value={formatDate(extracted.issue_date)}
                        />
                        <Info
                            label="Due date"
                            value={formatDate(extracted.due_date)}
                        />
                        <Info
                            label="Subtotal"
                            value={formatMoney(extracted.subtotal)}
                        />
                        <Info
                            label="Transport"
                            value={formatMoney(extracted.shipping_cost)}
                        />
                        <Info
                            label="Tax"
                            value={formatMoney(extracted.tax_amount)}
                        />
                        <Info
                            label="Total"
                            value={formatMoney(extracted.total)}
                        />
                    </div>
                </section>

                {inboxDocument.status === 'converted' &&
                inboxDocument.purchase_document ? (
                    <section className="flex flex-wrap items-center justify-between gap-3 border-y py-5">
                        <div>
                            <h2 className="font-semibold">
                                Document converted
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                Processed by{' '}
                                {inboxDocument.processor?.name || 'staff'}.
                            </p>
                        </div>
                        <Button asChild>
                            <Link
                                href={`/purchases/${inboxDocument.purchase_document.id}`}
                            >
                                <ShieldCheck /> Open{' '}
                                {inboxDocument.purchase_document.number}
                            </Link>
                        </Button>
                    </section>
                ) : !closed ? (
                    <>
                        <AssignmentForm
                            document={inboxDocument}
                            suppliers={suppliers}
                            orders={availableOrders}
                            classification={classification}
                            supplierId={supplierId}
                            orderId={orderId}
                            setClassification={setClassification}
                            changeSupplier={changeSupplier}
                            setOrderId={setOrderId}
                        />
                        <ConversionForm
                            document={inboxDocument}
                            extracted={extracted}
                            suppliers={suppliers}
                            orders={availableOrders}
                            classification={classification}
                            supplierId={supplierId}
                            orderId={orderId}
                            setClassification={setClassification}
                            changeSupplier={changeSupplier}
                            setOrderId={setOrderId}
                        />
                        <RejectForm document={inboxDocument} />
                    </>
                ) : (
                    <p className="border-y py-5 text-sm text-muted-foreground">
                        This document was rejected and retained for audit.
                    </p>
                )}
            </div>
        </>
    );
}

type WorkflowProps = {
    document: InboxDocument;
    suppliers: Supplier[];
    orders: PurchaseOrder[];
    classification: string;
    supplierId: string;
    orderId: string;
    setClassification: (value: string) => void;
    changeSupplier: (value: string) => void;
    setOrderId: (value: string) => void;
};

function AssignmentForm(props: WorkflowProps) {
    return (
        <section className="border-t pt-5">
            <h2 className="font-semibold">Classification and assignment</h2>
            <p className="text-sm text-muted-foreground">
                Save progress before the accounting document is created.
            </p>
            <Form
                action={`/purchase-inbox/${props.document.id}`}
                method="patch"
                className="mt-4 grid gap-4"
            >
                {({ errors, processing }) => (
                    <>
                        <WorkflowFields {...props} />
                        <div className="grid gap-2">
                            <Label>Internal notes</Label>
                            <textarea
                                name="notes"
                                defaultValue={props.document.notes || ''}
                                className="min-h-20 rounded-md border bg-transparent p-3 text-sm"
                            />
                        </div>
                        <InputError
                            message={
                                errors.purchase_order_id || errors.supplier_id
                            }
                        />
                        <Button
                            className="w-fit"
                            variant="outline"
                            disabled={processing}
                        >
                            <Save /> Save assignment
                        </Button>
                    </>
                )}
            </Form>
        </section>
    );
}

function ConversionForm(
    props: WorkflowProps & { extracted: Record<string, string | number> },
) {
    const supplier = props.suppliers.find(
        (item) => item.id === Number(props.supplierId),
    );
    const order = props.orders.find(
        (item) => item.id === Number(props.orderId),
    );
    const absolute = (value: string | number | undefined) =>
        value === undefined || value === ''
            ? ''
            : String(Math.abs(Number(value)));

    return (
        <section className="border-t pt-5">
            <h2 className="font-semibold">Create accounting document</h2>
            <p className="text-sm text-muted-foreground">
                Confirm every value. Conversion posts the supplier document to
                accounting.
            </p>
            <Form
                action={`/purchase-inbox/${props.document.id}/convert`}
                method="post"
                className="mt-4 grid gap-4"
            >
                {({ errors, processing }) => (
                    <>
                        <input
                            type="hidden"
                            name="classification"
                            value={props.classification}
                        />
                        <input
                            type="hidden"
                            name="supplier_id"
                            value={props.supplierId}
                        />
                        <input
                            type="hidden"
                            name="purchase_order_id"
                            value={props.orderId}
                        />
                        <div className="grid border-y text-sm sm:grid-cols-3">
                            <Info
                                label="Document type"
                                value={props.classification.replaceAll(
                                    '_',
                                    ' ',
                                )}
                            />
                            <Info
                                label="Supplier"
                                value={
                                    supplier?.trade_name ||
                                    supplier?.legal_name ||
                                    'Not selected'
                                }
                            />
                            <Info
                                label="Purchase order"
                                value={order?.number || 'Not linked'}
                            />
                        </div>
                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            <Field
                                label="Supplier document number"
                                name="supplier_number"
                                value={String(
                                    props.extracted.invoice_number || '',
                                )}
                                error={errors.supplier_number}
                            />
                            <Field
                                label="Issue date"
                                name="issue_date"
                                type="date"
                                value={String(
                                    props.extracted.issue_date || today(),
                                )}
                                error={errors.issue_date}
                            />
                            <Field
                                label="Due date"
                                name="due_date"
                                type="date"
                                value={String(props.extracted.due_date || '')}
                                error={errors.due_date}
                            />
                            <Field
                                label="Subtotal"
                                name="subtotal"
                                type="number"
                                value={absolute(props.extracted.subtotal)}
                                error={errors.subtotal}
                            />
                            <Field
                                label="Transport (included in subtotal)"
                                name="shipping_cost"
                                type="number"
                                value={
                                    absolute(props.extracted.shipping_cost) ||
                                    '0'
                                }
                                error={errors.shipping_cost}
                            />
                            <Field
                                label="Tax"
                                name="tax_amount"
                                type="number"
                                value={absolute(props.extracted.tax_amount)}
                                error={errors.tax_amount}
                            />
                            <Field
                                label="Total"
                                name="total"
                                type="number"
                                value={absolute(props.extracted.total)}
                                error={errors.total}
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label>Accounting notes</Label>
                            <textarea
                                name="notes"
                                defaultValue={props.document.notes || ''}
                                className="min-h-20 rounded-md border bg-transparent p-3 text-sm"
                            />
                        </div>
                        <InputError
                            message={
                                errors.accounting ||
                                errors.purchase_order_id ||
                                errors.supplier_id
                            }
                        />
                        <Button
                            className="w-fit"
                            disabled={
                                processing ||
                                props.classification === 'unknown' ||
                                props.supplierId === 'none'
                            }
                        >
                            <ShieldCheck /> Convert and post
                        </Button>
                    </>
                )}
            </Form>
        </section>
    );
}

function WorkflowFields({
    suppliers,
    orders,
    classification,
    supplierId,
    orderId,
    setClassification,
    changeSupplier,
    setOrderId,
    allowUnknown = true,
}: WorkflowProps & { allowUnknown?: boolean }) {
    return (
        <div className="grid gap-4 sm:grid-cols-3">
            <SelectField
                label="Document type"
                name="classification"
                value={classification}
                onChange={setClassification}
                options={[
                    ...(allowUnknown ? [['unknown', 'Unclassified']] : []),
                    ['supplier_invoice', 'Supplier invoice'],
                    ['supplier_credit_note', 'Supplier credit note'],
                ]}
            />
            <SelectField
                label="Supplier"
                name="supplier_id"
                value={supplierId}
                onChange={changeSupplier}
                options={[
                    ['none', 'Select supplier'],
                    ...suppliers.map((supplier) => [
                        String(supplier.id),
                        supplier.trade_name || supplier.legal_name,
                    ]),
                ]}
            />
            <SelectField
                label="Purchase order (optional)"
                name="purchase_order_id"
                value={orderId}
                onChange={setOrderId}
                disabled={classification === 'supplier_credit_note'}
                options={[
                    ['none', 'No purchase order'],
                    ...orders.map((order) => [
                        String(order.id),
                        `${order.number} · ${money(Number(order.amount))}`,
                    ]),
                ]}
            />
        </div>
    );
}

function RejectForm({ document }: { document: InboxDocument }) {
    return (
        <section className="border-t pt-5">
            <h2 className="font-semibold">Reject document</h2>
            <Form
                action={`/purchase-inbox/${document.id}/reject`}
                method="post"
                className="mt-3 flex flex-col items-start gap-3 sm:flex-row"
            >
                <div className="grid w-full gap-2">
                    <Label>Reason</Label>
                    <Input
                        name="notes"
                        required
                        placeholder="Duplicate, not an accounting document…"
                    />
                </div>
                <Button variant="destructive" className="sm:mt-6">
                    <X /> Reject
                </Button>
            </Form>
        </section>
    );
}

function Field({
    label,
    name,
    value,
    type = 'text',
    error,
}: {
    label: string;
    name: string;
    value: string;
    type?: string;
    error?: string;
}) {
    return (
        <div className="grid gap-2">
            <Label>{label}</Label>
            <Input
                name={name}
                type={type}
                step={type === 'number' ? '0.01' : undefined}
                min={type === 'number' ? '0' : undefined}
                defaultValue={value}
                required={name !== 'due_date'}
            />
            <InputError message={error} />
        </div>
    );
}
function SelectField({
    label,
    name,
    value,
    onChange,
    options,
    disabled = false,
}: {
    label: string;
    name: string;
    value: string;
    onChange: (value: string) => void;
    options: string[][];
    disabled?: boolean;
}) {
    return (
        <div className="grid gap-2">
            <Label>{label}</Label>
            <Select
                name={name}
                value={disabled ? 'none' : value}
                onValueChange={onChange}
                disabled={disabled}
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
            {disabled && <input type="hidden" name={name} value="none" />}
        </div>
    );
}
function Info({ label, value }: { label: string; value: string }) {
    return (
        <div className="border-b p-3 last:border-0 sm:border-r">
            <div className="text-muted-foreground">{label}</div>
            <div className="mt-1 font-medium">{value}</div>
        </div>
    );
}
const formatMoney = (value: string | number | undefined) =>
    value === undefined || value === '' ? '—' : money(Number(value));
const formatDate = (value: string | number | undefined) =>
    !value
        ? '—'
        : new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(
              new Date(`${String(value).slice(0, 10)}T12:00:00`),
          );
const money = (value: number) =>
    new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: 'EUR',
    }).format(value);
const date = (value: string) =>
    new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(
        new Date(value),
    );
const today = () => new Date().toISOString().slice(0, 10);
const fileSize = (bytes: number) =>
    bytes < 1024
        ? `${bytes} B`
        : bytes < 1024 * 1024
          ? `${(bytes / 1024).toFixed(1)} KB`
          : `${(bytes / 1024 / 1024).toFixed(1)} MB`;
PurchaseInboxShow.layout = {
    breadcrumbs: [
        { title: 'Document inbox', href: '/purchase-inbox' },
        { title: 'Review', href: '#' },
    ],
};

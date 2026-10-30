import { Link } from '@inertiajs/react';
import { useState } from 'react';
import { date, labels, money } from '@/components/billing/workspace';
import Form from '@/components/finance/protected-form';
import Dialog from '@/components/projects/protected-dialog';
import { Button } from '@/components/ui/button';
import {
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Textarea } from '@/components/ui/textarea';

export type CustomerReceipt = {
    id: number;
    paid_at: string;
    amount: string;
    method: string;
    reference: string | null;
    cancellation_revision?: string;
    origin_bank_transaction_id?: number | null;
    reconciliation?: { id: number; bank_transaction_id: number } | null;
    accounting_entry?: { id: number; number: string } | null;
};

export function CancelCustomerReceipt({
    receipt,
    invoiceId,
    invoiceNumber,
    customer,
    allowed,
}: {
    receipt: CustomerReceipt;
    invoiceId: number;
    invoiceNumber: string;
    customer: string;
    allowed: boolean;
}) {
    const [open, setOpen] = useState(false);
    const [key, setKey] = useState('');
    const bankLinked = Boolean(
        receipt.reconciliation || receipt.origin_bank_transaction_id,
    );

    return (
        <Dialog
            open={open}
            onOpenChange={(value) => {
                if (value && !key) {
                    setKey(crypto.randomUUID());
                }

                setOpen(value);
            }}
        >
            <DialogTrigger asChild>
                <Button variant="outline" size="sm">
                    Ver cobro
                </Button>
            </DialogTrigger>
            <DialogContent className="finance-mobile-dialog max-h-[90dvh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>Detalle del cobro</DialogTitle>
                </DialogHeader>
                <dl className="grid grid-cols-2 gap-3 text-sm">
                    <dt>Referencia del cobro</dt>
                    <dd>{receipt.reference || `#${receipt.id}`}</dd>
                    <dt>Cliente</dt>
                    <dd>{customer}</dd>
                    <dt>Factura</dt>
                    <dd>{invoiceNumber}</dd>
                    <dt>Importe</dt>
                    <dd>{money(receipt.amount)}</dd>
                    <dt>Fecha original</dt>
                    <dd>{date(receipt.paid_at)}</dd>
                    <dt>Medio de cobro</dt>
                    <dd>{labels[receipt.method] || receipt.method}</dd>
                    <dt>Conciliación</dt>
                    <dd>
                        {receipt.reconciliation
                            ? 'Conciliado'
                            : receipt.origin_bank_transaction_id
                              ? 'Vinculado al banco'
                              : 'Sin conciliación registrada'}
                    </dd>
                </dl>
                {receipt.accounting_entry && (
                    <Link
                        href={`/accounting/entries/${receipt.accounting_entry.id}`}
                        className="underline"
                    >
                        {receipt.accounting_entry.number}
                    </Link>
                )}
                {bankLinked && (
                    <p role="status" className="text-sm">
                        Este cobro está vinculado al banco. La anulación
                        requiere una corrección bancaria auditada; se conserva
                        la conciliación original.
                    </p>
                )}
                {allowed && !bankLinked && receipt.cancellation_revision && (
                    <Form
                        action={`/sales/${invoiceId}/payments/${receipt.id}/cancel`}
                        method="post"
                        onSuccess={() => setOpen(false)}
                    >
                        {({ processing, errors }) => (
                            <div className="space-y-4">
                                <input
                                    type="hidden"
                                    name="operation_key"
                                    value={key}
                                />
                                <input
                                    type="hidden"
                                    name="revision"
                                    value={receipt.cancellation_revision}
                                />
                                <p className="text-sm">
                                    Se conservará el cobro original, se
                                    revertirán sus asientos en periodos abiertos
                                    y se recalculará el saldo. Esta acción no
                                    devuelve dinero al cliente ni realiza un
                                    pago bancario.
                                </p>
                                <label className="block text-sm">
                                    Motivo de anulación
                                    <Textarea
                                        name="reason"
                                        required
                                        minLength={5}
                                        maxLength={2000}
                                    />
                                </label>
                                <label className="flex items-start gap-2 text-sm">
                                    <input
                                        type="checkbox"
                                        name="confirmed"
                                        value="1"
                                        required
                                    />
                                    Confirmo expresamente la anulación de este
                                    cobro.
                                </label>
                                {Object.entries(errors).map(
                                    ([field, message]) => (
                                        <p
                                            role="alert"
                                            className="text-sm text-destructive"
                                            key={field}
                                        >
                                            {message}
                                        </p>
                                    ),
                                )}
                                <Button
                                    type="submit"
                                    variant="destructive"
                                    disabled={processing || !key}
                                >
                                    Anular cobro
                                </Button>
                            </div>
                        )}
                    </Form>
                )}
            </DialogContent>
        </Dialog>
    );
}

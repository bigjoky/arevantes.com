import { Form, Head, Link, useForm } from '@inertiajs/react';
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

type Quote = {
    id: number;
    number: string;
    version: number;
    status: string;
    approval_status: string;
    approval_notes: string | null;
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
    lines: {
        id: number;
        description: string;
        quantity: string;
        unit_price: string;
        discount_percent: string;
        tax_percent: string;
        subtotal: string;
        total: string;
    }[];
    deliveries: {
        id: number;
        recipient_email: string;
        status: string;
        sent_at: string | null;
        first_viewed_at: string | null;
        responded_at: string | null;
    }[];
    versions: Array<{
        id: number;
        version: number;
        subtotal: string;
        discount_amount: string;
        tax_amount: string;
        amount: string;
        expected_margin_amount: string | null;
        created_at: string;
    }>;
};
type ProjectDefaults = {
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
    documents: Array<{
        id: number;
        number: string;
        status: string;
        billing_percentage: number;
        billing_sequence: number | null;
        billing_label: string | null;
        amount: number;
        issue_date: string;
    }>;
};

export default function QuoteShow({
    quote,
    billing,
    technicians,
    projectDefaults,
}: {
    quote: Quote;
    billing: BillingPlan;
    technicians: Array<{ id: number; name: string }>;
    projectDefaults: ProjectDefaults;
}) {
    const [projectOpen, setProjectOpen] = useState(false);
    const [billingOpen, setBillingOpen] = useState(false);
    const [billingPercentage, setBillingPercentage] = useState(
        String(billing.remaining_percentage),
    );
    const [billingLabel, setBillingLabel] = useState('Final invoice');
    const projectForm = useForm({
        service_type: 'solar',
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
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <header className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <div className="flex items-center gap-2">
                            <h1 className="text-xl font-semibold">
                                {quote.number}
                            </h1>
                            <Badge variant="outline">{quote.status}</Badge>
                        </div>
                        <p className="text-sm text-muted-foreground">
                            {customer} · version {quote.version}
                        </p>
                        {quote.opportunity && (
                            <Link
                                href={`/opportunities?opportunity=${quote.opportunity.id}`}
                                className="text-xs text-muted-foreground underline-offset-4 hover:underline"
                            >
                                {quote.opportunity.code} ·{' '}
                                {quote.opportunity.title}
                            </Link>
                        )}
                    </div>
                    <div className="flex gap-2">
                        <Button variant="outline" asChild>
                            <Link href="/operations">
                                <ArrowLeft /> Operations
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
                        {quote.status === 'draft' && (
                            <Button asChild>
                                <Link href={`/quotes/${quote.id}/edit`}>
                                    <Pencil /> Edit
                                </Link>
                            </Button>
                        )}
                    </div>
                </header>
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
                                label="Expected margin"
                                value={
                                    quote.expected_margin_amount
                                        ? money(quote.expected_margin_amount)
                                        : '—'
                                }
                            />
                            <Info
                                label="Actual margin"
                                value={
                                    quote.actual_margin_amount
                                        ? money(quote.actual_margin_amount)
                                        : 'Pending costs'
                                }
                            />
                        </div>
                        {quote.approval_status === 'pending' && (
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
                                    <Button name="decision" value="approved">
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
                        {quote.approval_notes && (
                            <p className="mt-3 text-xs text-muted-foreground">
                                {quote.approval_notes}
                            </p>
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
                                    <span>{dateTime(version.created_at)}</span>
                                    <span className="font-medium tabular-nums">
                                        {money(version.amount)}
                                    </span>
                                </div>
                            ))}
                        </div>
                    </div>
                </section>
                <section className="overflow-x-auto">
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
                                        </div>
                                    </div>
                                    <div className="text-right">
                                        <div className="font-medium">
                                            {money(document.amount)}
                                        </div>
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
                    <Form
                        action={`/quotes/${quote.id}/send`}
                        method="post"
                        className="grid content-start gap-3"
                    >
                        <div>
                            <h2 className="font-semibold">Send to customer</h2>
                            <p className="text-sm text-muted-foreground">
                                Email the PDF and a secure acceptance link.
                            </p>
                        </div>
                        <div className="grid gap-2">
                            <label className="text-sm font-medium">
                                Recipient email
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
                            <Send /> Send quote
                        </Button>
                    </Form>
                    <DeliveryHistory deliveries={quote.deliveries} />
                </section>
                <footer className="flex flex-wrap items-end justify-between gap-4 border-t pt-5">
                    {quote.status === 'accepted' && (
                        <div className="flex flex-wrap gap-2">
                            {!quote.installation && (
                                <Button
                                    type="button"
                                    onClick={() => setProjectOpen(true)}
                                >
                                    <Wrench /> Start project
                                </Button>
                            )}
                            {[
                                ['order', 'Create order'],
                                ['delivery_note', 'Create delivery note'],
                            ].map(([type, label]) => (
                                <Form
                                    key={type}
                                    action={`/quotes/${quote.id}/convert`}
                                    method="post"
                                >
                                    <input
                                        type="hidden"
                                        name="type"
                                        value={type}
                                    />
                                    <Button variant="outline">{label}</Button>
                                </Form>
                            ))}
                        </div>
                    )}
                    <Form
                        action={`/quotes/${quote.id}/status`}
                        method="patch"
                        className="flex items-end gap-2"
                    >
                        {({ processing }) => (
                            <>
                                <div className="grid gap-2">
                                    <label className="text-sm font-medium">
                                        Status
                                    </label>
                                    <Select
                                        name="status"
                                        defaultValue={quote.status}
                                    >
                                        <SelectTrigger className="w-44">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {[
                                                'draft',
                                                'sent',
                                                'accepted',
                                                'rejected',
                                                'expired',
                                            ].map((status) => (
                                                <SelectItem
                                                    key={status}
                                                    value={status}
                                                >
                                                    {status}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                                <Button disabled={processing}>Update</Button>
                            </>
                        )}
                    </Form>
                    <Form
                        action={`/quotes/${quote.id}`}
                        method="delete"
                        onBefore={() => confirm(`Delete ${quote.number}?`)}
                    >
                        <Button variant="destructive">
                            <Trash2 /> Delete
                        </Button>
                    </Form>
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
                                    projectForm.setData('service_type', value)
                                }
                                options={[
                                    ['solar', 'Solar photovoltaic'],
                                    ['ev_charger', 'EV charging'],
                                    ['hvac', 'Air conditioning / HVAC'],
                                    ['electrical', 'Electrical installation'],
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
                                        Add billing milestone
                                    </DialogTitle>
                                    <DialogDescription>
                                        {billing.remaining_percentage}% of the
                                        accepted quote remains available.
                                    </DialogDescription>
                                </DialogHeader>
                                <input
                                    type="hidden"
                                    name="type"
                                    value="invoice"
                                />
                                <div className="grid grid-cols-3 gap-2">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() => {
                                            setBillingPercentage(
                                                String(
                                                    Math.min(
                                                        30,
                                                        billing.remaining_percentage,
                                                    ),
                                                ),
                                            );
                                            setBillingLabel('Advance payment');
                                        }}
                                    >
                                        Advance
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() => {
                                            setBillingPercentage(
                                                String(
                                                    Math.min(
                                                        40,
                                                        billing.remaining_percentage,
                                                    ),
                                                ),
                                            );
                                            setBillingLabel('Progress payment');
                                        }}
                                    >
                                        Progress
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() => {
                                            setBillingPercentage(
                                                String(
                                                    billing.remaining_percentage,
                                                ),
                                            );
                                            setBillingLabel('Final invoice');
                                        }}
                                    >
                                        Final
                                    </Button>
                                </div>
                                <div className="grid gap-4 sm:grid-cols-[9rem_1fr]">
                                    <div className="grid gap-2">
                                        <Label htmlFor="billing_percentage">
                                            Percentage
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
                                                    setBillingPercentage(
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
                                        <Label htmlFor="billing_label">
                                            Milestone name
                                        </Label>
                                        <Input
                                            id="billing_label"
                                            name="billing_label"
                                            value={billingLabel}
                                            onChange={(event) =>
                                                setBillingLabel(
                                                    event.target.value,
                                                )
                                            }
                                            required
                                        />
                                        <InputError
                                            message={errors.billing_label}
                                        />
                                    </div>
                                </div>
                                <div className="flex items-center justify-between border-y py-3 text-sm">
                                    <span className="text-muted-foreground">
                                        Estimated milestone total
                                    </span>
                                    <span className="font-semibold">
                                        {money(
                                            (Number(quote.amount) *
                                                Number(
                                                    billingPercentage || 0,
                                                )) /
                                                100,
                                        )}
                                    </span>
                                </div>
                                <DialogFooter>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() => setBillingOpen(false)}
                                    >
                                        Cancel
                                    </Button>
                                    <Button disabled={processing}>
                                        <CircleDollarSign /> Create draft
                                    </Button>
                                </DialogFooter>
                            </div>
                        )}
                    </Form>
                </DialogContent>
            </Dialog>
        </>
    );
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
                        <Badge variant="outline">{delivery.status}</Badge>
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
function money(value: string | number) {
    return new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: 'EUR',
    }).format(Number(value));
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
        { title: 'Operations', href: '/operations' },
        { title: 'Quote', href: '#' },
    ],
};

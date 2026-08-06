import { Form, Head, Link } from '@inertiajs/react';
import {
    ArrowRight,
    FileText,
    Inbox,
    Mail,
    RefreshCw,
    Settings2,
    Upload,
} from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
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

type Mailbox = {
    id: number;
    name: string;
    email: string;
    host?: string;
    port?: number;
    encryption?: string;
    validate_certificate?: boolean;
    username?: string;
    folder?: string;
    lookback_days?: number;
    auto_extract: boolean;
    is_active: boolean;
    last_sync_attempt_at: string | null;
    last_synced_at: string | null;
    last_error: string | null;
    messages_count: number;
    imported_messages_count: number;
    failed_messages_count: number;
};

type InboxDocument = {
    id: number;
    original_name: string;
    classification: string;
    status: string;
    extraction_status: string;
    source: string;
    extracted_data: Record<string, string | number> | null;
    created_at: string;
    supplier: { legal_name: string; trade_name: string | null } | null;
    purchase_order: { id: number; number: string } | null;
    purchase_document: { id: number; number: string } | null;
};

export default function PurchaseInboxIndex({
    documents,
    counts,
    activeStatus,
    mailbox,
    canConfigureMailbox,
}: {
    documents: InboxDocument[];
    counts: Record<string, number>;
    activeStatus: string;
    mailbox: Mailbox | null;
    canConfigureMailbox: boolean;
}) {
    return (
        <>
            <Head title="Document inbox" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <header className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold">
                            Document inbox
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Review incoming supplier invoices before posting.
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {canConfigureMailbox && (
                            <MailboxDialog mailbox={mailbox} />
                        )}
                        <UploadDialog />
                    </div>
                </header>
                {(mailbox || canConfigureMailbox) && (
                    <MailboxStatus mailbox={mailbox} />
                )}
                <section className="grid grid-cols-2 border-y sm:grid-cols-4">
                    {[
                        ['pending_review', 'To review'],
                        ['ready', 'Ready'],
                        ['converted', 'Converted'],
                        ['rejected', 'Rejected'],
                    ].map(([status, label]) => (
                        <Link
                            key={status}
                            href={`/purchase-inbox?status=${status}`}
                            className={`border-r p-4 last:border-0 ${activeStatus === status ? 'bg-muted' : ''}`}
                        >
                            <div className="text-sm text-muted-foreground">
                                {label}
                            </div>
                            <div className="mt-1 text-2xl font-semibold">
                                {counts[status] ?? 0}
                            </div>
                        </Link>
                    ))}
                </section>
                <div className="flex items-center gap-2 text-sm">
                    <Button
                        variant={activeStatus === 'all' ? 'secondary' : 'ghost'}
                        size="sm"
                        asChild
                    >
                        <Link href="/purchase-inbox">All documents</Link>
                    </Button>
                </div>
                <section className="overflow-x-auto">
                    <table className="w-full min-w-[54rem] text-sm">
                        <thead>
                            <tr className="border-b text-left text-muted-foreground">
                                <th className="py-2">Document</th>
                                <th>Detected data</th>
                                <th>Supplier</th>
                                <th>Purchase order</th>
                                <th>Received</th>
                                <th>Status</th>
                                <th className="w-12" />
                            </tr>
                        </thead>
                        <tbody>
                            {documents.map((document) => (
                                <tr key={document.id} className="border-b">
                                    <td className="py-3">
                                        <Link
                                            href={`/purchase-inbox/${document.id}`}
                                            className="font-medium underline underline-offset-2"
                                        >
                                            {document.original_name}
                                        </Link>
                                        <div className="mt-1 text-xs text-muted-foreground capitalize">
                                            {document.classification.replaceAll(
                                                '_',
                                                ' ',
                                            )}{' '}
                                            · {document.extraction_status} ·{' '}
                                            {document.source}
                                        </div>
                                    </td>
                                    <td>
                                        <div>
                                            {String(
                                                document.extracted_data
                                                    ?.invoice_number || '—',
                                            )}
                                        </div>
                                        <div className="text-xs text-muted-foreground">
                                            {document.extracted_data?.total !==
                                            undefined
                                                ? money(
                                                      Number(
                                                          document
                                                              .extracted_data
                                                              .total,
                                                      ),
                                                  )
                                                : 'Not extracted'}
                                        </div>
                                    </td>
                                    <td>
                                        {document.supplier?.trade_name ||
                                            document.supplier?.legal_name ||
                                            '—'}
                                    </td>
                                    <td>
                                        {document.purchase_order?.number || '—'}
                                    </td>
                                    <td>{date(document.created_at)}</td>
                                    <td>
                                        <StatusBadge status={document.status} />
                                    </td>
                                    <td>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            asChild
                                        >
                                            <Link
                                                href={`/purchase-inbox/${document.id}`}
                                                aria-label="Review document"
                                            >
                                                <ArrowRight />
                                            </Link>
                                        </Button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    {documents.length === 0 && (
                        <div className="flex flex-col items-center gap-2 py-14 text-sm text-muted-foreground">
                            <Inbox className="size-6" />
                            No documents in this view.
                        </div>
                    )}
                </section>
            </div>
        </>
    );
}

function MailboxStatus({ mailbox }: { mailbox: Mailbox | null }) {
    if (!mailbox) {
        return (
            <section className="flex items-center gap-3 border-y py-4 text-sm text-muted-foreground">
                <Mail className="size-5" />
                No supplier mailbox configured.
            </section>
        );
    }

    return (
        <section className="flex flex-wrap items-center justify-between gap-4 border-y py-4 text-sm">
            <div className="flex min-w-0 items-center gap-3">
                <Mail className="size-5 shrink-0" />
                <div className="min-w-0">
                    <div className="truncate font-medium">
                        {mailbox.name} · {mailbox.email}
                    </div>
                    <div className="text-muted-foreground">
                        {mailbox.last_synced_at
                            ? `Last synchronized ${dateTime(mailbox.last_synced_at)}`
                            : 'Not synchronized yet'}
                        {' · '}
                        {mailbox.imported_messages_count} messages imported
                        {mailbox.failed_messages_count > 0 &&
                            ` · ${mailbox.failed_messages_count} failed`}
                    </div>
                    {mailbox.last_error && (
                        <div className="mt-1 text-destructive">
                            {mailbox.last_error}
                        </div>
                    )}
                </div>
            </div>
            <Form action="/purchase-inbox/mailbox/sync" method="post">
                {({ processing }) => (
                    <Button
                        variant="outline"
                        disabled={processing || !mailbox.is_active}
                    >
                        <RefreshCw
                            className={processing ? 'animate-spin' : ''}
                        />
                        Sync mailbox
                    </Button>
                )}
            </Form>
        </section>
    );
}

function MailboxDialog({ mailbox }: { mailbox: Mailbox | null }) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="outline">
                    <Settings2 /> Mailbox
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Supplier mailbox</DialogTitle>
                </DialogHeader>
                <Form
                    action="/purchase-inbox/mailbox"
                    method="put"
                    onSuccess={() => setOpen(false)}
                    className="grid gap-4"
                >
                    {({ errors, processing }) => (
                        <>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <MailboxField
                                    label="Name"
                                    name="name"
                                    value={mailbox?.name || 'Supplier invoices'}
                                    error={errors.name}
                                />
                                <MailboxField
                                    label="Email address"
                                    name="email"
                                    type="email"
                                    value={mailbox?.email || ''}
                                    error={errors.email}
                                />
                                <MailboxField
                                    label="IMAP host"
                                    name="host"
                                    value={mailbox?.host || ''}
                                    placeholder="imap.example.com"
                                    error={errors.host}
                                />
                                <MailboxField
                                    label="Port"
                                    name="port"
                                    type="number"
                                    value={String(mailbox?.port || 993)}
                                    error={errors.port}
                                />
                                <div className="grid gap-2">
                                    <Label>Encryption</Label>
                                    <Select
                                        name="encryption"
                                        defaultValue={
                                            mailbox?.encryption || 'ssl'
                                        }
                                    >
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="ssl">
                                                SSL
                                            </SelectItem>
                                            <SelectItem value="tls">
                                                TLS
                                            </SelectItem>
                                            <SelectItem value="none">
                                                None
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <InputError message={errors.encryption} />
                                </div>
                                <MailboxField
                                    label="Folder"
                                    name="folder"
                                    value={mailbox?.folder || 'INBOX'}
                                    error={errors.folder}
                                />
                                <MailboxField
                                    label="Username"
                                    name="username"
                                    value={mailbox?.username || ''}
                                    error={errors.username}
                                />
                                <MailboxField
                                    label="Password"
                                    name="password"
                                    type="password"
                                    value=""
                                    placeholder={
                                        mailbox
                                            ? 'Leave blank to keep current password'
                                            : ''
                                    }
                                    required={!mailbox}
                                    error={errors.password}
                                />
                                <MailboxField
                                    label="Initial lookback (days)"
                                    name="lookback_days"
                                    type="number"
                                    value={String(mailbox?.lookback_days || 30)}
                                    error={errors.lookback_days}
                                />
                            </div>
                            <div className="grid gap-3 border-y py-4 sm:grid-cols-3">
                                <CheckField
                                    name="validate_certificate"
                                    label="Validate certificate"
                                    checked={
                                        mailbox?.validate_certificate ?? true
                                    }
                                />
                                <CheckField
                                    name="auto_extract"
                                    label="Extract automatically"
                                    checked={mailbox?.auto_extract ?? true}
                                />
                                <CheckField
                                    name="is_active"
                                    label="Mailbox active"
                                    checked={mailbox?.is_active ?? true}
                                />
                            </div>
                            <Button disabled={processing}>
                                <Settings2 /> Save mailbox
                            </Button>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function MailboxField({
    label,
    name,
    value,
    error,
    type = 'text',
    placeholder,
    required = true,
}: {
    label: string;
    name: string;
    value: string;
    error?: string;
    type?: string;
    placeholder?: string;
    required?: boolean;
}) {
    return (
        <div className="grid gap-2">
            <Label>{label}</Label>
            <Input
                name={name}
                type={type}
                defaultValue={value}
                placeholder={placeholder}
                required={required}
            />
            <InputError message={error} />
        </div>
    );
}

function CheckField({
    name,
    label,
    checked,
}: {
    name: string;
    label: string;
    checked: boolean;
}) {
    return (
        <label className="flex items-center gap-2 text-sm">
            <Checkbox name={name} value="1" defaultChecked={checked} />
            {label}
        </label>
    );
}

function UploadDialog() {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button>
                    <Upload /> Add documents
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Add supplier documents</DialogTitle>
                </DialogHeader>
                <Form
                    action="/purchase-inbox"
                    method="post"
                    onSuccess={() => setOpen(false)}
                    className="grid gap-4"
                >
                    {({ errors, processing }) => (
                        <>
                            <div className="grid gap-2">
                                <label className="text-sm font-medium">
                                    PDF or images
                                </label>
                                <Input
                                    name="documents[]"
                                    type="file"
                                    accept="application/pdf,image/jpeg,image/png,image/webp"
                                    multiple
                                    required
                                />
                                <p className="text-xs text-muted-foreground">
                                    Up to 10 files, 15 MB each.
                                </p>
                                <InputError message={errors.documents} />
                            </div>
                            <Button disabled={processing}>
                                <FileText /> Add to inbox
                            </Button>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function StatusBadge({ status }: { status: string }) {
    return (
        <Badge
            variant={
                status === 'ready'
                    ? 'secondary'
                    : status === 'rejected'
                      ? 'destructive'
                      : 'outline'
            }
        >
            {status.replaceAll('_', ' ')}
        </Badge>
    );
}

const money = (value: number) =>
    new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: 'EUR',
    }).format(value);
const date = (value: string) =>
    new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(
        new Date(value),
    );
const dateTime = (value: string) =>
    new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
PurchaseInboxIndex.layout = {
    breadcrumbs: [{ title: 'Document inbox', href: '/purchase-inbox' }],
};

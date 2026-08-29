import { Form, Head, Link } from '@inertiajs/react';
import {
    BatteryCharging,
    CircleDollarSign,
    ClipboardList,
    FileText,
    KeyRound,
    ListTodo,
    PhoneCall,
    PlugZap,
    Snowflake,
    ShieldCheck,
    Sun,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useState } from 'react';
import { statusLabels } from '@/components/installations/installation-form';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
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
import { serviceLabels } from '@/lib/business-lines';

type Installation = {
    id: number;
    service_type: keyof typeof serviceLabels;
    status: keyof typeof statusLabels;
    project_reference: string | null;
    site_name: string | null;
    site_city: string | null;
    site_country: string | null;
    scheduled_date: string | null;
    relationship?: string;
};

type Person = {
    id: number;
    category: 'customer' | 'staff';
    customer_type: string | null;
    first_name: string;
    last_name: string | null;
    company_name: string | null;
    tax_id: string | null;
    employee_advance_account_code?: string | null;
    employee_payable_account_code?: string | null;
    staff_role: string | null;
    document_type: string | null;
    document_number: string | null;
    phone: string | null;
    email: string | null;
    billing_email: string | null;
    address_line_1: string | null;
    address_line_2: string | null;
    city: string | null;
    locality: string | null;
    province_state: string | null;
    postal_code: string | null;
    country: string | null;
    service_interests: string[] | null;
    lead_source: string | null;
    lifecycle_status: string | null;
    preferred_contact_channel: string | null;
    do_not_contact: boolean;
    data_source: string | null;
    notes: string | null;
    contacts: Array<{
        id: number;
        first_name: string;
        last_name: string | null;
        position: string | null;
        email: string | null;
        phone: string | null;
        preferred_channel: string | null;
        is_primary: boolean;
    }>;
    tags: Array<{ id: number; name: string; color: string }>;
    consents: Array<{
        id: number;
        channel: string;
        status: string;
        lawful_basis: string;
        source: string | null;
        captured_at: string;
        recorded_by?: { name: string } | null;
    }>;
    installations: Installation[];
    portal_accesses?: {
        id: number;
        recipient_email: string;
        sent_at: string | null;
        first_accessed_at: string | null;
        last_accessed_at: string | null;
        expires_at: string;
        revoked_at: string | null;
    }[];
    access?: {
        id: number;
        email: string;
        role: string;
        created_at: string;
    } | null;
};

type PeopleShowProps = {
    person: Person;
    customer360?: Customer360;
    activityUsers?: Array<{ id: number; name: string; role: string }>;
    availableAccessUsers?: Array<{
        id: number;
        name: string;
        email: string;
        role: string;
    }>;
    availableTags?: Array<{ id: number; name: string; color: string }>;
    basePath?: string;
    showBackLabel?: string;
};

type Customer360 = {
    metrics: {
        billed: number;
        outstanding: number;
        open_activities: number;
        active_work_orders: number;
        open_pipeline: number;
        open_tickets: number;
        collected: number;
    };
    quotes: Array<{
        id: number;
        number: string;
        status: string;
        amount: number;
        date: string | null;
        installation: string | null;
    }>;
    documents: Array<{
        id: number;
        number: string;
        type: string;
        status: string;
        amount: number;
        outstanding: number;
        date: string | null;
    }>;
    timeline: Array<{
        id: string;
        kind:
            | 'quote'
            | 'document'
            | 'activity'
            | 'work_order'
            | 'opportunity'
            | 'payment'
            | 'ticket'
            | 'communication'
            | 'portal';
        title: string;
        subtitle: string;
        date: string | null;
        href: string;
    }>;
};

export default function PeopleShow({
    person,
    customer360,
    activityUsers = [],
    availableAccessUsers = [],
    availableTags = [],
    basePath = '/people',
    showBackLabel = 'All customers',
}: PeopleShowProps) {
    const displayName =
        person.company_name ||
        `${person.first_name} ${person.last_name || ''}`.trim();
    const isStaff = person.category === 'staff';

    return (
        <>
            <Head title={displayName} />

            <div className="flex min-w-0 flex-1 flex-col gap-6 p-4 md:p-6">
                <Card className="rounded-lg">
                    <CardHeader className="flex-col items-start justify-between gap-4 sm:flex-row">
                        <div>
                            <div className="flex flex-wrap items-center gap-2">
                                <CardTitle className="text-xl tracking-tight">
                                    {displayName}
                                </CardTitle>
                                <Badge variant="outline">
                                    {person.category}
                                </Badge>
                            </div>
                            <CardDescription>
                                {person.staff_role ||
                                    person.customer_type ||
                                    'Profile'}
                            </CardDescription>
                        </div>
                        <div className="flex w-full flex-wrap gap-2 sm:w-auto sm:justify-end">
                            {!isStaff && (
                                <>
                                    <ActivityDialog
                                        person={person}
                                        users={activityUsers}
                                    />
                                    <Button variant="outline" asChild>
                                        <Link
                                            href={`/quotes/create?customer=${person.id}`}
                                        >
                                            <FileText /> New quote
                                        </Link>
                                    </Button>
                                </>
                            )}
                            <Button variant="outline" asChild>
                                <Link href={basePath}>{showBackLabel}</Link>
                            </Button>
                            <Button asChild>
                                <Link href={`${basePath}/${person.id}/edit`}>
                                    Edit
                                </Link>
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent className="grid gap-6 lg:grid-cols-3">
                        <InfoBlock
                            title="Contact"
                            items={[
                                ['Email', person.email],
                                ['Phone', person.phone],
                                ['Billing email', person.billing_email],
                                ['Lead source', person.lead_source],
                            ]}
                        />
                        <InfoBlock
                            title="Identity"
                            items={[
                                ['ID type', person.document_type],
                                ['ID number', person.document_number],
                                ['Tax ID', person.tax_id],
                                ...(isStaff
                                    ? [
                                          [
                                              'Advance account',
                                              person.employee_advance_account_code ??
                                                  null,
                                          ] as [string, string | null],
                                          [
                                              'Employee expenses payable account',
                                              person.employee_payable_account_code ??
                                                  null,
                                          ] as [string, string | null],
                                      ]
                                    : []),
                            ]}
                        />
                        <InfoBlock
                            title="Address"
                            items={[
                                ['Address', person.address_line_1],
                                ['Address 2', person.address_line_2],
                                ['Municipality', person.city],
                                ['Locality', person.locality],
                                ['Province', person.province_state],
                                ['Postal code', person.postal_code],
                                ['Country', person.country],
                            ]}
                        />
                    </CardContent>
                </Card>

                {!isStaff && customer360 && (
                    <>
                        <section className="grid grid-cols-2 border-y xl:grid-cols-7">
                            <ProfileMetric
                                icon={CircleDollarSign}
                                label="Total billed"
                                value={money(customer360.metrics.billed)}
                            />
                            <ProfileMetric
                                icon={CircleDollarSign}
                                label="Outstanding"
                                value={money(customer360.metrics.outstanding)}
                                attention={customer360.metrics.outstanding > 0}
                            />
                            <ProfileMetric
                                icon={ListTodo}
                                label="Open activities"
                                value={String(
                                    customer360.metrics.open_activities,
                                )}
                            />
                            <ProfileMetric
                                icon={ClipboardList}
                                label="Active work orders"
                                value={String(
                                    customer360.metrics.active_work_orders,
                                )}
                            />
                            <ProfileMetric
                                icon={CircleDollarSign}
                                label="Open pipeline"
                                value={money(customer360.metrics.open_pipeline)}
                            />
                            <ProfileMetric
                                icon={CircleDollarSign}
                                label="Collected"
                                value={money(customer360.metrics.collected)}
                            />
                            <ProfileMetric
                                icon={ClipboardList}
                                label="Open tickets"
                                value={String(customer360.metrics.open_tickets)}
                            />
                        </section>

                        <div className="grid gap-8 border-b pb-6 xl:grid-cols-[minmax(0,1.15fr)_minmax(22rem,.85fr)]">
                            <section>
                                <div className="mb-3">
                                    <h2 className="font-semibold">
                                        Recent activity
                                    </h2>
                                    <p className="text-sm text-muted-foreground">
                                        Commercial and operational history for
                                        this customer.
                                    </p>
                                </div>
                                {customer360.timeline.length ? (
                                    <div className="divide-y border-y">
                                        {customer360.timeline.map((item) => (
                                            <Link
                                                key={item.id}
                                                href={item.href}
                                                className="grid gap-2 py-3 hover:bg-muted/40 sm:grid-cols-[1fr_auto] sm:px-2"
                                            >
                                                <div className="min-w-0">
                                                    <div className="flex items-center gap-2">
                                                        <Badge variant="outline">
                                                            {item.kind.replace(
                                                                '_',
                                                                ' ',
                                                            )}
                                                        </Badge>
                                                        <span className="truncate text-sm font-medium">
                                                            {item.title}
                                                        </span>
                                                    </div>
                                                    <p className="mt-1 truncate text-xs text-muted-foreground">
                                                        {item.subtitle}
                                                    </p>
                                                </div>
                                                <time className="text-xs text-muted-foreground">
                                                    {formatDate(item.date)}
                                                </time>
                                            </Link>
                                        ))}
                                    </div>
                                ) : (
                                    <div className="border-y py-8 text-sm text-muted-foreground">
                                        No commercial or operational activity
                                        yet.
                                    </div>
                                )}
                            </section>

                            <section>
                                <div className="mb-3 flex items-center justify-between gap-3">
                                    <div>
                                        <h2 className="font-semibold">
                                            Commercial documents
                                        </h2>
                                        <p className="text-sm text-muted-foreground">
                                            Latest quotes and sales documents.
                                        </p>
                                    </div>
                                    <div className="flex gap-2">
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            asChild
                                        >
                                            <Link
                                                href={`/opportunities?create=1&customer=${person.id}`}
                                            >
                                                Opportunity
                                            </Link>
                                        </Button>
                                        <Button size="sm" asChild>
                                            <Link
                                                href={`/quotes/create?customer=${person.id}`}
                                            >
                                                <FileText /> New
                                            </Link>
                                        </Button>
                                    </div>
                                </div>
                                <div className="divide-y border-y">
                                    {customer360.quotes
                                        .slice(0, 5)
                                        .map((quote) => (
                                            <Link
                                                key={`quote-${quote.id}`}
                                                href={`/quotes/${quote.id}`}
                                                className="flex items-center justify-between gap-3 py-3 hover:bg-muted/40 sm:px-2"
                                            >
                                                <div className="min-w-0">
                                                    <div className="truncate text-sm font-medium">
                                                        {quote.number}
                                                    </div>
                                                    <div className="truncate text-xs text-muted-foreground">
                                                        Quote · {quote.status}
                                                        {quote.installation
                                                            ? ` · ${quote.installation}`
                                                            : ''}
                                                    </div>
                                                </div>
                                                <span className="shrink-0 text-sm tabular-nums">
                                                    {money(quote.amount)}
                                                </span>
                                            </Link>
                                        ))}
                                    {customer360.documents
                                        .slice(0, 7)
                                        .map((document) => (
                                            <Link
                                                key={`document-${document.id}`}
                                                href={`/sales/${document.id}`}
                                                className="flex items-center justify-between gap-3 py-3 hover:bg-muted/40 sm:px-2"
                                            >
                                                <div className="min-w-0">
                                                    <div className="truncate text-sm font-medium">
                                                        {document.number}
                                                    </div>
                                                    <div className="truncate text-xs text-muted-foreground">
                                                        {document.type.replace(
                                                            '_',
                                                            ' ',
                                                        )}{' '}
                                                        · {document.status}
                                                    </div>
                                                </div>
                                                <span className="shrink-0 text-sm tabular-nums">
                                                    {money(document.amount)}
                                                </span>
                                            </Link>
                                        ))}
                                    {customer360.quotes.length === 0 &&
                                        customer360.documents.length === 0 && (
                                            <div className="py-8 text-sm text-muted-foreground">
                                                No commercial documents yet.
                                            </div>
                                        )}
                                </div>
                            </section>
                        </div>
                    </>
                )}

                {!isStaff && (
                    <CustomerCrmDetails
                        person={person}
                        availableTags={availableTags}
                    />
                )}

                <div className="grid gap-4 lg:grid-cols-[1fr_0.4fr]">
                    {!isStaff && (
                        <Card className="rounded-lg">
                            <CardHeader className="flex-row items-start justify-between gap-3">
                                <div>
                                    <CardTitle>Installations</CardTitle>
                                    <CardDescription>
                                        Work linked to this customer profile.
                                    </CardDescription>
                                </div>
                                <Button size="sm" asChild>
                                    <Link
                                        href={`/installations/create?customer=${person.id}`}
                                    >
                                        Add installation
                                    </Link>
                                </Button>
                            </CardHeader>
                            <CardContent>
                                {person.installations.length > 0 ? (
                                    <div className="overflow-hidden rounded-md border">
                                        <div className="divide-y">
                                            {person.installations.map(
                                                (installation) => (
                                                    <Link
                                                        key={installation.id}
                                                        href={`/installations/${installation.id}`}
                                                        className="grid gap-3 p-4 transition-colors hover:bg-muted/50 md:grid-cols-[1fr_auto]"
                                                    >
                                                        <div>
                                                            <div className="flex flex-wrap items-center gap-2">
                                                                <ServiceIcon
                                                                    service={
                                                                        installation.service_type
                                                                    }
                                                                />
                                                                <span className="font-medium">
                                                                    {
                                                                        serviceLabels[
                                                                            installation
                                                                                .service_type
                                                                        ]
                                                                    }
                                                                </span>
                                                                <Badge variant="outline">
                                                                    {
                                                                        statusLabels[
                                                                            installation
                                                                                .status
                                                                        ]
                                                                    }
                                                                </Badge>
                                                                {installation.relationship && (
                                                                    <Badge variant="secondary">
                                                                        {installation.relationship.replace(
                                                                            '_',
                                                                            ' ',
                                                                        )}
                                                                    </Badge>
                                                                )}
                                                            </div>
                                                            <div className="mt-1 text-sm text-muted-foreground">
                                                                {[
                                                                    installation.site_name,
                                                                    installation.site_city,
                                                                    installation.site_country,
                                                                ]
                                                                    .filter(
                                                                        Boolean,
                                                                    )
                                                                    .join(
                                                                        ', ',
                                                                    ) ||
                                                                    'No site'}
                                                            </div>
                                                        </div>
                                                        <div className="text-sm text-muted-foreground md:text-right">
                                                            {installation.project_reference ||
                                                                'No reference'}
                                                            <br />
                                                            {installation.scheduled_date ||
                                                                'No date'}
                                                        </div>
                                                    </Link>
                                                ),
                                            )}
                                        </div>
                                    </div>
                                ) : (
                                    <div className="rounded-md border border-dashed p-6">
                                        <p className="text-sm text-muted-foreground">
                                            No installations linked yet.
                                        </p>
                                        <Button
                                            className="mt-4"
                                            variant="outline"
                                            asChild
                                        >
                                            <Link
                                                href={`/installations/create?customer=${person.id}`}
                                            >
                                                Create first installation
                                            </Link>
                                        </Button>
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    )}

                    {isStaff && (
                        <Card className="rounded-lg">
                            <CardHeader>
                                <div className="flex items-center gap-2">
                                    <KeyRound className="size-5" />
                                    <CardTitle>CRM access</CardTitle>
                                </div>
                                <CardDescription>
                                    {person.access
                                        ? 'Account credentials and system permissions.'
                                        : 'Create an optional login after the staff profile has been reviewed.'}
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                {!person.access &&
                                availableAccessUsers.length > 0 ? (
                                    <div className="space-y-3 rounded-md border bg-muted/30 p-4">
                                        <div>
                                            <p className="text-sm font-medium">
                                                Link an existing CRM account
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                Keeps its password, role and
                                                permissions unchanged.
                                            </p>
                                        </div>
                                        <Form
                                            action={`/staff/${person.id}/access/link`}
                                            method="post"
                                            className="grid gap-3 sm:grid-cols-[minmax(0,1fr)_auto]"
                                        >
                                            {({ processing, errors }) => (
                                                <>
                                                    <div className="grid gap-2">
                                                        <Label htmlFor="existing_access_user">
                                                            CRM account
                                                        </Label>
                                                        <Select name="user_id">
                                                            <SelectTrigger
                                                                id="existing_access_user"
                                                                className="w-full"
                                                            >
                                                                <SelectValue placeholder="Select an account" />
                                                            </SelectTrigger>
                                                            <SelectContent>
                                                                {availableAccessUsers.map(
                                                                    (user) => (
                                                                        <SelectItem
                                                                            key={
                                                                                user.id
                                                                            }
                                                                            value={String(
                                                                                user.id,
                                                                            )}
                                                                        >
                                                                            {
                                                                                user.name
                                                                            }{' '}
                                                                            ·{' '}
                                                                            {
                                                                                user.email
                                                                            }{' '}
                                                                            ·{' '}
                                                                            {
                                                                                user.role
                                                                            }
                                                                        </SelectItem>
                                                                    ),
                                                                )}
                                                            </SelectContent>
                                                        </Select>
                                                        {errors.user_id ? (
                                                            <p className="text-xs text-destructive">
                                                                {errors.user_id}
                                                            </p>
                                                        ) : null}
                                                    </div>
                                                    <Button
                                                        className="self-end"
                                                        variant="secondary"
                                                        disabled={processing}
                                                    >
                                                        Link account
                                                    </Button>
                                                </>
                                            )}
                                        </Form>
                                    </div>
                                ) : null}
                                {!person.access ? (
                                    <div className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                        Create a new CRM account
                                    </div>
                                ) : null}
                                <Form
                                    action={`/staff/${person.id}/access`}
                                    method={person.access ? 'patch' : 'post'}
                                    className="grid gap-4"
                                >
                                    {({ processing, errors }) => (
                                        <>
                                            <div className="grid gap-2">
                                                <Label htmlFor="access_email">
                                                    Login email
                                                </Label>
                                                <Input
                                                    id="access_email"
                                                    name="email"
                                                    type="email"
                                                    defaultValue={
                                                        person.access?.email ||
                                                        person.email ||
                                                        ''
                                                    }
                                                    required
                                                />
                                                {errors.email ? (
                                                    <p className="text-xs text-destructive">
                                                        {errors.email}
                                                    </p>
                                                ) : null}
                                            </div>
                                            <div className="grid gap-2">
                                                <Label htmlFor="access_role">
                                                    System role
                                                </Label>
                                                <Select
                                                    name="role"
                                                    defaultValue={
                                                        person.access?.role ||
                                                        'technician'
                                                    }
                                                >
                                                    <SelectTrigger
                                                        id="access_role"
                                                        className="w-full"
                                                    >
                                                        <SelectValue />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        <SelectItem value="admin">
                                                            Administrator
                                                        </SelectItem>
                                                        <SelectItem value="commercial">
                                                            Commercial
                                                        </SelectItem>
                                                        <SelectItem value="technician">
                                                            Technician
                                                        </SelectItem>
                                                    </SelectContent>
                                                </Select>
                                                {errors.role ? (
                                                    <p className="text-xs text-destructive">
                                                        {errors.role}
                                                    </p>
                                                ) : null}
                                            </div>
                                            <div className="grid gap-3 md:grid-cols-2">
                                                <div className="grid gap-2">
                                                    <Label htmlFor="access_password">
                                                        {person.access
                                                            ? 'New password (optional)'
                                                            : 'Temporary password'}
                                                    </Label>
                                                    <Input
                                                        id="access_password"
                                                        name="password"
                                                        type="password"
                                                        required={
                                                            !person.access
                                                        }
                                                        autoComplete="new-password"
                                                    />
                                                    {errors.password ? (
                                                        <p className="text-xs text-destructive">
                                                            {errors.password}
                                                        </p>
                                                    ) : null}
                                                </div>
                                                <div className="grid gap-2">
                                                    <Label htmlFor="access_password_confirmation">
                                                        Confirm password
                                                    </Label>
                                                    <Input
                                                        id="access_password_confirmation"
                                                        name="password_confirmation"
                                                        type="password"
                                                        required={
                                                            !person.access
                                                        }
                                                        autoComplete="new-password"
                                                    />
                                                </div>
                                            </div>
                                            <Button disabled={processing}>
                                                {person.access
                                                    ? 'Update CRM access'
                                                    : 'Create CRM access'}
                                            </Button>
                                        </>
                                    )}
                                </Form>
                                {person.access ? (
                                    <Form
                                        action={`/staff/${person.id}/access`}
                                        method="delete"
                                    >
                                        {({ processing }) => (
                                            <Button
                                                variant="destructive"
                                                disabled={processing}
                                            >
                                                Remove CRM access
                                            </Button>
                                        )}
                                    </Form>
                                ) : null}
                            </CardContent>
                        </Card>
                    )}

                    <Card className="rounded-lg">
                        <CardHeader>
                            <CardTitle>Notes</CardTitle>
                            <CardDescription>
                                Service interests and internal notes.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4 text-sm">
                            <div>
                                {!isStaff && (
                                    <>
                                        <div className="font-medium">
                                            Service interests
                                        </div>
                                        <div className="mt-1 text-muted-foreground">
                                            {person.service_interests?.join(
                                                ', ',
                                            ) || 'No interests recorded'}
                                        </div>
                                    </>
                                )}
                            </div>
                            <div>
                                <div className="font-medium">Notes</div>
                                <div className="mt-1 whitespace-pre-line text-muted-foreground">
                                    {person.notes || 'No notes recorded'}
                                </div>
                            </div>
                            <Form
                                action={`${basePath}/${person.id}`}
                                method="delete"
                            >
                                {({ processing }) => (
                                    <Button
                                        variant="destructive"
                                        disabled={processing}
                                    >
                                        {isStaff
                                            ? 'Delete staff'
                                            : 'Archive customer'}
                                    </Button>
                                )}
                            </Form>
                        </CardContent>
                    </Card>
                </div>
                {!isStaff && (
                    <Card className="rounded-lg">
                        <CardHeader>
                            <CardTitle>Customer portal</CardTitle>
                            <CardDescription>
                                Secure access to installations, documents and
                                support.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-5 lg:grid-cols-[20rem_1fr]">
                            <Form
                                action={`/people/${person.id}/portal`}
                                method="post"
                                className="grid content-start gap-3"
                            >
                                <div className="grid gap-2">
                                    <Label>Recipient email</Label>
                                    <Input
                                        name="recipient_email"
                                        type="email"
                                        defaultValue={
                                            person.billing_email ||
                                            person.email ||
                                            ''
                                        }
                                        required
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label>Link validity (days)</Label>
                                    <Input
                                        name="expires_days"
                                        type="number"
                                        min={1}
                                        max={365}
                                        defaultValue={90}
                                        required
                                    />
                                </div>
                                <Button>Send portal invitation</Button>
                            </Form>
                            <div className="border-y">
                                {(person.portal_accesses || []).map(
                                    (access) => (
                                        <div
                                            key={access.id}
                                            className="flex items-center justify-between gap-3 border-b py-3 text-sm last:border-0"
                                        >
                                            <div>
                                                <div className="font-medium">
                                                    {access.recipient_email}
                                                </div>
                                                <div className="text-xs text-muted-foreground">
                                                    {access.revoked_at
                                                        ? 'Revoked'
                                                        : access.first_accessed_at
                                                          ? 'Accessed'
                                                          : 'Not accessed'}{' '}
                                                    · expires{' '}
                                                    {new Intl.DateTimeFormat(
                                                        undefined,
                                                        { dateStyle: 'medium' },
                                                    ).format(
                                                        new Date(
                                                            access.expires_at,
                                                        ),
                                                    )}
                                                </div>
                                            </div>
                                            {!access.revoked_at && (
                                                <Form
                                                    action={`/people/${person.id}/portal/${access.id}`}
                                                    method="delete"
                                                >
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                    >
                                                        Revoke
                                                    </Button>
                                                </Form>
                                            )}
                                        </div>
                                    ),
                                )}
                                {(person.portal_accesses || []).length ===
                                    0 && (
                                    <div className="py-8 text-center text-sm text-muted-foreground">
                                        No portal invitations.
                                    </div>
                                )}
                            </div>
                        </CardContent>
                    </Card>
                )}
            </div>
        </>
    );
}

function CustomerCrmDetails({
    person,
    availableTags,
}: {
    person: Person;
    availableTags: Array<{ id: number; name: string; color: string }>;
}) {
    const unassignedTags = availableTags.filter(
        (tag) => !person.tags.some((assigned) => assigned.id === tag.id),
    );

    return (
        <div className="grid gap-4 xl:grid-cols-3">
            <Card className="rounded-lg">
                <CardHeader>
                    <CardTitle>Commercial profile</CardTitle>
                    <CardDescription>
                        Segmentation, preferences and origin of the record.
                    </CardDescription>
                </CardHeader>
                <CardContent className="space-y-4 text-sm">
                    <InfoBlock
                        title="Preferences"
                        items={[
                            ['Lifecycle', person.lifecycle_status],
                            [
                                'Preferred channel',
                                person.preferred_contact_channel,
                            ],
                            ['Data source', person.data_source],
                            [
                                'Commercial contact',
                                person.do_not_contact ? 'Blocked' : 'Allowed',
                            ],
                        ]}
                    />
                    <div className="flex flex-wrap gap-2">
                        {person.tags.map((tag) => (
                            <Form
                                key={tag.id}
                                action={`/people/${person.id}/tags/${tag.id}`}
                                method="delete"
                            >
                                {({ processing }) => (
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        disabled={processing}
                                        title="Remove tag"
                                    >
                                        {tag.name} ×
                                    </Button>
                                )}
                            </Form>
                        ))}
                    </div>
                    <Form
                        action={`/people/${person.id}/tags`}
                        method="post"
                        className="flex gap-2"
                    >
                        {({ processing }) => (
                            <>
                                <Select name="tag_id">
                                    <SelectTrigger className="w-full">
                                        <SelectValue placeholder="Add tag" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {unassignedTags.map((tag) => (
                                            <SelectItem
                                                key={tag.id}
                                                value={String(tag.id)}
                                            >
                                                {tag.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <Input
                                    name="name"
                                    placeholder="or create a tag"
                                />
                                <Button disabled={processing}>Add</Button>
                            </>
                        )}
                    </Form>
                </CardContent>
            </Card>

            <Card className="rounded-lg">
                <CardHeader>
                    <CardTitle>Company contacts</CardTitle>
                    <CardDescription>
                        Multiple contacts with one designated as primary.
                    </CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                    <div className="divide-y border-y">
                        {person.contacts.map((contact) => (
                            <div
                                key={contact.id}
                                className="flex items-start justify-between gap-3 py-3"
                            >
                                <div className="text-sm">
                                    <div className="font-medium">
                                        {contact.first_name} {contact.last_name}
                                        {contact.is_primary && (
                                            <Badge className="ml-2">
                                                Primary
                                            </Badge>
                                        )}
                                    </div>
                                    <div className="text-muted-foreground">
                                        {[
                                            contact.position,
                                            contact.email,
                                            contact.phone,
                                        ]
                                            .filter(Boolean)
                                            .join(' · ')}
                                    </div>
                                </div>
                                <Form
                                    action={`/people/${person.id}/contacts/${contact.id}`}
                                    method="delete"
                                >
                                    {({ processing }) => (
                                        <Button
                                            size="sm"
                                            variant="ghost"
                                            disabled={processing}
                                        >
                                            Remove
                                        </Button>
                                    )}
                                </Form>
                            </div>
                        ))}
                    </div>
                    <Form
                        action={`/people/${person.id}/contacts`}
                        method="post"
                        className="grid gap-2 sm:grid-cols-2"
                    >
                        {({ processing }) => (
                            <>
                                <Input
                                    name="first_name"
                                    placeholder="First name"
                                    required
                                />
                                <Input
                                    name="last_name"
                                    placeholder="Last name"
                                />
                                <Input name="position" placeholder="Position" />
                                <Input
                                    name="email"
                                    type="email"
                                    placeholder="Email"
                                />
                                <Input name="phone" placeholder="Phone" />
                                <label className="flex items-center gap-2 text-sm">
                                    <input
                                        type="hidden"
                                        name="is_primary"
                                        value="0"
                                    />
                                    <input
                                        type="checkbox"
                                        name="is_primary"
                                        value="1"
                                    />
                                    Primary contact
                                </label>
                                <Button
                                    className="sm:col-span-2"
                                    disabled={processing}
                                >
                                    Add contact
                                </Button>
                            </>
                        )}
                    </Form>
                </CardContent>
            </Card>

            <Card className="rounded-lg">
                <CardHeader>
                    <CardTitle>Commercial consent</CardTitle>
                    <CardDescription>
                        Auditable preference by communication channel.
                    </CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                    <div className="divide-y border-y">
                        {person.consents.slice(0, 5).map((consent) => (
                            <div key={consent.id} className="py-3 text-sm">
                                <div className="flex items-center gap-2">
                                    <Badge variant="outline">
                                        {consent.channel}
                                    </Badge>
                                    <span className="font-medium">
                                        {consent.status}
                                    </span>
                                </div>
                                <div className="mt-1 text-xs text-muted-foreground">
                                    {formatDate(consent.captured_at)}
                                    {consent.lawful_basis
                                        ? ` · ${consent.lawful_basis}`
                                        : ''}
                                    {consent.source
                                        ? ` · ${consent.source}`
                                        : ''}
                                </div>
                            </div>
                        ))}
                    </div>
                    <Form
                        action={`/people/${person.id}/consents`}
                        method="post"
                        className="grid gap-2 sm:grid-cols-2"
                    >
                        {({ processing }) => (
                            <>
                                <ActivitySelect
                                    label="Channel"
                                    name="channel"
                                    options={[
                                        ['email', 'Email'],
                                        ['phone', 'Phone'],
                                        ['sms', 'SMS'],
                                        ['whatsapp', 'WhatsApp'],
                                    ]}
                                    defaultValue="email"
                                />
                                <ActivitySelect
                                    label="Status"
                                    name="status"
                                    options={[
                                        ['granted', 'Granted'],
                                        ['denied', 'Denied'],
                                        ['withdrawn', 'Withdrawn'],
                                    ]}
                                    defaultValue="granted"
                                />
                                <Input
                                    type="hidden"
                                    name="captured_at"
                                    value={new Date().toISOString()}
                                />
                                <ActivitySelect
                                    label="Legal basis"
                                    name="lawful_basis"
                                    options={[
                                        ['consent', 'Consent'],
                                        ['contract', 'Contract'],
                                        [
                                            'legitimate_interest',
                                            'Legitimate interest',
                                        ],
                                        [
                                            'legal_obligation',
                                            'Legal obligation',
                                        ],
                                    ]}
                                    defaultValue="consent"
                                />
                                <Input name="source" placeholder="Source" />
                                <Button
                                    className="sm:col-span-2"
                                    disabled={processing}
                                >
                                    Record consent
                                </Button>
                            </>
                        )}
                    </Form>
                </CardContent>
            </Card>
        </div>
    );
}

function InfoBlock({
    title,
    items,
}: {
    title: string;
    items: [string, string | null][];
}) {
    return (
        <div className="rounded-md border p-4">
            <div className="font-medium">{title}</div>
            <dl className="mt-3 grid gap-2 text-sm">
                {items.map(([label, value]) => (
                    <div key={label}>
                        <dt className="text-muted-foreground">{label}</dt>
                        <dd>{value || '—'}</dd>
                    </div>
                ))}
            </dl>
        </div>
    );
}

function ServiceIcon({ service }: { service: keyof typeof serviceLabels }) {
    const icons: Record<keyof typeof serviceLabels, LucideIcon> = {
        solar: Sun,
        ev_charger: BatteryCharging,
        hvac: Snowflake,
        electrical: PlugZap,
        security: ShieldCheck,
    };
    const Icon = icons[service];

    return <Icon className="size-4 text-muted-foreground" />;
}

function ProfileMetric({
    icon: Icon,
    label,
    value,
    attention = false,
}: {
    icon: LucideIcon;
    label: string;
    value: string;
    attention?: boolean;
}) {
    return (
        <div className="border-r p-4 last:border-r-0 even:border-r-0 lg:last:border-r-0 lg:even:border-r">
            <div className="flex items-center gap-2 text-xs text-muted-foreground">
                <Icon className="size-4" />
                {label}
            </div>
            <div
                className={`mt-2 text-xl font-semibold tabular-nums ${attention ? 'text-amber-700 dark:text-amber-400' : ''}`}
            >
                {value}
            </div>
        </div>
    );
}

function ActivityDialog({
    person,
    users,
}: {
    person: Person;
    users: Array<{ id: number; name: string; role: string }>;
}) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="outline">
                    <PhoneCall /> Log activity
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Customer activity</DialogTitle>
                    <DialogDescription>
                        Record an interaction or schedule a follow-up for this
                        customer.
                    </DialogDescription>
                </DialogHeader>
                <Form
                    action="/operations/activities"
                    method="post"
                    resetOnSuccess
                    onSuccess={() => setOpen(false)}
                    className="grid gap-4"
                >
                    {({ errors, processing }) => (
                        <>
                            <input
                                type="hidden"
                                name="person_id"
                                value={person.id}
                            />
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2 sm:col-span-2">
                                    <Label htmlFor="activity_title">
                                        Title
                                    </Label>
                                    <Input
                                        id="activity_title"
                                        name="title"
                                        required
                                    />
                                    {errors.title && (
                                        <p className="text-xs text-destructive">
                                            {errors.title}
                                        </p>
                                    )}
                                </div>
                                <ActivitySelect
                                    label="Type"
                                    name="type"
                                    defaultValue="call"
                                    options={[
                                        ['call', 'Call'],
                                        ['email', 'Email'],
                                        ['meeting', 'Meeting'],
                                        ['visit', 'Visit'],
                                        ['task', 'Task'],
                                        ['reminder', 'Reminder'],
                                    ]}
                                />
                                <ActivitySelect
                                    label="Result"
                                    name="status"
                                    defaultValue="completed"
                                    options={[
                                        ['completed', 'Interaction completed'],
                                        ['pending', 'Follow-up pending'],
                                        ['in_progress', 'In progress'],
                                    ]}
                                />
                                <ActivitySelect
                                    label="Priority"
                                    name="priority"
                                    defaultValue="normal"
                                    options={[
                                        ['low', 'Low'],
                                        ['normal', 'Normal'],
                                        ['high', 'High'],
                                    ]}
                                />
                                {person.installations.length > 0 && (
                                    <ActivitySelect
                                        label="Installation (optional)"
                                        name="installation_id"
                                        options={person.installations.map(
                                            (installation) => [
                                                String(installation.id),
                                                installation.project_reference ||
                                                    installation.site_name ||
                                                    `#${installation.id}`,
                                            ],
                                        )}
                                    />
                                )}
                                {users.length > 0 && (
                                    <ActivitySelect
                                        label="Assigned to (optional)"
                                        name="assigned_user_id"
                                        options={users.map((user) => [
                                            String(user.id),
                                            user.name,
                                        ])}
                                    />
                                )}
                                <div className="grid gap-2">
                                    <Label htmlFor="activity_starts_at">
                                        Starts
                                    </Label>
                                    <Input
                                        id="activity_starts_at"
                                        name="starts_at"
                                        type="datetime-local"
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="activity_due_at">Due</Label>
                                    <Input
                                        id="activity_due_at"
                                        name="due_at"
                                        type="datetime-local"
                                    />
                                </div>
                                <div className="grid gap-2 sm:col-span-2">
                                    <Label htmlFor="activity_notes">
                                        Notes
                                    </Label>
                                    <textarea
                                        id="activity_notes"
                                        name="notes"
                                        className="min-h-28 rounded-md border bg-transparent p-3 text-sm"
                                    />
                                </div>
                            </div>
                            <Button disabled={processing}>
                                <PhoneCall /> Save activity
                            </Button>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function ActivitySelect({
    label,
    name,
    options,
    defaultValue,
}: {
    label: string;
    name: string;
    options: Array<[string, string]>;
    defaultValue?: string;
}) {
    return (
        <div className="grid gap-2">
            <Label>{label}</Label>
            <Select name={name} defaultValue={defaultValue}>
                <SelectTrigger className="w-full">
                    <SelectValue placeholder="Select" />
                </SelectTrigger>
                <SelectContent>
                    {options.map(([value, text]) => (
                        <SelectItem key={value} value={value}>
                            {text}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
        </div>
    );
}

function money(value: number) {
    return new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: 'EUR',
    }).format(value);
}

function formatDate(value: string | null) {
    return value
        ? new Intl.DateTimeFormat(undefined, {
              dateStyle: 'medium',
              timeStyle: 'short',
          }).format(new Date(value))
        : 'No date';
}

PeopleShow.layout = {
    breadcrumbs: [{ title: 'Person profile', href: '/people' }],
};

import { Form, Head, Link } from '@inertiajs/react';
import {
    Activity,
    DatabaseBackup,
    Download,
    FileDown,
    KeyRound,
    ShieldCheck,
    Users,
} from 'lucide-react';
import { PaginationBar } from '@/components/page-shell';
import type { PaginationLink } from '@/components/page-shell';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

type UserRow = {
    id: number;
    name: string;
    email: string;
    role: string;
    created_at: string;
    permission_profile_id: number | null;
    permission_profile: { id: number; name: string } | null;
    permission_overrides: Array<Permission & { pivot: { allowed: boolean } }>;
    access_reviewed_at: string | null;
    access_review_due_at: string | null;
};
type Permission = {
    id: number;
    key: string;
    name: string;
    category: string;
    is_sensitive: boolean;
};
type Profile = {
    id: number;
    name: string;
    slug: string;
    permissions: Permission[];
};
type Audit = {
    id: number;
    event: string;
    auditable_type: string;
    auditable_id: number;
    old_values: Record<string, unknown> | null;
    new_values: Record<string, unknown> | null;
    created_at: string;
    user: { name: string } | null;
};
type Backup = { name: string; size: number; created_at: string };
type Restoration = {
    id: number;
    backup_name: string;
    status: string;
    details: string;
    verified_at: string;
};
type HealthCheck = {
    id: number;
    component: string;
    status: string;
    message: string;
    metrics: Record<string, unknown>;
    checked_at: string;
};
type SecurityEvent = {
    id: number;
    type: string;
    severity: string;
    ip_address: string | null;
    created_at: string;
    user: { name: string } | null;
};
type AuditPage = {
    data: Audit[];
    links: PaginationLink[];
    total: number;
};

export default function AdminIndex({
    section,
    users = [],
    audits = { data: [], links: [], total: 0 },
    backups = [],
    profiles = [],
    permissions = [],
    restorations = [],
    healthChecks = [],
    securityEvents = [],
    accessReviewsDue = 0,
    backupSupported = true,
}: {
    section: 'access' | 'backups' | 'audit' | 'health';
    users?: UserRow[];
    audits?: AuditPage;
    backups?: Backup[];
    profiles?: Profile[];
    permissions?: Permission[];
    restorations?: Restoration[];
    healthChecks?: HealthCheck[];
    securityEvents?: SecurityEvent[];
    accessReviewsDue?: number;
    backupSupported?: boolean;
}) {
    return (
        <>
            <Head title="Administration" />
            <div className="flex min-w-0 flex-1 flex-col gap-8 p-4 md:p-6">
                <header>
                    <h1 className="text-xl font-semibold">
                        {section === 'access'
                            ? 'Access & roles'
                            : section === 'backups'
                              ? 'Backups'
                              : section === 'health'
                                ? 'Reliability dashboard'
                                : 'Audit trail'}
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Access, traceability and recovery.
                    </p>
                </header>

                {section === 'access' ? (
                    <section className="overflow-x-auto border-t pt-5">
                        <div className="mb-3 flex items-center gap-2">
                            <Users className="size-4" />
                            <h2 className="font-semibold">Users and roles</h2>
                        </div>
                        <table className="w-full min-w-[56rem] text-sm">
                            <thead>
                                <tr className="border-b text-left text-muted-foreground">
                                    <th className="py-2">User</th>
                                    <th>Email</th>
                                    <th>Profile and exceptions</th>
                                    <th>Review</th>
                                </tr>
                            </thead>
                            <tbody>
                                {users.map((user) => (
                                    <tr key={user.id} className="border-b">
                                        <td className="py-3 font-medium">
                                            {user.name}
                                        </td>
                                        <td>{user.email}</td>
                                        <td>
                                            <Form
                                                action={`/admin/users/${user.id}`}
                                                method="patch"
                                                className="flex max-w-xs gap-2"
                                            >
                                                <Select
                                                    name="permission_profile_id"
                                                    defaultValue={String(
                                                        user.permission_profile_id ||
                                                            '',
                                                    )}
                                                >
                                                    <SelectTrigger className="w-52">
                                                        <SelectValue />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        {profiles.map(
                                                            (profile) => (
                                                                <SelectItem
                                                                    key={
                                                                        profile.id
                                                                    }
                                                                    value={String(
                                                                        profile.id,
                                                                    )}
                                                                >
                                                                    {
                                                                        profile.name
                                                                    }
                                                                </SelectItem>
                                                            ),
                                                        )}
                                                    </SelectContent>
                                                </Select>
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                >
                                                    Save
                                                </Button>
                                                <details className="w-full text-xs">
                                                    <summary className="cursor-pointer text-muted-foreground">
                                                        User-specific exceptions
                                                    </summary>
                                                    <div className="mt-2 grid gap-1 rounded-md border p-2">
                                                        {permissions.map(
                                                            (permission) => {
                                                                const override =
                                                                    user.permission_overrides.find(
                                                                        (
                                                                            item,
                                                                        ) =>
                                                                            item.id ===
                                                                            permission.id,
                                                                    )?.pivot
                                                                        .allowed;

                                                                return (
                                                                    <label
                                                                        key={
                                                                            permission.id
                                                                        }
                                                                        className="flex items-center justify-between gap-3"
                                                                    >
                                                                        <span>
                                                                            {
                                                                                permission.name
                                                                            }
                                                                        </span>
                                                                        <select
                                                                            name={`overrides[${permission.id}]`}
                                                                            defaultValue={
                                                                                override ===
                                                                                undefined
                                                                                    ? 'inherit'
                                                                                    : override
                                                                                      ? 'allow'
                                                                                      : 'deny'
                                                                            }
                                                                            className="rounded border bg-background p-1"
                                                                        >
                                                                            <option value="inherit">
                                                                                Profile
                                                                            </option>
                                                                            <option value="allow">
                                                                                Allow
                                                                            </option>
                                                                            <option value="deny">
                                                                                Deny
                                                                            </option>
                                                                        </select>
                                                                    </label>
                                                                );
                                                            },
                                                        )}
                                                    </div>
                                                </details>
                                            </Form>
                                        </td>
                                        <td className="min-w-48">
                                            <div className="text-xs text-muted-foreground">
                                                Due{' '}
                                                {user.access_review_due_at
                                                    ? formatDate(
                                                          user.access_review_due_at,
                                                      )
                                                    : 'now'}
                                            </div>
                                            <Form
                                                action={`/admin/users/${user.id}/review`}
                                                method="post"
                                            >
                                                <Button
                                                    size="sm"
                                                    variant="ghost"
                                                >
                                                    Mark reviewed
                                                </Button>
                                            </Form>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                        <div className="mt-8 space-y-5">
                            <div className="flex items-center gap-2">
                                <KeyRound className="size-4" />
                                <h2 className="font-semibold">
                                    Permission matrix by profile
                                </h2>
                            </div>
                            {profiles.map((profile) => (
                                <Form
                                    key={profile.id}
                                    action={`/admin/profiles/${profile.id}`}
                                    method="patch"
                                    className="rounded-lg border p-4"
                                >
                                    <div className="mb-3 flex items-center justify-between">
                                        <h3 className="font-medium">
                                            {profile.name}
                                        </h3>
                                        <Button size="sm" variant="outline">
                                            Save matrix
                                        </Button>
                                    </div>
                                    <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                                        {permissions.map((permission) => (
                                            <label
                                                key={permission.id}
                                                className="flex items-center gap-2 text-sm"
                                            >
                                                <input
                                                    type="checkbox"
                                                    name="permissions[]"
                                                    value={permission.id}
                                                    defaultChecked={profile.permissions.some(
                                                        (item) =>
                                                            item.id ===
                                                            permission.id,
                                                    )}
                                                />
                                                <span>
                                                    {permission.name}
                                                    {permission.is_sensitive
                                                        ? ' · sensitive'
                                                        : ''}
                                                </span>
                                            </label>
                                        ))}
                                    </div>
                                </Form>
                            ))}
                        </div>
                    </section>
                ) : null}

                {section === 'backups' ? (
                    <section className="border-t pt-5">
                        <div className="mb-3 flex flex-wrap items-center justify-between gap-3">
                            <div className="flex items-center gap-2">
                                <DatabaseBackup className="size-4" />
                                <h2 className="font-semibold">
                                    Encrypted backups
                                </h2>
                            </div>
                            <Form action="/admin/backups" method="post">
                                <Button disabled={!backupSupported}>
                                    <DatabaseBackup /> Create backup
                                </Button>
                            </Form>
                        </div>
                        {!backupSupported ? (
                            <p className="mb-4 text-sm text-amber-700 dark:text-amber-400">
                                PostgreSQL backups are managed by the hosting
                                platform. Verify their status and restoration
                                tests in Laravel Cloud.
                            </p>
                        ) : null}
                        <div className="grid gap-2">
                            {backups.map((backup) => (
                                <div
                                    key={backup.name}
                                    className="flex items-center justify-between border-b py-2 text-sm"
                                >
                                    <div>
                                        <div className="font-medium">
                                            {backup.name}
                                        </div>
                                        <div className="text-xs text-muted-foreground">
                                            {formatDate(backup.created_at)} ·{' '}
                                            {(
                                                backup.size /
                                                1024 /
                                                1024
                                            ).toFixed(1)}{' '}
                                            MB
                                        </div>
                                    </div>
                                    <Button
                                        size="icon"
                                        variant="ghost"
                                        asChild
                                        title="Download"
                                    >
                                        <Link
                                            href={`/admin/backups/${backup.name}`}
                                        >
                                            <Download />
                                        </Link>
                                    </Button>
                                </div>
                            ))}
                        </div>
                        <p className="mt-3 text-xs text-muted-foreground">
                            Restoration is intentionally restricted to the
                            server command database:restore and creates a safety
                            backup first.
                        </p>
                        <div className="mt-8">
                            <h3 className="mb-3 font-semibold">
                                Isolated restoration tests
                            </h3>
                            <div className="grid gap-2">
                                {restorations.map((item) => (
                                    <div
                                        key={item.id}
                                        className="flex justify-between border-b py-2 text-sm"
                                    >
                                        <span>
                                            {item.backup_name}
                                            <span className="block text-xs text-muted-foreground">
                                                {item.details}
                                            </span>
                                        </span>
                                        <Badge variant="outline">
                                            {item.status}
                                        </Badge>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </section>
                ) : null}

                {section === 'audit' ? (
                    <section className="overflow-x-auto border-t pt-5">
                        <div className="mb-3 flex items-center gap-2">
                            <ShieldCheck className="size-4" />
                            <h2 className="font-semibold">
                                Recent audit trail
                            </h2>
                            <Button size="sm" variant="outline" asChild>
                                <a href="/admin/audit/export">
                                    <FileDown />
                                    Export CSV
                                </a>
                            </Button>
                        </div>
                        <table className="w-full min-w-[56rem] text-sm">
                            <thead>
                                <tr className="border-b text-left text-muted-foreground">
                                    <th className="py-2">When</th>
                                    <th>User</th>
                                    <th>Event</th>
                                    <th>Record</th>
                                    <th>Changes</th>
                                </tr>
                            </thead>
                            <tbody>
                                {audits.data.map((audit) => (
                                    <tr
                                        key={audit.id}
                                        className="border-b align-top"
                                    >
                                        <td className="py-3 whitespace-nowrap">
                                            {formatDate(audit.created_at)}
                                        </td>
                                        <td>{audit.user?.name || 'System'}</td>
                                        <td>
                                            <Badge variant="outline">
                                                {audit.event}
                                            </Badge>
                                        </td>
                                        <td>
                                            {audit.auditable_type
                                                .split('\\')
                                                .pop()}{' '}
                                            #{audit.auditable_id}
                                        </td>
                                        <td className="max-w-xl text-xs text-muted-foreground">
                                            {Object.keys(
                                                audit.new_values ||
                                                    audit.old_values ||
                                                    {},
                                            ).join(', ')}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                        <div className="mt-5">
                            <PaginationBar
                                links={audits.links}
                                total={audits.total}
                                noun="audit events"
                            />
                        </div>
                    </section>
                ) : null}

                {section === 'health' ? (
                    <section className="space-y-8 border-t pt-5">
                        <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                            {healthChecks.map((check) => (
                                <div
                                    key={check.id}
                                    className="rounded-lg border p-4"
                                >
                                    <div className="flex items-center justify-between">
                                        <span className="font-medium">
                                            {check.component}
                                        </span>
                                        <Badge variant="outline">
                                            {check.status}
                                        </Badge>
                                    </div>
                                    <p className="mt-2 text-sm text-muted-foreground">
                                        {check.message}
                                    </p>
                                    <p className="mt-2 text-xs text-muted-foreground">
                                        Checked {formatDate(check.checked_at)}
                                    </p>
                                </div>
                            ))}
                        </div>
                        <div>
                            <div className="mb-3 flex items-center gap-2">
                                <Activity className="size-4" />
                                <h2 className="font-semibold">
                                    Security and operational alerts
                                </h2>
                                {accessReviewsDue > 0 ? (
                                    <Badge variant="outline">
                                        {accessReviewsDue} access reviews due
                                    </Badge>
                                ) : null}
                            </div>
                            <div className="grid gap-2">
                                {securityEvents.map((event) => (
                                    <div
                                        key={event.id}
                                        className="flex items-center justify-between border-b py-2 text-sm"
                                    >
                                        <span>
                                            {event.type.replaceAll('_', ' ')}
                                            <span className="block text-xs text-muted-foreground">
                                                {event.user?.name || 'System'} ·{' '}
                                                {event.ip_address || 'internal'}
                                            </span>
                                        </span>
                                        <span className="text-xs">
                                            {formatDate(event.created_at)}
                                        </span>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </section>
                ) : null}
            </div>
        </>
    );
}

function formatDate(value: string) {
    return new Intl.DateTimeFormat('es-ES', {
        dateStyle: 'short',
        timeStyle: 'short',
    }).format(new Date(value));
}
AdminIndex.layout = {
    breadcrumbs: [{ title: 'Administration', href: '/admin' }],
};

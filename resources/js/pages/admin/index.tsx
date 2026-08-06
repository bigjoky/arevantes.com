import { Form, Head, Link } from '@inertiajs/react';
import { DatabaseBackup, Download, ShieldCheck, Users } from 'lucide-react';
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
}: {
    section: 'users' | 'backups' | 'audit';
    users?: UserRow[];
    audits?: AuditPage;
    backups?: Backup[];
}) {
    return (
        <>
            <Head title="Administration" />
            <div className="flex min-w-0 flex-1 flex-col gap-8 p-4 md:p-6">
                <header>
                    <h1 className="text-xl font-semibold">
                        {section === 'users'
                            ? 'Access & roles'
                            : section === 'backups'
                              ? 'Backups'
                              : 'Audit trail'}
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Access, traceability and recovery.
                    </p>
                </header>

                {section === 'users' ? (
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
                                    <th>Role</th>
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
                                                    name="role"
                                                    defaultValue={user.role}
                                                >
                                                    <SelectTrigger className="w-40">
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
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                >
                                                    Save
                                                </Button>
                                            </Form>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
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
                                <Button>
                                    <DatabaseBackup /> Create backup
                                </Button>
                            </Form>
                        </div>
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
                    </section>
                ) : null}

                {section === 'audit' ? (
                    <section className="overflow-x-auto border-t pt-5">
                        <div className="mb-3 flex items-center gap-2">
                            <ShieldCheck className="size-4" />
                            <h2 className="font-semibold">
                                Recent audit trail
                            </h2>
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

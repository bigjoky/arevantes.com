import { Form, Head, Link } from '@inertiajs/react';
import { ArrowLeft, Pencil, Plus, Search, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { PageHeader, PageShell } from '@/components/page-shell';
import { PaginationBar, TableViewport } from '@/components/page-shell';
import type { PaginationLink } from '@/components/page-shell';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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
import { accountTypes } from './workspace';

type Account = {
    id: number;
    code: string;
    name: string;
    type: string;
    effective_type?: string;
    parent_code: string | null;
    is_postable: boolean;
    is_active: boolean;
    source: string;
    lines_count?: number;
};
type Page<T> = {
    data: T[];
    links: PaginationLink[];
    total: number;
};
const types = ['asset', 'liability', 'equity', 'income', 'expense'];

export default function Accounts({
    accounts,
    parents,
    search,
    canConfigure,
}: {
    accounts: Page<Account>;
    parents: Account[];
    search: string;
    canConfigure: boolean;
}) {
    const admin = canConfigure;

    return (
        <>
            <Head title="Chart of accounts" />
            <PageShell mode="master-detail">
                <PageHeader
                    title={<>Chart of accounts</>}
                    description={
                        <>Official PGC structure and company subaccounts.</>
                    }
                    actions={
                        <>
                            <Button variant="outline" asChild>
                                <Link href="/accounting">
                                    <ArrowLeft /> Accounting
                                </Link>
                            </Button>
                            {admin && <AccountDialog parents={parents} />}
                        </>
                    }
                />
                <Form
                    action="/accounting/accounts"
                    method="get"
                    className="flex max-w-xl gap-2"
                >
                    <input type="hidden" name="mode" value="manage" />
                    <Input
                        name="search"
                        defaultValue={search}
                        placeholder="Search by code or name"
                    />
                    <Button variant="outline">
                        <Search /> Search
                    </Button>
                </Form>
                <TableViewport>
                    <table className="w-full min-w-[60rem] text-sm">
                        <thead>
                            <tr className="border-b text-left text-muted-foreground">
                                <th className="py-2">Code</th>
                                <th>Name</th>
                                <th>Type</th>
                                <th>Parent</th>
                                <th>Status</th>
                                <th>Origin</th>
                                <th className="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {accounts.data.map((account) => (
                                <tr key={account.id} className="border-b">
                                    <td className="py-2 font-mono font-medium">
                                        {account.code}
                                    </td>
                                    <td>
                                        <Link
                                            href={`/accounting/accounts/${account.id}`}
                                        >
                                            {account.name}
                                        </Link>
                                        <p className="text-xs">
                                            {account.lines_count ?? 0} apuntes
                                            registrados
                                        </p>
                                    </td>
                                    <td className="capitalize">
                                        {accountTypes[
                                            account.effective_type ??
                                                account.type
                                        ] ?? account.type}
                                    </td>
                                    <td className="font-mono">
                                        {account.parent_code || '—'}
                                    </td>
                                    <td>
                                        <Badge variant="outline">
                                            {account.is_active
                                                ? account.is_postable
                                                    ? 'Postable'
                                                    : 'Group'
                                                : 'Inactive'}
                                        </Badge>
                                    </td>
                                    <td>
                                        {account.source === 'pgc'
                                            ? 'PGC'
                                            : account.source === 'customized'
                                              ? 'PGC · customized'
                                              : 'Company'}
                                    </td>
                                    <td className="text-right">
                                        {admin && (
                                            <div className="inline-flex gap-1">
                                                <AccountDialog
                                                    account={account}
                                                    parents={parents}
                                                />
                                                <Form
                                                    action={`/accounting/accounts/${account.id}`}
                                                    method="delete"
                                                >
                                                    <Button
                                                        size="icon"
                                                        variant="ghost"
                                                        aria-label="Delete account"
                                                    >
                                                        <Trash2 />
                                                    </Button>
                                                </Form>
                                            </div>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </TableViewport>
                <PaginationBar
                    links={accounts.links}
                    total={accounts.total}
                    noun="accounts"
                />
            </PageShell>
        </>
    );
}

function AccountDialog({
    account,
    parents,
}: {
    account?: Account;
    parents: Account[];
}) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button
                    size={account ? 'icon' : 'default'}
                    variant={account ? 'ghost' : 'default'}
                    aria-label={account ? 'Edit account' : undefined}
                >
                    {account ? (
                        <Pencil />
                    ) : (
                        <>
                            <Plus /> New account
                        </>
                    )}
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {account
                            ? `Edit ${account.code}`
                            : 'New accounting account'}
                    </DialogTitle>
                </DialogHeader>
                <Form
                    action={
                        account
                            ? `/accounting/accounts/${account.id}`
                            : '/accounting/accounts'
                    }
                    method={account ? 'patch' : 'post'}
                    className="grid gap-4"
                    onSuccess={() => setOpen(false)}
                >
                    {!account && (
                        <Field label="Code">
                            <Input
                                name="code"
                                inputMode="numeric"
                                pattern="[0-9]{3,20}"
                                required
                            />
                        </Field>
                    )}
                    <Field label="Name">
                        <Input
                            name="name"
                            defaultValue={account?.name}
                            required
                        />
                    </Field>
                    <div className="grid grid-cols-2 gap-3">
                        <Field label="Type">
                            <Select
                                name="type"
                                defaultValue={account?.type || 'expense'}
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {types.map((type) => (
                                        <SelectItem
                                            key={type}
                                            value={type}
                                            className="capitalize"
                                        >
                                            {type}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </Field>
                        <Field label="Parent">
                            <Select
                                name="parent_code"
                                defaultValue={account?.parent_code || 'none'}
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="none">
                                        No parent
                                    </SelectItem>
                                    {parents
                                        .filter((p) => p.code !== account?.code)
                                        .map((p) => (
                                            <SelectItem
                                                key={p.code}
                                                value={p.code}
                                            >
                                                {p.code} · {p.name}
                                            </SelectItem>
                                        ))}
                                </SelectContent>
                            </Select>
                        </Field>
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                        <Field label="Allows entries">
                            <Select
                                name="is_postable"
                                defaultValue={
                                    account?.is_postable === false ? '0' : '1'
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="1">Yes</SelectItem>
                                    <SelectItem value="0">
                                        No, group only
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </Field>
                        <Field label="Active">
                            <Select
                                name="is_active"
                                defaultValue={
                                    account?.is_active === false ? '0' : '1'
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="1">Active</SelectItem>
                                    <SelectItem value="0">Inactive</SelectItem>
                                </SelectContent>
                            </Select>
                        </Field>
                    </div>
                    <Button>
                        {account ? 'Save changes' : 'Create account'}
                    </Button>
                </Form>
            </DialogContent>
        </Dialog>
    );
}
function Field({
    label,
    children,
}: {
    label: string;
    children: React.ReactNode;
}) {
    return (
        <div className="grid gap-1">
            <Label>{label}</Label>
            {children}
        </div>
    );
}
Accounts.layout = {
    breadcrumbs: [
        { title: 'Finance', href: '/finance' },
        { title: 'Accounting', href: '/accounting' },
        { title: 'Chart of accounts', href: '/accounting/accounts' },
    ],
};

import { Form, Head, Link, usePage } from '@inertiajs/react';
import { ArrowLeft, Pencil, Plus, Search, Trash2 } from 'lucide-react';
import { useState } from 'react';
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

type Account = {
    id: number;
    code: string;
    name: string;
    type: string;
    parent_code: string | null;
    is_postable: boolean;
    is_active: boolean;
    source: string;
};
type Page<T> = {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
    total: number;
};
const types = ['asset', 'liability', 'equity', 'income', 'expense'];

export default function Accounts({
    accounts,
    parents,
    search,
}: {
    accounts: Page<Account>;
    parents: Account[];
    search: string;
}) {
    const admin =
        usePage<{ auth: { user: { role: string } } }>().props.auth.user.role ===
        'admin';

    return (
        <>
            <Head title="Chart of accounts" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <header className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold">
                            Chart of accounts
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Official PGC structure and company subaccounts.
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <Button variant="outline" asChild>
                            <Link href="/accounting">
                                <ArrowLeft /> Accounting
                            </Link>
                        </Button>
                        {admin && <AccountDialog parents={parents} />}
                    </div>
                </header>
                <Form
                    action="/accounting/accounts"
                    method="get"
                    className="flex max-w-xl gap-2"
                >
                    <Input
                        name="search"
                        defaultValue={search}
                        placeholder="Search by code or name"
                    />
                    <Button variant="outline">
                        <Search /> Search
                    </Button>
                </Form>
                <div className="overflow-x-auto">
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
                                    <td>{account.name}</td>
                                    <td className="capitalize">
                                        {account.type}
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
                </div>
                <div className="flex flex-wrap items-center justify-between gap-3 text-sm">
                    <span className="text-muted-foreground">
                        {accounts.total} accounts
                    </span>
                    <div className="flex gap-1">
                        {accounts.links.map(
                            (link, i) =>
                                link.url && (
                                    <Button
                                        key={i}
                                        size="sm"
                                        variant={
                                            link.active ? 'default' : 'outline'
                                        }
                                        asChild
                                    >
                                        <Link
                                            href={link.url}
                                            preserveScroll
                                            dangerouslySetInnerHTML={{
                                                __html: link.label,
                                            }}
                                        />
                                    </Button>
                                ),
                        )}
                    </div>
                </div>
            </div>
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

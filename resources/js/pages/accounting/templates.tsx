import { Form, Head, Link } from '@inertiajs/react';
import { ArrowLeft, Play, Plus, Trash2 } from 'lucide-react';
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
type Account = { code: string; name: string };
type Line = {
    account_code: string;
    side: 'debit' | 'credit';
    percentage: string;
};
type Template = {
    id: number;
    name: string;
    description: string | null;
    is_system: boolean;
    is_active: boolean;
    lines: (Line & { account: Account })[];
};
export default function Templates({
    templates,
    accounts,
}: {
    templates: Template[];
    accounts: Account[];
}) {
    return (
        <>
            <Head title="Accounting templates" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <header className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold">
                            Entry templates
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Post frequent balanced movements with fewer steps.
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <Button variant="outline" asChild>
                            <Link href="/accounting">
                                <ArrowLeft /> Accounting
                            </Link>
                        </Button>
                        <TemplateDialog accounts={accounts} />
                    </div>
                </header>
                <div className="divide-y border-y">
                    {templates.map((t) => (
                        <section
                            key={t.id}
                            className="flex flex-wrap items-center justify-between gap-4 py-4"
                        >
                            <div>
                                <div className="flex items-center gap-2">
                                    <h2 className="font-medium">{t.name}</h2>
                                    {t.is_system && (
                                        <Badge variant="outline">System</Badge>
                                    )}
                                </div>
                                <p className="text-sm text-muted-foreground">
                                    {t.description}
                                </p>
                                <p className="mt-1 text-xs">
                                    {t.lines
                                        .map(
                                            (l) =>
                                                `${l.account_code} ${l.side} ${Number(l.percentage)}%`,
                                        )
                                        .join(' · ')}
                                </p>
                            </div>
                            <div className="flex gap-1">
                                <Execute template={t} />
                                {!t.is_system && (
                                    <Form
                                        action={`/accounting/templates/${t.id}`}
                                        method="delete"
                                    >
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            aria-label="Delete template"
                                        >
                                            <Trash2 />
                                        </Button>
                                    </Form>
                                )}
                            </div>
                        </section>
                    ))}
                </div>
            </div>
        </>
    );
}
function Execute({ template }: { template: Template }) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button>
                    <Play /> Execute
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{template.name}</DialogTitle>
                </DialogHeader>
                <Form
                    action={`/accounting/templates/${template.id}/execute`}
                    method="post"
                    className="grid gap-4"
                    onSuccess={() => setOpen(false)}
                >
                    <Field label="Date">
                        <Input
                            name="entry_date"
                            type="date"
                            defaultValue={new Date().toISOString().slice(0, 10)}
                            required
                        />
                    </Field>
                    <Field label="Base amount">
                        <Input
                            name="amount"
                            type="number"
                            min="0.01"
                            step="0.01"
                            required
                        />
                    </Field>
                    <Field label="Description">
                        <Input
                            name="description"
                            defaultValue={template.name}
                            required
                        />
                    </Field>
                    <Button>Post journal entry</Button>
                </Form>
            </DialogContent>
        </Dialog>
    );
}
function TemplateDialog({ accounts }: { accounts: Account[] }) {
    const [open, setOpen] = useState(false);
    const [lines, setLines] = useState<Line[]>([
        { account_code: '', side: 'debit', percentage: '100' },
        { account_code: '', side: 'credit', percentage: '100' },
    ]);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button>
                    <Plus /> New template
                </Button>
            </DialogTrigger>
            <DialogContent className="max-w-3xl">
                <DialogHeader>
                    <DialogTitle>New accounting template</DialogTitle>
                </DialogHeader>
                <Form
                    action="/accounting/templates"
                    method="post"
                    className="grid gap-4"
                    onSuccess={() => setOpen(false)}
                >
                    <div className="grid gap-3 sm:grid-cols-2">
                        <Field label="Name">
                            <Input name="name" required />
                        </Field>
                        <Field label="Description">
                            <Input name="description" />
                        </Field>
                    </div>
                    <div className="grid gap-2">
                        {lines.map((line, i) => (
                            <div
                                key={i}
                                className="grid grid-cols-[1fr_8rem_7rem_2.5rem] gap-2"
                            >
                                <Select
                                    name={`lines[${i}][account_code]`}
                                    value={line.account_code}
                                    onValueChange={(v) =>
                                        setLines((x) =>
                                            x.map((l, j) =>
                                                j === i
                                                    ? { ...l, account_code: v }
                                                    : l,
                                            ),
                                        )
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Account" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {accounts.map((a) => (
                                            <SelectItem
                                                key={a.code}
                                                value={a.code}
                                            >
                                                {a.code} · {a.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <Select
                                    name={`lines[${i}][side]`}
                                    value={line.side}
                                    onValueChange={(v: 'debit' | 'credit') =>
                                        setLines((x) =>
                                            x.map((l, j) =>
                                                j === i ? { ...l, side: v } : l,
                                            ),
                                        )
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="debit">
                                            Debit
                                        </SelectItem>
                                        <SelectItem value="credit">
                                            Credit
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <Input
                                    name={`lines[${i}][percentage]`}
                                    type="number"
                                    step="0.0001"
                                    value={line.percentage}
                                    onChange={(e) =>
                                        setLines((x) =>
                                            x.map((l, j) =>
                                                j === i
                                                    ? {
                                                          ...l,
                                                          percentage:
                                                              e.target.value,
                                                      }
                                                    : l,
                                            ),
                                        )
                                    }
                                />
                                <Button
                                    type="button"
                                    size="icon"
                                    variant="ghost"
                                    onClick={() =>
                                        setLines((x) =>
                                            x.filter((_, j) => j !== i),
                                        )
                                    }
                                >
                                    <Trash2 />
                                </Button>
                            </div>
                        ))}
                    </div>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() =>
                            setLines((x) => [
                                ...x,
                                {
                                    account_code: '',
                                    side: 'debit',
                                    percentage: '100',
                                },
                            ])
                        }
                    >
                        <Plus /> Add line
                    </Button>
                    <Button>Create template</Button>
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
Templates.layout = {
    breadcrumbs: [
        { title: 'Finance', href: '/finance' },
        { title: 'Accounting', href: '/accounting' },
        { title: 'Entry templates', href: '/accounting/templates' },
    ],
};

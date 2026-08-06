import { Form, Head, Link, router, usePage } from '@inertiajs/react';
import { ArrowLeft, Download, FileCheck2, Landmark } from 'lucide-react';
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
type FY = { id: number; year: number; status: string };
type Preview = {
    accounting_result: number;
    taxable_base: number;
    gross_tax: number;
    net_tax: number;
    deadline: string;
    bands: { base: number; rate: number }[];
};
type Return = {
    id: number;
    status: string;
    regime: string;
    prior_year_revenue: string;
    permanent_positive: string;
    permanent_negative: string;
    temporary_positive: string;
    temporary_negative: string;
    loss_carryforward: string;
    deductions: string;
    withholdings: string;
    instalment_payments: string;
    filing_reference: string | null;
    filed_on: string | null;
};
const fields = [
    ['permanent_positive', 'Permanent positive adjustments'],
    ['permanent_negative', 'Permanent negative adjustments'],
    ['temporary_positive', 'Temporary positive adjustments'],
    ['temporary_negative', 'Temporary negative adjustments'],
    ['loss_carryforward', 'Tax losses offset'],
    ['deductions', 'Deductions and allowances'],
    ['withholdings', 'Withholdings'],
    ['instalment_payments', 'Instalment payments'],
] as const;
export default function CorporateTax({
    fiscalYear,
    years,
    taxReturn,
    preview,
}: {
    fiscalYear: FY;
    years: FY[];
    taxReturn: Return | null;
    preview: Preview;
}) {
    const admin =
        usePage<{ auth: { user: { role: string } } }>().props.auth.user.role ===
        'admin';

    return (
        <>
            <Head title="Corporate tax" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <header className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold">Corporate tax</h1>
                        <p className="text-sm text-muted-foreground">
                            Calculation workspace and Modelo 200 filing record.
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button variant="outline" asChild>
                            <Link href="/accounting">
                                <ArrowLeft /> Accounting
                            </Link>
                        </Button>
                        <Select
                            value={String(fiscalYear.year)}
                            onValueChange={(year) =>
                                router.get('/corporate-tax', { year })
                            }
                        >
                            <SelectTrigger className="w-28">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {years.map((y) => (
                                    <SelectItem
                                        key={y.id}
                                        value={String(y.year)}
                                    >
                                        {y.year}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                </header>
                {taxReturn && (
                    <section className="flex flex-wrap items-center justify-between gap-3 border-y py-3">
                        <div className="flex items-center gap-3">
                            <Badge variant="outline">{taxReturn.status}</Badge>
                            <span className="text-sm">
                                Calculation for {fiscalYear.year}
                            </span>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            {taxReturn.status === 'prepared' && admin && (
                                <Filing taxReturn={taxReturn} />
                            )}{' '}
                            {taxReturn.status === 'filed' && (
                                <Button variant="outline" asChild>
                                    <a
                                        href={`/corporate-tax/returns/${taxReturn.id}/receipt`}
                                    >
                                        <Download /> Receipt
                                    </a>
                                </Button>
                            )}
                        </div>
                    </section>
                )}
                <div className="grid gap-8 xl:grid-cols-[minmax(0,1fr)_24rem]">
                    <Form
                        action="/corporate-tax/returns"
                        method="post"
                        className="grid gap-5"
                    >
                        <input
                            type="hidden"
                            name="fiscal_year_id"
                            value={fiscalYear.id}
                        />
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field label="Tax regime">
                                <Select
                                    name="regime"
                                    defaultValue={
                                        taxReturn?.regime || 'general'
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="general">
                                            General · 25%
                                        </SelectItem>
                                        <SelectItem value="micro">
                                            Micro company · revenue under €1m
                                        </SelectItem>
                                        <SelectItem value="reduced">
                                            Reduced-size company
                                        </SelectItem>
                                        <SelectItem value="new_entity">
                                            New entity · 15%
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </Field>
                            <Field label="Prior-year net revenue">
                                <MoneyInput
                                    name="prior_year_revenue"
                                    value={taxReturn?.prior_year_revenue}
                                />
                            </Field>
                        </div>
                        <section>
                            <h2 className="mb-3 font-semibold">
                                Tax adjustments
                            </h2>
                            <div className="grid gap-4 sm:grid-cols-2">
                                {fields.map(([name, label]) => (
                                    <Field key={name} label={label}>
                                        <MoneyInput
                                            name={name}
                                            value={taxReturn?.[name]}
                                        />
                                    </Field>
                                ))}
                            </div>
                        </section>
                        {admin ? (
                            <Button>
                                <FileCheck2 /> Calculate and prepare
                            </Button>
                        ) : (
                            <p className="text-sm text-muted-foreground">
                                An administrator must prepare the tax
                                calculation.
                            </p>
                        )}
                    </Form>
                    <aside>
                        <h2 className="mb-3 font-semibold">Calculation</h2>
                        <div className="divide-y border-y text-sm">
                            <Row
                                label="Accounting result"
                                value={preview.accounting_result}
                            />
                            <Row
                                label="Taxable base"
                                value={preview.taxable_base}
                            />
                            {preview.bands.map((b, i) => (
                                <Row
                                    key={i}
                                    label={`${b.rate}% on ${money(b.base)}`}
                                    value={(b.base * b.rate) / 100}
                                />
                            ))}
                            <Row label="Gross tax" value={preview.gross_tax} />
                            <Row
                                label={
                                    preview.net_tax >= 0
                                        ? 'Amount payable'
                                        : 'Amount refundable'
                                }
                                value={Math.abs(preview.net_tax)}
                                strong
                            />
                        </div>
                        <div className="mt-5 border-y py-4">
                            <div className="flex items-center gap-2 text-sm font-medium">
                                <Landmark /> Filing window
                            </div>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Deadline {date(preview.deadline)}. File Modelo
                                200 electronically through AEAT Sociedades WEB
                                and record its reference and receipt here.
                            </p>
                        </div>
                    </aside>
                </div>
            </div>
        </>
    );
}
function Filing({ taxReturn }: { taxReturn: Return }) {
    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button>
                    <FileCheck2 /> Record filing
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Record Modelo 200 filing</DialogTitle>
                </DialogHeader>
                <Form
                    action={`/corporate-tax/returns/${taxReturn.id}/file`}
                    method="post"
                    className="grid gap-4"
                >
                    <Field label="Filing reference">
                        <Input name="filing_reference" required />
                    </Field>
                    <Field label="Filed on">
                        <Input
                            name="filed_on"
                            type="date"
                            defaultValue={new Date().toISOString().slice(0, 10)}
                            required
                        />
                    </Field>
                    <Field label="Official receipt (PDF)">
                        <Input
                            name="receipt"
                            type="file"
                            accept="application/pdf,.pdf"
                            required
                        />
                    </Field>
                    <Button>Mark as filed</Button>
                </Form>
            </DialogContent>
        </Dialog>
    );
}
function MoneyInput({ name, value }: { name: string; value?: string }) {
    return (
        <Input
            name={name}
            type="number"
            min="0"
            step="0.01"
            defaultValue={value || '0'}
            required
        />
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
function Row({
    label,
    value,
    strong = false,
}: {
    label: string;
    value: number;
    strong?: boolean;
}) {
    return (
        <div
            className={`flex justify-between gap-3 py-3 ${strong ? 'font-semibold' : ''}`}
        >
            <span>{label}</span>
            <span>{money(value)}</span>
        </div>
    );
}
function money(v: number) {
    return new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: 'EUR',
    }).format(v);
}
function date(v: string) {
    return new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(
        new Date(`${v}T00:00:00`),
    );
}
CorporateTax.layout = {
    breadcrumbs: [
        { title: 'Finance', href: '/finance' },
        { title: 'Corporate tax', href: '/corporate-tax' },
    ],
};

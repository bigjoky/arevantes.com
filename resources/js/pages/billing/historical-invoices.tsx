import { QuickSupplier, type QuickSupplierValue } from '@/components/quick-supplier';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { PageHeader, PageShell } from '@/components/page-shell';
import { Button } from '@/components/ui/button';

type Row = { id: number; batch_id: number; status: string; document: Record<string, string | number | null>; errors: string[]; expense_id: number | null; evidence: string | null; created_at: string; accounting_date: string | null };
type Supplier = { id: number; legal_name: string; tax_id: string | null };
type Category = { id: number; name: string };
const blank = { supplier_id: '', supplier_tax_id: '', expense_category_id: '', supplier_number: '', issue_date: '', operation_date: '', received_date: '', due_date: '', taxable_base: '', vat_rate: '', vat_amount: '', withholding_rate: '0', withholding_amount: '0', total: '', notes: '', evidence: null as File | null };
type FormData = typeof blank;

export default function HistoricalInvoices({ rows, suppliers, categories, selectedYear, canManageSuppliers }: { rows: Row[]; suppliers: Supplier[]; categories: Category[]; selectedYear: number; canManageSuppliers: boolean }) {
    const [addedSuppliers, setAddedSuppliers] = useState<QuickSupplierValue[]>([]);
    const allSuppliers = [...suppliers, ...addedSuppliers.filter((item) => !suppliers.some((supplier) => supplier.id === item.id))];
    const [editing, setEditing] = useState<number | null>(null);
    const [accountingDate, setAccountingDate] = useState('');
    const [confirmError, setConfirmError] = useState('');
    const form = useForm<FormData>(blank);
    const csv = useForm<{ csv: File | null }>({ csv: null });
    function edit(row: Row) {
        const values = { ...blank };
        (Object.keys(blank) as (keyof FormData)[]).forEach((key) => {
            if (key !== 'evidence') {
values[key] = String(row.document[key] ?? blank[key]);
}
        });
        setEditing(row.id);
        setConfirmError('');
        setAccountingDate(row.accounting_date ?? '');
        form.setData(values);
        form.setDefaults(values);
        form.clearErrors();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
    function save(event: React.FormEvent) {
        event.preventDefault();
        form.post(editing ? `/billing/historical-invoices/${editing}` : '/billing/historical-invoices', { forceFormData: true, onSuccess: () => {
 form.setDefaults(blank); form.setData(blank); setEditing(null);
} });
    }
    const field = (name: Exclude<keyof FormData, 'evidence'>, label: string, type = 'text') => <label className="grid min-w-0 gap-1 text-sm">{label}<input className="min-w-0 w-full rounded border bg-white px-3 py-2 text-slate-900" type={type} value={form.data[name]} onChange={(e) => form.setData(name, e.target.value)} />{form.errors[name] && <span className="text-red-700">{form.errors[name]}</span>}</label>;

    return <PageShell className="billing-workspace">
        <Head title="Regularización histórica" />
        <PageHeader title="Regularización histórica de facturas recibidas" description="Prepare y revise cada factura antes de solicitar su registro." breadcrumbs={[{ title: 'Facturación', href: '/billing' }, { title: 'Facturas de proveedores', href: '/billing/suppliers' }, { title: 'Regularización histórica', href: '/billing/historical-invoices' }]} />
        {![2024, 2025, 2026].includes(selectedYear) ? <p role="alert" className="rounded-xl border bg-white p-5">Selecciona el ejercicio 2024, 2025 o 2026 para continuar.</p> :
        <div className="space-y-6">
            <section className="rounded-xl border bg-white p-5 text-slate-900"><h2 className="mb-2 text-lg font-semibold">{editing ? `Editar preparación #${editing}` : 'Nueva preparación'}</h2><p className="mb-4 text-sm text-slate-600">Conserva la fecha original de emisión y autoriza la fecha contable al confirmar. Después podrás registrar un pago acreditado desde la ficha de la factura con su fecha real. La deducción fiscal requiere revisión.</p>
                {canManageSuppliers && <a href="/suppliers/manage" className="mb-4 inline-block text-green-700 underline">Crear o gestionar proveedores</a>}
                <form onSubmit={save} className="grid gap-4 md:grid-cols-3">
                    <label className="grid min-w-0 gap-1 text-sm">Proveedor<select className="min-w-0 w-full rounded border bg-white px-3 py-2 text-slate-900" value={form.data.supplier_id} onChange={(e) => {
 const id = e.target.value; const supplier = allSuppliers.find((s) => String(s.id) === id); form.setData((data) => ({ ...data, supplier_id: id, supplier_tax_id: data.supplier_tax_id || supplier?.tax_id || '' }));
}}><option value="">Seleccionar</option>{allSuppliers.map((s) => <option key={s.id} value={s.id}>{s.legal_name}</option>)}</select>{form.errors.supplier_id && <span className="text-red-700">{form.errors.supplier_id}</span>}</label>
                    {canManageSuppliers && <QuickSupplier onSelect={(supplier) => { setAddedSuppliers((items) => [...items, supplier]); form.setData((data) => ({ ...data, supplier_id: String(supplier.id), supplier_tax_id: supplier.tax_id || '' })); }} />}
                    {field('supplier_tax_id', 'NIF del proveedor')}
                    <label className="grid min-w-0 gap-1 text-sm">Categoría de gasto<select className="min-w-0 w-full rounded border bg-white px-3 py-2 text-slate-900" value={form.data.expense_category_id} onChange={(e) => form.setData('expense_category_id', e.target.value)}><option value="">Seleccionar</option>{categories.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}</select>{form.errors.expense_category_id && <span className="text-red-700">{form.errors.expense_category_id}</span>}</label>
                    <div className="grid gap-1 text-sm"><>{field('supplier_number', 'Número de factura del proveedor')}</><span className="text-xs text-slate-600">Es el número que figura en la factura original del proveedor; no es el número interno ni el número de asiento. AREVANTES asigna sus identificadores internos al registrar.</span></div>{field('issue_date', 'Fecha de emisión', 'date')}{field('operation_date', 'Fecha de operación', 'date')}{field('received_date', 'Fecha de recepción acreditada (solo si difiere; requiere revisión)', 'date')}{field('due_date', 'Vencimiento', 'date')}{field('taxable_base', 'Base imponible')}{field('vat_rate', 'IVA %')}{field('vat_amount', 'Cuota IVA')}{field('withholding_rate', 'Retención % (requiere revisión)')}{field('withholding_amount', 'Retención importe (requiere revisión)')}{field('total', 'Total')}<div className="grid gap-1 text-sm"><>{field('notes', 'Notas internas (opcional)')}</><span className="text-xs text-slate-600">Use este campo para observaciones. La referencia documental es un identificador complementario del soporte (por ejemplo, albarán, contrato o expediente), no el número de factura ni el número interno de contabilización. Mientras no exista un campo específico, indíquela aquí como “Ref. documental: …”.</span></div>
                    <label className="grid min-w-0 gap-1 text-sm">Justificante PDF<input className="max-w-full" type="file" accept="application/pdf" onChange={(e) => form.setData('evidence', e.target.files?.[0] ?? null)} />{form.errors.evidence && <span className="text-red-700">{form.errors.evidence}</span>}</label>
                    <div className="flex items-end gap-2"><Button type="submit" disabled={form.processing}>Guardar preparación</Button>{editing && <Button type="button" variant="outline" onClick={() => {
 form.reset(); setEditing(null);
}}>Cancelar edición</Button>}</div>
                </form>
            </section>
            <section className="rounded-xl border bg-white p-5 text-slate-900"><h2 className="mb-2 text-lg font-semibold">Importar CSV</h2><p className="mb-3 text-sm">Máximo 1 MB y 1.000 filas. La importación no crea obligaciones ni asientos.</p><a className="text-green-700 underline" href="/billing/historical-invoices/template">Descargar plantilla CSV</a><form className="mt-3 flex flex-wrap gap-3" onSubmit={(e) => {
 e.preventDefault(); csv.post('/billing/historical-invoices/import', { forceFormData: true });
}}><input className="max-w-full" type="file" accept=".csv,.txt" onChange={(e) => csv.setData('csv', e.target.files?.[0] ?? null)} /><Button disabled={csv.processing}>Importar para revisión</Button>{csv.errors.csv && <span className="text-red-700">{csv.errors.csv}</span>}</form></section>
            <section className="rounded-xl border bg-white p-5 text-slate-900"><h2 className="mb-3 text-lg font-semibold">Preparaciones recientes</h2>{rows.length === 0 && <p>No hay preparaciones.</p>}<div className="space-y-3">{rows.map((row) => <article key={row.id} className="rounded border p-4"><div className="flex flex-wrap items-center justify-between gap-2"><strong>#{row.id} · {String(row.document.supplier_number || 'Sin número')}</strong><span>{row.status === 'registered' ? 'Registrada' : 'Pendiente de revisión'}</span></div><p className="mt-1 text-sm">Lote {row.batch_id} · Emisión {String(row.document.issue_date || 'sin fecha')} · Registro de preparación {row.created_at} · Justificante: {row.evidence || 'no adjunto'}</p>{row.errors.length > 0 && <ul className="mt-2 list-disc pl-5 text-sm text-red-700">{row.errors.map((error, i) => <li key={i}>{error}</li>)}</ul>}{row.expense_id ? <Link className="mt-2 inline-block text-green-700 underline" href={`/billing/suppliers/expense/${row.expense_id}`}>Abrir gasto registrado</Link> : <div className="mt-3 flex flex-wrap items-end gap-3"><Button variant="outline" type="button" onClick={() => edit(row)}>Revisar y corregir</Button><label className="grid min-w-0 gap-1 text-sm">Fecha contable autorizada<input className="min-w-0 w-full rounded border bg-white px-3 py-2 text-slate-900" type="date" value={editing === row.id ? accountingDate : ''} onFocus={() => {
 if (editing !== row.id) {
edit(row);
}
}} onChange={(e) => setAccountingDate(e.target.value)} /></label><Button type="button" disabled={editing !== row.id || !accountingDate || form.isDirty} onClick={() => {
 const otherYear = !accountingDate.startsWith(`${selectedYear}-`); const message = otherYear ? `La fecha contable pertenece a ${accountingDate.slice(0, 4)}, distinto del ejercicio ${selectedYear}. ¿Confirmas expresamente el registro en ese ejercicio?` : '¿Confirmar los datos guardados y su asiento con la fecha contable indicada?';

 if (window.confirm(message)) {
router.post(`/billing/historical-invoices/${row.id}/confirm`, { accounting_date: accountingDate, confirm_other_year: otherYear }, { onError: (errors) => setConfirmError(Object.values(errors).join(' ')) });
}
}}>Confirmar registro</Button>{editing === row.id && confirmError && <p role="alert" className="text-sm text-red-700">{confirmError}</p>}</div>}</article>)}</div></section>
        </div>}
    </PageShell>;
}

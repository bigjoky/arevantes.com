import { Form, Head, Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import { Empty, Panel, Pagination, date } from '@/components/billing/workspace';
import type { Page } from '@/components/billing/workspace';
import { PageHeader, PageShell, PageTabs } from '@/components/page-shell';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { label, State, size } from './common';
import type { Employee } from './common';
import '@/../css/company.css';
type Props = {
    employee: Employee;
    tab: string;
    canSensitive: boolean;
    documents: Page<{
        id: number;
        title: string;
        category: string;
        mime: string;
        size: number;
        expires: string | null;
        href: string;
    }>;
    history: Page<{
        id: number;
        date: string;
        actor: string | null;
        fields: string[];
    }>;
    leave: Page<{ id: number; start: string; end: string; status: string }>;
    departments: { id: number; name: string }[];
    fingerprint: string;
    returnFilters: Record<string, string | number>;
};
export default function CompanyEmployee(p: Props) {
    const [editing, setEditing] = useState(false);
    const e = p.employee;
    const back =
        '/company/staff?' +
        new URLSearchParams(
            Object.entries(p.returnFilters).map(([k, v]) => [
                k.replace('return_', ''),
                String(v),
            ]),
        );
    const query = new URLSearchParams(
        Object.entries(p.returnFilters).map(([k, v]) => [k, String(v)]),
    );

    return (
        <>
            <Head title={e.name} />
            <PageShell mode="full" className="company-workspace">
                <PageHeader
                    title={e.name}
                    description={`${e.job ?? 'Puesto sin registrar'} · ${e.department ?? 'Departamento sin registrar'}`}
                    secondaryNavigation={null}
                    actions={
                        <>
                            <Button variant="outline" asChild>
                                <Link href={back}>Volver a personal</Link>
                            </Button>
                            <Button onClick={() => setEditing(!editing)}>
                                Editar ficha laboral
                            </Button>
                        </>
                    }
                />
                <div>
                    <State value={e.status} />
                </div>
                <PageTabs>
                    {[
                        ['summary', 'Resumen'],
                        ['contracts', 'Contratación'],
                        ['training', 'Formación'],
                        ['absences', 'Ausencias'],
                        ['documents', 'Documentos'],
                        ['history', 'Historial'],
                    ].map(([id, title]) => (
                        <Link
                            className={p.tab === id ? 'active' : ''}
                            key={id}
                            href={`${e.href}?${query}&tab=${id}`}
                        >
                            {title}
                        </Link>
                    ))}
                </PageTabs>
                {editing ? (
                    <Panel title="Editar información profesional">
                        <EmploymentForm
                            employee={e}
                            departments={p.departments}
                            fingerprint={p.fingerprint}
                        />
                    </Panel>
                ) : ['summary', 'contracts'].includes(p.tab) ? (
                    <div className="company-grid">
                        <Panel title="Información profesional">
                            <dl className="company-description">
                                {[
                                    ['Número de empleado', e.number],
                                    ['Puesto', e.job],
                                    ['Departamento', e.department],
                                    ['Contrato', label(e.type)],
                                    ['Fecha de incorporación', date(e.start)],
                                    ['Fin de contrato', date(e.end)],
                                    [
                                        'Fin del periodo de prueba',
                                        date(e.probation),
                                    ],
                                    ['Centro de trabajo', e.location],
                                ].map(([key, value]) => (
                                    <div key={key}>
                                        <dt>{key}</dt>
                                        <dd>{value ?? '—'}</dd>
                                    </div>
                                ))}
                            </dl>
                        </Panel>
                        <Panel title="Administración de la ficha">
                            <p>
                                La ficha laboral no crea una cuenta de acceso al
                                ERP.
                            </p>
                            {p.canSensitive && (
                                <Button variant="outline" asChild>
                                    <Link
                                        href={`/human-resources/employees/${e.id}`}
                                    >
                                        Abrir expediente autorizado
                                    </Link>
                                </Button>
                            )}
                            <p className="company-note">
                                Los datos personales, retributivos y médicos
                                requieren permisos específicos.
                            </p>
                        </Panel>
                    </div>
                ) : p.tab === 'history' ? (
                    <Panel title="Historial contractual">
                        {p.history.data.map((h) => (
                            <div className="company-record" key={h.id}>
                                <span>{date(h.date)}</span>
                                <span>
                                    {h.actor ?? 'Usuario no disponible'}
                                </span>
                                <span>
                                    {h.fields.length
                                        ? 'Información profesional modificada'
                                        : 'Actualización administrativa'}
                                </span>
                            </div>
                        ))}
                        <Pagination page={p.history} noun="cambios" />
                        {!p.history.total && (
                            <Empty>
                                No hay cambios contractuales registrados.
                            </Empty>
                        )}
                    </Panel>
                ) : p.tab === 'absences' ? (
                    <Panel title="Ausencias administrativas">
                        {p.leave.data.map((l) => (
                            <div className="company-record" key={l.id}>
                                <span>
                                    {date(l.start)} — {date(l.end)}
                                </span>
                                <State value={l.status} />
                            </div>
                        ))}
                        <Pagination page={p.leave} noun="ausencias" />
                        {!p.leave.total && (
                            <Empty>No hay ausencias registradas.</Empty>
                        )}
                        <p className="company-note">
                            Los motivos y documentos médicos no se muestran en
                            esta vista.
                        </p>
                    </Panel>
                ) : (
                    <Panel
                        title={
                            p.tab === 'training'
                                ? 'Acreditaciones documentales'
                                : 'Documentación laboral'
                        }
                    >
                        {p.documents.data.map((d) => (
                            <div className="company-record" key={d.id}>
                                <a href={d.href}>{d.title}</a>
                                <span>
                                    {label(d.category)} · {size(d.size)}
                                </span>
                                <span>{date(d.expires)}</span>
                            </div>
                        ))}
                        {!p.documents.total && (
                            <Empty>
                                No hay documentos autorizados para consultar.
                            </Empty>
                        )}
                        <Pagination page={p.documents} noun="documentos" />
                        <p className="company-note">
                            La gestión de documentos y los datos sensibles se
                            mantienen en el expediente autorizado.
                        </p>
                    </Panel>
                )}
            </PageShell>
        </>
    );
}
export function NewEmployee({
    departments,
}: {
    departments: { id: number; name: string }[];
}) {
    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button>
                    <Plus />
                    Nuevo empleado
                </Button>
            </DialogTrigger>
            <DialogContent className="company-workspace company-dialog max-h-[90vh] overflow-y-auto sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>Nuevo empleado</DialogTitle>
                </DialogHeader>
                <EmploymentForm departments={departments} />
            </DialogContent>
        </Dialog>
    );
}
function EmploymentForm({
    employee,
    departments,
    fingerprint,
}: {
    employee?: Employee;
    departments: { id: number; name: string }[];
    fingerprint?: string;
}) {
    return (
        <Form
            action={
                employee ? `/company/staff/${employee.id}` : '/company/staff'
            }
            method={employee ? 'put' : 'post'}
            className="company-edit-form"
        >
            {({ errors, processing }) => (
                <>
                    {fingerprint && (
                        <input
                            type="hidden"
                            name="expected_fingerprint"
                            value={fingerprint}
                        />
                    )}
                    {!employee && (
                        <>
                            <label>
                                Nombre
                                <Input
                                    name="first_name"
                                    required
                                    maxLength={100}
                                />
                            </label>
                            <label>
                                Apellidos
                                <Input name="last_name" maxLength={100} />
                            </label>
                        </>
                    )}
                    <label>
                        Número de empleado
                        <Input
                            name="employee_number"
                            required={!employee}
                            defaultValue={employee?.number ?? ''}
                        />
                    </label>
                    <label>
                        Puesto
                        <Input
                            name="job_title"
                            defaultValue={employee?.job ?? ''}
                        />
                    </label>
                    <label>
                        Departamento
                        <select
                            name="department_id"
                            defaultValue={employee?.department_id ?? ''}
                        >
                            <option value="">Sin departamento</option>
                            {departments.map((d) => (
                                <option key={d.id} value={d.id}>
                                    {d.name}
                                </option>
                            ))}
                        </select>
                    </label>
                    <label>
                        Tipo de contrato
                        <select
                            name="employment_type"
                            required
                            defaultValue={employee?.type ?? ''}
                        >
                            <option value="">Seleccionar</option>
                            {[
                                'permanent',
                                'temporary',
                                'internship',
                                'contractor',
                            ].map((v) => (
                                <option value={v} key={v}>
                                    {label(v)}
                                </option>
                            ))}
                        </select>
                    </label>
                    <label>
                        Estado
                        <select
                            name="status"
                            required
                            defaultValue={employee?.status ?? ''}
                        >
                            <option value="">Seleccionar</option>
                            {['active', 'on_leave', 'ended'].map((v) => (
                                <option key={v} value={v}>
                                    {label(v)}
                                </option>
                            ))}
                        </select>
                    </label>
                    <label>
                        Fecha de incorporación
                        <Input
                            name="start_date"
                            type="date"
                            defaultValue={employee?.start ?? ''}
                        />
                    </label>
                    <label>
                        Fin de contrato
                        <Input
                            name="end_date"
                            type="date"
                            defaultValue={employee?.end ?? ''}
                        />
                    </label>
                    <label>
                        Centro de trabajo
                        <Input
                            name="work_location"
                            defaultValue={employee?.location ?? ''}
                        />
                    </label>
                    <label>
                        Fecha efectiva del cambio
                        <Input name="change_effective_date" type="date" />
                    </label>
                    <label>
                        Motivo del cambio
                        <Input name="change_reason" maxLength={255} />
                    </label>
                    {Object.entries(errors).map(([k, v]) => (
                        <p role="alert" key={k} className="text-destructive">
                            {v}
                        </p>
                    ))}
                    <Button disabled={processing}>Guardar ficha laboral</Button>
                </>
            )}
        </Form>
    );
}

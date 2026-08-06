import { Form, Head, Link } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowLeft,
    BriefcaseBusiness,
    Download,
    FileText,
    History,
    LockKeyhole,
    Phone,
    Trash2,
    Upload,
} from 'lucide-react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

type Employment = {
    employeeNumber: string | null;
    jobTitle: string | null;
    professionalCategory: string | null;
    department: string | null;
    manager: string | null;
    employmentType: string;
    status: string;
    workLocation: string | null;
    scheduleType: string | null;
    weeklyHours: string | null;
    startDate: string | null;
    probationEndDate: string | null;
    endDate: string | null;
    annualReferenceSalary: string | null;
    collectiveAgreement: string | null;
    costCenter: string | null;
    payrollCode: string | null;
};
type Change = {
    id: number;
    effectiveDate: string;
    reason: string;
    changedBy: string | null;
    changes: Record<string, unknown>;
};
type EmergencyContact = {
    id: number;
    name: string;
    relationship: string | null;
    phone: string;
    preventionNotes: string | null;
    isPrimary: boolean;
};
type Document = {
    id: number;
    category: string;
    title: string;
    originalName: string;
    size: number;
    visibility: string;
    expiresAt: string | null;
    isExpired: boolean;
    uploadedBy: string | null;
};
type Employee = {
    id: number;
    name: string;
    email: string | null;
    phone: string | null;
    staffRole: string | null;
    employment: Employment | null;
    history: Change[];
    emergencyContacts: EmergencyContact[];
    documents: Document[];
};

const selectClass =
    'border-input bg-background h-9 w-full rounded-md border px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]';

export default function Dossier({
    employee,
    canViewSensitive,
}: {
    employee: Employee;
    canViewSensitive: boolean;
}) {
    const employment = employee.employment;

    return (
        <>
            <Head title={`Expediente · ${employee.name}`} />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <header className="flex flex-wrap items-start justify-between gap-4">
                    <div className="space-y-2">
                        <Button variant="ghost" size="sm" asChild>
                            <Link href="/human-resources/employees">
                                <ArrowLeft /> Fichas laborales
                            </Link>
                        </Button>
                        <div>
                            <h1 className="text-xl font-semibold">
                                {employee.name}
                            </h1>
                            <p className="text-sm text-muted-foreground">
                                Expediente laboral, trazabilidad y documentación
                                protegida.
                            </p>
                        </div>
                    </div>
                    {canViewSensitive ? (
                        <Button variant="outline" asChild>
                            <a
                                href={`/human-resources/employees/${employee.id}/export`}
                            >
                                <Download /> Exportar para gestoría
                            </a>
                        </Button>
                    ) : null}
                </header>

                <div className="grid gap-6 xl:grid-cols-[minmax(0,1.25fr)_minmax(20rem,0.75fr)]">
                    <div className="grid gap-6">
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <BriefcaseBusiness className="size-5" />
                                    Condiciones contractuales
                                </CardTitle>
                                <CardDescription>
                                    Información vigente del puesto y la jornada.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                {employment ? (
                                    <dl className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                                        <Detail
                                            label="N.º empleado"
                                            value={employment.employeeNumber}
                                        />
                                        <Detail
                                            label="Puesto"
                                            value={employment.jobTitle}
                                        />
                                        <Detail
                                            label="Categoría profesional"
                                            value={
                                                employment.professionalCategory
                                            }
                                        />
                                        <Detail
                                            label="Departamento"
                                            value={employment.department}
                                        />
                                        <Detail
                                            label="Responsable"
                                            value={employment.manager}
                                        />
                                        <Detail
                                            label="Centro"
                                            value={employment.workLocation}
                                        />
                                        <Detail
                                            label="Contrato"
                                            value={employmentTypeLabel(
                                                employment.employmentType,
                                            )}
                                        />
                                        <Detail
                                            label="Jornada"
                                            value={
                                                employment.weeklyHours
                                                    ? `${Number(employment.weeklyHours)} h · ${scheduleLabel(employment.scheduleType)}`
                                                    : scheduleLabel(
                                                          employment.scheduleType,
                                                      )
                                            }
                                        />
                                        <Detail
                                            label="Alta"
                                            value={formatDate(
                                                employment.startDate,
                                            )}
                                        />
                                    </dl>
                                ) : (
                                    <p className="text-sm text-muted-foreground">
                                        La ficha contractual todavía no está
                                        completada.
                                    </p>
                                )}
                            </CardContent>
                        </Card>

                        {canViewSensitive ? (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <LockKeyhole className="size-5" />
                                        Datos restringidos
                                    </CardTitle>
                                    <CardDescription>
                                        Retribución de referencia y claves para
                                        la gestoría. Estos campos se almacenan
                                        cifrados.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <Form
                                        action={`/human-resources/employees/${employee.id}/sensitive`}
                                        method="put"
                                        className="grid gap-4 sm:grid-cols-2"
                                    >
                                        {({ processing, errors }) => (
                                            <>
                                                <Field
                                                    label="Salario bruto anual de referencia"
                                                    name="annual_reference_salary"
                                                    type="number"
                                                    min="0"
                                                    step="0.01"
                                                    defaultValue={
                                                        employment?.annualReferenceSalary ??
                                                        ''
                                                    }
                                                    error={
                                                        errors.annual_reference_salary
                                                    }
                                                />
                                                <Field
                                                    label="Convenio colectivo"
                                                    name="collective_agreement"
                                                    defaultValue={
                                                        employment?.collectiveAgreement ??
                                                        ''
                                                    }
                                                    error={
                                                        errors.collective_agreement
                                                    }
                                                />
                                                <Field
                                                    label="Centro de coste"
                                                    name="cost_center"
                                                    defaultValue={
                                                        employment?.costCenter ??
                                                        ''
                                                    }
                                                    error={errors.cost_center}
                                                />
                                                <Field
                                                    label="Código de nómina"
                                                    name="payroll_code"
                                                    defaultValue={
                                                        employment?.payrollCode ??
                                                        ''
                                                    }
                                                    error={errors.payroll_code}
                                                />
                                                <Field
                                                    label="Fecha efectiva"
                                                    name="change_effective_date"
                                                    type="date"
                                                    required
                                                    error={
                                                        errors.change_effective_date
                                                    }
                                                />
                                                <Field
                                                    label="Motivo del cambio"
                                                    name="change_reason"
                                                    required
                                                    placeholder="Revisión anual, cambio de convenio…"
                                                    error={errors.change_reason}
                                                />
                                                <div className="sm:col-span-2">
                                                    <Button
                                                        disabled={processing}
                                                    >
                                                        Guardar datos
                                                        restringidos
                                                    </Button>
                                                </div>
                                            </>
                                        )}
                                    </Form>
                                </CardContent>
                            </Card>
                        ) : null}

                        <Documents employee={employee} />
                    </div>

                    <div className="grid content-start gap-6">
                        {canViewSensitive ? (
                            <EmergencyContacts employee={employee} />
                        ) : null}
                        <HistoryCard history={employee.history} />
                    </div>
                </div>
            </div>
        </>
    );
}

function Documents({ employee }: { employee: Employee }) {
    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <FileText className="size-5" /> Documentos laborales
                </CardTitle>
                <CardDescription>
                    Archivos privados con visibilidad y caducidad controladas.
                </CardDescription>
            </CardHeader>
            <CardContent className="grid gap-5">
                <Form
                    action={`/human-resources/employees/${employee.id}/documents`}
                    method="post"
                    className="grid gap-3 rounded-lg border p-4 sm:grid-cols-2"
                >
                    {({ processing, errors }) => (
                        <>
                            <Field
                                label="Título"
                                name="title"
                                required
                                error={errors.title}
                            />
                            <div className="grid gap-2">
                                <Label>Categoría</Label>
                                <select name="category" className={selectClass}>
                                    <option value="contract">Contrato</option>
                                    <option value="identity">
                                        Identificación
                                    </option>
                                    <option value="certificate">
                                        Certificado
                                    </option>
                                    <option value="prevention">
                                        Prevención
                                    </option>
                                    <option value="payroll">Nómina</option>
                                    <option value="other">Otro</option>
                                </select>
                            </div>
                            <div className="grid gap-2">
                                <Label>Visibilidad</Label>
                                <select
                                    name="visibility"
                                    className={selectClass}
                                >
                                    <option value="hr_only">
                                        Solo RR. HH.
                                    </option>
                                    <option value="employee">
                                        RR. HH. y empleado
                                    </option>
                                </select>
                            </div>
                            <Field
                                label="Caducidad"
                                name="expires_at"
                                type="date"
                                error={errors.expires_at}
                            />
                            <Field
                                label="Archivo"
                                name="file"
                                type="file"
                                required
                                accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"
                                error={errors.file}
                            />
                            <div className="flex items-end">
                                <Button disabled={processing}>
                                    <Upload /> Subir documento
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
                <div className="grid gap-3">
                    {employee.documents.map((document) => (
                        <div
                            key={document.id}
                            className="flex flex-wrap items-center justify-between gap-3 rounded-lg border p-3"
                        >
                            <div className="min-w-0">
                                <div className="flex flex-wrap items-center gap-2">
                                    <span className="font-medium">
                                        {document.title}
                                    </span>
                                    <Badge variant="secondary">
                                        {document.visibility === 'employee'
                                            ? 'Visible para empleado'
                                            : 'Solo RR. HH.'}
                                    </Badge>
                                    {document.isExpired ? (
                                        <Badge variant="destructive">
                                            Caducado
                                        </Badge>
                                    ) : null}
                                </div>
                                <p className="truncate text-xs text-muted-foreground">
                                    {document.originalName} ·{' '}
                                    {formatBytes(document.size)}
                                    {document.expiresAt
                                        ? ` · Caduca ${formatDate(document.expiresAt)}`
                                        : ''}
                                </p>
                            </div>
                            <div className="flex gap-1">
                                <Button variant="ghost" size="icon" asChild>
                                    <a
                                        href={`/human-resources/employees/${employee.id}/documents/${document.id}`}
                                        aria-label="Descargar documento"
                                    >
                                        <Download />
                                    </a>
                                </Button>
                                <Form
                                    action={`/human-resources/employees/${employee.id}/documents/${document.id}`}
                                    method="delete"
                                >
                                    {({ processing }) => (
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            disabled={processing}
                                            aria-label="Eliminar documento"
                                        >
                                            <Trash2 />
                                        </Button>
                                    )}
                                </Form>
                            </div>
                        </div>
                    ))}
                    {!employee.documents.length ? (
                        <p className="text-sm text-muted-foreground">
                            No hay documentos en el expediente.
                        </p>
                    ) : null}
                </div>
            </CardContent>
        </Card>
    );
}

function EmergencyContacts({ employee }: { employee: Employee }) {
    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <Phone className="size-5" /> Emergencia y prevención
                </CardTitle>
                <CardDescription>
                    Datos cifrados y limitados al permiso sensible.
                </CardDescription>
            </CardHeader>
            <CardContent className="grid gap-4">
                {employee.emergencyContacts.map((contact) => (
                    <div key={contact.id} className="rounded-lg border p-3">
                        <div className="flex items-start justify-between gap-2">
                            <div>
                                <div className="font-medium">
                                    {contact.name}{' '}
                                    {contact.isPrimary ? (
                                        <Badge>Principal</Badge>
                                    ) : null}
                                </div>
                                <p className="text-sm text-muted-foreground">
                                    {contact.relationship || 'Sin parentesco'} ·{' '}
                                    {contact.phone}
                                </p>
                                {contact.preventionNotes ? (
                                    <p className="mt-2 text-sm">
                                        {contact.preventionNotes}
                                    </p>
                                ) : null}
                            </div>
                            <Form
                                action={`/human-resources/employees/${employee.id}/emergency-contacts/${contact.id}`}
                                method="delete"
                            >
                                <Button variant="ghost" size="icon">
                                    <Trash2 />
                                </Button>
                            </Form>
                        </div>
                    </div>
                ))}
                <Form
                    action={`/human-resources/employees/${employee.id}/emergency-contacts`}
                    method="post"
                    className="grid gap-3 border-t pt-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <Field
                                label="Nombre"
                                name="name"
                                required
                                error={errors.name}
                            />
                            <Field
                                label="Relación"
                                name="relationship"
                                error={errors.relationship}
                            />
                            <Field
                                label="Teléfono"
                                name="phone"
                                required
                                error={errors.phone}
                            />
                            <div className="grid gap-2">
                                <Label>Datos necesarios de prevención</Label>
                                <Textarea name="prevention_notes" />
                                <InputError message={errors.prevention_notes} />
                            </div>
                            <label className="flex items-center gap-2 text-sm">
                                <input
                                    type="checkbox"
                                    name="is_primary"
                                    value="1"
                                />
                                Contacto principal
                            </label>
                            <Button disabled={processing}>
                                Añadir contacto
                            </Button>
                        </>
                    )}
                </Form>
            </CardContent>
        </Card>
    );
}

function HistoryCard({ history }: { history: Change[] }) {
    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <History className="size-5" /> Historial contractual
                </CardTitle>
                <CardDescription>
                    Fecha efectiva, responsable y campos modificados.
                </CardDescription>
            </CardHeader>
            <CardContent className="grid gap-4">
                {history.map((change) => (
                    <div key={change.id} className="border-l-2 pl-4">
                        <div className="font-medium">{change.reason}</div>
                        <div className="text-xs text-muted-foreground">
                            {formatDate(change.effectiveDate)} ·{' '}
                            {change.changedBy || 'Sistema'}
                        </div>
                        <div className="mt-2 flex flex-wrap gap-1">
                            {Object.keys(change.changes).map((key) => (
                                <Badge key={key} variant="secondary">
                                    {fieldLabel(key)}
                                </Badge>
                            ))}
                        </div>
                    </div>
                ))}
                {!history.length ? (
                    <div className="flex gap-2 text-sm text-muted-foreground">
                        <AlertTriangle className="size-4" /> Todavía no hay
                        cambios registrados.
                    </div>
                ) : null}
            </CardContent>
        </Card>
    );
}

function Field({
    label,
    error,
    ...props
}: React.ComponentProps<typeof Input> & { label: string; error?: string }) {
    const id = props.name;

    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>{label}</Label>
            <Input id={id} {...props} />
            <InputError message={error} />
        </div>
    );
}

function Detail({ label, value }: { label: string; value: string | null }) {
    return (
        <div>
            <dt className="text-xs font-medium text-muted-foreground">
                {label}
            </dt>
            <dd className="mt-1 text-sm">{value || 'Sin definir'}</dd>
        </div>
    );
}

function formatDate(value: string | null) {
    return value
        ? new Intl.DateTimeFormat('es-ES').format(new Date(`${value}T00:00:00`))
        : null;
}

function formatBytes(value: number) {
    return value < 1024 * 1024
        ? `${Math.round(value / 1024)} KB`
        : `${(value / 1024 / 1024).toFixed(1)} MB`;
}

function employmentTypeLabel(value: string) {
    return (
        {
            permanent: 'Indefinido',
            temporary: 'Temporal',
            internship: 'Prácticas',
            contractor: 'Profesional externo',
        }[value] || value
    );
}

function scheduleLabel(value: string | null) {
    return value
        ? ({
              full_time: 'Completa',
              part_time: 'Parcial',
              shift: 'Turnos',
              flexible: 'Flexible',
              other: 'Otra',
          }[value] ?? value)
        : 'Sin definir';
}

function fieldLabel(value: string) {
    return (
        {
            employee_number: 'N.º empleado',
            job_title: 'Puesto',
            professional_category: 'Categoría',
            employment_type: 'Contrato',
            work_location: 'Centro',
            schedule_type: 'Jornada',
            weekly_hours: 'Horas',
            annual_reference_salary: 'Salario de referencia',
            collective_agreement: 'Convenio',
            cost_center: 'Centro de coste',
            payroll_code: 'Código de nómina',
            start_date: 'Alta',
            end_date: 'Baja',
        }[value] ?? value
    );
}

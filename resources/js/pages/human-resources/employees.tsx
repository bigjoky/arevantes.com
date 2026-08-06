import { Form, Head, Link } from '@inertiajs/react';
import { ChevronRight, UsersRound } from 'lucide-react';
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

type Department = { id: number; name: string };
type StaffOption = { id: number; name: string };
type Employment = {
    employeeNumber: string | null;
    jobTitle: string | null;
    professionalCategory: string | null;
    departmentId: number | null;
    departmentName: string | null;
    managerPersonId: number | null;
    managerName: string | null;
    employmentType: string;
    status: string;
    workLocation: string | null;
    scheduleType: string | null;
    weeklyHours: string | null;
    startDate: string | null;
    probationEndDate: string | null;
    endDate: string | null;
};
type Employee = {
    id: number;
    name: string;
    email: string | null;
    staffRole: string | null;
    employment: Employment | null;
};

const selectClass =
    'border-input bg-background h-9 w-full rounded-md border px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]';

export default function Employees({
    departments,
    staffOptions,
    employees,
}: {
    departments: Department[];
    staffOptions: StaffOption[];
    employees: Employee[];
}) {
    return (
        <>
            <Head title="Fichas laborales" />
            <div className="flex flex-1 flex-col gap-8 p-4">
                <header className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 className="text-xl font-semibold">
                            Fichas laborales
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Condiciones y organización interna de la plantilla.
                        </p>
                    </div>
                    <Button asChild>
                        <Link href="/staff/create">Añadir persona</Link>
                    </Button>
                </header>

                <div className="grid gap-4">
                    {employees.map((employee) => (
                        <Card key={employee.id}>
                            <CardHeader>
                                <div className="flex flex-wrap items-start justify-between gap-4">
                                    <div>
                                        <CardTitle>{employee.name}</CardTitle>
                                        <CardDescription>
                                            {employee.staffRole ||
                                                'Puesto sin definir'}
                                            {employee.email
                                                ? ` · ${employee.email}`
                                                : ''}
                                        </CardDescription>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <Badge
                                            variant={
                                                employee.employment
                                                    ? 'default'
                                                    : 'secondary'
                                            }
                                        >
                                            {employee.employment
                                                ? statusLabel(
                                                      employee.employment
                                                          .status,
                                                  )
                                                : 'Ficha pendiente'}
                                        </Badge>
                                        <Button
                                            size="sm"
                                            variant="ghost"
                                            asChild
                                        >
                                            <Link
                                                href={`/human-resources/employees/${employee.id}`}
                                            >
                                                Abrir expediente
                                                <ChevronRight />
                                            </Link>
                                        </Button>
                                    </div>
                                </div>
                            </CardHeader>
                            <CardContent>
                                <details>
                                    <summary className="cursor-pointer text-sm font-medium">
                                        {employee.employment
                                            ? 'Editar condiciones laborales'
                                            : 'Completar ficha laboral'}
                                    </summary>
                                    <Form
                                        action={`/human-resources/employees/${employee.id}`}
                                        method="put"
                                        className="mt-5 grid gap-5 md:grid-cols-2 xl:grid-cols-4"
                                    >
                                        {({ processing, errors }) => (
                                            <>
                                                <Field
                                                    id={`employee-number-${employee.id}`}
                                                    label="Número de empleado"
                                                    name="employee_number"
                                                    defaultValue={
                                                        employee.employment
                                                            ?.employeeNumber ??
                                                        ''
                                                    }
                                                    error={
                                                        errors.employee_number
                                                    }
                                                />
                                                <Field
                                                    id={`job-title-${employee.id}`}
                                                    label="Puesto"
                                                    name="job_title"
                                                    defaultValue={
                                                        employee.employment
                                                            ?.jobTitle ?? ''
                                                    }
                                                    error={errors.job_title}
                                                />
                                                <Field
                                                    id={`professional-category-${employee.id}`}
                                                    label="Categoría profesional"
                                                    name="professional_category"
                                                    defaultValue={
                                                        employee.employment
                                                            ?.professionalCategory ??
                                                        ''
                                                    }
                                                    error={
                                                        errors.professional_category
                                                    }
                                                />
                                                <div className="grid gap-2">
                                                    <Label
                                                        htmlFor={`department-${employee.id}`}
                                                    >
                                                        Departamento
                                                    </Label>
                                                    <select
                                                        id={`department-${employee.id}`}
                                                        name="department_id"
                                                        className={selectClass}
                                                        defaultValue={
                                                            employee.employment
                                                                ?.departmentId ??
                                                            ''
                                                        }
                                                    >
                                                        <option value="">
                                                            Sin departamento
                                                        </option>
                                                        {departments.map(
                                                            (department) => (
                                                                <option
                                                                    key={
                                                                        department.id
                                                                    }
                                                                    value={
                                                                        department.id
                                                                    }
                                                                >
                                                                    {
                                                                        department.name
                                                                    }
                                                                </option>
                                                            ),
                                                        )}
                                                    </select>
                                                    <InputError
                                                        message={
                                                            errors.department_id
                                                        }
                                                    />
                                                </div>
                                                <div className="grid gap-2">
                                                    <Label
                                                        htmlFor={`manager-${employee.id}`}
                                                    >
                                                        Responsable
                                                    </Label>
                                                    <select
                                                        id={`manager-${employee.id}`}
                                                        name="manager_person_id"
                                                        className={selectClass}
                                                        defaultValue={
                                                            employee.employment
                                                                ?.managerPersonId ??
                                                            ''
                                                        }
                                                    >
                                                        <option value="">
                                                            Sin responsable
                                                        </option>
                                                        {staffOptions
                                                            .filter(
                                                                (person) =>
                                                                    person.id !==
                                                                    employee.id,
                                                            )
                                                            .map((person) => (
                                                                <option
                                                                    key={
                                                                        person.id
                                                                    }
                                                                    value={
                                                                        person.id
                                                                    }
                                                                >
                                                                    {
                                                                        person.name
                                                                    }
                                                                </option>
                                                            ))}
                                                    </select>
                                                    <InputError
                                                        message={
                                                            errors.manager_person_id
                                                        }
                                                    />
                                                </div>
                                                <div className="grid gap-2">
                                                    <Label
                                                        htmlFor={`employment-type-${employee.id}`}
                                                    >
                                                        Tipo de vinculación
                                                    </Label>
                                                    <select
                                                        id={`employment-type-${employee.id}`}
                                                        name="employment_type"
                                                        className={selectClass}
                                                        defaultValue={
                                                            employee.employment
                                                                ?.employmentType ??
                                                            'permanent'
                                                        }
                                                    >
                                                        <option value="permanent">
                                                            Indefinido
                                                        </option>
                                                        <option value="temporary">
                                                            Temporal
                                                        </option>
                                                        <option value="internship">
                                                            Prácticas
                                                        </option>
                                                        <option value="contractor">
                                                            Profesional externo
                                                        </option>
                                                    </select>
                                                </div>
                                                <div className="grid gap-2">
                                                    <Label
                                                        htmlFor={`status-${employee.id}`}
                                                    >
                                                        Estado
                                                    </Label>
                                                    <select
                                                        id={`status-${employee.id}`}
                                                        name="status"
                                                        className={selectClass}
                                                        defaultValue={
                                                            employee.employment
                                                                ?.status ??
                                                            'active'
                                                        }
                                                    >
                                                        <option value="active">
                                                            Activo
                                                        </option>
                                                        <option value="on_leave">
                                                            En ausencia
                                                        </option>
                                                        <option value="ended">
                                                            Finalizado
                                                        </option>
                                                    </select>
                                                </div>
                                                <Field
                                                    id={`location-${employee.id}`}
                                                    label="Centro de trabajo"
                                                    name="work_location"
                                                    defaultValue={
                                                        employee.employment
                                                            ?.workLocation ?? ''
                                                    }
                                                    error={errors.work_location}
                                                />
                                                <div className="grid gap-2">
                                                    <Label
                                                        htmlFor={`schedule-${employee.id}`}
                                                    >
                                                        Modalidad de jornada
                                                    </Label>
                                                    <select
                                                        id={`schedule-${employee.id}`}
                                                        name="schedule_type"
                                                        className={selectClass}
                                                        defaultValue={
                                                            employee.employment
                                                                ?.scheduleType ??
                                                            ''
                                                        }
                                                    >
                                                        <option value="">
                                                            Sin definir
                                                        </option>
                                                        <option value="full_time">
                                                            Completa
                                                        </option>
                                                        <option value="part_time">
                                                            Parcial
                                                        </option>
                                                        <option value="shift">
                                                            Turnos
                                                        </option>
                                                        <option value="flexible">
                                                            Flexible
                                                        </option>
                                                        <option value="other">
                                                            Otra
                                                        </option>
                                                    </select>
                                                </div>
                                                <Field
                                                    id={`hours-${employee.id}`}
                                                    label="Horas semanales"
                                                    name="weekly_hours"
                                                    type="number"
                                                    step="0.5"
                                                    min="0"
                                                    max="168"
                                                    defaultValue={
                                                        employee.employment
                                                            ?.weeklyHours ?? ''
                                                    }
                                                    error={errors.weekly_hours}
                                                />
                                                <Field
                                                    id={`change-effective-${employee.id}`}
                                                    label="Fecha efectiva del cambio"
                                                    name="change_effective_date"
                                                    type="date"
                                                    error={
                                                        errors.change_effective_date
                                                    }
                                                />
                                                <Field
                                                    id={`change-reason-${employee.id}`}
                                                    label="Motivo del cambio"
                                                    name="change_reason"
                                                    placeholder="Alta o actualización contractual"
                                                    error={errors.change_reason}
                                                />
                                                <Field
                                                    id={`start-${employee.id}`}
                                                    label="Fecha de alta"
                                                    name="start_date"
                                                    type="date"
                                                    defaultValue={
                                                        employee.employment
                                                            ?.startDate ?? ''
                                                    }
                                                    error={errors.start_date}
                                                />
                                                <Field
                                                    id={`probation-${employee.id}`}
                                                    label="Fin del periodo de prueba"
                                                    name="probation_end_date"
                                                    type="date"
                                                    defaultValue={
                                                        employee.employment
                                                            ?.probationEndDate ??
                                                        ''
                                                    }
                                                    error={
                                                        errors.probation_end_date
                                                    }
                                                />
                                                <Field
                                                    id={`end-${employee.id}`}
                                                    label="Fecha de baja"
                                                    name="end_date"
                                                    type="date"
                                                    defaultValue={
                                                        employee.employment
                                                            ?.endDate ?? ''
                                                    }
                                                    error={errors.end_date}
                                                />
                                                <div className="flex items-end md:col-span-2 xl:col-span-2">
                                                    <Button
                                                        disabled={processing}
                                                    >
                                                        Guardar ficha
                                                    </Button>
                                                </div>
                                            </>
                                        )}
                                    </Form>
                                </details>
                            </CardContent>
                        </Card>
                    ))}
                    {!employees.length ? (
                        <Card>
                            <CardContent className="flex flex-col items-center gap-3 py-12 text-center">
                                <UsersRound className="size-8 text-muted-foreground" />
                                <div>
                                    <div className="font-medium">
                                        No hay personal registrado
                                    </div>
                                    <div className="text-sm text-muted-foreground">
                                        Crea el primer perfil para completar su
                                        ficha laboral.
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    ) : null}
                </div>
            </div>
        </>
    );
}

function Field({
    id,
    label,
    name,
    error,
    ...props
}: React.ComponentProps<typeof Input> & {
    id: string;
    label: string;
    name: string;
    error?: string;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>{label}</Label>
            <Input id={id} name={name} {...props} />
            <InputError message={error} />
        </div>
    );
}

function statusLabel(status: string) {
    return (
        {
            active: 'Activo',
            on_leave: 'En ausencia',
            ended: 'Finalizado',
        }[status] ?? status
    );
}

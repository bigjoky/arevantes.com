import { Form, Head } from '@inertiajs/react';
import {
    BriefcaseBusiness,
    Building2,
    CalendarDays,
    Check,
    ClipboardCheck,
    Clock3,
    MapPin,
    UserRound,
    X,
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
    departmentName: string | null;
    managerName: string | null;
    employmentType: string;
    status: string;
    workLocation: string | null;
    weeklyHours: string | null;
    startDate: string | null;
};
type Employee = {
    id: number;
    name: string;
    email: string | null;
    staffRole: string | null;
    employment: Employment | null;
};
type LeaveRequest = {
    id: number;
    type: string;
    startDate: string;
    endDate: string;
    requestedDays: number;
    status: string;
    notes: string | null;
    decisionNotes: string | null;
    reviewerName: string | null;
};
type EmployeeTask = {
    id: number;
    title: string;
    category: string;
    priority: string;
    dueDate: string | null;
    status: string;
    employeeActionable: boolean;
    notes: string | null;
    completedAt: string | null;
};

const selectClass =
    'border-input bg-background h-9 w-full rounded-md border px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]';

export default function MySpace({
    employee,
    requests,
    tasks,
}: {
    employee: Employee | null;
    requests: LeaveRequest[];
    tasks: EmployeeTask[];
}) {
    return (
        <>
            <Head title="Mi RR. HH." />
            <div className="flex flex-1 flex-col gap-8 p-4">
                <header>
                    <h1 className="text-xl font-semibold">Mi RR. HH.</h1>
                    <p className="text-sm text-muted-foreground">
                        Consulta tu ficha laboral y gestiona tus solicitudes de
                        ausencia.
                    </p>
                </header>

                {!employee ? (
                    <Card>
                        <CardContent className="flex flex-col items-center gap-4 py-14 text-center">
                            <UserRound className="size-9 text-muted-foreground" />
                            <div>
                                <div className="font-medium">
                                    Tu usuario no tiene una ficha de personal
                                    vinculada
                                </div>
                                <div className="mt-1 text-sm text-muted-foreground">
                                    Solicita a administración que vincule tu
                                    cuenta antes de utilizar este espacio.
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                ) : (
                    <>
                        <div className="grid gap-6 xl:grid-cols-[minmax(0,1.4fr)_minmax(20rem,1fr)]">
                            <Card>
                                <CardHeader>
                                    <div className="flex flex-wrap items-start justify-between gap-3">
                                        <div>
                                            <CardTitle>
                                                {employee.name}
                                            </CardTitle>
                                            <CardDescription>
                                                {employee.staffRole ||
                                                    'Puesto sin definir'}
                                                {employee.email
                                                    ? ` · ${employee.email}`
                                                    : ''}
                                            </CardDescription>
                                        </div>
                                        {employee.employment ? (
                                            <Badge>
                                                {employmentStatusLabel(
                                                    employee.employment.status,
                                                )}
                                            </Badge>
                                        ) : (
                                            <Badge variant="secondary">
                                                Ficha pendiente
                                            </Badge>
                                        )}
                                    </div>
                                </CardHeader>
                                <CardContent>
                                    {employee.employment ? (
                                        <dl className="grid gap-5 sm:grid-cols-2">
                                            <Detail
                                                icon={<BriefcaseBusiness />}
                                                label="Vinculación"
                                                value={employmentTypeLabel(
                                                    employee.employment
                                                        .employmentType,
                                                )}
                                            />
                                            <Detail
                                                icon={<Building2 />}
                                                label="Departamento"
                                                value={
                                                    employee.employment
                                                        .departmentName ||
                                                    'Sin asignar'
                                                }
                                            />
                                            <Detail
                                                icon={<UserRound />}
                                                label="Responsable"
                                                value={
                                                    employee.employment
                                                        .managerName ||
                                                    'Sin asignar'
                                                }
                                            />
                                            <Detail
                                                icon={<MapPin />}
                                                label="Centro de trabajo"
                                                value={
                                                    employee.employment
                                                        .workLocation ||
                                                    'Sin definir'
                                                }
                                            />
                                            <Detail
                                                icon={<Clock3 />}
                                                label="Jornada"
                                                value={
                                                    employee.employment
                                                        .weeklyHours
                                                        ? `${Number(employee.employment.weeklyHours)} horas semanales`
                                                        : 'Sin definir'
                                                }
                                            />
                                            <Detail
                                                icon={<CalendarDays />}
                                                label="Fecha de alta"
                                                value={
                                                    employee.employment
                                                        .startDate
                                                        ? formatDate(
                                                              employee
                                                                  .employment
                                                                  .startDate,
                                                          )
                                                        : 'Sin definir'
                                                }
                                            />
                                        </dl>
                                    ) : (
                                        <p className="text-sm text-muted-foreground">
                                            Administración todavía no ha
                                            completado tus condiciones
                                            laborales.
                                        </p>
                                    )}
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <CardTitle>Solicitar ausencia</CardTitle>
                                    <CardDescription>
                                        Administración revisará tu solicitud.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <Form
                                        action="/my-human-resources/leave"
                                        method="post"
                                        resetOnSuccess
                                        className="grid gap-4"
                                    >
                                        {({ processing, errors }) => (
                                            <>
                                                <div className="grid gap-2">
                                                    <Label htmlFor="my-leave-type">
                                                        Motivo
                                                    </Label>
                                                    <select
                                                        id="my-leave-type"
                                                        name="type"
                                                        className={selectClass}
                                                        defaultValue="vacation"
                                                    >
                                                        <option value="vacation">
                                                            Vacaciones
                                                        </option>
                                                        <option value="sick">
                                                            Baja médica
                                                        </option>
                                                        <option value="personal">
                                                            Permiso personal
                                                        </option>
                                                        <option value="other">
                                                            Otro
                                                        </option>
                                                    </select>
                                                </div>
                                                <div className="grid grid-cols-2 gap-3">
                                                    <div className="grid gap-2">
                                                        <Label htmlFor="my-leave-start">
                                                            Desde
                                                        </Label>
                                                        <Input
                                                            id="my-leave-start"
                                                            name="start_date"
                                                            type="date"
                                                            required
                                                        />
                                                        <InputError
                                                            message={
                                                                errors.start_date
                                                            }
                                                        />
                                                    </div>
                                                    <div className="grid gap-2">
                                                        <Label htmlFor="my-leave-end">
                                                            Hasta
                                                        </Label>
                                                        <Input
                                                            id="my-leave-end"
                                                            name="end_date"
                                                            type="date"
                                                            required
                                                        />
                                                        <InputError
                                                            message={
                                                                errors.end_date
                                                            }
                                                        />
                                                    </div>
                                                </div>
                                                <div className="grid gap-2">
                                                    <Label htmlFor="my-leave-notes">
                                                        Observaciones
                                                    </Label>
                                                    <Textarea
                                                        id="my-leave-notes"
                                                        name="notes"
                                                        rows={3}
                                                    />
                                                </div>
                                                <Button disabled={processing}>
                                                    Enviar solicitud
                                                </Button>
                                            </>
                                        )}
                                    </Form>
                                </CardContent>
                            </Card>
                        </div>

                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <ClipboardCheck className="size-4" />
                                    Mis tareas
                                </CardTitle>
                                <CardDescription>
                                    Acciones de incorporación, formación y
                                    cumplimiento.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                {tasks.length ? (
                                    <div className="divide-y">
                                        {tasks.map((task) => (
                                            <div
                                                key={task.id}
                                                className="flex flex-wrap items-start justify-between gap-4 py-4 first:pt-0 last:pb-0"
                                            >
                                                <div>
                                                    <div className="font-medium">
                                                        {task.title}
                                                    </div>
                                                    <div className="mt-1 flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                                                        <span>
                                                            {taskCategoryLabel(
                                                                task.category,
                                                            )}
                                                        </span>
                                                        {task.dueDate ? (
                                                            <span>
                                                                Fecha límite:{' '}
                                                                {formatDate(
                                                                    task.dueDate,
                                                                )}
                                                            </span>
                                                        ) : null}
                                                        <Badge variant="outline">
                                                            {taskPriorityLabel(
                                                                task.priority,
                                                            )}
                                                        </Badge>
                                                    </div>
                                                    {task.notes ? (
                                                        <p className="mt-2 text-sm">
                                                            {task.notes}
                                                        </p>
                                                    ) : null}
                                                </div>
                                                <div className="flex items-center gap-2">
                                                    <Badge
                                                        variant={taskStatusVariant(
                                                            task.status,
                                                        )}
                                                    >
                                                        {taskStatusLabel(
                                                            task.status,
                                                        )}
                                                    </Badge>
                                                    {task.employeeActionable &&
                                                    task.status !==
                                                        'completed' ? (
                                                        <Form
                                                            action={`/my-human-resources/tasks/${task.id}/complete`}
                                                            method="patch"
                                                        >
                                                            <Button size="sm">
                                                                <Check />
                                                                Completar
                                                            </Button>
                                                        </Form>
                                                    ) : null}
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                ) : (
                                    <div className="py-8 text-center text-sm text-muted-foreground">
                                        No tienes tareas asignadas.
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Mis solicitudes</CardTitle>
                                <CardDescription>
                                    Historial de ausencias y decisiones.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                {requests.length ? (
                                    <div className="divide-y">
                                        {requests.map((request) => (
                                            <div
                                                key={request.id}
                                                className="flex flex-wrap items-start justify-between gap-4 py-4 first:pt-0 last:pb-0"
                                            >
                                                <div>
                                                    <div className="font-medium">
                                                        {typeLabel(
                                                            request.type,
                                                        )}
                                                    </div>
                                                    <div className="text-sm text-muted-foreground">
                                                        {formatDate(
                                                            request.startDate,
                                                        )}
                                                        –
                                                        {formatDate(
                                                            request.endDate,
                                                        )}{' '}
                                                        ·{' '}
                                                        {request.requestedDays}{' '}
                                                        {request.requestedDays ===
                                                        1
                                                            ? 'día'
                                                            : 'días'}
                                                    </div>
                                                    {request.notes ? (
                                                        <p className="mt-2 text-sm">
                                                            {request.notes}
                                                        </p>
                                                    ) : null}
                                                    {request.decisionNotes ? (
                                                        <p className="mt-1 text-xs text-muted-foreground">
                                                            {
                                                                request.decisionNotes
                                                            }
                                                            {request.reviewerName
                                                                ? ` · ${request.reviewerName}`
                                                                : ''}
                                                        </p>
                                                    ) : null}
                                                </div>
                                                <div className="flex items-center gap-2">
                                                    <Badge
                                                        variant={statusVariant(
                                                            request.status,
                                                        )}
                                                    >
                                                        {statusLabel(
                                                            request.status,
                                                        )}
                                                    </Badge>
                                                    {request.status ===
                                                    'pending' ? (
                                                        <Form
                                                            action={`/my-human-resources/leave/${request.id}/cancel`}
                                                            method="patch"
                                                        >
                                                            <Button
                                                                size="sm"
                                                                variant="ghost"
                                                            >
                                                                <X />
                                                                Cancelar
                                                            </Button>
                                                        </Form>
                                                    ) : null}
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                ) : (
                                    <div className="py-8 text-center text-sm text-muted-foreground">
                                        Todavía no has realizado solicitudes.
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </>
                )}
            </div>
        </>
    );
}

function Detail({
    icon,
    label,
    value,
}: {
    icon: React.ReactNode;
    label: string;
    value: string;
}) {
    return (
        <div className="flex gap-3">
            <span className="mt-0.5 text-muted-foreground [&>svg]:size-4">
                {icon}
            </span>
            <div>
                <dt className="text-xs text-muted-foreground">{label}</dt>
                <dd className="text-sm font-medium">{value}</dd>
            </div>
        </div>
    );
}

function employmentTypeLabel(type: string) {
    return (
        {
            permanent: 'Indefinido',
            temporary: 'Temporal',
            internship: 'Prácticas',
            contractor: 'Profesional externo',
        }[type] ?? type
    );
}

function taskCategoryLabel(category: string) {
    return (
        {
            onboarding: 'Incorporación',
            offboarding: 'Salida',
            training: 'Formación',
            compliance: 'Cumplimiento',
            other: 'Otra',
        }[category] ?? category
    );
}

function taskPriorityLabel(priority: string) {
    return (
        {
            low: 'Prioridad baja',
            normal: 'Prioridad normal',
            high: 'Prioridad alta',
        }[priority] ?? priority
    );
}

function taskStatusLabel(status: string) {
    return (
        {
            pending: 'Pendiente',
            in_progress: 'En curso',
            completed: 'Completada',
        }[status] ?? status
    );
}

function taskStatusVariant(
    status: string,
): 'default' | 'secondary' | 'outline' {
    if (status === 'completed') {
        return 'default';
    }

    if (status === 'in_progress') {
        return 'secondary';
    }

    return 'outline';
}

function employmentStatusLabel(status: string) {
    return (
        {
            active: 'Activo',
            on_leave: 'En ausencia',
            ended: 'Finalizado',
        }[status] ?? status
    );
}

function typeLabel(type: string) {
    return (
        {
            vacation: 'Vacaciones',
            sick: 'Baja médica',
            personal: 'Permiso personal',
            other: 'Otro',
        }[type] ?? type
    );
}

function statusLabel(status: string) {
    return (
        {
            pending: 'Pendiente',
            approved: 'Aprobada',
            rejected: 'Rechazada',
            cancelled: 'Cancelada',
        }[status] ?? status
    );
}

function statusVariant(status: string): 'default' | 'secondary' | 'outline' {
    if (status === 'approved') {
        return 'default';
    }

    if (status === 'pending') {
        return 'secondary';
    }

    return 'outline';
}

function formatDate(value: string) {
    return new Intl.DateTimeFormat('es-ES', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        timeZone: 'UTC',
    }).format(new Date(value));
}

import { Form, Head } from '@inertiajs/react';
import {
    CalendarClock,
    Check,
    ClipboardCheck,
    Play,
    RotateCcw,
    Trash2,
    UserRound,
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

type StaffOption = { id: number; name: string };
type TaskSummary = { open: number; overdue: number; completed: number };
type EmployeeTask = {
    id: number;
    personName: string;
    title: string;
    category: string;
    priority: string;
    dueDate: string | null;
    status: string;
    employeeVisible: boolean;
    employeeActionable: boolean;
    notes: string | null;
    createdByName: string | null;
    completedByName: string | null;
    completedAt: string | null;
};

const selectClass =
    'border-input bg-background h-9 w-full rounded-md border px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]';

export default function Tasks({
    staff,
    summary,
    tasks,
}: {
    staff: StaffOption[];
    summary: TaskSummary;
    tasks: EmployeeTask[];
}) {
    return (
        <>
            <Head title="Tareas de RR. HH." />
            <div className="flex flex-1 flex-col gap-8 p-4">
                <header>
                    <h1 className="text-xl font-semibold">
                        Tareas y onboarding
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Seguimiento de incorporaciones, salidas, formación y
                        cumplimiento.
                    </p>
                </header>

                <section className="grid gap-4 sm:grid-cols-3">
                    <Metric label="Tareas abiertas" value={summary.open} />
                    <Metric label="Fuera de plazo" value={summary.overdue} />
                    <Metric label="Completadas" value={summary.completed} />
                </section>

                <div className="grid gap-6 xl:grid-cols-[22rem_minmax(0,1fr)]">
                    <Card>
                        <CardHeader>
                            <CardTitle>Nueva tarea</CardTitle>
                            <CardDescription>
                                Asigna una acción a una ficha de personal.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Form
                                action="/human-resources/tasks"
                                method="post"
                                resetOnSuccess
                                className="grid gap-4"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <div className="grid gap-2">
                                            <Label htmlFor="task-person">
                                                Persona
                                            </Label>
                                            <select
                                                id="task-person"
                                                name="person_id"
                                                className={selectClass}
                                                defaultValue=""
                                                required
                                            >
                                                <option value="" disabled>
                                                    Selecciona una persona
                                                </option>
                                                {staff.map((person) => (
                                                    <option
                                                        key={person.id}
                                                        value={person.id}
                                                    >
                                                        {person.name}
                                                    </option>
                                                ))}
                                            </select>
                                            <InputError
                                                message={errors.person_id}
                                            />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="task-title">
                                                Tarea
                                            </Label>
                                            <Input
                                                id="task-title"
                                                name="title"
                                                required
                                                placeholder="Entregar documentación de bienvenida"
                                            />
                                            <InputError
                                                message={errors.title}
                                            />
                                        </div>
                                        <div className="grid grid-cols-2 gap-3">
                                            <div className="grid gap-2">
                                                <Label htmlFor="task-category">
                                                    Categoría
                                                </Label>
                                                <select
                                                    id="task-category"
                                                    name="category"
                                                    className={selectClass}
                                                    defaultValue="onboarding"
                                                >
                                                    <option value="onboarding">
                                                        Incorporación
                                                    </option>
                                                    <option value="offboarding">
                                                        Salida
                                                    </option>
                                                    <option value="training">
                                                        Formación
                                                    </option>
                                                    <option value="compliance">
                                                        Cumplimiento
                                                    </option>
                                                    <option value="other">
                                                        Otra
                                                    </option>
                                                </select>
                                            </div>
                                            <div className="grid gap-2">
                                                <Label htmlFor="task-priority">
                                                    Prioridad
                                                </Label>
                                                <select
                                                    id="task-priority"
                                                    name="priority"
                                                    className={selectClass}
                                                    defaultValue="normal"
                                                >
                                                    <option value="low">
                                                        Baja
                                                    </option>
                                                    <option value="normal">
                                                        Normal
                                                    </option>
                                                    <option value="high">
                                                        Alta
                                                    </option>
                                                </select>
                                            </div>
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="task-due-date">
                                                Fecha límite
                                            </Label>
                                            <Input
                                                id="task-due-date"
                                                name="due_date"
                                                type="date"
                                            />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="task-notes">
                                                Indicaciones
                                            </Label>
                                            <Textarea
                                                id="task-notes"
                                                name="notes"
                                                rows={4}
                                            />
                                        </div>
                                        <label className="flex items-start gap-2 text-sm">
                                            <input
                                                type="hidden"
                                                name="employee_visible"
                                                value="0"
                                            />
                                            <input
                                                type="checkbox"
                                                name="employee_visible"
                                                value="1"
                                                defaultChecked
                                                className="mt-0.5 size-4"
                                            />
                                            Visible para el empleado
                                        </label>
                                        <label className="flex items-start gap-2 text-sm">
                                            <input
                                                type="hidden"
                                                name="employee_actionable"
                                                value="0"
                                            />
                                            <input
                                                type="checkbox"
                                                name="employee_actionable"
                                                value="1"
                                                className="mt-0.5 size-4"
                                            />
                                            El empleado puede marcarla como
                                            completada
                                        </label>
                                        <Button
                                            disabled={
                                                processing || !staff.length
                                            }
                                        >
                                            Crear tarea
                                        </Button>
                                    </>
                                )}
                            </Form>
                        </CardContent>
                    </Card>

                    <div className="grid gap-4">
                        {tasks.map((task) => (
                            <Card key={task.id}>
                                <CardHeader>
                                    <div className="flex flex-wrap items-start justify-between gap-4">
                                        <div>
                                            <CardTitle>{task.title}</CardTitle>
                                            <CardDescription className="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1">
                                                <span className="flex items-center gap-1">
                                                    <UserRound className="size-3" />
                                                    {task.personName}
                                                </span>
                                                <span>
                                                    {categoryLabel(
                                                        task.category,
                                                    )}
                                                </span>
                                                {task.dueDate ? (
                                                    <span className="flex items-center gap-1">
                                                        <CalendarClock className="size-3" />
                                                        {formatDate(
                                                            task.dueDate,
                                                        )}
                                                    </span>
                                                ) : null}
                                            </CardDescription>
                                        </div>
                                        <div className="flex gap-2">
                                            <Badge
                                                variant={
                                                    task.priority === 'high'
                                                        ? 'default'
                                                        : 'outline'
                                                }
                                            >
                                                {priorityLabel(task.priority)}
                                            </Badge>
                                            <Badge
                                                variant={statusVariant(
                                                    task.status,
                                                )}
                                            >
                                                {statusLabel(task.status)}
                                            </Badge>
                                        </div>
                                    </div>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    {task.notes ? (
                                        <p className="text-sm">{task.notes}</p>
                                    ) : null}
                                    <div className="flex flex-wrap items-center gap-2 border-t pt-4">
                                        {task.status === 'pending' ? (
                                            <StatusButton
                                                taskId={task.id}
                                                status="in_progress"
                                                label="Iniciar"
                                                icon={<Play />}
                                                variant="outline"
                                            />
                                        ) : null}
                                        {task.status !== 'completed' ? (
                                            <StatusButton
                                                taskId={task.id}
                                                status="completed"
                                                label="Completar"
                                                icon={<Check />}
                                            />
                                        ) : (
                                            <StatusButton
                                                taskId={task.id}
                                                status="pending"
                                                label="Reabrir"
                                                icon={<RotateCcw />}
                                                variant="outline"
                                            />
                                        )}
                                        {task.employeeActionable ? (
                                            <Badge variant="secondary">
                                                Autogestionable
                                            </Badge>
                                        ) : null}
                                        {!task.employeeVisible ? (
                                            <Badge variant="outline">
                                                Solo RR. HH.
                                            </Badge>
                                        ) : null}
                                        <Form
                                            action={`/human-resources/tasks/${task.id}`}
                                            method="delete"
                                            className="ml-auto"
                                        >
                                            <Button
                                                size="sm"
                                                variant="ghost"
                                            >
                                                <Trash2 />
                                                Eliminar
                                            </Button>
                                        </Form>
                                    </div>
                                    {task.completedByName ? (
                                        <p className="text-xs text-muted-foreground">
                                            Completada por{' '}
                                            {task.completedByName}
                                        </p>
                                    ) : null}
                                </CardContent>
                            </Card>
                        ))}
                        {!tasks.length ? (
                            <Card>
                                <CardContent className="flex flex-col items-center gap-3 py-12 text-center">
                                    <ClipboardCheck className="size-8 text-muted-foreground" />
                                    <p className="text-sm text-muted-foreground">
                                        Todavía no hay tareas de RR. HH.
                                    </p>
                                </CardContent>
                            </Card>
                        ) : null}
                    </div>
                </div>
            </div>
        </>
    );
}

function Metric({ label, value }: { label: string; value: number }) {
    return (
        <Card>
            <CardHeader>
                <CardDescription>{label}</CardDescription>
            </CardHeader>
            <CardContent className="text-3xl font-semibold">{value}</CardContent>
        </Card>
    );
}

function StatusButton({
    taskId,
    status,
    label,
    icon,
    variant = 'default',
}: {
    taskId: number;
    status: 'pending' | 'in_progress' | 'completed';
    label: string;
    icon: React.ReactNode;
    variant?: 'default' | 'outline';
}) {
    return (
        <Form action={`/human-resources/tasks/${taskId}`} method="put">
            <input type="hidden" name="status" value={status} />
            <Button size="sm" variant={variant}>
                {icon}
                {label}
            </Button>
        </Form>
    );
}

function categoryLabel(category: string) {
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

function priorityLabel(priority: string) {
    return (
        {
            low: 'Baja',
            normal: 'Normal',
            high: 'Alta',
        }[priority] ?? priority
    );
}

function statusLabel(status: string) {
    return (
        {
            pending: 'Pendiente',
            in_progress: 'En curso',
            completed: 'Completada',
        }[status] ?? status
    );
}

function statusVariant(status: string): 'default' | 'secondary' | 'outline' {
    if (status === 'completed') {
        return 'default';
    }

    if (status === 'in_progress') {
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

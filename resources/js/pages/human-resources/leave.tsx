import { Form, Head } from '@inertiajs/react';
import { CalendarDays, Check, Trash2, X } from 'lucide-react';
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
type LeaveRequest = {
    id: number;
    personName: string;
    type: string;
    startDate: string;
    endDate: string;
    requestedDays: number;
    status: string;
    notes: string | null;
    decisionNotes: string | null;
    reviewerName: string | null;
    reviewedAt: string | null;
};

const selectClass =
    'border-input bg-background h-9 w-full rounded-md border px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]';

export default function Leave({
    staff,
    requests,
}: {
    staff: StaffOption[];
    requests: LeaveRequest[];
}) {
    return (
        <>
            <Head title="Ausencias" />
            <div className="flex flex-1 flex-col gap-8 p-4">
                <header>
                    <h1 className="text-xl font-semibold">Ausencias</h1>
                    <p className="text-sm text-muted-foreground">
                        Registra vacaciones, bajas y permisos, y conserva su
                        estado de aprobación.
                    </p>
                </header>

                <div className="grid gap-6 xl:grid-cols-[22rem_minmax(0,1fr)]">
                    <Card>
                        <CardHeader>
                            <CardTitle>Nueva solicitud</CardTitle>
                            <CardDescription>
                                El número de días se calcula de forma inclusiva.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Form
                                action="/human-resources/leave"
                                method="post"
                                resetOnSuccess
                                className="grid gap-4"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <div className="grid gap-2">
                                            <Label htmlFor="leave-person">
                                                Persona
                                            </Label>
                                            <select
                                                id="leave-person"
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
                                            <Label htmlFor="leave-type">
                                                Motivo
                                            </Label>
                                            <select
                                                id="leave-type"
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
                                                <Label htmlFor="leave-start">
                                                    Desde
                                                </Label>
                                                <Input
                                                    id="leave-start"
                                                    name="start_date"
                                                    type="date"
                                                    required
                                                />
                                                <InputError
                                                    message={errors.start_date}
                                                />
                                            </div>
                                            <div className="grid gap-2">
                                                <Label htmlFor="leave-end">
                                                    Hasta
                                                </Label>
                                                <Input
                                                    id="leave-end"
                                                    name="end_date"
                                                    type="date"
                                                    required
                                                />
                                                <InputError
                                                    message={errors.end_date}
                                                />
                                            </div>
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="leave-notes">
                                                Observaciones
                                            </Label>
                                            <Textarea
                                                id="leave-notes"
                                                name="notes"
                                                rows={4}
                                            />
                                        </div>
                                        <Button
                                            disabled={
                                                processing || !staff.length
                                            }
                                        >
                                            Crear solicitud
                                        </Button>
                                    </>
                                )}
                            </Form>
                        </CardContent>
                    </Card>

                    <div className="grid gap-4">
                        {requests.map((request) => (
                            <Card key={request.id}>
                                <CardHeader>
                                    <div className="flex flex-wrap items-start justify-between gap-4">
                                        <div>
                                            <CardTitle className="flex items-center gap-2">
                                                <CalendarDays className="size-4" />
                                                {request.personName}
                                            </CardTitle>
                                            <CardDescription>
                                                {typeLabel(request.type)} ·{' '}
                                                {formatDate(request.startDate)}–
                                                {formatDate(request.endDate)} ·{' '}
                                                {request.requestedDays}{' '}
                                                {request.requestedDays === 1
                                                    ? 'día'
                                                    : 'días'}
                                            </CardDescription>
                                        </div>
                                        <Badge
                                            variant={statusVariant(
                                                request.status,
                                            )}
                                        >
                                            {statusLabel(request.status)}
                                        </Badge>
                                    </div>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    {request.notes ? (
                                        <p className="text-sm">
                                            {request.notes}
                                        </p>
                                    ) : null}
                                    {request.reviewerName ? (
                                        <p className="text-xs text-muted-foreground">
                                            Revisado por {request.reviewerName}
                                            {request.decisionNotes
                                                ? ` · ${request.decisionNotes}`
                                                : ''}
                                        </p>
                                    ) : null}
                                    {request.status === 'pending' ? (
                                        <div className="flex flex-wrap gap-2 border-t pt-4">
                                            <DecisionForm
                                                requestId={request.id}
                                                status="approved"
                                                label="Aprobar"
                                                icon={<Check />}
                                            />
                                            <DecisionForm
                                                requestId={request.id}
                                                status="rejected"
                                                label="Rechazar"
                                                icon={<X />}
                                                variant="outline"
                                            />
                                            <Form
                                                action={`/human-resources/leave/${request.id}`}
                                                method="delete"
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
                                    ) : null}
                                </CardContent>
                            </Card>
                        ))}
                        {!requests.length ? (
                            <Card>
                                <CardContent className="py-12 text-center text-sm text-muted-foreground">
                                    No hay solicitudes de ausencia.
                                </CardContent>
                            </Card>
                        ) : null}
                    </div>
                </div>
            </div>
        </>
    );
}

function DecisionForm({
    requestId,
    status,
    label,
    icon,
    variant = 'default',
}: {
    requestId: number;
    status: 'approved' | 'rejected';
    label: string;
    icon: React.ReactNode;
    variant?: 'default' | 'outline';
}) {
    return (
        <Form
            action={`/human-resources/leave/${requestId}`}
            method="put"
        >
            <input type="hidden" name="status" value={status} />
            <Button size="sm" variant={variant}>
                {icon}
                {label}
            </Button>
        </Form>
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

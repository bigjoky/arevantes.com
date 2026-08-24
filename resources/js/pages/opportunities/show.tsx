import { Form, Head, Link } from '@inertiajs/react';
import {
    ArrowLeft,
    CalendarClock,
    CheckCircle2,
    CircleDollarSign,
    Clock3,
    FileText,
    History,
    MessageSquarePlus,
    Pencil,
    Target,
    UserRound,
} from 'lucide-react';
import { useState } from 'react';
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
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

type User = { id: number; name: string };
type TimelineItem = {
    id: string;
    type: string;
    title: string;
    description: string | null;
    note: string | null;
    occurred_at: string;
    actor: User | null;
    href: string | null;
    changes: Record<string, { from: unknown; to: unknown }> | null;
};
type Opportunity = {
    id: number;
    code: string;
    title: string;
    stage: string;
    probability: number;
    commercial_score: number;
    score_breakdown: Record<
        string,
        { met: boolean; points: number; weight: number }
    >;
    estimated_value: string;
    expected_close_on: string | null;
    commercial_kind: string;
    source: string | null;
    source_detail: string | null;
    notes: string | null;
    created_at: string;
    updated_at: string;
    customer: {
        id: number;
        first_name: string;
        last_name: string | null;
        company_name: string | null;
        phone: string | null;
        email: string | null;
    } | null;
    installation: {
        id: number;
        project_reference: string | null;
        site_name: string | null;
        service_type: string;
        status: string;
    } | null;
    owner: User | null;
    creator: User | null;
    updater: User | null;
    activities: Array<{
        id: number;
        title: string;
        type: string;
        status: string;
        priority: string;
        due_at: string | null;
        assigned_user: User | null;
    }>;
    quote: {
        id: number;
        number: string;
        version: number;
        status: string;
        amount: string;
        approval_status: string;
        updated_at: string;
        versions: Array<{ id: number }>;
    } | null;
    follow_up_opportunities: Array<{
        id: number;
        code: string;
        title: string;
        stage: string;
        estimated_value: string;
    }>;
};

const stageLabels: Record<string, string> = {
    new: 'Nueva',
    qualified: 'Cualificada',
    survey: 'Visita técnica',
    proposal: 'Propuesta',
    negotiation: 'Negociación',
    won: 'Ganada',
    lost: 'Perdida',
};
const fieldLabels: Record<string, string> = {
    title: 'Título',
    owner_user_id: 'Responsable',
    estimated_value: 'Valor estimado',
    expected_close_on: 'Fecha prevista',
    installation_id: 'Instalación',
    commercial_kind: 'Tipo comercial',
    source: 'Origen',
    source_detail: 'Detalle del origen',
    renewal_due_on: 'Renovación',
    recurrence_interval: 'Recurrencia',
    notes: 'Notas generales',
    status: 'Estado de la acción',
    completed_at: 'Realizada el',
    completed_by: 'Realizada por',
    completion_notes: 'Resultado',
};

export default function OpportunityShow({
    opportunity,
    timeline,
    sourceOptions,
    commercialKindOptions,
    users,
}: {
    opportunity: Opportunity;
    timeline: TimelineItem[];
    sourceOptions: Record<string, string>;
    commercialKindOptions: Record<string, string>;
    users: User[];
}) {
    const openActivities = opportunity.activities
        .filter((item) =>
            ['pending', 'in_progress', 'blocked'].includes(item.status),
        )
        .sort((left, right) =>
            (left.due_at || '9999').localeCompare(right.due_at || '9999'),
        );
    const customerName = opportunity.customer
        ? opportunity.customer.company_name ||
          `${opportunity.customer.first_name} ${opportunity.customer.last_name || ''}`.trim()
        : 'Sin cliente';

    return (
        <>
            <Head title={`${opportunity.code} · ${opportunity.title}`} />
            <div className="space-y-6 p-4 md:p-6">
                <header className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div className="space-y-3">
                        <Button
                            asChild
                            variant="ghost"
                            size="sm"
                            className="-ml-3"
                        >
                            <Link href="/opportunities">
                                <ArrowLeft /> Oportunidades
                            </Link>
                        </Button>
                        <div className="flex flex-wrap items-center gap-2">
                            <Badge variant="outline">{opportunity.code}</Badge>
                            <Badge>
                                {stageLabels[opportunity.stage] ||
                                    opportunity.stage}
                            </Badge>
                            <Badge variant="secondary">
                                {commercialKindOptions[
                                    opportunity.commercial_kind
                                ] || opportunity.commercial_kind}
                            </Badge>
                        </div>
                        <div>
                            <h1 className="text-2xl font-semibold tracking-tight md:text-3xl">
                                {opportunity.title}
                            </h1>
                            <p className="mt-1 text-muted-foreground">
                                Expediente comercial de {customerName}
                            </p>
                        </div>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button asChild variant="outline">
                            <Link
                                href={`/opportunities?opportunity=${opportunity.id}`}
                            >
                                <Pencil /> Editar datos
                            </Link>
                        </Button>
                        <Button asChild>
                            <Link
                                href={
                                    opportunity.quote
                                        ? `/quotes/${opportunity.quote.id}`
                                        : `/quotes/create?opportunity=${opportunity.id}`
                                }
                            >
                                <FileText />{' '}
                                {opportunity.quote
                                    ? 'Ver oferta'
                                    : 'Crear oferta'}
                            </Link>
                        </Button>
                    </div>
                </header>

                <section className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <Metric
                        icon={CircleDollarSign}
                        label="Valor estimado"
                        value={money(opportunity.estimated_value)}
                    />
                    <Metric
                        icon={Target}
                        label="Probabilidad"
                        value={`${opportunity.probability}%`}
                        detail={`Score ${opportunity.commercial_score}/100`}
                    />
                    <Metric
                        icon={CalendarClock}
                        label="Cierre previsto"
                        value={date(opportunity.expected_close_on)}
                    />
                    <Metric
                        icon={UserRound}
                        label="Responsable"
                        value={opportunity.owner?.name || 'Sin asignar'}
                    />
                </section>

                <div className="grid gap-6 xl:grid-cols-[minmax(0,1.6fr)_minmax(20rem,0.8fr)]">
                    <div className="space-y-6">
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <History className="size-5" /> Evolución
                                    completa
                                </CardTitle>
                                <CardDescription>
                                    Etapas, acciones, cambios de ficha y
                                    versiones de oferta ordenados
                                    cronológicamente.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div className="space-y-0">
                                    {timeline.map((item, index) => (
                                        <article
                                            key={item.id}
                                            className="relative grid grid-cols-[1.5rem_1fr] gap-3 pb-6 last:pb-0"
                                        >
                                            {index < timeline.length - 1 ? (
                                                <span className="absolute top-6 bottom-0 left-[0.7rem] w-px bg-border" />
                                            ) : null}
                                            <span className="relative mt-1 size-6 rounded-full border bg-background ring-4 ring-background" />
                                            <div className="min-w-0 rounded-lg border p-3">
                                                <div className="flex flex-wrap items-start justify-between gap-2">
                                                    <div>
                                                        {item.href ? (
                                                            <Link
                                                                href={item.href}
                                                                className="font-medium hover:underline"
                                                            >
                                                                {item.title}
                                                            </Link>
                                                        ) : (
                                                            <h3 className="font-medium">
                                                                {item.title}
                                                            </h3>
                                                        )}
                                                        {item.description ? (
                                                            <p className="text-sm text-muted-foreground">
                                                                {timelineDescription(
                                                                    item,
                                                                )}
                                                            </p>
                                                        ) : null}
                                                    </div>
                                                    <time className="text-xs text-muted-foreground">
                                                        {dateTime(
                                                            item.occurred_at,
                                                        )}
                                                    </time>
                                                </div>
                                                {item.note ? (
                                                    <p className="mt-2 rounded-md bg-muted/50 p-2 text-sm whitespace-pre-wrap">
                                                        {item.note}
                                                    </p>
                                                ) : null}
                                                {item.changes ? (
                                                    <ChangeList
                                                        changes={item.changes}
                                                    />
                                                ) : null}
                                                <p className="mt-2 text-xs text-muted-foreground">
                                                    {item.actor?.name ||
                                                        'Sistema'}
                                                </p>
                                            </div>
                                        </article>
                                    ))}
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <aside className="space-y-6">
                        <Card>
                            <CardHeader>
                                <CardTitle>Contexto comercial</CardTitle>
                            </CardHeader>
                            <CardContent className="grid gap-4 text-sm">
                                <Detail
                                    label="Cliente"
                                    value={customerName}
                                    href={
                                        opportunity.customer
                                            ? `/people/${opportunity.customer.id}`
                                            : null
                                    }
                                />
                                <Detail
                                    label="Instalación"
                                    value={
                                        opportunity.installation
                                            ?.project_reference ||
                                        opportunity.installation?.site_name ||
                                        'Sin vincular'
                                    }
                                    href={
                                        opportunity.installation
                                            ? `/installations/${opportunity.installation.id}`
                                            : null
                                    }
                                />
                                <Detail
                                    label="Origen"
                                    value={
                                        opportunity.source
                                            ? sourceOptions[
                                                  opportunity.source
                                              ] || opportunity.source
                                            : 'Sin registrar'
                                    }
                                />
                                <Detail
                                    label="Creada por"
                                    value={
                                        opportunity.creator?.name || 'Sistema'
                                    }
                                />
                                <Detail
                                    label="Última edición"
                                    value={`${opportunity.updater?.name || 'Autor no registrado'} · ${dateTime(opportunity.updated_at)}`}
                                />
                                {opportunity.notes ? (
                                    <div>
                                        <span className="text-muted-foreground">
                                            Notas iniciales
                                        </span>
                                        <p className="mt-1 whitespace-pre-wrap">
                                            {opportunity.notes}
                                        </p>
                                    </div>
                                ) : null}
                                <details className="rounded-md border p-3">
                                    <summary className="cursor-pointer font-medium">
                                        Calidad comercial ·{' '}
                                        {opportunity.commercial_score}/100
                                    </summary>
                                    <div className="mt-3 grid gap-2">
                                        {Object.entries(
                                            opportunity.score_breakdown,
                                        ).map(([criterion, result]) => (
                                            <div
                                                key={criterion}
                                                className="flex items-center justify-between gap-3 text-xs"
                                            >
                                                <span
                                                    className={
                                                        result.met
                                                            ? 'text-foreground'
                                                            : 'text-muted-foreground'
                                                    }
                                                >
                                                    {result.met ? '✓' : '○'}{' '}
                                                    {scoreLabel(criterion)}
                                                </span>
                                                <span className="font-medium tabular-nums">
                                                    {result.points}/
                                                    {result.weight}
                                                </span>
                                            </div>
                                        ))}
                                    </div>
                                </details>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Oferta</CardTitle>
                            </CardHeader>
                            <CardContent>
                                {opportunity.quote ? (
                                    <div className="space-y-2 text-sm">
                                        <Link
                                            href={`/quotes/${opportunity.quote.id}`}
                                            className="font-medium hover:underline"
                                        >
                                            {opportunity.quote.number}
                                        </Link>
                                        <div className="flex flex-wrap gap-2">
                                            <Badge variant="outline">
                                                {opportunity.quote.status}
                                            </Badge>
                                            <Badge variant="secondary">
                                                v{opportunity.quote.version}
                                            </Badge>
                                        </div>
                                        <p className="text-lg font-semibold">
                                            {money(opportunity.quote.amount)}
                                        </p>
                                        <p className="text-muted-foreground">
                                            {opportunity.quote.versions.length}{' '}
                                            versiones conservadas
                                        </p>
                                    </div>
                                ) : (
                                    <div className="space-y-3 text-sm text-muted-foreground">
                                        <p>
                                            Aún no existe una oferta vinculada.
                                        </p>
                                        <Button asChild size="sm">
                                            <Link
                                                href={`/quotes/create?opportunity=${opportunity.id}`}
                                            >
                                                Crear primera oferta
                                            </Link>
                                        </Button>
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Clock3 className="size-5" /> Próximas
                                    acciones
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                {openActivities.length ? (
                                    openActivities.map((activity) => (
                                        <div
                                            key={activity.id}
                                            className="rounded-md border p-3 text-sm"
                                        >
                                            <div className="flex items-start justify-between gap-2">
                                                <div>
                                                    <p className="font-medium">
                                                        {activity.title}
                                                    </p>
                                                    <p className="text-muted-foreground">
                                                        {activity.assigned_user
                                                            ?.name ||
                                                            'Sin asignar'}{' '}
                                                        ·{' '}
                                                        {dateTime(
                                                            activity.due_at,
                                                        )}
                                                    </p>
                                                </div>
                                                {activity.id ===
                                                openActivities[0]?.id ? (
                                                    <Badge variant="secondary">
                                                        Siguiente
                                                    </Badge>
                                                ) : null}
                                            </div>
                                            <CompleteActivityDialog
                                                opportunity={opportunity}
                                                activity={activity}
                                                users={users}
                                            />
                                        </div>
                                    ))
                                ) : (
                                    <p className="text-sm text-muted-foreground">
                                        No hay acciones pendientes.
                                    </p>
                                )}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <MessageSquarePlus className="size-5" />{' '}
                                    Añadir nota
                                </CardTitle>
                                <CardDescription>
                                    La nota quedará firmada y fechada en el
                                    histórico.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <Form
                                    action={`/opportunities/${opportunity.id}/notes`}
                                    method="post"
                                    resetOnSuccess={['note']}
                                    className="space-y-3"
                                >
                                    {({ errors, processing }) => (
                                        <>
                                            <Textarea
                                                name="note"
                                                required
                                                minLength={2}
                                                rows={4}
                                                placeholder="Conversación, objeción, decisión, compromiso o contexto relevante…"
                                            />
                                            <InputError message={errors.note} />
                                            <Button
                                                disabled={processing}
                                                className="w-full"
                                            >
                                                Guardar nota
                                            </Button>
                                        </>
                                    )}
                                </Form>
                            </CardContent>
                        </Card>
                    </aside>
                </div>
            </div>
        </>
    );
}

function CompleteActivityDialog({
    opportunity,
    activity,
    users,
}: {
    opportunity: Opportunity;
    activity: Opportunity['activities'][number];
    users: User[];
}) {
    const [open, setOpen] = useState(false);
    const [scheduleNext, setScheduleNext] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="outline" size="sm" className="mt-3 w-full">
                    <CheckCircle2 /> Registrar realizada
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>Completar acción</DialogTitle>
                    <DialogDescription>
                        Registra el resultado de “{activity.title}”. La
                        siguiente acción pendiente pasará a ser la activa
                        automáticamente.
                    </DialogDescription>
                </DialogHeader>
                <Form
                    action={`/opportunities/${opportunity.id}/activities/${activity.id}/complete`}
                    method="patch"
                    resetOnSuccess
                    onSuccess={() => {
                        setOpen(false);
                        setScheduleNext(false);
                    }}
                    className="grid gap-4"
                >
                    {({ errors, processing }) => (
                        <>
                            <input
                                type="hidden"
                                name="schedule_next"
                                value={scheduleNext ? '1' : '0'}
                            />
                            <div className="grid gap-2">
                                <Label
                                    htmlFor={`completion-notes-${activity.id}`}
                                >
                                    Resultado de la acción
                                </Label>
                                <Textarea
                                    id={`completion-notes-${activity.id}`}
                                    name="completion_notes"
                                    required
                                    minLength={2}
                                    rows={4}
                                    placeholder="Qué se trató, respuesta obtenida y compromiso alcanzado…"
                                />
                                <InputError message={errors.completion_notes} />
                            </div>
                            <label className="flex cursor-pointer items-start gap-3 rounded-md border p-3">
                                <input
                                    type="checkbox"
                                    checked={scheduleNext}
                                    onChange={(event) =>
                                        setScheduleNext(event.target.checked)
                                    }
                                    className="mt-1 size-4"
                                />
                                <span>
                                    <span className="block font-medium">
                                        Programar otra acción
                                    </span>
                                    <span className="block text-xs text-muted-foreground">
                                        Úsalo si esta conversación genera un
                                        seguimiento nuevo. Si ya hay acciones
                                        pendientes, se conservarán.
                                    </span>
                                </span>
                            </label>
                            {scheduleNext ? (
                                <div className="grid gap-4 rounded-md border bg-muted/20 p-4">
                                    <div className="grid gap-2">
                                        <Label
                                            htmlFor={`next-title-${activity.id}`}
                                        >
                                            Nueva acción
                                        </Label>
                                        <Input
                                            id={`next-title-${activity.id}`}
                                            name="next_action_title"
                                            required
                                            placeholder="Enviar propuesta revisada"
                                        />
                                        <InputError
                                            message={errors.next_action_title}
                                        />
                                    </div>
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <ActivitySelect
                                            label="Tipo"
                                            name="next_action_type"
                                            defaultValue="call"
                                            error={errors.next_action_type}
                                            options={[
                                                ['call', 'Llamada'],
                                                ['email', 'Email'],
                                                ['meeting', 'Reunión'],
                                                ['visit', 'Visita'],
                                                ['task', 'Tarea'],
                                                ['reminder', 'Recordatorio'],
                                            ]}
                                        />
                                        <ActivitySelect
                                            label="Prioridad"
                                            name="next_action_priority"
                                            defaultValue="normal"
                                            error={errors.next_action_priority}
                                            options={[
                                                ['low', 'Baja'],
                                                ['normal', 'Normal'],
                                                ['high', 'Alta'],
                                            ]}
                                        />
                                        <div className="grid gap-2">
                                            <Label>Fecha y hora</Label>
                                            <Input
                                                name="next_action_due_at"
                                                type="datetime-local"
                                                required
                                                min={nextActionDateTime(0)}
                                                defaultValue={nextActionDateTime(
                                                    1,
                                                )}
                                            />
                                            <InputError
                                                message={
                                                    errors.next_action_due_at
                                                }
                                            />
                                        </div>
                                        <ActivitySelect
                                            label="Responsable"
                                            name="next_action_assigned_user_id"
                                            defaultValue={String(
                                                opportunity.owner?.id || '',
                                            )}
                                            error={
                                                errors.next_action_assigned_user_id
                                            }
                                            options={[
                                                [
                                                    '',
                                                    'Responsable de la oportunidad',
                                                ],
                                                ...users.map((user) => [
                                                    String(user.id),
                                                    user.name,
                                                ]),
                                            ]}
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label>Notas de seguimiento</Label>
                                        <Textarea
                                            name="next_action_notes"
                                            rows={2}
                                        />
                                        <InputError
                                            message={errors.next_action_notes}
                                        />
                                    </div>
                                </div>
                            ) : null}
                            <DialogFooter>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => setOpen(false)}
                                >
                                    Cancelar
                                </Button>
                                <Button disabled={processing}>
                                    {processing
                                        ? 'Guardando…'
                                        : 'Completar acción'}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function ActivitySelect({
    label,
    name,
    defaultValue,
    options,
    error,
}: {
    label: string;
    name: string;
    defaultValue: string;
    options: string[][];
    error?: string;
}) {
    return (
        <div className="grid gap-2">
            <Label>{label}</Label>
            <select
                name={name}
                defaultValue={defaultValue}
                className="h-9 rounded-md border bg-background px-3 text-sm"
            >
                {options.map(([value, optionLabel]) => (
                    <option key={value || 'default'} value={value}>
                        {optionLabel}
                    </option>
                ))}
            </select>
            <InputError message={error} />
        </div>
    );
}

function Metric({
    icon: Icon,
    label,
    value,
    detail,
}: {
    icon: typeof Target;
    label: string;
    value: string;
    detail?: string;
}) {
    return (
        <Card>
            <CardContent className="flex items-start gap-3 p-4">
                <Icon className="mt-0.5 size-5 text-muted-foreground" />
                <div>
                    <p className="text-xs text-muted-foreground">{label}</p>
                    <p className="mt-1 font-semibold">{value}</p>
                    {detail ? (
                        <p className="text-xs text-muted-foreground">
                            {detail}
                        </p>
                    ) : null}
                </div>
            </CardContent>
        </Card>
    );
}

function Detail({
    label,
    value,
    href,
}: {
    label: string;
    value: string;
    href?: string | null;
}) {
    return (
        <div>
            <span className="text-muted-foreground">{label}</span>
            {href ? (
                <Link href={href} className="block font-medium hover:underline">
                    {value}
                </Link>
            ) : (
                <p className="font-medium">{value}</p>
            )}
        </div>
    );
}

function ChangeList({
    changes,
}: {
    changes: Record<string, { from: unknown; to: unknown }>;
}) {
    return (
        <dl className="mt-2 grid gap-1 text-xs">
            {Object.entries(changes).map(([field, change]) => (
                <div key={field} className="grid grid-cols-[8rem_1fr] gap-2">
                    <dt className="text-muted-foreground">
                        {fieldLabels[field] || field}
                    </dt>
                    <dd>
                        {displayValue(change.from)} → {displayValue(change.to)}
                    </dd>
                </div>
            ))}
        </dl>
    );
}

function timelineDescription(item: TimelineItem) {
    if (item.type === 'stage_changed' && item.description) {
        return item.description
            .split(' → ')
            .map((stage) => stageLabels[stage] || stage)
            .join(' → ');
    }

    return item.description;
}

function displayValue(value: unknown) {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    return String(value);
}

function scoreLabel(value: string) {
    const labels: Record<string, string> = {
        customer_contactable: 'Cliente contactable',
        source_identified: 'Origen identificado',
        owner_assigned: 'Responsable asignado',
        expected_close_set: 'Fecha prevista',
        next_action_scheduled: 'Próxima acción programada',
        estimated_value_set: 'Importe estimado',
        advanced_stage: 'Etapa avanzada',
    };

    return labels[value] || value;
}

function money(value: string | number) {
    return new Intl.NumberFormat('es-ES', {
        style: 'currency',
        currency: 'EUR',
    }).format(Number(value));
}

function date(value: string | null) {
    return value
        ? new Intl.DateTimeFormat('es-ES', { dateStyle: 'medium' }).format(
              new Date(value),
          )
        : 'Sin fecha';
}

function dateTime(value: string | null) {
    return value
        ? new Intl.DateTimeFormat('es-ES', {
              dateStyle: 'medium',
              timeStyle: 'short',
          }).format(new Date(value))
        : 'Sin fecha';
}

function nextActionDateTime(days: number) {
    const value = new Date();
    value.setDate(value.getDate() + days);
    value.setHours(
        days ? 9 : value.getHours(),
        days ? 0 : value.getMinutes(),
        0,
        0,
    );

    const offset = value.getTimezoneOffset();

    return new Date(value.getTime() - offset * 60_000)
        .toISOString()
        .slice(0, 16);
}

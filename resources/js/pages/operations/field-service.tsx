import { Form, Head, Link } from '@inertiajs/react';
import { ArrowLeft, Plus, Save } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useLocale } from '@/hooks/use-locale';
import { translate } from '@/i18n';
import { serviceLabels } from '@/lib/business-lines';
import type { ServiceType } from '@/lib/business-lines';

type SkillOption = { value: string; label: string };

type Profile = {
    weekly_schedule: Record<string, { start: string; end: string }[]> | null;
    daily_capacity_minutes: number;
    max_travel_km: number;
    home_postal_code: string | null;
    service_zones: string[];
    skills: string[];
    is_available: boolean;
};
type User = {
    id: number;
    name: string;
    role: string;
    field_service_profile: Profile | null;
};
type Template = {
    id: number;
    name: string;
    kind: string;
    service_type: ServiceType | null;
    title: string;
    estimated_duration_minutes: number;
    required_skills: string[];
    checklist: { label: string; is_required: boolean }[];
    required_evidence: {
        key: string;
        label: string;
        kind: string;
        min_count: number;
    }[];
    handover_documents: { key: string; label: string }[];
    is_system: boolean;
    is_active: boolean;
};

export default function FieldServiceSettings({
    users,
    templates,
    skillOptions,
}: {
    users: User[];
    templates: Template[];
    skillOptions: SkillOption[];
}) {
    return (
        <>
            <Head title="Field service settings" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <header className="flex items-center gap-3">
                    <Button asChild size="icon" variant="outline">
                        <Link href="/operations">
                            <ArrowLeft />
                        </Link>
                    </Button>
                    <div>
                        <h1 className="text-xl font-semibold">
                            Field service settings
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Technician capacity, coverage, skills and reusable
                            work templates.
                        </p>
                    </div>
                </header>
                <section>
                    <h2 className="mb-3 font-semibold">Technicians</h2>
                    <div className="grid gap-4 xl:grid-cols-2">
                        {users.map((user) => (
                            <ProfileForm
                                key={user.id}
                                user={user}
                                skillOptions={skillOptions}
                            />
                        ))}
                    </div>
                </section>
                <section className="grid gap-6 xl:grid-cols-[1fr_1.2fr]">
                    <TemplateForm skillOptions={skillOptions} />
                    <Card>
                        <CardHeader>
                            <CardTitle>
                                Plantillas técnicas de órdenes de trabajo
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-3">
                            {templates.map((template) => (
                                <div
                                    key={template.id}
                                    className="border-t pt-3 text-sm"
                                >
                                    <div className="flex items-center justify-between gap-2">
                                        <div className="font-medium">
                                            {template.name}
                                        </div>
                                        <Badge
                                            variant={
                                                template.is_active
                                                    ? 'outline'
                                                    : 'secondary'
                                            }
                                        >
                                            {template.is_active
                                                ? 'Active'
                                                : 'Inactive'}
                                        </Badge>
                                    </div>
                                    <div className="text-muted-foreground">
                                        {template.service_type
                                            ? serviceLabels[
                                                  template.service_type
                                              ]
                                            : 'All business lines'}{' '}
                                        · {template.kind} ·{' '}
                                        {template.estimated_duration_minutes}{' '}
                                        min · {template.checklist.length} checks
                                        ·{' '}
                                        {template.required_evidence?.length ??
                                            0}{' '}
                                        evidence
                                    </div>
                                    {(template.required_skills?.length ?? 0) >
                                        0 && (
                                        <div className="mt-1">
                                            Skills:{' '}
                                            {(template.required_skills || [])
                                                .map(
                                                    (key) =>
                                                        skillOptions.find(
                                                            (option) =>
                                                                option.value ===
                                                                key,
                                                        )?.label ||
                                                        key.replaceAll(
                                                            '_',
                                                            ' ',
                                                        ),
                                                )
                                                .join(', ')}
                                        </div>
                                    )}
                                </div>
                            ))}
                            {templates.length === 0 && (
                                <p className="text-sm text-muted-foreground">
                                    No templates yet.
                                </p>
                            )}
                        </CardContent>
                    </Card>
                </section>
            </div>
        </>
    );
}

function ProfileForm({
    user,
    skillOptions,
}: {
    user: User;
    skillOptions: SkillOption[];
}) {
    const profile = user.field_service_profile;
    const [skills, setSkills] = useState<string[]>(profile?.skills || []);
    const [zones, setZones] = useState(
        (profile?.service_zones || []).join(', '),
    );

    return (
        <Card>
            <CardHeader className="pb-3">
                <CardTitle className="flex items-center justify-between text-base">
                    {user.name}
                    <Badge variant="outline">{user.role}</Badge>
                </CardTitle>
            </CardHeader>
            <CardContent>
                <Form
                    action={`/operations/field-service/users/${user.id}`}
                    method="put"
                    className="grid gap-4"
                >
                    {({ errors, processing }) => (
                        <>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <Field
                                    label="Daily capacity (minutes)"
                                    name="daily_capacity_minutes"
                                    type="number"
                                    value={String(
                                        profile?.daily_capacity_minutes || 480,
                                    )}
                                />
                                <Field
                                    label="Maximum travel (km)"
                                    name="max_travel_km"
                                    type="number"
                                    value={String(
                                        profile?.max_travel_km || 150,
                                    )}
                                />
                            </div>
                            <Field
                                label="Home postal code"
                                name="home_postal_code"
                                value={profile?.home_postal_code || ''}
                            />
                            <WeeklySchedule
                                schedule={profile?.weekly_schedule ?? null}
                            />
                            <SkillChecklist
                                label="Habilidades del técnico"
                                value={skills}
                                onChange={setSkills}
                                name="skills"
                                options={skillOptions}
                            />
                            <TextList
                                label="Service zones"
                                value={zones}
                                onChange={setZones}
                                name="service_zones"
                            />
                            <input
                                type="hidden"
                                name="is_available"
                                value="0"
                            />
                            <label className="flex items-center gap-2 text-sm">
                                <input
                                    type="checkbox"
                                    name="is_available"
                                    value="1"
                                    defaultChecked={
                                        profile?.is_available ?? true
                                    }
                                />{' '}
                                Available for assignment
                            </label>
                            {Object.entries(errors)

                                .map(([key, error]) => (
                                    <InputError key={key} message={error} />
                                ))}
                            <InputError
                                message={
                                    errors.weekly_schedule ||
                                    errors.daily_capacity_minutes ||
                                    errors.skills ||
                                    errors.service_zones
                                }
                            />
                            <Button disabled={processing}>
                                <Save /> Save profile
                            </Button>
                        </>
                    )}
                </Form>
            </CardContent>
        </Card>
    );
}

function WeeklySchedule({
    schedule,
}: {
    schedule: Profile['weekly_schedule'];
}) {
    const { locale } = useLocale();
    const days = [
        'Lunes',
        'Martes',
        'Miércoles',
        'Jueves',
        'Viernes',
        'Sábado',
        'Domingo',
    ];
    const period = (day: string, dayIndex: number, slot: number) => (
        <div className="min-w-0 rounded-md border bg-muted/20 p-3" key={slot}>
            <p className="mb-2 text-sm font-medium">
                {translate('Turno', locale)} {slot + 1}
            </p>
            <div className="grid min-w-0 grid-cols-2 gap-3">
                <label className="grid min-w-0 gap-1 text-xs text-muted-foreground">
                    <span>Hora de inicio</span>
                    <Input
                        className="w-full min-w-0"
                        type="time"
                        aria-label={`${translate(day, locale)} · ${translate('Turno', locale)} ${slot + 1} · ${translate('Hora de inicio', locale)}`}
                        name={`weekly_schedule[${dayIndex + 1}][${slot}][start]`}
                        defaultValue={
                            schedule?.[String(dayIndex + 1)]?.[slot]?.start ??
                            ''
                        }
                    />
                </label>
                <label className="grid min-w-0 gap-1 text-xs text-muted-foreground">
                    <span>Hora de fin</span>
                    <Input
                        className="w-full min-w-0"
                        type="text"
                        placeholder="HH:mm"
                        pattern="([01][0-9]|2[0-3]):[0-5][0-9]|24:00"
                        aria-label={`${translate(day, locale)} · ${translate('Turno', locale)} ${slot + 1} · ${translate('Hora de fin', locale)}`}
                        name={`weekly_schedule[${dayIndex + 1}][${slot}][end]`}
                        defaultValue={
                            schedule?.[String(dayIndex + 1)]?.[slot]?.end ?? ''
                        }
                    />
                </label>
            </div>
        </div>
    );

    return (
        <fieldset className="grid min-w-0 gap-4">
            <legend className="mb-2 text-sm font-semibold">
                Horario semanal (Europe/Madrid)
            </legend>
            <p className="text-sm text-muted-foreground">
                Sin horario configurado, la disponibilidad será desconocida.
                Deja ambos campos vacíos en los días no laborables.
            </p>
            <p className="text-sm text-muted-foreground">
                Cada turno tiene una hora de inicio y otra de fin. Deja vacíos
                los turnos que no utilices.
            </p>
            {days.map((day, index) => (
                <fieldset className="min-w-0 rounded-lg border p-3" key={day}>
                    <legend className="px-1 text-sm font-semibold">
                        {day}
                    </legend>
                    <div className="grid min-w-0 grid-cols-[repeat(auto-fit,minmax(min(100%,15rem),1fr))] gap-3">
                        {[0, 1].map((slot) => period(day, index, slot))}
                    </div>
                    <details
                        className="mt-3"
                        open={
                            (schedule?.[String(index + 1)]?.length ?? 0) > 2 ||
                            undefined
                        }
                    >
                        <summary className="cursor-pointer text-sm text-muted-foreground">
                            Turnos adicionales (3 y 4)
                        </summary>
                        <div className="mt-3 grid min-w-0 grid-cols-[repeat(auto-fit,minmax(min(100%,15rem),1fr))] gap-3">
                            {[2, 3].map((slot) => period(day, index, slot))}
                        </div>
                    </details>
                </fieldset>
            ))}
        </fieldset>
    );
}

function TemplateForm({ skillOptions }: { skillOptions: SkillOption[] }) {
    const [skills, setSkills] = useState<string[]>([]);
    const [checklist, setChecklist] = useState(
        'Prepare work area\nPerform technical checks\nCapture completion evidence',
    );
    const [transitions, setTransitions] = useState(
        'planned: in_progress, blocked, cancelled\nin_progress: blocked, completed, cancelled\nblocked: planned, in_progress, cancelled\ncompleted:\ncancelled: planned',
    );
    const [evidence, setEvidence] = useState(
        'overview|General overview\nresult|Final result',
    );
    const [handover, setHandover] = useState(
        'completion_report|Completion report',
    );

    return (
        <Card>
            <CardHeader>
                <CardTitle>
                    Nueva plantilla técnica: habilidades, tareas y evidencias
                </CardTitle>
            </CardHeader>
            <CardContent>
                <Form
                    action="/operations/work-order-templates"
                    method="post"
                    className="grid gap-4"
                >
                    {({ errors, processing }) => (
                        <>
                            <Field label="Template name" name="name" />
                            <div className="grid gap-2">
                                <Label>Business line</Label>
                                <Select
                                    name="service_type"
                                    defaultValue="solar"
                                >
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {Object.entries(serviceLabels).map(
                                            ([value, label]) => (
                                                <SelectItem
                                                    key={value}
                                                    value={value}
                                                >
                                                    {label}
                                                </SelectItem>
                                            ),
                                        )}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label>Work type</Label>
                                    <Select
                                        name="kind"
                                        defaultValue="installation"
                                    >
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {[
                                                'survey',
                                                'installation',
                                                'commissioning',
                                                'maintenance',
                                                'repair',
                                            ].map((kind) => (
                                                <SelectItem
                                                    key={kind}
                                                    value={kind}
                                                >
                                                    {kind}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                                <Field
                                    label="Estimated duration (minutes)"
                                    name="estimated_duration_minutes"
                                    type="number"
                                    value="120"
                                />
                            </div>
                            <Field label="Default title" name="title" />
                            <SkillChecklist
                                label="Habilidades necesarias"
                                value={skills}
                                onChange={setSkills}
                                name="required_skills"
                                options={skillOptions}
                            />
                            <StructuredList
                                label="Required evidence (key|label)"
                                value={evidence}
                                onChange={setEvidence}
                                name="required_evidence"
                                evidence
                            />
                            <StructuredList
                                label="Handover documents (key|label)"
                                value={handover}
                                onChange={setHandover}
                                name="handover_documents"
                            />
                            <div className="grid gap-2">
                                <Label>Checklist (one item per line)</Label>
                                <textarea
                                    value={checklist}
                                    onChange={(event) =>
                                        setChecklist(event.target.value)
                                    }
                                    className="min-h-32 rounded-md border bg-transparent p-3 text-sm"
                                />
                                {lines(checklist).map((item, index) => (
                                    <span key={`${item}-${index}`}>
                                        <input
                                            type="hidden"
                                            name={`checklist[${index}][label]`}
                                            value={item}
                                        />
                                        <input
                                            type="hidden"
                                            name={`checklist[${index}][is_required]`}
                                            value="1"
                                        />
                                    </span>
                                ))}
                            </div>
                            <div className="grid gap-2">
                                <Label>Allowed transitions</Label>
                                <textarea
                                    value={transitions}
                                    onChange={(event) =>
                                        setTransitions(event.target.value)
                                    }
                                    className="min-h-32 rounded-md border bg-transparent p-3 font-mono text-xs"
                                />
                                {Object.entries(
                                    parseTransitions(transitions),
                                ).flatMap(([from, targets]) =>
                                    targets.map((target, index) => (
                                        <input
                                            key={`${from}-${target}`}
                                            type="hidden"
                                            name={`allowed_transitions[${from}][${index}]`}
                                            value={target}
                                        />
                                    )),
                                )}
                            </div>
                            <InputError
                                message={errors.name || errors.checklist}
                            />
                            <Button disabled={processing}>
                                <Plus /> Create template
                            </Button>
                        </>
                    )}
                </Form>
            </CardContent>
        </Card>
    );
}

function StructuredList({
    label,
    value,
    onChange,
    name,
    evidence = false,
}: {
    label: string;
    value: string;
    onChange: (value: string) => void;
    name: string;
    evidence?: boolean;
}) {
    const items = lines(value)
        .map((line) => line.split('|', 2).map((part) => part.trim()))
        .filter(([key, text]) => key && text);

    return (
        <div className="grid gap-2">
            <Label>{label}</Label>
            <textarea
                value={value}
                onChange={(event) => onChange(event.target.value)}
                className="min-h-24 rounded-md border bg-transparent p-3 text-sm"
            />
            {items.map(([key, text], index) => (
                <span key={`${key}-${index}`}>
                    <input
                        type="hidden"
                        name={`${name}[${index}][key]`}
                        value={key}
                    />
                    <input
                        type="hidden"
                        name={`${name}[${index}][label]`}
                        value={text}
                    />
                    {evidence && (
                        <>
                            <input
                                type="hidden"
                                name={`${name}[${index}][kind]`}
                                value="photo"
                            />
                            <input
                                type="hidden"
                                name={`${name}[${index}][min_count]`}
                                value="1"
                            />
                        </>
                    )}
                </span>
            ))}
        </div>
    );
}

function SkillChecklist({
    label,
    value,
    onChange,
    name,
    options,
}: {
    label: string;
    value: string[];
    onChange: (value: string[]) => void;
    name: string;
    options: SkillOption[];
}) {
    return (
        <fieldset className="grid gap-3">
            <legend className="mb-2 text-sm font-medium">{label}</legend>
            <p className="text-xs text-muted-foreground">
                Marca las habilidades que correspondan. No necesitas escribir
                códigos.
            </p>
            <div className="grid gap-2 sm:grid-cols-2">
                {options.map((option) => (
                    <label
                        key={option.value}
                        className="flex cursor-pointer items-start gap-2 rounded-md border p-3 text-sm"
                    >
                        <input
                            type="checkbox"
                            name={`${name}[]`}
                            value={option.value}
                            checked={value.includes(option.value)}
                            onChange={(event) =>
                                onChange(
                                    event.target.checked
                                        ? [...value, option.value]
                                        : value.filter(
                                              (key) => key !== option.value,
                                          ),
                                )
                            }
                            className="mt-0.5 size-4 shrink-0 accent-primary"
                        />
                        {option.label}
                    </label>
                ))}
            </div>
        </fieldset>
    );
}

function TextList({
    label,
    value,
    onChange,
    name,
}: {
    label: string;
    value: string;
    onChange: (value: string) => void;
    name: string;
}) {
    return (
        <div className="grid gap-2">
            <Label>{label} (comma separated)</Label>
            <Input
                value={value}
                onChange={(event) => onChange(event.target.value)}
            />
            {csv(value).map((item, index) => (
                <input
                    key={`${item}-${index}`}
                    type="hidden"
                    name={`${name}[${index}]`}
                    value={item}
                />
            ))}
        </div>
    );
}

function Field({
    label,
    name,
    type = 'text',
    value,
}: {
    label: string;
    name: string;
    type?: string;
    value?: string;
}) {
    return (
        <div className="grid gap-2">
            <Label>{label}</Label>
            <Input name={name} type={type} defaultValue={value} />
        </div>
    );
}

const csv = (value: string) =>
    value
        .split(',')
        .map((item) => item.trim())
        .filter(Boolean);
const lines = (value: string) =>
    value
        .split('\n')
        .map((item) => item.trim())
        .filter(Boolean);
const parseTransitions = (value: string) =>
    Object.fromEntries(
        lines(value).map((line) => {
            const [from, destinations = ''] = line.split(':', 2);

            return [from.trim(), csv(destinations)];
        }),
    ) as Record<string, string[]>;

FieldServiceSettings.layout = {
    breadcrumbs: [
        { title: 'Operations', href: '/operations' },
        { title: 'Field service', href: '/operations/field-service' },
    ],
};

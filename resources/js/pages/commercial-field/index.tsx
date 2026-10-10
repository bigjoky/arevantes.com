import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import {
    canConvertVisit,
    localDateTime,
    visitPayload,
} from '@/lib/commercial-field';

type Session = { id: number; started_at: string; ended_at: string | null };
type Visit = {
    id: number;
    user_id: number;
    visited_at: string;
    prospect_type: string;
    outcome: string;
    address: string | null;
    notes: string | null;
    opportunity_id: number | null;
    route_plan: { id: number; sector_label: string | null } | null;
    commercial: { name: string };
};
type Report = {
    user_id: number;
    name: string;
    visits: number;
    measured_visits: number;
    unmeasured_visits: number;
    hours: number;
    open_sessions: number;
    visits_per_hour: number | null;
    interested: number;
    converted: number;
    outcomes: Record<string, number>;
};
type Props = {
    visits: {
        data: Visit[];
        links: { url: string | null; label: string; active: boolean }[];
    };
    report: Report[];
    filters: { from: string; to: string; user_id: number | null };
    supervisor: boolean;
    canRecord: boolean;
    canConvert: boolean;
    recorders: { id: number; name: string }[];
    assignedRoutes: {
        id: number;
        route_date: string;
        sector_label: string | null;
        instructions: string | null;
        commercial: { name: string };
    }[];
    currentUserId: number;
    activeSession: Session | null;
    recentSessions: Session[];
    routes: {
        id: number;
        route_date: string;
        status: string;
        sector_label: string | null;
        instructions: string | null;
    }[];
    types: Record<string, string>;
    outcomes: Record<string, string>;
    serviceInterests: string[];
};
const selectClass =
    'min-h-11 w-full rounded-md border border-input bg-background px-3 text-base';
const serviceLabels: Record<string, string> = {
    solar_photovoltaic: 'Instalación fotovoltaica',
    battery_storage: 'Almacenamiento con baterías',
    ev_charging: 'Recarga de vehículo eléctrico',
    hvac_climate: 'Climatización',
    electrical_installation: 'Instalación eléctrica',
    security_systems: 'Sistemas de seguridad',
    maintenance: 'Mantenimiento',
    energy_efficiency: 'Eficiencia energética',
    other: 'Otro',
};
const channelLabels: Record<string, string> = {
    phone: 'Teléfono',
    whatsapp: 'WhatsApp',
    email: 'Correo',
    sms: 'SMS',
};
const dateTime = (value: string) => new Date(value).toLocaleString('es-ES');

export default function CommercialField({
    visits,
    report,
    filters,
    supervisor,
    canRecord,
    canConvert,
    recorders,
    assignedRoutes,
    currentUserId,
    activeSession,
    recentSessions,
    routes,
    types,
    outcomes,
    serviceInterests,
}: Props) {
    const [initialNow] = useState(() => new Date());
    const [initialUuid] = useState(() => crypto.randomUUID());
    const visit = useForm({
        client_uuid: initialUuid,
        prospect_type: 'residential',
        outcome: 'no_answer',
        visited_at: localDateTime(initialNow),
        commercial_route_plan_id: '',
        address: '',
        notes: '',
        latitude: '' as string | number,
        longitude: '' as string | number,
        accuracy_meters: '' as string | number,
    });
    const assignment = useForm({
        user_id: '',
        route_date: localDateTime(initialNow).slice(0, 10),
        sector_label: '',
        instructions: '',
    });
    const timer = useForm({ ended_at: '' });
    const conversion = useForm({
        first_name: '',
        company_name: '',
        phone: '',
        email: '',
        preferred_contact_channel: 'phone',
        consent_status: 'not_recorded',
        consent_notes: '',
        service_interest: serviceInterests[0],
        next_action_at: localDateTime(
            new Date(initialNow.getTime() + 86400000),
        ),
    });
    const [selected, setSelected] = useState<Visit | null>(null);
    const [visitNow, setVisitNow] = useState(true);
    const [locationMessage, setLocationMessage] = useState('');
    const [locating, setLocating] = useState(false);
    const [period, setPeriod] = useState(filters);
    function captureLocation() {
        if (!navigator.geolocation) {
            setLocationMessage(
                'Este navegador no permite ubicación. Puedes guardar sin ella.',
            );

            return;
        }

        setLocating(true);
        navigator.geolocation.getCurrentPosition(
            ({ coords }) => {
                visit.setData((data) => ({
                    ...data,
                    latitude: coords.latitude,
                    longitude: coords.longitude,
                    accuracy_meters: coords.accuracy,
                }));
                setLocationMessage(
                    `Ubicación puntual añadida (precisión ${Math.round(coords.accuracy)} m).`,
                );
                setLocating(false);
            },
            () => {
                setLocationMessage(
                    'No se pudo obtener la ubicación. Puedes guardar sin ella.',
                );
                setLocating(false);
            },
            { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 },
        );
    }

    return (
        <>
            <Head title="Actividad comercial de campo" />
            <div className="flex min-w-0 flex-1 flex-col gap-6 p-4 md:p-6">
                <header>
                    <h1 className="text-xl font-semibold">
                        Actividad comercial de campo
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Puerta fría ·{' '}
                        {supervisor ? 'Supervisión del equipo' : 'Mi actividad'}
                    </p>
                </header>
                <p className="rounded-lg border p-4 text-sm">
                    Registra solo datos necesarios para la visita. No incluyas
                    información sensible ni nombres de vecinos en notas. La
                    ubicación es opcional y puntual; no hay seguimiento
                    continuo. Las sesiones miden prospección, no sustituyen el
                    fichaje laboral. Puedes solicitar revisión o rectificación a
                    la persona responsable de AREVANTES.
                </p>
                {canRecord && (
                    <section className="grid gap-6 lg:grid-cols-2">
                        <div className="rounded-xl border p-4">
                            <h2 className="mb-3 font-semibold">
                                Tiempo de prospección
                            </h2>
                            <p className="mb-3 text-sm">
                                Pausa al descansar o cambiar de actividad.
                                Reanudar inicia una nueva sesión. Solo las
                                sesiones cerradas entran en el cálculo por hora.
                            </p>
                            {activeSession ? (
                                <form
                                    onSubmit={(event) => {
                                        event.preventDefault();
                                        timer.transform((data) => ({
                                            ended_at: data.ended_at
                                                ? new Date(
                                                      data.ended_at,
                                                  ).toISOString()
                                                : null,
                                        }));
                                        timer.patch(
                                            `/commercial-field/sessions/${activeSession.id}/stop`,
                                            {
                                                preserveScroll: true,
                                                onSuccess: () => timer.reset(),
                                            },
                                        );
                                    }}
                                    className="space-y-3"
                                >
                                    <p>
                                        En curso desde{' '}
                                        {dateTime(activeSession.started_at)}
                                    </p>
                                    <Label htmlFor="ended_at">
                                        Fin real (opcional si olvidaste parar)
                                    </Label>
                                    <Input
                                        id="ended_at"
                                        type="datetime-local"
                                        value={timer.data.ended_at}
                                        onChange={(event) =>
                                            timer.setData(
                                                'ended_at',
                                                event.target.value,
                                            )
                                        }
                                    />
                                    <Button disabled={timer.processing}>
                                        Pausar / finalizar sesión
                                    </Button>
                                </form>
                            ) : (
                                <Button
                                    disabled={timer.processing}
                                    onClick={() =>
                                        timer.post(
                                            '/commercial-field/sessions',
                                            { preserveScroll: true },
                                        )
                                    }
                                >
                                    Iniciar / reanudar prospección
                                </Button>
                            )}
                            {Object.values(timer.errors).map((error, index) => (
                                <InputError key={index} message={error} />
                            ))}
                            <details className="mt-4 text-sm">
                                <summary>Mis últimas sesiones</summary>
                                {recentSessions.map((session) => (
                                    <p className="mt-2" key={session.id}>
                                        {dateTime(session.started_at)} →{' '}
                                        {session.ended_at
                                            ? dateTime(session.ended_at)
                                            : 'Abierta'}
                                    </p>
                                ))}
                            </details>
                        </div>
                        <form
                            className="space-y-3 rounded-xl border p-4"
                            onSubmit={(event) => {
                                event.preventDefault();
                                visit.transform((data) =>
                                    visitPayload({
                                        ...data,
                                        visited_at: visitNow
                                            ? new Date().toISOString()
                                            : data.visited_at,
                                    }),
                                );
                                visit.post('/commercial-field/visits', {
                                    preserveScroll: true,
                                    onSuccess: () => {
                                        visit.reset();
                                        visit.setData((data) => ({
                                            ...data,
                                            client_uuid: crypto.randomUUID(),
                                            visited_at: localDateTime(
                                                new Date(),
                                            ),
                                        }));
                                        setLocationMessage('Visita guardada.');
                                    },
                                });
                            }}
                        >
                            <h2 className="font-semibold">
                                Registrar visita puerta fría
                            </h2>
                            {!activeSession && (
                                <p className="text-sm text-muted-foreground">
                                    Sin sesión abierta: se guardará la visita,
                                    pero no contará en visitas por hora.
                                </p>
                            )}
                            <Label htmlFor="prospect_type">
                                Tipo de prospecto
                            </Label>
                            <select
                                id="prospect_type"
                                className={selectClass}
                                value={visit.data.prospect_type}
                                onChange={(event) =>
                                    visit.setData(
                                        'prospect_type',
                                        event.target.value,
                                    )
                                }
                            >
                                {Object.entries(types).map(([value, label]) => (
                                    <option value={value} key={value}>
                                        {label}
                                    </option>
                                ))}
                            </select>
                            <Label htmlFor="outcome">Resultado</Label>
                            <select
                                id="outcome"
                                className={selectClass}
                                value={visit.data.outcome}
                                onChange={(event) =>
                                    visit.setData('outcome', event.target.value)
                                }
                            >
                                {Object.entries(outcomes).map(
                                    ([value, label]) => (
                                        <option value={value} key={value}>
                                            {label}
                                        </option>
                                    ),
                                )}
                            </select>
                            <Label className="flex items-center gap-2">
                                <input
                                    type="checkbox"
                                    checked={visitNow}
                                    onChange={(event) =>
                                        setVisitNow(event.target.checked)
                                    }
                                />
                                Usar hora actual al guardar
                            </Label>
                            <Label htmlFor="visited_at">
                                Fecha y hora real
                            </Label>
                            <Input
                                id="visited_at"
                                disabled={visitNow}
                                required
                                type="datetime-local"
                                value={visit.data.visited_at}
                                onChange={(event) =>
                                    visit.setData(
                                        'visited_at',
                                        event.target.value,
                                    )
                                }
                            />
                            <Label htmlFor="route">Ruta opcional de hoy</Label>
                            <select
                                id="route"
                                className={selectClass}
                                value={visit.data.commercial_route_plan_id}
                                onChange={(event) =>
                                    visit.setData(
                                        'commercial_route_plan_id',
                                        event.target.value,
                                    )
                                }
                            >
                                <option value="">Sin ruta</option>
                                {routes.map((route) => (
                                    <option key={route.id} value={route.id}>
                                        Ruta #{route.id} ·{' '}
                                        {route.sector_label ?? route.status}
                                    </option>
                                ))}
                            </select>
                            {routes.map(
                                (route) =>
                                    route.id ===
                                        Number(
                                            visit.data.commercial_route_plan_id,
                                        ) &&
                                    route.instructions && (
                                        <p className="text-sm" key={route.id}>
                                            {route.instructions}
                                        </p>
                                    ),
                            )}
                            <Label htmlFor="address">
                                Dirección o referencia (opcional)
                            </Label>
                            <Input
                                id="address"
                                maxLength={255}
                                value={visit.data.address}
                                onChange={(event) =>
                                    visit.setData('address', event.target.value)
                                }
                            />
                            <Label htmlFor="notes">
                                Notas necesarias (opcional)
                            </Label>
                            <Textarea
                                id="notes"
                                maxLength={3000}
                                value={visit.data.notes}
                                onChange={(event) =>
                                    visit.setData('notes', event.target.value)
                                }
                            />
                            <div className="flex flex-wrap gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    disabled={locating}
                                    onClick={captureLocation}
                                >
                                    {locating
                                        ? 'Obteniendo ubicación…'
                                        : 'Añadir ubicación puntual'}
                                </Button>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    onClick={() => {
                                        visit.setData((data) => ({
                                            ...data,
                                            latitude: '',
                                            longitude: '',
                                            accuracy_meters: '',
                                        }));
                                        setLocationMessage(
                                            'Ubicación eliminada del formulario.',
                                        );
                                    }}
                                >
                                    Quitar ubicación
                                </Button>
                            </div>
                            <p role="status" className="text-sm">
                                {locationMessage}
                            </p>
                            {Object.values(visit.errors).map((error, index) => (
                                <InputError key={index} message={error} />
                            ))}
                            <Button
                                className="min-h-11 w-full"
                                disabled={visit.processing}
                            >
                                Guardar visita
                            </Button>
                        </form>
                    </section>
                )}
                <section className="space-y-4">
                    <h2 className="font-semibold">
                        {supervisor ? 'Panel de supervisor' : 'Mis indicadores'}
                    </h2>
                    <form
                        className="flex flex-wrap items-end gap-3"
                        onSubmit={(event) => {
                            event.preventDefault();
                            router.get('/commercial-field', period, {
                                preserveState: true,
                            });
                        }}
                    >
                        <div>
                            <Label htmlFor="from">Desde</Label>
                            <Input
                                id="from"
                                type="date"
                                required
                                value={period.from}
                                onChange={(event) =>
                                    setPeriod({
                                        ...period,
                                        from: event.target.value,
                                    })
                                }
                            />
                        </div>
                        <div>
                            <Label htmlFor="to">Hasta</Label>
                            <Input
                                id="to"
                                type="date"
                                required
                                value={period.to}
                                onChange={(event) =>
                                    setPeriod({
                                        ...period,
                                        to: event.target.value,
                                    })
                                }
                            />
                        </div>
                        {supervisor && (
                            <div>
                                <Label htmlFor="user_id">Comercial</Label>
                                <select
                                    id="user_id"
                                    className={selectClass}
                                    value={period.user_id ?? ''}
                                    onChange={(event) =>
                                        setPeriod({
                                            ...period,
                                            user_id: event.target.value
                                                ? Number(event.target.value)
                                                : null,
                                        })
                                    }
                                >
                                    <option value="">Todo el equipo</option>
                                    {recorders.map((user) => (
                                        <option key={user.id} value={user.id}>
                                            {user.name}
                                        </option>
                                    ))}
                                </select>
                            </div>
                        )}
                        <Button variant="outline">Aplicar período</Button>
                    </form>
                    <p className="text-sm text-muted-foreground">
                        Visitas/h = visitas dentro de sesiones cerradas ÷ horas
                        reales de esas sesiones en el período. Las sesiones
                        abiertas y visitas sin tiempo se muestran por separado.
                        No se presupone una jornada fija.
                    </p>
                    {report.length === 0 && (
                        <p>No hay actividad en este período.</p>
                    )}
                    <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                        {report.map((row) => (
                            <article
                                className="rounded-xl border p-4"
                                key={row.user_id}
                            >
                                <h3 className="font-semibold">
                                    {row.name} · #{row.user_id}
                                </h3>
                                <dl className="mt-3 grid grid-cols-2 gap-2 text-sm">
                                    <dt>Visitas</dt>
                                    <dd>{row.visits}</dd>
                                    <dt>Sesiones abiertas</dt>
                                    <dd>{row.open_sessions}</dd>
                                    <dt>Horas cerradas</dt>
                                    <dd>{row.hours}</dd>
                                    <dt>Visitas/hora</dt>
                                    <dd>
                                        {row.visits_per_hour ??
                                            'Sin tiempo medido'}
                                    </dd>
                                    <dt>Sin tiempo cerrado</dt>
                                    <dd>{row.unmeasured_visits}</dd>
                                    <dt>Interesados</dt>
                                    <dd>{row.interested}</dd>
                                    <dt>Convertidos</dt>
                                    <dd>{row.converted}</dd>
                                </dl>
                                <details className="mt-3 text-sm">
                                    <summary>Resultados</summary>
                                    {Object.entries(row.outcomes).map(
                                        ([key, count]) => (
                                            <p key={key}>
                                                {outcomes[key]}: {count}
                                            </p>
                                        ),
                                    )}
                                </details>
                            </article>
                        ))}
                    </div>
                </section>
                <section className="space-y-3">
                    <h2 className="font-semibold">Visitas registradas</h2>
                    {visits.data.map((item) => (
                        <article
                            key={item.id}
                            className="space-y-2 rounded-xl border p-4 text-sm"
                        >
                            <p className="font-medium">
                                {dateTime(item.visited_at)} ·{' '}
                                {item.commercial.name}
                            </p>
                            <p>
                                {types[item.prospect_type]} ·{' '}
                                {outcomes[item.outcome]}
                            </p>
                            {item.route_plan && (
                                <p>
                                    Ruta:{' '}
                                    {item.route_plan.sector_label ??
                                        `#${item.route_plan.id}`}
                                </p>
                            )}
                            <p>{item.address}</p>
                            <p className="whitespace-pre-wrap">{item.notes}</p>
                            {item.opportunity_id && (
                                <Link
                                    className="underline"
                                    href={`/opportunities/${item.opportunity_id}`}
                                >
                                    Ver oportunidad
                                </Link>
                            )}
                            {canConvert &&
                                canConvertVisit(item, currentUserId) && (
                                    <Button
                                        variant="outline"
                                        onClick={() => {
                                            conversion.clearErrors();
                                            setSelected(item);
                                        }}
                                    >
                                        Convertir a oportunidad
                                    </Button>
                                )}
                        </article>
                    ))}
                    <nav
                        aria-label="Páginas de visitas"
                        className="flex flex-wrap gap-2"
                    >
                        {visits.links.map(
                            (link, index) =>
                                link.url && (
                                    <Link
                                        className={`rounded border px-3 py-2 ${link.active ? 'bg-muted' : ''}`}
                                        key={index}
                                        href={link.url}
                                        preserveScroll
                                    >
                                        {link.label
                                            .replace(/&laquo;/g, '«')
                                            .replace(/&raquo;/g, '»')}
                                    </Link>
                                ),
                        )}
                    </nav>
                </section>
                {supervisor && (
                    <section className="space-y-3 rounded-xl border p-4">
                        <h2 className="font-semibold">
                            Asignar sector a una ruta
                        </h2>
                        <form
                            className="space-y-3"
                            onSubmit={(event) => {
                                event.preventDefault();
                                assignment.post('/commercial-field/routes', {
                                    preserveScroll: true,
                                    onSuccess: () => assignment.reset(),
                                });
                            }}
                        >
                            <Label htmlFor="assignee">Comercial</Label>
                            <select
                                id="assignee"
                                required
                                className={selectClass}
                                value={assignment.data.user_id}
                                onChange={(event) =>
                                    assignment.setData(
                                        'user_id',
                                        event.target.value,
                                    )
                                }
                            >
                                <option value="">
                                    Selecciona un comercial
                                </option>
                                {recorders.map((user) => (
                                    <option key={user.id} value={user.id}>
                                        {user.name}
                                    </option>
                                ))}
                            </select>
                            <Label htmlFor="route_date">Día de ruta</Label>
                            <Input
                                id="route_date"
                                required
                                type="date"
                                value={assignment.data.route_date}
                                onChange={(event) =>
                                    assignment.setData(
                                        'route_date',
                                        event.target.value,
                                    )
                                }
                            />
                            <Label htmlFor="sector_label">Sector / zona</Label>
                            <Input
                                id="sector_label"
                                required
                                maxLength={150}
                                value={assignment.data.sector_label}
                                onChange={(event) =>
                                    assignment.setData(
                                        'sector_label',
                                        event.target.value,
                                    )
                                }
                            />
                            <Label htmlFor="instructions">Instrucciones</Label>
                            <Textarea
                                id="instructions"
                                maxLength={500}
                                value={assignment.data.instructions}
                                onChange={(event) =>
                                    assignment.setData(
                                        'instructions',
                                        event.target.value,
                                    )
                                }
                            />
                            {Object.values(assignment.errors).map(
                                (error, index) => (
                                    <InputError key={index} message={error} />
                                ),
                            )}
                            <Button disabled={assignment.processing}>
                                Asignar sector
                            </Button>
                        </form>
                        <p className="text-sm text-muted-foreground">
                            Se actualiza la ruta del día o se crea una ruta de
                            prospección sin clientes ficticios. Sus paradas CRM
                            existentes se conservan.
                        </p>
                        {assignedRoutes.map((route) => (
                            <p className="text-sm" key={route.id}>
                                {route.commercial.name} ·{' '}
                                {dateTime(route.route_date).split(',')[0]} ·{' '}
                                {route.sector_label} · {route.instructions}
                            </p>
                        ))}
                    </section>
                )}
                {selected && (
                    <form
                        className="space-y-3 rounded-xl border p-4"
                        onSubmit={(event) => {
                            event.preventDefault();
                            conversion.transform((data) => ({
                                ...data,
                                next_action_at: new Date(
                                    data.next_action_at,
                                ).toISOString(),
                            }));
                            conversion.post(
                                `/commercial-field/visits/${selected.id}/convert`,
                                {
                                    preserveScroll: true,
                                    onSuccess: () => {
                                        setSelected(null);
                                        conversion.reset();
                                    },
                                },
                            );
                        }}
                    >
                        <h2 className="font-semibold">
                            Convertir visita #{selected.id}
                        </h2>
                        <p className="text-sm">
                            Introduce el contacto real y su siguiente acción. El
                            CRM registrará los datos de contacto y la decisión
                            indicada; no presupone consentimiento.
                        </p>
                        <Label htmlFor="first_name">
                            Nombre real o empresa
                        </Label>
                        <Input
                            id="first_name"
                            value={conversion.data.first_name}
                            onChange={(event) =>
                                conversion.setData(
                                    'first_name',
                                    event.target.value,
                                )
                            }
                        />
                        <Label htmlFor="company_name">Empresa</Label>
                        <Input
                            id="company_name"
                            value={conversion.data.company_name}
                            onChange={(event) =>
                                conversion.setData(
                                    'company_name',
                                    event.target.value,
                                )
                            }
                        />
                        <Label htmlFor="phone">Móvil (o correo)</Label>
                        <Input
                            id="phone"
                            type="tel"
                            value={conversion.data.phone}
                            onChange={(event) =>
                                conversion.setData('phone', event.target.value)
                            }
                        />
                        <Label htmlFor="email">Correo</Label>
                        <Input
                            id="email"
                            type="email"
                            value={conversion.data.email}
                            onChange={(event) =>
                                conversion.setData('email', event.target.value)
                            }
                        />
                        <Label htmlFor="channel">Canal preferido</Label>
                        <select
                            id="channel"
                            className={selectClass}
                            value={conversion.data.preferred_contact_channel}
                            onChange={(event) =>
                                conversion.setData(
                                    'preferred_contact_channel',
                                    event.target.value,
                                )
                            }
                        >
                            {['phone', 'whatsapp', 'email', 'sms'].map(
                                (channel) => (
                                    <option key={channel} value={channel}>
                                        {channelLabels[channel]}
                                    </option>
                                ),
                            )}
                        </select>
                        <Label htmlFor="consent">
                            Consentimiento del contacto
                        </Label>
                        <select
                            id="consent"
                            className={selectClass}
                            value={conversion.data.consent_status}
                            onChange={(event) =>
                                conversion.setData(
                                    'consent_status',
                                    event.target.value,
                                )
                            }
                        >
                            <option value="not_recorded">No registrado</option>
                            <option value="granted">Otorgado</option>
                            <option value="denied">
                                Denegado / no contactar
                            </option>
                        </select>
                        <Label htmlFor="consent_notes">
                            Evidencia de la decisión
                        </Label>
                        <Textarea
                            id="consent_notes"
                            maxLength={1000}
                            value={conversion.data.consent_notes}
                            onChange={(event) =>
                                conversion.setData(
                                    'consent_notes',
                                    event.target.value,
                                )
                            }
                        />
                        <Label htmlFor="interest">Servicio de interés</Label>
                        <select
                            id="interest"
                            className={selectClass}
                            value={conversion.data.service_interest}
                            onChange={(event) =>
                                conversion.setData(
                                    'service_interest',
                                    event.target.value,
                                )
                            }
                        >
                            {serviceInterests.map((interest) => (
                                <option key={interest} value={interest}>
                                    {serviceLabels[interest] ?? interest}
                                </option>
                            ))}
                        </select>
                        <Label htmlFor="next_action">Siguiente acción</Label>
                        <Input
                            id="next_action"
                            required
                            type="datetime-local"
                            value={conversion.data.next_action_at}
                            onChange={(event) =>
                                conversion.setData(
                                    'next_action_at',
                                    event.target.value,
                                )
                            }
                        />
                        {Object.values(conversion.errors).map(
                            (error, index) => (
                                <InputError key={index} message={error} />
                            ),
                        )}
                        <div className="flex gap-2">
                            <Button disabled={conversion.processing}>
                                Crear oportunidad
                            </Button>
                            <Button
                                type="button"
                                variant="ghost"
                                onClick={() => setSelected(null)}
                            >
                                Cancelar
                            </Button>
                        </div>
                    </form>
                )}
            </div>
        </>
    );
}

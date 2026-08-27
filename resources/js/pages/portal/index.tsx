import { Form, Head } from '@inertiajs/react';
import {
    Bell,
    CheckCircle2,
    Clock3,
    Download,
    FileText,
    Gauge,
    Headphones,
    Star,
    Wrench,
} from 'lucide-react';
import { useState } from 'react';
import BrandLogo from '@/components/brand-logo';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { serviceLabel } from '@/lib/business-lines';
type Monitoring = {
    provider: string | null;
    status: string;
    current_power_kw: string | null;
    today_energy_kwh: string | null;
    synced_at: string | null;
};
type Installation = {
    id: number;
    service_type: string;
    status: string;
    project_reference: string | null;
    site_name: string | null;
    site_city: string | null;
    completed_date: string | null;
    system_power_kw: string | null;
    monitoring: Monitoring;
};
type Quote = {
    id: number;
    number: string;
    status: string;
    issue_date: string;
    valid_until: string | null;
    amount: string;
    document_version: string;
    document_hash: string;
    accepted_at: string | null;
    can_accept: boolean;
};
type Document = {
    id: number;
    type: string;
    number: string;
    status: string;
    issue_date: string;
    due_date: string | null;
    amount: string;
    paid_amount: string;
    document_version: string;
    document_hash: string;
    accepted_at: string | null;
    can_accept: boolean;
};
type Ticket = {
    id: number;
    installation_id: number;
    number: string;
    kind: string;
    priority: string;
    status: string;
    title: string;
    description: string | null;
    resolution: string | null;
    created_at: string;
    updated_at: string;
    resolved_at: string | null;
};
type Maintenance = {
    id: number;
    installation_id: number;
    name: string;
    version: number;
    service_level: string;
    coverage_type: string;
    included_visits: number | null;
    response_minutes: number | null;
    resolution_minutes: number | null;
    contract_ends_on: string | null;
    next_due_on: string;
    last_completed_on: string | null;
    scope: string | null;
    covered_assets: {
        id: number;
        asset_tag: string;
        name: string;
        category: string;
    }[];
};
type Certificate = {
    id: number;
    name: string;
    label: string;
    caption: string | null;
    size: number;
    version: number;
    work_order: string;
    created_at: string;
    installation: Pick<
        Installation,
        | 'id'
        | 'service_type'
        | 'status'
        | 'project_reference'
        | 'site_name'
        | 'completed_date'
    >;
};
type TimelineEvent = {
    id: number;
    type: string;
    title: string;
    occurred_at: string;
};
type Preferences = {
    email_documents: boolean;
    email_tickets: boolean;
    email_maintenance: boolean;
};
type ServiceCommunication = {
    id: number;
    type: string;
    channel: string;
    subject: string;
    message: string;
    scheduled_for: string;
    confirmed_at: string | null;
    sent_at: string | null;
    plan: string | null;
};
type Tab = 'overview' | 'documents' | 'support' | 'activity' | 'preferences';
export default function Portal({
    company,
    customer,
    token,
    installations,
    quotes,
    documents,
    tickets,
    maintenance,
    serviceCommunications,
    certificates,
    timeline,
    preferences,
    surveyed_ticket_ids,
    has_sale_survey,
}: {
    company: {
        legal_name: string;
        trade_name: string | null;
        email: string | null;
        phone: string | null;
    } | null;
    customer: {
        first_name: string;
        last_name: string | null;
        company_name: string | null;
        email: string | null;
    };
    token: string;
    installations: Installation[];
    quotes: Quote[];
    documents: Document[];
    tickets: Ticket[];
    maintenance: Maintenance[];
    serviceCommunications: ServiceCommunication[];
    certificates: Certificate[];
    timeline: TimelineEvent[];
    preferences: Preferences;
    surveyed_ticket_ids: number[];
    has_sale_survey: boolean;
}) {
    const [tab, setTab] = useState<Tab>('overview');
    const name =
        customer.company_name ||
        `${customer.first_name} ${customer.last_name || ''}`.trim();

    return (
        <>
            <Head title="Customer portal" />
            <main className="min-h-screen bg-background text-foreground">
                <header className="border-b">
                    <div className="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-5">
                        <div className="flex items-center gap-3">
                            <BrandLogo className="h-14" />
                            <div className="text-sm text-muted-foreground">
                                <span className="sr-only">
                                    {company?.trade_name ||
                                        company?.legal_name ||
                                        'Arevantes'}
                                </span>
                                Portal de clientes
                            </div>
                        </div>
                        <div className="text-right text-sm">
                            <div className="font-medium">{name}</div>
                            <div className="text-muted-foreground">
                                {customer.email}
                            </div>
                        </div>
                    </div>
                </header>
                <div className="mx-auto grid max-w-6xl gap-7 px-4 py-7">
                    <div className="flex gap-1 border-b">
                        <TabButton
                            active={tab === 'overview'}
                            onClick={() => setTab('overview')}
                            icon={<Gauge />}
                        >
                            Resumen
                        </TabButton>
                        <TabButton
                            active={tab === 'documents'}
                            onClick={() => setTab('documents')}
                            icon={<FileText />}
                        >
                            Documentos
                        </TabButton>
                        <TabButton
                            active={tab === 'support'}
                            onClick={() => setTab('support')}
                            icon={<Headphones />}
                        >
                            Soporte
                        </TabButton>
                        <TabButton
                            active={tab === 'activity'}
                            onClick={() => setTab('activity')}
                            icon={<Clock3 />}
                        >
                            Actividad
                        </TabButton>
                        <TabButton
                            active={tab === 'preferences'}
                            onClick={() => setTab('preferences')}
                            icon={<Bell />}
                        >
                            Avisos
                        </TabButton>
                    </div>
                    {tab === 'overview' && (
                        <>
                            <section>
                                <h1 className="text-xl font-semibold">
                                    Tus instalaciones
                                </h1>
                                <div className="mt-4 grid gap-5 md:grid-cols-2">
                                    {installations.map((item) => (
                                        <div
                                            key={item.id}
                                            className="border-t pt-4"
                                        >
                                            <div className="flex items-start justify-between gap-3">
                                                <div>
                                                    <h2 className="font-semibold">
                                                        {item.project_reference ||
                                                            item.site_name ||
                                                            `Installation #${item.id}`}
                                                    </h2>
                                                    <p className="text-sm text-muted-foreground capitalize">
                                                        {serviceLabel(
                                                            item.service_type,
                                                        )}{' '}
                                                        · {item.site_city}
                                                    </p>
                                                </div>
                                                <Badge variant="outline">
                                                    {item.status}
                                                </Badge>
                                            </div>
                                            <div className="mt-4 grid grid-cols-3 border-y py-3 text-sm">
                                                <Info
                                                    label="System"
                                                    value={
                                                        item.system_power_kw
                                                            ? `${item.system_power_kw} kW`
                                                            : '—'
                                                    }
                                                />
                                                <Info
                                                    label="Now"
                                                    value={
                                                        item.monitoring
                                                            .current_power_kw
                                                            ? `${item.monitoring.current_power_kw} kW`
                                                            : '—'
                                                    }
                                                />
                                                <Info
                                                    label="Today"
                                                    value={
                                                        item.monitoring
                                                            .today_energy_kwh
                                                            ? `${item.monitoring.today_energy_kwh} kWh`
                                                            : '—'
                                                    }
                                                />
                                            </div>
                                            <div className="mt-3 flex items-center gap-2 text-sm">
                                                <span
                                                    className={`size-2 rounded-full ${statusColor(item.monitoring.status)}`}
                                                />
                                                <span className="capitalize">
                                                    {item.monitoring.status.replaceAll(
                                                        '_',
                                                        ' ',
                                                    )}
                                                </span>
                                                {item.monitoring.synced_at && (
                                                    <span className="text-muted-foreground">
                                                        · updated{' '}
                                                        {dateTime(
                                                            item.monitoring
                                                                .synced_at,
                                                        )}
                                                    </span>
                                                )}
                                            </div>
                                        </div>
                                    ))}
                                </div>
                                {installations.length === 0 && (
                                    <Empty text="No installations linked to this customer." />
                                )}
                            </section>
                            <section className="border-t pt-5">
                                <h2 className="font-semibold">
                                    Próximos mantenimientos
                                </h2>
                                <div className="mt-3 border-y">
                                    {maintenance.map((plan) => (
                                        <div
                                            key={plan.id}
                                            className="grid gap-2 border-b py-4 text-sm last:border-0 sm:grid-cols-[1fr_auto]"
                                        >
                                            <div>
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <span className="font-medium">
                                                        {plan.name}
                                                    </span>
                                                    <Badge variant="outline">
                                                        v{plan.version}
                                                    </Badge>
                                                    <Badge variant="secondary">
                                                        {servicePlanLabel(
                                                            plan.coverage_type,
                                                        )}
                                                    </Badge>
                                                </div>
                                                <p className="mt-1 text-muted-foreground">
                                                    Nivel{' '}
                                                    {servicePlanLabel(
                                                        plan.service_level,
                                                    )}
                                                    {plan.included_visits !==
                                                    null
                                                        ? ` · ${plan.included_visits} visitas incluidas/año`
                                                        : ''}
                                                    {plan.response_minutes
                                                        ? ` · respuesta ${duration(plan.response_minutes)}`
                                                        : ''}
                                                </p>
                                                {plan.covered_assets.length >
                                                0 ? (
                                                    <p className="mt-1 text-xs text-muted-foreground">
                                                        Equipos cubiertos:{' '}
                                                        {plan.covered_assets
                                                            .map(
                                                                (asset) =>
                                                                    `${asset.asset_tag} · ${asset.name}`,
                                                            )
                                                            .join(', ')}
                                                    </p>
                                                ) : null}
                                                {plan.scope ? (
                                                    <p className="mt-2 text-xs">
                                                        {plan.scope}
                                                    </p>
                                                ) : null}
                                            </div>
                                            <div className="text-left sm:text-right">
                                                <div className="font-medium">
                                                    Próxima visita{' '}
                                                    {date(plan.next_due_on)}
                                                </div>
                                                <div className="text-xs text-muted-foreground">
                                                    {plan.contract_ends_on
                                                        ? `Contrato hasta ${date(plan.contract_ends_on)}`
                                                        : 'Servicio sin fecha de finalización'}
                                                </div>
                                            </div>
                                        </div>
                                    ))}
                                    {maintenance.length === 0 && (
                                        <Empty text="No hay mantenimientos programados." />
                                    )}
                                </div>
                            </section>
                            {serviceCommunications.length > 0 ? (
                                <section className="border-t pt-5">
                                    <h2 className="font-semibold">
                                        Comunicaciones confirmadas
                                    </h2>
                                    <p className="mt-1 text-sm text-muted-foreground">
                                        Solo mostramos avisos revisados y fechas
                                        confirmadas por nuestro equipo.
                                    </p>
                                    <div className="mt-3 divide-y border-y">
                                        {serviceCommunications.map(
                                            (communication) => (
                                                <article
                                                    key={communication.id}
                                                    className="grid gap-2 py-4 text-sm sm:grid-cols-[1fr_auto]"
                                                >
                                                    <div>
                                                        <div className="flex flex-wrap items-center gap-2">
                                                            <span className="font-medium">
                                                                {
                                                                    communication.subject
                                                                }
                                                            </span>
                                                            <Badge variant="outline">
                                                                {
                                                                    communication.channel
                                                                }
                                                            </Badge>
                                                        </div>
                                                        <p className="mt-1 text-muted-foreground">
                                                            {
                                                                communication.message
                                                            }
                                                        </p>
                                                        {communication.plan ? (
                                                            <p className="mt-1 text-xs text-muted-foreground">
                                                                Plan:{' '}
                                                                {
                                                                    communication.plan
                                                                }
                                                            </p>
                                                        ) : null}
                                                    </div>
                                                    <div className="font-medium sm:text-right">
                                                        {dateTime(
                                                            communication.scheduled_for,
                                                        )}
                                                        <div className="text-xs font-normal text-muted-foreground">
                                                            {communication.sent_at
                                                                ? `Entregada ${dateTime(communication.sent_at)}`
                                                                : 'Confirmada'}
                                                        </div>
                                                    </div>
                                                </article>
                                            ),
                                        )}
                                    </div>
                                </section>
                            ) : null}
                            {!has_sale_survey &&
                                installations.some(
                                    (item) => item.status === 'completed',
                                ) && (
                                    <section className="border-t pt-5">
                                        <h2 className="font-semibold">
                                            ¿Cómo fue tu experiencia de compra?
                                        </h2>
                                        <p className="mt-1 text-sm text-muted-foreground">
                                            Tu opinión nos ayuda a mejorar.
                                        </p>
                                        <SaleSurveyForm
                                            token={token}
                                            installationId={
                                                installations.find(
                                                    (item) =>
                                                        item.status ===
                                                        'completed',
                                                )!.id
                                            }
                                        />
                                    </section>
                                )}
                        </>
                    )}
                    {tab === 'documents' && (
                        <section className="grid gap-7">
                            <DocumentTable
                                title="Presupuestos"
                                items={quotes.map((q) => ({
                                    id: q.id,
                                    kind: 'quote',
                                    number: q.number,
                                    status: q.status,
                                    date: q.issue_date,
                                    amount: q.amount,
                                    accepted_at: q.accepted_at,
                                    can_accept: q.can_accept,
                                    version: q.document_version,
                                    hash: q.document_hash,
                                }))}
                                token={token}
                            />
                            <DocumentTable
                                title="Pedidos, albaranes y facturas"
                                items={documents.map((d) => ({
                                    id: d.id,
                                    kind: d.type,
                                    number: d.number,
                                    status: d.status,
                                    date: d.issue_date,
                                    amount: d.amount,
                                    accepted_at: d.accepted_at,
                                    can_accept: d.can_accept,
                                    version: d.document_version,
                                    hash: d.document_hash,
                                }))}
                                token={token}
                            />
                            <CertificateList
                                certificates={certificates}
                                token={token}
                            />
                        </section>
                    )}
                    {tab === 'support' && (
                        <section className="grid gap-8 lg:grid-cols-[22rem_1fr]">
                            <Form
                                action={`/portal/${token}/tickets`}
                                method="post"
                                resetOnSuccess
                                className="grid content-start gap-4 border-t pt-4"
                            >
                                <div>
                                    <h2 className="font-semibold">
                                        Solicitar asistencia
                                    </h2>
                                    <p className="text-sm text-muted-foreground">
                                        Describe la incidencia y nuestro equipo
                                        técnico la revisará.
                                    </p>
                                </div>
                                <div className="grid gap-2">
                                    <Label>Instalación</Label>
                                    <select
                                        name="installation_id"
                                        className="h-9 rounded-md border bg-background px-3 text-sm"
                                    >
                                        {installations.map((item) => (
                                            <option
                                                key={item.id}
                                                value={item.id}
                                            >
                                                {item.project_reference ||
                                                    item.site_name ||
                                                    `#${item.id}`}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                                <div className="grid gap-2">
                                    <Label>Asunto</Label>
                                    <Input name="title" required />
                                </div>
                                <div className="grid gap-2">
                                    <Label>Prioridad</Label>
                                    <select
                                        name="priority"
                                        className="h-9 rounded-md border bg-background px-3 text-sm"
                                    >
                                        <option value="normal">Normal</option>
                                        <option value="low">Baja</option>
                                        <option value="high">Alta</option>
                                    </select>
                                </div>
                                <div className="grid gap-2">
                                    <Label>Descripción</Label>
                                    <textarea
                                        name="description"
                                        required
                                        className="min-h-28 rounded-md border bg-transparent p-3 text-sm"
                                    />
                                </div>
                                <Button disabled={installations.length === 0}>
                                    <Wrench /> Enviar solicitud
                                </Button>
                            </Form>
                            <div>
                                <h2 className="mb-3 font-semibold">
                                    Tus solicitudes
                                </h2>
                                <div className="border-y">
                                    {tickets.map((ticket) => (
                                        <div
                                            key={ticket.id}
                                            className="flex items-start justify-between gap-4 border-b py-3 text-sm last:border-0"
                                        >
                                            <div>
                                                <div className="font-medium">
                                                    {ticket.number} ·{' '}
                                                    {ticket.title}
                                                </div>
                                                <div className="text-xs text-muted-foreground">
                                                    Creada{' '}
                                                    {dateTime(
                                                        ticket.created_at,
                                                    )}{' '}
                                                    · {ticket.priority}
                                                </div>
                                            </div>
                                            <Badge variant="outline">
                                                {ticket.status}
                                            </Badge>
                                            {ticket.resolved_at &&
                                                !surveyed_ticket_ids.includes(
                                                    ticket.id,
                                                ) && (
                                                    <SurveyForm
                                                        token={token}
                                                        ticket={ticket}
                                                    />
                                                )}
                                        </div>
                                    ))}
                                    {tickets.length === 0 && (
                                        <Empty text="No hay solicitudes de soporte." />
                                    )}
                                </div>
                            </div>
                        </section>
                    )}
                    {tab === 'activity' && (
                        <section>
                            <h2 className="font-semibold">
                                Cronología de comunicaciones
                            </h2>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Accesos, descargas, aceptaciones y solicitudes
                                realizadas desde el portal.
                            </p>
                            <div className="mt-4 divide-y border-y">
                                {timeline.map((event) => (
                                    <div
                                        key={event.id}
                                        className="flex items-center justify-between gap-4 py-3 text-sm"
                                    >
                                        <div className="flex items-center gap-3">
                                            <span className="flex size-8 items-center justify-center rounded-full bg-muted">
                                                <Clock3 className="size-4" />
                                            </span>
                                            <span>{event.title}</span>
                                        </div>
                                        <time className="shrink-0 text-xs text-muted-foreground">
                                            {dateTime(event.occurred_at)}
                                        </time>
                                    </div>
                                ))}
                                {timeline.length === 0 && (
                                    <Empty text="Todavía no hay actividad registrada." />
                                )}
                            </div>
                        </section>
                    )}
                    {tab === 'preferences' && (
                        <section className="max-w-xl">
                            <h2 className="font-semibold">
                                Preferencias de notificación
                            </h2>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Elige qué comunicaciones quieres recibir por
                                correo electrónico.
                            </p>
                            <Form
                                action={`/portal/${token}/preferences`}
                                method="patch"
                                className="mt-5 grid gap-4 border-y py-5"
                            >
                                <Preference
                                    name="email_documents"
                                    label="Nuevos documentos y presupuestos"
                                    defaultChecked={preferences.email_documents}
                                />
                                <Preference
                                    name="email_tickets"
                                    label="Cambios en incidencias y solicitudes"
                                    defaultChecked={preferences.email_tickets}
                                />
                                <Preference
                                    name="email_maintenance"
                                    label="Mantenimientos e intervenciones"
                                    defaultChecked={
                                        preferences.email_maintenance
                                    }
                                />
                                <Button className="mt-2 w-fit">
                                    <Bell /> Guardar preferencias
                                </Button>
                            </Form>
                        </section>
                    )}
                </div>
            </main>
        </>
    );
}
type DocumentRow = {
    id: number;
    kind: string;
    number: string;
    status: string;
    date: string;
    amount: string;
    accepted_at: string | null;
    can_accept: boolean;
    version: string;
    hash: string;
};
function DocumentTable({
    title,
    items,
    token,
}: {
    title: string;
    items: DocumentRow[];
    token: string;
}) {
    return (
        <div>
            <h2 className="mb-3 font-semibold">{title}</h2>
            <div className="overflow-x-auto border-y">
                <table className="w-full min-w-[38rem] text-sm">
                    <thead>
                        <tr className="border-b text-left text-muted-foreground">
                            <th className="py-2">Document</th>
                            <th>Type</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th className="text-right">Total</th>
                            <th className="w-24" />
                        </tr>
                    </thead>
                    <tbody>
                        {items.map((item) => (
                            <tr
                                key={`${item.kind}-${item.id}`}
                                className="border-b last:border-0"
                            >
                                <td className="py-3 font-medium">
                                    {item.number}
                                </td>
                                <td className="capitalize">
                                    {item.kind.replaceAll('_', ' ')}
                                </td>
                                <td>{date(item.date)}</td>
                                <td>
                                    {item.accepted_at ? (
                                        <Badge variant="outline">
                                            <CheckCircle2 className="mr-1 size-3" />
                                            Aceptado
                                        </Badge>
                                    ) : (
                                        <Badge variant="outline">
                                            {item.status}
                                        </Badge>
                                    )}
                                </td>
                                <td className="text-right">
                                    {money(item.amount)}
                                </td>
                                <td className="flex justify-end gap-2 py-2">
                                    {item.can_accept && !item.accepted_at && (
                                        <Form
                                            action={`/portal/${token}/documents/${item.kind === 'quote' ? 'quote' : 'sales'}/${item.id}/accept`}
                                            method="post"
                                            onSubmit={(event) => {
                                                if (
                                                    !window.confirm(
                                                        `¿Confirmas que aceptas ${item.number}, versión ${item.version}?`,
                                                    )
                                                ) {
                                                    event.preventDefault();
                                                }
                                            }}
                                        >
                                            <input
                                                type="hidden"
                                                name="expected_version"
                                                value={item.version}
                                            />
                                            <input
                                                type="hidden"
                                                name="expected_hash"
                                                value={item.hash}
                                            />
                                            <Button size="sm">Aceptar</Button>
                                        </Form>
                                    )}
                                    <Button size="sm" variant="outline" asChild>
                                        <a
                                            href={`/portal/${token}/documents/${item.kind === 'quote' ? 'quote' : 'sales'}/${item.id}/pdf`}
                                        >
                                            <Download /> PDF
                                        </a>
                                    </Button>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
                {items.length === 0 && <Empty text="No documents available." />}
            </div>
        </div>
    );
}
function CertificateList({
    certificates,
    token,
}: {
    certificates: Certificate[];
    token: string;
}) {
    const packages = Object.values(
        certificates.reduce<Record<number, Certificate[]>>((grouped, item) => {
            (grouped[item.installation.id] ??= []).push(item);

            return grouped;
        }, {}),
    );

    return (
        <div>
            <h2 className="mb-3 font-semibold">
                Paquetes técnicos de tus instalaciones
            </h2>
            <div className="grid gap-5">
                {packages.map((items) => {
                    const installation = items[0].installation;

                    return (
                        <section
                            key={installation.id}
                            className="border-t pt-3"
                        >
                            <div className="mb-3 flex flex-wrap items-start justify-between gap-2">
                                <div>
                                    <div className="font-medium">
                                        {installation.project_reference ||
                                            installation.site_name ||
                                            `Instalación ${installation.id}`}
                                    </div>
                                    <div className="text-xs text-muted-foreground">
                                        {serviceLabel(
                                            installation.service_type,
                                        )}
                                        {installation.completed_date &&
                                            ` · Finalizada ${date(installation.completed_date)}`}
                                    </div>
                                </div>
                                <Badge variant="outline">
                                    {installation.status}
                                </Badge>
                            </div>
                            <div className="divide-y border-y">
                                {items.map((item) => (
                                    <div
                                        key={item.id}
                                        className="flex items-center justify-between gap-4 py-3 text-sm"
                                    >
                                        <div>
                                            <div className="font-medium">
                                                {item.label}
                                            </div>
                                            <div className="text-xs text-muted-foreground">
                                                {item.name} · v{item.version} ·{' '}
                                                {item.work_order} ·{' '}
                                                {fileSize(item.size)} ·{' '}
                                                {dateTime(item.created_at)}
                                            </div>
                                        </div>
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            asChild
                                        >
                                            <a
                                                href={`/portal/${token}/certificates/${item.id}`}
                                            >
                                                <Download /> Descargar
                                            </a>
                                        </Button>
                                    </div>
                                ))}
                            </div>
                        </section>
                    );
                })}
                {packages.length === 0 && (
                    <div className="border-y">
                        <Empty text="Aún no hay documentación técnica autorizada." />
                    </div>
                )}
            </div>
        </div>
    );
}
function SurveyForm({ token, ticket }: { token: string; ticket: Ticket }) {
    return (
        <Form
            action={`/portal/${token}/surveys`}
            method="post"
            className="mt-3 grid min-w-48 gap-2 border-t pt-3"
        >
            <input type="hidden" name="service_ticket_id" value={ticket.id} />
            <input type="hidden" name="context" value="intervention" />
            <Label className="text-xs">Valora la intervención</Label>
            <select
                name="score"
                defaultValue="5"
                className="h-9 rounded-md border bg-background px-3 text-sm"
            >
                {[5, 4, 3, 2, 1].map((score) => (
                    <option key={score} value={score}>
                        {score} / 5
                    </option>
                ))}
            </select>
            <Input name="comments" placeholder="Comentario opcional" />
            <Button size="sm">
                <Star /> Enviar valoración
            </Button>
        </Form>
    );
}
function SaleSurveyForm({
    token,
    installationId,
}: {
    token: string;
    installationId: number;
}) {
    return (
        <Form
            action={`/portal/${token}/surveys`}
            method="post"
            className="mt-4 grid max-w-md gap-3"
        >
            <input
                type="hidden"
                name="installation_id"
                value={installationId}
            />
            <input type="hidden" name="context" value="sale" />
            <select
                name="score"
                defaultValue="5"
                className="h-9 rounded-md border bg-background px-3 text-sm"
            >
                {[5, 4, 3, 2, 1].map((score) => (
                    <option key={score} value={score}>
                        {score} / 5
                    </option>
                ))}
            </select>
            <Input name="comments" placeholder="Comentario opcional" />
            <Button className="w-fit">
                <Star /> Enviar valoración
            </Button>
        </Form>
    );
}
function Preference({
    name,
    label,
    defaultChecked,
}: {
    name: string;
    label: string;
    defaultChecked: boolean;
}) {
    return (
        <label className="flex items-center gap-3 text-sm">
            <input type="hidden" name={name} value="0" />
            <input
                type="checkbox"
                name={name}
                value="1"
                defaultChecked={defaultChecked}
                className="size-4 rounded border"
            />
            {label}
        </label>
    );
}
function TabButton({
    active,
    onClick,
    icon,
    children,
}: {
    active: boolean;
    onClick: () => void;
    icon: React.ReactNode;
    children: React.ReactNode;
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            className={`flex items-center gap-2 border-b-2 px-3 py-2 text-sm [&_svg]:size-4 ${active ? 'border-primary font-medium' : 'border-transparent text-muted-foreground'}`}
        >
            {icon}
            {children}
        </button>
    );
}
function Info({ label, value }: { label: string; value: string }) {
    return (
        <div>
            <div className="text-xs text-muted-foreground">{label}</div>
            <div className="mt-1 font-medium">{value}</div>
        </div>
    );
}
function Empty({ text }: { text: string }) {
    return (
        <div className="py-9 text-center text-sm text-muted-foreground">
            {text}
        </div>
    );
}
const statusColor = (status: string) =>
    status === 'healthy' || status === 'producing'
        ? 'bg-green-600'
        : status === 'fault'
          ? 'bg-red-600'
          : status === 'disconnected'
            ? 'bg-amber-500'
            : 'bg-muted-foreground/40';
const money = (v: string) =>
    new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: 'EUR',
    }).format(Number(v));
const date = (v: string) =>
    new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(
        new Date(`${v.slice(0, 10)}T12:00:00`),
    );
const dateTime = (v: string) =>
    new Intl.DateTimeFormat(undefined, {
        dateStyle: 'short',
        timeStyle: 'short',
    }).format(new Date(v));
const servicePlanLabel = (value: string) => value.replaceAll('_', ' ');
const duration = (minutes: number) =>
    minutes >= 1440
        ? `${Math.round(minutes / 1440)} d`
        : minutes >= 60
          ? `${Math.round(minutes / 60)} h`
          : `${minutes} min`;
const fileSize = (bytes: number) =>
    bytes < 1024 * 1024
        ? `${Math.max(1, Math.round(bytes / 1024))} KB`
        : `${(bytes / 1024 / 1024).toFixed(1)} MB`;

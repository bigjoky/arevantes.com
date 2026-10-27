import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    CalendarDays,
    CheckCircle2,
    Pencil,
    Settings2,
} from 'lucide-react';
import { useCustomerNavigation } from '@/components/customers/use-customer-navigation';
import { PageHeader, PageShell, PaginationBar } from '@/components/page-shell';
import ProjectJourney from '@/components/project-journey';
import type { Journey } from '@/components/project-journey';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { useLocale } from '@/hooks/use-locale';
import { translate } from '@/i18n';
import { serviceLabel } from '@/lib/business-lines';
import {
    customerName,
    date,
    money,
    power,
    projectUrl,
    stateLabels,
} from './types';
import type { InstallationRow, Pagination, Permissions } from './types';
type RecordRow = {
    id: number;
    number?: string;
    title?: string;
    name?: string;
    asset_tag?: string;
    status?: string;
    scheduled_start?: string;
    scheduled_end?: string;
    assigned_user?: { name: string } | null;
    original_name?: string;
    work_order_id?: number;
    kind?: string;
    created_at?: string;
    event?: string;
    user?: { name: string } | null;
    commissioned_at?: string;
    warranty_ends_on?: string;
    serial_number?: string;
};
export type DetailProps = {
    journey?: Journey;
    installation: InstallationRow;
    quote: {
        id: number;
        number: string;
        status: string;
        amount?: string;
        accepted_at: string | null;
    } | null;
    opportunity: { id: number; code: string; title: string } | null;
    tab: string;
    mode: 'projects' | 'installations';
    records: (Pagination & { data: RecordRow[] }) | null;
    dossiers: { id: number; code: string; title: string; status: string }[];
    permissions: Permissions;
    costs:
        | (Record<string, unknown> & {
              has_actuals?: boolean;
              quality?: { score: number; issues: string[] };
          })
        | null;
    milestones: Record<string, string | null>;
};
const allTabs: [string, string][] = [
    ['summary', 'Resumen'],
    ['quote', 'Presupuesto'],
    ['installations', 'Instalación'],
    ['planning', 'Planificación'],
    ['documents', 'Documentos'],
    ['equipment', 'Equipos'],
    ['costs', 'Costes'],
    ['activity', 'Actividad'],
];
const milestoneLabels: Record<string, string> = {
    created: 'Proyecto creado',
    accepted: 'Presupuesto aceptado',
    materials: 'Material solicitado',
    scheduled: 'Inicio programado',
    completed: 'Instalación finalizada',
};
const costLabels: [string, string][] = [
    ['planned_revenue', 'Ingreso previsto'],
    ['planned_cost', 'Coste previsto'],
    ['actual_material_cost', 'Material consumido'],
    ['actual_labour_cost', 'Mano de obra registrada'],
    ['supplier_service_cost', 'Servicios de proveedores'],
    ['direct_expenses', 'Gastos directos'],
    ['actual_cost', 'Coste real'],
    ['actual_revenue', 'Ingreso facturado'],
    ['actual_margin', 'Margen real'],
    ['committed_purchase_cost', 'Compras comprometidas'],
];
const tabKeys = {
    summary: 'summary',
    quote: 'quote',
    installations: 'installations',
    costs: 'costs',
    planning: 'planning',
    equipment: 'equipment',
    documents: 'documents',
    activity: 'activity',
};
const scheduledMilestone = 'scheduled';
export default function ProjectDetail({
    installation: i,
    quote,
    opportunity,
    tab,
    mode,
    records,
    dossiers,
    permissions,
    costs,
    milestones,
    journey,
}: DetailProps) {
    const page = usePage();
    const filters = Object.fromEntries(
        new URL(page.url, 'http://localhost').searchParams.entries(),
    );
    const loading = useCustomerNavigation();
    const { locale } = useLocale();
    const isProject = mode === 'projects';
    const reference =
        i.project_reference || i.site_name || 'Instalación ' + i.id;
    const tabs = allTabs.filter(
        ([key]) =>
            (key !== 'quote' || permissions.quotes) &&
            (!['planning', 'equipment'].includes(key) ||
                permissions.planning) &&
            (key !== 'costs' || permissions.finance) &&
            (key !== 'installations' || isProject),
    );
    const info = (label: string, value: string | null | undefined) => (
        <div>
            <dt>{label}</dt>
            <dd>{value || '—'}</dd>
        </div>
    );

    return (
        <>
            <Head title={reference} />
            <PageShell className="projects-workspace projects-detail">
                <Link
                    className="projects-back"
                    href={projectUrl(filters, undefined, undefined, mode)}
                >
                    <ArrowLeft size={15} />
                    {isProject
                        ? 'Volver a proyectos'
                        : 'Volver a instalaciones'}
                </Link>
                <PageHeader
                    title={
                        <>
                            {reference}{' '}
                            <span
                                className={
                                    'projects-status projects-status-' +
                                    i.status
                                }
                            >
                                {stateLabels[i.status] || i.status}
                            </span>
                        </>
                    }
                    description={
                        i.site_name +
                        ' · ' +
                        customerName(i) +
                        ' · ' +
                        translate(serviceLabel(i.service_type), locale) +
                        (i.system_power_kw
                            ? ' · ' + power(i.system_power_kw)
                            : '')
                    }
                    breadcrumbs={[
                        { title: 'Proyectos', href: '/execution' },
                        {
                            title: isProject ? 'Proyectos' : 'Instalaciones',
                            href: projectUrl(
                                filters,
                                undefined,
                                undefined,
                                mode,
                            ),
                        },
                        {
                            title: reference,
                            href: projectUrl(filters, i.id, undefined, mode),
                        },
                    ]}
                    secondaryNavigation={null}
                    actions={
                        <>
                            {permissions.manage && (
                                <Button variant="outline" asChild>
                                    <Link
                                        href={
                                            '/installations/' + i.id + '/edit'
                                        }
                                    >
                                        <Pencil />
                                        Editar
                                    </Link>
                                </Button>
                            )}
                            <Button variant="outline" asChild>
                                <Link
                                    href={
                                        '/installations/' + i.id + '?tab=manage'
                                    }
                                >
                                    <Settings2 />
                                    Gestionar instalación
                                </Link>
                            </Button>
                        </>
                    }
                />
                <nav
                    className="projects-detail-tabs"
                    aria-label="Secciones del proyecto"
                >
                    {tabs.map(([key, label]) => (
                        <Link
                            key={key}
                            aria-current={tab === key ? 'page' : undefined}
                            href={projectUrl(filters, i.id, key, mode)}
                        >
                            {label}
                        </Link>
                    ))}
                </nav>
                {tab === tabKeys.summary && (
                    <ProjectJourney
                        key={journey?.closure?.revision}
                        journey={journey}
                    />
                )}
                {loading && <Skeleton className="h-32 w-full" />}
                {tab === tabKeys.summary && (
                    <div className="projects-summary-grid">
                        <section className="projects-panel">
                            <h2>Información general</h2>
                            <dl className="projects-info">
                                {info('Referencia', i.project_reference)}
                                {info('Nombre', i.site_name)}
                                {info('Cliente', customerName(i))}
                                {info(
                                    'Tipo',
                                    translate(
                                        serviceLabel(i.service_type),
                                        locale,
                                    ),
                                )}
                                {info('Potencia FV', power(i.system_power_kw))}
                                {info(
                                    'Dirección',
                                    [
                                        i.site_address_line_1,
                                        i.site_city,
                                        i.site_province_state,
                                    ]
                                        .filter(Boolean)
                                        .join(', '),
                                )}
                                {info('Comercial', i.commercial_name)}
                                {info('Técnico', i.technician_name)}
                                {info(
                                    'Inicio programado',
                                    date(i.scheduled_date),
                                )}
                                {info('Finalización', date(i.completed_date))}
                            </dl>
                            {permissions.customer && i.person_id && (
                                <Link href={'/people/' + i.person_id}>
                                    Ver cliente
                                </Link>
                            )}
                            {permissions.quotes && quote && (
                                <Link href={'/quotes/' + quote.id}>
                                    {quote.number} · Ver presupuesto
                                </Link>
                            )}
                            {permissions.customer && opportunity && (
                                <Link href={'/opportunities/' + opportunity.id}>
                                    {opportunity.code} · Ver oportunidad
                                </Link>
                            )}
                            {i.people && i.people.length > 1 && (
                                <div>
                                    <h3>Personas vinculadas</h3>
                                    {i.people.map((p) => (
                                        <p key={p.id}>{p.name}</p>
                                    ))}
                                </div>
                            )}
                        </section>
                        <section className="projects-panel">
                            <h2>Instalación</h2>
                            <Link
                                className="projects-event"
                                href={'/installations/' + i.id}
                            >
                                <strong>{i.site_name || reference}</strong>
                                <span>{stateLabels[i.status] || i.status}</span>
                                <small>{power(i.system_power_kw)}</small>
                            </Link>
                            <h2>Hitos registrados</h2>
                            {Object.entries(milestones)
                                .filter(([, value]) => value)
                                .map(([key, value]) => (
                                    <div
                                        className="projects-milestone"
                                        key={key}
                                    >
                                        {key === scheduledMilestone ? (
                                            <CalendarDays size={15} />
                                        ) : (
                                            <CheckCircle2 size={15} />
                                        )}
                                        <span>{milestoneLabels[key]}</span>
                                        <time>{date(value)}</time>
                                    </div>
                                ))}
                            {permissions.planning && (
                                <Link
                                    href={
                                        '/after-sales?view=installations&project=' +
                                        i.id
                                    }
                                >
                                    Ver Postventa
                                </Link>
                            )}
                        </section>
                        <section className="projects-panel">
                            <h2>Descripción</h2>
                            <p className="whitespace-pre-wrap">
                                {i.site_notes || 'Sin descripción registrada.'}
                            </p>
                            <h2>Notas internas</h2>
                            <p className="whitespace-pre-wrap">
                                {i.technical_notes || 'Sin notas registradas.'}
                            </p>
                        </section>
                    </div>
                )}
                {tab === tabKeys.quote && (
                    <section className="projects-panel">
                        <h2>Presupuesto aceptado</h2>
                        {quote ? (
                            <>
                                <strong>{quote.number}</strong>
                                <p>Aceptado: {date(quote.accepted_at)}</p>
                                {quote.amount !== undefined && (
                                    <p>{money(quote.amount)}</p>
                                )}
                                <Button asChild>
                                    <Link href={'/quotes/' + quote.id}>
                                        Ver presupuesto original
                                    </Link>
                                </Button>
                            </>
                        ) : (
                            <p className="projects-empty">
                                No hay un presupuesto aceptado relacionado.
                            </p>
                        )}
                    </section>
                )}
                {tab === tabKeys.installations && (
                    <section className="projects-panel">
                        <h2>Instalación física</h2>
                        <Link
                            className="projects-event"
                            href={'/installations/' + i.id}
                        >
                            <strong>{i.site_name || reference}</strong>
                            <span>
                                {translate(
                                    serviceLabel(i.service_type),
                                    locale,
                                )}
                            </span>
                            <span>{stateLabels[i.status] || i.status}</span>
                        </Link>
                    </section>
                )}
                {tab === tabKeys.costs && costs && (
                    <section className="projects-panel">
                        <h2>Costes del proyecto</h2>
                        <dl className="projects-costs">
                            {costLabels.map(([key, label]) => (
                                <div key={key}>
                                    <dt>{label}</dt>
                                    <dd>{money(Number(costs[key] ?? 0))}</dd>
                                </div>
                            ))}
                        </dl>
                        {costs.quality?.issues.length ? (
                            <ul className="projects-cost-quality">
                                {costs.quality.issues.map((issue) => (
                                    <li key={issue}>
                                        {translate(issue, locale)}
                                    </li>
                                ))}
                            </ul>
                        ) : null}
                        {!costs.has_actuals && (
                            <p>No hay costes reales registrados.</p>
                        )}
                        <p className="projects-source">
                            Fuentes: presupuesto, consumos, horas, compras,
                            facturación y gastos registrados.
                        </p>
                    </section>
                )}
                {[
                    tabKeys.planning,
                    tabKeys.equipment,
                    tabKeys.documents,
                    tabKeys.activity,
                ].includes(tab) && (
                    <section className="projects-panel">
                        <h2>{allTabs.find(([key]) => key === tab)?.[1]}</h2>
                        {tab === tabKeys.documents && (
                            <>
                                <h3>Expedientes técnicos</h3>
                                {dossiers.map((d) => (
                                    <p key={d.id}>
                                        {d.code} · {d.title} · {d.status}
                                    </p>
                                ))}
                                {dossiers.length > 0 && (
                                    <Link href="/engineering">
                                        Abrir expedientes técnicos
                                    </Link>
                                )}
                            </>
                        )}
                        {records?.data.length ? (
                            records.data.map((row) => (
                                <article
                                    className="projects-event"
                                    key={row.id}
                                >
                                    {tab === tabKeys.planning ? (
                                        <>
                                            <Link
                                                href={
                                                    '/work-orders?work_order=' +
                                                    row.id
                                                }
                                            >
                                                <strong>
                                                    {row.number} · {row.title}
                                                </strong>
                                            </Link>
                                            <span>
                                                {row.status} ·{' '}
                                                {row.assigned_user?.name ||
                                                    'Sin asignar'}
                                            </span>
                                            <time>
                                                {date(row.scheduled_start)} →{' '}
                                                {date(row.scheduled_end)}
                                            </time>
                                        </>
                                    ) : tab === tabKeys.equipment ? (
                                        <>
                                            <strong>
                                                {row.asset_tag} · {row.name}
                                            </strong>
                                            <span>
                                                {row.status} ·{' '}
                                                {row.serial_number || '—'}
                                            </span>
                                            <small>
                                                Puesta en marcha:{' '}
                                                {date(row.commissioned_at)} ·
                                                Garantía:{' '}
                                                {date(row.warranty_ends_on)}
                                            </small>
                                        </>
                                    ) : tab === tabKeys.documents ? (
                                        <>
                                            <a
                                                href={
                                                    '/work-orders/' +
                                                    row.work_order_id +
                                                    '/attachments/' +
                                                    row.id
                                                }
                                                target="_blank"
                                                rel="noreferrer"
                                            >
                                                <strong>
                                                    {row.original_name}
                                                </strong>
                                            </a>
                                            <span>{row.kind}</span>
                                            <time>{date(row.created_at)}</time>
                                        </>
                                    ) : (
                                        <>
                                            <strong>
                                                {translate(
                                                    row.event || '',
                                                    locale,
                                                )}
                                            </strong>
                                            <span>{row.user?.name || '—'}</span>
                                            <time>{date(row.created_at)}</time>
                                        </>
                                    )}
                                </article>
                            ))
                        ) : (
                            <p className="projects-empty">
                                {tab === tabKeys.planning
                                    ? 'No hay órdenes de trabajo registradas.'
                                    : tab === tabKeys.equipment
                                      ? 'No hay equipos registrados.'
                                      : tab === tabKeys.documents
                                        ? 'No hay archivos adjuntos.'
                                        : 'No hay actividad registrada.'}
                            </p>
                        )}
                        {records && (
                            <PaginationBar
                                links={records.links}
                                total={records.total}
                                noun="registros"
                            />
                        )}
                        {tab === tabKeys.planning && permissions.manage && (
                            <Link
                                href={`/work-orders/create?installation=${i.id}`}
                            >
                                Nueva orden de trabajo
                            </Link>
                        )}
                        {tab === tabKeys.planning && (
                            <Link href="/operations/planning">
                                Abrir planificación de Operaciones
                            </Link>
                        )}
                        {tab === tabKeys.equipment && (
                            <Link
                                href={'/installations/' + i.id + '?tab=manage'}
                            >
                                Gestionar equipos y materiales
                            </Link>
                        )}
                    </section>
                )}
            </PageShell>
        </>
    );
}

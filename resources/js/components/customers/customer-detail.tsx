import '../../../css/commercial-mobile.css';
import { Form, Link } from '@inertiajs/react';
import {
    ArrowLeft,
    ChevronRight,
    FileText,
    Mail,
    MapPin,
    Pencil,
    Phone,
} from 'lucide-react';
import type { ComponentProps } from 'react';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { Button } from '@/components/ui/button';
import { CustomerCrmDetails } from '@/pages/people/legacy-show';
import {
    customerStatuses,
    customerTypes,
    customerUrl,
    date,
    domainLabels,
    money,
} from './types';
import type {
    CustomerFilters,
    CustomerWorkspace,
    Domain,
    Pagination,
    RelationRow,
} from './types';

type ProfilePerson = ComponentProps<typeof CustomerCrmDetails>['person'] & {
    created_at?: string;
};
export function CustomerDetail({
    person,
    workspace,
    filters,
    availableTags,
}: {
    person: ProfilePerson;
    workspace: CustomerWorkspace;
    filters: CustomerFilters;
    availableTags: ComponentProps<typeof CustomerCrmDetails>['availableTags'];
}) {
    const name =
        person.company_name ||
        `${person.first_name} ${person.last_name ?? ''}`.trim();
    const address = [
        person.address_line_1,
        person.address_line_2,
        person.postal_code,
        person.city || person.locality,
        person.province_state,
        person.country,
    ]
        .filter(Boolean)
        .join(', ');
    const billingAddress = [
        person.billing_address_line_1,
        person.billing_address_line_2,
        person.billing_postal_code,
        person.billing_city || person.billing_locality,
        person.billing_province_state,
        person.billing_country,
    ]
        .filter(Boolean)
        .join(', ');
    const tabs = [
        ['summary', 'Resumen'],
        ...Object.entries(domainLabels).filter(
            ([key]) => workspace.permissions[key as Domain],
        ),
    ] as [CustomerWorkspace['tab'], string][];
    const url = (tab: string) => customerUrl(filters, person.id, tab);

    return (
        <section className="customers-detail" aria-label={`Ficha de ${name}`}>
            <header className="shell-page-header customers-detail-header">
                <Breadcrumbs
                    breadcrumbs={[
                        { title: 'Comercial', href: '/commercial' },
                        { title: 'Clientes', href: customerUrl(filters) },
                        { title: name, href: customerUrl(filters, person.id) },
                    ]}
                />
                <Link
                    className="customers-detail-back"
                    href={customerUrl(filters)}
                >
                    <ArrowLeft size={16} />
                    Volver a clientes
                </Link>
                <div className="customers-detail-title">
                    <h1>{name}</h1>
                    <span
                        className={`customer-status customer-status-${person.lifecycle_status}`}
                    >
                        {customerStatuses[person.lifecycle_status ?? ''] ??
                            'Sin estado'}
                    </span>
                </div>
                <p>
                    {[
                        person.tax_id || person.document_number,
                        customerTypes[person.customer_type ?? ''],
                        person.city || person.locality,
                        person.province_state,
                        `Alta ${date(person.created_at)}`,
                    ]
                        .filter(Boolean)
                        .join(' · ')}
                </p>
                <div className="customers-detail-actions">
                    <Button variant="outline" size="sm" asChild>
                        <Link href={`/people/${person.id}/edit`}>
                            <Pencil />
                            Editar
                        </Link>
                    </Button>
                    {workspace.permissions.quotes && (
                        <Button variant="outline" size="sm" asChild>
                            <Link href={`/quotes/create?customer=${person.id}`}>
                                <FileText />
                                Crear presupuesto
                            </Link>
                        </Button>
                    )}
                    <details className="customers-actions-menu">
                        <summary>Más acciones</summary>
                        <div>
                            <Link href="/opportunities?view=kanban">
                                Abrir CRM
                            </Link>
                            <Link href={`/people/${person.id}/edit`}>
                                Editar datos y direcciones
                            </Link>
                            <Form
                                action={`/people/${person.id}`}
                                method="delete"
                            >
                                <Button variant="ghost" size="sm">
                                    Archivar cliente
                                </Button>
                            </Form>
                        </div>
                    </details>
                </div>
            </header>
            <nav
                className="customers-detail-tabs"
                aria-label="Secciones de la ficha del cliente"
            >
                {tabs.map(([key, label]) => (
                    <Link
                        key={key}
                        href={url(key)}
                        aria-current={
                            workspace.tab === key ? 'page' : undefined
                        }
                        preserveScroll
                    >
                        {label}
                    </Link>
                ))}
            </nav>
            <div className="customers-detail-body" key={workspace.tab}>
                {workspace.possibleDuplicates.length > 0 && (
                    <aside className="customer-duplicate-warning">
                        <strong>Posibles coincidencias de identidad</strong>
                        <p>
                            Revisa estos perfiles antes de crear otro cliente.
                            No se han fusionado.
                        </p>
                        {workspace.possibleDuplicates.map((match) => (
                            <Link
                                key={match.id}
                                href={customerUrl(filters, match.id)}
                            >
                                {match.name}
                            </Link>
                        ))}
                    </aside>
                )}
                {workspace.tab === 'summary' ? (
                    <>
                        <div className="customers-summary-top">
                            <section className="customer-panel">
                                <h3>Información general</h3>
                                <div className="customers-general-layout">
                                    <dl className="customers-general-info">
                                        <Info label="Nombre" value={name} />
                                        <Info
                                            label="NIF/CIF"
                                            value={
                                                person.tax_id ||
                                                person.document_number ||
                                                '—'
                                            }
                                        />
                                        <Info
                                            label="Tipo"
                                            value={
                                                customerTypes[
                                                    person.customer_type ?? ''
                                                ] ?? 'Sin clasificar'
                                            }
                                        />
                                        <Info
                                            label="Teléfono"
                                            icon={<Phone size={15} />}
                                            value={
                                                person.phone ? (
                                                    <a
                                                        href={`tel:${person.phone}`}
                                                    >
                                                        {person.phone}
                                                    </a>
                                                ) : (
                                                    'Sin teléfono'
                                                )
                                            }
                                        />
                                        <Info
                                            label="Email"
                                            icon={<Mail size={15} />}
                                            value={
                                                person.email ? (
                                                    <a
                                                        href={`mailto:${person.email}`}
                                                    >
                                                        {person.email}
                                                    </a>
                                                ) : (
                                                    'Sin email'
                                                )
                                            }
                                        />
                                        <Info
                                            label="Dirección"
                                            icon={<MapPin size={15} />}
                                            value={
                                                [
                                                    person.address_line_1,
                                                    person.address_line_2,
                                                    person.postal_code,
                                                    person.city ||
                                                        person.locality,
                                                    person.province_state,
                                                    person.country,
                                                ]
                                                    .filter(Boolean)
                                                    .join(', ') ||
                                                'Sin dirección'
                                            }
                                        />
                                        <Info
                                            label="Municipio"
                                            value={
                                                person.city ||
                                                person.locality ||
                                                '—'
                                            }
                                        />
                                        <Info
                                            label="Provincia"
                                            value={person.province_state || '—'}
                                        />
                                        <Info
                                            label="Código postal"
                                            value={person.postal_code || '—'}
                                        />
                                        <Info
                                            label="País"
                                            value={person.country || '—'}
                                        />
                                        <Info
                                            label="Fecha de alta"
                                            value={date(person.created_at)}
                                        />
                                        {person.billing_name &&
                                            (person.billing_name !== name ||
                                                person.billing_tax_id !==
                                                    person.tax_id) && (
                                                <Info
                                                    label="Identidad fiscal"
                                                    value={[
                                                        person.billing_name,
                                                        person.billing_tax_id,
                                                    ]
                                                        .filter(Boolean)
                                                        .join(' · ')}
                                                />
                                            )}
                                        {person.billing_address_line_1 &&
                                            billingAddress !== address && (
                                                <Info
                                                    label="Dirección fiscal"
                                                    value={[
                                                        person.billing_address_line_1,
                                                        person.billing_address_line_2,
                                                        person.billing_postal_code,
                                                        person.billing_city,
                                                        person.billing_province_state,
                                                        person.billing_country,
                                                    ]
                                                        .filter(Boolean)
                                                        .join(', ')}
                                                />
                                            )}
                                    </dl>
                                    <div
                                        className="customers-avatar"
                                        aria-hidden="true"
                                    >
                                        {name
                                            .split(' ')
                                            .map((n) => n[0])
                                            .slice(0, 2)
                                            .join('')}
                                    </div>
                                </div>
                            </section>
                            <section className="customer-panel">
                                <h3>Métricas</h3>
                                <dl className="customers-metrics">
                                    {(Object.keys(domainLabels) as Domain[])
                                        .filter(
                                            (key) =>
                                                workspace.domains[key] !=
                                                    null &&
                                                key !== 'activity' &&
                                                key !== 'documents',
                                        )
                                        .map((key) => (
                                            <div key={key}>
                                                <dt>
                                                    <Link href={url(key)}>
                                                        {domainLabels[key]}
                                                    </Link>
                                                </dt>
                                                <dd>
                                                    {workspace.domains[key]
                                                        ?.total ?? 0}
                                                </dd>
                                            </div>
                                        ))}
                                    {workspace.financial && (
                                        <>
                                            <div>
                                                <dt>Facturación emitida</dt>
                                                <dd>
                                                    {money(
                                                        workspace.financial
                                                            .billed,
                                                    )}
                                                </dd>
                                            </div>
                                            <div>
                                                <dt>Pendiente de cobro</dt>
                                                <dd>
                                                    {money(
                                                        workspace.financial
                                                            .outstanding,
                                                    )}
                                                </dd>
                                            </div>
                                        </>
                                    )}
                                </dl>
                                {workspace.financial && (
                                    <p className="customer-source">
                                        Facturas emitidas, IVA incluido. Excluye
                                        borradores y anuladas.
                                    </p>
                                )}
                            </section>
                        </div>
                        <div className="customers-summary-grid">
                            {(Object.keys(domainLabels) as Domain[])
                                .filter((key) => workspace.domains[key] != null)
                                .map((key) => (
                                    <RelationPanel
                                        key={key}
                                        title={
                                            key === 'activity'
                                                ? 'Actividad reciente'
                                                : domainLabels[key]
                                        }
                                        relation={workspace.domains[key]!}
                                        href={url(key)}
                                    />
                                ))}
                        </div>
                        <section className="customer-panel">
                            <h3>Contactos</h3>
                            {person.contacts.length ? (
                                <ul className="customers-contacts">
                                    {person.contacts.map((contact) => (
                                        <li key={contact.id}>
                                            <strong>
                                                {contact.first_name}{' '}
                                                {contact.last_name}
                                                {contact.is_primary
                                                    ? ' · Principal'
                                                    : ''}
                                            </strong>
                                            <span>{contact.position}</span>
                                            {contact.email && (
                                                <a
                                                    href={`mailto:${contact.email}`}
                                                >
                                                    {contact.email}
                                                </a>
                                            )}
                                            {contact.phone && (
                                                <a
                                                    href={`tel:${contact.phone}`}
                                                >
                                                    {contact.phone}
                                                </a>
                                            )}
                                        </li>
                                    ))}
                                </ul>
                            ) : (
                                <p className="customer-empty">
                                    No hay contactos adicionales. Se utilizan
                                    los datos generales del cliente.
                                </p>
                            )}
                            <details className="customers-contact-management">
                                <summary>
                                    Gestionar contactos, etiquetas y
                                    preferencias
                                </summary>
                                <CustomerCrmDetails
                                    person={person}
                                    availableTags={availableTags}
                                />
                            </details>
                        </section>
                    </>
                ) : (
                    <RelationPanel
                        title={domainLabels[workspace.tab]}
                        relation={workspace.domains[workspace.tab]!}
                        expanded
                    />
                )}
            </div>
        </section>
    );
}
function Info({
    label,
    value,
    icon,
}: {
    label: string;
    value: React.ReactNode;
    icon?: React.ReactNode;
}) {
    return (
        <div>
            <dt>
                {icon}
                {label}
            </dt>
            <dd>{value}</dd>
        </div>
    );
}
function RelationPanel({
    title,
    relation,
    href,
    expanded = false,
}: {
    title: string;
    relation: Pagination<RelationRow>;
    href?: string;
    expanded?: boolean;
}) {
    return (
        <section className="customer-panel">
            <div className="customer-panel-heading">
                <h3>
                    {title}
                    {expanded ? ` (${relation.total})` : ''}
                </h3>
                {href && (
                    <Link href={href} preserveScroll>
                        Ver todos <ChevronRight size={13} />
                    </Link>
                )}
            </div>
            {relation.data.length ? (
                <ul className="customer-relation-list">
                    {relation.data.map((row) => (
                        <li key={row.id}>
                            <FileText size={16} />
                            <div>
                                {row.href ? (
                                    <Link href={row.href}>{row.title}</Link>
                                ) : (
                                    <strong>{row.title}</strong>
                                )}
                                {row.reference && (
                                    <small>{row.reference}</small>
                                )}
                                <small>{row.subtitle}</small>
                                <span className="customer-relation-status">
                                    {status(row.status)}
                                </span>
                            </div>
                            <div className="customer-relation-end">
                                {row.amount != null && (
                                    <strong>{money(row.amount)}</strong>
                                )}
                                <time>{date(row.date)}</time>
                            </div>
                        </li>
                    ))}
                </ul>
            ) : (
                <p className="customer-empty">
                    Sin registros de {title.toLowerCase()} para este cliente.
                </p>
            )}
            {expanded && relation.links.length > 3 && (
                <nav
                    className="customers-pagination"
                    aria-label={`Páginas de ${title}`}
                >
                    {relation.links
                        .filter((l) => l.url)
                        .map((link, index) => (
                            <Link
                                key={index}
                                href={link.url!}
                                aria-current={link.active ? 'page' : undefined}
                                preserveScroll
                            >
                                {link.label.includes('Previous') ||
                                link.label.includes('Anterior')
                                    ? '‹'
                                    : link.label.includes('Next') ||
                                        link.label.includes('Siguiente')
                                      ? '›'
                                      : link.label.replace(/&[^;]+;/g, '')}
                            </Link>
                        ))}
                </nav>
            )}
        </section>
    );
}
function status(value?: string | null) {
    const labels: Record<string, string> = {
        new: 'Nuevo',
        qualified: 'Cualificado',
        visit_scheduled: 'Visita técnica',
        proposal: 'Propuesta',
        negotiation: 'Negociación',
        won: 'Ganada',
        lost: 'Perdida',
        draft: 'Borrador',
        sent: 'Enviado',
        accepted: 'Aceptado',
        rejected: 'Rechazado',
        expired: 'Caducado',
        issued: 'Emitida',
        partial: 'Parcial',
        paid: 'Pagada',
        overdue: 'Vencida',
        active: 'Activa',
        completed: 'Completada',
        cancelled: 'Anulada',
        pending: 'Pendiente',
        in_progress: 'En curso',
        internal: 'Interno',
        published: 'Publicado',
        pending_review: 'Pendiente de revisión',
    };

    return value ? (labels[value] ?? value) : '';
}

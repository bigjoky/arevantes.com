import '../../../css/commercial-mobile.css';
import { Form, Link, router } from '@inertiajs/react';
import {
    ArrowDownUp,
    Archive,
    Filter,
    Plus,
    RotateCcw,
    Search,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { PageHeader } from '@/components/page-shell';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import {
    customerStatuses,
    customerTypes,
    customerUrl,
    date,
    money,
} from './types';
import type { CustomerFilters, CustomersProps } from './types';
import { useCustomerNavigation } from './use-customer-navigation';

export function CustomerList({
    people,
    filters = {},
    provinces,
    customerPermissions,
    savedFilters,
    leadSources,
    tags,
    selectedId,
}: CustomersProps & { selectedId?: number }) {
    const loading = useCustomerNavigation();
    const [search, setSearch] = useState(filters.search ?? '');
    const [more, setMore] = useState(false);
    const pending = useRef<ReturnType<typeof setTimeout> | null>(null);
    useEffect(
        () => () => {
            if (pending.current) {
                clearTimeout(pending.current);
            }
        },
        [],
    );
    function visit(changes: CustomerFilters) {
        if (pending.current) {
            clearTimeout(pending.current);
        }

        router.get(
            '/people',
            { ...filters, ...changes, page: undefined },
            { preserveState: true, preserveScroll: true },
        );
    }
    function find(value: string) {
        setSearch(value);

        if (pending.current) {
            clearTimeout(pending.current);
        }

        pending.current = setTimeout(() => visit({ search: value }), 300);
    }

    return (
        <section className="customers-master" aria-label="Listado de clientes">
            <PageHeader
                title="Clientes"
                description="Gestiona tu cartera de clientes y accede a toda su información."
                breadcrumbs={[
                    { title: 'Comercial', href: '/commercial' },
                    { title: 'Clientes', href: '/people' },
                ]}
                secondaryNavigation={null}
                actions={
                    <Button asChild>
                        <Link href="/people/create">
                            <Plus />
                            Nuevo cliente
                        </Link>
                    </Button>
                }
            />
            <div className="customers-filter-bar">
                <label className="customers-search">
                    <Search size={16} />
                    <span className="sr-only">Buscar clientes</span>
                    <input
                        type="search"
                        placeholder="Buscar clientes…"
                        value={search}
                        onChange={(e) => find(e.target.value)}
                    />
                </label>
                <FilterSelect
                    label="Estado"
                    value={filters.lifecycle_status}
                    options={customerStatuses}
                    onChange={(value) => visit({ lifecycle_status: value })}
                />
                <FilterSelect
                    label="Tipo"
                    value={filters.customer_type}
                    options={customerTypes}
                    onChange={(value) => visit({ customer_type: value })}
                />
                <FilterSelect
                    label="Provincia"
                    value={filters.province_state}
                    options={Object.fromEntries(provinces.map((p) => [p, p]))}
                    onChange={(value) => visit({ province_state: value })}
                />
                <Button
                    variant="outline"
                    onClick={() => setMore(!more)}
                    aria-expanded={more}
                    aria-controls="customer-more-filters"
                >
                    <Filter />
                    Más filtros
                </Button>
            </div>
            {more && (
                <div
                    className="customers-more-filters"
                    id="customer-more-filters"
                >
                    <FilterSelect
                        label="Origen"
                        value={filters.lead_source}
                        options={Object.fromEntries(
                            leadSources.map((p) => [p, p]),
                        )}
                        onChange={(value) => visit({ lead_source: value })}
                    />
                    <FilterSelect
                        label="Etiqueta"
                        value={filters.tag}
                        options={Object.fromEntries(
                            tags.map((t) => [String(t.id), t.name]),
                        )}
                        onChange={(value) => visit({ tag: value })}
                    />
                    <label>
                        <input
                            type="checkbox"
                            checked={filters.has_opportunities === '1'}
                            onChange={(e) =>
                                visit({
                                    has_opportunities: e.target.checked
                                        ? '1'
                                        : '',
                                })
                            }
                        />
                        Con oportunidades
                    </label>
                    {customerPermissions.quotes && (
                        <label>
                            <input
                                type="checkbox"
                                checked={filters.has_quotes === '1'}
                                onChange={(e) =>
                                    visit({
                                        has_quotes: e.target.checked ? '1' : '',
                                    })
                                }
                            />
                            Con presupuestos
                        </label>
                    )}
                    <Button variant="outline" asChild>
                        <Link
                            href={customerUrl({
                                ...filters,
                                archived: filters.archived ? '' : '1',
                                page: '',
                            })}
                        >
                            {filters.archived ? <RotateCcw /> : <Archive />}
                            {filters.archived ? 'Activos' : 'Archivados'}
                        </Link>
                    </Button>
                    <Button variant="ghost" asChild>
                        <Link href="/people">Limpiar filtros</Link>
                    </Button>
                </div>
            )}
            {savedFilters.length > 0 && (
                <div
                    className="customers-saved-filters"
                    aria-label="Vistas guardadas"
                >
                    {savedFilters.map((view) => (
                        <Link key={view.id} href={customerUrl(view.filters)}>
                            {view.name}
                        </Link>
                    ))}
                </div>
            )}
            <div className="customers-table-card" aria-busy={loading}>
                {loading && (
                    <div role="status" className="customers-loading">
                        Cargando clientes…
                        <Skeleton className="mt-4 h-8 w-64" />
                        <Skeleton className="mt-3 h-8 w-64" />
                        <Skeleton className="mt-3 h-8 w-64" />
                    </div>
                )}
                <div className="customers-list-heading">
                    <strong>
                        {people.total}{' '}
                        {people.total === 1 ? 'cliente' : 'clientes'}
                        {filters.archived ? ' archivados' : ''}
                    </strong>
                    <label>
                        <ArrowDownUp size={14} />
                        <span className="sr-only">Ordenar clientes</span>
                        <select
                            aria-label="Ordenar clientes"
                            value={`${filters.sort ?? 'created_at'}:${filters.direction ?? 'desc'}`}
                            onChange={(e) => {
                                const [sort, direction] =
                                    e.target.value.split(':');
                                visit({ sort, direction });
                            }}
                        >
                            <option value="created_at:desc">
                                Alta reciente
                            </option>
                            <option value="created_at:asc">
                                Alta más antigua
                            </option>
                            <option value="name:asc">Nombre A–Z</option>
                            <option value="name:desc">Nombre Z–A</option>
                            {customerPermissions.invoices && (
                                <option value="volume:desc">
                                    Mayor facturación
                                </option>
                            )}
                            {customerPermissions.activity && (
                                <option value="last_activity_at:desc">
                                    Actividad reciente
                                </option>
                            )}
                        </select>
                    </label>
                </div>
                <div className="customers-table-scroll">
                    <table>
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>Tipo</th>
                                <th>Ubicación</th>
                                <th>Estado</th>
                                <th>Oportunidades</th>
                                {customerPermissions.quotes && (
                                    <th>Presupuestos</th>
                                )}
                                {customerPermissions.invoices && (
                                    <th title="Facturas emitidas, IVA incluido; excluye borradores y anuladas">
                                        Volumen
                                    </th>
                                )}
                                {customerPermissions.activity && (
                                    <th title="Último registro directo de actividad CRM del cliente">
                                        Última actividad CRM
                                    </th>
                                )}
                                {filters.archived && <th>Acciones</th>}
                            </tr>
                        </thead>
                        <tbody>
                            {people.data.map((person) => (
                                <tr
                                    key={person.id}
                                    data-selected={selectedId === person.id}
                                >
                                    <td>
                                        {filters.archived ? (
                                            <strong>{person.name}</strong>
                                        ) : (
                                            <Link
                                                className="customers-name"
                                                href={customerUrl(
                                                    filters,
                                                    person.id,
                                                )}
                                                aria-current={
                                                    selectedId === person.id
                                                        ? 'page'
                                                        : undefined
                                                }
                                            >
                                                {person.name}
                                            </Link>
                                        )}
                                        <small>{person.tax_id || '—'}</small>
                                    </td>
                                    <td>
                                        {customerTypes[
                                            person.customer_type ?? ''
                                        ] ?? 'Sin clasificar'}
                                    </td>
                                    <td>{person.location || '—'}</td>
                                    <td>
                                        <span
                                            className={`customer-status customer-status-${person.lifecycle_status}`}
                                        >
                                            {customerStatuses[
                                                person.lifecycle_status ?? ''
                                            ] ?? 'Sin estado'}
                                        </span>
                                    </td>
                                    <td>{person.opportunities_count}</td>
                                    {customerPermissions.quotes && (
                                        <td>{person.quotes_count}</td>
                                    )}
                                    {customerPermissions.invoices && (
                                        <td className="customers-amount">
                                            {money(person.volume ?? 0)}
                                        </td>
                                    )}
                                    {customerPermissions.activity && (
                                        <td>{date(person.last_activity_at)}</td>
                                    )}
                                    {filters.archived && (
                                        <td>
                                            <Form
                                                action={`/people/${person.id}/restore`}
                                                method="post"
                                            >
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                >
                                                    Restaurar
                                                </Button>
                                            </Form>
                                        </td>
                                    )}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                <div className="customers-mobile-list">
                    {people.data.map((person) => (
                        <article
                            key={person.id}
                            className="customers-mobile-row"
                        >
                            {filters.archived ? (
                                <strong>{person.name}</strong>
                            ) : (
                                <Link
                                    className="customers-name"
                                    href={customerUrl(filters, person.id)}
                                >
                                    {person.name}
                                </Link>
                            )}
                            <span>
                                {[customerTypes[person.customer_type ?? ''], person.location]
                                    .filter(Boolean)
                                    .join(' · ') || 'Sin ubicación'}
                            </span>
                            <span>
                                <span
                                    className={`customer-status customer-status-${person.lifecycle_status}`}
                                >
                                    {customerStatuses[
                                        person.lifecycle_status ?? ''
                                    ] ?? 'Sin estado'}
                                </span>{' '}
                                · {person.opportunities_count} oportunidades
                            </span>
                            {customerPermissions.invoices && (
                                <b>{money(person.volume ?? 0)}</b>
                            )}
                            {!filters.archived && (person.phone || person.email) && <nav className="commercial-contact-actions" aria-label={`Contacto de ${person.name}`}>
                                {person.phone && <a href={`tel:${person.phone}`} aria-label={`Llamar a ${person.name}`}>Llamar</a>}
                                {person.email && <a href={`mailto:${person.email}`} aria-label={`Enviar email a ${person.name}`}>Email</a>}
                            </nav>}
                            {filters.archived && (
                                <Form
                                    action={`/people/${person.id}/restore`}
                                    method="post"
                                >
                                    <Button size="sm" variant="outline">
                                        Restaurar
                                    </Button>
                                </Form>
                            )}
                        </article>
                    ))}
                </div>
                {people.total === 0 && (
                    <div className="customers-empty">
                        <p>
                            {Object.values(filters).some(Boolean)
                                ? 'No hay clientes que coincidan con los filtros.'
                                : 'Todavía no hay clientes.'}
                        </p>
                        <Button variant="outline" asChild>
                            <Link href="/people/create">
                                Crear primer cliente
                            </Link>
                        </Button>
                    </div>
                )}
                <div className="customers-pagination">
                    <span>
                        Mostrando {people.from ?? 0}–{people.to ?? 0} de{' '}
                        {people.total}
                    </span>
                    <nav aria-label="Páginas de clientes">
                        {people.links
                            .filter((link) => link.url)
                            .map((link, index) => (
                                <Link
                                    key={index}
                                    href={link.url!}
                                    aria-current={
                                        link.active ? 'page' : undefined
                                    }
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
                    <label>
                        <span className="sr-only">Clientes por página</span>
                        <select
                            aria-label="Clientes por página"
                            value={filters.per_page ?? '15'}
                            onChange={(e) =>
                                visit({ per_page: e.target.value })
                            }
                        >
                            <option value="15">15 por página</option>
                            <option value="30">30 por página</option>
                            <option value="50">50 por página</option>
                        </select>
                    </label>
                </div>
            </div>
        </section>
    );
}
function FilterSelect({
    label,
    value = '',
    options,
    onChange,
}: {
    label: string;
    value?: string;
    options: Record<string, string>;
    onChange: (value: string) => void;
}) {
    return (
        <label className="customers-filter">
            <span>{label}</span>
            <select
                value={value}
                onChange={(e) => onChange(e.target.value)}
                aria-label={label}
            >
                <option value="">Todos</option>
                {Object.entries(options).map(([value, text]) => (
                    <option value={value} key={value}>
                        {text}
                    </option>
                ))}
            </select>
        </label>
    );
}

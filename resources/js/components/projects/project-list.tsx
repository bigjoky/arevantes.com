import { Head, Link, router } from '@inertiajs/react';
import { Plus, Search, SlidersHorizontal } from 'lucide-react';
import { useState } from 'react';
import { useCustomerNavigation } from '@/components/customers/use-customer-navigation';
import { PageHeader, PageShell, PaginationBar } from '@/components/page-shell';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { Skeleton } from '@/components/ui/skeleton';
import { useLocale } from '@/hooks/use-locale';
import { translate } from '@/i18n';
import { serviceLabel, serviceLabels } from '@/lib/business-lines';
import {
    customerName,
    date,
    phaseLabels,
    power,
    projectUrl,
    stateLabels,
} from './types';
import type {
    Filters,
    InstallationRow,
    Pagination,
    Permissions,
} from './types';
export type ListProps = {
    items: InstallationRow[];
    pagination: Pagination;
    filters: Filters;
    counts: Record<string, number>;
    customers: {
        id: number;
        first_name: string;
        last_name: string | null;
        company_name: string | null;
    }[];
    technicians: { id: number; name: string }[];
    permissions: Permissions;
    mode: 'projects' | 'installations';
};
export default function ProjectList({
    items,
    pagination,
    filters,
    counts,
    customers,
    technicians,
    permissions,
    mode,
}: ListProps) {
    const [search, setSearch] = useState(filters.search ?? '');
    const loading = useCustomerNavigation();
    const { locale } = useLocale();
    const isProject = mode === 'projects';
    const title = isProject ? 'Proyectos' : 'Instalaciones';
    const update = (patch: Filters) =>
        router.get(
            projectUrl({}, undefined, undefined, mode),
            Object.fromEntries(
                Object.entries({
                    ...filters,
                    search,
                    ...patch,
                    page: '',
                }).filter(([, v]) => v !== ''),
            ),
            { preserveScroll: true },
        );
    const select = (
        label: string,
        key: string,
        options: [string, string][],
        fallback = '',
    ) => (
        <label className="projects-filter">
            <span>{label}</span>
            <select
                value={filters[key] ?? fallback}
                onChange={(e) => update({ [key]: e.target.value })}
            >
                {!fallback && <option value="">Todos</option>}
                {options.map(([value, text]) => (
                    <option key={value} value={value}>
                        {text}
                    </option>
                ))}
            </select>
        </label>
    );
    const afterSalesKey = 'after_sales';
    const defaultSort = 'created_at';
    const defaultDirection = 'desc';
    const stateKey = 'status';
    const typeKey = 'service_type';
    const customerKey = 'customer';
    const sortKey = 'sort';
    const directionKey = 'direction';
    const sortOptions: [string, string][] = [
        ['created_at', 'Fecha de creación'],
        ['project_reference', 'Referencia'],
        ['site_name', 'Nombre'],
        ['scheduled_date', 'Inicio programado'],
        ['completed_date', 'Finalización'],
        ['status', 'Estado'],
    ];
    const directions: [string, string][] = [
        ['desc', 'Descendente'],
        ['asc', 'Ascendente'],
    ];

    return (
        <>
            <Head title={title} />
            <PageShell className="projects-workspace">
                <PageHeader
                    title={title}
                    description={
                        isProject
                            ? 'Planifica, ejecuta y controla todos tus proyectos.'
                            : 'Gestiona las instalaciones, su ubicación y su ejecución.'
                    }
                    breadcrumbs={[
                        { title: 'Proyectos', href: '/execution' },
                        {
                            title,
                            href: projectUrl({}, undefined, undefined, mode),
                        },
                    ]}
                    secondaryNavigation={null}
                    primaryAction={
                        permissions.manage &&
                        (!isProject || permissions.quotes) && (
                            <Button asChild>
                                <Link
                                    href={
                                        isProject
                                            ? '/execution/projects/create'
                                            : '/installations/create'
                                    }
                                >
                                    <Plus />
                                    {isProject
                                        ? 'Nuevo proyecto'
                                        : 'Nueva instalación'}
                                </Link>
                            </Button>
                        )
                    }
                />
                <form
                    className="projects-filters"
                    onSubmit={(e) => {
                        e.preventDefault();
                        update({ search });
                    }}
                >
                    <label className="projects-search">
                        <Search size={15} />
                        <input
                            aria-label="Buscar proyectos e instalaciones"
                            placeholder={
                                isProject
                                    ? 'Buscar proyectos...'
                                    : 'Buscar instalaciones...'
                            }
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                        />
                    </label>
                    <Button variant="outline" type="submit">
                        Buscar
                    </Button>
                    {select('Estado', stateKey, Object.entries(stateLabels))}
                    {select(
                        'Tipo',
                        typeKey,
                        Object.entries(serviceLabels).map(([key, label]) => [
                            key,
                            translate(label, locale),
                        ]),
                    )}
                    {select(
                        'Cliente',
                        customerKey,
                        customers.map((c) => [
                            String(c.id),
                            c.company_name ||
                                (
                                    c.first_name +
                                    ' ' +
                                    (c.last_name || '')
                                ).trim(),
                        ]),
                    )}
                    <Sheet>
                        <SheetTrigger asChild>
                            <Button type="button" variant="outline">
                                <SlidersHorizontal />
                                Más filtros
                            </Button>
                        </SheetTrigger>
                        <SheetContent>
                            <SheetHeader>
                                <SheetTitle>Más filtros</SheetTitle>
                            </SheetHeader>
                            <div className="projects-extra-filters">
                                <div className="projects-mobile-filters">
                                    {select(
                                        'Estado',
                                        stateKey,
                                        Object.entries(stateLabels),
                                    )}
                                    {select(
                                        'Tipo',
                                        typeKey,
                                        Object.entries(serviceLabels).map(
                                            ([key, label]) => [
                                                key,
                                                translate(label, locale),
                                            ],
                                        ),
                                    )}
                                    {select(
                                        'Cliente',
                                        customerKey,
                                        customers.map((c) => [
                                            String(c.id),
                                            c.company_name ||
                                                (
                                                    c.first_name +
                                                    ' ' +
                                                    (c.last_name || '')
                                                ).trim(),
                                        ]),
                                    )}
                                </div>
                                {select(
                                    'Responsable',
                                    'technician',
                                    technicians.map((t) => [
                                        String(t.id),
                                        t.name,
                                    ]),
                                )}
                                <label>
                                    Inicio desde
                                    <input
                                        type="date"
                                        value={filters.date_from ?? ''}
                                        onChange={(e) =>
                                            update({
                                                date_from: e.target.value,
                                            })
                                        }
                                    />
                                </label>
                                <label>
                                    Inicio hasta
                                    <input
                                        type="date"
                                        value={filters.date_to ?? ''}
                                        onChange={(e) =>
                                            update({ date_to: e.target.value })
                                        }
                                    />
                                </label>
                                <Button
                                    variant="outline"
                                    onClick={() =>
                                        router.get(
                                            projectUrl(
                                                {},
                                                undefined,
                                                undefined,
                                                mode,
                                            ),
                                        )
                                    }
                                >
                                    Limpiar filtros
                                </Button>
                            </div>
                        </SheetContent>
                    </Sheet>
                </form>
                {isProject && (
                    <nav
                        className="projects-quick-filters"
                        aria-label="Estados de proyectos"
                    >
                        <Button
                            variant="outline"
                            aria-pressed={!filters.phase}
                            onClick={() => update({ phase: '' })}
                        >
                            Todos
                        </Button>
                        {Object.entries(phaseLabels)
                            .filter(
                                ([key]) =>
                                    key !== afterSalesKey ||
                                    permissions.planning,
                            )
                            .map(([key, label]) => (
                                <Button
                                    key={key}
                                    variant="outline"
                                    aria-pressed={filters.phase === key}
                                    onClick={() =>
                                        update({
                                            phase:
                                                filters.phase === key
                                                    ? ''
                                                    : key,
                                        })
                                    }
                                >
                                    {label}
                                    <span>{counts[key] ?? 0}</span>
                                </Button>
                            ))}
                    </nav>
                )}
                <section className="projects-panel projects-list-panel">
                    <div className="projects-list-toolbar">
                        <span>
                            {pagination.total}{' '}
                            {title.toLocaleLowerCase('es-ES')}
                        </span>
                        {select(
                            'Ordenar por',
                            sortKey,
                            sortOptions,
                            defaultSort,
                        )}
                        {select(
                            'Dirección',
                            directionKey,
                            directions,
                            defaultDirection,
                        )}
                    </div>
                    {loading && (
                        <div className="projects-loading">
                            <Skeleton className="h-8 w-full" />
                            <Skeleton className="h-8 w-full" />
                        </div>
                    )}
                    {!items.length ? (
                        <p className="projects-empty">
                            {isProject
                                ? 'No se encontraron proyectos.'
                                : 'No se encontraron instalaciones.'}
                        </p>
                    ) : (
                        <>
                            <div className="projects-table-scroll">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Referencia</th>
                                            <th>
                                                {isProject
                                                    ? 'Proyecto'
                                                    : 'Instalación'}
                                            </th>
                                            <th>Cliente</th>
                                            <th>Tipo</th>
                                            <th>Potencia FV</th>
                                            <th>Estado</th>
                                            <th>Inicio programado</th>
                                            <th>
                                                {isProject
                                                    ? 'Finalización'
                                                    : 'Responsable'}
                                            </th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {items.map((row) => (
                                            <tr
                                                key={row.id}
                                                className="projects-clickable-row"
                                                tabIndex={0}
                                                onClick={(event) => {
                                                    if (
                                                        !(
                                                            event.target as HTMLElement
                                                        ).closest(
                                                            'a,button,input',
                                                        )
                                                    ) {
                                                        router.visit(
                                                            projectUrl(
                                                                filters,
                                                                row.id,
                                                                undefined,
                                                                mode,
                                                            ),
                                                        );
                                                    }
                                                }}
                                                onKeyDown={(event) => {
                                                    if (
                                                        event.target ===
                                                            event.currentTarget &&
                                                        event.key === 'Enter'
                                                    ) {
                                                        router.visit(
                                                            projectUrl(
                                                                filters,
                                                                row.id,
                                                                undefined,
                                                                mode,
                                                            ),
                                                        );
                                                    }
                                                }}
                                            >
                                                <td>
                                                    <Link
                                                        href={projectUrl(
                                                            filters,
                                                            row.id,
                                                            undefined,
                                                            mode,
                                                        )}
                                                    >
                                                        {row.project_reference ||
                                                            'Instalación ' +
                                                                row.id}
                                                    </Link>
                                                </td>
                                                <td>
                                                    {row.site_name || '—'}
                                                    {!isProject &&
                                                        row.project_reference && (
                                                            <Link
                                                                className="projects-small-link"
                                                                href={
                                                                    '/execution/projects/' +
                                                                    row.id
                                                                }
                                                            >
                                                                Ver proyecto
                                                            </Link>
                                                        )}
                                                </td>
                                                <td>{customerName(row)}</td>
                                                <td>
                                                    {translate(
                                                        serviceLabel(
                                                            row.service_type,
                                                        ),
                                                        locale,
                                                    )}
                                                </td>
                                                <td>
                                                    {power(row.system_power_kw)}
                                                </td>
                                                <td>
                                                    <span
                                                        className={
                                                            'projects-status projects-status-' +
                                                            row.status
                                                        }
                                                    >
                                                        {stateLabels[
                                                            row.status
                                                        ] || row.status}
                                                    </span>
                                                </td>
                                                <td>
                                                    {date(row.scheduled_date)}
                                                </td>
                                                <td>
                                                    {isProject
                                                        ? date(
                                                              row.completed_date,
                                                          )
                                                        : row.technician_name ||
                                                          '—'}
                                                </td>
                                                <td>
                                                    <Link
                                                        aria-label={
                                                            'Abrir ' +
                                                            (row.project_reference ||
                                                                'instalación ' +
                                                                    row.id)
                                                        }
                                                        href={projectUrl(
                                                            filters,
                                                            row.id,
                                                            undefined,
                                                            mode,
                                                        )}
                                                    >
                                                        ···
                                                    </Link>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                            <div className="projects-mobile-list">
                                {items.map((row) => (
                                    <Link
                                        className="projects-mobile-card"
                                        key={row.id}
                                        href={projectUrl(
                                            filters,
                                            row.id,
                                            undefined,
                                            mode,
                                        )}
                                    >
                                        <strong>
                                            {row.project_reference ||
                                                row.site_name ||
                                                'Instalación ' + row.id}
                                        </strong>
                                        <span
                                            className={
                                                'projects-status projects-status-' +
                                                row.status
                                            }
                                        >
                                            {stateLabels[row.status] ||
                                                row.status}
                                        </span>
                                        <p>
                                            {row.site_name} ·{' '}
                                            {customerName(row)}
                                        </p>
                                        <small>
                                            {translate(
                                                serviceLabel(row.service_type),
                                                locale,
                                            )}{' '}
                                            · {power(row.system_power_kw)}
                                        </small>
                                        <small>
                                            Inicio programado:{' '}
                                            {date(row.scheduled_date)}
                                        </small>
                                    </Link>
                                ))}
                            </div>
                        </>
                    )}
                    <div className="projects-pagination">
                        <PaginationBar
                            links={pagination.links}
                            total={pagination.total}
                            noun={title.toLocaleLowerCase('es-ES')}
                        />
                        <label>
                            Por página
                            <select
                                value={filters.per_page ?? '15'}
                                onChange={(e) =>
                                    update({ per_page: e.target.value })
                                }
                            >
                                {[10, 15, 30, 50].map((n) => (
                                    <option key={n} value={n}>
                                        {n}
                                    </option>
                                ))}
                            </select>
                        </label>
                    </div>
                </section>
            </PageShell>
        </>
    );
}

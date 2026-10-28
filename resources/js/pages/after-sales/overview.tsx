import { Head, Link, router, usePage } from '@inertiajs/react';
import { Search, SlidersHorizontal } from 'lucide-react';
import { useState } from 'react';
import { useCustomerNavigation } from '@/components/customers/use-customer-navigation';
import { PageHeader, PageShell, PaginationBar } from '@/components/page-shell';
import { customerName, date, power } from '@/components/projects/types';
import type {
    Filters,
    InstallationRow,
    Pagination,
    Permissions,
} from '@/components/projects/types';
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
import { serviceLabels } from '@/lib/business-lines';
import { projectEntityUrl } from '@/lib/projects/navigation';
type Props = {
    items: InstallationRow[];
    pagination: Pagination;
    filters: Filters;
    counts: Record<string, number>;
    permissions: Permissions;
    upcoming: {
        id: number;
        installation_id: number;
        name: string;
        next_due_on: string;
        installation: {
            site_name: string | null;
            project_reference: string | null;
        };
    }[];
    tickets: {
        id: number;
        installation_id: number;
        number: string;
        title: string;
        priority: string;
        status: string;
        created_at: string;
        installation: {
            site_name: string | null;
            project_reference: string | null;
        };
    }[];
};
const states: [string, string][] = [
    ['', 'Todas'],
    ['review', 'Revisión pendiente'],
    ['follow_up', 'En seguimiento'],
    ['incidents', 'Incidencias'],
    ['none', 'Sin plan ni incidencias'],
];
export default function AfterSalesOverview({
    items,
    pagination,
    filters,
    counts,
    upcoming,
    tickets,
}: Props) {
    const { url: context } = usePage();
    const loading = useCustomerNavigation();
    const [search, setSearch] = useState(filters.search ?? '');
    const { locale } = useLocale();
    const update = (patch: Filters) =>
        router.get(
            '/after-sales',
            Object.fromEntries(
                Object.entries({
                    ...filters,
                    search,
                    view: 'installations',
                    ...patch,
                    page: '',
                }).filter(([, v]) => v !== ''),
            ),
            { preserveScroll: true },
        );
    const state = (i: InstallationRow) =>
        i.service_state || 'Sin plan registrado';
    const totalKey = 'total';
    const typeKey = 'service_type';
    const stateKey = 'service_state';

    return (
        <>
            <Head title="Postventa" />
            <PageShell className="projects-workspace">
                <PageHeader
                    title="Postventa"
                    description="Gestiona el mantenimiento y seguimiento de tus instalaciones."
                    breadcrumbs={[
                        { title: 'Proyectos', href: '/execution' },
                        {
                            title: 'Postventa',
                            href: '/after-sales?view=installations',
                        },
                    ]}
                    secondaryNavigation={null}
                    actions={
                        <>
                            <Button asChild variant="outline">
                                <Link href="/maintenance">
                                    Planes de mantenimiento
                                </Link>
                            </Button>
                            <Button asChild variant="outline">
                                <Link href="/after-sales">
                                    Gestionar incidencias
                                </Link>
                            </Button>
                        </>
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
                            aria-label="Buscar instalaciones de postventa"
                            placeholder="Buscar instalaciones..."
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                        />
                    </label>
                    <Button variant="outline" type="submit">
                        Buscar
                    </Button>
                    <label className="projects-filter">
                        <span>Estado</span>
                        <select
                            value={filters.service_state ?? ''}
                            onChange={(e) =>
                                update({ [stateKey]: e.target.value })
                            }
                        >
                            {states.map(([key, label]) => (
                                <option key={key} value={key}>
                                    {label}
                                </option>
                            ))}
                        </select>
                    </label>
                    <label className="projects-filter">
                        <span>Tipo</span>
                        <select
                            value={filters.service_type ?? ''}
                            onChange={(e) =>
                                update({ [typeKey]: e.target.value })
                            }
                        >
                            <option value="">Todos</option>
                            {Object.entries(serviceLabels).map(
                                ([key, label]) => (
                                    <option key={key} value={key}>
                                        {translate(label, locale)}
                                    </option>
                                ),
                            )}
                        </select>
                    </label>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() =>
                            router.get('/after-sales', {
                                view: 'installations',
                            })
                        }
                    >
                        Limpiar filtros
                    </Button>
                </form>
                <label className="projects-filter">
                    <span>Ordenar por</span>
                    <select
                        value={filters.sort ?? 'completed_date'}
                        onChange={(e) => update({ sort: e.target.value })}
                    >
                        <option value="completed_date">Finalización</option>
                        <option value="created_at">Fecha de creación</option>
                        <option value="site_name">Nombre</option>
                    </select>
                </label>
                <Sheet>
                    <SheetTrigger asChild>
                        <Button variant="outline">
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
                                <label className="projects-filter">
                                    <span>Estado</span>
                                    <select
                                        value={filters.service_state ?? ''}
                                        onChange={(e) =>
                                            update({
                                                [stateKey]: e.target.value,
                                            })
                                        }
                                    >
                                        {states.map(([key, label]) => (
                                            <option key={key} value={key}>
                                                {label}
                                            </option>
                                        ))}
                                    </select>
                                </label>
                                <label className="projects-filter">
                                    <span>Tipo</span>
                                    <select
                                        value={filters.service_type ?? ''}
                                        onChange={(e) =>
                                            update({
                                                [typeKey]: e.target.value,
                                            })
                                        }
                                    >
                                        <option value="">Todos</option>
                                        {Object.entries(serviceLabels).map(
                                            ([key, label]) => (
                                                <option key={key} value={key}>
                                                    {translate(label, locale)}
                                                </option>
                                            ),
                                        )}
                                    </select>
                                </label>
                            </div>
                            <label>
                                Inicio desde
                                <input
                                    type="date"
                                    value={filters.date_from ?? ''}
                                    onChange={(e) =>
                                        update({ date_from: e.target.value })
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
                            <label>
                                Dirección
                                <select
                                    value={filters.direction ?? 'desc'}
                                    onChange={(e) =>
                                        update({ direction: e.target.value })
                                    }
                                >
                                    <option value="desc">Descendente</option>
                                    <option value="asc">Ascendente</option>
                                </select>
                            </label>
                        </div>
                    </SheetContent>
                </Sheet>
                <nav
                    className="projects-quick-filters"
                    aria-label="Estados de postventa"
                >
                    {states.slice(0, 4).map(([key, label]) => (
                        <Button
                            key={key}
                            variant="outline"
                            aria-pressed={(filters.service_state ?? '') === key}
                            onClick={() => update({ service_state: key })}
                        >
                            {label} <span>{counts[key || totalKey] ?? 0}</span>
                        </Button>
                    ))}
                </nav>
                {loading && <Skeleton className="h-24 w-full" />}
                <section className="projects-panel">
                    <div className="projects-table-scroll">
                        <table>
                            <thead>
                                <tr>
                                    <th>Instalación</th>
                                    <th>Cliente</th>
                                    <th>Potencia FV</th>
                                    <th>Estado</th>
                                    <th>Última revisión</th>
                                    <th>Próxima revisión</th>
                                </tr>
                            </thead>
                            <tbody>
                                {items.map((i) => (
                                    <tr key={i.id}>
                                        <td>
                                            <Link
                                                href={projectEntityUrl(
                                                    '/installations/' + i.id,
                                                    context,
                                                )}
                                            >
                                                {i.project_reference ||
                                                    i.site_name ||
                                                    'Instalación ' + i.id}
                                            </Link>
                                            {i.project_reference && (
                                                <Link
                                                    className="projects-small-link"
                                                    href={
                                                        '/execution/projects/' +
                                                        i.id
                                                    }
                                                >
                                                    Ver proyecto
                                                </Link>
                                            )}
                                        </td>
                                        <td>{customerName(i)}</td>
                                        <td>{power(i.system_power_kw)}</td>
                                        <td>
                                            <span className="projects-status">
                                                {state(i)}
                                            </span>
                                            {i.open_tickets ? (
                                                <small>
                                                    {i.open_tickets} incidencias
                                                </small>
                                            ) : null}
                                        </td>
                                        <td>{date(i.last_review)}</td>
                                        <td>{date(i.next_review)}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <div className="projects-mobile-list">
                        {items.map((i) => (
                            <Link
                                className="projects-mobile-card"
                                href={projectEntityUrl(
                                    '/installations/' + i.id,
                                    context,
                                )}
                                key={i.id}
                            >
                                <strong>
                                    {i.project_reference ||
                                        i.site_name ||
                                        'Instalación ' + i.id}
                                </strong>
                                <span className="projects-status">
                                    {state(i)}
                                </span>
                                <p>
                                    {customerName(i)} ·{' '}
                                    {power(i.system_power_kw)}
                                </p>
                                <small>
                                    Próxima revisión: {date(i.next_review)}
                                </small>
                            </Link>
                        ))}
                    </div>
                    {!items.length && (
                        <p className="projects-empty">
                            No hay instalaciones finalizadas para estos filtros.
                        </p>
                    )}
                    <div className="projects-pagination">
                        <PaginationBar
                            links={pagination.links}
                            total={pagination.total}
                            noun="instalaciones"
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
                <div className="projects-after-sales-grid">
                    <section className="projects-panel">
                        <h2>Revisiones pendientes y próximas</h2>
                        {upcoming.length ? (
                            upcoming.map((p) => (
                                <Link
                                    className="projects-event"
                                    key={p.id}
                                    href={
                                        '/maintenance?installation=' +
                                        p.installation_id
                                    }
                                >
                                    <strong>{p.name}</strong>
                                    <span>
                                        {p.installation.site_name ||
                                            p.installation.project_reference}
                                    </span>
                                    <time>{date(p.next_due_on)}</time>
                                </Link>
                            ))
                        ) : (
                            <p className="projects-empty">
                                No hay revisiones programadas.
                            </p>
                        )}
                    </section>
                    <section className="projects-panel">
                        <h2>Incidencias abiertas</h2>
                        {tickets.length ? (
                            tickets.map((t) => (
                                <Link
                                    className="projects-event"
                                    key={t.id}
                                    href={
                                        '/after-sales?installation=' +
                                        t.installation_id
                                    }
                                >
                                    <strong>
                                        {t.number} · {t.title}
                                    </strong>
                                    <span>
                                        {t.installation.site_name} ·{' '}
                                        {t.priority}
                                    </span>
                                    <time>{date(t.created_at)}</time>
                                </Link>
                            ))
                        ) : (
                            <p className="projects-empty">
                                No hay incidencias abiertas.
                            </p>
                        )}
                    </section>
                </div>
            </PageShell>
        </>
    );
}

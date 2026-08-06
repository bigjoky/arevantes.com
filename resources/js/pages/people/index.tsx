import { Form, Head, Link } from '@inertiajs/react';
import {
    Archive,
    KeyRound,
    Mail,
    Phone,
    RotateCcw,
    Search,
    Wrench,
    X,
} from 'lucide-react';
import { PageHeader, PageShell } from '@/components/page-shell';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';

type Person = {
    id: number;
    category: 'customer' | 'staff';
    customer_type: string | null;
    first_name: string;
    last_name: string | null;
    company_name: string | null;
    staff_role: string | null;
    phone: string | null;
    email: string | null;
    city: string | null;
    country: string | null;
    installations_count: number | null;
    access?: { email: string; role: string } | null;
};

type PeopleIndexProps = {
    people:
        | Person[]
        | {
              data: Person[];
              links: {
                  url: string | null;
                  label: string;
                  active: boolean;
              }[];
              total: number;
          };
    filters?: {
        search?: string;
        customer_type?: string;
        lead_source?: string;
        archived?: string;
    };
    leadSources?: string[];
    basePath?: string;
    title?: string;
    description?: string;
    createTitle?: string;
};

export default function PeopleIndex({
    people,
    filters = {},
    leadSources = [],
    basePath = '/people',
    title = 'People',
    description = 'Customer profiles for residential, business, community, and public-sector work.',
    createTitle = 'Add customer',
}: PeopleIndexProps) {
    const isStaff = basePath === '/staff';
    const peopleData = Array.isArray(people) ? people : people.data;
    const pagination = Array.isArray(people) ? null : people;

    return (
        <>
            <Head title={title} />

            <PageShell>
                <PageHeader
                    title={title}
                    description={description}
                    actions={
                        <>
                            {!isStaff && (
                                <Button variant="outline" asChild>
                                    <Link
                                        href={
                                            filters.archived
                                                ? basePath
                                                : `${basePath}?archived=1`
                                        }
                                    >
                                        {filters.archived ? (
                                            <RotateCcw />
                                        ) : (
                                            <Archive />
                                        )}
                                        {filters.archived
                                            ? 'Clientes activos'
                                            : 'Archivados'}
                                    </Link>
                                </Button>
                            )}
                            <Button variant="outline" asChild>
                                <Link href="/dashboard">Back to dashboard</Link>
                            </Button>
                            <Button asChild>
                                <Link href={`${basePath}/create`}>
                                    {createTitle}
                                </Link>
                            </Button>
                        </>
                    }
                />
                <Card className="rounded-lg py-0">
                    <CardContent className="p-4">
                        {!isStaff && (
                            <Form
                                action={basePath}
                                method="get"
                                className="mb-5 grid gap-3 lg:grid-cols-[minmax(16rem,1fr)_13rem_13rem_auto]"
                            >
                                {filters.archived && (
                                    <input
                                        type="hidden"
                                        name="archived"
                                        value="1"
                                    />
                                )}
                                <div className="relative">
                                    <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                                    <input
                                        name="search"
                                        defaultValue={filters.search}
                                        placeholder="Buscar por nombre, empresa, email, teléfono o NIF"
                                        className="h-9 w-full rounded-md border border-input bg-background pr-3 pl-9 text-sm shadow-xs"
                                    />
                                </div>
                                <select
                                    name="customer_type"
                                    defaultValue={filters.customer_type || ''}
                                    className="h-9 rounded-md border border-input bg-background px-3 text-sm shadow-xs"
                                >
                                    <option value="">Todos los tipos</option>
                                    <option value="residential">
                                        Residencial
                                    </option>
                                    <option value="business">Empresa</option>
                                    <option value="community">Comunidad</option>
                                    <option value="public_sector">
                                        Sector público
                                    </option>
                                </select>
                                <select
                                    name="lead_source"
                                    defaultValue={filters.lead_source || ''}
                                    className="h-9 rounded-md border border-input bg-background px-3 text-sm shadow-xs"
                                >
                                    <option value="">Todos los orígenes</option>
                                    {leadSources.map((source) => (
                                        <option key={source} value={source}>
                                            {source}
                                        </option>
                                    ))}
                                </select>
                                <div className="flex gap-2">
                                    <Button variant="outline">
                                        <Search /> Filtrar
                                    </Button>
                                    {Object.values(filters).some(Boolean) && (
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            asChild
                                        >
                                            <Link href={basePath}>
                                                <X /> Limpiar
                                            </Link>
                                        </Button>
                                    )}
                                </div>
                            </Form>
                        )}
                        {peopleData.length > 0 ? (
                            <div className="overflow-hidden rounded-md border">
                                <div className="divide-y">
                                    {peopleData.map((person) => (
                                        <div
                                            key={person.id}
                                            className="grid gap-3 p-4 transition-colors hover:bg-muted/50 lg:grid-cols-[1fr_auto]"
                                        >
                                            <div>
                                                <div className="flex flex-wrap items-center gap-2">
                                                    {filters.archived ? (
                                                        <span className="font-medium">
                                                            {person.company_name ||
                                                                `${person.first_name} ${person.last_name || ''}`}
                                                        </span>
                                                    ) : (
                                                        <Link
                                                            href={`${basePath}/${person.id}`}
                                                            className="font-medium hover:underline"
                                                        >
                                                            {person.company_name ||
                                                                `${person.first_name} ${person.last_name || ''}`}
                                                        </Link>
                                                    )}
                                                    <Badge
                                                        variant={
                                                            isStaff
                                                                ? 'secondary'
                                                                : 'outline'
                                                        }
                                                    >
                                                        {isStaff
                                                            ? 'staff'
                                                            : 'customer'}
                                                    </Badge>
                                                    {!isStaff &&
                                                        person.customer_type && (
                                                            <Badge variant="outline">
                                                                {
                                                                    person.customer_type
                                                                }
                                                            </Badge>
                                                        )}
                                                    {isStaff && (
                                                        <Badge
                                                            variant={
                                                                person.access
                                                                    ? 'outline'
                                                                    : 'secondary'
                                                            }
                                                        >
                                                            <KeyRound className="size-3" />
                                                            {person.access
                                                                ? person.access
                                                                      .role
                                                                : 'No CRM access'}
                                                        </Badge>
                                                    )}
                                                </div>
                                                <div className="mt-1 text-sm text-muted-foreground">
                                                    {person.staff_role ||
                                                        [
                                                            person.city,
                                                            person.country,
                                                        ]
                                                            .filter(Boolean)
                                                            .join(', ') ||
                                                        'No location'}
                                                </div>
                                            </div>

                                            <div className="grid gap-1 text-sm text-muted-foreground lg:text-right">
                                                {filters.archived && (
                                                    <Form
                                                        action={`${basePath}/${person.id}/restore`}
                                                        method="post"
                                                    >
                                                        {({ processing }) => (
                                                            <Button
                                                                size="sm"
                                                                variant="outline"
                                                                disabled={
                                                                    processing
                                                                }
                                                            >
                                                                <RotateCcw />{' '}
                                                                Restaurar
                                                            </Button>
                                                        )}
                                                    </Form>
                                                )}
                                                <span className="inline-flex items-center gap-2 lg:justify-end">
                                                    <Mail className="size-4" />
                                                    {person.email || 'No email'}
                                                </span>
                                                <span className="inline-flex items-center gap-2 lg:justify-end">
                                                    <Phone className="size-4" />
                                                    {person.phone || 'No phone'}
                                                </span>
                                                {!isStaff && (
                                                    <span className="inline-flex items-center gap-2 lg:justify-end">
                                                        <Wrench className="size-4" />
                                                        {person.installations_count ??
                                                            0}{' '}
                                                        installations
                                                    </span>
                                                )}
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        ) : (
                            <div className="rounded-md border border-dashed p-6 text-sm text-muted-foreground">
                                {isStaff
                                    ? 'No staff profiles have been added yet.'
                                    : 'No hay clientes que coincidan con los filtros.'}
                            </div>
                        )}
                        {pagination && pagination.links.length > 3 && (
                            <div className="mt-4 flex flex-wrap items-center justify-between gap-3 text-sm">
                                <span className="text-muted-foreground">
                                    {pagination.total} clientes
                                </span>
                                <div className="flex flex-wrap gap-1">
                                    {pagination.links.map(
                                        (link, index) =>
                                            link.url && (
                                                <Button
                                                    key={index}
                                                    size="sm"
                                                    variant={
                                                        link.active
                                                            ? 'default'
                                                            : 'outline'
                                                    }
                                                    asChild
                                                >
                                                    <Link
                                                        href={link.url}
                                                        preserveScroll
                                                        dangerouslySetInnerHTML={{
                                                            __html: link.label,
                                                        }}
                                                    />
                                                </Button>
                                            ),
                                    )}
                                </div>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </PageShell>
        </>
    );
}

PeopleIndex.layout = {
    breadcrumbs: [{ title: 'People', href: '/people' }],
};

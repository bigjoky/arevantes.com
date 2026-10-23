export type CustomerRow = {
    id: number;
    name: string;
    tax_id: string | null;
    customer_type: string | null;
    lifecycle_status: string | null;
    location: string;
    opportunities_count: number;
    quotes_count: number | null;
    volume: number | null;
    last_activity_at: string | null;
    email: string | null;
    phone: string | null;
};
export type Pagination<T> = {
    data: T[];
    total: number;
    from?: number | null;
    to?: number | null;
    links: { url: string | null; label: string; active: boolean }[];
};
export type Domain =
    | 'opportunities'
    | 'quotes'
    | 'projects'
    | 'installations'
    | 'invoices'
    | 'documents'
    | 'activity';
export type RelationRow = {
    id: number | string;
    title: string;
    reference?: string;
    subtitle?: string | null;
    status?: string | null;
    amount?: number;
    date: string | null;
    href: string | null;
};
export type CustomerWorkspace = {
    possibleDuplicates: { id: number; name: string }[];
    tab: 'summary' | Domain;
    permissions: Record<Domain, boolean>;
    domains: Partial<Record<Domain, Pagination<RelationRow> | null>>;
    financial: { billed: number; outstanding: number } | null;
};
export type CustomerFilters = Record<string, string>;
export type CustomersProps = {
    people: Pagination<CustomerRow>;
    filters?: CustomerFilters;
    provinces: string[];
    customerPermissions: Record<Domain, boolean>;
    savedFilters: { id: number; name: string; filters: CustomerFilters }[];
    leadSources: string[];
    tags: { id: number; name: string; color: string }[];
};
export const customerTypes: Record<string, string> = {
    residential: 'Particular',
    business: 'Empresa',
    community: 'Comunidad',
    public_sector: 'Administración pública',
};
export const customerStatuses: Record<string, string> = {
    lead: 'Potencial',
    active: 'Cliente',
    inactive: 'Inactivo',
    former: 'Antiguo cliente',
};
export const domainLabels: Record<Domain, string> = {
    opportunities: 'Oportunidades',
    quotes: 'Presupuestos',
    projects: 'Proyectos',
    installations: 'Instalaciones',
    invoices: 'Facturas',
    documents: 'Documentos',
    activity: 'Actividad',
};
export const money = (value: number) =>
    new Intl.NumberFormat('es-ES', {
        style: 'currency',
        currency: 'EUR',
    }).format(value);
export const date = (value?: string | null) =>
    value
        ? new Date(value).toLocaleDateString('es-ES', {
              day: '2-digit',
              month: '2-digit',
              year: 'numeric',
          })
        : '—';
export function customerUrl(
    filters: CustomerFilters,
    id?: number,
    tab?: string,
) {
    const params = new URLSearchParams(
        Object.entries(filters).filter(
            ([, value]) => value !== '' && value != null,
        ),
    );

    if (tab && tab !== 'summary') {
        params.set('tab', tab);
    }

    return `/people${id ? `/${id}` : ''}${params.size ? `?${params}` : ''}`;
}

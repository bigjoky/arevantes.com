export type QuoteFilters = Record<string, string>;
export type QuoteListRow = {
    id: number;
    number: string;
    version: number;
    status: string;
    effective_status: string;
    approval_status: string;
    service_type: string | null;
    issue_date: string;
    valid_until: string | null;
    amount: string;
    customer: {
        id: number;
        first_name: string;
        last_name: string | null;
        company_name: string | null;
    } | null;
    creator: { id: number; name: string } | null;
    opportunity: { id: number; title: string; code: string } | null;
};
export const statusLabels: Record<string, string> = {
    draft: 'Borrador',
    sent: 'Enviado',
    accepted: 'Aceptado',
    rejected: 'Rechazado',
    expired: 'Caducado',
};
export const quoteMoney = (n: string | number) =>
    new Intl.NumberFormat('es-ES', {
        style: 'currency',
        currency: 'EUR',
    }).format(Number(n));
export const quoteDate = (s?: string | null) =>
    s
        ? new Date(s).toLocaleDateString('es-ES', {
              day: '2-digit',
              month: '2-digit',
              year: 'numeric',
          })
        : '—';
export function quoteUrl(filters: QuoteFilters, id?: number, tab?: string) {
    const params = new URLSearchParams(
        Object.entries(filters).filter(
            ([, value]) => value !== '' && value != null,
        ),
    );
    params.delete('tab');

    if (tab && tab !== 'summary') {
        params.set('tab', tab);
    }

    return `/quotes${id ? `/${id}` : ''}${params.size ? `?${params}` : ''}`;
}

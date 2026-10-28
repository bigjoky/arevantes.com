import '../../../css/projects-mobile.css';
import { quoteDate, quoteMoney } from '@/components/quotes/types';
export const money = quoteMoney;
export const date = quoteDate;
export type Filters = Record<string, string>;
export type Permissions = {
    manage: boolean;
    quotes: boolean;
    customer: boolean;
    planning: boolean;
    finance: boolean;
};
export type Pagination = {
    total: number;
    from: number | null;
    to: number | null;
    links: { url: string | null; label: string; active: boolean }[];
};
export type InstallationRow = {
    id: number;
    person_id: number | null;
    project_reference: string | null;
    site_name: string | null;
    service_type: string;
    status: string;
    phase: string | null;
    system_power_kw: string | null;
    customer_name: string;
    customer_company: string | null;
    technician_name: string | null;
    commercial_name?: string | null;
    site_address_line_1: string | null;
    site_city: string | null;
    site_province_state: string | null;
    survey_date: string | null;
    scheduled_date: string | null;
    completed_date: string | null;
    site_notes?: string | null;
    technical_notes?: string | null;
    service_state?: string;
    open_tickets?: number;
    has_plan?: boolean;
    next_review?: string | null;
    last_review?: string | null;
    people?: { id: number; name: string; role: string; is_primary: boolean }[];
};
export const phaseLabels: Record<string, string> = {
    preparation: 'En preparación',
    execution: 'En ejecución',
    completed: 'Finalizados',
    after_sales: 'En postventa',
    cancelled: 'Cancelados',
    other: 'Otros estados',
};
export const phaseColors: Record<string, string> = {
    preparation: '#ffbb45',
    execution: '#1683ff',
    completed: '#009266',
    after_sales: '#9141de',
    cancelled: '#ed727c',
    other: '#a5afbe',
};
export const stateLabels: Record<string, string> = {
    lead: 'Pendiente',
    survey_booked: 'Visita programada',
    quoted: 'Presupuestado',
    approved: 'Aprobado',
    scheduled: 'Programado',
    in_progress: 'En ejecución',
    completed: 'Finalizado',
    cancelled: 'Cancelado',
};
export function projectUrl(
    filters: Filters,
    id?: number,
    tab?: string,
    mode: 'projects' | 'installations' = 'projects',
) {
    const params = new URLSearchParams(
        Object.entries(filters).filter(([, v]) => v !== '' && v != null),
    );
    params.delete('tab');
    params.delete('section_page');

    if (tab && tab !== 'summary') {
        params.set('tab', tab);
    }

    const base = mode === 'projects' ? '/execution/projects' : '/installations';

    return base + (id ? '/' + id : '') + (params.size ? '?' + params : '');
}
export const customerName = (row: InstallationRow) =>
    row.customer_company || row.customer_name || '—';
export const power = (value?: string | number | null) =>
    value == null
        ? '—'
        : new Intl.NumberFormat('es-ES', { maximumFractionDigits: 2 }).format(
              Number(value),
          ) + ' kW';

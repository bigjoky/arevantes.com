import { Link } from '@inertiajs/react';
import { Cell, Pie, PieChart, ResponsiveContainer, Tooltip } from 'recharts';
import { Empty, Pagination, date } from '@/components/billing/workspace';
import type { Page } from '@/components/billing/workspace';
export type Employee = {
    id: number;
    name: string;
    job: string | null;
    department: string | null;
    department_id: number | null;
    type: string | null;
    status: string | null;
    number: string | null;
    start: string | null;
    end: string | null;
    probation: string | null;
    location: string | null;
    href: string;
};
export type Document = {
    id: number;
    title: string;
    description: string | null;
    category: string;
    visibility: string;
    status: string;
    version: number;
    revision: number;
    expires: string | null;
    created: string;
    updated: string;
    mime: string | null;
    size: number | null;
    href: string;
};
export type Expiry = {
    id: string;
    title: string;
    type: string;
    date: string;
    href: string;
};
export const categories: Record<string, string> = {
    general: 'General',
    legal: 'Legal y societario',
    tax: 'Fiscal',
    insurance: 'Seguros',
    fleet: 'Flota',
    prevention: 'Prevención',
    contracts: 'Contratos',
    other: 'Otros',
};
export const labels: Record<string, string> = {
    active: 'Activo',
    on_leave: 'Ausencia',
    ended: 'Finalizado',
    archived: 'Archivado',
    permanent: 'Indefinido',
    temporary: 'Temporal',
    internship: 'Prácticas',
    contractor: 'Colaborador externo',
    contract: 'Contrato',
    pending: 'Pendiente',
    approved: 'Aprobada',
    rejected: 'Rechazada',
    cancelled: 'Cancelada',
    employee: 'Compartido con empleado',
    restricted: 'Restringido',
    staff: 'Administración de personal',
    certificate: 'Certificado',
    identity: 'Identificación',
    payroll: 'Nómina',
    prevention: 'Prevención',
    other: 'Otros',
    created: 'Creado',
    updated: 'Actualizado',
    version_created: 'Nueva versión',
};
export const label = (value: string | null) =>
    value ? (labels[value] ?? categories[value] ?? value) : 'Sin registrar';
export const size = (bytes: number | null) =>
    bytes === null
        ? '—'
        : new Intl.NumberFormat('es-ES', { maximumFractionDigits: 1 }).format(
              bytes / 1024,
          ) + ' KB';
export function State({ value }: { value: string | null }) {
    return (
        <span className={`company-state ${value ?? 'missing'}`}>
            {label(value)}
        </span>
    );
}
export function Distribution({
    rows,
}: {
    rows: { name: string; total: number }[];
}) {
    const colors = ['#008b63', '#3e9ef7', '#ffb26b', '#88c9fa', '#b7a4ef'];

    return rows.length ? (
        <div className="company-distribution">
            <div className="company-donut">
                <ResponsiveContainer>
                    <PieChart>
                        <Pie
                            data={rows}
                            dataKey="total"
                            nameKey="name"
                            innerRadius="65%"
                            outerRadius="90%"
                        >
                            {rows.map((r, i) => (
                                <Cell
                                    key={r.name}
                                    fill={colors[i % colors.length]}
                                />
                            ))}
                        </Pie>
                        <Tooltip formatter={(v) => Number(v)} />
                    </PieChart>
                </ResponsiveContainer>
                <strong>{rows.reduce((n, r) => n + r.total, 0)}</strong>
            </div>
            <ul>
                {rows.map((r, i) => (
                    <li key={r.name}>
                        <i style={{ background: colors[i % colors.length] }} />
                        <span>{label(r.name)}</span>
                        <b>{r.total}</b>
                    </li>
                ))}
            </ul>
        </div>
    ) : (
        <Empty>No hay datos registrados para esta distribución.</Empty>
    );
}
export function EmployeeList({ page }: { page: Page<Employee> }) {
    return (
        <>
            <div className="company-table">
                <table>
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Puesto</th>
                            <th>Departamento</th>
                            <th>Tipo de contrato</th>
                            <th>Fecha de incorporación</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        {page.data.map((e) => (
                            <tr key={e.id}>
                                <td>
                                    <Link href={e.href}>
                                        <span className="company-avatar">
                                            {e.name
                                                .split(' ')
                                                .slice(0, 2)
                                                .map((n) => n[0])
                                                .join('')}
                                        </span>
                                        {e.name}
                                    </Link>
                                </td>
                                <td>{e.job ?? '—'}</td>
                                <td>{e.department ?? '—'}</td>
                                <td>{label(e.type)}</td>
                                <td>{date(e.start)}</td>
                                <td>
                                    <State value={e.status} />
                                </td>
                                <td>
                                    <Link
                                        href={e.href}
                                        aria-label="Ver empleado"
                                    >
                                        ···
                                    </Link>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            <div className="company-mobile-list">
                {page.data.map((e) => (
                    <Link
                        href={e.href}
                        key={e.id}
                        className="company-mobile-row"
                    >
                        <strong>{e.name}</strong>
                        <span>
                            {e.job ?? 'Puesto sin registrar'} ·{' '}
                            {e.department ?? 'Departamento sin registrar'}
                        </span>
                        <span>
                            <State value={e.status} />
                            {label(e.type)}
                        </span>
                    </Link>
                ))}
            </div>
            {!page.total && <Empty>No hay empleados para estos filtros.</Empty>}
            <Pagination page={page} noun="fichas de personal" />
        </>
    );
}
export function DocumentList({ page }: { page: Page<Document> }) {
    return (
        <>
            <div className="company-table">
                <table>
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Categoría</th>
                            <th>Tipo</th>
                            <th>Fecha</th>
                            <th>Tamaño</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        {page.data.map((d) => (
                            <tr key={d.id}>
                                <td>
                                    <Link href={d.href}>{d.title}</Link>
                                </td>
                                <td>{categories[d.category] ?? d.category}</td>
                                <td>{d.mime ?? '—'}</td>
                                <td>{date(d.updated)}</td>
                                <td>{size(d.size)}</td>
                                <td>
                                    <State value={d.status} />
                                </td>
                                <td>
                                    <Link
                                        href={d.href}
                                        aria-label="Ver documento"
                                    >
                                        ···
                                    </Link>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            <div className="company-mobile-list">
                {page.data.map((d) => (
                    <Link
                        href={d.href}
                        key={d.id}
                        className="company-mobile-row"
                    >
                        <strong>{d.title}</strong>
                        <span>
                            {categories[d.category]} · {size(d.size)}
                        </span>
                        <span>
                            {date(d.updated)}
                            <State value={d.status} />
                        </span>
                    </Link>
                ))}
            </div>
            {!page.total && (
                <Empty>No hay documentos autorizados para estos filtros.</Empty>
            )}
            <Pagination page={page} noun="documentos" />
        </>
    );
}

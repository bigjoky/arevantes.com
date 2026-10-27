import { Form, Head, Link } from '@inertiajs/react';
import {
    CalendarClock,
    FileText,
    Search,
    Settings2,
    Users,
} from 'lucide-react';
import { useState } from 'react';
import { Empty, Panel, Pagination, date } from '@/components/billing/workspace';
import type { Page } from '@/components/billing/workspace';
import BrandLogo, { useCompanyBrand } from '@/components/brand-logo';
import { PageHeader, PageShell, PageTabs } from '@/components/page-shell';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    DocumentList,
    Distribution,
    EmployeeList,
    categories,
    label,
    State,
} from './common';
import type { Document, Employee, Expiry } from './common';
import { UploadDialog } from './document';
import { NewEmployee } from './employee';
import '@/../css/company.css';
type Capability = {
    staff: boolean;
    documents: boolean;
    upload: boolean;
    settings: boolean;
    editCompany: boolean;
    users: boolean;
    audit: boolean;
    backups: boolean;
    sensitive: boolean;
    folders: boolean;
    trainingRegister: boolean;
    multiCompany: boolean;
};
type Company = Record<string, string | null>;
type Props = {
    area: 'dashboard' | 'staff' | 'documents' | 'settings';
    filters: Record<string, string | number>;
    company: Company | null;
    metrics: {
        employees: number | null;
        documents: number | null;
        configuration: string;
        renewals: number | null;
    };
    distributions: {
        departments: { name: string; total: number }[];
        contracts: { name: string; total: number }[];
    };
    staff: Page<Employee> | null;
    documents: Page<Document> | null;
    departments: { id: number; name: string; is_active: boolean }[];
    recentDocuments: Document[];
    categoryCounts: Record<string, number>;
    expiries: Expiry[];
    tasks: {
        id: number;
        title: string;
        due: string | null;
        href: string;
        status: string;
    }[];
    capabilities: Capability;
    leave: Page<{
        id: number;
        name: string;
        start: string;
        end: string;
        status: string;
        href: string;
    }> | null;
    certificates: Page<{
        id: number;
        title: string;
        name: string;
        expires: string | null;
        href: string;
    }> | null;
};
const titles = {
    dashboard: 'Empresa',
    staff: 'Personal',
    documents: 'Documentos',
    settings: 'Configuración',
};
const descriptions = {
    dashboard: 'Gestiona tu equipo, documentos y configuración de la empresa.',
    staff: 'Gestiona tu equipo, contratos, formación y documentación laboral.',
    documents: 'Organiza y gestiona toda la documentación de la empresa.',
    settings: 'Gestiona los datos, parámetros y ajustes de tu empresa.',
};
export default function CompanyWorkspace(p: Props) {
    const { area, filters: f, capabilities: c } = p;
    const base = area === 'dashboard' ? '/company' : `/company/${area}`;
    const selected = String(
        f.tab ?? (area === 'staff' ? 'employees' : 'company'),
    );
    const href = (values: Record<string, string | number>) =>
        base +
        '?' +
        new URLSearchParams(
            Object.entries({ ...f, ...values })
                .filter(([, v]) => v !== null && v !== undefined && v !== '')
                .map(([k, v]) => [k, String(v)]),
        );
    const tabs = (values: [string, string][]) => (
        <PageTabs>
            {values.map(([id, title]) => (
                <Link
                    key={id}
                    href={href({ tab: id, page: 1 })}
                    className={selected === id ? 'active' : ''}
                >
                    {title}
                </Link>
            ))}
        </PageTabs>
    );

    return (
        <>
            <Head title={titles[area]} />
            <PageShell mode="full" className="company-workspace">
                <PageHeader
                    title={titles[area]}
                    description={descriptions[area]}
                    secondaryNavigation={null}
                    metadata={p.company?.legal_name ?? 'Empresa sin configurar'}
                    actions={
                        <>
                            {area === 'staff' && c.staff && (
                                <NewEmployee departments={p.departments} />
                            )}
                            {area === 'documents' && c.upload && (
                                <UploadDialog />
                            )}
                        </>
                    }
                />
                {area === 'dashboard' && (
                    <>
                        <div className="company-kpis">
                            {[
                                [
                                    Users,
                                    'Empleados activos',
                                    p.metrics.employees,
                                ],
                                [
                                    FileText,
                                    'Documentos de empresa',
                                    p.metrics.documents,
                                ],
                                [
                                    Settings2,
                                    'Estado de configuración',
                                    p.metrics.configuration === 'registered'
                                        ? 'Datos registrados'
                                        : 'Sin configurar',
                                ],
                                [
                                    CalendarClock,
                                    'Próximas renovaciones',
                                    p.metrics.renewals,
                                ],
                            ].map(([Icon, title, value]) => {
                                const I = Icon as typeof Users;

                                return (
                                    <Panel
                                        key={String(title)}
                                        title={String(title)}
                                        className="company-kpi"
                                    >
                                        <I />
                                        <strong>
                                            {value === null
                                                ? 'Acceso restringido'
                                                : String(value)}
                                        </strong>
                                        <small>
                                            {title === 'Próximas renovaciones'
                                                ? 'Próximos 30 días'
                                                : title === 'Empleados activos'
                                                  ? 'Con ficha laboral activa'
                                                  : title ===
                                                      'Documentos de empresa'
                                                    ? 'Archivo corporativo autorizado'
                                                    : 'Sin porcentaje estimado'}
                                        </small>
                                    </Panel>
                                );
                            })}
                        </div>
                        <div className="company-grid">
                            <Panel
                                title="Equipo por departamento"
                                action={
                                    c.staff && (
                                        <Link href="/company/staff">
                                            Ver personal →
                                        </Link>
                                    )
                                }
                            >
                                {c.staff ? (
                                    <Distribution
                                        rows={p.distributions.departments}
                                    />
                                ) : (
                                    <Empty unavailable>
                                        Acceso restringido a la información de
                                        personal.
                                    </Empty>
                                )}
                            </Panel>
                            <Panel
                                title="Documentos recientes"
                                action={
                                    c.documents && (
                                        <Link href="/company/documents">
                                            Ver todos →
                                        </Link>
                                    )
                                }
                            >
                                {c.documents ? (
                                    <div className="company-list">
                                        {p.recentDocuments.map((d) => (
                                            <Link key={d.id} href={d.href}>
                                                <FileText />
                                                <span>
                                                    <strong>{d.title}</strong>
                                                    <small>
                                                        {categories[d.category]}{' '}
                                                        · {date(d.updated)}
                                                    </small>
                                                </span>
                                                <State value={d.status} />
                                            </Link>
                                        ))}
                                        {!p.recentDocuments.length && (
                                            <Empty>
                                                No hay documentos corporativos
                                                autorizados.
                                            </Empty>
                                        )}
                                    </div>
                                ) : (
                                    <Empty unavailable>
                                        Acceso restringido al archivo
                                        corporativo.
                                    </Empty>
                                )}
                            </Panel>
                        </div>
                        <div className="company-grid">
                            <Panel title="Próximas renovaciones y vencimientos">
                                <Expiries rows={p.expiries} />
                            </Panel>
                            <Panel title="Tareas pendientes">
                                {c.staff ? (
                                    <div className="company-list">
                                        {p.tasks.map((t) => (
                                            <Link href={t.href} key={t.id}>
                                                <span>{t.title}</span>
                                                <small>
                                                    {t.due
                                                        ? date(t.due)
                                                        : 'Sin fecha'}
                                                </small>
                                            </Link>
                                        ))}
                                        {!p.tasks.length && (
                                            <Empty>
                                                No hay tareas administrativas
                                                pendientes.
                                            </Empty>
                                        )}
                                    </div>
                                ) : (
                                    <Empty unavailable>
                                        Acceso restringido a las tareas de
                                        personal.
                                    </Empty>
                                )}
                            </Panel>
                        </div>
                        <p className="company-note">
                            Los registros de personal, las cuentas de acceso y
                            los documentos corporativos se gestionan de forma
                            independiente. Las alertas consultan vencimientos
                            existentes y no crean tareas duplicadas.
                        </p>
                    </>
                )}
                {area === 'staff' && (
                    <>
                        {tabs([
                            ['employees', 'Empleados'],
                            ['positions', 'Puestos'],
                            ['contracts', 'Contratos'],
                            ['training', 'Formación'],
                            ['absences', 'Ausencias'],
                            ['expiry', 'Vencimientos'],
                        ])}
                        {['employees', 'contracts'].includes(selected) && (
                            <>
                                <Filters
                                    area={area}
                                    base={base}
                                    f={f}
                                    departments={p.departments}
                                />
                                <Panel
                                    title={
                                        selected === 'contracts'
                                            ? 'Contratación registrada'
                                            : `${p.staff?.total ?? 0} fichas de personal`
                                    }
                                >
                                    {p.staff && <EmployeeList page={p.staff} />}
                                </Panel>
                            </>
                        )}
                        {selected === 'positions' && (
                            <Panel title="Puestos y departamentos">
                                <Button variant="outline" asChild>
                                    <Link href="/human-resources/departments">
                                        Gestionar departamentos
                                    </Link>
                                </Button>
                                <p className="company-note">
                                    El puesto se registra en cada ficha laboral.
                                    No hay un catálogo independiente de puestos.
                                </p>
                                <div className="company-tags">
                                    {p.departments.map((d) => (
                                        <Link
                                            key={d.id}
                                            href={href({
                                                tab: 'employees',
                                                department: d.id,
                                            })}
                                        >
                                            {d.name} ·{' '}
                                            {d.is_active
                                                ? 'Activo'
                                                : 'Inactivo'}
                                        </Link>
                                    ))}
                                </div>
                                {!p.departments.length && (
                                    <Empty>
                                        No hay departamentos configurados.
                                    </Empty>
                                )}
                            </Panel>
                        )}
                        {selected === 'training' && (
                            <Panel title="Formación y acreditaciones documentales">
                                <p className="company-note">
                                    Documentos de certificados y prevención ya
                                    registrados. La entidad emisora y la fecha
                                    de realización no están disponibles como
                                    formación estructurada.
                                </p>
                                {p.certificates?.data.map((d) => (
                                    <div className="company-record" key={d.id}>
                                        <a href={d.href}>
                                            {d.title} · {d.name}
                                        </a>
                                        <span>{date(d.expires)}</span>
                                    </div>
                                ))}
                                {p.certificates && (
                                    <Pagination
                                        page={p.certificates}
                                        noun="acreditaciones"
                                    />
                                )}
                                {!p.certificates?.total && (
                                    <Empty>
                                        No hay acreditaciones autorizadas para
                                        consultar.
                                    </Empty>
                                )}
                            </Panel>
                        )}
                        {selected === 'absences' && (
                            <Panel title="Ausencias registradas">
                                {c.sensitive && (
                                    <Button variant="outline" asChild>
                                        <Link href="/human-resources/leave">
                                            Gestionar ausencias
                                        </Link>
                                    </Button>
                                )}
                                {p.leave?.data.map((l) => (
                                    <Link
                                        href={l.href}
                                        className="company-record"
                                        key={l.id}
                                    >
                                        <strong>{l.name}</strong>
                                        <span>
                                            {date(l.start)} — {date(l.end)}
                                        </span>
                                        <State value={l.status} />
                                    </Link>
                                ))}
                                {p.leave && (
                                    <Pagination
                                        page={p.leave}
                                        noun="ausencias"
                                    />
                                )}
                                {!p.leave?.total && (
                                    <Empty>No hay ausencias registradas.</Empty>
                                )}
                                <p className="company-note">
                                    No se muestran motivos, diagnósticos ni
                                    adjuntos médicos.
                                </p>
                            </Panel>
                        )}
                        {selected === 'expiry' && (
                            <Panel title="Vencimientos de los próximos 30 días">
                                <Expiries rows={p.expiries} />
                            </Panel>
                        )}
                        {['employees', 'contracts'].includes(selected) && (
                            <div className="company-grid">
                                <Panel title="Distribución por departamento">
                                    <Distribution
                                        rows={p.distributions.departments}
                                    />
                                </Panel>
                                <Panel title="Tipos de contrato">
                                    <Distribution
                                        rows={p.distributions.contracts.map(
                                            (r) => ({
                                                ...r,
                                                name: label(r.name),
                                            }),
                                        )}
                                    />
                                </Panel>
                            </div>
                        )}
                    </>
                )}
                {area === 'documents' && (
                    <>
                        <PageTabs>
                            <Link
                                href={href({ category: '', page: 1 })}
                                className={!f.category ? 'active' : ''}
                            >
                                Todos
                            </Link>
                            {Object.entries(categories).map(([key, title]) => (
                                <Link
                                    href={href({ category: key, page: 1 })}
                                    className={
                                        f.category === key ? 'active' : ''
                                    }
                                    key={key}
                                >
                                    {title}
                                </Link>
                            ))}
                        </PageTabs>
                        <div className="company-doc-layout">
                            <aside className="company-categories">
                                <strong>Categorías</strong>
                                <Link href={href({ category: '', page: 1 })}>
                                    Todos los documentos{' '}
                                    <b>{p.metrics.documents}</b>
                                </Link>
                                {Object.entries(categories).map(
                                    ([key, title]) => (
                                        <Link
                                            key={key}
                                            href={href({
                                                category: key,
                                                page: 1,
                                            })}
                                            className={
                                                f.category === key
                                                    ? 'active'
                                                    : ''
                                            }
                                        >
                                            {title}
                                            <b>{p.categoryCounts[key] ?? 0}</b>
                                        </Link>
                                    ),
                                )}
                            </aside>
                            <div className="company-doc-main">
                                <Filters area={area} base={base} f={f} />
                                <Panel title="Archivo corporativo">
                                    {p.documents && (
                                        <DocumentList page={p.documents} />
                                    )}
                                </Panel>
                            </div>
                        </div>
                        <p className="company-note">
                            Los documentos laborales se consultan en la ficha de
                            personal. Las carpetas y relaciones documentales
                            adicionales quedan pendientes.
                        </p>
                    </>
                )}
                {area === 'settings' && (
                    <>
                        {tabs([
                            ['company', 'Datos de la empresa'],
                            ['preferences', 'Preferencias'],
                            ['users', 'Usuarios y permisos'],
                            ['integrations', 'Integraciones'],
                            ['numbering', 'Numeración'],
                            ['notifications', 'Notificaciones'],
                            ['security', 'Seguridad'],
                        ])}
                        {selected === 'company' ? (
                            <CompanyData
                                company={p.company}
                                canEdit={c.editCompany}
                            />
                        ) : (
                            <SettingsPanel tab={selected} c={c} />
                        )}
                    </>
                )}
            </PageShell>
        </>
    );
}
function Filters({
    area,
    base,
    f,
    departments = [],
}: {
    area: string;
    base: string;
    f: Record<string, string | number>;
    departments?: { id: number; name: string }[];
}) {
    return (
        <Form
            key={area + JSON.stringify(f)}
            action={base}
            method="get"
            className="company-filters"
        >
            {f.tab && <input type="hidden" name="tab" value={f.tab} />}
            {f.category && (
                <input type="hidden" name="category" value={f.category} />
            )}
            <label>
                <span className="sr-only">Buscar</span>
                <Input
                    name="search"
                    defaultValue={f.search}
                    placeholder={
                        area === 'staff'
                            ? 'Buscar por nombre o puesto...'
                            : 'Buscar nombre o descripción...'
                    }
                />
            </label>
            {area === 'staff' && (
                <label>
                    Departamento
                    <select name="department" defaultValue={f.department ?? ''}>
                        <option value="">Todos</option>
                        {departments.map((d) => (
                            <option key={d.id} value={d.id}>
                                {d.name}
                            </option>
                        ))}
                    </select>
                </label>
            )}
            <label>
                Estado
                <select
                    name="state"
                    defaultValue={
                        f.state ?? (area === 'documents' ? 'active' : '')
                    }
                >
                    {area === 'staff' && <option value="">Todos</option>}
                    <option value="active">Activo</option>
                    {area === 'staff' ? (
                        <>
                            <option value="on_leave">Ausencia</option>
                            <option value="ended">Finalizado</option>
                            <option value="missing">Sin ficha laboral</option>
                        </>
                    ) : (
                        <option value="archived">Archivado</option>
                    )}
                </select>
            </label>
            <label>
                Ordenar
                <select
                    name="sort"
                    defaultValue={
                        f.sort ?? (area === 'staff' ? 'name' : 'recent')
                    }
                >
                    <option value="name">Nombre</option>
                    <option value="recent">Más recientes</option>
                    {area === 'documents' && (
                        <>
                            <option value="oldest">Más antiguos</option>
                            <option value="expiry">Caducidad</option>
                        </>
                    )}
                </select>
            </label>
            <Button variant="outline">
                <Search />
                Filtrar
            </Button>
        </Form>
    );
}
function Expiries({ rows }: { rows: Expiry[] }) {
    return rows.length ? (
        <div className="company-list">
            {rows.map((r) => (
                <Link key={r.id} href={r.href}>
                    <span>
                        <strong>{r.title}</strong>
                        <small>{categories[r.type] ?? label(r.type)}</small>
                    </span>
                    <span>{date(r.date)}</span>
                </Link>
            ))}
        </div>
    ) : (
        <Empty>No hay vencimientos autorizados en los próximos 30 días.</Empty>
    );
}
function CompanyData({
    company,
    canEdit,
}: {
    company: Company | null;
    canEdit: boolean;
}) {
    const brand = useCompanyBrand();
    const [editing, setEditing] = useState(false);
    const fields: Record<string, string> = {
        legal_name: 'Razón social',
        trade_name: 'Nombre comercial',
        tax_id: 'NIF',
        address: 'Domicilio',
        postal_code: 'Código postal',
        city: 'Municipio',
        province: 'Provincia',
        country: 'País',
        phone: 'Teléfono',
        email: 'Correo electrónico',
        website: 'Web',
    };

    return (
        <div className="company-settings-grid">
            <Panel
                title="Datos de la empresa"
                action={
                    canEdit && (
                        <Button
                            variant="outline"
                            onClick={() => setEditing(!editing)}
                        >
                            Editar
                        </Button>
                    )
                }
            >
                {!company ? (
                    <Empty>Los datos corporativos no están configurados.</Empty>
                ) : editing ? (
                    <Form
                        action="/company-fiscal/company"
                        method="post"
                        className="company-edit-form"
                    >
                        {({ errors, processing }) => (
                            <>
                                {Object.entries(fields).map(([key, title]) => (
                                    <label key={key}>
                                        {title}
                                        <Input
                                            name={key}
                                            defaultValue={company[key] ?? ''}
                                            type={
                                                key === 'email'
                                                    ? 'email'
                                                    : key === 'website'
                                                      ? 'url'
                                                      : 'text'
                                            }
                                        />
                                        {errors[key] && (
                                            <small role="alert">
                                                {errors[key]}
                                            </small>
                                        )}
                                    </label>
                                ))}
                                <Button disabled={processing}>
                                    Guardar datos
                                </Button>
                            </>
                        )}
                    </Form>
                ) : (
                    <dl className="company-description">
                        {Object.entries(fields).map(([key, title]) => (
                            <div key={key}>
                                <dt>{title}</dt>
                                <dd>{company[key] || '—'}</dd>
                            </div>
                        ))}
                    </dl>
                )}
            </Panel>
            <div className="company-stack">
                <Panel title="Logo y marca">
                    <div className="company-logo">
                        <BrandLogo surface="light" />
                    </div>
                    <p className="company-note">
                        {brand.custom
                            ? 'Recurso corporativo personalizado. El SVG oficial de AREVANTES se conserva como respaldo.'
                            : 'Recurso canónico oficial: arevantes-logo.svg.'}
                    </p>
                    <p>{brand.name}</p>
                    {canEdit && (
                        <Form
                            action="/company-fiscal/branding"
                            method="post"
                            resetOnSuccess
                        >
                            {({ errors, processing }) => (
                                <div className="grid gap-3">
                                    <label htmlFor="company-brand-logo">
                                        Sustituir logo (SVG, PNG, JPEG o WebP;
                                        máximo 2 MB)
                                    </label>
                                    <Input
                                        id="company-brand-logo"
                                        name="logo"
                                        type="file"
                                        accept="image/svg+xml,image/png,image/jpeg,image/webp"
                                    />
                                    {errors.logo && (
                                        <small role="alert">
                                            {errors.logo}
                                        </small>
                                    )}
                                    <label>
                                        <input
                                            type="checkbox"
                                            name="remove_logo"
                                            value="1"
                                        />{' '}
                                        Restaurar SVG oficial de AREVANTES
                                    </label>
                                    <Button disabled={processing}>
                                        Guardar logo
                                    </Button>
                                </div>
                            )}
                        </Form>
                    )}
                </Panel>
                <Panel title="Datos de contacto">
                    <dl className="company-description">
                        <div>
                            <dt>Correo electrónico</dt>
                            <dd>{company?.email ?? '—'}</dd>
                        </div>
                        <div>
                            <dt>Teléfono</dt>
                            <dd>{company?.phone ?? '—'}</dd>
                        </div>
                        <div>
                            <dt>Dirección</dt>
                            <dd>{company?.address ?? '—'}</dd>
                        </div>
                    </dl>
                </Panel>
            </div>
            <div className="company-stack">
                <Panel title="Estado de configuración">
                    <p>
                        {company
                            ? 'Datos corporativos registrados'
                            : 'Sin datos corporativos'}
                    </p>
                    <p className="company-note">
                        La integridad fiscal, los permisos y las integraciones
                        se revisan en sus herramientas. No hay un porcentaje
                        global de configuración definido.
                    </p>
                </Panel>
                <Panel title="Accesos de configuración">
                    <Link href="/company/settings?tab=preferences">
                        Preferencias personales →
                    </Link>
                    <br />
                    <Link href="/company/settings?tab=security">
                        Seguridad y acceso →
                    </Link>
                </Panel>
            </div>
        </div>
    );
}
function SettingsPanel({ tab, c }: { tab: string; c: Capability }) {
    const content: Record<string, [string, string, string | null]> = {
        preferences: [
            'Preferencias',
            'Idioma y preferencias de la interfaz del usuario.',
            '/settings/appearance',
        ],
        users: [
            'Usuarios y permisos',
            'Cuentas de acceso, perfiles y permisos existentes.',
            c.users ? '/admin/users' : null,
        ],
        integrations: [
            'Integraciones',
            'No se verifica el estado de una conexión por mostrar su configuración.',
            null,
        ],
        numbering: [
            'Numeración',
            'Series y ejercicios existentes; sin alterar numeraciones históricas.',
            c.editCompany ? '/company-fiscal' : null,
        ],
        notifications: [
            'Notificaciones',
            'El centro de acción existente conserva sus avisos. No hay un editor corporativo de canales en esta vista.',
            null,
        ],
        security: [
            'Seguridad',
            'Configura la seguridad de tu cuenta con los controles existentes.',
            '/settings/security',
        ],
    };
    const [title, text, href] = content[tab] ?? content.preferences;

    return (
        <Panel title={title}>
            <p>{text}</p>
            {href ? (
                <Button variant="outline" asChild>
                    <Link href={href}>Abrir configuración</Link>
                </Button>
            ) : (
                <Empty unavailable>
                    Esta capacidad requiere configuración o soporte adicional.
                </Empty>
            )}
            {tab === 'security' && (
                <div className="company-settings-actions">
                    {c.audit && (
                        <Button variant="outline" asChild>
                            <Link href="/admin/audit">
                                Historial de cambios
                            </Link>
                        </Button>
                    )}
                    {c.backups && (
                        <Button variant="outline" asChild>
                            <Link href="/admin/backups">
                                Consultar copias de seguridad
                            </Link>
                        </Button>
                    )}
                    <p className="company-note">
                        No se exportan secretos ni se ejecutan restauraciones
                        desde esta pantalla.
                    </p>
                </div>
            )}
        </Panel>
    );
}

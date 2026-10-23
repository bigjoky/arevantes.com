import { Form, Head, Link } from '@inertiajs/react';
import { Upload } from 'lucide-react';
import { useState } from 'react';
import { Panel, Pagination, date } from '@/components/billing/workspace';
import type { Page } from '@/components/billing/workspace';
import { PageShell, PageHeader } from '@/components/page-shell';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { categories, State, label, size } from './common';
import type { Document } from './common';
import '@/../css/company.css';
type Version = {
    id: number;
    version: number;
    name: string;
    mime: string;
    size: number;
    created: string;
    author: string | null;
    href: string;
};
export default function CompanyDocument({
    document: d,
    versions,
    canManage,
    history,
    returnFilters,
}: {
    document: Document;
    versions: Page<Version>;
    canManage: boolean;
    returnFilters: Record<string, string | number>;
    history: Page<{
        id: number;
        event: string;
        user_id: number | null;
        created_at: string;
    }>;
}) {
    const [editing, setEditing] = useState(false);

    return (
        <>
            <Head title={d.title} />
            <PageShell mode="full" className="company-workspace">
                <PageHeader
                    title={d.title}
                    description={`${categories[d.category]} · ${size(d.size)} · v${d.version}`}
                    secondaryNavigation={null}
                    actions={
                        <>
                            <Button variant="outline" asChild>
                                <Link
                                    href={
                                        '/company/documents?' +
                                        new URLSearchParams(
                                            Object.entries(returnFilters).map(
                                                ([k, v]) => [
                                                    k.replace(/^return_/, ''),
                                                    String(v),
                                                ],
                                            ),
                                        ).toString()
                                    }
                                >
                                    Volver a documentos
                                </Link>
                            </Button>
                            {canManage && (
                                <Button
                                    variant="outline"
                                    onClick={() => setEditing(!editing)}
                                >
                                    Editar datos
                                </Button>
                            )}
                        </>
                    }
                />
                <div>
                    <State value={d.status} />
                </div>
                <div className="company-grid">
                    <Panel title="Información del documento">
                        {editing ? (
                            <Form
                                action={`/company/documents/${d.id}`}
                                method="patch"
                                className="company-edit-form"
                            >
                                {({ errors, processing }) => (
                                    <>
                                        <input
                                            type="hidden"
                                            name="expected_revision"
                                            value={d.revision}
                                        />
                                        <Metadata document={d} />
                                        <label>
                                            Estado
                                            <select
                                                name="status"
                                                defaultValue={d.status}
                                            >
                                                <option value="active">
                                                    Activo
                                                </option>
                                                <option value="archived">
                                                    Archivado
                                                </option>
                                            </select>
                                        </label>
                                        {Object.entries(errors).map(
                                            ([key, error]) => (
                                                <p role="alert" key={key}>
                                                    {error}
                                                </p>
                                            ),
                                        )}
                                        <Button disabled={processing}>
                                            Guardar datos
                                        </Button>
                                    </>
                                )}
                            </Form>
                        ) : (
                            <dl className="company-description">
                                {[
                                    ['Descripción', d.description],
                                    ['Categoría', categories[d.category]],
                                    ['Acceso', label(d.visibility)],
                                    ['Incorporación', date(d.created)],
                                    ['Última modificación', date(d.updated)],
                                    ['Caducidad', date(d.expires)],
                                    ['Tipo', d.mime],
                                    ['Tamaño', size(d.size)],
                                    ['Versión', String(d.version)],
                                ].map(([key, value]) => (
                                    <div key={key}>
                                        <dt>{key}</dt>
                                        <dd>{value ?? '—'}</dd>
                                    </div>
                                ))}
                            </dl>
                        )}
                    </Panel>
                    <Panel title="Versiones conservadas">
                        <div className="company-list">
                            {versions.data.map((v) => (
                                <a href={v.href} key={v.id}>
                                    <span>
                                        <strong>
                                            v{v.version} · {v.name}
                                        </strong>
                                        <small>
                                            {date(v.created)} · {size(v.size)} ·{' '}
                                            {v.author ?? '—'}
                                        </small>
                                    </span>
                                    <span>Descargar</span>
                                </a>
                            ))}
                        </div>
                        <Pagination page={versions} noun="versiones" />
                        {canManage && d.status === 'active' && (
                            <UploadDialog document={d} />
                        )}
                        <p className="company-note">
                            Las versiones anteriores se conservan. Las descargas
                            requieren autorización y comprobación de integridad.
                        </p>
                    </Panel>
                </div>
                <Panel title="Historial documental">
                    {history.data.map((h) => (
                        <div className="company-record" key={h.id}>
                            <span>{date(h.created_at)}</span>
                            <span>{label(h.event)}</span>
                            <span>Usuario {h.user_id ?? '—'}</span>
                        </div>
                    ))}
                    <Pagination page={history} noun="cambios" />
                </Panel>
            </PageShell>
        </>
    );
}
function Metadata({ document: d }: { document?: Document }) {
    return (
        <>
            <label>
                Nombre
                <Input
                    name="title"
                    required
                    maxLength={180}
                    defaultValue={d?.title ?? ''}
                />
            </label>
            <label>
                Descripción
                <Textarea
                    name="description"
                    maxLength={3000}
                    defaultValue={d?.description ?? ''}
                />
            </label>
            <label>
                Categoría
                <select
                    name="category"
                    required
                    defaultValue={d?.category ?? ''}
                >
                    <option value="">Seleccionar</option>
                    {Object.entries(categories).map(([key, value]) => (
                        <option key={key} value={key}>
                            {value}
                        </option>
                    ))}
                </select>
            </label>
            <label>
                Acceso
                <select
                    name="visibility"
                    required
                    defaultValue={d?.visibility ?? 'restricted'}
                >
                    <option value="restricted">
                        Solo administración de empresa
                    </option>
                    <option value="staff">
                        Administración de empresa y personal
                    </option>
                </select>
            </label>
            <label>
                Fecha de caducidad
                <Input
                    type="date"
                    name="expires_at"
                    defaultValue={d?.expires ?? ''}
                />
            </label>
        </>
    );
}
export function UploadDialog({ document: d }: { document?: Document }) {
    const [token] = useState(() => crypto.randomUUID());

    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button>
                    <Upload />
                    {d ? 'Añadir nueva versión' : 'Subir documento'}
                </Button>
            </DialogTrigger>
            <DialogContent className="company-workspace company-dialog max-h-[90vh] overflow-y-auto sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>
                        {d
                            ? 'Nueva versión del documento'
                            : 'Subir documento corporativo'}
                    </DialogTitle>
                </DialogHeader>
                <Form
                    action={
                        d
                            ? `/company/documents/${d.id}/versions`
                            : '/company/documents'
                    }
                    method="post"
                    encType="multipart/form-data"
                    className="company-edit-form"
                >
                    {({ errors, processing }) => (
                        <>
                            {d ? (
                                <input
                                    type="hidden"
                                    name="expected_revision"
                                    value={d.revision}
                                />
                            ) : (
                                <>
                                    <input
                                        type="hidden"
                                        name="upload_token"
                                        value={token}
                                    />
                                    <Metadata />
                                </>
                            )}
                            <label>
                                Archivo
                                <Input
                                    name="file"
                                    type="file"
                                    required
                                    accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"
                                />
                            </label>
                            <p className="company-note">
                                PDF, imagen o Word · máximo 12 MB. Los
                                documentos laborales se gestionan desde el
                                expediente de personal.
                            </p>
                            {Object.entries(errors).map(([key, error]) => (
                                <p
                                    role="alert"
                                    key={key}
                                    className="text-destructive"
                                >
                                    {error}
                                </p>
                            ))}
                            <Button disabled={processing}>
                                {d
                                    ? 'Conservar nueva versión'
                                    : 'Guardar documento'}
                            </Button>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

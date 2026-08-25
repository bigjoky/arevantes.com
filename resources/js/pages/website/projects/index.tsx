import { Form, Head } from '@inertiajs/react';
import {
    ExternalLink,
    Eye,
    EyeOff,
    ImagePlus,
    Plus,
    Save,
    Trash2,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import type { PublicProjectImage } from '@/types/public-project';

type Project = {
    id: number;
    installation_id: number | null;
    installation_label: string | null;
    title: string;
    slug: string;
    service_slug: string;
    location: string | null;
    summary: string;
    description: string | null;
    completed_on: string | null;
    is_featured: boolean;
    sort_order: number;
    published: boolean;
    published_at: string | null;
    public_url: string | null;
    images: PublicProjectImage[];
};

type Installation = {
    id: number;
    label: string;
    location: string | null;
    service_type: string;
};

type Service = { slug: string; name: string };

export default function WebsiteProjects({
    projects,
    installations,
    services,
}: {
    projects: Project[];
    installations: Installation[];
    services: Service[];
}) {
    return (
        <>
            <Head title="Web y proyectos" />
            <div className="flex min-w-0 flex-1 flex-col gap-8 p-4 md:p-6">
                <header className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <div className="flex items-center gap-2">
                            <ImagePlus className="size-5" />
                            <h1 className="text-xl font-semibold">
                                Web y proyectos
                            </h1>
                        </div>
                        <p className="mt-1 max-w-3xl text-sm text-muted-foreground">
                            Crea casos públicos, prepara el contenido como
                            borrador y publícalo cuando las imágenes y los
                            textos estén listos.
                        </p>
                    </div>
                    <Button asChild variant="outline">
                        <a href="/proyectos" target="_blank" rel="noreferrer">
                            Ver web pública <ExternalLink />
                        </a>
                    </Button>
                </header>

                <details open className="rounded-xl border bg-card">
                    <summary className="flex cursor-pointer list-none items-center gap-2 p-5 font-semibold">
                        <Plus className="size-4" /> Nuevo proyecto
                    </summary>
                    <Form
                        action="/website/projects"
                        method="post"
                        encType="multipart/form-data"
                        resetOnSuccess
                        className="border-t p-5"
                    >
                        {({ processing, errors }) => (
                            <>
                                <ProjectFields
                                    services={services}
                                    installations={installations}
                                    errors={errors}
                                />
                                <div className="mt-5 flex justify-end">
                                    <Button disabled={processing}>
                                        <Plus />{' '}
                                        {processing
                                            ? 'Creando…'
                                            : 'Crear proyecto'}
                                    </Button>
                                </div>
                            </>
                        )}
                    </Form>
                </details>

                <section>
                    <div className="mb-4 flex items-center justify-between gap-4">
                        <div>
                            <h2 className="font-semibold">
                                Proyectos gestionados
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                {projects.length} proyectos en total
                            </p>
                        </div>
                    </div>

                    {projects.length === 0 ? (
                        <div className="rounded-xl border border-dashed p-10 text-center text-sm text-muted-foreground">
                            Aún no hay proyectos. Crea el primero desde el
                            formulario superior.
                        </div>
                    ) : (
                        <div className="grid gap-5">
                            {projects.map((project) => (
                                <article
                                    key={project.id}
                                    className="overflow-hidden rounded-xl border bg-card"
                                >
                                    <div className="flex flex-col gap-4 border-b p-5 sm:flex-row sm:items-center sm:justify-between">
                                        <div className="flex min-w-0 items-center gap-4">
                                            {project.images[0] ? (
                                                <img
                                                    src={project.images[0].url}
                                                    alt=""
                                                    className="size-20 shrink-0 rounded-lg object-cover"
                                                />
                                            ) : (
                                                <div className="grid size-20 shrink-0 place-items-center rounded-lg bg-muted">
                                                    <ImagePlus className="size-6 text-muted-foreground" />
                                                </div>
                                            )}
                                            <div className="min-w-0">
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <h3 className="truncate font-semibold">
                                                        {project.title}
                                                    </h3>
                                                    <span
                                                        className={`inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-medium ${project.published ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200' : 'bg-muted text-muted-foreground'}`}
                                                    >
                                                        {project.published ? (
                                                            <Eye className="size-3" />
                                                        ) : (
                                                            <EyeOff className="size-3" />
                                                        )}
                                                        {project.published
                                                            ? 'Publicado'
                                                            : 'Borrador'}
                                                    </span>
                                                </div>
                                                <p className="mt-1 truncate text-sm text-muted-foreground">
                                                    /{project.slug}
                                                </p>
                                                <p className="mt-1 text-xs text-muted-foreground">
                                                    {project.images.length}{' '}
                                                    {project.images.length === 1
                                                        ? 'imagen'
                                                        : 'imágenes'}
                                                    {project.installation_label
                                                        ? ` · Vinculado a ${project.installation_label}`
                                                        : ''}
                                                </p>
                                            </div>
                                        </div>
                                        {project.public_url && (
                                            <Button
                                                asChild
                                                size="sm"
                                                variant="outline"
                                            >
                                                <a
                                                    href={project.public_url}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                >
                                                    Abrir <ExternalLink />
                                                </a>
                                            </Button>
                                        )}
                                    </div>

                                    <details className="group">
                                        <summary className="cursor-pointer list-none px-5 py-4 text-sm font-semibold text-primary">
                                            Editar contenido e imágenes
                                        </summary>
                                        <div className="border-t p-5">
                                            {project.images.length > 0 && (
                                                <div className="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                                                    {project.images.map(
                                                        (image, index) => (
                                                            <div
                                                                key={image.id}
                                                                className="overflow-hidden rounded-lg border"
                                                            >
                                                                <img
                                                                    src={
                                                                        image.url
                                                                    }
                                                                    alt={
                                                                        image.alt
                                                                    }
                                                                    className="aspect-[16/10] w-full object-cover"
                                                                />
                                                                <Form
                                                                    action={`/website/project-images/${image.id}`}
                                                                    method="delete"
                                                                    className="flex items-center justify-between gap-3 p-3"
                                                                >
                                                                    <span className="truncate text-xs text-muted-foreground">
                                                                        Imagen{' '}
                                                                        {index +
                                                                            1}
                                                                    </span>
                                                                    <Button
                                                                        type="submit"
                                                                        size="icon"
                                                                        variant="ghost"
                                                                        aria-label="Eliminar imagen"
                                                                        onClick={(
                                                                            event,
                                                                        ) => {
                                                                            if (
                                                                                !window.confirm(
                                                                                    '¿Eliminar esta imagen del proyecto?',
                                                                                )
                                                                            ) {
                                                                                event.preventDefault();
                                                                            }
                                                                        }}
                                                                    >
                                                                        <Trash2 />
                                                                    </Button>
                                                                </Form>
                                                            </div>
                                                        ),
                                                    )}
                                                </div>
                                            )}

                                            <Form
                                                action={`/website/projects/${project.id}`}
                                                method="post"
                                                encType="multipart/form-data"
                                            >
                                                {({ processing, errors }) => (
                                                    <>
                                                        <input
                                                            type="hidden"
                                                            name="_method"
                                                            value="put"
                                                        />
                                                        <ProjectFields
                                                            project={project}
                                                            services={services}
                                                            installations={
                                                                installations
                                                            }
                                                            errors={errors}
                                                        />
                                                        {project.images.map(
                                                            (image, index) => (
                                                                <div
                                                                    key={
                                                                        image.id
                                                                    }
                                                                    className="mt-4 grid gap-3 rounded-lg border p-4 md:grid-cols-[1fr_1fr_8rem]"
                                                                >
                                                                    <input
                                                                        type="hidden"
                                                                        name={`existing_images[${index}][id]`}
                                                                        value={
                                                                            image.id
                                                                        }
                                                                    />
                                                                    <Field
                                                                        label={`Texto alternativo · imagen ${index + 1}`}
                                                                        error={
                                                                            errors[
                                                                                `existing_images.${index}.alt_text`
                                                                            ]
                                                                        }
                                                                    >
                                                                        <Input
                                                                            name={`existing_images[${index}][alt_text]`}
                                                                            defaultValue={
                                                                                image.alt
                                                                            }
                                                                            required
                                                                        />
                                                                    </Field>
                                                                    <Field
                                                                        label="Pie de foto (opcional)"
                                                                        error={
                                                                            errors[
                                                                                `existing_images.${index}.caption`
                                                                            ]
                                                                        }
                                                                    >
                                                                        <Input
                                                                            name={`existing_images[${index}][caption]`}
                                                                            defaultValue={
                                                                                image.caption ??
                                                                                ''
                                                                            }
                                                                        />
                                                                    </Field>
                                                                    <Field
                                                                        label="Orden"
                                                                        error={
                                                                            errors[
                                                                                `existing_images.${index}.position`
                                                                            ]
                                                                        }
                                                                    >
                                                                        <Input
                                                                            name={`existing_images[${index}][position]`}
                                                                            type="number"
                                                                            min="0"
                                                                            max="10000"
                                                                            defaultValue={
                                                                                image.position
                                                                            }
                                                                            required
                                                                        />
                                                                    </Field>
                                                                </div>
                                                            ),
                                                        )}
                                                        <div className="mt-5 flex flex-wrap items-center justify-between gap-3 border-t pt-5">
                                                            <p className="text-xs text-muted-foreground">
                                                                Los cambios de
                                                                texto y
                                                                publicación se
                                                                aplican al
                                                                guardar.
                                                            </p>
                                                            <Button
                                                                disabled={
                                                                    processing
                                                                }
                                                            >
                                                                <Save />{' '}
                                                                {processing
                                                                    ? 'Guardando…'
                                                                    : 'Guardar cambios'}
                                                            </Button>
                                                        </div>
                                                    </>
                                                )}
                                            </Form>

                                            <Form
                                                action={`/website/projects/${project.id}`}
                                                method="delete"
                                                className="mt-4 flex justify-end border-t pt-4"
                                            >
                                                <Button
                                                    type="submit"
                                                    size="sm"
                                                    variant="destructive"
                                                    onClick={(event) => {
                                                        if (
                                                            !window.confirm(
                                                                '¿Eliminar este proyecto y sus imágenes? Esta acción no se puede deshacer.',
                                                            )
                                                        ) {
                                                            event.preventDefault();
                                                        }
                                                    }}
                                                >
                                                    <Trash2 /> Eliminar proyecto
                                                </Button>
                                            </Form>
                                        </div>
                                    </details>
                                </article>
                            ))}
                        </div>
                    )}
                </section>
            </div>
        </>
    );
}

function ProjectFields({
    project,
    services,
    installations,
    errors,
}: {
    project?: Project;
    services: Service[];
    installations: Installation[];
    errors: Record<string, string>;
}) {
    return (
        <div className="grid gap-4">
            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <Field
                    label="Título"
                    error={errors.title}
                    className="xl:col-span-2"
                >
                    <Input
                        name="title"
                        defaultValue={project?.title ?? ''}
                        maxLength={160}
                        required
                    />
                </Field>
                <Field label="Servicio" error={errors.service_slug}>
                    <select
                        name="service_slug"
                        defaultValue={project?.service_slug ?? 'energia'}
                        className="h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm"
                        required
                    >
                        {services.map((service) => (
                            <option key={service.slug} value={service.slug}>
                                {service.name}
                            </option>
                        ))}
                    </select>
                </Field>
                <Field
                    label="Fecha de finalización"
                    error={errors.completed_on}
                >
                    <Input
                        name="completed_on"
                        type="date"
                        defaultValue={project?.completed_on ?? ''}
                    />
                </Field>
            </div>

            <div className="grid gap-4 md:grid-cols-2">
                <Field label="Ubicación pública" error={errors.location}>
                    <Input
                        name="location"
                        defaultValue={project?.location ?? ''}
                        maxLength={160}
                        placeholder="Ej. Baena, Córdoba"
                    />
                </Field>
                <Field
                    label="Vincular a instalación (opcional)"
                    error={errors.installation_id}
                >
                    <select
                        name="installation_id"
                        defaultValue={project?.installation_id ?? ''}
                        className="h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm"
                    >
                        <option value="">Sin vincular</option>
                        {installations.map((installation) => (
                            <option
                                key={installation.id}
                                value={installation.id}
                            >
                                {installation.label}
                                {installation.location
                                    ? ` · ${installation.location}`
                                    : ''}
                            </option>
                        ))}
                    </select>
                </Field>
            </div>

            <Field label="Resumen" error={errors.summary}>
                <Textarea
                    name="summary"
                    defaultValue={project?.summary ?? ''}
                    maxLength={500}
                    rows={3}
                    required
                />
            </Field>
            <Field label="Descripción del proyecto" error={errors.description}>
                <Textarea
                    name="description"
                    defaultValue={project?.description ?? ''}
                    maxLength={5000}
                    rows={5}
                />
            </Field>

            <div className="grid gap-4 md:grid-cols-[1fr_9rem]">
                <Field
                    label={project ? 'Añadir imágenes' : 'Imágenes'}
                    error={errors.images}
                >
                    <Input
                        name="images[]"
                        type="file"
                        multiple
                        accept="image/jpeg,image/png,image/webp"
                        required={!project}
                    />
                    <p className="text-xs text-muted-foreground">
                        JPG, PNG o WebP, hasta 15 MB por archivo. Se
                        redimensionan y convierten automáticamente a WebP.
                    </p>
                </Field>
                <Field label="Orden" error={errors.sort_order}>
                    <Input
                        name="sort_order"
                        type="number"
                        min="0"
                        max="10000"
                        defaultValue={project?.sort_order ?? 100}
                        required
                    />
                </Field>
            </div>

            <div className="flex flex-wrap gap-5 rounded-lg border bg-muted/30 p-4 text-sm">
                <input type="hidden" name="is_featured" value="0" />
                <label className="flex items-center gap-2">
                    <input
                        type="checkbox"
                        name="is_featured"
                        value="1"
                        defaultChecked={project?.is_featured ?? false}
                        className="size-4 accent-primary"
                    />{' '}
                    Destacar en portada
                </label>
                <input type="hidden" name="published" value="0" />
                <label className="flex items-center gap-2">
                    <input
                        type="checkbox"
                        name="published"
                        value="1"
                        defaultChecked={project?.published ?? false}
                        className="size-4 accent-primary"
                    />{' '}
                    Publicar en la web
                </label>
            </div>
            {(errors.is_featured || errors.published) && (
                <p className="text-xs text-destructive">
                    {errors.is_featured || errors.published}
                </p>
            )}
        </div>
    );
}

function Field({
    label,
    error,
    children,
    className = '',
}: {
    label: string;
    error?: string;
    children: ReactNode;
    className?: string;
}) {
    return (
        <Label className={`grid content-start gap-2 ${className}`}>
            <span>{label}</span>
            {children}
            {error && <span className="text-xs text-destructive">{error}</span>}
        </Label>
    );
}

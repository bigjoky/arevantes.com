import { Head, Link } from '@inertiajs/react';
import { ArrowRight, Check, ChevronRight, MapPin } from 'lucide-react';
import { ProjectCard } from '@/components/public/project-card';
import { PublicSiteFooter } from '@/components/public/site-footer';
import { PublicSiteHeader } from '@/components/public/site-header';
import type {
    PublicProjectDetail,
    PublicProjectSummary,
} from '@/types/public-project';

export default function ProjectShow({
    project,
    relatedProjects,
    siteUrl,
}: {
    project: PublicProjectDetail;
    relatedProjects: PublicProjectSummary[];
    siteUrl: string;
}) {
    const title = `${project.title} · Proyectos Arevantes`;
    const canonical = `${siteUrl}${project.href}`;
    const primaryImage = project.images[0];

    return (
        <>
            <Head>
                <title head-key="title">{title}</title>
                <meta
                    head-key="description"
                    name="description"
                    content={project.summary}
                />
                <meta head-key="robots" name="robots" content="index, follow" />
                <link head-key="canonical" rel="canonical" href={canonical} />
                <meta head-key="og-type" property="og:type" content="article" />
                <meta
                    head-key="og-locale"
                    property="og:locale"
                    content="es_ES"
                />
                <meta head-key="og-title" property="og:title" content={title} />
                <meta
                    head-key="og-description"
                    property="og:description"
                    content={project.summary}
                />
                <meta head-key="og-url" property="og:url" content={canonical} />
                {primaryImage && (
                    <meta
                        head-key="og-image"
                        property="og:image"
                        content={primaryImage.absolute_url}
                    />
                )}
                {primaryImage && (
                    <meta
                        head-key="og-image-alt"
                        property="og:image:alt"
                        content={primaryImage.alt}
                    />
                )}
                <meta
                    head-key="twitter-card"
                    name="twitter:card"
                    content="summary_large_image"
                />
                <meta
                    head-key="twitter-title"
                    name="twitter:title"
                    content={title}
                />
                <meta
                    head-key="twitter-description"
                    name="twitter:description"
                    content={project.summary}
                />
                {primaryImage && (
                    <meta
                        head-key="twitter-image"
                        name="twitter:image"
                        content={primaryImage.absolute_url}
                    />
                )}
                <script
                    head-key="project-structured-data"
                    type="application/ld+json"
                >
                    {JSON.stringify({
                        '@context': 'https://schema.org',
                        '@type': 'CreativeWork',
                        name: project.title,
                        description: project.summary,
                        url: canonical,
                        image: primaryImage?.absolute_url,
                        locationCreated: project.location,
                        dateCreated: project.completed_on,
                        creator: {
                            '@type': 'Organization',
                            name: 'Arevantes',
                            url: siteUrl,
                        },
                    })}
                </script>
            </Head>

            <div className="min-h-screen bg-[#f7f8f4] text-zinc-950 antialiased">
                <a
                    href="#contenido"
                    className="fixed top-3 left-3 z-[100] -translate-y-20 rounded-full bg-zinc-950 px-5 py-3 font-semibold text-white focus:translate-y-0"
                >
                    Saltar al contenido
                </a>
                <PublicSiteHeader active="projects" />
                <main id="contenido">
                    <section className="bg-zinc-950 px-5 pt-10 pb-16 text-white sm:px-8 sm:pt-14 sm:pb-24">
                        <div className="mx-auto max-w-7xl">
                            <nav
                                aria-label="Migas de pan"
                                className="flex flex-wrap items-center gap-2 text-sm text-zinc-400"
                            >
                                <Link href="/">Inicio</Link>
                                <ChevronRight className="size-4" />
                                <Link href="/proyectos">Proyectos</Link>
                                <ChevronRight className="size-4" />
                                <span className="text-zinc-200">
                                    {project.title}
                                </span>
                            </nav>
                            <div className="mt-14 grid gap-12 lg:grid-cols-[.82fr_1.18fr] lg:items-end">
                                <div>
                                    <span className="inline-flex rounded-full border border-[#87b550]/30 bg-[#87b550]/10 px-4 py-2 text-sm font-semibold text-[#b8df8c]">
                                        {project.service_name}
                                    </span>
                                    <h1 className="mt-7 text-5xl leading-[.98] font-semibold tracking-[-.055em] sm:text-6xl lg:text-7xl">
                                        {project.title}
                                    </h1>
                                    <p className="mt-7 text-lg leading-8 text-zinc-300">
                                        {project.summary}
                                    </p>
                                    <div className="mt-8 flex flex-wrap gap-x-6 gap-y-3 text-sm text-zinc-300">
                                        {project.location && (
                                            <span className="flex items-center gap-2">
                                                <MapPin className="size-4 text-[#87b550]" />
                                                {project.location}
                                            </span>
                                        )}
                                        {project.completed_year && (
                                            <span>
                                                Proyecto realizado en{' '}
                                                {project.completed_year}
                                            </span>
                                        )}
                                    </div>
                                </div>
                                {primaryImage && (
                                    <figure className="overflow-hidden rounded-[2rem] border border-white/15 bg-white/5 shadow-2xl">
                                        <img
                                            src={primaryImage.url}
                                            alt={primaryImage.alt}
                                            width={primaryImage.width}
                                            height={primaryImage.height}
                                            fetchPriority="high"
                                            className="aspect-[16/10] h-full w-full object-cover"
                                        />
                                    </figure>
                                )}
                            </div>
                        </div>
                    </section>

                    <section className="px-5 py-20 sm:px-8 lg:py-28">
                        <div className="mx-auto grid max-w-7xl gap-12 lg:grid-cols-[.72fr_1.28fr]">
                            <div>
                                <p className="text-sm font-bold tracking-[.18em] text-[#217a3b] uppercase">
                                    El proyecto
                                </p>
                                <h2 className="mt-4 text-4xl font-semibold tracking-[-.04em] sm:text-5xl">
                                    Una solución adaptada al lugar.
                                </h2>
                            </div>
                            <div>
                                <p className="text-xl leading-9 text-zinc-700">
                                    {project.description || project.summary}
                                </p>
                                <ul className="mt-9 grid gap-4 border-t border-zinc-200 pt-8 sm:grid-cols-2">
                                    {[
                                        'Estudio de las condiciones reales',
                                        'Diseño y ejecución coordinados',
                                        'Puesta en marcha documentada',
                                        'Continuidad y atención posventa',
                                    ].map((item) => (
                                        <li
                                            key={item}
                                            className="flex items-start gap-3 text-sm font-medium text-zinc-700"
                                        >
                                            <Check className="mt-0.5 size-4 shrink-0 text-[#217a3b]" />
                                            {item}
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        </div>
                    </section>

                    {project.images.length > 1 && (
                        <section className="bg-white px-5 py-20 sm:px-8 lg:py-28">
                            <div className="mx-auto max-w-7xl">
                                <div className="max-w-2xl">
                                    <p className="text-sm font-bold tracking-[.18em] text-[#217a3b] uppercase">
                                        Galería
                                    </p>
                                    <h2 className="mt-4 text-4xl font-semibold tracking-[-.04em] sm:text-5xl">
                                        Detalles de la ejecución.
                                    </h2>
                                </div>
                                <div className="mt-12 grid gap-5 md:grid-cols-2">
                                    {project.images.slice(1).map((image) => (
                                        <figure
                                            key={image.id}
                                            className="overflow-hidden rounded-[1.75rem] bg-zinc-100"
                                        >
                                            <img
                                                src={image.url}
                                                alt={image.alt}
                                                width={image.width}
                                                height={image.height}
                                                loading="lazy"
                                                className="aspect-[4/3] h-full w-full object-cover"
                                            />
                                            {image.caption && (
                                                <figcaption className="p-4 text-sm text-zinc-600">
                                                    {image.caption}
                                                </figcaption>
                                            )}
                                        </figure>
                                    ))}
                                </div>
                            </div>
                        </section>
                    )}

                    <section className="px-5 py-20 sm:px-8 lg:py-28">
                        <div className="mx-auto max-w-7xl overflow-hidden rounded-[2.25rem] bg-[#217a3b] px-7 py-12 text-white sm:px-12 lg:px-16 lg:py-16">
                            <div className="grid gap-9 lg:grid-cols-[1fr_auto] lg:items-end">
                                <div className="max-w-3xl">
                                    <p className="text-sm font-bold tracking-[.18em] text-white/70 uppercase">
                                        Hablemos de tu caso
                                    </p>
                                    <h2 className="mt-4 text-4xl font-semibold tracking-[-.04em] sm:text-5xl">
                                        Cada buen proyecto empieza con las
                                        preguntas adecuadas.
                                    </h2>
                                </div>
                                <Link
                                    href={`/?servicio=${project.service_slug === 'energia' ? 'solar' : project.service_slug}#contacto`}
                                    className="inline-flex items-center justify-center gap-2 rounded-full bg-white px-6 py-3.5 font-semibold text-[#185f2d]"
                                >
                                    Solicitar estudio{' '}
                                    <ArrowRight className="size-5" />
                                </Link>
                            </div>
                        </div>
                    </section>

                    {relatedProjects.length > 0 && (
                        <section className="border-t border-zinc-200 px-5 py-20 sm:px-8 lg:py-28">
                            <div className="mx-auto max-w-7xl">
                                <div className="flex items-end justify-between gap-6">
                                    <div>
                                        <p className="text-sm font-bold tracking-[.18em] text-[#217a3b] uppercase">
                                            Sigue explorando
                                        </p>
                                        <h2 className="mt-3 text-3xl font-semibold tracking-[-.035em] sm:text-4xl">
                                            Otros proyectos realizados
                                        </h2>
                                    </div>
                                    <Link
                                        href="/proyectos"
                                        className="hidden items-center gap-2 text-sm font-semibold text-[#217a3b] sm:inline-flex"
                                    >
                                        Ver todos{' '}
                                        <ArrowRight className="size-4" />
                                    </Link>
                                </div>
                                <div className="mt-10 grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                                    {relatedProjects.map((item) => (
                                        <ProjectCard
                                            key={item.slug}
                                            project={item}
                                        />
                                    ))}
                                </div>
                            </div>
                        </section>
                    )}
                </main>
                <PublicSiteFooter />
            </div>
        </>
    );
}

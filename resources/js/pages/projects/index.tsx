import { Head, Link } from '@inertiajs/react';
import { ArrowRight, Camera, ChevronRight } from 'lucide-react';
import { ProjectCard } from '@/components/public/project-card';
import { PublicSiteFooter } from '@/components/public/site-footer';
import { PublicSiteHeader } from '@/components/public/site-header';
import type { PublicProjectSummary } from '@/types/public-project';

type Service = { slug: string; name: string };

export default function ProjectsIndex({
    projects,
    activeService,
    services,
    siteUrl,
}: {
    projects: PublicProjectSummary[];
    activeService: string | null;
    services: Service[];
    siteUrl: string;
}) {
    const title = 'Proyectos realizados · Arevantes';
    const description =
        'Una selección de proyectos de energía, electricidad y seguridad realizados por Arevantes en Andalucía.';
    const canonical = `${siteUrl}/proyectos`;

    return (
        <>
            <Head>
                <title head-key="title">{title}</title>
                <meta
                    head-key="description"
                    name="description"
                    content={description}
                />
                <meta head-key="robots" name="robots" content="index, follow" />
                <link head-key="canonical" rel="canonical" href={canonical} />
                <meta head-key="og-type" property="og:type" content="website" />
                <meta
                    head-key="og-locale"
                    property="og:locale"
                    content="es_ES"
                />
                <meta head-key="og-title" property="og:title" content={title} />
                <meta
                    head-key="og-description"
                    property="og:description"
                    content={description}
                />
                <meta head-key="og-url" property="og:url" content={canonical} />
                <meta
                    head-key="og-image"
                    property="og:image"
                    content={`${siteUrl}/og-multirramo-v2.png`}
                />
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
                    content={description}
                />
                <meta
                    head-key="twitter-image"
                    name="twitter:image"
                    content={`${siteUrl}/og-multirramo-v2.png`}
                />
            </Head>

            <div className="min-h-screen bg-[#f7f8f4] text-zinc-950 antialiased">
                <a
                    href="#proyectos"
                    className="fixed top-3 left-3 z-[100] -translate-y-20 rounded-full bg-zinc-950 px-5 py-3 font-semibold text-white focus:translate-y-0"
                >
                    Saltar a los proyectos
                </a>
                <PublicSiteHeader active="projects" />
                <main>
                    <section className="relative overflow-hidden bg-zinc-950 px-5 py-20 text-white sm:px-8 sm:py-28 lg:py-32">
                        <div className="absolute inset-0 [background-image:linear-gradient(rgba(255,255,255,.04)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,.04)_1px,transparent_1px)] [background-size:52px_52px] opacity-50" />
                        <div className="absolute -top-48 right-0 size-[32rem] rounded-full bg-[#217a3b]/25 blur-3xl" />
                        <div className="relative mx-auto max-w-7xl">
                            <nav
                                aria-label="Migas de pan"
                                className="flex items-center gap-2 text-sm text-zinc-400"
                            >
                                <Link href="/" className="hover:text-white">
                                    Inicio
                                </Link>
                                <ChevronRight className="size-4" />
                                <span className="text-zinc-200">Proyectos</span>
                            </nav>
                            <div className="mt-14 grid gap-10 lg:grid-cols-[1.1fr_.9fr] lg:items-end">
                                <div>
                                    <div className="inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/5 px-4 py-2 text-sm text-zinc-300">
                                        <Camera className="size-4 text-[#87b550]" />
                                        Trabajo real, soluciones a medida
                                    </div>
                                    <h1 className="mt-8 text-5xl leading-[.98] font-semibold tracking-[-.055em] sm:text-6xl lg:text-7xl">
                                        Proyectos que ya están en marcha.
                                    </h1>
                                </div>
                                <p className="max-w-2xl text-lg leading-8 text-zinc-300 sm:text-xl lg:justify-self-end">
                                    Cada espacio plantea condiciones distintas.
                                    Aquí mostramos una selección de trabajos
                                    ejecutados por nuestro equipo y cómo nos
                                    adaptamos a cada caso.
                                </p>
                            </div>
                        </div>
                    </section>

                    <section
                        id="proyectos"
                        className="scroll-mt-24 px-5 py-20 sm:px-8 lg:py-28"
                    >
                        <div className="mx-auto max-w-7xl">
                            <div className="flex flex-col gap-6 sm:flex-row sm:items-end sm:justify-between">
                                <div>
                                    <p className="text-sm font-bold tracking-[.18em] text-[#217a3b] uppercase">
                                        Casos realizados
                                    </p>
                                    <h2 className="mt-3 text-3xl font-semibold tracking-[-.035em] sm:text-4xl">
                                        {activeService
                                            ? `Proyectos de ${services.find((service) => service.slug === activeService)?.name ?? activeService}`
                                            : 'Todos los proyectos publicados'}
                                    </h2>
                                </div>
                                <nav
                                    aria-label="Filtrar proyectos"
                                    className="flex flex-wrap gap-2"
                                >
                                    <Link
                                        href="/proyectos"
                                        preserveScroll
                                        className={`rounded-full border px-4 py-2 text-sm font-semibold transition ${!activeService ? 'border-zinc-950 bg-zinc-950 text-white' : 'border-zinc-200 bg-white hover:border-[#217a3b]'}`}
                                    >
                                        Todos
                                    </Link>
                                    {services.map((service) => (
                                        <Link
                                            key={service.slug}
                                            href={`/proyectos?servicio=${service.slug}`}
                                            preserveScroll
                                            className={`rounded-full border px-4 py-2 text-sm font-semibold transition ${activeService === service.slug ? 'border-[#217a3b] bg-[#217a3b] text-white' : 'border-zinc-200 bg-white hover:border-[#217a3b]'}`}
                                        >
                                            {service.name}
                                        </Link>
                                    ))}
                                </nav>
                            </div>

                            {projects.length > 0 ? (
                                <div className="mt-12 grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                                    {projects.map((project) => (
                                        <ProjectCard
                                            key={project.slug}
                                            project={project}
                                        />
                                    ))}
                                </div>
                            ) : (
                                <div className="mt-12 rounded-[2rem] border border-zinc-200 bg-white p-10 text-center sm:p-16">
                                    <h3 className="text-2xl font-semibold">
                                        Próximamente
                                    </h3>
                                    <p className="mx-auto mt-3 max-w-xl leading-7 text-zinc-600">
                                        Estamos preparando casos de esta línea
                                        de servicio. Mientras tanto, puedes ver
                                        el resto de proyectos publicados.
                                    </p>
                                    <Link
                                        href="/proyectos"
                                        className="mt-7 inline-flex items-center gap-2 font-semibold text-[#217a3b]"
                                    >
                                        Ver todos los proyectos{' '}
                                        <ArrowRight className="size-4" />
                                    </Link>
                                </div>
                            )}
                        </div>
                    </section>

                    <section className="px-5 pb-10 sm:px-8">
                        <div className="relative mx-auto flex max-w-7xl flex-col gap-8 overflow-hidden rounded-[2.25rem] bg-[#217a3b] px-7 py-12 text-white sm:px-12 lg:flex-row lg:items-center lg:justify-between lg:px-16">
                            <div className="absolute -right-28 -bottom-36 size-80 rounded-full border-[58px] border-white/10" />
                            <div className="relative max-w-3xl">
                                <p className="text-sm font-bold tracking-[.18em] text-white/70 uppercase">
                                    Tu proyecto puede ser el siguiente
                                </p>
                                <h2 className="mt-3 text-3xl font-semibold tracking-[-.04em] sm:text-5xl">
                                    Empezamos por entender bien tu espacio.
                                </h2>
                            </div>
                            <Link
                                href="/#contacto"
                                className="relative inline-flex shrink-0 items-center justify-center gap-2 rounded-full bg-white px-6 py-3.5 font-semibold text-[#185f2d]"
                            >
                                Solicitar una valoración{' '}
                                <ArrowRight className="size-5" />
                            </Link>
                        </div>
                    </section>
                </main>
                <PublicSiteFooter />
            </div>
        </>
    );
}

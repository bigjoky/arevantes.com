import { Head, Link } from '@inertiajs/react';
import {
    ArrowRight,
    Check,
    ChevronRight,
    Gauge,
    MapPin,
    Menu,
    ShieldCheck,
    Sun,
    X,
    Zap,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { ProjectCard } from '@/components/public/project-card';
import type { PublicProjectSummary } from '@/types/public-project';

type ServiceSummary = {
    slug: string;
    name: string;
    short_name: string;
};

type PublicService = ServiceSummary & {
    eyebrow: string;
    title: string;
    description: string;
    seo_title: string;
    seo_description: string;
    image: string;
    image_alt: string;
    form_value: string;
    intro: string;
    solutions: Array<{ title: string; description: string }>;
    deliverables: string[];
    audiences: string[];
    faqs: Array<{ question: string; answer: string }>;
};

const icons = {
    energia: Sun,
    electricidad: Zap,
    seguridad: ShieldCheck,
};

export default function ServicePage({
    service,
    services,
    projects,
    siteUrl,
}: {
    service: PublicService;
    services: ServiceSummary[];
    projects: PublicProjectSummary[];
    siteUrl: string;
}) {
    const Icon = icons[service.slug as keyof typeof icons] ?? Gauge;
    const canonical = `${siteUrl}/servicios/${service.slug}`;
    const contactUrl = `/?servicio=${service.form_value}#contacto`;
    const [isMenuOpen, setIsMenuOpen] = useState(false);

    useEffect(() => {
        if (!isMenuOpen) {
            return;
        }

        const closeOnEscape = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                setIsMenuOpen(false);
            }
        };

        document.addEventListener('keydown', closeOnEscape);

        return () => document.removeEventListener('keydown', closeOnEscape);
    }, [isMenuOpen]);

    const closeMenu = () => setIsMenuOpen(false);

    return (
        <>
            <Head>
                <title head-key="title">{service.seo_title}</title>
                <meta
                    head-key="description"
                    name="description"
                    content={service.seo_description}
                />
                <meta head-key="robots" name="robots" content="index, follow" />
                <link head-key="canonical" rel="canonical" href={canonical} />
                <meta head-key="og-type" property="og:type" content="website" />
                <meta
                    head-key="og-locale"
                    property="og:locale"
                    content="es_ES"
                />
                <meta
                    head-key="og-title"
                    property="og:title"
                    content={service.seo_title}
                />
                <meta
                    head-key="og-description"
                    property="og:description"
                    content={service.seo_description}
                />
                <meta head-key="og-url" property="og:url" content={canonical} />
                <meta
                    head-key="og-image"
                    property="og:image"
                    content={`${siteUrl}${service.image}`}
                />
                <meta
                    head-key="og-image-alt"
                    property="og:image:alt"
                    content={service.image_alt}
                />
                <meta
                    head-key="twitter-card"
                    name="twitter:card"
                    content="summary_large_image"
                />
                <meta
                    head-key="twitter-title"
                    name="twitter:title"
                    content={service.seo_title}
                />
                <meta
                    head-key="twitter-description"
                    name="twitter:description"
                    content={service.seo_description}
                />
                <meta
                    head-key="twitter-image"
                    name="twitter:image"
                    content={`${siteUrl}${service.image}`}
                />
                <script
                    head-key="faq-structured-data"
                    type="application/ld+json"
                >
                    {JSON.stringify({
                        '@context': 'https://schema.org',
                        '@type': 'FAQPage',
                        mainEntity: service.faqs.map((faq) => ({
                            '@type': 'Question',
                            name: faq.question,
                            acceptedAnswer: {
                                '@type': 'Answer',
                                text: faq.answer,
                            },
                        })),
                    })}
                </script>
            </Head>

            <div className="min-h-screen bg-[#f7f8f4] text-zinc-950 antialiased">
                <a
                    href="#contenido"
                    className="fixed top-3 left-3 z-[100] -translate-y-20 rounded-full bg-zinc-950 px-5 py-3 font-semibold text-white shadow-xl transition-transform focus:translate-y-0"
                >
                    Saltar al contenido
                </a>

                <header className="sticky top-0 z-50 border-b border-zinc-200/70 bg-[#f7f8f4]/90 backdrop-blur-2xl">
                    <div className="mx-auto flex h-20 max-w-7xl items-center justify-between gap-6 px-5 sm:px-8 lg:px-10">
                        <Link href="/" aria-label="Arevantes, inicio">
                            <img
                                src="/brand/arevantes-logo-light.svg"
                                alt="Arevantes"
                                className="h-7 w-auto sm:h-8"
                            />
                        </Link>
                        <nav
                            aria-label="Navegación principal"
                            className="hidden items-center gap-6 text-sm font-medium text-zinc-600 lg:flex"
                        >
                            <Link
                                href="/"
                                className="transition hover:text-[#217a3b]"
                            >
                                Inicio
                            </Link>
                            {services.map((item) => (
                                <Link
                                    key={item.slug}
                                    href={`/servicios/${item.slug}`}
                                    aria-current={
                                        item.slug === service.slug
                                            ? 'page'
                                            : undefined
                                    }
                                    className={
                                        item.slug === service.slug
                                            ? 'text-[#217a3b]'
                                            : 'transition hover:text-[#217a3b]'
                                    }
                                >
                                    {item.short_name}
                                </Link>
                            ))}
                            <Link
                                href={`/proyectos?servicio=${service.slug}`}
                                className="transition hover:text-[#217a3b]"
                            >
                                Proyectos
                            </Link>
                            <Link
                                href="/#contacto"
                                className="transition hover:text-[#217a3b]"
                            >
                                Contacto
                            </Link>
                        </nav>
                        <div className="flex items-center gap-2">
                            <Link
                                href={contactUrl}
                                className="hidden items-center gap-2 rounded-full bg-zinc-950 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-[#217a3b] sm:inline-flex"
                            >
                                Solicitar estudio
                                <ArrowRight className="size-4" />
                            </Link>
                            <button
                                type="button"
                                className="grid size-11 place-items-center rounded-full border border-zinc-300 bg-white text-zinc-900 shadow-sm transition hover:border-[#289548] hover:text-[#217a3b] lg:hidden"
                                aria-label={
                                    isMenuOpen
                                        ? 'Cerrar navegación'
                                        : 'Abrir navegación'
                                }
                                aria-expanded={isMenuOpen}
                                aria-controls="service-mobile-navigation"
                                onClick={() => setIsMenuOpen((open) => !open)}
                            >
                                {isMenuOpen ? (
                                    <X className="size-5" />
                                ) : (
                                    <Menu className="size-5" />
                                )}
                            </button>
                        </div>
                    </div>
                    {isMenuOpen && (
                        <div
                            id="service-mobile-navigation"
                            className="website-menu-enter border-t border-zinc-200/80 bg-[#f7f8f4] px-5 py-5 lg:hidden"
                        >
                            <nav
                                aria-label="Navegación móvil"
                                className="mx-auto grid max-w-7xl gap-1 text-lg font-semibold"
                            >
                                <Link
                                    href="/"
                                    onClick={closeMenu}
                                    className="flex items-center justify-between rounded-2xl px-4 py-3 transition hover:bg-white hover:text-[#217a3b]"
                                >
                                    Inicio
                                    <ChevronRight className="size-4" />
                                </Link>
                                {services.map((item) => (
                                    <Link
                                        key={item.slug}
                                        href={`/servicios/${item.slug}`}
                                        onClick={closeMenu}
                                        aria-current={
                                            item.slug === service.slug
                                                ? 'page'
                                                : undefined
                                        }
                                        className={`flex items-center justify-between rounded-2xl px-4 py-3 transition hover:bg-white hover:text-[#217a3b] ${
                                            item.slug === service.slug
                                                ? 'bg-white text-[#217a3b]'
                                                : ''
                                        }`}
                                    >
                                        {item.name}
                                        <ChevronRight className="size-4" />
                                    </Link>
                                ))}
                                <Link
                                    href={`/proyectos?servicio=${service.slug}`}
                                    onClick={closeMenu}
                                    className="flex items-center justify-between rounded-2xl px-4 py-3 transition hover:bg-white hover:text-[#217a3b]"
                                >
                                    Proyectos realizados
                                    <ChevronRight className="size-4" />
                                </Link>
                                <Link
                                    href="/#contacto"
                                    onClick={closeMenu}
                                    className="flex items-center justify-between rounded-2xl px-4 py-3 transition hover:bg-white hover:text-[#217a3b]"
                                >
                                    Contacto
                                    <ChevronRight className="size-4" />
                                </Link>
                                <Link
                                    href={contactUrl}
                                    onClick={closeMenu}
                                    className="mt-3 inline-flex items-center justify-center gap-2 rounded-full bg-zinc-950 px-5 py-3 text-sm text-white sm:hidden"
                                >
                                    Solicitar estudio
                                    <ArrowRight className="size-4" />
                                </Link>
                            </nav>
                        </div>
                    )}
                </header>

                <main id="contenido">
                    <section className="relative overflow-hidden bg-zinc-950 px-5 py-20 text-white sm:px-8 sm:py-28 lg:py-32">
                        <div className="absolute inset-0 [background-image:linear-gradient(rgba(255,255,255,.035)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,.035)_1px,transparent_1px)] [background-size:48px_48px] opacity-50" />
                        <div className="absolute -top-52 right-0 size-[34rem] rounded-full bg-[#217a3b]/25 blur-3xl" />
                        <div className="relative mx-auto grid max-w-7xl gap-14 lg:grid-cols-[1.25fr_.75fr] lg:items-end">
                            <div>
                                <div className="inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/5 px-4 py-2 text-sm text-zinc-300">
                                    <Icon className="size-4 text-[#87b550]" />
                                    {service.eyebrow}
                                </div>
                                <h1 className="mt-8 max-w-4xl text-5xl leading-[.98] font-semibold tracking-[-0.055em] sm:text-6xl lg:text-7xl">
                                    {service.title}
                                </h1>
                                <p className="mt-8 max-w-3xl text-lg leading-8 text-zinc-300 sm:text-xl">
                                    {service.description}
                                </p>
                                <div className="mt-10 flex flex-col gap-4 sm:flex-row">
                                    <Link
                                        href={contactUrl}
                                        className="group inline-flex items-center justify-center gap-2 rounded-full bg-[#217a3b] px-6 py-3.5 font-semibold text-white transition hover:-translate-y-0.5 hover:bg-[#185f2d]"
                                    >
                                        Cuéntanos tu proyecto
                                        <ArrowRight className="size-5 transition-transform group-hover:translate-x-1" />
                                    </Link>
                                    <a
                                        href="#soluciones"
                                        className="inline-flex items-center justify-center rounded-full border border-white/20 px-6 py-3.5 font-semibold text-white transition hover:border-white/45 hover:bg-white/5"
                                    >
                                        Ver alcance
                                    </a>
                                </div>
                            </div>
                            <div>
                                <figure className="overflow-hidden rounded-[2rem] border border-white/15 bg-white/[.07] shadow-2xl">
                                    <img
                                        src={service.image}
                                        alt={service.image_alt}
                                        width="1800"
                                        height="1200"
                                        loading="eager"
                                        fetchPriority="high"
                                        className="aspect-[4/3] h-full w-full object-cover"
                                    />
                                </figure>
                                <div className="mt-4 rounded-[2rem] border border-white/15 bg-white/[.07] p-7 backdrop-blur-xl sm:p-8">
                                    <p className="text-xs font-bold tracking-[.16em] text-[#87b550] uppercase">
                                        Para cada proyecto
                                    </p>
                                    <ul className="mt-6 grid gap-4">
                                        {service.deliverables.map((item) => (
                                            <li
                                                key={item}
                                                className="flex items-start gap-3 border-b border-white/10 pb-4 text-sm leading-6 text-zinc-200 last:border-0 last:pb-0"
                                            >
                                                <Check className="mt-1 size-4 shrink-0 text-[#87b550]" />
                                                {item}
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section className="px-5 py-20 sm:px-8 lg:py-28">
                        <div className="mx-auto grid max-w-7xl gap-12 lg:grid-cols-[.75fr_1.25fr] lg:items-start">
                            <div>
                                <p className="text-sm font-bold tracking-[.18em] text-[#217a3b] uppercase">
                                    Nuestro enfoque
                                </p>
                                <h2 className="mt-4 text-4xl leading-tight font-semibold tracking-[-.04em] sm:text-5xl">
                                    Primero entendemos. Después resolvemos.
                                </h2>
                            </div>
                            <p className="text-xl leading-9 text-zinc-600">
                                {service.intro}
                            </p>
                        </div>
                    </section>

                    <section
                        id="soluciones"
                        className="scroll-mt-24 bg-white px-5 py-20 sm:px-8 lg:py-28"
                    >
                        <div className="mx-auto max-w-7xl">
                            <div className="max-w-3xl">
                                <p className="text-sm font-bold tracking-[.18em] text-[#217a3b] uppercase">
                                    Qué hacemos
                                </p>
                                <h2 className="mt-4 text-4xl font-semibold tracking-[-.04em] sm:text-5xl">
                                    Soluciones dentro de este servicio
                                </h2>
                            </div>
                            <div className="mt-12 grid gap-5 lg:grid-cols-3">
                                {service.solutions.map((solution, index) => (
                                    <article
                                        key={solution.title}
                                        className="rounded-[2rem] border border-zinc-200 bg-[#f7f8f4] p-7 sm:p-8"
                                    >
                                        <span className="text-sm font-semibold text-[#217a3b]">
                                            0{index + 1}
                                        </span>
                                        <h3 className="mt-10 text-2xl font-semibold tracking-[-.03em]">
                                            {solution.title}
                                        </h3>
                                        <p className="mt-4 leading-7 text-zinc-600">
                                            {solution.description}
                                        </p>
                                    </article>
                                ))}
                            </div>
                        </div>
                    </section>

                    <section className="px-5 py-20 sm:px-8 lg:py-28">
                        <div className="mx-auto grid max-w-7xl overflow-hidden rounded-[2.5rem] bg-zinc-950 text-white lg:grid-cols-2">
                            <div className="p-8 sm:p-12 lg:p-14">
                                <p className="text-xs font-bold tracking-[.16em] text-[#87b550] uppercase">
                                    Ámbitos de trabajo
                                </p>
                                <h2 className="mt-5 text-4xl font-semibold tracking-[-.04em]">
                                    Una solución adaptada al espacio y a su uso
                                </h2>
                                <div className="mt-9 flex flex-wrap gap-3">
                                    {service.audiences.map((audience) => (
                                        <span
                                            key={audience}
                                            className="rounded-full border border-white/15 bg-white/5 px-4 py-2 text-sm text-zinc-200"
                                        >
                                            {audience}
                                        </span>
                                    ))}
                                </div>
                            </div>
                            <div className="flex flex-col justify-between bg-[#217a3b] p-8 sm:p-12 lg:p-14">
                                <MapPin className="size-9 text-[#b8df8c]" />
                                <div className="mt-16">
                                    <p className="text-lg leading-8 text-white/80">
                                        Trabajamos desde Baena para proyectos en
                                        Córdoba y el resto de Andalucía.
                                    </p>
                                    <Link
                                        href={contactUrl}
                                        className="mt-8 inline-flex items-center gap-2 text-lg font-semibold text-white"
                                    >
                                        Solicitar una valoración
                                        <ChevronRight className="size-5" />
                                    </Link>
                                </div>
                            </div>
                        </div>
                    </section>

                    {projects.length > 0 && (
                        <section className="bg-white px-5 py-20 sm:px-8 lg:py-28">
                            <div className="mx-auto max-w-7xl">
                                <div className="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                                    <div className="max-w-3xl">
                                        <p className="text-sm font-bold tracking-[.18em] text-[#217a3b] uppercase">
                                            Trabajo realizado
                                        </p>
                                        <h2 className="mt-4 text-4xl font-semibold tracking-[-.04em] sm:text-5xl">
                                            Proyectos de{' '}
                                            {service.short_name.toLowerCase()}
                                        </h2>
                                    </div>
                                    <Link
                                        href={`/proyectos?servicio=${service.slug}`}
                                        className="inline-flex items-center gap-2 font-semibold text-[#217a3b]"
                                    >
                                        Ver todos los proyectos
                                        <ArrowRight className="size-4" />
                                    </Link>
                                </div>
                                <div className="mt-12 grid gap-6 md:grid-cols-2 xl:grid-cols-3">
                                    {projects.map((project) => (
                                        <ProjectCard
                                            key={project.slug}
                                            project={project}
                                        />
                                    ))}
                                </div>
                            </div>
                        </section>
                    )}

                    <section className="px-5 py-20 sm:px-8 lg:py-28">
                        <div className="mx-auto grid max-w-7xl gap-12 lg:grid-cols-[.72fr_1.28fr]">
                            <div>
                                <p className="text-sm font-bold tracking-[.18em] text-[#217a3b] uppercase">
                                    Preguntas frecuentes
                                </p>
                                <h2 className="mt-4 text-4xl font-semibold tracking-[-.04em] sm:text-5xl">
                                    Antes de empezar
                                </h2>
                                <p className="mt-5 max-w-md leading-7 text-zinc-600">
                                    Resolvemos las dudas habituales y estudiamos
                                    cada caso antes de concretar el alcance.
                                </p>
                            </div>
                            <div className="divide-y divide-zinc-200 border-y border-zinc-200">
                                {service.faqs.map((faq) => (
                                    <details
                                        key={faq.question}
                                        className="group py-1"
                                    >
                                        <summary className="flex cursor-pointer list-none items-center justify-between gap-6 py-6 text-lg font-semibold marker:content-none">
                                            {faq.question}
                                            <span
                                                aria-hidden="true"
                                                className="grid size-8 shrink-0 place-items-center rounded-full border border-zinc-300 text-xl font-normal transition group-open:rotate-45 group-open:border-[#217a3b] group-open:text-[#217a3b]"
                                            >
                                                +
                                            </span>
                                        </summary>
                                        <p className="max-w-3xl pr-12 pb-7 leading-7 text-zinc-600">
                                            {faq.answer}
                                        </p>
                                    </details>
                                ))}
                            </div>
                        </div>
                    </section>

                    <section className="border-y border-zinc-200 bg-white px-5 py-12 sm:px-8">
                        <div className="mx-auto flex max-w-7xl flex-col gap-7 lg:flex-row lg:items-center lg:justify-between">
                            <div>
                                <p className="text-sm font-bold tracking-[.16em] text-[#217a3b] uppercase">
                                    Explora otros servicios
                                </p>
                                <p className="mt-2 text-xl font-semibold">
                                    Un mismo equipo para necesidades conectadas.
                                </p>
                            </div>
                            <nav
                                aria-label="Otros servicios"
                                className="flex flex-wrap gap-3"
                            >
                                {services
                                    .filter(
                                        (item) => item.slug !== service.slug,
                                    )
                                    .map((item) => (
                                        <Link
                                            key={item.slug}
                                            href={`/servicios/${item.slug}`}
                                            className="inline-flex items-center gap-2 rounded-full border border-zinc-200 px-5 py-3 text-sm font-semibold transition hover:border-[#217a3b] hover:text-[#217a3b]"
                                        >
                                            {item.name}
                                            <ArrowRight className="size-4" />
                                        </Link>
                                    ))}
                            </nav>
                        </div>
                    </section>
                </main>

                <footer className="px-5 py-10 sm:px-8">
                    <div className="mx-auto flex max-w-7xl flex-col gap-6 sm:flex-row sm:items-center sm:justify-between">
                        <Link href="/">
                            <img
                                src="/brand/arevantes-logo-light.svg"
                                alt="Arevantes"
                                className="h-7 w-auto"
                            />
                        </Link>
                        <div className="flex flex-wrap gap-5 text-sm text-zinc-600">
                            <Link href="/aviso-legal">Aviso legal</Link>
                            <Link href="/privacidad">Privacidad</Link>
                            <Link href="/cookies">Cookies</Link>
                        </div>
                    </div>
                </footer>
            </div>
        </>
    );
}

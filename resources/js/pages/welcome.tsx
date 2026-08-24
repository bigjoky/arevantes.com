import { Form, Head, Link, usePage } from '@inertiajs/react';
import {
    Activity,
    ArrowRight,
    ArrowUpRight,
    Check,
    ChevronRight,
    Gauge,
    MapPin,
    Menu,
    ShieldCheck,
    Sparkles,
    Sun,
    X,
    Zap,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { dashboard, login } from '@/routes';

const steps = [
    {
        number: '01',
        title: 'Escuchamos',
        description:
            'Revisamos tus necesidades, consumos y las condiciones reales del emplazamiento.',
    },
    {
        number: '02',
        title: 'Diseñamos',
        description:
            'Preparamos una solución clara, dimensionada y explicada antes de empezar.',
    },
    {
        number: '03',
        title: 'Instalamos',
        description:
            'Coordinamos materiales, ejecución, puesta en marcha y documentación.',
    },
    {
        number: '04',
        title: 'Acompañamos',
        description:
            'Seguimos cerca con monitorización, mantenimiento y atención posventa.',
    },
];

const businessLines = [
    {
        number: '01',
        icon: Sun,
        label: 'Energía',
        cardTitle: 'Energía y movilidad',
        title: 'Produce, almacena y aprovecha tu energía',
        description:
            'Fotovoltaica, baterías y recarga coordinadas para reducir consumo y ganar autonomía.',
        accent: 'Autoconsumo',
        detail: 'Solar · Baterías · Recarga',
        services: ['Fotovoltaica', 'Almacenamiento', 'Puntos de recarga'],
        formValue: 'solar',
        path: '/servicios/energia',
    },
    {
        number: '02',
        icon: Zap,
        label: 'Electricidad',
        cardTitle: 'Instalaciones eléctricas',
        title: 'Una instalación eléctrica preparada para crecer',
        description:
            'Cuadros, circuitos, adecuaciones y certificación con ejecución técnica y trazable.',
        accent: 'Nueva línea',
        detail: 'Instalación · Reforma · Certificación',
        services: ['Instalaciones', 'Adecuaciones', 'Certificación'],
        formValue: 'electrical',
        path: '/servicios/electricidad',
    },
    {
        number: '03',
        icon: ShieldCheck,
        label: 'Seguridad',
        cardTitle: 'Alarmas y seguridad',
        title: 'Protección conectada a cada espacio',
        description:
            'Alarmas, videovigilancia y control de accesos dimensionados para viviendas y negocios.',
        accent: 'Nueva línea',
        detail: 'Alarmas · CCTV · Accesos',
        services: ['Alarmas', 'Videovigilancia', 'Control de accesos'],
        formValue: 'security',
        path: '/servicios/seguridad',
    },
];

const siteDescription =
    'Arevantes diseña, instala y mantiene soluciones de energía, instalaciones eléctricas y sistemas de seguridad en Córdoba y Andalucía.';

const siteTitle = 'Arevantes · Energía, electricidad y seguridad';
const contactServiceValues = new Set(['solar', 'electrical', 'security']);

export default function Welcome({ siteUrl }: { siteUrl: string }) {
    const { auth } = usePage().props;
    const privateArea = auth.user ? dashboard() : login();
    const [isMenuOpen, setIsMenuOpen] = useState(false);
    const [activeModeIndex, setActiveModeIndex] = useState(0);
    const [selectedService, setSelectedService] = useState(() => {
        if (typeof window === 'undefined') {
            return '';
        }

        const requestedService = new URLSearchParams(
            window.location.search,
        ).get('servicio');

        return requestedService && contactServiceValues.has(requestedService)
            ? requestedService
            : '';
    });
    const activeMode = businessLines[activeModeIndex];
    const ActiveModeIcon = activeMode.icon;

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
                <title head-key="title">{siteTitle}</title>
                <meta
                    head-key="description"
                    name="description"
                    content={siteDescription}
                />
                <meta head-key="robots" name="robots" content="index, follow" />
                <link head-key="canonical" rel="canonical" href={siteUrl} />
                <meta head-key="og-type" property="og:type" content="website" />
                <meta
                    head-key="og-locale"
                    property="og:locale"
                    content="es_ES"
                />
                <meta
                    head-key="og-site-name"
                    property="og:site_name"
                    content="Arevantes"
                />
                <meta
                    head-key="og-title"
                    property="og:title"
                    content={siteTitle}
                />
                <meta
                    head-key="og-description"
                    property="og:description"
                    content={siteDescription}
                />
                <meta head-key="og-url" property="og:url" content={siteUrl} />
                <meta
                    head-key="og-image"
                    property="og:image"
                    content={`${siteUrl}/og-multirramo.png`}
                />
                <meta
                    head-key="og-image-width"
                    property="og:image:width"
                    content="1200"
                />
                <meta
                    head-key="og-image-height"
                    property="og:image:height"
                    content="630"
                />
                <meta
                    head-key="og-image-alt"
                    property="og:image:alt"
                    content="Arevantes: energía, electricidad y seguridad"
                />
                <meta
                    head-key="twitter-card"
                    name="twitter:card"
                    content="summary_large_image"
                />
                <meta
                    head-key="twitter-title"
                    name="twitter:title"
                    content={siteTitle}
                />
                <meta
                    head-key="twitter-description"
                    name="twitter:description"
                    content={siteDescription}
                />
                <meta
                    head-key="twitter-image"
                    name="twitter:image"
                    content={`${siteUrl}/og-multirramo.png`}
                />
                <script head-key="structured-data" type="application/ld+json">
                    {JSON.stringify({
                        '@context': 'https://schema.org',
                        '@type': 'LocalBusiness',
                        name: 'Arevantes',
                        legalName: 'AREVANTES, S.L.',
                        url: siteUrl,
                        logo: `${siteUrl}/brand/arevantes-logo-light.svg`,
                        email: 'contact@arevantes.com',
                        address: {
                            '@type': 'PostalAddress',
                            streetAddress: 'Calle Arco de la Villa 8',
                            postalCode: '14850',
                            addressLocality: 'Baena',
                            addressRegion: 'Córdoba',
                            addressCountry: 'ES',
                        },
                        areaServed: 'Andalucía',
                        hasOfferCatalog: {
                            '@type': 'OfferCatalog',
                            name: 'Servicios Arevantes',
                            itemListElement: businessLines.map((line) => ({
                                '@type': 'OfferCatalog',
                                name: line.cardTitle,
                                itemListElement: line.services.map(
                                    (service) => ({
                                        '@type': 'Offer',
                                        itemOffered: {
                                            '@type': 'Service',
                                            name: service,
                                        },
                                    }),
                                ),
                            })),
                        },
                    })}
                </script>
            </Head>

            <div className="min-h-screen bg-[#f7f8f4] text-zinc-950 antialiased">
                <a
                    href="#main-content"
                    className="fixed top-3 left-3 z-[100] -translate-y-20 rounded-full bg-zinc-950 px-5 py-3 font-semibold text-white shadow-xl transition-transform focus:translate-y-0"
                >
                    Saltar al contenido
                </a>
                <header className="sticky top-0 z-50 border-b border-zinc-200/70 bg-[#f7f8f4]/85 backdrop-blur-2xl">
                    <div className="mx-auto flex h-20 max-w-7xl items-center justify-between px-5 sm:px-8 lg:px-10">
                        <a
                            href="#inicio"
                            aria-label="Arevantes, inicio"
                            onClick={closeMenu}
                        >
                            <img
                                src="/brand/arevantes-logo-light.svg"
                                alt="Arevantes"
                                className="h-7 w-auto sm:h-8"
                            />
                        </a>

                        <nav
                            aria-label="Navegación principal"
                            className="hidden items-center gap-8 text-sm font-medium text-zinc-600 md:flex"
                        >
                            <a
                                href="#inicio"
                                aria-current="page"
                                className="text-[#217a3b]"
                            >
                                Inicio
                            </a>
                            <a
                                href="#servicios"
                                className="transition hover:text-[#217a3b]"
                            >
                                Servicios
                            </a>
                            <a
                                href="#metodo"
                                className="transition hover:text-[#217a3b]"
                            >
                                Cómo trabajamos
                            </a>
                            <a
                                href="#contacto"
                                className="transition hover:text-[#217a3b]"
                            >
                                Contacto
                            </a>
                        </nav>

                        <div className="flex items-center gap-2">
                            <Link
                                href={privateArea}
                                className="hidden items-center gap-2 rounded-full border border-zinc-300 bg-white px-4 py-2 text-sm font-semibold shadow-sm transition hover:border-[#289548] hover:text-[#217a3b] sm:inline-flex"
                            >
                                {auth.user ? 'Ir al panel' : 'Área privada'}
                                <ChevronRight className="size-4" />
                            </Link>
                            <button
                                type="button"
                                className="grid size-11 place-items-center rounded-full border border-zinc-300 bg-white text-zinc-900 shadow-sm transition hover:border-[#289548] hover:text-[#217a3b] md:hidden"
                                aria-label={
                                    isMenuOpen
                                        ? 'Cerrar navegación'
                                        : 'Abrir navegación'
                                }
                                aria-expanded={isMenuOpen}
                                aria-controls="mobile-navigation"
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
                            id="mobile-navigation"
                            className="website-menu-enter border-t border-zinc-200/80 bg-[#f7f8f4] px-5 py-5 md:hidden"
                        >
                            <nav
                                aria-label="Navegación móvil"
                                className="mx-auto grid max-w-7xl gap-1 text-lg font-semibold"
                            >
                                {[
                                    ['Inicio', '#inicio'],
                                    ['Servicios', '#servicios'],
                                    ['Cómo trabajamos', '#metodo'],
                                    ['Contacto', '#contacto'],
                                ].map(([label, href]) => (
                                    <a
                                        key={href}
                                        href={href}
                                        onClick={closeMenu}
                                        className="flex items-center justify-between rounded-2xl px-4 py-3 transition hover:bg-white hover:text-[#217a3b]"
                                    >
                                        {label}
                                        <ArrowUpRight className="size-4" />
                                    </a>
                                ))}
                                <Link
                                    href={privateArea}
                                    className="mt-3 inline-flex items-center justify-center gap-2 rounded-full bg-zinc-950 px-5 py-3 text-sm text-white sm:hidden"
                                >
                                    {auth.user ? 'Ir al panel' : 'Área privada'}
                                    <ChevronRight className="size-4" />
                                </Link>
                            </nav>
                        </div>
                    )}
                </header>

                <main id="main-content" tabIndex={-1}>
                    <section
                        id="inicio"
                        className="relative isolate scroll-mt-20 overflow-hidden bg-zinc-950 text-white"
                    >
                        <div className="website-aurora absolute inset-0 -z-20 bg-[radial-gradient(circle_at_78%_28%,rgba(40,149,72,0.30),transparent_34%),radial-gradient(circle_at_15%_85%,rgba(135,181,80,0.16),transparent_30%)]" />
                        <div className="absolute inset-0 -z-10 [background-image:linear-gradient(rgba(255,255,255,.6)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,.6)_1px,transparent_1px)] [background-size:56px_56px] opacity-[0.08]" />

                        <div className="mx-auto grid min-h-[calc(100svh-5rem)] max-w-7xl items-center gap-14 px-5 py-16 sm:px-8 sm:py-20 lg:grid-cols-[1.04fr_.96fr] lg:px-10 lg:py-24">
                            <div className="website-rise max-w-3xl">
                                <div className="mb-7 inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/5 px-4 py-2 text-sm text-zinc-300">
                                    <span className="size-2 rounded-full bg-[#87b550] shadow-[0_0_16px_#87b550]" />
                                    Energía · Electricidad · Seguridad
                                </div>
                                <h1 className="text-5xl leading-[0.98] font-semibold tracking-[-0.055em] text-balance sm:text-6xl lg:text-7xl xl:text-[5.4rem]">
                                    Tres especialidades.
                                    <span className="mt-2 block text-[#87b550]">
                                        Una ejecución impecable.
                                    </span>
                                </h1>
                                <p className="mt-8 max-w-2xl text-lg leading-8 text-zinc-300 sm:text-xl">
                                    Diseñamos, instalamos y mantenemos
                                    soluciones de energía, electricidad y
                                    seguridad con un único equipo responsable de
                                    principio a fin.
                                </p>
                                <div className="mt-10 flex flex-col gap-4 sm:flex-row">
                                    <a
                                        href="#contacto"
                                        className="group inline-flex items-center justify-center gap-2 rounded-full bg-[#217a3b] px-6 py-3.5 font-semibold text-white shadow-[0_14px_40px_rgba(40,149,72,.28)] transition hover:-translate-y-0.5 hover:bg-[#185f2d]"
                                    >
                                        Cuéntanos tu proyecto
                                        <ArrowRight className="size-5 transition-transform group-hover:translate-x-1" />
                                    </a>
                                    <a
                                        href="#servicios"
                                        className="inline-flex items-center justify-center rounded-full border border-white/20 px-6 py-3.5 font-semibold text-white transition hover:border-white/45 hover:bg-white/5"
                                    >
                                        Ver servicios
                                    </a>
                                </div>

                                <div className="mt-14 flex flex-wrap gap-x-8 gap-y-4 border-t border-white/10 pt-7 text-sm text-zinc-300">
                                    <span className="flex items-center gap-2">
                                        <Check className="size-4 text-[#87b550]" />
                                        Diseño a medida
                                    </span>
                                    <span className="flex items-center gap-2">
                                        <Check className="size-4 text-[#87b550]" />
                                        Ejecución integral
                                    </span>
                                    <span className="flex items-center gap-2">
                                        <Check className="size-4 text-[#87b550]" />
                                        Seguimiento posventa
                                    </span>
                                </div>
                            </div>

                            <div className="website-rise relative mx-auto w-full max-w-xl [animation-delay:160ms]">
                                <div className="absolute -inset-12 rounded-full bg-[#289548]/20 blur-3xl" />
                                <div className="relative overflow-hidden rounded-[2rem] border border-white/15 bg-white/[0.07] p-4 shadow-2xl backdrop-blur-xl sm:p-5">
                                    <div className="flex items-center justify-between px-1 pb-4">
                                        <div className="flex items-center gap-2 text-xs font-medium tracking-[0.12em] text-zinc-300 uppercase">
                                            <Activity className="size-4 text-[#87b550]" />
                                            Elige tu línea de proyecto
                                        </div>
                                        <span className="flex items-center gap-2 rounded-full bg-[#87b550]/10 px-3 py-1 text-xs text-[#b8df8c]">
                                            <span
                                                aria-hidden="true"
                                                className="website-status-dot size-1.5 rounded-full bg-[#87b550]"
                                            />
                                            En evolución
                                        </span>
                                    </div>

                                    <div className="relative overflow-hidden rounded-[1.5rem] border border-white/10 bg-[#08110c]/80 p-5 sm:p-7">
                                        <div className="website-energy-orbit absolute -top-16 -right-16 size-52 rounded-full border border-[#87b550]/25" />
                                        <div className="website-energy-orbit absolute -top-3 -right-3 size-28 rounded-full border border-[#289548]/20 [animation-direction:reverse]" />
                                        <div
                                            key={activeMode.label}
                                            className="website-mode-enter relative min-h-64"
                                        >
                                            <div className="flex items-start justify-between gap-6">
                                                <span className="grid size-14 shrink-0 place-items-center rounded-2xl border border-[#87b550]/25 bg-[#87b550]/10 text-[#a8d878] shadow-[0_0_36px_rgba(135,181,80,.12)]">
                                                    <ActiveModeIcon className="size-7" />
                                                </span>
                                                <span className="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs text-zinc-300">
                                                    {activeMode.accent}
                                                </span>
                                            </div>
                                            <p className="mt-10 text-xs font-semibold tracking-[0.16em] text-[#87b550] uppercase">
                                                {activeMode.detail}
                                            </p>
                                            <h2 className="mt-3 max-w-sm text-2xl leading-tight font-semibold tracking-[-0.03em] sm:text-3xl">
                                                {activeMode.title}
                                            </h2>
                                            <p className="mt-3 max-w-md text-sm leading-6 text-zinc-400 sm:text-base">
                                                {activeMode.description}
                                            </p>
                                            <Link
                                                href={activeMode.path}
                                                className="mt-6 inline-flex items-center gap-2 text-sm font-semibold text-[#b8df8c] transition hover:text-white"
                                            >
                                                Conocer este servicio
                                                <ArrowRight className="size-4" />
                                            </Link>
                                        </div>

                                        <div className="relative mt-5 grid grid-cols-3 gap-2 border-t border-white/10 pt-5">
                                            {businessLines.map(
                                                (mode, index) => (
                                                    <button
                                                        key={mode.label}
                                                        type="button"
                                                        onClick={() =>
                                                            setActiveModeIndex(
                                                                index,
                                                            )
                                                        }
                                                        aria-label={`Mostrar solución: ${mode.label}`}
                                                        aria-pressed={
                                                            activeModeIndex ===
                                                            index
                                                        }
                                                        className={`rounded-xl px-2 py-2.5 text-xs font-medium transition sm:text-sm ${
                                                            activeModeIndex ===
                                                            index
                                                                ? 'bg-[#217a3b] text-white shadow-lg'
                                                                : 'bg-white/5 text-zinc-400 hover:bg-white/10 hover:text-white'
                                                        }`}
                                                    >
                                                        {mode.label}
                                                    </button>
                                                ),
                                            )}
                                        </div>
                                    </div>

                                    <div className="mt-3 grid grid-cols-2 gap-3">
                                        <div className="rounded-2xl border border-white/10 bg-black/20 p-4">
                                            <Gauge className="mb-4 size-5 text-[#87b550]" />
                                            <p className="text-sm font-medium">
                                                Control sencillo
                                            </p>
                                        </div>
                                        <div className="rounded-2xl border border-white/10 bg-black/20 p-4">
                                            <ShieldCheck className="mb-4 size-5 text-[#87b550]" />
                                            <p className="text-sm font-medium">
                                                Soporte cercano
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div className="border-t border-white/10 bg-black/15">
                            <div className="mx-auto flex max-w-7xl flex-wrap items-center justify-center gap-x-8 gap-y-3 px-5 py-4 text-xs font-medium tracking-[0.13em] text-zinc-400 uppercase sm:px-8 lg:justify-between lg:px-10">
                                {[
                                    'Energía solar',
                                    'Instalaciones eléctricas',
                                    'Alarmas y seguridad',
                                    'Mantenimiento integral',
                                ].map((item) => (
                                    <span
                                        key={item}
                                        className="flex items-center gap-2"
                                    >
                                        <Sparkles className="size-3 text-[#87b550]" />
                                        {item}
                                    </span>
                                ))}
                            </div>
                        </div>
                    </section>

                    <section
                        id="servicios"
                        className="scroll-mt-20 px-5 py-24 sm:px-8 lg:py-32"
                    >
                        <div className="mx-auto max-w-7xl">
                            <div className="grid gap-8 lg:grid-cols-[.8fr_1.2fr] lg:items-end">
                                <div>
                                    <p className="text-sm font-bold tracking-[0.18em] text-[#217a3b] uppercase">
                                        Tres líneas, un solo equipo
                                    </p>
                                    <h2 className="mt-4 text-4xl leading-tight font-semibold tracking-[-0.04em] sm:text-5xl">
                                        La especialidad que necesitas. La
                                        coordinación que esperas.
                                    </h2>
                                </div>
                                <p className="max-w-2xl text-lg leading-8 text-zinc-600 lg:justify-self-end">
                                    Cada línea tiene su propio alcance técnico,
                                    pero todas comparten el mismo método:
                                    estudio real, ejecución documentada y
                                    continuidad después de la puesta en marcha.
                                </p>
                            </div>

                            <div className="mt-14 grid gap-5 lg:grid-cols-3">
                                {businessLines.map((line) => (
                                    <article
                                        key={line.label}
                                        className="group relative flex min-h-[31rem] flex-col overflow-hidden rounded-[2rem] border border-zinc-200 bg-white p-7 shadow-[0_12px_40px_rgba(24,24,27,.04)] transition duration-500 hover:-translate-y-2 hover:border-[#289548]/40 hover:shadow-[0_24px_60px_rgba(40,149,72,.12)] sm:p-8"
                                    >
                                        <div className="absolute -top-16 -right-20 size-52 rounded-full border-[38px] border-[#87b550]/0 transition duration-500 group-hover:border-[#87b550]/10" />
                                        <div className="flex items-start justify-between">
                                            <span className="grid size-14 place-items-center rounded-2xl bg-[#eaf5ec] text-[#217a3b] transition duration-500 group-hover:scale-110 group-hover:rotate-3">
                                                <line.icon className="size-7" />
                                            </span>
                                            <span className="text-sm font-medium text-zinc-400">
                                                {line.number}
                                            </span>
                                        </div>
                                        <div className="pt-12">
                                            <p className="text-xs font-bold tracking-[0.16em] text-[#217a3b] uppercase">
                                                {line.detail}
                                            </p>
                                            <h3 className="mt-4 text-3xl font-semibold tracking-[-0.035em]">
                                                {line.cardTitle}
                                            </h3>
                                            <p className="mt-4 leading-7 text-zinc-600">
                                                {line.description}
                                            </p>
                                            <ul className="mt-7 grid gap-3 border-t border-zinc-100 pt-6 text-sm font-medium text-zinc-700">
                                                {line.services.map(
                                                    (service) => (
                                                        <li
                                                            key={service}
                                                            className="flex items-center gap-3"
                                                        >
                                                            <Check className="size-4 text-[#217a3b]" />
                                                            {service}
                                                        </li>
                                                    ),
                                                )}
                                            </ul>
                                        </div>
                                        <div className="mt-auto flex flex-wrap items-center gap-x-5 gap-y-3 pt-8">
                                            <Link
                                                href={line.path}
                                                className="inline-flex items-center gap-2 text-sm font-semibold text-[#217a3b] transition group-hover:text-[#185f2d]"
                                            >
                                                Ver servicio
                                                <ArrowUpRight className="size-4 transition-transform group-hover:translate-x-0.5 group-hover:-translate-y-0.5" />
                                            </Link>
                                            <a
                                                href="#contacto"
                                                onClick={() =>
                                                    setSelectedService(
                                                        line.formValue,
                                                    )
                                                }
                                                className="text-sm font-medium text-zinc-500 transition hover:text-zinc-950"
                                            >
                                                Solicitar estudio
                                            </a>
                                        </div>
                                    </article>
                                ))}
                            </div>

                            <div className="mt-5 grid gap-8 overflow-hidden rounded-[2rem] bg-zinc-950 p-7 text-white sm:p-9 lg:grid-cols-[.8fr_1.2fr] lg:items-center">
                                <div>
                                    <span className="grid size-12 place-items-center rounded-2xl bg-[#87b550]/15 text-[#b8df8c]">
                                        <Activity className="size-6" />
                                    </span>
                                    <p className="mt-8 text-xs font-bold tracking-[0.16em] text-[#87b550] uppercase">
                                        Capacidad transversal
                                    </p>
                                    <h3 className="mt-3 text-3xl font-semibold tracking-[-0.035em]">
                                        Mantenimiento y continuidad
                                    </h3>
                                </div>
                                <div>
                                    <p className="max-w-2xl text-lg leading-8 text-zinc-300">
                                        El proyecto no termina al ponerlo en
                                        marcha. Mantenemos cerca el seguimiento,
                                        la documentación y la atención técnica
                                        para cualquiera de las tres líneas.
                                    </p>
                                    <div className="mt-6 flex flex-wrap gap-3 text-sm text-zinc-300">
                                        {[
                                            'Monitorización',
                                            'Mantenimiento preventivo',
                                            'Atención posventa',
                                        ].map((item) => (
                                            <span
                                                key={item}
                                                className="rounded-full border border-white/15 px-4 py-2"
                                            >
                                                {item}
                                            </span>
                                        ))}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section className="overflow-hidden bg-[#eaf1e4] px-5 py-24 sm:px-8 lg:py-32">
                        <div className="mx-auto grid max-w-7xl gap-14 lg:grid-cols-2 lg:items-center">
                            <div className="relative min-h-[28rem] overflow-hidden rounded-[2rem] bg-[#16301e] p-8 text-white sm:p-10">
                                <div className="absolute -right-24 -bottom-24 size-80 rounded-full border-[52px] border-[#87b550]/20" />
                                <div className="absolute top-10 right-10 size-28 rounded-full bg-[#289548]/30 blur-2xl" />
                                <Activity className="size-10 text-[#87b550]" />
                                <div className="absolute right-8 bottom-8 left-8 sm:right-10 sm:bottom-10 sm:left-10">
                                    <p className="max-w-lg text-3xl leading-tight font-medium tracking-[-0.03em] sm:text-4xl">
                                        Una instalación bien resuelta se
                                        entiende, se documenta y sigue
                                        respondiendo mañana.
                                    </p>
                                </div>
                            </div>

                            <div className="lg:pl-10">
                                <p className="text-sm font-bold tracking-[0.18em] text-[#217a3b] uppercase">
                                    Una forma de trabajar
                                </p>
                                <h2 className="mt-4 text-4xl leading-tight font-semibold tracking-[-0.04em] sm:text-5xl">
                                    Especialistas en cada línea. Responsables
                                    del conjunto.
                                </h2>
                                <p className="mt-6 text-lg leading-8 text-zinc-700">
                                    Energía, electricidad y seguridad exigen
                                    conocimientos distintos. Las abordamos con
                                    un mismo estándar de análisis, coordinación,
                                    documentación y atención posventa.
                                </p>
                                <div className="mt-9 grid gap-5 sm:grid-cols-2">
                                    {[
                                        [
                                            'Estudio real',
                                            'Partimos de datos y condiciones concretas.',
                                        ],
                                        [
                                            'Visión integral',
                                            'Coordinamos todas las fases del proyecto.',
                                        ],
                                        [
                                            'Información clara',
                                            'Sabes qué instalamos y por qué.',
                                        ],
                                        [
                                            'Continuidad',
                                            'La relación no termina en la puesta en marcha.',
                                        ],
                                    ].map(([title, text]) => (
                                        <div
                                            key={title}
                                            className="border-l-2 border-[#289548] pl-4"
                                        >
                                            <h3 className="font-semibold">
                                                {title}
                                            </h3>
                                            <p className="mt-1 text-sm leading-6 text-zinc-600">
                                                {text}
                                            </p>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        </div>
                    </section>

                    <section
                        id="metodo"
                        className="scroll-mt-20 bg-white px-5 py-24 sm:px-8 lg:py-32"
                    >
                        <div className="mx-auto max-w-7xl">
                            <div className="max-w-2xl">
                                <p className="text-sm font-bold tracking-[0.18em] text-[#217a3b] uppercase">
                                    Cómo trabajamos
                                </p>
                                <h2 className="mt-4 text-4xl leading-tight font-semibold tracking-[-0.04em] sm:text-5xl">
                                    De la primera conversación al seguimiento.
                                </h2>
                            </div>

                            <div className="mt-14 grid gap-px overflow-hidden rounded-[1.75rem] border border-zinc-200 bg-zinc-200 md:grid-cols-2 lg:grid-cols-4">
                                {steps.map((step) => (
                                    <article
                                        key={step.number}
                                        className="group relative bg-white p-7 transition duration-300 hover:bg-[#f5f9f2] sm:p-8"
                                    >
                                        <span className="inline-flex size-9 items-center justify-center rounded-full bg-[#eaf5ec] text-sm font-bold text-[#217a3b] transition group-hover:bg-[#217a3b] group-hover:text-white">
                                            {step.number}
                                        </span>
                                        <h3 className="mt-12 text-2xl font-semibold tracking-tight">
                                            {step.title}
                                        </h3>
                                        <p className="mt-3 leading-7 text-zinc-600">
                                            {step.description}
                                        </p>
                                    </article>
                                ))}
                            </div>
                        </div>
                    </section>

                    <section
                        id="contacto"
                        className="scroll-mt-20 px-5 py-8 sm:px-8 lg:pb-10"
                    >
                        <div className="relative mx-auto max-w-7xl overflow-hidden rounded-[2.25rem] bg-[#217a3b] px-6 py-12 text-white sm:px-12 sm:py-16 lg:px-20">
                            <div className="absolute -top-40 -right-32 size-96 rounded-full border-[70px] border-white/10" />
                            <div className="relative grid gap-12 lg:grid-cols-[.85fr_1.15fr] lg:items-center">
                                <div className="max-w-3xl">
                                    <p className="text-sm font-bold tracking-[0.18em] text-white/70 uppercase">
                                        Hablemos
                                    </p>
                                    <h2 className="mt-4 text-4xl leading-tight font-semibold tracking-[-0.04em] sm:text-5xl lg:text-6xl">
                                        ¿Tienes un proyecto en mente?
                                    </h2>
                                    <p className="mt-5 max-w-xl text-lg leading-8 text-white/80">
                                        Elige tu línea, cuéntanos qué necesitas
                                        y empezaremos por entender bien tu caso.
                                    </p>
                                    <a
                                        href="mailto:contact@arevantes.com"
                                        className="mt-8 inline-flex items-center gap-2 text-sm font-semibold text-white/80 transition hover:text-white"
                                    >
                                        contact@arevantes.com
                                        <ArrowRight className="size-4" />
                                    </a>
                                </div>

                                <Form
                                    action="/contact"
                                    method="post"
                                    className="rounded-[1.75rem] bg-white p-6 text-zinc-950 shadow-2xl sm:p-8"
                                >
                                    {({ errors, processing, wasSuccessful }) =>
                                        wasSuccessful ? (
                                            <div
                                                className="flex min-h-96 flex-col items-center justify-center text-center"
                                                role="status"
                                                aria-live="polite"
                                            >
                                                <span className="grid size-14 place-items-center rounded-full bg-[#eaf5ec] text-[#217a3b]">
                                                    <Check className="size-7" />
                                                </span>
                                                <h3 className="mt-6 text-2xl font-semibold">
                                                    Solicitud recibida
                                                </h3>
                                                <p className="mt-3 max-w-sm leading-7 text-zinc-600">
                                                    Gracias por contarnos tu
                                                    proyecto. Nuestro equipo la
                                                    revisará y se pondrá en
                                                    contacto contigo.
                                                </p>
                                            </div>
                                        ) : (
                                            <>
                                                <div className="grid gap-5 sm:grid-cols-2">
                                                    <ContactField
                                                        label="Nombre"
                                                        name="name"
                                                        autoComplete="name"
                                                        error={errors.name}
                                                    />
                                                    <ContactField
                                                        label="Correo electrónico"
                                                        name="email"
                                                        type="email"
                                                        autoComplete="email"
                                                        error={errors.email}
                                                    />
                                                    <ContactField
                                                        label="Teléfono"
                                                        name="phone"
                                                        type="tel"
                                                        autoComplete="tel"
                                                        error={errors.phone}
                                                    />
                                                    <ContactField
                                                        label="Código postal"
                                                        name="postal_code"
                                                        inputMode="numeric"
                                                        autoComplete="postal-code"
                                                        error={
                                                            errors.postal_code
                                                        }
                                                    />
                                                </div>

                                                <label
                                                    htmlFor="service"
                                                    className="mt-5 grid gap-2 text-sm font-medium"
                                                >
                                                    ¿En qué podemos ayudarte?
                                                    <select
                                                        id="service"
                                                        name="service"
                                                        value={selectedService}
                                                        onChange={(event) =>
                                                            setSelectedService(
                                                                event.target
                                                                    .value,
                                                            )
                                                        }
                                                        aria-invalid={
                                                            errors.service
                                                                ? true
                                                                : undefined
                                                        }
                                                        aria-describedby={
                                                            errors.service
                                                                ? 'service-error'
                                                                : undefined
                                                        }
                                                        className="h-12 rounded-xl border border-zinc-300 bg-white px-3 font-normal transition outline-none focus:border-[#217a3b] focus:ring-3 focus:ring-[#217a3b]/25"
                                                        required
                                                    >
                                                        <option
                                                            value=""
                                                            disabled
                                                        >
                                                            Selecciona un
                                                            servicio
                                                        </option>
                                                        <option value="solar">
                                                            Instalación
                                                            fotovoltaica
                                                        </option>
                                                        <option value="storage_charging">
                                                            Recarga y
                                                            almacenamiento
                                                        </option>
                                                        <option value="hvac">
                                                            Climatización
                                                        </option>
                                                        <option value="electrical">
                                                            Instalaciones
                                                            eléctricas
                                                        </option>
                                                        <option value="security">
                                                            Alarmas y sistemas
                                                            de seguridad
                                                        </option>
                                                        <option value="maintenance">
                                                            Mantenimiento
                                                        </option>
                                                    </select>
                                                    {errors.service && (
                                                        <span
                                                            id="service-error"
                                                            role="alert"
                                                            className="text-xs text-red-700"
                                                        >
                                                            {errors.service}
                                                        </span>
                                                    )}
                                                </label>

                                                <label
                                                    htmlFor="message"
                                                    className="mt-5 grid gap-2 text-sm font-medium"
                                                >
                                                    Cuéntanos brevemente tu
                                                    proyecto
                                                    <textarea
                                                        id="message"
                                                        name="message"
                                                        rows={4}
                                                        minLength={20}
                                                        maxLength={2000}
                                                        aria-invalid={
                                                            errors.message
                                                                ? true
                                                                : undefined
                                                        }
                                                        aria-describedby={
                                                            errors.message
                                                                ? 'message-error'
                                                                : undefined
                                                        }
                                                        className="resize-none rounded-xl border border-zinc-300 bg-white p-3 font-normal transition outline-none focus:border-[#217a3b] focus:ring-3 focus:ring-[#217a3b]/25"
                                                        required
                                                    />
                                                    {errors.message && (
                                                        <span
                                                            id="message-error"
                                                            role="alert"
                                                            className="text-xs text-red-700"
                                                        >
                                                            {errors.message}
                                                        </span>
                                                    )}
                                                </label>

                                                <div
                                                    className="absolute -left-[10000px]"
                                                    aria-hidden="true"
                                                >
                                                    <label>
                                                        Empresa web
                                                        <input
                                                            name="company_url"
                                                            tabIndex={-1}
                                                            autoComplete="off"
                                                        />
                                                    </label>
                                                </div>

                                                <label
                                                    htmlFor="privacy"
                                                    className="mt-5 flex items-start gap-3 text-sm leading-6 text-zinc-600"
                                                >
                                                    <input
                                                        id="privacy"
                                                        type="checkbox"
                                                        name="privacy"
                                                        value="1"
                                                        aria-invalid={
                                                            errors.privacy
                                                                ? true
                                                                : undefined
                                                        }
                                                        aria-describedby={
                                                            errors.privacy
                                                                ? 'privacy-error'
                                                                : undefined
                                                        }
                                                        className="mt-1 size-4 rounded border-zinc-300 accent-[#217a3b]"
                                                        required
                                                    />
                                                    <span>
                                                        Acepto que Arevantes
                                                        utilice estos datos para
                                                        responder a mi
                                                        solicitud, según la{' '}
                                                        <Link
                                                            href="/privacidad"
                                                            className="font-semibold text-[#217a3b] underline decoration-[#289548]/40 underline-offset-4"
                                                        >
                                                            política de
                                                            privacidad
                                                        </Link>
                                                        .
                                                        {errors.privacy && (
                                                            <span
                                                                id="privacy-error"
                                                                role="alert"
                                                                className="mt-1 block text-xs text-red-700"
                                                            >
                                                                {errors.privacy}
                                                            </span>
                                                        )}
                                                    </span>
                                                </label>

                                                <button
                                                    type="submit"
                                                    disabled={processing}
                                                    aria-busy={processing}
                                                    className="mt-6 inline-flex w-full items-center justify-center gap-2 rounded-full bg-zinc-950 px-6 py-3.5 font-semibold text-white transition hover:bg-zinc-800 disabled:cursor-wait disabled:opacity-60"
                                                >
                                                    {processing
                                                        ? 'Enviando…'
                                                        : 'Enviar solicitud'}
                                                    {!processing && (
                                                        <ArrowRight className="size-5" />
                                                    )}
                                                </button>
                                            </>
                                        )
                                    }
                                </Form>
                            </div>
                        </div>
                    </section>
                </main>

                <footer className="px-5 py-12 sm:px-8">
                    <div className="mx-auto flex max-w-7xl flex-col gap-8 border-t border-zinc-200 pt-10 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <img
                                src="/brand/arevantes-logo-light.svg"
                                alt="Arevantes"
                                className="h-7 w-auto"
                            />
                            <p className="mt-4 max-w-sm text-sm leading-6 text-zinc-600">
                                Instalaciones energéticas pensadas para durar.
                            </p>
                        </div>
                        <div className="flex flex-col gap-2 text-sm text-zinc-600 sm:items-end">
                            <span className="flex items-center gap-2">
                                <MapPin className="size-4 text-[#289548]" />
                                Baena · Córdoba · Andalucía
                            </span>
                            <a
                                href="mailto:contact@arevantes.com"
                                className="transition hover:text-[#217a3b]"
                            >
                                contact@arevantes.com
                            </a>
                        </div>
                    </div>
                    <div className="mx-auto mt-10 flex max-w-7xl flex-col gap-3 text-xs text-zinc-500 sm:flex-row sm:items-center sm:justify-between">
                        <span>
                            © {new Date().getFullYear()} Arevantes, S.L.
                        </span>
                        <nav className="flex flex-wrap gap-x-5 gap-y-2">
                            <Link
                                href="/aviso-legal"
                                className="transition hover:text-[#217a3b]"
                            >
                                Aviso legal
                            </Link>
                            <Link
                                href="/privacidad"
                                className="transition hover:text-[#217a3b]"
                            >
                                Privacidad
                            </Link>
                            <Link
                                href="/cookies"
                                className="transition hover:text-[#217a3b]"
                            >
                                Cookies
                            </Link>
                        </nav>
                    </div>
                </footer>
            </div>
        </>
    );
}

function ContactField({
    label,
    name,
    type = 'text',
    inputMode,
    autoComplete,
    error,
}: {
    label: string;
    name: string;
    type?: string;
    inputMode?: 'numeric';
    autoComplete?: string;
    error?: string;
}) {
    const errorId = `${name}-error`;

    return (
        <label htmlFor={name} className="grid gap-2 text-sm font-medium">
            {label}
            <input
                id={name}
                name={name}
                type={type}
                inputMode={inputMode}
                autoComplete={autoComplete}
                aria-invalid={error ? true : undefined}
                aria-describedby={error ? errorId : undefined}
                className="h-12 rounded-xl border border-zinc-300 bg-white px-3 font-normal transition outline-none focus:border-[#217a3b] focus:ring-3 focus:ring-[#217a3b]/25"
                required={name === 'name' || name === 'email'}
            />
            {error && (
                <span
                    id={errorId}
                    role="alert"
                    className="text-xs text-red-700"
                >
                    {error}
                </span>
            )}
        </label>
    );
}

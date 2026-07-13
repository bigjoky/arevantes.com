import { Form, Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    BatteryCharging,
    Check,
    ChevronRight,
    Gauge,
    Leaf,
    MapPin,
    ShieldCheck,
    Sun,
    Wrench,
    Zap,
} from 'lucide-react';
import { dashboard, login } from '@/routes';

const services = [
    {
        icon: Sun,
        title: 'Instalaciones fotovoltaicas',
        description:
            'Sistemas de autoconsumo diseñados para aprovechar mejor cada cubierta y cada kilovatio.',
    },
    {
        icon: BatteryCharging,
        title: 'Recarga y almacenamiento',
        description:
            'Puntos de recarga y soluciones de batería preparados para acompañar tu consumo real.',
    },
    {
        icon: Zap,
        title: 'Soluciones eléctricas',
        description:
            'Instalaciones, adecuaciones y mejoras eléctricas ejecutadas con criterio técnico y seguridad.',
    },
    {
        icon: Wrench,
        title: 'Mantenimiento',
        description:
            'Seguimiento, monitorización y asistencia para que la instalación siga rindiendo con el tiempo.',
    },
];

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

const siteUrl = 'https://www.arevantes.com';
const siteDescription =
    'Arevantes diseña, instala y mantiene soluciones fotovoltaicas, eléctricas y de recarga en Córdoba y Andalucía.';

export default function Welcome() {
    const { auth } = usePage().props;
    const privateArea = auth.user ? dashboard() : login();

    return (
        <>
            <Head title="Energía bien instalada">
                <meta name="description" content={siteDescription} />
                <meta name="robots" content="index, follow" />
                <link rel="canonical" href={siteUrl} />
                <meta property="og:type" content="website" />
                <meta property="og:locale" content="es_ES" />
                <meta property="og:site_name" content="Arevantes" />
                <meta
                    property="og:title"
                    content="Arevantes · Energía bien instalada"
                />
                <meta property="og:description" content={siteDescription} />
                <meta property="og:url" content={siteUrl} />
                <meta
                    property="og:image"
                    content={`${siteUrl}/brand/arevantes-social.png`}
                />
                <meta property="og:image:width" content="1200" />
                <meta property="og:image:height" content="630" />
                <meta
                    property="og:image:alt"
                    content="Arevantes, energía bien instalada"
                />
                <meta name="twitter:card" content="summary_large_image" />
                <meta
                    name="twitter:title"
                    content="Arevantes · Energía bien instalada"
                />
                <meta name="twitter:description" content={siteDescription} />
                <meta
                    name="twitter:image"
                    content={`${siteUrl}/brand/arevantes-social.png`}
                />
                <script type="application/ld+json">
                    {JSON.stringify({
                        '@context': 'https://schema.org',
                        '@type': 'Electrician',
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
                    })}
                </script>
            </Head>

            <div className="min-h-screen bg-[#f7f8f4] text-zinc-950 antialiased">
                <header className="sticky top-0 z-50 border-b border-zinc-200/80 bg-[#f7f8f4]/90 backdrop-blur-xl">
                    <div className="mx-auto flex h-20 max-w-7xl items-center justify-between px-5 sm:px-8 lg:px-10">
                        <a href="#inicio" aria-label="Arevantes, inicio">
                            <img
                                src="/brand/arevantes-logo-light.svg"
                                alt="Arevantes"
                                className="h-7 w-auto sm:h-8"
                            />
                        </a>

                        <nav className="hidden items-center gap-8 text-sm font-medium text-zinc-600 md:flex">
                            <a
                                href="#servicios"
                                className="transition hover:text-[#289548]"
                            >
                                Servicios
                            </a>
                            <a
                                href="#metodo"
                                className="transition hover:text-[#289548]"
                            >
                                Cómo trabajamos
                            </a>
                            <a
                                href="#contacto"
                                className="transition hover:text-[#289548]"
                            >
                                Contacto
                            </a>
                        </nav>

                        <Link
                            href={privateArea}
                            className="inline-flex items-center gap-2 rounded-full border border-zinc-300 bg-white px-4 py-2 text-sm font-semibold shadow-sm transition hover:border-[#289548] hover:text-[#217a3b]"
                        >
                            {auth.user ? 'Ir al panel' : 'Área privada'}
                            <ChevronRight className="size-4" />
                        </Link>
                    </div>
                </header>

                <main>
                    <section
                        id="inicio"
                        className="relative isolate overflow-hidden bg-zinc-950 text-white"
                    >
                        <div className="absolute inset-0 -z-20 bg-[radial-gradient(circle_at_78%_28%,rgba(40,149,72,0.28),transparent_34%),radial-gradient(circle_at_15%_85%,rgba(135,181,80,0.14),transparent_30%)]" />
                        <div className="absolute inset-0 -z-10 [background-image:linear-gradient(rgba(255,255,255,.6)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,.6)_1px,transparent_1px)] [background-size:56px_56px] opacity-[0.08]" />

                        <div className="mx-auto grid min-h-[calc(100svh-5rem)] max-w-7xl items-center gap-16 px-5 py-20 sm:px-8 lg:grid-cols-[1.08fr_.92fr] lg:px-10 lg:py-24">
                            <div className="max-w-3xl">
                                <div className="mb-7 inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/5 px-4 py-2 text-sm text-zinc-300">
                                    <span className="size-2 rounded-full bg-[#87b550] shadow-[0_0_16px_#87b550]" />
                                    Energía, electricidad y mantenimiento
                                </div>
                                <h1 className="text-5xl leading-[0.98] font-semibold tracking-[-0.055em] text-balance sm:text-6xl lg:text-7xl xl:text-[5.4rem]">
                                    Energía bien pensada.
                                    <span className="mt-2 block text-[#87b550]">
                                        Energía bien instalada.
                                    </span>
                                </h1>
                                <p className="mt-8 max-w-2xl text-lg leading-8 text-zinc-300 sm:text-xl">
                                    Diseñamos y ejecutamos instalaciones que
                                    convierten el consumo energético en una
                                    decisión más eficiente, controlable y
                                    duradera.
                                </p>
                                <div className="mt-10 flex flex-col gap-4 sm:flex-row">
                                    <a
                                        href="#contacto"
                                        className="inline-flex items-center justify-center gap-2 rounded-full bg-[#289548] px-6 py-3.5 font-semibold text-white shadow-[0_14px_40px_rgba(40,149,72,.28)] transition hover:bg-[#237f3e]"
                                    >
                                        Cuéntanos tu proyecto
                                        <ArrowRight className="size-5" />
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

                            <div className="relative mx-auto hidden w-full max-w-xl lg:block">
                                <div className="absolute -inset-16 rounded-full bg-[#289548]/15 blur-3xl" />
                                <div className="relative rotate-[-4deg] rounded-[2.25rem] border border-white/15 bg-white/[0.06] p-5 shadow-2xl backdrop-blur-sm">
                                    <div className="grid aspect-[5/4] grid-cols-4 gap-2 rounded-[1.5rem] bg-gradient-to-br from-[#17231b] to-[#08100b] p-4">
                                        {Array.from({ length: 16 }).map(
                                            (_, index) => (
                                                <div
                                                    key={index}
                                                    className="relative overflow-hidden rounded-md border border-[#87b550]/20 bg-gradient-to-br from-[#193824] via-[#102819] to-[#091a10] shadow-inner"
                                                >
                                                    <div className="absolute inset-x-0 top-1/2 h-px bg-[#87b550]/20" />
                                                    <div className="absolute inset-y-0 left-1/2 w-px bg-[#87b550]/15" />
                                                    <div className="absolute -top-5 -left-5 size-12 rounded-full bg-white/10 blur-xl" />
                                                </div>
                                            ),
                                        )}
                                    </div>
                                    <div className="mt-5 grid grid-cols-2 gap-3">
                                        <div className="rounded-2xl border border-white/10 bg-black/20 p-4">
                                            <Gauge className="mb-5 size-5 text-[#87b550]" />
                                            <p className="text-xs tracking-wide text-zinc-400 uppercase">
                                                Control
                                            </p>
                                            <p className="mt-1 font-medium">
                                                Monitorización conectada
                                            </p>
                                        </div>
                                        <div className="rounded-2xl border border-white/10 bg-black/20 p-4">
                                            <ShieldCheck className="mb-5 size-5 text-[#87b550]" />
                                            <p className="text-xs tracking-wide text-zinc-400 uppercase">
                                                Confianza
                                            </p>
                                            <p className="mt-1 font-medium">
                                                Soporte después de instalar
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section
                        id="servicios"
                        className="px-5 py-24 sm:px-8 lg:py-32"
                    >
                        <div className="mx-auto max-w-7xl">
                            <div className="grid gap-8 lg:grid-cols-[.8fr_1.2fr] lg:items-end">
                                <div>
                                    <p className="text-sm font-bold tracking-[0.18em] text-[#289548] uppercase">
                                        Lo que hacemos
                                    </p>
                                    <h2 className="mt-4 text-4xl leading-tight font-semibold tracking-[-0.04em] sm:text-5xl">
                                        Una solución completa, no piezas
                                        sueltas.
                                    </h2>
                                </div>
                                <p className="max-w-2xl text-lg leading-8 text-zinc-600 lg:justify-self-end">
                                    Unimos estudio, instalación y seguimiento
                                    para que cada decisión técnica tenga sentido
                                    dentro del conjunto.
                                </p>
                            </div>

                            <div className="mt-14 grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                                {services.map((service, index) => (
                                    <article
                                        key={service.title}
                                        className="group flex min-h-72 flex-col rounded-[1.75rem] border border-zinc-200 bg-white p-7 shadow-[0_12px_40px_rgba(24,24,27,.04)] transition duration-300 hover:-translate-y-1 hover:border-[#289548]/40 hover:shadow-[0_20px_50px_rgba(40,149,72,.10)]"
                                    >
                                        <div className="flex items-start justify-between">
                                            <span className="grid size-12 place-items-center rounded-2xl bg-[#eaf5ec] text-[#217a3b]">
                                                <service.icon className="size-6" />
                                            </span>
                                            <span className="text-sm text-zinc-400">
                                                0{index + 1}
                                            </span>
                                        </div>
                                        <div className="mt-auto pt-10">
                                            <h3 className="text-xl font-semibold tracking-tight">
                                                {service.title}
                                            </h3>
                                            <p className="mt-3 leading-7 text-zinc-600">
                                                {service.description}
                                            </p>
                                        </div>
                                    </article>
                                ))}
                            </div>
                        </div>
                    </section>

                    <section className="overflow-hidden bg-[#eaf1e4] px-5 py-24 sm:px-8 lg:py-32">
                        <div className="mx-auto grid max-w-7xl gap-14 lg:grid-cols-2 lg:items-center">
                            <div className="relative min-h-[28rem] overflow-hidden rounded-[2rem] bg-[#16301e] p-8 text-white sm:p-10">
                                <div className="absolute -right-24 -bottom-24 size-80 rounded-full border-[52px] border-[#87b550]/20" />
                                <div className="absolute top-10 right-10 size-28 rounded-full bg-[#289548]/30 blur-2xl" />
                                <Leaf className="size-10 text-[#87b550]" />
                                <div className="absolute right-8 bottom-8 left-8 sm:right-10 sm:bottom-10 sm:left-10">
                                    <p className="max-w-lg text-3xl leading-tight font-medium tracking-[-0.03em] sm:text-4xl">
                                        La mejor instalación es la que encaja en
                                        tu consumo y sigue funcionando bien
                                        mañana.
                                    </p>
                                </div>
                            </div>

                            <div className="lg:pl-10">
                                <p className="text-sm font-bold tracking-[0.18em] text-[#217a3b] uppercase">
                                    Nuestro criterio
                                </p>
                                <h2 className="mt-4 text-4xl leading-tight font-semibold tracking-[-0.04em] sm:text-5xl">
                                    Técnica clara. Trabajo cercano.
                                </h2>
                                <p className="mt-6 text-lg leading-8 text-zinc-700">
                                    No se trata solo de colocar equipos. Se
                                    trata de entender el lugar, dimensionar con
                                    coherencia y dejar una instalación segura,
                                    documentada y fácil de controlar.
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
                        className="bg-white px-5 py-24 sm:px-8 lg:py-32"
                    >
                        <div className="mx-auto max-w-7xl">
                            <div className="max-w-2xl">
                                <p className="text-sm font-bold tracking-[0.18em] text-[#289548] uppercase">
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
                                        className="bg-white p-7 sm:p-8"
                                    >
                                        <span className="text-sm font-bold text-[#289548]">
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
                        className="px-5 py-8 sm:px-8 lg:pb-10"
                    >
                        <div className="relative mx-auto max-w-7xl overflow-hidden rounded-[2.25rem] bg-[#289548] px-6 py-12 text-white sm:px-12 sm:py-16 lg:px-20">
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
                                        Cuéntanos qué necesitas y empezaremos
                                        por entender bien tu caso.
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
                                                        error={errors.name}
                                                    />
                                                    <ContactField
                                                        label="Correo electrónico"
                                                        name="email"
                                                        type="email"
                                                        error={errors.email}
                                                    />
                                                    <ContactField
                                                        label="Teléfono"
                                                        name="phone"
                                                        type="tel"
                                                        error={errors.phone}
                                                    />
                                                    <ContactField
                                                        label="Código postal"
                                                        name="postal_code"
                                                        inputMode="numeric"
                                                        error={
                                                            errors.postal_code
                                                        }
                                                    />
                                                </div>

                                                <label className="mt-5 grid gap-2 text-sm font-medium">
                                                    ¿En qué podemos ayudarte?
                                                    <select
                                                        name="service"
                                                        defaultValue=""
                                                        className="h-12 rounded-xl border border-zinc-300 bg-white px-3 font-normal transition outline-none focus:border-[#289548] focus:ring-3 focus:ring-[#289548]/15"
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
                                                        <option value="electrical">
                                                            Soluciones
                                                            eléctricas
                                                        </option>
                                                        <option value="maintenance">
                                                            Mantenimiento
                                                        </option>
                                                    </select>
                                                    {errors.service && (
                                                        <span className="text-xs text-red-600">
                                                            {errors.service}
                                                        </span>
                                                    )}
                                                </label>

                                                <label className="mt-5 grid gap-2 text-sm font-medium">
                                                    Cuéntanos brevemente tu
                                                    proyecto
                                                    <textarea
                                                        name="message"
                                                        rows={4}
                                                        minLength={20}
                                                        maxLength={2000}
                                                        className="resize-none rounded-xl border border-zinc-300 bg-white p-3 font-normal transition outline-none focus:border-[#289548] focus:ring-3 focus:ring-[#289548]/15"
                                                        required
                                                    />
                                                    {errors.message && (
                                                        <span className="text-xs text-red-600">
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

                                                <label className="mt-5 flex items-start gap-3 text-sm leading-6 text-zinc-600">
                                                    <input
                                                        type="checkbox"
                                                        name="privacy"
                                                        value="1"
                                                        className="mt-1 size-4 rounded border-zinc-300 accent-[#289548]"
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
                                                            <span className="mt-1 block text-xs text-red-600">
                                                                {errors.privacy}
                                                            </span>
                                                        )}
                                                    </span>
                                                </label>

                                                <button
                                                    type="submit"
                                                    disabled={processing}
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
    error,
}: {
    label: string;
    name: string;
    type?: string;
    inputMode?: 'numeric';
    error?: string;
}) {
    return (
        <label className="grid gap-2 text-sm font-medium">
            {label}
            <input
                name={name}
                type={type}
                inputMode={inputMode}
                className="h-12 rounded-xl border border-zinc-300 bg-white px-3 font-normal transition outline-none focus:border-[#289548] focus:ring-3 focus:ring-[#289548]/15"
                required={name === 'name' || name === 'email'}
            />
            {error && <span className="text-xs text-red-600">{error}</span>}
        </label>
    );
}

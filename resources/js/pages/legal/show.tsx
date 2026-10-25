import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Cookie, Scale, ShieldCheck } from 'lucide-react';
import type { ReactNode } from 'react';
import { useCompanyBrand } from '@/components/brand-logo';
import { LanguageSelector } from '@/components/language-selector';

type DocumentKind = 'notice' | 'privacy' | 'cookies';

type Company = {
    legal_name: string | null;
    trade_name: string | null;
    tax_id: string | null;
    address: string | null;
    postal_code: string | null;
    city: string | null;
    province: string | null;
    country: string | null;
    email: string | null;
    phone: string | null;
    website: string | null;
    commercial_registry: string | null;
};

const documents = {
    notice: {
        title: 'Aviso legal',
        description:
            'Información identificativa y condiciones de uso de este sitio web.',
        icon: Scale,
    },
    privacy: {
        title: 'Política de privacidad',
        description: 'Cómo tratamos los datos personales que nos facilitas.',
        icon: ShieldCheck,
    },
    cookies: {
        title: 'Política de cookies',
        description:
            'Información sobre las cookies técnicas utilizadas en este sitio.',
        icon: Cookie,
    },
} satisfies Record<
    DocumentKind,
    { title: string; description: string; icon: typeof Scale }
>;

export default function LegalPage({
    document,
    siteUrl,
    company,
}: {
    document: DocumentKind;
    siteUrl: string;
    company: Company;
}) {
    const brand = useCompanyBrand();
    const metadata = documents[document];
    const Icon = metadata.icon;
    const paths: Record<DocumentKind, string> = {
        notice: 'aviso-legal',
        privacy: 'privacidad',
        cookies: 'cookies',
    };
    const canonical = `${siteUrl}/${paths[document]}`;

    return (
        <>
            <Head>
                <title head-key="title">{`${metadata.title} · Arevantes`}</title>
                <meta
                    head-key="description"
                    name="description"
                    content={metadata.description}
                />
                <meta head-key="robots" name="robots" content="index, follow" />
                <link head-key="canonical" rel="canonical" href={canonical} />
            </Head>
            <div className="min-h-screen bg-[#f7f8f4] text-zinc-950 antialiased">
                <header className="border-b border-zinc-200 bg-[#f7f8f4]">
                    <div className="mx-auto flex h-20 max-w-5xl items-center justify-between px-5 sm:px-8">
                        <Link href="/" aria-label="Arevantes, inicio">
                            <img
                                src={brand.logoUrl}
                                alt="Arevantes"
                                className="h-7 w-auto sm:h-8"
                            />
                        </Link>
                        <div className="flex items-center gap-2">
                            <LanguageSelector />
                            <Link
                                href="/"
                                className="inline-flex items-center gap-2 text-sm font-semibold text-zinc-600 transition hover:text-[#217a3b]"
                            >
                                <ArrowLeft className="size-4" />
                                Volver a la web
                            </Link>
                        </div>
                    </div>
                </header>

                <main className="px-5 py-14 sm:px-8 sm:py-20">
                    <article className="mx-auto max-w-3xl">
                        <div className="mb-10 border-b border-zinc-200 pb-10">
                            <span className="grid size-12 place-items-center rounded-2xl bg-[#eaf5ec] text-[#217a3b]">
                                <Icon className="size-6" />
                            </span>
                            <h1 className="mt-6 text-4xl font-semibold tracking-[-0.04em] sm:text-5xl">
                                {metadata.title}
                            </h1>
                            <p className="mt-4 text-lg leading-8 text-zinc-600">
                                {metadata.description}
                            </p>
                            <p className="mt-3 text-sm text-zinc-500">
                                Última actualización: 23 de agosto de 2026
                            </p>
                        </div>

                        <div className="space-y-10 text-[15px] leading-7 text-zinc-700">
                            {document === 'notice' && (
                                <LegalNotice company={company} />
                            )}
                            {document === 'privacy' && (
                                <PrivacyPolicy company={company} />
                            )}
                            {document === 'cookies' && (
                                <CookiesPolicy company={company} />
                            )}
                        </div>
                    </article>
                </main>

                <LegalFooter />
            </div>
        </>
    );
}

function LegalNotice({ company }: { company: Company }) {
    return (
        <>
            <Section title="1. Titular del sitio web">
                <p>Este sitio web es titularidad de:</p>
                <dl className="mt-4 grid gap-2 rounded-2xl border border-zinc-200 bg-white p-5 sm:grid-cols-[11rem_1fr]">
                    <Data label="Razón social" value={company.legal_name} />
                    <Data label="Nombre comercial" value={company.trade_name} />
                    <Data label="NIF" value={company.tax_id} />
                    <Data label="Domicilio" value={formatAddress(company)} />
                    <Data
                        label="Correo electrónico"
                        value={company.email}
                        email
                    />
                    {company.phone && (
                        <Data label="Teléfono" value={company.phone} />
                    )}
                    {company.website && (
                        <Data label="Sitio web" value={company.website} />
                    )}
                    {company.commercial_registry && (
                        <Data
                            label="Registro Mercantil"
                            value={company.commercial_registry}
                        />
                    )}
                </dl>
            </Section>

            <Section title="2. Objeto y aceptación">
                <p>
                    Este aviso regula el acceso y el uso del sitio web de
                    Arevantes. La navegación implica la aceptación de estas
                    condiciones. Si no estás de acuerdo con ellas, debes
                    abstenerte de utilizar el sitio.
                </p>
            </Section>

            <Section title="3. Uso del sitio web">
                <p>
                    La persona usuaria se compromete a utilizar el sitio, sus
                    contenidos y servicios de forma lícita, diligente y
                    respetuosa con los derechos de terceros. Queda prohibido
                    emplearlo para introducir software malicioso, intentar
                    accesos no autorizados o realizar actividades que puedan
                    dañar su funcionamiento.
                </p>
            </Section>

            <Section title="4. Propiedad intelectual e industrial">
                <p>
                    Los textos, diseños, marcas, logotipos, imágenes, código y
                    demás contenidos del sitio pertenecen a Arevantes o se
                    utilizan con la autorización correspondiente. No se permite
                    su reproducción, distribución o transformación sin
                    autorización, salvo en los casos permitidos por la ley.
                </p>
            </Section>

            <Section title="5. Responsabilidad y enlaces">
                <p>
                    Arevantes procura mantener la información actualizada y el
                    sitio disponible, pero no garantiza la ausencia absoluta de
                    errores o interrupciones. Los enlaces a sitios de terceros
                    se facilitan, en su caso, como referencia; Arevantes no
                    controla ni responde de sus contenidos o políticas.
                </p>
            </Section>

            <Section title="6. Legislación aplicable">
                <p>
                    Este aviso se rige por la legislación española. Cualquier
                    controversia se someterá a los juzgados y tribunales que
                    resulten competentes de acuerdo con la normativa aplicable.
                </p>
            </Section>
        </>
    );
}

function PrivacyPolicy({ company }: { company: Company }) {
    return (
        <>
            <Section title="1. Responsable del tratamiento">
                <p>
                    <strong>{company.legal_name}</strong>
                    {company.tax_id ? `, con NIF ${company.tax_id},` : ''} es
                    responsable del tratamiento de los datos personales
                    recogidos mediante este sitio web. Puedes contactar en{' '}
                    <Email value={company.email} /> o en{' '}
                    {formatAddress(company)}.
                </p>
            </Section>

            <Section title="2. Datos que tratamos">
                <p>
                    Cuando utilizas el formulario de contacto podemos tratar tu
                    nombre, correo electrónico, teléfono, código postal,
                    servicio de interés, el contenido de tu mensaje y la
                    información técnica imprescindible para tramitar la
                    solicitud y proteger el formulario frente a abusos.
                </p>
            </Section>

            <Section title="3. Finalidad y base jurídica">
                <p>
                    Tratamos los datos para responder a tu consulta, preparar la
                    atención comercial solicitada y gestionar el seguimiento de
                    la oportunidad en nuestro sistema interno. La base jurídica
                    es tu consentimiento al enviar el formulario y, cuando
                    corresponda, la aplicación de medidas precontractuales
                    solicitadas por ti.
                </p>
            </Section>

            <Section title="4. Conservación">
                <p>
                    Los datos se conservarán mientras sea necesario para atender
                    y realizar el seguimiento de la solicitud. Después podrán
                    permanecer bloqueados durante los plazos legales aplicables
                    para atender posibles responsabilidades. Si la consulta da
                    lugar a una relación contractual, se conservarán conforme a
                    las obligaciones legales asociadas.
                </p>
            </Section>

            <Section title="5. Destinatarios y transferencias">
                <p>
                    No vendemos tus datos. Solo podrán acceder a ellos
                    proveedores que nos prestan servicios necesarios —por
                    ejemplo, alojamiento, correo o soporte tecnológico— bajo las
                    garantías contractuales exigibles, o las administraciones y
                    autoridades cuando exista una obligación legal. No están
                    previstas transferencias internacionales fuera del Espacio
                    Económico Europeo; si fueran necesarias, se aplicarían las
                    garantías legalmente exigidas.
                </p>
            </Section>

            <Section title="6. Tus derechos">
                <p>
                    Puedes solicitar el acceso, rectificación, supresión,
                    oposición, limitación o portabilidad de tus datos, así como
                    retirar el consentimiento, escribiendo a{' '}
                    <Email value={company.email} /> e indicando el derecho que
                    deseas ejercer. Podremos pedirte información necesaria para
                    comprobar tu identidad.
                </p>
                <p className="mt-3">
                    También puedes presentar una reclamación ante la{' '}
                    <a
                        className="font-semibold text-[#217a3b] underline decoration-[#289548]/40 underline-offset-4"
                        href="https://www.aepd.es"
                        target="_blank"
                        rel="noreferrer"
                    >
                        Agencia Española de Protección de Datos
                    </a>
                    .
                </p>
            </Section>

            <Section title="7. Carácter obligatorio y seguridad">
                <p>
                    Los campos marcados como obligatorios son necesarios para
                    tramitar la solicitud. Aplicamos medidas técnicas y
                    organizativas destinadas a proteger los datos frente a
                    accesos, alteraciones, pérdidas o tratamientos no
                    autorizados.
                </p>
            </Section>

            <Section title="8. Cambios en esta política">
                <p>
                    Podremos actualizar esta política para adaptarla a cambios
                    legales o del servicio. La fecha de la versión vigente
                    aparece al inicio de la página.
                </p>
            </Section>
        </>
    );
}

function CookiesPolicy({ company }: { company: Company }) {
    return (
        <>
            <Section title="1. Qué son las cookies">
                <p>
                    Las cookies son pequeños archivos que un sitio web guarda en
                    el navegador para recordar información necesaria para su
                    funcionamiento o las preferencias de la persona usuaria.
                </p>
            </Section>

            <Section title="2. Cookies utilizadas">
                <p>
                    Actualmente este sitio no utiliza cookies de publicidad,
                    seguimiento ni analítica. Solo emplea cookies técnicas y de
                    preferencias necesarias para la seguridad, el funcionamiento
                    de la sesión y las opciones elegidas por la persona usuaria.
                </p>
                <div className="mt-5 overflow-x-auto rounded-2xl border border-zinc-200 bg-white">
                    <table className="w-full min-w-[36rem] text-left text-sm">
                        <thead className="border-b border-zinc-200 bg-zinc-50 text-zinc-900">
                            <tr>
                                <th className="p-4 font-semibold">Cookie</th>
                                <th className="p-4 font-semibold">Finalidad</th>
                                <th className="p-4 font-semibold">Duración</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-zinc-100">
                            <CookieRow
                                name="XSRF-TOKEN"
                                purpose="Protección de los formularios frente a solicitudes maliciosas."
                                duration="2 horas"
                            />
                            <CookieRow
                                name="Cookie de sesión"
                                purpose="Mantiene de forma segura la sesión y el estado necesario de la aplicación."
                                duration="2 horas"
                            />
                            <CookieRow
                                name="__cf_bm"
                                purpose="Ayuda a Cloudflare a distinguir el tráfico legítimo y proteger el sitio frente a abusos automatizados."
                                duration="30 minutos"
                            />
                            <CookieRow
                                name="appearance"
                                purpose="Recuerda la apariencia elegida por la persona usuaria."
                                duration="1 año"
                            />
                            <CookieRow
                                name="sidebar_state"
                                purpose="Recuerda el estado del menú dentro del área privada."
                                duration="7 días"
                            />
                        </tbody>
                    </table>
                </div>
            </Section>

            <Section title="3. Gestión desde el navegador">
                <p>
                    Puedes bloquear o eliminar las cookies desde la
                    configuración de tu navegador. Si desactivas las cookies
                    técnicas, algunas funciones —como los formularios o el
                    acceso al área privada— podrían dejar de funcionar
                    correctamente.
                </p>
            </Section>

            <Section title="4. Cambios y contacto">
                <p>
                    Si en el futuro incorporamos cookies de analítica,
                    publicidad u otras que requieran consentimiento, se
                    solicitará antes de instalarlas y se actualizará esta
                    política. Para cualquier consulta puedes escribir a{' '}
                    <Email value={company.email} />.
                </p>
            </Section>
        </>
    );
}

function Section({ title, children }: { title: string; children: ReactNode }) {
    return (
        <section>
            <h2 className="mb-3 text-xl font-semibold tracking-[-0.02em] text-zinc-950">
                {title}
            </h2>
            {children}
        </section>
    );
}

function Data({
    label,
    value,
    email = false,
}: {
    label: string;
    value: string | null;
    email?: boolean;
}) {
    if (!value) {
        return null;
    }

    return (
        <>
            <dt className="font-semibold text-zinc-950">{label}</dt>
            <dd>{email ? <Email value={value} /> : value}</dd>
        </>
    );
}

function Email({ value }: { value: string | null }) {
    return value ? (
        <a
            href={`mailto:${value}`}
            className="font-semibold text-[#217a3b] underline decoration-[#289548]/40 underline-offset-4"
        >
            {value}
        </a>
    ) : (
        <span>el canal de contacto indicado en esta web</span>
    );
}

function CookieRow({
    name,
    purpose,
    duration,
}: {
    name: string;
    purpose: string;
    duration: string;
}) {
    return (
        <tr>
            <td className="p-4 font-mono text-xs text-zinc-900">{name}</td>
            <td className="p-4">{purpose}</td>
            <td className="p-4 whitespace-nowrap">{duration}</td>
        </tr>
    );
}

function formatAddress(company: Company) {
    return [
        company.address,
        [company.postal_code, company.city].filter(Boolean).join(' '),
        company.province,
        company.country,
    ]
        .filter(Boolean)
        .join(', ');
}

function LegalFooter() {
    return (
        <footer className="border-t border-zinc-200 px-5 py-10 sm:px-8">
            <div className="mx-auto flex max-w-5xl flex-col gap-5 text-sm text-zinc-500 sm:flex-row sm:items-center sm:justify-between">
                <span>© {new Date().getFullYear()} Arevantes, S.L.</span>
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
    );
}

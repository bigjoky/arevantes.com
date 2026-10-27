import { Form, Link } from '@inertiajs/react';
import { Breadcrumbs, SiteLayout } from '@/components/public/site-layout';
export default function Contact({
    siteUrl,
    metadata,
    contactServices,
    selectedService,
}: {
    siteUrl: string;
    metadata: { title: string; description: string; path: string };
    contactServices: Record<string, string>;
    selectedService: string;
}) {
    return (
        <SiteLayout {...metadata} siteUrl={siteUrl}>
            <section className="av-page-heading">
                <Breadcrumbs label="Contacto" />
                <p className="av-eyebrow">Empezamos por escucharte</p>
                <h1>¿Hablamos de tu proyecto?</h1>
                <p>{metadata.description}</p>
            </section>
            <section className="av-section av-contact">
                <aside>
                    <h2>Cuéntanos qué necesitas.</h2>
                    <p>
                        Indica dónde se encuentra el inmueble y qué te gustaría
                        resolver. Déjanos un correo electrónico o un teléfono
                        para poder responderte.
                    </p>
                    <a
                        className="av-text-link"
                        href="mailto:contact@arevantes.com"
                    >
                        contact@arevantes.com ↗
                    </a>
                    <p>Baena · Córdoba · Andalucía</p>
                    <p>
                        Para redes y sistemas, escríbenos directamente por
                        correo. El formulario recoge las categorías actualmente
                        disponibles.
                    </p>
                </aside>
                <Form action="/contact" method="post">
                    {({ errors, processing, wasSuccessful }) =>
                        wasSuccessful ? (
                            <div className="av-status" role="status">
                                <h2>Solicitud recibida.</h2>
                                <p>
                                    Gracias por contarnos tu proyecto. Nuestro
                                    equipo revisará tu consulta y se pondrá en
                                    contacto contigo.
                                </p>
                            </div>
                        ) : (
                            <div className="av-form">
                                {[
                                    {
                                        name: 'name',
                                        label: 'Nombre y apellidos',
                                        autoComplete: 'name',
                                        required: true,
                                    },
                                    {
                                        name: 'email',
                                        label: 'Correo electrónico',
                                        autoComplete: 'email',
                                        type: 'email',
                                    },
                                    {
                                        name: 'phone',
                                        label: 'Teléfono',
                                        autoComplete: 'tel',
                                        type: 'tel',
                                    },
                                    {
                                        name: 'postal_code',
                                        label: 'Código postal',
                                        autoComplete: 'postal-code',
                                    },
                                ].map((field) => (
                                    <label
                                        className="av-field"
                                        key={field.name}
                                        htmlFor={field.name}
                                    >
                                        {field.label}
                                        <input
                                            id={field.name}
                                            {...field}
                                            aria-invalid={!!errors[field.name]}
                                            aria-describedby={
                                                errors[field.name]
                                                    ? `${field.name}-error`
                                                    : undefined
                                            }
                                        />
                                        {errors[field.name] && (
                                            <span
                                                className="av-error"
                                                id={`${field.name}-error`}
                                            >
                                                {errors[field.name]}
                                            </span>
                                        )}
                                    </label>
                                ))}
                                <label
                                    className="av-field av-wide"
                                    htmlFor="service"
                                >
                                    Servicio
                                    <select
                                        name="service"
                                        id="service"
                                        defaultValue={selectedService}
                                        required
                                        aria-invalid={!!errors.service}
                                        aria-describedby={
                                            errors.service
                                                ? 'service-error'
                                                : undefined
                                        }
                                    >
                                        <option value="" disabled>
                                            Selecciona un servicio
                                        </option>
                                        {Object.entries(contactServices).map(
                                            ([value, label]) => (
                                                <option
                                                    key={value}
                                                    value={value}
                                                >
                                                    {label}
                                                </option>
                                            ),
                                        )}
                                    </select>
                                    {errors.service && (
                                        <span
                                            className="av-error"
                                            id="service-error"
                                        >
                                            {errors.service}
                                        </span>
                                    )}
                                </label>
                                <label
                                    className="av-field av-wide"
                                    htmlFor="message"
                                >
                                    Tu proyecto
                                    <textarea
                                        id="message"
                                        name="message"
                                        required
                                        minLength={20}
                                        maxLength={2000}
                                        aria-invalid={!!errors.message}
                                        aria-describedby="message-help message-error"
                                    />
                                    <span id="message-help">
                                        Entre 20 y 2.000 caracteres. No incluyas
                                        información sensible.
                                    </span>
                                    <span
                                        className="av-error"
                                        id="message-error"
                                    >
                                        {errors.message}
                                    </span>
                                </label>
                                <label
                                    className="av-honeypot"
                                    aria-hidden="true"
                                >
                                    Dejar vacío
                                    <input
                                        name="company_url"
                                        tabIndex={-1}
                                        autoComplete="off"
                                    />
                                </label>
                                <label
                                    className="av-privacy av-wide"
                                    htmlFor="privacy"
                                >
                                    <input
                                        type="checkbox"
                                        name="privacy"
                                        id="privacy"
                                        required
                                        aria-invalid={!!errors.privacy}
                                        aria-describedby="privacy-error"
                                    />
                                    <span>
                                        He leído la{' '}
                                        <Link href="/privacidad">
                                            política de privacidad
                                        </Link>{' '}
                                        y acepto el tratamiento de mis datos
                                        para atender esta consulta.
                                    </span>
                                </label>
                                {errors.privacy && (
                                    <span
                                        className="av-error av-wide"
                                        id="privacy-error"
                                    >
                                        {errors.privacy}
                                    </span>
                                )}
                                <p className="av-wide">
                                    Los datos se utilizarán para gestionar tu
                                    solicitud. Consulta el responsable, tus
                                    derechos y el resto de información en la
                                    política de privacidad.
                                </p>
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="av-button av-wide"
                                >
                                    {processing
                                        ? 'Enviando…'
                                        : 'Enviar consulta'}{' '}
                                    <span aria-hidden="true">→</span>
                                </button>
                            </div>
                        )
                    }
                </Form>
            </section>
        </SiteLayout>
    );
}

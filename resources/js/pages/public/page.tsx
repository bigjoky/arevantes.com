import {
    Breadcrumbs,
    ContactCta,
    ProcessSteps,
    ServiceGrid,
    SiteLayout,
    TextLink,
} from '@/components/public/site-layout';
export default function PublicPage({
    section,
    siteUrl,
    metadata,
}: {
    section: string;
    siteUrl: string;
    metadata: { title: string; description: string; path: string };
}) {
    return (
        <SiteLayout {...metadata} siteUrl={siteUrl}>
            <section className="av-page-heading">
                <Breadcrumbs label={metadata.title} />
                <p className="av-eyebrow">
                    Arevantes · Instalaciones y tecnología
                </p>
                <h1>{metadata.title}</h1>
                <p>{metadata.description}</p>
            </section>
            {section === 'servicios' && (
                <section className="av-section">
                    <ServiceGrid />
                </section>
            )}
            {section === 'empresa' && (
                <>
                    <section className="av-section av-company">
                        <p className="av-eyebrow">Quiénes somos</p>
                        <h2>
                            Un interlocutor.
                            <br />
                            Una visión de conjunto.
                        </h2>
                        <div>
                            <p>
                                AREVANTES trabaja en soluciones de energía,
                                electricidad, seguridad y comunicaciones.
                                Nuestro punto de partida es entender cómo
                                utilizas tu espacio y qué necesitas que resuelva
                                la instalación.
                            </p>
                            <p>
                                Desde Baena, Córdoba, atendemos proyectos en
                                Andalucía con una forma de trabajar cercana:
                                alcance claro, coordinación técnica y atención
                                durante la puesta en servicio.
                            </p>
                            <TextLink href="/proceso">
                                Nuestra manera de trabajar
                            </TextLink>
                        </div>
                    </section>
                    <section className="av-section av-tinted">
                        <p className="av-eyebrow">Lo que cuidamos</p>
                        <div className="av-grid av-grid-three">
                            {[
                                [
                                    'Criterio técnico',
                                    'Cada propuesta parte de las condiciones reales y de la compatibilidad de las soluciones.',
                                ],
                                [
                                    'Comunicación clara',
                                    'Explicamos qué incluye la intervención y resolvemos las dudas antes de empezar.',
                                ],
                                [
                                    'Calidad de ejecución',
                                    'Cuidamos la instalación, las comprobaciones y la documentación correspondiente al trabajo contratado.',
                                ],
                            ].map(([title, text]) => (
                                <article key={title}>
                                    <h3>{title}</h3>
                                    <p>{text}</p>
                                </article>
                            ))}
                        </div>
                    </section>
                </>
            )}
            {section === 'proceso' && (
                <>
                    <section className="av-section">
                        <ProcessSteps />
                    </section>
                    <section className="av-section av-company av-tinted">
                        <h2>
                            Decisiones claras
                            <br />
                            en cada fase.
                        </h2>
                        <div>
                            <p>
                                Para empezar, cuéntanos el tipo de inmueble, su
                                ubicación y qué necesitas resolver. En proyectos
                                de autoconsumo, el perfil de consumo y la
                                información de la cubierta ayudan a preparar el
                                estudio.
                            </p>
                            <p>
                                La propuesta concreta el alcance, los plazos y
                                la documentación aplicable. Las ampliaciones o
                                cambios se revisan antes de ejecutarlos. Al
                                finalizar, comprobamos el funcionamiento y
                                explicamos el uso de los equipos.
                            </p>
                            <TextLink href="/contacto">
                                Preparar mi consulta
                            </TextLink>
                        </div>
                    </section>
                </>
            )}
            {section === 'redes' && (
                <>
                    <section className="av-section av-company">
                        <h2>
                            La base de una
                            <br />
                            buena conexión.
                        </h2>
                        <div>
                            <p>
                                El cableado estructurado, los puntos de red y la
                                cobertura inalámbrica deben responder al uso del
                                edificio. Estudiamos la distribución, el número
                                de dispositivos y las necesidades de
                                conectividad antes de plantear una solución.
                            </p>
                            <p>
                                La organización del cableado y la identificación
                                de los puntos facilitan el mantenimiento y las
                                futuras ampliaciones.
                            </p>
                            <a
                                className="av-button"
                                href="mailto:contact@arevantes.com?subject=Consulta%20sobre%20redes%20y%20sistemas"
                            >
                                Consultar por correo{' '}
                                <span aria-hidden="true">→</span>
                            </a>
                        </div>
                    </section>
                    <section className="av-section av-tinted">
                        <div className="av-grid av-grid-three">
                            {[
                                [
                                    'Cableado estructurado',
                                    'Planificación de tomas, recorridos y organización del armario de comunicaciones.',
                                ],
                                [
                                    'Redes y conectividad',
                                    'Distribución de conexiones y cobertura Wi-Fi adaptada al espacio y a los dispositivos.',
                                ],
                                [
                                    'Configuración y comprobación',
                                    'Revisión del funcionamiento y documentación de la solución acordada.',
                                ],
                            ].map(([title, text]) => (
                                <article key={title}>
                                    <h3>{title}</h3>
                                    <p>{text}</p>
                                </article>
                            ))}
                        </div>
                    </section>
                </>
            )}
            <ContactCta />
        </SiteLayout>
    );
}

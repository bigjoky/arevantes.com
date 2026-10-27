import { Link } from '@inertiajs/react';
import {
    ContactCta,
    ProcessSteps,
    ProjectSection,
    ServiceGrid,
    SiteLayout,
    TextLink,
} from '@/components/public/site-layout';
import type { PublicProjectSummary } from '@/types/public-project';
export default function Welcome({
    siteUrl,
    projects,
}: {
    siteUrl: string;
    projects: PublicProjectSummary[];
}) {
    return (
        <SiteLayout
            title="Soluciones técnicas para un futuro más eficiente"
            description="Electricidad, energías renovables, seguridad y redes para viviendas y empresas. Diseño, instalación y acompañamiento técnico."
            siteUrl={siteUrl}
        >
            <section className="av-hero">
                <img
                    className="av-hero-photo"
                    src="/images/services/energia.webp"
                    alt="Instalación fotovoltaica sobre la cubierta de una vivienda. Imagen ilustrativa."
                    width="1800"
                    height="1200"
                    fetchPriority="high"
                />
                <div className="av-hero-copy">
                    <p className="av-eyebrow">
                        Instalaciones · Energía · Tecnología
                    </p>
                    <h1>
                        Soluciones técnicas
                        <br />
                        para un futuro
                        <br />
                        más eficiente.
                    </h1>
                    <p>
                        Diseñamos y ejecutamos proyectos de electricidad,
                        energías renovables, seguridad y redes para viviendas y
                        empresas.
                    </p>
                    <div className="av-actions">
                        <Link className="av-button" href="/contacto">
                            Cuéntanos tu proyecto{' '}
                            <span aria-hidden="true">→</span>
                        </Link>
                        <TextLink href="/servicios">Conocer servicios</TextLink>
                    </div>
                </div>
                <span className="av-photo-caption">
                    Imagen ilustrativa de autoconsumo
                </span>
            </section>
            <section className="av-section" id="servicios">
                <div className="av-section-heading">
                    <div>
                        <p className="av-eyebrow">Qué hacemos</p>
                        <h2>
                            Cuatro áreas de servicio,
                            <br />
                            una visión común.
                        </h2>
                    </div>
                    <p>
                        Soluciones técnicas integradas, adaptadas a cada
                        proyecto y con un enfoque en la eficiencia, la seguridad
                        y la tecnología.
                    </p>
                    <TextLink href="/servicios">
                        Ver todos los servicios
                    </TextLink>
                </div>
                <ServiceGrid />
            </section>
            <ProjectSection projects={projects} />
            <section className="av-section" id="metodo">
                <div className="av-section-heading">
                    <div>
                        <p className="av-eyebrow">Una manera de trabajar</p>
                        <h2>Del análisis al resultado.</h2>
                    </div>
                    <TextLink href="/proceso">Conoce nuestro proceso</TextLink>
                </div>
                <ProcessSteps />
            </section>
            <section className="av-section av-company">
                <p className="av-eyebrow">Arevantes</p>
                <h2>
                    La técnica, al servicio
                    <br />
                    de las personas.
                </h2>
                <div>
                    <p>
                        Detrás de cada instalación hay una necesidad concreta.
                        La escuchamos, estudiamos las alternativas y cuidamos la
                        ejecución para que tengas una solución que entiendas y
                        puedas utilizar con confianza.
                    </p>
                    <TextLink href="/empresa">Conoce la empresa</TextLink>
                </div>
            </section>
            <div id="contacto">
                <ContactCta />
            </div>
        </SiteLayout>
    );
}

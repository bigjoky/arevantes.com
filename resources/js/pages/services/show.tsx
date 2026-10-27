import { Link } from '@inertiajs/react';
import {
    Breadcrumbs,
    ContactCta,
    ProcessSteps,
    ProjectSection,
    SiteLayout,
    TextLink,
} from '@/components/public/site-layout';
import type { PublicProjectSummary } from '@/types/public-project';
type Service = {
    slug: string;
    name: string;
    eyebrow: string;
    title: string;
    description: string;
    seo_title: string;
    seo_description: string;
    image: string;
    image_alt: string;
    form_value: string;
    intro: string;
    solutions: { title: string; description: string }[];
    deliverables: string[];
    audiences: string[];
    faqs: { question: string; answer: string }[];
};
export default function ServicePage({
    service,
    projects,
    siteUrl,
}: {
    service: Service;
    projects: PublicProjectSummary[];
    siteUrl: string;
}) {
    const solar = service.slug === 'energia';
    const title = solar ? 'Autoconsumo que genera valor.' : service.title;
    const contact = `/contacto?servicio=${service.form_value}`;

    return (
        <SiteLayout
            title={service.seo_title.replace(' · Arevantes', '')}
            description={service.seo_description}
            path={`/servicios/${service.slug}`}
            siteUrl={siteUrl}
        >
            <section className="av-hero">
                <img
                    className="av-hero-photo"
                    src={service.image}
                    alt={`${service.image_alt}. Imagen ilustrativa.`}
                    width="1800"
                    height="1200"
                    fetchPriority="high"
                />
                <div className="av-hero-copy">
                    <Breadcrumbs
                        label={`Servicios / ${solar ? 'Energías renovables' : service.name}`}
                    />
                    <p className="av-eyebrow">
                        {solar ? 'Energías renovables' : service.eyebrow}
                    </p>
                    <h1>{title}</h1>
                    <p>{service.description}</p>
                    <div className="av-actions">
                        <Link className="av-button" href={contact}>
                            Solicitar asesoramiento{' '}
                            <span aria-hidden="true">→</span>
                        </Link>
                        <TextLink href={`/proyectos?servicio=${service.slug}`}>
                            Ver proyectos
                        </TextLink>
                    </div>
                </div>
                <span className="av-photo-caption">
                    Imagen ilustrativa del servicio
                </span>
            </section>
            {solar && (
                <div className="av-benefits">
                    {[
                        [
                            'Ahorro en la factura',
                            'Aprovecha la energía que produces.',
                        ],
                        [
                            'Mayor independencia',
                            'Reduce tu dependencia de la red.',
                        ],
                        [
                            'Soluciones fiables',
                            'Equipos adecuados a tu instalación.',
                        ],
                        ['Consumo sostenible', 'Una energía más limpia.'],
                    ].map(([heading, text]) => (
                        <div key={heading}>
                            <strong>{heading}</strong>
                            <p>{text}</p>
                        </div>
                    ))}
                </div>
            )}
            <section className="av-section av-solution">
                <img
                    src={service.image}
                    alt={service.image_alt}
                    width="600"
                    height="700"
                    loading="lazy"
                />
                <div>
                    <p className="av-eyebrow">Soluciones</p>
                    <h2>Instalaciones a medida</h2>
                    <p>{service.intro}</p>
                    <ul>
                        {service.deliverables.map((item) => (
                            <li key={item}>{item}</li>
                        ))}
                    </ul>
                    {solar && (
                        <p>
                            La monitorización permite conocer la producción y el
                            consumo. Estudiamos su integración, las baterías y
                            la recarga según la compatibilidad de los equipos y
                            tus necesidades.
                        </p>
                    )}
                </div>
                <aside className="av-brands">
                    {solar ? (
                        <>
                            <p>Marcas habituales de trabajo</p>
                            {['LONGi', 'HUAWEI', 'Deye', 'FelicityESS'].map(
                                (brand) => (
                                    <strong key={brand}>{brand}</strong>
                                ),
                            )}
                            <small>
                                La elección depende del estudio técnico. Esta
                                mención no implica vinculación ni acreditación
                                oficial.
                            </small>
                        </>
                    ) : (
                        <>
                            <p className="av-eyebrow">Para cada espacio</p>
                            {service.audiences.map((item) => (
                                <p key={item}>{item}</p>
                            ))}
                        </>
                    )}
                </aside>
            </section>
            <section className="av-section av-tinted">
                <p className="av-eyebrow">Tipos de instalaciones</p>
                <h2>Soluciones para cada necesidad.</h2>
                <div
                    className={`av-grid ${solar ? 'av-grid-four' : 'av-grid-three'}`}
                >
                    {(solar
                        ? [
                              {
                                  title: 'Viviendas unifamiliares',
                                  description:
                                      'Autoconsumo adaptado al perfil de consumo de tu hogar y al espacio de cubierta disponible.',
                              },
                              {
                                  title: 'Empresas e industria',
                                  description:
                                      'Instalaciones dimensionadas según la actividad, los horarios y la demanda energética.',
                              },
                              {
                                  title: 'Baterías de almacenamiento',
                                  description:
                                      'Aprovecha los excedentes cuando los necesitas, según la capacidad y compatibilidad del sistema.',
                              },
                              {
                                  title: 'Cargadores para vehículo eléctrico',
                                  description:
                                      'Recarga coordinada con la potencia disponible y la producción fotovoltaica.',
                              },
                          ]
                        : service.solutions
                    ).map((solution, index) => (
                        <article
                            className="av-solution-card"
                            key={solution.title}
                        >
                            <span>0{index + 1}</span>
                            <h3>{solution.title}</h3>
                            <p>{solution.description}</p>
                            <TextLink href={contact}>
                                Consultar solución
                            </TextLink>
                        </article>
                    ))}
                </div>
            </section>
            <section className="av-section">
                <div className="av-section-heading">
                    <div>
                        <p className="av-eyebrow">Nuestro proceso</p>
                        <h2>Del estudio a la puesta en servicio.</h2>
                    </div>
                    <TextLink href="/proceso">Cómo trabajamos</TextLink>
                </div>
                <ProcessSteps />
            </section>
            <ProjectSection
                projects={projects}
                title="Proyectos relacionados."
            />
            <section className="av-section av-faq">
                <p className="av-eyebrow">Resolvemos tus dudas</p>
                <h2>Antes de empezar.</h2>
                {service.faqs.map((faq) => (
                    <details key={faq.question}>
                        <summary>{faq.question}</summary>
                        <p>{faq.answer}</p>
                    </details>
                ))}
            </section>
            <ContactCta />
        </SiteLayout>
    );
}

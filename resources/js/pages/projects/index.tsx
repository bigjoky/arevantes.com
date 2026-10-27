import { Link } from '@inertiajs/react';
import { ProjectCard } from '@/components/public/project-card';
import {
    Breadcrumbs,
    ContactCta,
    SiteLayout,
} from '@/components/public/site-layout';
import type { PublicProjectSummary } from '@/types/public-project';
export default function ProjectsIndex({
    projects,
    activeService,
    services,
    siteUrl,
}: {
    projects: PublicProjectSummary[];
    activeService: string | null;
    services: { slug: string; name: string }[];
    siteUrl: string;
}) {
    return (
        <SiteLayout
            title="Proyectos realizados"
            description="Una selección de instalaciones publicadas de AREVANTES. Consulta el alcance y las características de cada proyecto."
            path="/proyectos"
            siteUrl={siteUrl}
        >
            <section className="av-page-heading">
                <Breadcrumbs label="Proyectos" />
                <p className="av-eyebrow">Trabajo realizado</p>
                <h1>
                    Instalaciones reales,
                    <br />
                    resultados medibles.
                </h1>
                <p>
                    Cada espacio plantea condiciones distintas. Descubre cómo se
                    ha resuelto cada instalación.
                </p>
            </section>
            <section className="av-section">
                <nav
                    className="av-filter"
                    aria-label="Filtrar proyectos por servicio"
                >
                    <Link
                        href="/proyectos"
                        aria-current={!activeService ? 'page' : undefined}
                    >
                        Todos
                    </Link>
                    {services.map((service) => (
                        <Link
                            key={service.slug}
                            href={`/proyectos?servicio=${service.slug}`}
                            aria-current={
                                activeService === service.slug
                                    ? 'page'
                                    : undefined
                            }
                        >
                            {service.name}
                        </Link>
                    ))}
                </nav>
                {projects.length ? (
                    <div className="av-grid av-grid-three">
                        {projects.map((project) => (
                            <ProjectCard key={project.slug} project={project} />
                        ))}
                    </div>
                ) : (
                    <div className="av-empty">
                        <h2>Sin proyectos publicados.</h2>
                        <p>
                            Todavía no hay casos publicados para esta selección.
                            Puedes consultarnos sobre una instalación similar.
                        </p>
                    </div>
                )}
            </section>
            <ContactCta />
        </SiteLayout>
    );
}

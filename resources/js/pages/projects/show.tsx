import {
    Breadcrumbs,
    ContactCta,
    ProjectSection,
    SiteLayout,
} from '@/components/public/site-layout';
import type {
    PublicProjectDetail,
    PublicProjectSummary,
} from '@/types/public-project';
export default function ProjectShow({
    project,
    relatedProjects,
    siteUrl,
}: {
    project: PublicProjectDetail;
    relatedProjects: PublicProjectSummary[];
    siteUrl: string;
}) {
    return (
        <SiteLayout
            title={project.title}
            description={project.summary}
            path={`/proyectos/${project.slug}`}
            siteUrl={siteUrl}
        >
            <section className="av-page-heading">
                <Breadcrumbs label={`Proyectos / ${project.title}`} />
                <p className="av-eyebrow">
                    {project.service_name}
                    {project.location ? ` · ${project.location}` : ''}
                    {project.completed_year
                        ? ` · ${project.completed_year}`
                        : ''}
                </p>
                <h1>{project.title}</h1>
                <p>{project.summary}</p>
            </section>
            {project.image && (
                <img
                    className="av-detail-image"
                    src={project.image.url}
                    alt={project.image.alt}
                    width={project.image.width}
                    height={project.image.height}
                    fetchPriority="high"
                />
            )}
            <section className="av-section">
                <h2>El proyecto.</h2>
                <p className="av-prose">
                    {project.description || project.summary}
                </p>
            </section>
            {project.images.length > 0 && (
                <section
                    className="av-section av-gallery av-grid av-grid-three"
                    aria-label="Fotografías del proyecto"
                >
                    {project.images.map((image) => (
                        <figure key={image.id}>
                            <img
                                src={image.url}
                                alt={image.alt}
                                width={image.width}
                                height={image.height}
                                loading="lazy"
                            />
                            {image.caption && (
                                <figcaption>{image.caption}</figcaption>
                            )}
                        </figure>
                    ))}
                </section>
            )}
            {relatedProjects.length > 0 && (
                <ProjectSection
                    title="Otros proyectos realizados."
                    projects={relatedProjects}
                />
            )}
            <ContactCta />
        </SiteLayout>
    );
}

import { Link } from '@inertiajs/react';
import type { PublicProjectSummary } from '@/types/public-project';
export function ProjectCard({ project }: { project: PublicProjectSummary }) {
    return (
        <article className="av-project">
            <Link href={project.href} tabIndex={-1} aria-hidden="true">
                {project.image ? (
                    <img
                        src={project.image.url}
                        alt=""
                        width={project.image.width}
                        height={project.image.height}
                        loading="lazy"
                    />
                ) : (
                    <div className="av-image-missing">
                        Fotografía no disponible
                    </div>
                )}
            </Link>
            <div className="av-project-copy">
                <p className="av-eyebrow">{project.service_name}</p>
                <h3>
                    <Link href={project.href}>
                        {project.title}
                        <span aria-hidden="true">↗</span>
                    </Link>
                </h3>
                {project.location && <p>{project.location}</p>}
                <p>{project.summary}</p>
            </div>
        </article>
    );
}

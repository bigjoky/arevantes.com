import { Link } from '@inertiajs/react';
import { ArrowUpRight, MapPin } from 'lucide-react';
import type { PublicProjectSummary } from '@/types/public-project';

export function ProjectCard({ project }: { project: PublicProjectSummary }) {
    return (
        <article className="group flex h-full flex-col overflow-hidden rounded-[1.75rem] border border-zinc-200 bg-white shadow-[0_12px_36px_rgba(24,24,27,.04)] transition duration-500 hover:-translate-y-1.5 hover:border-[#289548]/35 hover:shadow-[0_24px_55px_rgba(40,149,72,.11)]">
            <Link
                href={project.href}
                className="relative block aspect-[4/3] overflow-hidden bg-zinc-100"
                aria-label={`Ver proyecto: ${project.title}`}
            >
                {project.image ? (
                    <img
                        src={project.image.url}
                        alt={project.image.alt}
                        width={project.image.width}
                        height={project.image.height}
                        loading="lazy"
                        className="h-full w-full object-cover transition duration-700 group-hover:scale-105"
                    />
                ) : (
                    <div className="h-full bg-[radial-gradient(circle_at_70%_25%,rgba(135,181,80,.35),transparent_30%),linear-gradient(145deg,#17301f,#08110c)]" />
                )}
                <span className="absolute top-5 left-5 rounded-full border border-white/30 bg-zinc-950/70 px-3 py-1.5 text-xs font-bold tracking-[.12em] text-white uppercase backdrop-blur-md">
                    {project.service_name}
                </span>
            </Link>
            <div className="flex flex-1 flex-col p-6 sm:p-7">
                <div className="flex flex-wrap items-center gap-x-4 gap-y-2 text-xs font-medium text-zinc-500">
                    {project.location && (
                        <span className="flex items-center gap-1.5">
                            <MapPin className="size-3.5 text-[#289548]" />
                            {project.location}
                        </span>
                    )}
                    {project.completed_year && (
                        <span>{project.completed_year}</span>
                    )}
                </div>
                <h3 className="mt-4 text-2xl font-semibold tracking-[-.03em]">
                    <Link href={project.href}>{project.title}</Link>
                </h3>
                <p className="mt-3 line-clamp-3 leading-7 text-zinc-600">
                    {project.summary}
                </p>
                <Link
                    href={project.href}
                    className="mt-auto inline-flex items-center gap-2 pt-7 text-sm font-semibold text-[#217a3b]"
                >
                    Ver proyecto
                    <ArrowUpRight className="size-4 transition-transform group-hover:translate-x-0.5 group-hover:-translate-y-0.5" />
                </Link>
            </div>
        </article>
    );
}

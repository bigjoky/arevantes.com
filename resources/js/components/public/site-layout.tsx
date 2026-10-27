import { Head, Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import type { PublicProjectSummary } from '@/types/public-project';
import { ProjectCard } from './project-card';
import { areas, steps } from './site-content';
import { PublicSiteFooter } from './site-footer';
import { PublicSiteHeader } from './site-header';
import '../../../css/public-site.css';

export function SiteLayout({
    title,
    description,
    path = '',
    siteUrl,
    children,
}: {
    title: string;
    description: string;
    path?: string;
    siteUrl: string;
    children: ReactNode;
}) {
    return (
        <div className="av-site">
            <Head>
                <title head-key="title">{title}</title>
                <meta
                    head-key="description"
                    name="description"
                    content={description}
                />
                <link
                    head-key="canonical"
                    rel="canonical"
                    href={`${siteUrl}${path}`}
                />
                <meta
                    head-key="og-title"
                    property="og:title"
                    content={`${`${title} · Arevantes`}`}
                />
                <meta
                    head-key="og-description"
                    property="og:description"
                    content={description}
                />
                <meta
                    head-key="og-url"
                    property="og:url"
                    content={`${siteUrl}${path}`}
                />
                <meta head-key="og-type" property="og:type" content="website" />
                <meta
                    head-key="og-image"
                    property="og:image"
                    content={`${siteUrl}/og-multirramo-v2.png`}
                />
            </Head>
            <a className="av-skip" href="#contenido">
                Saltar al contenido
            </a>
            <PublicSiteHeader active={path} />
            <main id="contenido">{children}</main>
            <PublicSiteFooter />
        </div>
    );
}
export function Breadcrumbs({ label }: { label: string }) {
    return (
        <nav className="av-breadcrumbs" aria-label="Migas de pan">
            <Link href="/">Inicio</Link>
            <span aria-hidden="true">/</span>
            <span aria-current="page">{label}</span>
        </nav>
    );
}
export function TextLink({
    href,
    children,
}: {
    href: string;
    children: ReactNode;
}) {
    return (
        <Link className="av-text-link" href={href}>
            {children} <span aria-hidden="true">→</span>
        </Link>
    );
}
export function ContactCta() {
    return (
        <section className="av-cta">
            <div>
                <h2>¿Hablamos de tu proyecto?</h2>
                <p>
                    Estudiaremos tu caso y te propondremos la solución más
                    adecuada.
                </p>
                <Link className="av-button av-button-light" href="/contacto">
                    Cuéntanos tu proyecto <span aria-hidden="true">→</span>
                </Link>
            </div>
        </section>
    );
}
export function ServiceGrid() {
    return (
        <div className="av-grid av-grid-four">
            {areas.map((area) => (
                <article className="av-service" key={area.slug}>
                    <Link
                        href={`/servicios/${area.slug}`}
                        tabIndex={-1}
                        aria-hidden="true"
                    >
                        {area.image ? (
                            <img
                                src={area.image}
                                alt=""
                                width="600"
                                height="400"
                                loading="lazy"
                            />
                        ) : (
                            <div className="av-network-art">
                                <span>LAN / WLAN</span>
                                <i />
                                <i />
                                <i />
                                <small>Infraestructura conectada</small>
                            </div>
                        )}
                    </Link>
                    <h3>
                        <Link href={`/servicios/${area.slug}`}>
                            {area.name}
                        </Link>
                    </h3>
                    <p>{area.description}</p>
                    <TextLink href={`/servicios/${area.slug}`}>
                        Saber más
                    </TextLink>
                </article>
            ))}
        </div>
    );
}
export function ProcessSteps() {
    return (
        <ol className="av-steps">
            {steps.map((step, index) => (
                <li key={step.title}>
                    <span className="av-step-number">
                        0{index + 1}
                        <span aria-hidden="true">↗</span>
                    </span>
                    <h3>{step.title}</h3>
                    <p>{step.description}</p>
                </li>
            ))}
        </ol>
    );
}
export function ProjectSection({
    projects,
    title = 'Instalaciones reales, resultados medibles.',
}: {
    projects: PublicProjectSummary[];
    title?: string;
}) {
    return (
        <section className="av-section av-tinted">
            <div className="av-section-heading">
                <div>
                    <p className="av-eyebrow">Proyectos destacados</p>
                    <h2>{title}</h2>
                </div>
                <TextLink href="/proyectos">Ver todos los proyectos</TextLink>
            </div>
            {projects.length ? (
                <div className="av-grid av-grid-three">
                    {projects.map((project) => (
                        <ProjectCard key={project.slug} project={project} />
                    ))}
                </div>
            ) : (
                <div className="av-empty">
                    <p>
                        No hay proyectos publicados en esta sección por el
                        momento.
                    </p>
                    <TextLink href="/contacto">
                        Consultar una instalación similar
                    </TextLink>
                </div>
            )}
        </section>
    );
}

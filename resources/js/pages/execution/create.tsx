import { Head, Link } from '@inertiajs/react';
import { PageHeader, PageShell, PaginationBar } from '@/components/page-shell';
import type { Pagination } from '@/components/projects/types';
import { Button } from '@/components/ui/button';
type Props = {
    quotes: Pagination & {
        data: {
            id: number;
            number: string;
            customer: {
                first_name: string;
                last_name: string | null;
                company_name: string | null;
            } | null;
        }[];
    };
};
export default function CreateProject({ quotes }: Props) {
    return (
        <>
            <Head title="Nuevo proyecto" />
            <PageShell className="projects-workspace">
                <PageHeader
                    title="Nuevo proyecto"
                    description="Selecciona un presupuesto aceptado para continuar con su proyecto."
                    breadcrumbs={[
                        { title: 'Proyectos', href: '/execution' },
                        { title: 'Proyectos', href: '/execution/projects' },
                        {
                            title: 'Nuevo proyecto',
                            href: '/execution/projects/create',
                        },
                    ]}
                    secondaryNavigation={null}
                />
                <section className="projects-panel">
                    {quotes.data.length ? (
                        quotes.data.map((q) => (
                            <div className="projects-event" key={q.id}>
                                <strong>{q.number}</strong>
                                <span>
                                    {q.customer?.company_name ||
                                        (q.customer?.first_name ?? '') +
                                            ' ' +
                                            (q.customer?.last_name ?? '')}
                                </span>
                                <Button asChild variant="outline">
                                    <Link href={'/quotes/' + q.id}>
                                        Abrir presupuesto
                                    </Link>
                                </Button>
                            </div>
                        ))
                    ) : (
                        <p className="projects-empty">
                            No hay presupuestos aceptados pendientes de
                            proyecto.
                        </p>
                    )}
                    <PaginationBar
                        links={quotes.links}
                        total={quotes.total}
                        noun="presupuestos"
                    />
                </section>
                <Link href="/execution/projects">Volver a proyectos</Link>
            </PageShell>
        </>
    );
}

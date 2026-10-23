import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowUpRight } from 'lucide-react';
import { PageHeader, PageShell } from '@/components/page-shell';
import { useLocale } from '@/hooks/use-locale';
import { translate } from '@/i18n';
import { contextualHref } from '@/lib/workspace-navigation';
import type { WorkspaceNavItem } from '@/types';

export default function WorkspaceModule({
    module,
    section,
}: {
    module: string;
    section: WorkspaceNavItem;
}) {
    const page = usePage();
    const { locale } = useLocale();
    const year =
        typeof page.props.year === 'number'
            ? page.props.year
            : page.props.workspaceContext?.currentYear;

    return (
        <>
            <Head title={`${section.title} · ${module}`} />
            <PageShell>
                <PageHeader
                    title={section.title}
                    description={module}
                    secondaryNavigation={null}
                />
                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    {section.links?.map((link) => (
                        <Link
                            className="flex items-center justify-between gap-3 rounded-lg border p-5 text-sm hover:bg-accent"
                            key={link.href}
                            href={contextualHref(link.href, year)}
                        >
                            {link.title}
                            <ArrowUpRight className="size-4" />
                        </Link>
                    ))}
                </div>
                {!!section.pending?.length && (
                    <section className="rounded-lg border border-dashed p-5">
                        <h2 className="text-sm font-semibold">
                            {translate('Pendiente de implantación', locale)}
                        </h2>
                        <ul className="mt-3 list-inside list-disc space-y-2 text-sm text-muted-foreground">
                            {section.pending.map((item) => (
                                <li key={item}>{item}</li>
                            ))}
                        </ul>
                        <p className="mt-3 text-xs text-muted-foreground">
                            {translate(
                                'Estas capacidades están planificadas en el roadmap y todavía no están disponibles.',
                                locale,
                            )}
                        </p>
                    </section>
                )}
            </PageShell>
        </>
    );
}

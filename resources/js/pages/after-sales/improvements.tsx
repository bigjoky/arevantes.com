import { Head, Link } from '@inertiajs/react';
import { AlertTriangle, CheckCircle2, Clock3, XCircle } from 'lucide-react';
import { ImprovementDecisionInbox } from '@/components/after-sales/improvement-decision-inbox';
import type { ImprovementInboxProposal } from '@/components/after-sales/improvement-decision-inbox';
import { PageHeader, PageShell, PaginationBar } from '@/components/page-shell';
import type { PaginationLink } from '@/components/page-shell';
import { Button } from '@/components/ui/button';
import { useLocale } from '@/hooks/use-locale';
import { translate } from '@/i18n';

type Props = {
    proposals: {
        data: ImprovementInboxProposal[];
        links: PaginationLink[];
        total: number;
    };
    filters: { status: 'open' | 'approved' | 'applied' | 'rejected' | 'all' };
    summary: {
        open: number;
        approved: number;
        applied: number;
        rejected: number;
        conflicting: number;
    };
    canManageOperations: boolean;
};

const views = ['open', 'approved', 'applied', 'rejected', 'all'] as const;

export default function ImprovementDecisions({
    proposals,
    filters,
    summary,
    canManageOperations,
}: Props) {
    const { locale } = useLocale();

    return (
        <>
            <Head title={translate('Improvement decisions', locale)} />
            <PageShell>
                <PageHeader
                    title="Improvement decisions"
                    description="Each area reviews only the CAPA proposals within its authority. Approval records intent and evidence, but never applies the downstream change automatically."
                    actions={
                        canManageOperations ? (
                            <Button asChild variant="outline">
                                <Link href="/after-sales/quality">
                                    Return to service quality
                                </Link>
                            </Button>
                        ) : undefined
                    }
                />

                <section className="grid grid-cols-2 border-y md:grid-cols-5">
                    <Metric label="Pending decisions" value={summary.open} icon={Clock3} />
                    <Metric
                        label="Conflicting proposals"
                        value={summary.conflicting}
                        icon={AlertTriangle}
                        attention={summary.conflicting > 0}
                    />
                    <Metric label="Ready to apply" value={summary.approved} icon={CheckCircle2} />
                    <Metric label="Applied" value={summary.applied} icon={CheckCircle2} />
                    <Metric label="Rejected" value={summary.rejected} icon={XCircle} />
                </section>

                <nav className="flex flex-wrap gap-2" aria-label="Improvement proposal views">
                    {views.map((view) => (
                        <Button
                            key={view}
                            size="sm"
                            variant={filters.status === view ? 'default' : 'outline'}
                            asChild
                        >
                            <Link href={`/improvement-decisions?status=${view}`}>
                                {viewLabel(view)}
                            </Link>
                        </Button>
                    ))}
                </nav>

                <ImprovementDecisionInbox proposals={proposals.data} />
                <PaginationBar
                    links={proposals.links}
                    total={proposals.total}
                    noun="proposals"
                />
            </PageShell>
        </>
    );
}

function Metric({
    label,
    value,
    icon: Icon,
    attention = false,
}: {
    label: string;
    value: number;
    icon: typeof Clock3;
    attention?: boolean;
}) {
    return (
        <div className="border-r p-4 last:border-r-0">
            <div className="mb-2 flex items-center gap-2 text-xs text-muted-foreground">
                <Icon className="size-4" /> {label}
            </div>
            <div className={attention ? 'text-xl font-semibold text-destructive' : 'text-xl font-semibold'}>
                {value}
            </div>
        </div>
    );
}

function viewLabel(view: (typeof views)[number]) {
    if (view === 'open') {
        return 'Awaiting decision';
    }

    return view.replace(/^./, (letter) => letter.toUpperCase());
}

ImprovementDecisions.layout = {
    breadcrumbs: [
        { title: 'Improvement decisions', href: '/improvement-decisions' },
    ],
};

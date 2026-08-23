import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    CalendarDays,
    CircleAlert,
    CircleDollarSign,
    ClipboardList,
    MapPinned,
    TrendingUp,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { lazy, Suspense, useEffect } from 'react';
import type {
    serviceLabels,
    statusLabels,
} from '@/components/installations/installation-form';
import { PageHeader, PageShell } from '@/components/page-shell';
import { Badge } from '@/components/ui/badge';
import { Card } from '@/components/ui/card';
import { dashboard } from '@/routes';

const InstallationMap = lazy(
    () => import('@/components/dashboard/installation-map'),
);

export type DashboardInstallation = {
    id: number;
    customer_name: string | null;
    customer_company: string | null;
    service_type: keyof typeof serviceLabels;
    deye_status: 'healthy' | 'fault' | 'disconnected' | null;
    deye_current_power_kw: string | null;
    deye_today_energy_kwh: string | null;
    deye_status_message: string | null;
    huawei_status: 'healthy' | 'fault' | 'disconnected' | null;
    huawei_current_power_kw: string | null;
    huawei_today_energy_kwh: string | null;
    huawei_status_message: string | null;
    monitoring: {
        provider: 'huawei' | 'deye';
        station_name: string | null;
        status: 'healthy' | 'fault' | 'disconnected' | null;
        current_power_kw: string | null;
        today_energy_kwh: string | null;
        message: string;
        synced_at: string | null;
    } | null;
    status: keyof typeof statusLabels;
    project_reference: string | null;
    site_name: string | null;
    site_city: string | null;
    site_locality: string | null;
    site_province_state: string | null;
    site_postal_code: string | null;
    site_country: string | null;
    site_latitude: string | null;
    site_longitude: string | null;
    scheduled_date: string | null;
    system_power_kw: string | null;
    charger_power_kw: string | null;
};

type DashboardProps = {
    installations: DashboardInstallation[];
    peopleSummary: {
        total: number;
        customers: number;
        staff: number;
    };
    installationSummary: {
        total: number;
        active: number;
        completed: number;
    };
    businessSummary: {
        open_opportunities: number;
        active_work_orders: number;
        overdue_activities: number;
        outstanding_amount: number;
    };
    agenda: Array<{
        id: number;
        type: string;
        title: string;
        priority: string;
        when: string | null;
        installation: string | null;
    }>;
};

export default function Dashboard({
    installations,
    peopleSummary,
    installationSummary,
    businessSummary,
    agenda,
}: DashboardProps) {
    const { abilities } = usePage().props.auth;
    const hasOverview =
        abilities.viewInstallations ||
        abilities.manageCustomers ||
        abilities.manageOperations ||
        abilities.viewFinance;

    useEffect(() => {
        if (!abilities.viewInstallations) {
            return;
        }

        const interval = window.setInterval(() => {
            router.reload({
                only: ['installations', 'installationSummary'],
            });
        }, 60_000);

        return () => window.clearInterval(interval);
    }, [abilities.viewInstallations]);

    return (
        <>
            <Head title="Dashboard" />

            <PageShell>
                <PageHeader
                    title="Workspace overview"
                    description="Customers, delivery, pipeline and cash position at a glance."
                />

                {hasOverview ? (
                    <section className="grid gap-px overflow-hidden rounded-lg border bg-border sm:grid-cols-2 xl:grid-cols-4">
                        {abilities.viewInstallations && (
                            <OverviewMetric
                                href="/installations"
                                label="Active installations"
                                value={String(installationSummary.active)}
                                detail={`${installationSummary.total} total`}
                                icon={MapPinned}
                            />
                        )}
                        {abilities.manageCustomers && (
                            <OverviewMetric
                                href="/opportunities"
                                label="Open opportunities"
                                value={String(
                                    businessSummary.open_opportunities,
                                )}
                                detail={`${peopleSummary.customers} customers`}
                                icon={TrendingUp}
                            />
                        )}
                        {abilities.manageOperations && (
                            <OverviewMetric
                                href="/work-orders"
                                label="Active work orders"
                                value={String(
                                    businessSummary.active_work_orders,
                                )}
                                detail={`${businessSummary.overdue_activities} overdue activities`}
                                icon={ClipboardList}
                                attention={
                                    businessSummary.overdue_activities > 0
                                }
                            />
                        )}
                        {abilities.viewFinance && (
                            <OverviewMetric
                                href="/finance"
                                label="Outstanding invoices"
                                value={money(
                                    businessSummary.outstanding_amount,
                                )}
                                detail={
                                    abilities.manageStaff
                                        ? `${peopleSummary.staff} staff members`
                                        : 'Financial position'
                                }
                                icon={CircleDollarSign}
                            />
                        )}
                    </section>
                ) : (
                    <Card className="p-6 text-sm text-muted-foreground">
                        Your profile has no operational sections assigned. Use
                        Mi RR. HH. or your personal settings from the
                        navigation.
                    </Card>
                )}

                {(abilities.viewInstallations ||
                    abilities.manageOperations) && (
                    <div
                        className={`grid min-w-0 gap-6 ${abilities.viewInstallations && abilities.manageOperations ? 'xl:grid-cols-[minmax(0,1fr)_20rem]' : ''}`}
                    >
                        {abilities.viewInstallations && (
                            <Suspense
                                fallback={
                                    <div className="min-h-[34rem] animate-pulse rounded-lg bg-muted" />
                                }
                            >
                                <InstallationMap
                                    installations={installations}
                                    canManageInstallations={
                                        abilities.manageInstallations
                                    }
                                />
                            </Suspense>
                        )}

                        {abilities.manageOperations && (
                            <Card className="gap-0 overflow-hidden py-0">
                                <div className="flex items-center justify-between border-b p-4">
                                    <div className="flex items-center gap-2">
                                        <CalendarDays className="size-4 text-muted-foreground" />
                                        <h2 className="font-semibold">
                                            Next 7 days
                                        </h2>
                                    </div>
                                    <Badge variant="outline">
                                        {agenda.length}
                                    </Badge>
                                </div>
                                <div className="divide-y">
                                    {agenda.length ? (
                                        agenda.map((item) => (
                                            <Link
                                                key={item.id}
                                                href="/operations"
                                                className="block p-4 transition-colors hover:bg-muted/50"
                                            >
                                                <div className="flex items-start justify-between gap-3">
                                                    <div className="font-medium">
                                                        {item.title}
                                                    </div>
                                                    {item.priority ===
                                                    'urgent' ? (
                                                        <CircleAlert className="size-4 shrink-0 text-destructive" />
                                                    ) : null}
                                                </div>
                                                <div className="mt-1 text-xs text-muted-foreground">
                                                    {formatAgendaDate(
                                                        item.when,
                                                    )}
                                                </div>
                                                <div className="mt-1 truncate text-xs text-muted-foreground">
                                                    {item.installation ||
                                                        item.type}
                                                </div>
                                            </Link>
                                        ))
                                    ) : (
                                        <div className="grid min-h-40 place-items-center p-6 text-center text-sm text-muted-foreground">
                                            No scheduled activity in the next 7
                                            days.
                                        </div>
                                    )}
                                </div>
                            </Card>
                        )}
                    </div>
                )}
            </PageShell>
        </>
    );
}

function OverviewMetric({
    href,
    label,
    value,
    detail,
    icon: Icon,
    attention = false,
}: {
    href: string;
    label: string;
    value: string;
    detail: string;
    icon: LucideIcon;
    attention?: boolean;
}) {
    return (
        <Link
            href={href}
            className="group bg-background p-4 transition-colors hover:bg-muted/50"
        >
            <div className="flex items-center gap-2 text-sm text-muted-foreground">
                <Icon className="size-4" />
                {label}
            </div>
            <div className="mt-2 flex items-end justify-between gap-3">
                <div className="text-2xl font-semibold tracking-tight">
                    {value}
                </div>
                <span
                    className={`text-xs ${attention ? 'text-destructive' : 'text-muted-foreground'}`}
                >
                    {detail}
                </span>
            </div>
        </Link>
    );
}

const money = (value: number) =>
    new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: 'EUR',
        maximumFractionDigits: 0,
    }).format(value);

function formatAgendaDate(value: string | null) {
    return value
        ? new Intl.DateTimeFormat('es-ES', {
              day: '2-digit',
              month: '2-digit',
              hour: '2-digit',
              minute: '2-digit',
          }).format(new Date(value))
        : 'No date';
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};

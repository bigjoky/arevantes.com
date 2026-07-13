import { Head, router, usePage } from '@inertiajs/react';
import { CalendarDays } from 'lucide-react';
import { lazy, Suspense, useEffect } from 'react';
import type {
    serviceLabels,
    statusLabels,
} from '@/components/installations/installation-form';
import { dashboard } from '@/routes';
import type { User } from '@/types';

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
    agenda: Array<{
        id: number;
        type: string;
        title: string;
        priority: string;
        when: string | null;
        installation: string | null;
    }>;
};

export default function Dashboard({ installations, agenda }: DashboardProps) {
    const { user } = usePage<{ auth: { user: User } }>().props.auth;
    const canManageInstallations = user.role !== 'technician';

    useEffect(() => {
        const interval = window.setInterval(() => {
            router.reload({
                only: ['installations', 'installationSummary'],
            });
        }, 60_000);

        return () => window.clearInterval(interval);
    }, []);

    return (
        <>
            <Head title="Dashboard" />

            <div className="flex h-full flex-1 flex-col p-4">
                {agenda.length ? (
                    <div className="mb-3 flex items-center gap-3 overflow-x-auto border-b pb-3">
                        <CalendarDays className="size-4 shrink-0 text-muted-foreground" />
                        <div className="flex gap-2">
                            {agenda.map((item) => (
                                <a
                                    key={item.id}
                                    href="/operations"
                                    className="min-w-56 border-l px-3 text-sm"
                                >
                                    <div className="font-medium">
                                        {item.title}
                                    </div>
                                    <div className="text-xs text-muted-foreground">
                                        {formatAgendaDate(item.when)} ·{' '}
                                        {item.installation || item.type}
                                    </div>
                                </a>
                            ))}
                        </div>
                    </div>
                ) : null}
                <Suspense
                    fallback={
                        <div className="min-h-[calc(100vh-7rem)] animate-pulse rounded-lg bg-muted" />
                    }
                >
                    <InstallationMap
                        installations={installations}
                        canManageInstallations={canManageInstallations}
                    />
                </Suspense>
            </div>
        </>
    );
}

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

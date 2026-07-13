import { Link } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import {
    BatteryCharging,
    MapPin,
    Plus,
    PlugZap,
    Snowflake,
    Sun,
} from 'lucide-react';
import { useState } from 'react';
import {
    serviceLabels,
    statusLabels,
} from '@/components/installations/installation-form';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import {
    Map,
    MapControls,
    MapMarker,
    MapPopup,
    MarkerContent,
} from '@/components/ui/map';
import type { DashboardInstallation } from '@/pages/dashboard';

export default function InstallationMap({
    installations,
    canManageInstallations,
}: {
    installations: DashboardInstallation[];
    canManageInstallations: boolean;
}) {
    const mappedInstallations = installations.filter(hasCoordinates);
    const [selectedId, setSelectedId] = useState<number | null>(null);
    const selected =
        mappedInstallations.find(
            (installation) => installation.id === selectedId,
        ) ?? null;

    return (
        <Card className="relative min-h-[calc(100vh-7rem)] overflow-hidden rounded-lg p-0">
            <Map center={[-3.7038, 40.4168]} zoom={5.4}>
                <MapControls />
                {mappedInstallations.map((installation) => (
                    <MapMarker
                        key={installation.id}
                        longitude={Number(installation.site_longitude)}
                        latitude={Number(installation.site_latitude)}
                        onClick={() => setSelectedId(installation.id)}
                    >
                        <MarkerContent
                            ariaLabel={`Open ${installation.project_reference || installation.site_name || serviceLabels[installation.service_type]}`}
                            className={markerClassName(
                                installation.service_type,
                                installation.monitoring?.status ?? null,
                            )}
                        >
                            <ServiceIcon
                                service={installation.service_type}
                                className="size-4"
                            />
                        </MarkerContent>
                    </MapMarker>
                ))}
                {selected ? (
                    <MapPopup
                        longitude={Number(selected.site_longitude)}
                        latitude={Number(selected.site_latitude)}
                        onClose={() => setSelectedId(null)}
                    >
                        <InstallationPreview installation={selected} />
                    </MapPopup>
                ) : null}
            </Map>

            <div className="pointer-events-none absolute top-4 left-4 z-10 grid max-w-[calc(100%-2rem)] gap-3 md:max-w-md">
                <div className="rounded-md border bg-background/95 p-4 shadow-sm backdrop-blur">
                    <div className="flex flex-wrap items-center gap-2">
                        <MapPin className="size-4 text-muted-foreground" />
                        <h1 className="text-base font-semibold">
                            Installation map
                        </h1>
                        <Badge variant="outline">
                            {mappedInstallations.length} mapped
                        </Badge>
                    </div>
                    <p className="mt-2 text-sm text-muted-foreground">
                        Markers appear when an installation has map coordinates.
                    </p>
                    <div className="pointer-events-auto mt-3 flex gap-2">
                        {canManageInstallations ? (
                            <Button size="sm" asChild>
                                <Link href="/installations/create">
                                    <Plus /> Add installation
                                </Link>
                            </Button>
                        ) : null}
                        <Button size="sm" variant="outline" asChild>
                            <Link href="/installations">View all</Link>
                        </Button>
                    </div>
                </div>
            </div>
        </Card>
    );
}

function hasCoordinates(installation: DashboardInstallation) {
    return (
        installation.site_latitude !== null &&
        installation.site_longitude !== null &&
        !Number.isNaN(Number(installation.site_latitude)) &&
        !Number.isNaN(Number(installation.site_longitude))
    );
}

function InstallationPreview({
    installation,
}: {
    installation: DashboardInstallation;
}) {
    const monitoring = installation.monitoring;

    return (
        <div className="w-80 max-w-[calc(100vw-5rem)] bg-popover p-1 pr-5 text-popover-foreground">
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                    <div className="flex flex-wrap items-center gap-2">
                        <ServiceIcon
                            service={installation.service_type}
                            className="size-4 text-muted-foreground"
                        />
                        <div className="font-medium text-popover-foreground">
                            {installation.project_reference ||
                                serviceLabels[installation.service_type]}
                        </div>
                        <Badge variant="outline">
                            {statusLabels[installation.status]}
                        </Badge>
                    </div>
                    <div className="mt-1 text-sm text-muted-foreground">
                        {installation.customer_company ||
                            installation.customer_name ||
                            'No customer'}
                    </div>
                </div>
                <Button size="sm" asChild>
                    <Link href={`/installations/${installation.id}`}>Open</Link>
                </Button>
            </div>
            <div className="mt-3 grid gap-2 text-sm text-muted-foreground">
                <span>
                    {[
                        installation.site_name,
                        installation.site_locality,
                        installation.site_city,
                        installation.site_province_state,
                    ]
                        .filter(Boolean)
                        .join(', ') || 'No site'}
                </span>
                <span>
                    {installation.scheduled_date || 'No scheduled date'}
                </span>
                {monitoring ? (
                    <div className="mt-1 border-t pt-3">
                        <div className="flex flex-wrap items-center gap-2">
                            <Badge variant="outline">
                                {monitoring.provider === 'huawei'
                                    ? 'Huawei FusionSolar'
                                    : 'Deye Cloud'}
                            </Badge>
                            <span
                                className={`size-2 rounded-full ${statusDotClass(monitoring.status)}`}
                            />
                            <span className="font-medium text-popover-foreground">
                                {statusText(monitoring.status)}
                            </span>
                        </div>
                        <div className="mt-2 truncate text-xs">
                            {monitoring.station_name ||
                                'Unnamed monitoring station'}
                        </div>
                        <div className="mt-3 grid grid-cols-2 gap-2">
                            <Metric
                                label="Power now"
                                value={`${formatMetric(monitoring.current_power_kw)} kW`}
                            />
                            <Metric
                                label="Energy today"
                                value={`${formatMetric(monitoring.today_energy_kwh)} kWh`}
                            />
                        </div>
                        <p className="mt-3 text-xs">{monitoring.message}</p>
                        <p className="mt-1 text-[11px]">
                            Updated {formatMonitoringDate(monitoring.synced_at)}
                        </p>
                    </div>
                ) : (
                    <span>
                        {installation.system_power_kw
                            ? `${installation.system_power_kw} kW installed · Monitoring not connected`
                            : installation.charger_power_kw
                              ? `${installation.charger_power_kw} kW charger`
                              : 'Monitoring not connected'}
                    </span>
                )}
            </div>
        </div>
    );
}

function ServiceIcon({
    service,
    className,
}: {
    service: keyof typeof serviceLabels;
    className?: string;
}) {
    const icons: Record<keyof typeof serviceLabels, LucideIcon> = {
        solar: Sun,
        ev_charger: BatteryCharging,
        hvac: Snowflake,
        electrical: PlugZap,
    };
    const Icon = icons[service];

    return <Icon className={className} />;
}

function markerClassName(
    service: keyof typeof serviceLabels,
    status: 'healthy' | 'fault' | 'disconnected' | null,
) {
    if (status === 'healthy') {
        return 'bg-emerald-600 text-white dark:bg-emerald-500 dark:text-emerald-950';
    }

    if (status === 'fault') {
        return 'bg-red-600 text-white dark:bg-red-500 dark:text-red-950';
    }

    if (status === 'disconnected') {
        return 'bg-zinc-600 text-white dark:bg-zinc-400 dark:text-zinc-950';
    }

    return {
        solar: 'bg-amber-500 text-white',
        ev_charger: 'bg-emerald-600 text-white',
        hvac: 'bg-sky-600 text-white',
        electrical: 'bg-violet-600 text-white',
    }[service];
}

function Metric({ label, value }: { label: string; value: string }) {
    return (
        <div className="rounded-md border border-border bg-secondary p-2 text-secondary-foreground">
            <div className="text-[11px] text-muted-foreground">{label}</div>
            <div className="font-medium text-secondary-foreground">{value}</div>
        </div>
    );
}

function formatMetric(value: string | null) {
    return Number(value ?? 0).toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

function statusText(status: 'healthy' | 'fault' | 'disconnected' | null) {
    return status === 'healthy'
        ? 'Operating'
        : status === 'fault'
          ? 'Fault'
          : status === 'disconnected'
            ? 'Disconnected'
            : 'Pending data';
}

function statusDotClass(status: 'healthy' | 'fault' | 'disconnected' | null) {
    return status === 'healthy'
        ? 'bg-emerald-500'
        : status === 'fault'
          ? 'bg-red-500'
          : status === 'disconnected'
            ? 'bg-zinc-500'
            : 'bg-amber-500';
}

function formatMonitoringDate(value: string | null) {
    return value
        ? new Intl.DateTimeFormat(undefined, {
              dateStyle: 'short',
              timeStyle: 'short',
          }).format(new Date(value))
        : 'not yet';
}

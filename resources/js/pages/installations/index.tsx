import { Head, Link, router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useState } from 'react';
import {
    serviceLabels,
    statusLabels,
} from '@/components/installations/installation-form';
import { PageHeader, PageShell } from '@/components/page-shell';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

type InstallationSummary = {
    id: number;
    customer_name: string | null;
    customer_company: string | null;
    service_type: keyof typeof serviceLabels;
    status: keyof typeof statusLabels;
    project_reference: string | null;
    site_name: string | null;
    site_address_line_1: string | null;
    site_city: string | null;
    site_province_state: string | null;
    scheduled_date: string | null;
};

export default function InstallationsIndex({
    installations,
    filters,
}: {
    installations: InstallationSummary[];
    filters: { search?: string; service_type?: string; status?: string };
}) {
    const [search, setSearch] = useState(filters.search ?? '');

    function applyFilters(key: string, value: string) {
        router.get(
            '/installations',
            { ...filters, search, [key]: value === 'all' ? undefined : value },
            { preserveState: true, replace: true },
        );
    }

    return (
        <>
            <Head title="Installations" />
            <PageShell>
                <PageHeader
                    title="Installations"
                    description="Solar, EV charging, climate, and electrical work."
                    actions={
                        <Button asChild>
                            <Link href="/installations/create">
                                Add installation
                            </Link>
                        </Button>
                    }
                />
                <Card className="rounded-lg">
                    <CardContent className="space-y-4">
                        <form
                            className="grid gap-3 md:grid-cols-[1fr_14rem_14rem_auto]"
                            onSubmit={(event) => {
                                event.preventDefault();
                                applyFilters('search', search);
                            }}
                        >
                            <Input
                                value={search}
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                                placeholder="Search reference, site, or customer"
                            />
                            <Select
                                defaultValue={filters.service_type ?? 'all'}
                                onValueChange={(value) =>
                                    applyFilters('service_type', value)
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Service" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        All services
                                    </SelectItem>
                                    {Object.entries(serviceLabels).map(
                                        ([value, label]) => (
                                            <SelectItem
                                                key={value}
                                                value={value}
                                            >
                                                {label}
                                            </SelectItem>
                                        ),
                                    )}
                                </SelectContent>
                            </Select>
                            <Select
                                defaultValue={filters.status ?? 'all'}
                                onValueChange={(value) =>
                                    applyFilters('status', value)
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Status" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        All statuses
                                    </SelectItem>
                                    {Object.entries(statusLabels).map(
                                        ([value, label]) => (
                                            <SelectItem
                                                key={value}
                                                value={value}
                                            >
                                                {label}
                                            </SelectItem>
                                        ),
                                    )}
                                </SelectContent>
                            </Select>
                            <Button type="submit" variant="outline">
                                <Search />
                                Search
                            </Button>
                        </form>

                        {installations.length > 0 ? (
                            <div className="divide-y overflow-hidden rounded-md border">
                                {installations.map((installation) => (
                                    <Link
                                        key={installation.id}
                                        href={`/installations/${installation.id}`}
                                        className="grid gap-3 p-4 transition-colors hover:bg-muted/50 md:grid-cols-[1fr_auto]"
                                    >
                                        <div className="min-w-0">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <span className="font-medium">
                                                    {installation.project_reference ||
                                                        serviceLabels[
                                                            installation
                                                                .service_type
                                                        ]}
                                                </span>
                                                <Badge variant="outline">
                                                    {
                                                        statusLabels[
                                                            installation.status
                                                        ]
                                                    }
                                                </Badge>
                                                <Badge variant="secondary">
                                                    {
                                                        serviceLabels[
                                                            installation
                                                                .service_type
                                                        ]
                                                    }
                                                </Badge>
                                            </div>
                                            <p className="mt-1 truncate text-sm text-muted-foreground">
                                                {installation.customer_company ||
                                                    installation.customer_name ||
                                                    'No customer'}
                                            </p>
                                        </div>
                                        <div className="text-sm text-muted-foreground md:text-right">
                                            <div>
                                                {[
                                                    installation.site_name,
                                                    installation.site_city,
                                                    installation.site_province_state,
                                                ]
                                                    .filter(Boolean)
                                                    .join(', ') ||
                                                    installation.site_address_line_1}
                                            </div>
                                            <div>
                                                {installation.scheduled_date ||
                                                    'No scheduled date'}
                                            </div>
                                        </div>
                                    </Link>
                                ))}
                            </div>
                        ) : (
                            <div className="rounded-md border border-dashed p-6 text-sm text-muted-foreground">
                                No installations match these filters.
                            </div>
                        )}
                    </CardContent>
                </Card>
            </PageShell>
        </>
    );
}

InstallationsIndex.layout = {
    breadcrumbs: [{ title: 'Installations', href: '/installations' }],
};

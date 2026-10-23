import { usePage } from '@inertiajs/react';
import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { ShellContext } from '@/components/shell-context';
import type { AppLayoutProps } from '@/types';

export default function AppSidebarLayout({
    children,
    breadcrumbs = [],
}: AppLayoutProps) {
    const page = usePage();
    const requestedYear = Number(
        new URL(page.url, 'http://localhost').searchParams.get('year'),
    );
    const year =
        typeof page.props.year === 'number'
            ? page.props.year
            : Number.isInteger(requestedYear) &&
                requestedYear >= 2000 &&
                requestedYear <= 2100
              ? requestedYear
              : page.props.workspaceContext?.currentYear;

    return (
        <ShellContext.Provider value={{ breadcrumbs, year }}>
            <AppShell variant="sidebar">
                <AppSidebar />
                <AppContent
                    variant="sidebar"
                    className="min-w-0 overflow-x-clip"
                >
                    <AppSidebarHeader />
                    {breadcrumbs.length > 0 && (
                        <div className="shell-legacy-breadcrumbs">
                            <Breadcrumbs breadcrumbs={breadcrumbs} />
                        </div>
                    )}
                    {children}
                </AppContent>
            </AppShell>
        </ShellContext.Provider>
    );
}

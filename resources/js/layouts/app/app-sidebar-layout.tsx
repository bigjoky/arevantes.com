import { router, usePage } from '@inertiajs/react';
import { useEffect } from 'react';
import { AccountingContextPanel } from '@/components/accounting/context-panel';
import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { MobileExperience } from '@/components/mobile/mobile-experience';
import { ShellContext } from '@/components/shell-context';
import { useIsMobile } from '@/hooks/use-mobile';
import type { AppLayoutProps } from '@/types';

export default function AppSidebarLayout({
    children,
    breadcrumbs = [],
}: AppLayoutProps) {
    const page = usePage();
    const isMobile = useIsMobile(1024);
    const requestedYear = Number(
        new URL(page.url, 'http://localhost').searchParams.get('year'),
    );
    const year =
        page.props.workspaceContext?.currentYear != null
            ? page.props.workspaceContext.currentYear
            : Number.isInteger(requestedYear) &&
                requestedYear >= 2000 &&
                requestedYear <= 2100
              ? requestedYear
              : page.props.workspaceContext?.currentYear;

    useEffect(() => router.on('before', (event) => {
        const visit = event.detail.visit;

        if (visit.method === 'get' || !year) {
return;
}

        const path = visit.url.pathname;
        const field = /^(\/expenses|\/supplier-invoices|\/sales)\/\d+\/payments$/.test(path) ? 'paid_at'
            : /^\/expenses(?:\/\d+)?$/.test(path) ? 'expense_date'
              : /^(\/purchases|\/sales)(?:\/\d+)?(?:\/invoice|\/issue)?$/.test(path) ? 'issue_date'
                : /^\/accounting\/(?:entries|drafts)(?:\/\d+)?$/.test(path) ? 'entry_date' : null;

        if (!field) {
return;
}

        const data = visit.data;
        const value = data instanceof FormData ? data.get(field) : data[field];

        if (typeof value !== 'string' || !/^\d{4}-\d{2}-\d{2}$/.test(value) || Number(value.slice(0, 4)) === year) {
return;
}

        if (!window.confirm(`La fecha ${value} pertenece a otro ejercicio distinto de ${year}. ¿Confirmas expresamente esta operación?`)) {
return false;
}

        if (data instanceof FormData) {
data.set('confirm_other_year', '1');
} else {
data.confirm_other_year = true;
}
    }), [year]);

    return (
        <ShellContext.Provider value={{ breadcrumbs, year }}>
            <AppShell variant="sidebar">
                {!isMobile && <AppSidebar />}
                <AppContent
                    variant="sidebar"
                    className="min-w-0 overflow-x-clip"
                >
                    <MobileExperience
                        active={isMobile}
                        desktopChrome={
                            <>
                                <AppSidebarHeader />
                                {breadcrumbs.length > 0 && (
                                    <div className="shell-legacy-breadcrumbs">
                                        <Breadcrumbs
                                            breadcrumbs={breadcrumbs}
                                        />
                                    </div>
                                )}
                            </>
                        }
                    >
                        {children}
                        <AccountingContextPanel />
                    </MobileExperience>
                </AppContent>
            </AppShell>
        </ShellContext.Provider>
    );
}

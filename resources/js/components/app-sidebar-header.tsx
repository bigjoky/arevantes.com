import { usePage } from '@inertiajs/react';
import { ActionCenter } from '@/components/action-center';
import { AreviaChat } from '@/components/arevia-chat';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { GlobalSearch } from '@/components/global-search';
import { LanguageSelector } from '@/components/language-selector';
import { QuickCreate } from '@/components/quick-create';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

export function AppSidebarHeader({
    breadcrumbs = [],
}: {
    breadcrumbs?: BreadcrumbItemType[];
}) {
    const { abilities } = usePage().props.auth;
    const canSearch =
        abilities.manageCustomers ||
        abilities.viewInstallations ||
        abilities.manageInstallations ||
        abilities.manageOperations ||
        abilities.manageCatalog ||
        abilities.viewPurchases;
    const hasActionCenter =
        abilities.viewInstallations ||
        abilities.manageOperations ||
        abilities.managePurchases ||
        abilities.approvePurchaseOrders ||
        abilities.viewFinance;

    return (
        <header className="flex h-16 shrink-0 items-center gap-2 border-b border-sidebar-border/50 px-3 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4">
            <div className="flex min-w-0 items-center gap-2">
                <SidebarTrigger className="-ml-1" />
                <div className="hidden min-w-0 md:block">
                    <Breadcrumbs breadcrumbs={breadcrumbs} />
                </div>
            </div>
            <div className="ml-auto flex min-w-0 items-center gap-1">
                <LanguageSelector />
                {canSearch && <GlobalSearch />}
                <QuickCreate />
                {hasActionCenter && <ActionCenter />}
                {abilities.useAi && <AreviaChat />}
            </div>
        </header>
    );
}

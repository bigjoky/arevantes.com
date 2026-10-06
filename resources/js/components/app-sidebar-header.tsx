import { router, usePage } from '@inertiajs/react';
import { CalendarDays } from 'lucide-react';
import { ActionCenter } from '@/components/action-center';
import { AreviaChat } from '@/components/arevia-chat';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { FavoritesBar } from '@/components/favorites-bar';
import { GlobalSearch } from '@/components/global-search';
import { LanguageSelector } from '@/components/language-selector';
import { NavUser } from '@/components/nav-user';
import { QuickCreate } from '@/components/quick-create';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { useLocale } from '@/hooks/use-locale';
import { translate } from '@/i18n';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

export function AppSidebarHeader({
    breadcrumbs = [],
}: {
    breadcrumbs?: BreadcrumbItemType[];
}) {
    const page = usePage();
    const { abilities } = page.props.auth;
    const { locale } = useLocale();
    const isHome =
        new URL(page.url, 'http://localhost').pathname === '/dashboard' &&
        abilities.viewFinance;
    const year =
        typeof page.props.year === 'number'
            ? page.props.year
            : new Date().getFullYear();
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
        <>
            <header
                className={`${isHome ? 'financial-home-header' : ''} flex h-16 shrink-0 items-center gap-2 border-b border-sidebar-border/50 px-3 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4`}
            >
                <div className="flex min-w-0 items-center gap-2">
                    <SidebarTrigger className="-ml-1" />
                    <div
                        className={
                            isHome ? 'hidden' : 'hidden min-w-0 md:block'
                        }
                    >
                        <Breadcrumbs breadcrumbs={breadcrumbs} />
                    </div>
                </div>
                {isHome && canSearch && (
                    <div className="hidden flex-1 px-2 md:block">
                        <GlobalSearch expanded />
                    </div>
                )}
                <div className="ml-auto flex min-w-0 items-center gap-1">
                    {isHome && (
                        <label className="mr-2 flex h-9 items-center gap-2 rounded-md border px-2 text-xs">
                            <CalendarDays className="size-4" />
                            <span className="hidden lg:inline">
                                {translate('Ejercicio', locale)}
                            </span>
                            <select
                                aria-label={translate('Ejercicio', locale)}
                                className="bg-transparent"
                                value={year}
                                onChange={(event) =>
                                    router.get('/dashboard', {
                                        year: Number(event.target.value),
                                    })
                                }
                            >
                                {Array.from(
                                    { length: 7 },
                                    (_, index) => year - 3 + index,
                                )
                                    .filter(
                                        (value) =>
                                            value >= 2000 && value <= 2100,
                                    )
                                    .map((value) => (
                                        <option key={value} value={value}>
                                            {value}
                                        </option>
                                    ))}
                            </select>
                        </label>
                    )}
                    <LanguageSelector />
                    {canSearch && !isHome && <GlobalSearch />}
                    <QuickCreate />
                    {hasActionCenter && <ActionCenter />}
                    {abilities.useAi && <AreviaChat />}
                    <NavUser placement="header" />
                </div>
            </header>
            {isHome && canSearch && (
                <div className="border-b px-3 py-2 md:hidden">
                    <GlobalSearch expanded />
                </div>
            )}
            {!isHome && <FavoritesBar />}
        </>
    );
}

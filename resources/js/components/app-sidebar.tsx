import { Link, usePage } from '@inertiajs/react';
import {
    BadgeEuro,
    ChevronDown,
    Building,
    BookOpenText,
    Boxes,
    BriefcaseBusiness,
    Building2,
    CalendarClock,
    CalendarDays,
    ChartNoAxesCombined,
    ChartSpline,
    Headphones,
    LayoutGrid,
    Scale,
    Settings2,
    ShieldCheck,
    ShoppingCart,
    UserCog,
    UsersRound,
    WalletCards,
    Wrench,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import AppLogoIcon from '@/components/app-logo-icon';
import { NavMain } from '@/components/nav-main';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { accountingNavigation } from '@/lib/accounting-navigation';
import { dashboard } from '@/routes';
import type { NavItem, WorkspaceNavItem } from '@/types';

const icons: Record<string, LucideIcon> = {
    'badge-euro': BadgeEuro,
    'book-open-text': BookOpenText,
    boxes: Boxes,
    'briefcase-business': BriefcaseBusiness,
    'building-2': Building2,
    'calendar-clock': CalendarClock,
    'calendar-days': CalendarDays,
    'chart-no-axes-combined': ChartNoAxesCombined,
    'chart-spline': ChartSpline,
    headphones: Headphones,
    'layout-grid': LayoutGrid,
    scale: Scale,
    'settings-2': Settings2,
    'shield-check': ShieldCheck,
    'shopping-cart': ShoppingCart,
    'user-cog': UserCog,
    'users-round': UsersRound,
    'wallet-cards': WalletCards,
    wrench: Wrench,
};

const toNavItem = (item: WorkspaceNavItem): NavItem => ({
    title: item.title,
    code: item.code,
    href: item.href,
    icon: item.icon ? icons[item.icon] : undefined,
    children: item.children?.map(toNavItem),
});

export function AppSidebar() {
    const page = usePage();
    const navigation = page.props.auth.navigation.map(toNavItem);

    const pathname = new URL(page.url, 'http://localhost').pathname;
    const financial =
        /^\/(accounting|finance|tax|corporate-tax|company-fiscal|expenses|banking)(\/|$)/.test(
            pathname,
        );
    const year =
        typeof page.props.year === 'number' ? page.props.year : undefined;
    const contextual = accountingNavigation(navigation, year);
    const company = page.props.workspaceContext?.companyName;
    const companySettings = navigation
        .flatMap((item) => item.children ?? [item])
        .find((item) => item.href === '/company-fiscal');

    return (
        <Sidebar
            collapsible="icon"
            variant="sidebar"
            className="arevantes-sidebar"
        >
            <div aria-hidden="true" className="sidebar-monogram">
                <AppLogoIcon />
            </div>
            <SidebarHeader className="relative px-6 pt-6 pb-9 group-data-[collapsible=icon]:px-2 group-data-[collapsible=icon]:pb-4">
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton
                            asChild
                            className="sidebar-brand h-auto rounded-none p-0 hover:bg-transparent"
                        >
                            <Link
                                href={dashboard()}
                                prefetch
                                aria-label="AREVANTES · Inicio"
                            >
                                <span className="sidebar-wordmark text-[27px] font-semibold tracking-[0.1em]">
                                    AREVANTES
                                </span>
                                <AppLogoIcon className="hidden size-7 fill-current group-data-[collapsible=icon]:block" />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>
            <SidebarContent className="relative gap-0 pb-4">
                <nav aria-label="Navegación principal">
                    {financial ? (
                        <>
                            <NavMain items={contextual.home} />
                            {contextual.groups.map((group) => (
                                <div
                                    key={group.label}
                                    className="sidebar-section"
                                >
                                    <NavMain
                                        label={group.label}
                                        items={group.items}
                                    />
                                </div>
                            ))}
                            {contextual.remaining.length > 0 && (
                                <Collapsible className="sidebar-section">
                                    <div className="px-2">
                                        <CollapsibleTrigger asChild>
                                            <SidebarMenuButton className="text-sidebar-foreground/80">
                                                <Building />
                                                <span>Otras áreas</span>
                                                <ChevronDown className="ml-auto" />
                                            </SidebarMenuButton>
                                        </CollapsibleTrigger>
                                    </div>
                                    <CollapsibleContent>
                                        <NavMain items={contextual.remaining} />
                                    </CollapsibleContent>
                                </Collapsible>
                            )}
                        </>
                    ) : (
                        <NavMain items={navigation} />
                    )}
                </nav>
            </SidebarContent>
            <SidebarFooter className="relative p-3">
                <SidebarMenu>
                    <SidebarMenuItem>
                        {companySettings ? (
                            <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                    <SidebarMenuButton
                                        aria-label="Empresa y ejercicios fiscales"
                                        className="sidebar-company h-auto min-h-16 border border-white/20 bg-white/[0.025] px-3 py-2"
                                    >
                                        <Building2 className="hidden group-data-[collapsible=icon]:block" />
                                        <span className="grid gap-1 group-data-[collapsible=icon]:hidden">
                                            <span className="truncate font-semibold">
                                                {company || 'Empresa'}
                                            </span>
                                            <span className="text-xs font-normal">
                                                Ejercicio{' '}
                                                {year ??
                                                    page.props.workspaceContext
                                                        ?.currentYear}
                                            </span>
                                        </span>
                                        <ChevronDown className="ml-auto size-3 group-data-[collapsible=icon]:hidden" />
                                    </SidebarMenuButton>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent
                                    side="top"
                                    align="start"
                                    className="min-w-56"
                                >
                                    <DropdownMenuItem asChild>
                                        <Link href={companySettings.href}>
                                            <Settings2 />
                                            Empresa y ejercicios fiscales
                                        </Link>
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        ) : (
                            <div className="rounded-md border border-white/20 p-3 text-sm group-data-[collapsible=icon]:hidden">
                                <p className="truncate font-semibold">
                                    {company || 'Empresa'}
                                </p>
                            </div>
                        )}
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarFooter>
        </Sidebar>
    );
}

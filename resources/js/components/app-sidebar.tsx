import { Link, router, usePage } from '@inertiajs/react';
import {
    BadgeEuro,
    ChevronDown,
    BookOpenText,
    Boxes,
    BriefcaseBusiness,
    Building2,
    CalendarClock,
    CalendarDays,
    ChartNoAxesCombined,
    ChartSpline,
    Headphones,
    Home,
    FileText,
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
import BrandLogo from '@/components/brand-logo';
import { NavMain } from '@/components/nav-main';
import { useShellContext } from '@/components/shell-context';
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
import { dashboard } from '@/routes';
import type { NavItem, WorkspaceNavItem } from '@/types';

export const workspaceNavigationIcons: Record<string, LucideIcon> = {
    home: Home,
    'file-text': FileText,
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
    ...item,
    title: item.title,
    code: item.code,
    href: item.href,
    icon: item.icon ? workspaceNavigationIcons[item.icon] : undefined,
    children: item.children?.map(toNavItem),
});

export function AppSidebar() {
    const page = usePage();
    const navigation = page.props.auth.navigation.map(toNavItem);

    return (
        <Sidebar
            collapsible="icon"
            variant="sidebar"
            className="arevantes-sidebar"
        >
            <SidebarHeader className="relative px-6 pt-4 pb-4 group-data-[collapsible=icon]:px-2 group-data-[collapsible=icon]:pb-4">
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
                                <span className="sidebar-wordmark text-[27px] font-medium tracking-[0.1em]">
                                    <BrandLogo
                                        surface="dark"
                                        className="h-6 max-w-32"
                                    />
                                </span>
                                <AppLogoIcon
                                    surface="dark"
                                    className="hidden size-7 fill-current group-data-[collapsible=icon]:block"
                                />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>
            <SidebarContent className="relative gap-0 pb-4">
                <nav aria-label="Navegación principal">
                    <NavMain items={navigation} />
                </nav>
            </SidebarContent>
            <SidebarCompany />
        </Sidebar>
    );
}

export function SidebarCompany() {
    const page = usePage();
    const { year } = useShellContext();
    const company = page.props.workspaceContext?.companyName;
    const companySettings = page.props.auth.navigation
        .flatMap((item) => item.children ?? [item])
        .flatMap(
            (item) => item.links ?? [{ title: item.title, href: item.href }],
        )
        .find((item) => item.href === '/company-fiscal');

    return (
        <SidebarFooter className="relative p-3">
            <SidebarMenu>
                <SidebarMenuItem>
                    {
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <SidebarMenuButton
                                    aria-label="Empresa y ejercicios fiscales"
                                    className="sidebar-company h-auto min-h-11 border border-white/15 bg-transparent px-3 py-2"
                                >
                                    <Building2 className="hidden group-data-[collapsible=icon]:block" />
                                    <span className="grid gap-0.5 group-data-[collapsible=icon]:hidden">
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
                                {companySettings && (
                                    <DropdownMenuItem asChild>
                                        <Link href={companySettings.href}>
                                            <Settings2 />
                                            Empresa y ejercicios fiscales
                                        </Link>
                                    </DropdownMenuItem>
                                )}
                                <div className="border-t px-2 py-2">
                                    <label className="flex items-center justify-between gap-2 text-xs">
                                        Ejercicio
                                        <select
                                            aria-label="Ejercicio"
                                            className="rounded border bg-background px-2 py-1"
                                            value={
                                                year ??
                                                page.props.workspaceContext
                                                    ?.currentYear
                                            }
                                            onChange={(event) => {
                                                const url = new URL(
                                                    page.url,
                                                    window.location.origin,
                                                );
                                                url.searchParams.set(
                                                    'year',
                                                    event.target.value,
                                                );
                                                router.get(
                                                    `${url.pathname}${url.search}${url.hash}`,
                                                );
                                            }}
                                        >
                                            {Array.from(
                                                { length: 7 },
                                                (_, index) =>
                                                    (year ??
                                                        page.props
                                                            .workspaceContext
                                                            ?.currentYear ??
                                                        new Date().getFullYear()) -
                                                    3 +
                                                    index,
                                            )
                                                .filter(
                                                    (value) =>
                                                        value >= 2000 &&
                                                        value <= 2100,
                                                )
                                                .map((value) => (
                                                    <option
                                                        key={value}
                                                        value={value}
                                                    >
                                                        {value}
                                                    </option>
                                                ))}
                                        </select>
                                    </label>
                                </div>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    }
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarFooter>
    );
}

import { Link, router, usePage } from '@inertiajs/react';
import {
    BadgeEuro,
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
    Star,
    UserCog,
    UsersRound,
    WalletCards,
    Wrench,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
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
    const shortcuts = page.props.auth.shortcuts;
    const path = page.url.split('?')[0].split('#')[0];
    const current = page.props.auth.navigation
        .flatMap((item) => item.children ?? [item])
        .find((item) => item.href === path);
    const favorite = shortcuts.favorites.some((item) => item.href === path);

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>
            <SidebarContent>
                {current && (
                    <SidebarMenu className="px-2">
                        <SidebarMenuItem>
                            <SidebarMenuButton
                                onClick={() =>
                                    router.patch(
                                        '/workspace/favorite',
                                        {
                                            href: current.href,
                                            enabled: !favorite,
                                        },
                                        { preserveScroll: true },
                                    )
                                }
                                aria-pressed={favorite}
                                tooltip={
                                    favorite
                                        ? 'Remove from favorites'
                                        : 'Add to favorites'
                                }
                            >
                                <Star
                                    className={favorite ? 'fill-current' : ''}
                                />
                                <span>
                                    {favorite
                                        ? 'Remove from favorites'
                                        : 'Add to favorites'}
                                </span>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    </SidebarMenu>
                )}
                {shortcuts.favorites.length > 0 && (
                    <NavMain
                        label="Favorites"
                        items={shortcuts.favorites.map(toNavItem)}
                    />
                )}
                {shortcuts.recent.length > 0 && (
                    <NavMain
                        label="Recently visited"
                        items={shortcuts.recent.map(toNavItem)}
                    />
                )}
                <NavMain items={navigation} />
            </SidebarContent>
            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}

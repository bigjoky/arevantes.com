import { Link, usePage } from '@inertiajs/react';
import {
    BriefcaseBusiness,
    Building2,
    CalendarDays,
    ChartNoAxesCombined,
    ChartSpline,
    LayoutGrid,
    Settings2,
    ShoppingCart,
    UsersRound,
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
    'briefcase-business': BriefcaseBusiness,
    'building-2': Building2,
    'calendar-days': CalendarDays,
    'chart-no-axes-combined': ChartNoAxesCombined,
    'chart-spline': ChartSpline,
    'layout-grid': LayoutGrid,
    'settings-2': Settings2,
    'shopping-cart': ShoppingCart,
    'users-round': UsersRound,
};

const toNavItem = (item: WorkspaceNavItem): NavItem => ({
    title: item.title,
    href: item.href,
    icon: item.icon ? icons[item.icon] : undefined,
    children: item.children?.map(toNavItem),
});

export function AppSidebar() {
    const navigation = usePage().props.auth.navigation.map(toNavItem);

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
                <NavMain items={navigation} />
            </SidebarContent>
            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}

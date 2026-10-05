import { Link, usePage } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { toUrl } from '@/lib/utils';
import type { NavItem } from '@/types';

export function NavMain({
    items = [],
    label,
}: {
    items: NavItem[];
    label?: string;
}) {
    const { isCurrentOrParentUrl } = useCurrentUrl();
    const page = usePage();
    const { setOpenMobile } = useSidebar();
    const authorizedPaths = page.props.auth.navigation
        .flatMap((item) => item.children ?? [item])
        .map((item) => new URL(item.href, 'http://localhost').pathname);
    const current = new URL(page.url, 'http://localhost');
    const isActive = (item: NavItem) => {
        const target = new URL(toUrl(item.href), 'http://localhost');

        if (target.searchParams.has('view')) {
            return (
                current.pathname === target.pathname &&
                (current.searchParams.get('view') ??
                    (current.searchParams.has('account')
                        ? 'ledger'
                        : 'journal')) === target.searchParams.get('view')
            );
        }

        const moreSpecific = authorizedPaths.some(
            (path) =>
                path.length > target.pathname.length &&
                (current.pathname === path ||
                    current.pathname.startsWith(`${path}/`)),
        );
        return !moreSpecific && isCurrentOrParentUrl(item.href);
    };

    return (
        <SidebarGroup className="px-2 py-0">
            {label && <SidebarGroupLabel>{label}</SidebarGroupLabel>}
            <SidebarMenu>
                {items.map((item) => {
                    const active =
                        isActive(item) ||
                        item.children?.some((child) => isActive(child));
                    const tooltip = item.code
                        ? `${item.code} · ${item.title}`
                        : item.title;

                    if (!item.children?.length) {
                        return (
                            <SidebarMenuItem key={item.title}>
                                <SidebarMenuButton
                                    asChild
                                    isActive={isActive(item)}
                                    tooltip={{ children: tooltip }}
                                >
                                    <Link
                                        href={item.href}
                                        onClick={() => setOpenMobile(false)}
                                        prefetch
                                        aria-current={
                                            isActive(item) ? 'page' : undefined
                                        }
                                    >
                                        {item.icon && <item.icon />}
                                        <span>{item.title}</span>
                                    </Link>
                                </SidebarMenuButton>
                            </SidebarMenuItem>
                        );
                    }

                    return (
                        <Collapsible
                            key={item.title}
                            asChild
                            defaultOpen={active}
                            className="group/collapsible"
                        >
                            <SidebarMenuItem>
                                <CollapsibleTrigger asChild>
                                    <SidebarMenuButton
                                        isActive={active}
                                        tooltip={{ children: tooltip }}
                                    >
                                        {item.icon && <item.icon />}
                                        <span>{item.title}</span>
                                        <ChevronRight className="ml-auto transition-transform group-data-[state=open]/collapsible:rotate-90" />
                                    </SidebarMenuButton>
                                </CollapsibleTrigger>
                                <CollapsibleContent>
                                    <SidebarMenuSub>
                                        {item.children.map((child) => (
                                            <SidebarMenuSubItem
                                                key={child.title}
                                            >
                                                <SidebarMenuSubButton
                                                    asChild
                                                    isActive={
                                                        isActive(child) &&
                                                        !item.children?.some(
                                                            (other) =>
                                                                String(
                                                                    other.href,
                                                                ).length >
                                                                    String(
                                                                        child.href,
                                                                    ).length &&
                                                                isActive(other),
                                                        )
                                                    }
                                                >
                                                    <Link
                                                        href={child.href}
                                                        onClick={() =>
                                                            setOpenMobile(false)
                                                        }
                                                        prefetch
                                                    >
                                                        <span>
                                                            {child.title}
                                                        </span>
                                                    </Link>
                                                </SidebarMenuSubButton>
                                            </SidebarMenuSubItem>
                                        ))}
                                    </SidebarMenuSub>
                                </CollapsibleContent>
                            </SidebarMenuItem>
                        </Collapsible>
                    );
                })}
            </SidebarMenu>
        </SidebarGroup>
    );
}

import { Link, usePage } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import { useCallback, useState, useSyncExternalStore } from 'react';
import { useShellContext } from '@/components/shell-context';
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
    SidebarMenuAction,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { activeNavigation, contextualHref } from '@/lib/workspace-navigation';
import type { NavItem } from '@/types';

function subscribe(callback: () => void) {
    window.addEventListener('storage', callback);
    window.addEventListener('arevantes:sidebar-preferences', callback);

    return () => {
        window.removeEventListener('storage', callback);
        window.removeEventListener('arevantes:sidebar-preferences', callback);
    };
}

export function NavMain({
    items = [],
    label,
}: {
    items: NavItem[];
    label?: string;
}) {
    const page = usePage();
    const { year } = useShellContext();
    const { state, setOpen, setOpenMobile, isMobile } = useSidebar();
    const key = `arevantes:sidebar-modules:${isMobile ? "mobile:" : ""}v1:${page.props.auth.user.id}`;
    const read = useCallback(() => {
        try {
            return window.localStorage.getItem(key) ?? '{}';
        } catch {
            return '{}';
        }
    }, [key]);
    const stored = useSyncExternalStore(subscribe, read, () => '{}');
    let preferences: Record<string, boolean> = {};

    try {
        const parsed: unknown = JSON.parse(stored);

        if (parsed && typeof parsed === 'object' && !Array.isArray(parsed)) {
            preferences = parsed as Record<string, boolean>;
        }
    } catch {
        /* A damaged preference must never break navigation. */
    }

    const [dismissed, setDismissed] = useState<{
        id: string;
        url: string;
    } | null>(null);
    const selected = activeNavigation(page.props.auth.navigation, page.url);
    const remember = (id: string, open: boolean) => {
        try {
            window.localStorage.setItem(
                key,
                JSON.stringify({ ...preferences, [id]: open }),
            );
            window.dispatchEvent(new Event('arevantes:sidebar-preferences'));
        } catch {
            /* Storage may be unavailable in private or restricted browsing. */
        }

        setDismissed(open ? null : { id, url: page.url });

        if (open && !isMobile && state === 'collapsed') {
            setOpen(true);
        }
    };

    return (
        <SidebarGroup className="px-3 py-0">
            {label && <SidebarGroupLabel>{label}</SidebarGroupLabel>}
            <SidebarMenu>
                {items.map((item) => {
                    const id = item.id ?? item.code ?? item.title;
                    const active =
                        selected === item.id ||
                        item.children?.some(
                            (child) => child.id === selected,
                        ) === true;

                    if (!item.children?.length) {
                        return (
                            <SidebarMenuItem key={id}>
                                <SidebarMenuButton
                                    asChild
                                    isActive={active}
                                    tooltip={item.title}
                                >
                                    <Link
                                        href={item.href}
                                        onClick={() => setOpenMobile(false)}
                                        aria-current={
                                            active ? 'page' : undefined
                                        }
                                    >
                                        {item.icon && <item.icon />}
                                        <span>{item.title}</span>
                                    </Link>
                                </SidebarMenuButton>
                            </SidebarMenuItem>
                        );
                    }

                    const open =
                        (item.navigable
                            ? item.children.some(
                                  (child) => child.id === selected,
                              )
                            : active) &&
                        !(dismissed?.id === id && dismissed.url === page.url)
                            ? true
                            : preferences[id] === true;

                    return (
                        <Collapsible
                            key={id}
                            asChild
                            open={open}
                            onOpenChange={(value) => remember(id, value)}
                            className="group/collapsible"
                        >
                            <SidebarMenuItem>
                                {item.navigable ? (
                                    <>
                                        <SidebarMenuButton
                                            asChild
                                            isActive={active}
                                            tooltip={item.title}
                                            className="min-h-11 pr-12 md:min-h-9 md:pr-11"
                                        >
                                            <Link
                                                href={item.href}
                                                onClick={() =>
                                                    setOpenMobile(false)
                                                }
                                                aria-current={
                                                    selected === item.id
                                                        ? 'page'
                                                        : undefined
                                                }
                                            >
                                                {item.icon && <item.icon />}
                                                <span>{item.title}</span>
                                            </Link>
                                        </SidebarMenuButton>
                                        <CollapsibleTrigger asChild>
                                            <SidebarMenuAction
                                                type="button"
                                                aria-label={`${open ? 'Contraer' : 'Expandir'} ${item.title}`}
                                                aria-controls={`sidebar-group-${id}`}
                                                className="top-0! right-0! size-11! after:hidden md:size-9!"
                                            >
                                                <ChevronRight className="size-3! transition-transform duration-150 group-data-[state=open]/collapsible:rotate-90 motion-reduce:transition-none" />
                                            </SidebarMenuAction>
                                        </CollapsibleTrigger>
                                    </>
                                ) : (
                                    <CollapsibleTrigger asChild>
                                        <SidebarMenuButton
                                            isActive={active}
                                            tooltip={item.title}
                                        >
                                            {item.icon && <item.icon />}
                                            <span>{item.title}</span>
                                            <ChevronRight className="ml-auto size-3 transition-transform duration-150 group-data-[state=open]/collapsible:rotate-90 motion-reduce:transition-none" />
                                        </SidebarMenuButton>
                                    </CollapsibleTrigger>
                                )}
                                <CollapsibleContent id={`sidebar-group-${id}`}>
                                    <SidebarMenuSub>
                                        {item.children.map((child) => (
                                            <SidebarMenuSubItem
                                                key={child.id ?? child.title}
                                            >
                                                <SidebarMenuSubButton
                                                    asChild
                                                    isActive={
                                                        selected === child.id
                                                    }
                                                >
                                                    <Link
                                                        href={contextualHref(
                                                            typeof child.href ===
                                                                'string'
                                                                ? child.href
                                                                : child.href
                                                                      .url,
                                                            year,
                                                        )}
                                                        onClick={() =>
                                                            setOpenMobile(false)
                                                        }
                                                        aria-current={
                                                            selected ===
                                                            child.id
                                                                ? 'page'
                                                                : undefined
                                                        }
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

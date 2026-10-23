import type { WorkspaceNavItem } from '@/types';

export type MobileView =
    | 'menu'
    | 'search'
    | 'notifications'
    | 'profile'
    | 'create';
const views: MobileView[] = [
    'menu',
    'search',
    'notifications',
    'profile',
    'create',
];
export function mobileView(url: string): MobileView | null {
    const value = new URL(url, 'https://local.invalid').searchParams.get(
        'mobile',
    );

    return views.includes(value as MobileView) ? (value as MobileView) : null;
}
export function mobileUrl(url: string, view: MobileView | null): string {
    const parsed = new URL(url, 'https://local.invalid');

    if (view) {
        parsed.searchParams.set('mobile', view);
    } else {
        parsed.searchParams.delete('mobile');
    }

    return `${parsed.pathname}${parsed.search}${parsed.hash}`;
}
export function mobileDestinations(items: WorkspaceNavItem[]) {
    const flatten = items.flatMap((item) => [item, ...(item.children ?? [])]);
    const find = (href: string) =>
        flatten.find((item) => item.href.split('?')[0] === href);

    return [
        { title: 'Inicio', href: find('/dashboard')?.href, key: 'home' },
        {
            title: 'Agenda',
            href: find('/operations/agenda')?.href,
            key: 'agenda',
        },
        {
            title: 'Comercial',
            href: find('/commercial')?.href,
            key: 'commercial',
        },
        {
            title: 'Operaciones',
            href: find('/operations')?.href,
            key: 'operations',
        },
    ].filter((item): item is typeof item & { href: string } =>
        Boolean(item.href),
    );
}
export function mobileTabActive(url: string, href: string) {
    const path = new URL(url, 'https://local.invalid').pathname;
    const target = href.split('?')[0];

    if (target === '/dashboard') {
        return path === target;
    }

    if (target === '/operations') {
        return path.startsWith('/operations') && path !== '/operations/agenda';
    }

    if (target === '/commercial') {
        return ['/commercial', '/opportunities', '/people', '/quotes'].some(
            (prefix) => path === prefix || path.startsWith(`${prefix}/`),
        );
    }

    return path === target;
}

export function mobilePlatform(
    userAgent: string,
    platform: string,
    touchPoints: number,
    coarsePointer: boolean,
) {
    if (touchPoints < 1 && !coarsePointer) {
        return 'universal';
    }

    if (
        /iPhone|iPad|iPod/.test(userAgent) ||
        (platform === 'MacIntel' && touchPoints > 1)
    ) {
        return 'ios';
    }

    if (/Android/.test(userAgent)) {
        return 'android';
    }

    return 'universal';
}

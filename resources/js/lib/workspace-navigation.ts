import type { WorkspaceNavItem } from '@/types';

/** Match child URLs and required query keys; ignore unrelated filters and years. */
export function navigationScore(href: string, currentUrl: string): number {
    const target = new URL(href, 'http://localhost');
    const current = new URL(currentUrl, 'http://localhost');

    if (
        current.pathname !== target.pathname &&
        !current.pathname.startsWith(`${target.pathname}/`)
    ) {
        return -1;
    }

    for (const [key, value] of target.searchParams) {
        if (current.searchParams.get(key) !== value) {
            return -1;
        }
    }

    if (
        target.pathname === '/accounting' &&
        !target.searchParams.has('view') &&
        current.searchParams.has('view')
    ) {
        return -1;
    }

    return target.pathname.length + target.searchParams.size * 100;
}

export function activeNavigation(
    items: WorkspaceNavItem[],
    url: string,
): string | undefined {
    let best = -1;
    let selected: string | undefined;

    for (const item of items) {
        for (const child of item.children
            ? [...(item.navigable ? [item] : []), ...item.children]
            : [item]) {
            for (const href of [
                child.href,
                ...(item.children && child.id
                    ? [`/workspace/modules/${child.id.replace('.', '/')}`]
                    : []),
                ...(child.links ?? []).map((link) => link.href),
            ]) {
                const score = navigationScore(href, url);

                if (score > best) {
                    best = score;
                    selected = child.id;
                }
            }
        }
    }

    if (
        new URL(url, 'http://localhost').pathname.startsWith(
            '/accounting/entries/',
        ) &&
        items.some((module) =>
            module.children?.some((child) => child.id === 'accounting.entries'),
        )
    ) {
        return 'accounting.entries';
    }

    // Fiscal declaration details belong to the Modelos subsection.
    if (
        new URL(url, 'http://localhost').pathname.startsWith(
            '/fiscal/declaraciones/',
        ) &&
        items.some((module) =>
            module.children?.some((child) => child.id === 'tax.models'),
        )
    ) {
        return 'tax.models';
    }

    // Legacy accounting deep links retain their destination without requiring new query keys.
    const current = new URL(url, 'http://localhost');

    if (best < 0 && current.pathname === '/accounting') {
        const view =
            current.searchParams.get('view') ??
            (current.searchParams.has('account') ? 'ledger' : 'journal');
        selected =
            view === 'journal'
                ? 'accounting.entries'
                : view === 'mappings'
                  ? 'accounting'
                  : 'accounting.reports';

        if (
            !items.some(
                (module) =>
                    module.id === selected ||
                    module.children?.some((child) => child.id === selected),
            )
        ) {
            return undefined;
        }
    }

    return selected;
}

export function contextualHref(href: string, year?: number): string {
    const target = new URL(href, 'http://localhost');

    if (
        (year &&
            [
                '/accounting',
                '/accounting/accounts',
                '/accounting/templates',
                '/tax/models/303',
                '/finance',
                '/fiscal',
                '/fiscal/impuestos',
                '/fiscal/modelos',
                '/fiscal/periodos',
            ].includes(target.pathname)) ||
        (year && target.pathname.startsWith('/workspace/modules/'))
    ) {
        target.searchParams.set('year', String(year));
    }

    return `${target.pathname}${target.search}${target.hash}`;
}

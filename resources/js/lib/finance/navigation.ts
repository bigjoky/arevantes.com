const allowed =
    /^\/(?:finance|banking|sales(?:\/\d+)?|expenses(?:\/\d+)?|billing(?:\/(?:customers|suppliers|payments)(?:\/(?:purchase\/|expense\/)?\d+)?)?|people(?:\/\d+)?|suppliers(?:\/\d+)?|accounting)\/?$/;
const keys = new Set([
    'area',
    'tab',
    'year',
    'month',
    'months',
    'account',
    'account_id',
    'bank_status',
    'status',
    'kind',
    'search',
    'from',
    'to',
    'page',
    'per_page',
    'horizon',
    'scenario',
    'source',
    'party',
    'customer',
    'supplier',
    'state',
    'sort',
    'direction',
    'view',
]);
export function financeReturn(value: unknown): string | null {
    if (
        typeof value !== 'string' ||
        !value.startsWith('/') ||
        value.startsWith('//') ||
        [...value].some((char) => char.charCodeAt(0) <= 32) ||
        value.includes('\\')
    ) {
        return null;
    }

    const url = new URL(value, 'https://arevantes.invalid');

    if (
        url.origin !== 'https://arevantes.invalid' ||
        !allowed.test(url.pathname)
    ) {
        return null;
    }

    const params = new URLSearchParams();
    url.searchParams.forEach((v, k) => {
        if (keys.has(k)) {
            params.set(k, v);
        }
    });

    return url.pathname + (params.size ? `?${params}` : '');
}
export function financeEntity(href: string, origin: string): string {
    const target = financeReturn(href);
    const parent = financeReturn(origin);

    if (!target || !parent) {
        return href;
    }

    const url = new URL(target, 'https://arevantes.invalid');
    url.searchParams.set('return_to', parent);

    return url.pathname + '?' + url.searchParams;
}

export function financeView(
    origin: string,
    changes: Record<string, string>,
): string {
    const safe = financeReturn(origin) ?? '/finance';
    const url = new URL(safe, 'https://arevantes.invalid');
    url.searchParams.delete('page');

    for (const [key, value] of Object.entries(changes)) {
        if (keys.has(key)) {
            url.searchParams.set(key, value);
        }
    }

    return url.pathname + (url.searchParams.size ? `?${url.searchParams}` : '');
}

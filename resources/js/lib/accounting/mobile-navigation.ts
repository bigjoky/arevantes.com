const keys = new Set([
    'area',
    'view',
    'year',
    'from',
    'to',
    'search',
    'entry_status',
    'kind',
    'reference',
    'concept',
    'sort',
    'account',
    'group',
    'active',
    'accounts_page',
    'journal_page',
    'account_filter',
    'financial_category',
    'tab',
    'page',
    'per_page',
    'mode',
]);

/** Only known local, read-only accounting listings can be return destinations. */
export function accountingReturn(value: unknown): string | null {
    if (
        typeof value !== 'string' ||
        !value.startsWith('/') ||
        value.startsWith('//') ||
        value.includes('\\') ||
        [...value].some((c) => c.charCodeAt(0) <= 32)
    ) {
        return null;
    }

    const url = new URL(value, 'https://arevantes.invalid');

    if (
        url.origin !== 'https://arevantes.invalid' ||
        !['/accounting', '/accounting/accounts'].includes(url.pathname)
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

export function accountingDetail(href: string, origin: string): string {
    const safe = accountingReturn(origin);

    if (
        !safe ||
        !/^\/accounting\/(?:entries|accounts)\/\d+(?:\?year=\d{4})?$/.test(href)
    ) {
        return href;
    }

    const url = new URL(href, 'https://arevantes.invalid');
    url.searchParams.set('return_to', safe);

    return url.pathname + '?' + url.searchParams;
}

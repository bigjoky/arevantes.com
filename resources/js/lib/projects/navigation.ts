const filters = new Set([
    'search',
    'status',
    'phase',
    'service_type',
    'customer',
    'technician',
    'project',
    'created_from',
    'created_to',
    'date_from',
    'date_to',
    'sort',
    'direction',
    'per_page',
    'page',
    'period',
    'year',
    'service_state',
    'installation',
    'view',
    'tab',
    'section_page',
]);

/** Local, recognized contexts only. Never replay mobile overlays or commands. */
export function projectReturnUrl(
    value: string | null | undefined,
    fallback = '/execution/projects',
): string {
    if (
        !value?.startsWith('/') ||
        value.startsWith('//') ||
        value.includes('\\')
    ) {
return fallback;
}

    const url = new URL(value, 'https://projects.invalid');

    if (
        !/^\/(execution(?:\/projects(?:\/\d+)?)?|installations(?:\/\d+)?|after-sales)$/.test(
            url.pathname,
        )
    ) {
return fallback;
}

    const query = new URLSearchParams();
    url.searchParams.forEach((v, k) => {
        if (filters.has(k)) {
query.set(k, v);
}
    });

    return url.pathname + (query.size ? `?${query}` : '');
}

export function projectEntityUrl(path: string, context: string): string {
    const url = new URL(path, 'https://projects.invalid');
    url.searchParams.set('return_to', projectReturnUrl(context));

    return url.pathname + '?' + url.searchParams;
}

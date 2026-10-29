const keys = new Set([
    'view',
    'tab',
    'search',
    'warehouse',
    'installation',
    'status',
    'supplier',
    'type',
    'page',
    'period',
    'date_from',
    'date_to',
    'order',
    'work_order',
]);

export function supplyReturnUrl(
    value: string | null | undefined,
    fallback = '/supply',
): string {
    if (
        !value?.startsWith('/') ||
        value.startsWith('//') ||
        value.includes('\\')
    ) {
        return fallback;
    }

    const url = new URL(value, 'https://supply.invalid');

    if (
        !/^\/(supply|catalog(?:\/\d+)?|inventory(?:\/(reservations|movements|transport))?|purchases(?:\/\d+)?|work-orders\/\d+|installations\/\d+)$/.test(
            url.pathname,
        )
    ) {
        return fallback;
    }

    const query = new URLSearchParams();
    url.searchParams.forEach((v, k) => {
        if (keys.has(k)) {
            query.set(k, v);
        }
    });

    return url.pathname + (query.size ? '?' + query : '');
}

export function supplyEntityUrl(path: string, context: string): string {
    const url = new URL(path, 'https://supply.invalid');
    url.searchParams.set('return_to', supplyReturnUrl(context));

    return url.pathname + '?' + url.searchParams;
}

export function supplyViewUrl(context: string, view: string): string {
    const origin = new URL(context, 'https://supply.invalid').searchParams.get(
        'return_to',
    );
    const url = new URL(
        supplyReturnUrl(context, '/inventory'),
        'https://supply.invalid',
    );

    if (origin) {
        url.searchParams.set('return_to', supplyReturnUrl(origin));
    }

    url.searchParams.delete('page');
    url.searchParams.set('view', view);

    return '/inventory?' + url.searchParams;
}

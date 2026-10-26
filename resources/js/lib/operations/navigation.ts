const allowed = new Set([
    'q',
    'status',
    'kind',
    'owner',
    'mine',
    'metric',
    'date_from',
    'date_to',
    'per_page',
    'page',
    'view',
    'week',
]);
const origins = new Set([
    '/work-orders',
    '/operations/agenda',
    '/operations/planning',
    '/operations',
]);

/** Only same-app, recognized list contexts; never accept an external return URL. */
export function operationsReturnUrl(value: string | null | undefined): string {
    if (!value || !value.startsWith('/') || value.startsWith('//')) {
        return '/work-orders';
    }

    const url = new URL(value, 'https://operations.invalid');

    if (!origins.has(url.pathname)) {
        return '/work-orders';
    }

    const query = new URLSearchParams();
    url.searchParams.forEach((v, k) => {
        if (allowed.has(k)) {
            query.set(k, v);
        }
    });

    return url.pathname + (query.size ? `?${query}` : '');
}

export function operationsOrderUrl(
    id: number,
    context: string,
    tab?: string,
): string {
    const query = new URLSearchParams({
        return_to: operationsReturnUrl(context),
    });

    if (tab) {
        query.set('tab', tab);
    }

    return `/work-orders/${id}?${query}`;
}

export function operationsFilterUrl(context: string, status?: string): string {
    const url = new URL(
        operationsReturnUrl(context),
        'https://operations.invalid',
    );
    url.searchParams.delete('page');
    url.searchParams.delete('metric');

    if (status) {
        url.searchParams.set('status', status);
    } else {
        url.searchParams.delete('status');
    }

    return (
        '/work-orders' + (url.searchParams.size ? `?${url.searchParams}` : '')
    );
}

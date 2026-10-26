export type CommercialBase = '/opportunities' | '/people' | '/quotes';

/** Preserve list context only; editor/tab commands are not list filters. */
export function commercialListUrl(current: string, base: CommercialBase) {
    const params = new URL(current, 'https://local.invalid').searchParams;

    for (const key of ['mobile', 'tab', 'create', 'opportunity']) {
        params.delete(key);
    }

    return base + (params.size ? `?${params}` : '');
}

export function commercialDetailUrl(
    base: CommercialBase,
    id: number,
    filters: Record<string, string | undefined>,
) {
    const list = commercialListUrl(
        base +
            '?' +
            new URLSearchParams(
                Object.entries(filters).filter(
                    (entry): entry is [string, string] => Boolean(entry[1]),
                ),
            ),
        base,
    );

    return list.replace(base, `${base}/${id}`);
}

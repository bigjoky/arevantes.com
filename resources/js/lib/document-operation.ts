/** Retain request identity across errors/retries; success starts a new user intent. */
export function documentOperation(makeKey = () => crypto.randomUUID()) {
    let key: string | null = null;

    return {
        transform<T extends Record<string, unknown>>(data: T) {
            key ??= makeKey();

            return { ...data, creation_key: key };
        },
        complete() {
            key = null;
        },
    };
}

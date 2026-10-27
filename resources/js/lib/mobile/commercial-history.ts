/** Register before Inertia initializes: window popstate listeners run in
 * registration order even when the later listener requests capture. */
const guards = new Set<(event: PopStateEvent) => void>();
let installed = false;

export function installCommercialHistoryProtection() {
    if (installed || typeof window === 'undefined') {
        return;
    }

    installed = true;
    window.addEventListener('popstate', (event) => {
        for (const guard of guards) {
            guard(event);

            if (event.cancelBubble) {
                break;
            }
        }
    });
}

export function registerCommercialHistoryGuard(
    guard: (event: PopStateEvent) => void,
) {
    guards.add(guard);

    return () => {
        guards.delete(guard);
    };
}

/** Compare the existing Inertia page payload without decrypting it or adding
 * draft fields to history. Structured cloning changes object identity. */
export function sameCommercialHistoryEntry(
    left: unknown,
    right: unknown,
): boolean {
    const page = (value: unknown): unknown =>
        value && typeof value === 'object' && 'page' in value
            ? value.page
            : undefined;
    const a = page(left);
    const b = page(right);

    if (a === undefined || b === undefined) {
        return false;
    }

    if (a instanceof ArrayBuffer && b instanceof ArrayBuffer) {
        const first = new Uint8Array(a);
        const second = new Uint8Array(b);

        return (
            first.length === second.length &&
            first.every((byte, index) => byte === second[index])
        );
    }

    if (a instanceof ArrayBuffer || b instanceof ArrayBuffer) {
        return false;
    }

    try {
        return JSON.stringify(a) === JSON.stringify(b);
    } catch {
        return false;
    }
}

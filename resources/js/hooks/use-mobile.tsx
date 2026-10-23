import { useSyncExternalStore } from 'react';

import { useCallback } from 'react';

export function useIsMobile(breakpoint = 768): boolean {
    const subscribe = useCallback(
        (callback: () => void) => {
            const query = window.matchMedia(`(max-width: ${breakpoint - 1}px)`);
            query.addEventListener('change', callback);

            return () => query.removeEventListener('change', callback);
        },
        [breakpoint],
    );
    const snapshot = useCallback(
        () =>
            typeof window !== 'undefined' &&
            window.matchMedia(`(max-width: ${breakpoint - 1}px)`).matches,
        [breakpoint],
    );

    return useSyncExternalStore(subscribe, snapshot, () => false);
}

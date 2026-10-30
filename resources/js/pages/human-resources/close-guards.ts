export type CloseGuard = (close: () => void) => void;

/** Continue only when each mounted form permits closing. */
export function requestGuardedClose(
    guards: Iterable<CloseGuard>,
    close: () => void,
) {
    const pending = [...guards];
    const next = () => {
        const guard = pending.shift();

        if (guard) {
            guard(next);
        } else {
            close();
        }
    };
    next();
}

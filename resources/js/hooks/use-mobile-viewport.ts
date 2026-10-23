import { useSyncExternalStore } from 'react';

function subscribe(callback: () => void) {
    window.visualViewport?.addEventListener('resize', callback);
    window.visualViewport?.addEventListener('scroll', callback);
    window.addEventListener('resize', callback);
    document.addEventListener('focusin', callback);
    document.addEventListener('focusout', callback);

    return () => {
        window.visualViewport?.removeEventListener('resize', callback);
        window.visualViewport?.removeEventListener('scroll', callback);
        window.removeEventListener('resize', callback);
        document.removeEventListener('focusin', callback);
        document.removeEventListener('focusout', callback);
    };
}
function snapshot() {
    const height = Math.round(
        window.visualViewport?.height ?? window.innerHeight,
    );
    const focused = document.activeElement;
    const editable = focused?.matches(
        'input, textarea, select, [contenteditable="true"]',
    );

    return `${height}:${editable && window.innerHeight - height > 150 ? 'keyboard' : 'normal'}`;
}
export function useMobileViewport() {
    const value = useSyncExternalStore(subscribe, snapshot, () => '0:normal');
    const [height, mode] = value.split(':');

    return { height: Number(height), keyboard: mode === 'keyboard' };
}

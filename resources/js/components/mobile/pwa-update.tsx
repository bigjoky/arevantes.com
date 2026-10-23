import { useEffect, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';

function openForm() {
    // Controlled React fields can update defaultValue too; conservatively require
    // closing any form instead of claiming to detect all unsaved domain state.
    return Boolean(
        document.querySelector(
            'form input:not([type="hidden"]):not([type="submit"]):not([type="button"]), form textarea, form select',
        ),
    );
}

export function PwaUpdate() {
    const [waiting, setWaiting] = useState<ServiceWorker | null>(null);
    const [notice, setNotice] = useState('');
    const reloadRequested = useRef(false);
    useEffect(() => {
        if (!('serviceWorker' in navigator)) {
            return;
        }

        const changed = () => {
            if (reloadRequested.current) {
                window.location.reload();
            }
        };
        navigator.serviceWorker.addEventListener('controllerchange', changed);

        return () =>
            navigator.serviceWorker.removeEventListener(
                'controllerchange',
                changed,
            );
    }, []);
    useEffect(() => {
        if (!('serviceWorker' in navigator)) {
            return;
        }

        let disposed = false;
        let registration: ServiceWorkerRegistration | undefined;
        let installing: ServiceWorker | null = null;
        const sync = () => {
            if (
                !disposed &&
                registration?.waiting &&
                navigator.serviceWorker.controller
            ) {
                setWaiting(registration.waiting);
            }
        };
        const found = () => {
            installing?.removeEventListener('statechange', sync);
            installing = registration?.installing ?? null;
            installing?.addEventListener('statechange', sync);
            sync();
        };
        void navigator.serviceWorker.ready
            .then((value) => {
                if (disposed) {
                    return;
                }

                registration = value;
                registration.addEventListener('updatefound', found);
                found();
            })
            .catch(() => {});

        return () => {
            disposed = true;
            registration?.removeEventListener('updatefound', found);
            installing?.removeEventListener('statechange', sync);
        };
    }, []);

    if (!waiting) {
        return null;
    }

    return (
        <aside className="mobile-update" role="status">
            <p>
                {notice ||
                    'Hay una nueva versión. Guarda tus cambios antes de actualizar.'}
            </p>
            <Button
                size="sm"
                variant="outline"
                onClick={() => {
                    if (openForm()) {
                        setNotice(
                            'Cierra el formulario después de guardar o descartar sus cambios antes de actualizar.',
                        );

                        return;
                    }

                    reloadRequested.current = true;
                    waiting.postMessage({ type: 'ACTIVATE_UPDATE' });
                }}
            >
                Actualizar
            </Button>
        </aside>
    );
}

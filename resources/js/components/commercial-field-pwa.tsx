import { useEffect, useState, useSyncExternalStore } from 'react';
import { Button } from '@/components/ui/button';

type InstallPrompt = Event & {
    prompt: () => Promise<void>;
    userChoice: Promise<{ outcome: 'accepted' | 'dismissed' }>;
};

function subscribe(callback: () => void) {
    const display = window.matchMedia('(display-mode: standalone)');
    display.addEventListener('change', callback);
    window.addEventListener('appinstalled', callback);
    window.addEventListener('online', callback);
    window.addEventListener('offline', callback);

    return () => {
        display.removeEventListener('change', callback);
        window.removeEventListener('appinstalled', callback);
        window.removeEventListener('online', callback);
        window.removeEventListener('offline', callback);
    };
}

export function CommercialFieldPwa() {
    const [installPrompt, setInstallPrompt] = useState<InstallPrompt | null>(
        null,
    );
    const standalone = useSyncExternalStore(
        subscribe,
        () =>
            window.matchMedia('(display-mode: standalone)').matches ||
            (navigator as Navigator & { standalone?: boolean }).standalone ===
                true,
        () => false,
    );
    const online = useSyncExternalStore(
        subscribe,
        () => navigator.onLine,
        () => true,
    );

    useEffect(() => {
        const receive = (event: Event) => {
            event.preventDefault();
            setInstallPrompt(event as InstallPrompt);
        };
        const installed = () => setInstallPrompt(null);
        window.addEventListener('beforeinstallprompt', receive);
        window.addEventListener('appinstalled', installed);

        return () => {
            window.removeEventListener('beforeinstallprompt', receive);
            window.removeEventListener('appinstalled', installed);
        };
    }, []);

    async function install() {
        if (!installPrompt) {
            return;
        }

        await installPrompt.prompt();
        await installPrompt.userChoice;
        setInstallPrompt(null);
    }

    return (
        <div className="space-y-2 text-sm">
            {!online && (
                <p role="status" className="rounded-lg border p-3">
                    Sin conexión. Necesitas internet para guardar cambios.
                    Espera la confirmación de guardado antes de cerrar la
                    aplicación.
                </p>
            )}
            {!standalone && (
                <details className="rounded-lg border p-3">
                    <summary className="cursor-pointer font-medium">
                        Instalar AREVANTES en el teléfono
                    </summary>
                    <div className="mt-3 space-y-3 text-muted-foreground">
                        <p>
                            En iPhone: abre esta web en Safari, pulsa Compartir
                            → Añadir a pantalla de inicio y confirma Añadir. Si
                            aparece «Abrir como app web», actívalo.
                        </p>
                        <p>
                            Abre el icono AREVANTES e inicia sesión con tu
                            cuenta autorizada. Marca «Recordarme» solo en tu
                            teléfono personal. La aplicación abre Inicio.
                        </p>
                        <p>
                            En Android: utiliza Instalar aplicación o Añadir a
                            pantalla de inicio en el menú del navegador.
                            Necesitas conexión para guardar cambios.
                        </p>
                        {installPrompt && (
                            <Button
                                type="button"
                                variant="outline"
                                onClick={install}
                            >
                                Instalar AREVANTES
                            </Button>
                        )}
                    </div>
                </details>
            )}
        </div>
    );
}

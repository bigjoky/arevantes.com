import { router, usePage } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useIsMobile } from '@/hooks/use-mobile';
import {
    registerCommercialHistoryGuard,
    sameCommercialHistoryEntry,
} from '@/lib/mobile/commercial-history';
import { mobileView } from '@/lib/mobile/navigation';
import { TaskDraftGuard } from '@/lib/mobile/task-draft-guard';
import '../../css/commercial-mobile.css';

type Draft = {
    isDirty: boolean;
    processing: boolean;
    resetAndClearErrors: () => void;
};

/** Scoped to an existing commercial form; values stay in that form's memory. */
export function useCommercialFormProtection(
    read: () => Draft | null,
    enabled = true,
) {
    const isMobile = useIsMobile(1024);
    const page = usePage();
    const reader = useRef(read);
    const currentUrl = useRef(page.url);
    const entryState = useRef<unknown>(null);
    const underlyingEntry = useRef<{ url: string; state: unknown } | null>(
        null,
    );
    const allowNextBack = useRef(false);
    const allowNextVisit = useRef(false);
    const confirmationOpener = useRef<HTMLElement | null>(null);
    const continueButton = useRef<HTMLButtonElement | null>(null);
    const [open, setOpen] = useState(false);
    const [notice, setNotice] = useState('');
    const [guard] = useState(() => new TaskDraftGuard());
    useEffect(() => {
        reader.current = read;
        currentUrl.current = page.url;
        entryState.current = window.history.state;

        if (!mobileView(page.url)) {
            underlyingEntry.current = {
                url: page.url,
                state: window.history.state,
            };
        }
    });
    useEffect(() => {
        guard.connect(
            () => ({
                dirty: reader.current()?.isDirty ?? false,
                processing: reader.current()?.processing ?? false,
            }),
            (value) => {
                if (value) {
                    confirmationOpener.current =
                        document.activeElement as HTMLElement;
                }

                setOpen(value);
            },
            () => reader.current()?.resetAndClearErrors(),
        );
    }, [guard]);
    const requestClose = useCallback(
        (close: () => void) => {
            if (!isMobile || !enabled) {
                close();

                return;
            }

            if (guard.requestClose(close) === 'busy') {
                setNotice('Espera a que termine el envío antes de salir.');
            }
        },
        [guard, isMobile, enabled],
    );
    useEffect(() => {
        if (!isMobile || !enabled) {
            guard.keepEditing();

            return;
        }

        const before = router.on('before', (event) => {
            if (allowNextVisit.current) {
                allowNextVisit.current = false;

                return;
            }

            if (mobileView(currentUrl.current) === 'create') {
                return;
            }

            if (
                event.detail.visit.method !== 'get' ||
                (!reader.current()?.isDirty && !guard.busy())
            ) {
                return;
            }

            event.preventDefault();
            const visit = event.detail.visit;
            requestClose(() => {
                allowNextVisit.current = true;
                router.visit(visit.url, visit);
            });
        });
        // Restore the existing encrypted entry without router.push: a router
        // visit would remount uncontrolled Form fields and lose their draft.
        // No form values are added to history or persistent storage.
        const pop = (event: PopStateEvent) => {
            if (allowNextBack.current) {
                allowNextBack.current = false;

                return;
            }

            if (!reader.current()?.isDirty && !guard.busy()) {
                return;
            }

            const overlay = mobileView(currentUrl.current);
            const underlying = underlyingEntry.current;

            if (
                overlay &&
                overlay !== 'create' &&
                underlying &&
                sameCommercialHistoryEntry(underlying.state, event.state)
            ) {
                // Closing a PWA screen is not leaving the commercial form.
                // Inertia's default pop restores with preserveState:false;
                // replace the target entry instead to retain uncontrolled fields.
                event.stopImmediatePropagation();
                router.replace({
                    url: underlying.url,
                    preserveState: true,
                    preserveScroll: true,
                });

                return;
            }

            // The global task sheet owns its close/discard confirmation.
            if (overlay === 'create') {
                return;
            }

            event.stopImmediatePropagation();
            window.history.pushState(
                entryState.current,
                '',
                currentUrl.current,
            );
            requestClose(() => {
                allowNextBack.current = true;
                window.history.back();
            });
        };
        const unload = (event: BeforeUnloadEvent) => {
            if (reader.current()?.isDirty || guard.busy()) {
                event.preventDefault();
                event.returnValue = '';
            }
        };
        const stopHistoryGuard = registerCommercialHistoryGuard(pop);
        window.addEventListener('beforeunload', unload);

        return () => {
            before();
            stopHistoryGuard();
            window.removeEventListener('beforeunload', unload);
        };
    }, [guard, isMobile, enabled, requestClose]);
    const beginSubmit = () => !enabled || !isMobile || guard.beginSubmit();
    const finishSubmit = () => guard.finishSubmit();
    const saved = (callback: () => void = () => {}) => {
        if (!isMobile || !enabled) {
            callback();

            return;
        }

        guard.saved(callback);
    };
    const confirmation = (
        <>
            {notice && (
                <p role="status" className="commercial-form-notice">
                    {notice}
                </p>
            )}
            <Dialog
                open={open}
                onOpenChange={(value) => {
                    if (!value) {
                        guard.keepEditing();
                    }
                }}
            >
                <DialogContent
                    className="commercial-draft-dialog"
                    onOpenAutoFocus={(event) => {
                        event.preventDefault();
                        requestAnimationFrame(() =>
                            continueButton.current?.focus(),
                        );
                    }}
                    onPointerDownOutside={(event) => event.preventDefault()}
                    onEscapeKeyDown={(event) => {
                        event.preventDefault();
                        guard.keepEditing();
                    }}
                    onCloseAutoFocus={(event) => {
                        event.preventDefault();

                        if (confirmationOpener.current?.isConnected) {
                            confirmationOpener.current.focus();
                        }
                    }}
                >
                    <DialogHeader>
                        <DialogTitle>¿Descartar los cambios?</DialogTitle>
                        <DialogDescription>
                            Hay cambios sin guardar. Puedes continuar editando o
                            descartarlos antes de salir.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            ref={continueButton}
                            variant="outline"
                            onClick={() => guard.keepEditing()}
                        >
                            Seguir editando
                        </Button>
                        <Button
                            variant="destructive"
                            onClick={() => guard.discard()}
                        >
                            Descartar cambios
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );

    return {
        requestClose,
        beginSubmit,
        finishSubmit,
        saved,
        confirmation,
        confirmationOpen: open,
        mobile: isMobile,
    };
}

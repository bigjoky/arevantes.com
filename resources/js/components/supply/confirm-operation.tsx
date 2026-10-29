import { useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

/** One accessible, local confirmation; the existing form owns data and submission. */
export function useSupplyConfirmation() {
    const action = useRef<(() => void) | null>(null);
    const [open, setOpen] = useState(false);

    return {
        confirm: (run: () => void) => {
            action.current = run;
            setOpen(true);
        },
        dialog: (
            <Dialog
                open={open}
                onOpenChange={(value) => {
                    if (!value) {
                        action.current = null;
                    }

                    setOpen(value);
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            Confirmar operación de material
                        </DialogTitle>
                        <DialogDescription>
                            Revisa las cantidades, el almacén y el destino antes
                            de registrar la operación. Se aplicarán las
                            validaciones del servidor.
                        </DialogDescription>
                    </DialogHeader>
                    <Button
                        variant="outline"
                        onClick={() => {
                            action.current = null;
                            setOpen(false);
                        }}
                    >
                        Seguir revisando
                    </Button>
                    <Button
                        onClick={() => {
                            const run = action.current;
                            action.current = null;
                            setOpen(false);
                            run?.();
                        }}
                    >
                        Confirmar operación
                    </Button>
                </DialogContent>
            </Dialog>
        ),
    };
}

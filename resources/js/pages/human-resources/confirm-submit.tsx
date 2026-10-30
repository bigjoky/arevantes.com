import { useRef, useState } from 'react';
import type { ComponentProps } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

/** Confirm a consequential action while retaining the existing Inertia form. */
export default function ConfirmSubmit({
    children,
    description = 'Revisa los datos antes de confirmar la operación.',
    ...props
}: ComponentProps<typeof Button> & { description?: string }) {
    const button = useRef<HTMLButtonElement>(null);
    const [open, setOpen] = useState(false);

    return (
        <>
            <Button
                {...props}
                ref={button}
                type="button"
                onClick={() => {
                    if (button.current?.form?.reportValidity()) {
                        setOpen(true);
                    }
                }}
            >
                {children}
            </Button>
            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Confirmar operación</DialogTitle>
                        <DialogDescription>{description}</DialogDescription>
                    </DialogHeader>
                    <div className="flex flex-wrap justify-end gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setOpen(false)}
                        >
                            Cancelar
                        </Button>
                        <Button
                            type="button"
                            onClick={() => {
                                setOpen(false);
                                button.current?.form?.requestSubmit();
                            }}
                        >
                            Confirmar
                        </Button>
                    </div>
                </DialogContent>
            </Dialog>
        </>
    );
}

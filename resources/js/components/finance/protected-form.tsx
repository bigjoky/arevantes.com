import { useRef, useState } from 'react';
import type { ComponentProps } from 'react';
import ProjectForm from '@/components/projects/protected-form';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

/** Local confirmation adapter; all validation and submission remain in the existing form. */
export default function FinanceForm({
    onBefore,
    ...props
}: ComponentProps<typeof ProjectForm>) {
    const root = useRef<HTMLDivElement>(null);
    const approved = useRef(false);
    const [confirming, setConfirming] = useState(false);

    return (
        <div ref={root} className="finance-protected-form">
            <ProjectForm
                {...props}
                onBefore={(visit) => {
                    if (!approved.current) {
                        setConfirming(true);

                        return false;
                    }

                    approved.current = false;

                    return onBefore?.(visit);
                }}
            />
            <Dialog open={confirming} onOpenChange={setConfirming}>
                <DialogContent className="finance-mobile-dialog">
                    <DialogHeader>
                        <DialogTitle>
                            Confirmar operación financiera
                        </DialogTitle>
                        <DialogDescription>
                            Revisa los datos y el documento de origen. Se
                            aplicarán los permisos y controles del servidor.
                        </DialogDescription>
                    </DialogHeader>
                    <Button
                        variant="outline"
                        onClick={() => setConfirming(false)}
                    >
                        Seguir revisando
                    </Button>
                    <Button
                        onClick={() => {
                            if (approved.current) {
                                return;
                            }

                            approved.current = true;
                            setConfirming(false);
                            root.current
                                ?.querySelector('form')
                                ?.requestSubmit();
                        }}
                    >
                        Confirmar operación
                    </Button>
                </DialogContent>
            </Dialog>
        </div>
    );
}

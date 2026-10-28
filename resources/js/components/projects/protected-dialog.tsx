import { createContext, useCallback, useRef, useState } from 'react';
import type { ComponentProps } from 'react';
import { Dialog, DialogContent } from '@/components/ui/dialog';

export function ProjectDialogContent({
    className,
    ...props
}: ComponentProps<typeof DialogContent>) {
    return (
        <DialogContent
            {...props}
            className={'projects-mobile-dialog ' + (className ?? '')}
        />
    );
}

type CloseGuard = (close: () => void) => void;
export const ProjectDialogForms = createContext<
    ((guard: CloseGuard) => () => void) | null
>(null);

/** Only forms mounted in this existing dialog participate in its close request. */
export default function ProjectDialog({
    open,
    defaultOpen,
    onOpenChange,
    ...props
}: ComponentProps<typeof Dialog>) {
    const [localOpen, setLocalOpen] = useState(defaultOpen ?? false);
    const guards = useRef(new Set<CloseGuard>());
    const register = useCallback((guard: CloseGuard) => {
        guards.current.add(guard);

        return () => {
            guards.current.delete(guard);
        };
    }, []);
    const setOpen = (value: boolean) => {
        setLocalOpen(value);
        onOpenChange?.(value);
    };
    const close = () => {
        const pending = [...guards.current];
        const next = () => {
            const guard = pending.shift();

            if (guard) {
                guard(next);
            } else {
                setOpen(false);
            }
        };
        next();
    };

    return (
        <ProjectDialogForms.Provider value={register}>
            <Dialog
                {...props}
                open={open ?? localOpen}
                onOpenChange={(value) => {
                    if (value) {
                        setOpen(true);
                    } else {
                        close();
                    }
                }}
            />
        </ProjectDialogForms.Provider>
    );
}

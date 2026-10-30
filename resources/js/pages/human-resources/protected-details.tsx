import { useCallback, useRef } from 'react';
import type { ComponentProps } from 'react';
import { ProjectDialogForms } from '@/components/projects/protected-dialog';
import { requestGuardedClose } from './close-guards';
import type { CloseGuard } from './close-guards';

/** Keeps inline HR forms under the same discard guard as protected dialogs. */
export default function ProtectedDetails(props: ComponentProps<'details'>) {
    const details = useRef<HTMLDetailsElement>(null);
    const guards = useRef(new Set<CloseGuard>());
    const register = useCallback((guard: CloseGuard) => {
        guards.current.add(guard);

        return () => {
            guards.current.delete(guard);
        };
    }, []);

    return (
        <ProjectDialogForms.Provider value={register}>
            <details
                {...props}
                ref={details}
                onClickCapture={(event) => {
                    props.onClickCapture?.(event);
                    const summary = (event.target as Element).closest(
                        'summary',
                    );

                    if (
                        event.defaultPrevented ||
                        !details.current?.open ||
                        summary?.closest('details') !== details.current
                    ) {
                        return;
                    }

                    event.preventDefault();
                    requestGuardedClose(guards.current, () => {
                        if (details.current) {
                            details.current.open = false;
                        }
                    });
                }}
            />
        </ProjectDialogForms.Provider>
    );
}

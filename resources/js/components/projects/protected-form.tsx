import type { FormComponentRef } from '@inertiajs/core';
import { Form } from '@inertiajs/react';
import {
    useContext,
    useEffect,
    useImperativeHandle,
    useRef,
    useState,
} from 'react';
import type { ComponentProps, Ref } from 'react';
import { useOperationsFormProtection } from '@/hooks/use-operations-form-protection';
import { ProjectDialogForms } from './protected-dialog';

export type ProjectFormHandle = { requestClose: (close: () => void) => void };

/** Adapts the existing Inertia Form without moving or persisting its values. */
export default function ProjectForm({
    ref,
    onBefore,
    onSuccess,
    onFinish,
    onResetFields,
    children,
    ...props
}: Omit<ComponentProps<typeof Form<Record<string, unknown>>>, 'ref'> & {
    ref?: Ref<ProjectFormHandle>;
    onResetFields?: () => void;
}) {
    const [resetVersion, setResetVersion] = useState(0);
    const form = useRef<FormComponentRef>(null);
    const protection = useOperationsFormProtection(() =>
        form.current
            ? {
                  isDirty: form.current.isDirty,
                  processing: form.current.processing,
                  resetAndClearErrors: () => {
                      form.current?.resetAndClearErrors();
                      onResetFields?.();
                      setResetVersion((version) => version + 1);
                  },
              }
            : null,
    );
    const register = useContext(ProjectDialogForms);
    useEffect(
        () => register?.(protection.requestClose),
        [register, protection.requestClose],
    );
    const renderChildren =
        typeof children === 'function' ? children : () => children;
    useImperativeHandle(
        ref,
        () => ({ requestClose: protection.requestClose }),
        [protection.requestClose],
    );

    return (
        <>
            {protection.confirmation}
            <Form
                {...props}
                key={resetVersion}
                ref={form}
                onBefore={(visit) => {
                    if (onBefore?.(visit) === false) {
                        return false;
                    }

                    return protection.beginSubmit();
                }}
                onSuccess={(page) => {
                    form.current?.defaults();
                    protection.saved(() => {
                        void onSuccess?.(page);
                    });
                }}
                onFinish={(visit) => {
                    protection.finishSubmit();
                    onFinish?.(visit);
                }}
            >
                {(state) => (
                    <>
                        {Object.values(state.errors).length > 0 && (
                            <div role="alert">
                                {Object.entries(state.errors).map(
                                    ([field, error]) => (
                                        <p key={field}>{String(error)}</p>
                                    ),
                                )}
                            </div>
                        )}
                        {renderChildren(state)}
                    </>
                )}
            </Form>
        </>
    );
}

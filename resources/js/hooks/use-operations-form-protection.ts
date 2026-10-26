import { useRef } from 'react';
import { useCommercialFormProtection } from '@/hooks/use-commercial-form-protection';

type Draft = {
    isDirty: boolean;
    processing: boolean;
    resetAndClearErrors: () => void;
};
/** Reuse the published guard and dialog; synchronous submit latch also covers desktop. */
export function useOperationsFormProtection(read: () => Draft | null) {
    const protection = useCommercialFormProtection(read);
    const sending = useRef(false);

    return {
        ...protection,
        beginSubmit: () => {
            if (
                sending.current ||
                read()?.processing ||
                !protection.beginSubmit()
            ) {
                return false;
            }

            sending.current = true;

            return true;
        },
        saved: (callback: () => void = () => {}) => {
            if (!protection.mobile) {
                read()?.resetAndClearErrors();
            }

            protection.saved(callback);
        },
        finishSubmit: () => {
            sending.current = false;
            protection.finishSubmit();
        },
    };
}

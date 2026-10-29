import { useForm } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { useOperationsFormProtection } from '@/hooks/use-operations-form-protection';
import { useSupplyConfirmation } from './confirm-operation';

export default function ReleaseReservation({ id }: { id: number }) {
    const form = useForm({});
    const protection = useOperationsFormProtection(() => form);
    const confirmation = useSupplyConfirmation();

    return (
        <>
            {protection.confirmation}
            {confirmation.dialog}
            <Button
                variant="outline"
                disabled={form.processing}
                onClick={() =>
                    confirmation.confirm(() => {
                        if (!protection.beginSubmit()) {
                            return;
                        }

                        form.delete(`/inventory/reservations/${id}`, {
                            preserveScroll: true,
                            onFinish: protection.finishSubmit,
                        });
                    })
                }
            >
                Liberar reserva
            </Button>
            {Object.entries(form.errors).map(([key, error]) => (
                <InputError key={key} message={String(error)} />
            ))}
        </>
    );
}

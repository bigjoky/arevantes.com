import { useForm } from '@inertiajs/react';
import { useRef } from 'react';
import { Button } from '@/components/ui/button';
import { useOperationsFormProtection } from '@/hooks/use-operations-form-protection';

export function OrderEvidenceUpload({ orderId }: { orderId: number }) {
    const form = useForm<{ files: File[]; caption: string }>({
        files: [],
        caption: '',
    });
    const input = useRef<HTMLInputElement>(null);
    const protection = useOperationsFormProtection(() => ({
        ...form,
        resetAndClearErrors: () => {
            form.resetAndClearErrors();

            if (input.current) {
                input.current.value = '';
            }
        },
    }));

    return (
        <>
            {protection.confirmation}
            <form
                className="ops-schedule-form"
                onSubmit={(event) => {
                    event.preventDefault();

                    if (!protection.beginSubmit()) {
                        return;
                    }

                    form.post(`/work-orders/${orderId}/attachments`, {
                        forceFormData: true,
                        preserveScroll: true,
                        onSuccess: () =>
                            protection.saved(() => {
                                if (input.current) {
                                    input.current.value = '';
                                }
                            }),
                        onFinish: protection.finishSubmit,
                    });
                }}
            >
                <label>
                    Fotografías o documentos
                    <input
                        ref={input}
                        type="file"
                        multiple
                        accept=".jpg,.jpeg,.png,.webp,.pdf"
                        onChange={(e) =>
                            form.setData(
                                'files',
                                Array.from(e.target.files ?? []),
                            )
                        }
                    />
                </label>
                <p>
                    Hasta 12 archivos JPG, PNG, WebP o PDF, de 12 MB cada uno.
                    Uso interno; no se publican al cliente.
                </p>
                <label>
                    Observaciones de la evidencia
                    <textarea
                        value={form.data.caption}
                        maxLength={500}
                        onChange={(e) =>
                            form.setData('caption', e.target.value)
                        }
                    />
                </label>
                {Object.entries(form.errors).map(([key, error]) => (
                    <p role="alert" key={key}>
                        {error}
                    </p>
                ))}
                {form.progress && (
                    <p role="status">{form.progress.percentage}%</p>
                )}
                <Button disabled={form.processing || !form.data.files.length}>
                    Añadir evidencias
                </Button>
            </form>
        </>
    );
}

export function OrderTimer({
    orderId,
    running,
    canStart,
}: {
    orderId: number;
    running: { id: number; started_at: string } | null;
    canStart: boolean;
}) {
    const form = useForm({ notes: '', travel_km: '0' });
    const protection = useOperationsFormProtection(() => form);

    if (!running && !canStart) {
        return null;
    }

    return (
        <>
            {protection.confirmation}
            <form
                className="ops-schedule-form"
                onSubmit={(e) => {
                    e.preventDefault();

                    if (!protection.beginSubmit()) {
                        return;
                    }

                    form.transform((values) => ({
                        ...values,
                        travel_km:
                            values.travel_km === '' ? '0' : values.travel_km,
                    }));
                    const options = {
                        preserveScroll: true,
                        onSuccess: () => protection.saved(),
                        onFinish: protection.finishSubmit,
                    };

                    if (running) {
                        form.patch(`/time-entries/${running.id}/stop`, options);
                    } else {
                        form.post(`/work-orders/${orderId}/timer`, options);
                    }
                }}
            >
                {running ? (
                    <>
                        <p>
                            Temporizador en curso:{' '}
                            {new Date(running.started_at).toLocaleString()}
                        </p>
                        <label>
                            Actuación realizada
                            <textarea
                                value={form.data.notes}
                                onChange={(e) =>
                                    form.setData('notes', e.target.value)
                                }
                            />
                        </label>
                        <label>
                            Desplazamiento (km)
                            <input
                                type="number"
                                min="0"
                                step="0.01"
                                value={form.data.travel_km}
                                onChange={(e) =>
                                    form.setData('travel_km', e.target.value)
                                }
                            />
                        </label>
                    </>
                ) : (
                    <p>
                        Registra el tiempo de esta actuación. Se aplican las
                        validaciones operativas existentes.
                    </p>
                )}
                {Object.entries(form.errors).map(([key, error]) => (
                    <p role="alert" key={key}>
                        {error}
                    </p>
                ))}
                <Button disabled={form.processing}>
                    {running
                        ? 'Guardar actuación y detener'
                        : 'Iniciar actuación'}
                </Button>
            </form>
        </>
    );
}

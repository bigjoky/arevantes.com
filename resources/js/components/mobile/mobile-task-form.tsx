import type { FormComponentRef } from '@inertiajs/core';
import { Form, Link, usePage } from '@inertiajs/react';
import type { RefObject } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

/** Mobile presentation of the existing operational CrmActivity task endpoint. */
export function MobileTaskForm({
    offline,
    onSaved,
    formRef,
    onBeforeSubmit,
    onFinishSubmit,
}: {
    offline: boolean;
    onSaved: () => void;
    formRef: RefObject<FormComponentRef | null>;
    onBeforeSubmit: () => boolean;
    onFinishSubmit: () => void;
}) {
    const { auth } = usePage().props;

    if (!auth.abilities.manageOperations) {
        return null;
    }

    return (
        <Form
            ref={formRef}
            action="/operations/activities"
            method="post"
            resetOnSuccess
            onSuccess={onSaved}
            onBefore={onBeforeSubmit}
            onFinish={onFinishSubmit}
            disableWhileProcessing
            className="mobile-task-form"
        >
            {({ processing, errors }) => (
                <>
                    <input type="hidden" name="type" value="task" />
                    <input
                        type="hidden"
                        name="assigned_user_id"
                        value={auth.user.id}
                    />
                    <label htmlFor="mobile-task-title">Título</label>
                    <Input
                        id="mobile-task-title"
                        name="title"
                        maxLength={255}
                        required
                        aria-invalid={Boolean(errors.title)}
                        aria-describedby={
                            errors.title ? 'mobile-task-title-error' : undefined
                        }
                        placeholder="Título de la tarea"
                    />
                    {errors.title && (
                        <p role="alert" id="mobile-task-title-error">
                            {errors.title}
                        </p>
                    )}
                    <label htmlFor="mobile-task-due">Fecha y hora</label>
                    <Input
                        id="mobile-task-due"
                        type="datetime-local"
                        name="due_at"
                        aria-invalid={Boolean(errors.due_at)}
                    />
                    {errors.due_at && <p role="alert">{errors.due_at}</p>}
                    <label htmlFor="mobile-task-priority">Prioridad</label>
                    <select
                        id="mobile-task-priority"
                        name="priority"
                        defaultValue="normal"
                    >
                        <option value="low">Baja</option>
                        <option value="normal">Media</option>
                        <option value="high">Alta</option>
                    </select>
                    {errors.priority && <p role="alert">{errors.priority}</p>}
                    {errors.assigned_user_id && (
                        <p role="alert">{errors.assigned_user_id}</p>
                    )}
                    <p className="mobile-capability-note">
                        Asignada a ti. Puedes vincular instalaciones y clientes
                        desde Operaciones.
                    </p>
                    {offline && (
                        <p role="alert">
                            Necesitas conexión para crear la tarea.
                        </p>
                    )}
                    <Button type="submit" disabled={processing || offline}>
                        {processing ? 'Guardando…' : 'Crear tarea'}
                    </Button>
                    <Link
                        href="/operations/activities"
                        className="text-center text-sm underline"
                    >
                        Abrir Operaciones
                    </Link>
                </>
            )}
        </Form>
    );
}

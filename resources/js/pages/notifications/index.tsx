import { Form, Head, Link } from '@inertiajs/react';

type Entry = {
    id: string;
    message: string;
    url: string | null;
    created_at: string | null;
    read_at: string | null;
};
type Page = {
    data: Entry[];
    prev_page_url: string | null;
    next_page_url: string | null;
};

export default function NotificationHistory({
    notifications,
}: {
    notifications: Page;
}) {
    return (
        <>
            <Head title="Notificaciones" />
            <main className="mx-auto w-full max-w-4xl space-y-6 p-4 sm:p-6">
                <h1 className="text-2xl font-semibold">
                    Historial de notificaciones
                </h1>
                {notifications.data.length === 0 ? (
                    <p>No hay notificaciones.</p>
                ) : (
                    <ul className="space-y-3">
                        {notifications.data.map((item) => (
                            <li
                                key={item.id}
                                className="space-y-2 rounded-lg border p-4"
                            >
                                <p>
                                    {item.url ? (
                                        <Link
                                            className="underline"
                                            href={item.url}
                                        >
                                            {item.message}
                                        </Link>
                                    ) : (
                                        item.message
                                    )}
                                </p>
                                <p className="text-sm text-muted-foreground">
                                    {item.created_at
                                        ? new Date(
                                              item.created_at,
                                          ).toLocaleString('es-ES')
                                        : ''}{' '}
                                    · {item.read_at ? 'Leída' : 'No leída'}
                                </p>
                                {!item.read_at && (
                                    <Form
                                        action={`/notifications/${item.id}/read`}
                                        method="post"
                                    >
                                        {({ processing }) => (
                                            <button
                                                type="submit"
                                                disabled={processing}
                                                className="text-sm underline"
                                            >
                                                Marcar como leída
                                            </button>
                                        )}
                                    </Form>
                                )}
                            </li>
                        ))}
                    </ul>
                )}
                <nav
                    className="flex gap-4"
                    aria-label="Paginación de notificaciones"
                >
                    {notifications.prev_page_url && (
                        <Link href={notifications.prev_page_url}>Anterior</Link>
                    )}
                    {notifications.next_page_url && (
                        <Link href={notifications.next_page_url}>
                            Siguiente
                        </Link>
                    )}
                </nav>
            </main>
        </>
    );
}

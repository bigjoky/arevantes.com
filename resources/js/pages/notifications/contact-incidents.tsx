import { Form, Head, Link } from '@inertiajs/react';

type Receipt = {
    id: number;
    created_at: string;
    expires_at: string;
    payload: {
        name: string;
        email?: string;
        phone?: string;
        postal_code?: string;
        service: string;
        message: string;
    } | null;
};
type Page = {
    data: Receipt[];
    prev_page_url: string | null;
    next_page_url: string | null;
};
export default function ContactIncidents({ receipts }: { receipts: Page }) {
    return (
        <>
            <Head title="Incidencias de contacto web" />
            <main className="mx-auto w-full max-w-4xl space-y-5 p-4 sm:p-6">
                <h1 className="text-2xl font-semibold">
                    Incidencias de contacto web
                </h1>
                <p>
                    Solicitudes recibidas que aún no se han incorporado al CRM.
                    Revisa y vuelve a procesar cada incidencia.
                </p>
                {!receipts.data.length && <p>No hay incidencias pendientes.</p>}
                {receipts.data.map((item) => (
                    <article
                        className="space-y-2 rounded-lg border p-4"
                        key={item.id}
                    >
                        <h2 className="font-semibold">
                            Solicitud #{item.id} ·{' '}
                            {new Date(item.created_at).toLocaleString('es-ES')}
                        </h2>
                        {item.payload && (
                            <>
                                <p>
                                    {item.payload.name} ·{' '}
                                    {item.payload.email || 'Sin correo'} ·{' '}
                                    {item.payload.phone || 'Sin teléfono'}
                                </p>
                                <p>
                                    Servicio: {item.payload.service} · Código
                                    postal:{' '}
                                    {item.payload.postal_code || 'Sin indicar'}
                                </p>
                                <p className="whitespace-pre-wrap">
                                    {item.payload.message}
                                </p>
                            </>
                        )}
                        <p>
                            Revisar antes de:{' '}
                            {new Date(item.expires_at).toLocaleString('es-ES')}
                        </p>
                        <Form
                            action={`/contact-incidents/${item.id}/retry`}
                            method="post"
                        >
                            {({ processing }) => (
                                <button
                                    className="underline"
                                    disabled={processing}
                                >
                                    Reprocesar solicitud
                                </button>
                            )}
                        </Form>
                    </article>
                ))}
                <nav
                    className="flex gap-4"
                    aria-label="Paginación de incidencias"
                >
                    {receipts.prev_page_url && (
                        <Link href={receipts.prev_page_url}>Anterior</Link>
                    )}
                    {receipts.next_page_url && (
                        <Link href={receipts.next_page_url}>Siguiente</Link>
                    )}
                </nav>
            </main>
        </>
    );
}

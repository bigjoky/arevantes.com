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
type MailItem = { id: number; opportunity_id: number; receipt_id: number; kind: string };
type MailPage = { data: MailItem[]; prev_page_url: string | null; next_page_url: string | null };
type GraphTest = { id: string; status: string; accepted_at: string | null; receipt_confirmed_at: string | null };
export default function ContactIncidents({ receipts, mail, graphTestId, graphTests }: { receipts: Page; mail: MailPage; graphTestId: string; graphTests: GraphTest[] }) {
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
                <section className="space-y-4" aria-labelledby="pending-mail">
                    <h2 id="pending-mail" className="text-xl font-semibold">Correos pendientes sin intentos</h2>
                    <p>La cancelación queda registrada con tu usuario. No incluyas datos personales en el motivo.</p>
                    {!mail.data.length && <p>No hay correos pendientes sin intentos.</p>}
                    {mail.data.map((item) => (
                        <article key={item.id} className="space-y-2 rounded-lg border p-4">
                            <p>Correo #{item.id} · Solicitud #{item.receipt_id} · {item.kind === 'visitor' ? 'Acuse al visitante' : 'Aviso interno'}</p>
                            <Link href={`/opportunities/${item.opportunity_id}`} className="underline">Ver oportunidad #{item.opportunity_id}</Link>
                            <Form action={`/contact-incidents/mail/${item.id}/cancel`} method="post">
                                {({ processing, errors }) => (
                                    <div className="space-y-2">
                                        <label htmlFor={`reason-${item.id}`} className="block">Motivo de cancelación</label>
                                        <input id={`reason-${item.id}`} name="reason" required maxLength={250} className="w-full rounded border p-2" aria-describedby={errors.reason ? `error-${item.id}` : undefined} />
                                        {errors.reason && <p id={`error-${item.id}`} role="alert">{errors.reason}</p>}
                                        <button className="underline" disabled={processing}>Cancelar correo pendiente</button>
                                    </div>
                                )}
                            </Form>
                        </article>
                    ))}
                    <nav className="flex gap-4" aria-label="Paginación de correos">
                        {mail.prev_page_url && <Link href={mail.prev_page_url}>Anterior</Link>}
                        {mail.next_page_url && <Link href={mail.next_page_url}>Siguiente</Link>}
                    </nav>
                </section>
                <section className="space-y-4 rounded-lg border p-4" aria-labelledby="graph-test">
                    <h2 id="graph-test" className="text-xl font-semibold">Prueba técnica de Microsoft Graph</h2>
                    <p>La prueba utiliza exclusivamente info@arevantes.com como remitente y destinatario. Autorizar no envía el mensaje. La aceptación de Microsoft no confirma su recepción.</p>
                    <Form action="/contact-incidents/graph-test" method="post">
                        {({ processing, errors }) => <div className="space-y-2">
                            <input type="hidden" name="id" value={graphTestId} />
                            <label className="block" htmlFor="test-recipient">Escribe info@arevantes.com para autorizar este destinatario</label>
                            <input className="w-full rounded border p-2" id="test-recipient" name="recipient" type="email" required />
                            {errors.recipient && <p role="alert">{errors.recipient}</p>}
                            <button className="underline" disabled={processing}>Autorizar una prueba</button>
                        </div>}
                    </Form>
                    {graphTests.map((test) => <article key={test.id} className="space-y-2 border-t pt-3">
                        <p>Prueba {test.id} · {test.status === 'accepted' ? (test.receipt_confirmed_at ? 'Aceptada por Microsoft; recepción confirmada por Administración' : 'Aceptada por Microsoft; recepción sin verificar') : test.status === 'authorized' ? 'Autorizada, sin enviar' : 'Resultado incierto o en curso; no reenviar'}</p>
                        {test.status === 'authorized' && <Form action={`/contact-incidents/graph-test/${test.id}/send`} method="post">
                            {({ processing, errors }) => <div className="space-y-2">
                                <input type="hidden" name="recipient" value="info@arevantes.com" />
                                <label className="block" htmlFor={`confirm-${test.id}`}>Escribe ENVIAR UNA VEZ para confirmar el envío a info@arevantes.com</label>
                                <input className="w-full rounded border p-2" id={`confirm-${test.id}`} name="confirmation" required />
                                {(errors.confirmation || errors.test) && <p role="alert">{errors.confirmation || errors.test}</p>}
                                <button className="underline" disabled={processing}>Enviar una prueba</button>
                            </div>}
                        </Form>}
                        {test.status === 'accepted' && !test.receipt_confirmed_at && <Form action={`/contact-incidents/graph-test/${test.id}/receipt`} method="post">
                            {({ processing, errors }) => <div className="space-y-2">
                                <label className="block" htmlFor={`receipt-${test.id}`}>Tras comprobar el buzón, escribe RECIBIDO</label>
                                <input className="w-full rounded border p-2" id={`receipt-${test.id}`} name="confirmation" required />
                                {(errors.confirmation || errors.test) && <p role="alert">{errors.confirmation || errors.test}</p>}
                                <button className="underline" disabled={processing}>Registrar recepción comprobada</button>
                            </div>}
                        </Form>}
                    </article>)}
                </section>
            </main>
        </>
    );
}

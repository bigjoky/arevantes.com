import { useForm } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';

type Evidence = {
    id: number;
    channel: string;
    changed_at: string;
    signed_by_name?: string;
    proof_name?: string;
    changed_by?: { name: string } | null;
};
export default function QuotePaperAcceptance({
    quoteId,
    canAccept,
    history,
}: {
    quoteId: number;
    canAccept: boolean;
    history: Evidence[];
}) {
    const form = useForm<{
        accepted_at: string;
        signed_by_name: string;
        reason: string;
        proof: File | null;
    }>({
        accepted_at: new Date().toLocaleDateString('en-CA'),
        signed_by_name: '',
        reason: '',
        proof: null,
    });
    const paper = history.find((h) => h.channel === 'paper');

    if (paper) {
        return (
            <section className="rounded-lg border p-4">
                <h2 className="font-semibold">Aceptación firmada en papel</h2>
                <p className="mt-2 text-sm">
                    Firmante: {paper.signed_by_name} · Fecha:{' '}
                    {paper.changed_at.slice(0, 10)} · Registrada por:{' '}
                    {paper.changed_by?.name || 'Sin registrar'}
                </p>
                {paper.proof_name && (
                    <a
                        className="mt-2 inline-block text-sm underline"
                        href={`/quotes/${quoteId}/paper-acceptance/${paper.id}`}
                    >
                        Descargar justificante firmado
                    </a>
                )}
            </section>
        );
    }

    if (!canAccept) {
        return null;
    }

    return (
        <details className="rounded-lg border p-4">
            <summary className="cursor-pointer font-semibold">
                Registrar aceptación firmada en papel · cliente sin email
            </summary>
            <form
                className="mt-4 grid gap-3"
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post(`/quotes/${quoteId}/paper-acceptance`, {
                        forceFormData: true,
                        preserveScroll: true,
                    });
                }}
            >
                <p className="text-sm text-muted-foreground">
                    Registra la firma sin enviar correo. La aceptación no crea
                    una visita ni emite una factura.
                </p>
                <label className="grid gap-1 text-sm">
                    Fecha de firma
                    <Input
                        type="date"
                        required
                        value={form.data.accepted_at}
                        onChange={(e) =>
                            form.setData('accepted_at', e.target.value)
                        }
                    />
                </label>
                <label className="grid gap-1 text-sm">
                    Nombre de la persona firmante
                    <Input
                        required
                        value={form.data.signed_by_name}
                        onChange={(e) =>
                            form.setData('signed_by_name', e.target.value)
                        }
                    />
                </label>
                <label className="grid gap-1 text-sm">
                    Justificante firmado (PDF, JPG o PNG; máximo 10 MB)
                    <Input
                        required
                        type="file"
                        accept=".pdf,.jpg,.jpeg,.png"
                        onChange={(e) =>
                            form.setData('proof', e.target.files?.[0] || null)
                        }
                    />
                </label>
                <label className="grid gap-1 text-sm">
                    Observaciones
                    <Textarea
                        value={form.data.reason}
                        onChange={(e) => form.setData('reason', e.target.value)}
                    />
                </label>
                {Object.entries(form.errors).map(([key, value]) => (
                    <InputError key={key} message={value} />
                ))}
                <Button
                    className="justify-self-start"
                    disabled={form.processing}
                >
                    Registrar aceptación en papel
                </Button>
            </form>
        </details>
    );
}

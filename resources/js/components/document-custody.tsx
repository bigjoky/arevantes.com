import { useLocale } from '@/hooks/use-locale';
import { translate } from '@/i18n';

export type CustodyStatus =
    | 'draft'
    | 'original_archived'
    | 'historical_original_missing'
    | 'integrity_failed';

export function originalDownloadAllowed(status?: CustodyStatus) {
    return status === 'draft' || status === 'original_archived';
}

export default function DocumentCustody({
    status,
    reconstructionHref,
}: {
    status?: CustodyStatus;
    reconstructionHref?: string | null;
}) {
    const { locale } = useLocale();

    if (!status || status === 'draft') {
        return null;
    }

    return (
        <p role="status" className="rounded-lg border p-3 text-sm">
            {status === 'original_archived'
                ? translate(
                      'Original archivado. Integridad del archivo verificada mediante SHA-256; esta comprobación no equivale a una firma electrónica.',
                      locale,
                  )
                : status === 'integrity_failed'
                  ? translate(
                        'El original no supera la comprobación de integridad. Descarga bloqueada: requiere revisión documental.',
                        locale,
                    )
                  : translate(
                        'Documento histórico sin original localizado. Descarga bloqueada: requiere revisión documental. No se generará un original sustitutivo.',
                        locale,
                    )}
            {status === 'historical_original_missing' && reconstructionHref && (
                <>
                    {' '}
                    <a
                        className="underline"
                        href={reconstructionHref}
                        target="_blank"
                        rel="noreferrer"
                    >
                        {translate(
                            'Descargar reconstrucción informativa (no es el original)',
                            locale,
                        )}
                    </a>
                </>
            )}
        </p>
    );
}

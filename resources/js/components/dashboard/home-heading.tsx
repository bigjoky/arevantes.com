import { usePage } from '@inertiajs/react';
import { PageHeader } from '@/components/page-shell';
import { useLocale } from '@/hooks/use-locale';
import { translate } from '@/i18n';

export function HomeHeading({ today }: { today?: string }) {
    const { auth, workspaceContext } = usePage().props;
    const { locale } = useLocale();
    const t = (key: string) => translate(key, locale);
    const date = today ? new Date(`${today}T12:00:00`) : new Date();
    const formatLocale = locale === 'es' ? 'es-ES' : 'en-GB';

    return (
        <div className="home-heading">
            <div className="home-title-block">
                <p className="home-eyebrow">
                    {t('Bienvenido de nuevo')}, {auth.user.name}
                </p>
                <PageHeader
                    breadcrumbs={[]}
                    title={t('Inicio')}
                    description={`${t('Resumen de la actividad de')} ${workspaceContext?.companyName ?? t('Tu empresa')}`}
                    secondaryNavigation={null}
                />
            </div>
            <div className="home-date">
                <span>
                    {new Intl.DateTimeFormat(formatLocale, {
                        weekday: 'long',
                    }).format(date)}
                </span>
                <strong>
                    {new Intl.DateTimeFormat(formatLocale, {
                        day: 'numeric',
                        month: 'long',
                        year: 'numeric',
                    }).format(date)}
                </strong>
                <i aria-hidden="true" />
            </div>
        </div>
    );
}

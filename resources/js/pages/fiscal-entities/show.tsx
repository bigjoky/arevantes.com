import { Head, Link } from '@inertiajs/react';
import { FiscalEntityEditor } from '@/components/fiscal-entity-editor';
import type {
    CountryOption,
    FiscalEntity,
} from '@/components/fiscal-entity-editor';
import { Button } from '@/components/ui/button';
import { useLocale } from '@/hooks/use-locale';
import { translate } from '@/i18n';

export default function FiscalEntityShow({
    entity,
    countries,
    revision,
}: {
    entity: FiscalEntity;
    countries: CountryOption[];
    revision: string;
}) {
    const { locale } = useLocale();

    return (
        <main className="mx-auto grid w-full max-w-4xl gap-5 p-4 sm:p-6">
            <Head title={entity.legal_name} />
            <Button asChild variant="outline" className="w-fit">
                <Link href="/fiscal-entities">
                    {translate('Fiscal directory', locale)}
                </Link>
            </Button>
            <h1 className="text-2xl font-semibold break-words">
                {entity.legal_name}
            </h1>
            <FiscalEntityEditor
                key={revision}
                entity={entity}
                countries={countries}
                revision={revision}
            />
        </main>
    );
}

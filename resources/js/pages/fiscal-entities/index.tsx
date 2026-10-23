import { Form, Head, Link } from '@inertiajs/react';
import { FiscalEntityEditor } from '@/components/fiscal-entity-editor';
import type {
    CountryOption,
    FiscalEntity,
} from '@/components/fiscal-entity-editor';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useLocale } from '@/hooks/use-locale';
import { translate } from '@/i18n';

export default function FiscalEntities({
    entities,
    filters,
    countries,
}: {
    entities: {
        data: Pick<
            FiscalEntity,
            'id' | 'country' | 'tax_id' | 'legal_name' | 'is_active'
        >[];
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    filters: { q: string };
    countries: CountryOption[];
}) {
    const { locale } = useLocale();

    return (
        <main className="mx-auto grid w-full max-w-5xl gap-6 p-4 sm:p-6">
            <Head title={translate('Fiscal directory', locale)} />
            <h1 className="text-2xl font-semibold">
                {translate('Fiscal directory', locale)}
            </h1>
            <p className="text-muted-foreground">
                {translate(
                    'One tax identity per country, with its legal name, brands and addresses.',
                    locale,
                )}
            </p>
            <Form
                action="/fiscal-entities"
                method="get"
                className="flex flex-wrap gap-2"
            >
                <Input
                    name="q"
                    defaultValue={filters.q}
                    aria-label={translate(
                        'Search by legal name or tax ID',
                        locale,
                    )}
                    placeholder={translate(
                        'Search by legal name or tax ID',
                        locale,
                    )}
                    className="min-w-0 flex-1"
                    maxLength={100}
                />
                <Button type="submit" variant="outline">
                    {translate('Search', locale)}
                </Button>
            </Form>
            <section className="grid gap-2">
                {entities.data.map((entity) => (
                    <Link
                        key={entity.id}
                        href={`/fiscal-entities/${entity.id}`}
                        className="flex flex-wrap justify-between gap-2 rounded-md border p-4 hover:bg-muted/40"
                    >
                        <span className="min-w-0 font-medium break-words">
                            {entity.legal_name}
                        </span>
                        <span className="text-sm text-muted-foreground">
                            {entity.country} · {entity.tax_id} ·{' '}
                            {translate(
                                entity.is_active ? 'Active' : 'Inactive',
                                locale,
                            )}
                        </span>
                    </Link>
                ))}
                {!entities.data.length ? (
                    <p>{translate('No fiscal entities found.', locale)}</p>
                ) : null}
                <nav
                    className="flex gap-3"
                    aria-label={translate('Pagination', locale)}
                >
                    {entities.prev_page_url ? (
                        <Link href={entities.prev_page_url}>
                            {translate('Previous', locale)}
                        </Link>
                    ) : null}
                    {entities.next_page_url ? (
                        <Link href={entities.next_page_url}>
                            {translate('Next', locale)}
                        </Link>
                    ) : null}
                </nav>
            </section>
            <details className="rounded-md border p-4">
                <summary className="cursor-pointer font-semibold">
                    {translate('New fiscal entity', locale)}
                </summary>
                <div className="mt-4">
                    <FiscalEntityEditor countries={countries} />
                </div>
            </details>
        </main>
    );
}

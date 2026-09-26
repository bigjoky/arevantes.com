import { Form, Head, Link } from '@inertiajs/react';
import { FormErrorSummary } from '@/components/forms/form-layout';
import { Button } from '@/components/ui/button';
import { useLocale } from '@/hooks/use-locale';
import { translate } from '@/i18n';

export default function ArchivedOpportunity({
    opportunity,
}: {
    opportunity: {
        id: number;
        code: string;
        title: string;
        deleted_at: string;
    };
}) {
    const { locale } = useLocale();

    return (
        <main className="mx-auto grid w-full max-w-3xl gap-4 p-4 sm:p-6">
            <Head title={opportunity.code} />
            <Button asChild variant="outline" className="w-fit">
                <Link href="/opportunities?archived=1">
                    {translate('Archived opportunities', locale)}
                </Link>
            </Button>
            <h1 className="text-2xl font-semibold break-words">
                {opportunity.code} · {opportunity.title}
            </h1>
            <p>
                {translate(
                    'This opportunity is archived. Restore it to continue working without losing its history.',
                    locale,
                )}
            </p>
            <Form
                action={`/opportunities/${opportunity.id}/restore`}
                method="post"
            >
                {({ processing, errors }) => (
                    <>
                        <FormErrorSummary errors={errors} />
                        <Button disabled={processing}>
                            {translate('Restore opportunity', locale)}
                        </Button>
                    </>
                )}
            </Form>
        </main>
    );
}

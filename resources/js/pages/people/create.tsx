import { Head, Link } from '@inertiajs/react';
import { PageHeader, PageShell } from '@/components/page-shell';
import PersonForm from '@/components/people/person-form';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';

type PeopleCreateProps = {
    category: 'customer' | 'staff';
    basePath?: string;
    createTitle?: string;
    createDescription?: string;
};

export default function PeopleCreate({
    category,
    basePath = '/people',
    createTitle = 'Add customer',
    createDescription = 'Create a customer profile for solar, EV charging, climate, electrical or security work.',
}: PeopleCreateProps) {
    return (
        <>
            <Head title={createTitle} />

            <PageShell>
                <PageHeader
                    title={createTitle}
                    description={createDescription}
                    actions={
                        <Button variant="outline" asChild>
                            <Link href={basePath}>Cancel</Link>
                        </Button>
                    }
                />
                <Card className="rounded-lg">
                    <CardContent>
                        <PersonForm
                            action={basePath}
                            forcedCategory={category}
                            submitLabel={createTitle}
                        />
                    </CardContent>
                </Card>
            </PageShell>
        </>
    );
}

PeopleCreate.layout = {
    breadcrumbs: [{ title: 'Add profile', href: '/people' }],
};

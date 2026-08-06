import { Head, Link } from '@inertiajs/react';
import { PageHeader, PageShell } from '@/components/page-shell';
import PersonForm from '@/components/people/person-form';
import type { PersonFormValue } from '@/components/people/person-form';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';

type PeopleEditProps = {
    person: PersonFormValue;
    category?: 'customer' | 'staff';
    basePath?: string;
    editTitle?: string;
    editDescription?: string;
    submitLabel?: string;
};

export default function PeopleEdit({
    person,
    category = 'customer',
    basePath = '/people',
    editTitle = 'Edit customer',
    editDescription = 'Update customer contact, billing, address, and service details.',
    submitLabel = 'Save customer',
}: PeopleEditProps) {
    return (
        <>
            <Head title={editTitle} />

            <PageShell>
                <PageHeader
                    title={editTitle}
                    description={editDescription}
                    actions={
                        <Button variant="outline" asChild>
                            <Link href={`${basePath}/${person.id}`}>
                                Cancel
                            </Link>
                        </Button>
                    }
                />
                <Card className="rounded-lg">
                    <CardContent>
                        <PersonForm
                            action={`${basePath}/${person.id}`}
                            method="patch"
                            person={person}
                            forcedCategory={category}
                            submitLabel={submitLabel}
                        />
                    </CardContent>
                </Card>
            </PageShell>
        </>
    );
}

PeopleEdit.layout = {
    breadcrumbs: [{ title: 'Edit person', href: '/people' }],
};

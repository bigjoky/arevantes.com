import { Head, Link } from '@inertiajs/react';
import PersonForm from '@/components/people/person-form';
import type { PersonFormValue } from '@/components/people/person-form';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

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

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <Card className="rounded-lg">
                    <CardHeader className="flex-row items-center justify-between gap-3">
                        <div>
                            <CardTitle>{editTitle}</CardTitle>
                            <CardDescription>{editDescription}</CardDescription>
                        </div>
                        <Button variant="outline" asChild>
                            <Link href={`${basePath}/${person.id}`}>
                                Cancel
                            </Link>
                        </Button>
                    </CardHeader>
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
            </div>
        </>
    );
}

PeopleEdit.layout = {
    breadcrumbs: [{ title: 'Edit person', href: '/people' }],
};

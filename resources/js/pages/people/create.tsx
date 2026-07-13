import { Head, Link } from '@inertiajs/react';
import PersonForm from '@/components/people/person-form';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

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
    createDescription = 'Create a customer profile for solar, EV charging, clima, or electrical work.',
}: PeopleCreateProps) {
    return (
        <>
            <Head title={createTitle} />

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <Card className="rounded-lg">
                    <CardHeader className="flex-row items-center justify-between gap-3">
                        <div>
                            <CardTitle>{createTitle}</CardTitle>
                            <CardDescription>
                                {createDescription}
                            </CardDescription>
                        </div>
                        <Button variant="outline" asChild>
                            <Link href={basePath}>Cancel</Link>
                        </Button>
                    </CardHeader>
                    <CardContent>
                        <PersonForm
                            action={basePath}
                            forcedCategory={category}
                            submitLabel={createTitle}
                        />
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

PeopleCreate.layout = {
    breadcrumbs: [{ title: 'Add profile', href: '/people' }],
};

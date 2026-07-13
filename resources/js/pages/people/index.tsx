import { Head, Link } from '@inertiajs/react';
import { KeyRound, Mail, Phone, Wrench } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

type Person = {
    id: number;
    category: 'customer' | 'staff';
    customer_type: string | null;
    first_name: string;
    last_name: string | null;
    company_name: string | null;
    staff_role: string | null;
    phone: string | null;
    email: string | null;
    city: string | null;
    country: string | null;
    installations_count: number | null;
    access?: { email: string; role: string } | null;
};

type PeopleIndexProps = {
    people: Person[];
    basePath?: string;
    title?: string;
    description?: string;
    createTitle?: string;
};

export default function PeopleIndex({
    people,
    basePath = '/people',
    title = 'People',
    description = 'Customer profiles for residential, business, community, and public-sector work.',
    createTitle = 'Add customer',
}: PeopleIndexProps) {
    const isStaff = basePath === '/staff';

    return (
        <>
            <Head title={title} />

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <Card className="rounded-lg">
                    <CardHeader className="flex-row items-center justify-between gap-3">
                        <div>
                            <CardTitle>{title}</CardTitle>
                            <CardDescription>{description}</CardDescription>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            <Button variant="outline" asChild>
                                <Link href="/dashboard">Back to dashboard</Link>
                            </Button>
                            <Button asChild>
                                <Link href={`${basePath}/create`}>
                                    {createTitle}
                                </Link>
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent>
                        {people.length > 0 ? (
                            <div className="overflow-hidden rounded-md border">
                                <div className="divide-y">
                                    {people.map((person) => (
                                        <Link
                                            key={person.id}
                                            href={`${basePath}/${person.id}`}
                                            className="grid gap-3 p-4 transition-colors hover:bg-muted/50 lg:grid-cols-[1fr_auto]"
                                        >
                                            <div>
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <span className="font-medium">
                                                        {person.company_name ||
                                                            `${person.first_name} ${person.last_name || ''}`}
                                                    </span>
                                                    <Badge
                                                        variant={
                                                            isStaff
                                                                ? 'secondary'
                                                                : 'outline'
                                                        }
                                                    >
                                                        {isStaff
                                                            ? 'staff'
                                                            : 'customer'}
                                                    </Badge>
                                                    {!isStaff &&
                                                        person.customer_type && (
                                                            <Badge variant="outline">
                                                                {
                                                                    person.customer_type
                                                                }
                                                            </Badge>
                                                        )}
                                                    {isStaff && (
                                                        <Badge
                                                            variant={
                                                                person.access
                                                                    ? 'outline'
                                                                    : 'secondary'
                                                            }
                                                        >
                                                            <KeyRound className="size-3" />
                                                            {person.access
                                                                ? person.access
                                                                      .role
                                                                : 'No CRM access'}
                                                        </Badge>
                                                    )}
                                                </div>
                                                <div className="mt-1 text-sm text-muted-foreground">
                                                    {person.staff_role ||
                                                        [
                                                            person.city,
                                                            person.country,
                                                        ]
                                                            .filter(Boolean)
                                                            .join(', ') ||
                                                        'No location'}
                                                </div>
                                            </div>

                                            <div className="grid gap-1 text-sm text-muted-foreground lg:text-right">
                                                <span className="inline-flex items-center gap-2 lg:justify-end">
                                                    <Mail className="size-4" />
                                                    {person.email || 'No email'}
                                                </span>
                                                <span className="inline-flex items-center gap-2 lg:justify-end">
                                                    <Phone className="size-4" />
                                                    {person.phone || 'No phone'}
                                                </span>
                                                {!isStaff && (
                                                    <span className="inline-flex items-center gap-2 lg:justify-end">
                                                        <Wrench className="size-4" />
                                                        {person.installations_count ??
                                                            0}{' '}
                                                        installations
                                                    </span>
                                                )}
                                            </div>
                                        </Link>
                                    ))}
                                </div>
                            </div>
                        ) : (
                            <div className="rounded-md border border-dashed p-6 text-sm text-muted-foreground">
                                {isStaff
                                    ? 'No staff profiles have been added yet.'
                                    : 'No customers have been added yet.'}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

PeopleIndex.layout = {
    breadcrumbs: [{ title: 'People', href: '/people' }],
};

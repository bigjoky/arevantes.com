import { Head, Link } from '@inertiajs/react';
import InstallationForm from '@/components/installations/installation-form';
import type {
    AssigneeOption,
    CustomerOption,
    InstallationFormValue,
    RemoteStationOption,
} from '@/components/installations/installation-form';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

export default function InstallationCreate({
    customers,
    installation,
    remoteStations,
    assignees,
}: {
    customers: CustomerOption[];
    installation?: InstallationFormValue | null;
    remoteStations: RemoteStationOption[];
    assignees: AssigneeOption[];
}) {
    return (
        <>
            <Head title="Add installation" />

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <Card className="rounded-lg">
                    <CardHeader className="flex-row items-start justify-between gap-3">
                        <div>
                            <CardTitle>Add installation</CardTitle>
                            <CardDescription>
                                Define the site and technical scope. Assigning a
                                customer is optional.
                            </CardDescription>
                        </div>
                        <Button variant="outline" asChild>
                            <Link href="/installations">Cancel</Link>
                        </Button>
                    </CardHeader>
                    <CardContent>
                        <InstallationForm
                            action="/installations"
                            customers={customers}
                            remoteStations={remoteStations}
                            assignees={assignees}
                            installation={installation ?? undefined}
                        />
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

InstallationCreate.layout = {
    breadcrumbs: [
        { title: 'Installations', href: '/installations' },
        { title: 'Add installation', href: '/installations/create' },
    ],
};

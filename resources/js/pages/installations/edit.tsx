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

export default function InstallationEdit({
    installation,
    customers,
    remoteStations,
    assignees,
}: {
    installation: InstallationFormValue;
    customers: CustomerOption[];
    remoteStations: RemoteStationOption[];
    assignees: AssigneeOption[];
}) {
    return (
        <>
            <Head title="Edit installation" />

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <Card className="rounded-lg">
                    <CardHeader className="flex-row items-center justify-between gap-3">
                        <div>
                            <CardTitle>Edit installation</CardTitle>
                            <CardDescription>
                                Update project status, site details, and the
                                service-specific technical fields.
                            </CardDescription>
                        </div>
                        <Button variant="outline" asChild>
                            <Link href={`/installations/${installation.id}`}>
                                Cancel
                            </Link>
                        </Button>
                    </CardHeader>
                    <CardContent>
                        <InstallationForm
                            action={`/installations/${installation.id}`}
                            method="patch"
                            customers={customers}
                            remoteStations={remoteStations}
                            assignees={assignees}
                            installation={installation}
                            submitLabel="Save installation"
                        />
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

InstallationEdit.layout = {
    breadcrumbs: [{ title: 'Edit installation', href: '/dashboard' }],
};

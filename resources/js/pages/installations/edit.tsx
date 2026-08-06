import { Head, Link } from '@inertiajs/react';
import InstallationForm from '@/components/installations/installation-form';
import type {
    AssigneeOption,
    CustomerOption,
    InstallationFormValue,
    RemoteStationOption,
} from '@/components/installations/installation-form';
import { PageHeader, PageShell } from '@/components/page-shell';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';

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

            <PageShell>
                <PageHeader
                    title="Edit installation"
                    description="Update project status, site details, and the service-specific technical fields."
                    actions={
                        <Button variant="outline" asChild>
                            <Link href={`/installations/${installation.id}`}>
                                Cancel
                            </Link>
                        </Button>
                    }
                />
                <Card className="rounded-lg">
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
            </PageShell>
        </>
    );
}

InstallationEdit.layout = {
    breadcrumbs: [{ title: 'Edit installation', href: '/dashboard' }],
};

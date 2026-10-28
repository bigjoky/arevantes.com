import '../../../css/projects-mobile.css';
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

            <PageShell className="projects-workspace">
                <PageHeader
                    title="Add installation"
                    description="Define the site and technical scope. Assigning a customer is optional."
                    actions={
                        <Button variant="outline" asChild>
                            <Link href="/installations">Cancel</Link>
                        </Button>
                    }
                />
                <Card className="rounded-lg">
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
            </PageShell>
        </>
    );
}

InstallationCreate.layout = {
    breadcrumbs: [
        { title: 'Installations', href: '/installations' },
        { title: 'Add installation', href: '/installations/create' },
    ],
};

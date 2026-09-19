import type { ComponentProps } from 'react';
import ServiceWorkspace from '@/components/after-sales/service-workspace';

export default function ContractsServices(
    props: Omit<ComponentProps<typeof ServiceWorkspace>, 'initialSection'>,
) {
    return <ServiceWorkspace {...props} initialSection="contracts" />;
}

ContractsServices.layout = {
    breadcrumbs: [
        { title: 'Contracts & services', href: '/contracts-services' },
    ],
};

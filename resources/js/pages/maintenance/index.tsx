import type { ComponentProps } from 'react';
import ServiceWorkspace from '@/components/after-sales/service-workspace';

export default function Maintenance(
    props: Omit<ComponentProps<typeof ServiceWorkspace>, 'initialSection'>,
) {
    return <ServiceWorkspace {...props} initialSection="maintenance" />;
}

Maintenance.layout = {
    breadcrumbs: [{ title: 'Maintenance', href: '/maintenance' }],
};

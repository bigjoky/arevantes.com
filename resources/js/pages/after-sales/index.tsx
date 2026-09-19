import type { ComponentProps } from 'react';
import ServiceWorkspace from '@/components/after-sales/service-workspace';

export default function CustomerService(
    props: Omit<ComponentProps<typeof ServiceWorkspace>, 'initialSection'>,
) {
    return <ServiceWorkspace {...props} initialSection="tickets" />;
}

CustomerService.layout = {
    breadcrumbs: [{ title: 'Customer service', href: '/after-sales' }],
};

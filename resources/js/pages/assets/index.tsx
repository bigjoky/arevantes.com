import type { ComponentProps } from 'react';
import ServiceWorkspace from '@/components/after-sales/service-workspace';

export default function Assets(
    props: Omit<ComponentProps<typeof ServiceWorkspace>, 'initialSection'>,
) {
    return <ServiceWorkspace {...props} initialSection="assets" />;
}

Assets.layout = { breadcrumbs: [{ title: 'Assets', href: '/assets' }] };

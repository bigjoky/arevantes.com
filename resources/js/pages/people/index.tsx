import { Head } from '@inertiajs/react';
import type { ComponentProps } from 'react';
import { CustomerList } from '@/components/customers/customer-list';
import type { CustomersProps } from '@/components/customers/types';
import { PageShell } from '@/components/page-shell';
import LegacyIndex from './legacy-index';
export default function CustomersIndex(
    props: CustomersProps & { basePath?: string },
) {
    if (props.basePath === '/staff') {
        return (
            <LegacyIndex
                {...(props as unknown as ComponentProps<typeof LegacyIndex>)}
            />
        );
    }

    return (
        <>
            <Head title="Clientes" />
            <PageShell className="customers-workspace">
                <CustomerList key={props.filters?.search ?? ''} {...props} />
            </PageShell>
        </>
    );
}
CustomersIndex.layout = {
    breadcrumbs: [
        { title: 'Comercial', href: '/commercial' },
        { title: 'Clientes', href: '/people' },
    ],
};

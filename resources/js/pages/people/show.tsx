import { Head } from '@inertiajs/react';
import type { ComponentProps } from 'react';
import { CustomerDetail } from '@/components/customers/customer-detail';
import type {
    CustomersProps,
    CustomerWorkspace,
} from '@/components/customers/types';
import { PageShell } from '@/components/page-shell';
import LegacyShow from './legacy-show';
type Props = CustomersProps &
    ComponentProps<typeof LegacyShow> & {
        customerWorkspace?: CustomerWorkspace;
    };
export default function CustomersShow(props: Props) {
    if (!props.customerWorkspace || props.person.category === 'staff') {
        return <LegacyShow {...props} />;
    }

    const name =
        props.person.company_name ||
        `${props.person.first_name} ${props.person.last_name ?? ''}`.trim();

    return (
        <>
            <Head title={`${name} · Clientes`} />
            <PageShell className="customers-workspace customers-detail-page">
                <CustomerDetail
                    person={props.person}
                    workspace={props.customerWorkspace}
                    filters={props.filters ?? {}}
                    availableTags={props.availableTags ?? []}
                />
            </PageShell>
        </>
    );
}
CustomersShow.layout = {
    breadcrumbs: [
        { title: 'Comercial', href: '/commercial' },
        { title: 'Clientes', href: '/people' },
    ],
};

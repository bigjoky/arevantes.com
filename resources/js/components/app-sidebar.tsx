import { Link, usePage } from '@inertiajs/react';
import {
    BriefcaseBusiness,
    Building2,
    CalendarDays,
    ChartNoAxesCombined,
    LayoutGrid,
    Settings2,
    ShoppingCart,
    UsersRound,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import type { NavItem, User } from '@/types';

export function AppSidebar() {
    const { user } = usePage<{ auth: { user: User } }>().props.auth;
    const commercial = user.role !== 'technician';
    const items: NavItem[] = [
        { title: 'Dashboard', href: dashboard(), icon: LayoutGrid },
        {
            title: 'Mi RR. HH.',
            href: '/my-human-resources',
            icon: CalendarDays,
        },
        ...(commercial
            ? [
                  {
                      title: 'CRM',
                      href: '/people',
                      icon: BriefcaseBusiness,
                      children: [
                          { title: 'Customers', href: '/people' },
                          { title: 'Opportunities', href: '/opportunities' },
                          { title: 'Sales', href: '/sales' },
                          { title: 'Installations', href: '/installations' },
                      ],
                  },
              ]
            : [
                  {
                      title: 'Installations',
                      href: '/installations',
                      icon: BriefcaseBusiness,
                  },
              ]),
        {
            title: 'Operations',
            href: '/operations',
            icon: Building2,
            children: [
                { title: 'Planning', href: '/operations' },
                { title: 'Work orders', href: '/work-orders' },
                { title: 'After-sales', href: '/after-sales' },
            ],
        },
        ...(commercial
            ? [
                  {
                      title: 'Supply',
                      href: '/catalog',
                      icon: ShoppingCart,
                      children: [
                          { title: 'Catalog', href: '/catalog' },
                          { title: 'Inventory', href: '/inventory' },
                          { title: 'Document inbox', href: '/purchase-inbox' },
                          { title: 'Purchases', href: '/purchases' },
                          { title: 'Expenses', href: '/expenses' },
                      ],
                  },
                  {
                      title: 'Finance',
                      href: '/finance',
                      icon: ChartNoAxesCombined,
                      children: [
                          { title: 'Overview', href: '/finance' },
                          { title: 'Banking', href: '/banking' },
                          { title: 'Journal & reports', href: '/accounting' },
                          {
                              title: 'Chart of accounts',
                              href: '/accounting/accounts',
                          },
                          {
                              title: 'Entry templates',
                              href: '/accounting/templates',
                          },
                          { title: 'VAT', href: '/tax' },
                          { title: 'Corporate tax', href: '/corporate-tax' },
                      ],
                  },
              ]
            : []),
        ...(user.role === 'admin'
            ? [
                  {
                      title: 'Recursos Humanos',
                      href: '/human-resources',
                      icon: UsersRound,
                      children: [
                          { title: 'Resumen', href: '/human-resources' },
                          {
                              title: 'Fichas laborales',
                              href: '/human-resources/employees',
                          },
                          {
                              title: 'Ausencias',
                              href: '/human-resources/leave',
                          },
                          {
                              title: 'Departamentos',
                              href: '/human-resources/departments',
                          },
                          { title: 'Equipo y accesos', href: '/staff' },
                      ],
                  },
                  {
                      title: 'Company',
                      href: '/knowledge',
                      icon: Settings2,
                      children: [
                          { title: 'Arevia knowledge', href: '/knowledge' },
                          {
                              title: 'Empresa y fiscalidad',
                              href: '/company-fiscal',
                          },
                          { title: 'Backups', href: '/admin/backups' },
                          { title: 'Audit log', href: '/admin/audit' },
                      ],
                  },
              ]
            : []),
    ];

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>
            <SidebarContent>
                <NavMain items={items} />
            </SidebarContent>
            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}

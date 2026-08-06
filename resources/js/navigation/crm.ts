import { BriefcaseBusiness } from 'lucide-react';
import type { NavItem, PlatformAbilities } from '@/types';

export function crmNavigation(abilities: PlatformAbilities): NavItem[] {
    if (abilities.manageCustomers) {
        return [
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
        ];
    }

    return abilities.viewInstallations
        ? [
              {
                  title: 'Installations',
                  href: '/installations',
                  icon: BriefcaseBusiness,
              },
          ]
        : [];
}

import { ChartNoAxesCombined } from 'lucide-react';
import type { NavItem, PlatformAbilities } from '@/types';

export function financeNavigation(abilities: PlatformAbilities): NavItem[] {
    return abilities.viewFinance
        ? [
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
        : [];
}

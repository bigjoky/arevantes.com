import { Building2 } from 'lucide-react';
import type { NavItem, PlatformAbilities } from '@/types';

export function operationsNavigation(abilities: PlatformAbilities): NavItem[] {
    return abilities.manageOperations
        ? [
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
          ]
        : [];
}

import { Settings2 } from 'lucide-react';
import type { NavItem, PlatformAbilities } from '@/types';

export function administrationNavigation(
    abilities: PlatformAbilities,
): NavItem[] {
    return abilities.manageStaff
        ? [
              {
                  title: 'Company',
                  href: '/knowledge',
                  icon: Settings2,
                  children: [
                      { title: 'Arevia knowledge', href: '/knowledge' },
                      {
                          title: 'Company & fiscal settings',
                          href: '/company-fiscal',
                      },
                      { title: 'Backups', href: '/admin/backups' },
                      { title: 'Audit log', href: '/admin/audit' },
                  ],
              },
          ]
        : [];
}

import { UsersRound } from 'lucide-react';
import type { NavItem, PlatformAbilities } from '@/types';

export function humanResourcesNavigation(
    abilities: PlatformAbilities,
): NavItem[] {
    return abilities.manageStaff
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
                      { title: 'Ausencias', href: '/human-resources/leave' },
                      {
                          title: 'Tareas y onboarding',
                          href: '/human-resources/tasks',
                      },
                      {
                          title: 'Departamentos',
                          href: '/human-resources/departments',
                      },
                      { title: 'Equipo y accesos', href: '/staff' },
                  ],
              },
          ]
        : [];
}

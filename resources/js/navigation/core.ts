import { CalendarDays, LayoutGrid } from 'lucide-react';
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';

export const coreNavigation: NavItem[] = [
    { title: 'Dashboard', href: dashboard(), icon: LayoutGrid },
    {
        title: 'Mi RR. HH.',
        href: '/my-human-resources',
        icon: CalendarDays,
    },
];

import { Link, usePage } from '@inertiajs/react';
import {
    Boxes,
    BriefcaseBusiness,
    CalendarClock,
    HeartPulse,
    ShieldCheck,
} from 'lucide-react';
import { cn } from '@/lib/utils';

const sections = [
    {
        href: '/after-sales',
        label: 'Customer service',
        icon: HeartPulse,
        exact: true,
    },
    {
        href: '/maintenance',
        label: 'Maintenance',
        icon: CalendarClock,
        exact: true,
    },
    {
        href: '/assets',
        label: 'Assets',
        icon: Boxes,
        exact: true,
    },
    {
        href: '/contracts-services',
        label: 'Contracts & services',
        icon: BriefcaseBusiness,
        exact: true,
    },
    {
        href: '/quality/service',
        label: 'Service quality',
        icon: ShieldCheck,
        exact: true,
    },
];

export function AfterSalesSectionNav() {
    const { url } = usePage();

    return (
        <nav
            aria-label="After-sales sections"
            className="flex flex-wrap gap-1 border-b"
        >
            {sections.map((section) => {
                const active = section.exact
                    ? url.split('?')[0] === section.href
                    : url.startsWith(section.href);
                const Icon = section.icon;

                return (
                    <Link
                        key={section.href}
                        href={section.href}
                        className={cn(
                            'flex items-center gap-2 border-b-2 px-3 py-2 text-sm transition-colors',
                            active
                                ? 'border-foreground font-medium text-foreground'
                                : 'border-transparent text-muted-foreground hover:text-foreground',
                        )}
                    >
                        <Icon className="size-4" />
                        {section.label}
                    </Link>
                );
            })}
        </nav>
    );
}

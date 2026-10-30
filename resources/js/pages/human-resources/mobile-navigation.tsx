import { Link, usePage } from '@inertiajs/react';

const sections = [
    ['/human-resources', 'Recursos Humanos'],
    ['/human-resources/employees', 'Fichas laborales'],
    ['/human-resources/departments', 'Departamentos'],
    ['/human-resources/leave', 'Ausencias'],
    ['/human-resources/tasks', 'Tareas'],
] as const;

/** Local navigation for the existing, permission-protected HR pages. */
export default function MobileHrNavigation() {
    const { url } = usePage();
    const path = url.split('?')[0];

    return (
        <nav className="hr-mobile-navigation" aria-label="Recursos Humanos">
            {sections.map(([href, label]) => (
                <Link
                    key={href}
                    href={href}
                    aria-current={
                        path === href ||
                        (href === '/human-resources/employees' &&
                            path.startsWith(href + '/'))
                            ? 'page'
                            : undefined
                    }
                >
                    {label}
                </Link>
            ))}
        </nav>
    );
}

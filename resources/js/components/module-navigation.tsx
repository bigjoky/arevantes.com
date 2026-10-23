import { Link, usePage } from '@inertiajs/react';
import { useShellContext } from '@/components/shell-context';
import {
    activeNavigation,
    contextualHref,
    navigationScore,
} from '@/lib/workspace-navigation';

export function ModuleNavigation() {
    const page = usePage();
    const { year } = useShellContext();
    const navigation = page.props.auth.navigation;
    const selected = activeNavigation(navigation, page.url);
    const module = navigation.find((item) =>
        item.children?.some((child) => child.id === selected),
    );
    const section = module?.children?.find((child) => child.id === selected);

    if (!module || !section) {
        return null;
    }

    const links = section.links ?? [];
    const best = Math.max(
        -1,
        ...links.map((link) => navigationScore(link.href, page.url)),
    );

    if (links.length < 2 && !section.pending?.length) {
        return null;
    }

    return (
        <nav aria-label={section.title} className="shell-tabs">
            {links.map((link) => (
                <Link
                    key={link.href}
                    href={contextualHref(link.href, year)}
                    aria-current={
                        best >= 0 &&
                        navigationScore(link.href, page.url) === best
                            ? 'page'
                            : undefined
                    }
                    className="shell-tab"
                >
                    {link.title}
                </Link>
            ))}
            {section.pending?.map((label) => (
                <span
                    key={label}
                    className="shell-tab opacity-50"
                    aria-disabled="true"
                    title={label}
                >
                    {label}
                </span>
            ))}
        </nav>
    );
}

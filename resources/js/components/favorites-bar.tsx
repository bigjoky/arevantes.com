import { Link, router, usePage } from '@inertiajs/react';
import { Star } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { useLocale } from '@/hooks/use-locale';
import { translate } from '@/i18n';

export function FavoritesBar() {
    const page = usePage();
    const { locale } = useLocale();
    const [saving, setSaving] = useState(false);
    const favorites = page.props.auth.shortcuts.favorites;
    const path = page.url.split('?')[0].split('#')[0];
    const current = page.props.auth.navigation
        .flatMap((item) => item.children ?? [item])
        .flatMap((item) => [item, ...(item.links ?? [])])
        .find((item) => item.href === path);
    const favorite = favorites.some((item) => item.href === path);
    const label = translate(
        favorite ? 'Remove from favorites' : 'Add to favorites',
        locale,
    );

    if (!current && favorites.length === 0) {
        return null;
    }

    return (
        <nav
            aria-label={translate('Favorites', locale)}
            className="flex min-w-0 shrink-0 items-center gap-2 border-b px-3 py-1.5 md:px-4"
        >
            {current && (
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    className="shrink-0"
                    title={label}
                    aria-label={label}
                    aria-pressed={favorite}
                    disabled={saving}
                    onClick={() => {
                        setSaving(true);
                        router.patch(
                            '/workspace/favorite',
                            { href: current.href, enabled: !favorite },
                            {
                                preserveScroll: true,
                                onFinish: () => setSaving(false),
                            },
                        );
                    }}
                >
                    <Star className={favorite ? 'fill-current' : ''} />
                </Button>
            )}
            <div className="flex min-w-0 flex-1 items-center gap-1 overflow-x-auto">
                {favorites.map((item) => (
                    <Button
                        key={item.href}
                        variant={item.href === path ? 'secondary' : 'ghost'}
                        size="sm"
                        className="shrink-0"
                        asChild
                    >
                        <Link
                            href={item.href}
                            prefetch
                            aria-current={
                                item.href === path ? 'page' : undefined
                            }
                        >
                            {translate(item.title, locale)}
                        </Link>
                    </Button>
                ))}
            </div>
        </nav>
    );
}

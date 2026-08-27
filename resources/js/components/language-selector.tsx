import { router, usePage } from '@inertiajs/react';
import { Globe2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useLocale } from '@/hooks/use-locale';

export function LanguageSelector() {
    const { current, supported } = usePage().props.localization;
    const { locale } = useLocale();
    const selected = supported.find((locale) => locale.code === current);
    const selectLanguage =
        locale === 'es' ? 'Seleccionar idioma' : 'Select language';

    const changeLanguage = (locale: string) => {
        if (locale === current) {
            return;
        }

        const previousLocale = document.documentElement.lang;
        document.documentElement.lang = locale;

        router.patch(
            '/locale',
            { locale },
            {
                preserveScroll: true,
                onError: () => {
                    document.documentElement.lang = previousLocale;
                },
            },
        );
    };

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    size="sm"
                    className="h-9 gap-1.5 px-2"
                    aria-label={selectLanguage}
                    title={selectLanguage}
                >
                    <Globe2 className="size-4" />
                    <span className="hidden text-xs font-medium sm:inline">
                        {selected?.short || current.toUpperCase()}
                    </span>
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-44">
                <DropdownMenuLabel>
                    {locale === 'es' ? 'Idioma' : 'Language'}
                </DropdownMenuLabel>
                <DropdownMenuRadioGroup
                    value={current}
                    onValueChange={changeLanguage}
                >
                    {supported.map((locale) => (
                        <DropdownMenuRadioItem
                            key={locale.code}
                            value={locale.code}
                        >
                            <span>{locale.label}</span>
                            <span className="ml-auto text-xs text-muted-foreground">
                                {locale.short}
                            </span>
                        </DropdownMenuRadioItem>
                    ))}
                </DropdownMenuRadioGroup>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

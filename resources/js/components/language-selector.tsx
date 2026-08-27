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

export function LanguageSelector() {
    const { current, supported } = usePage().props.localization;
    const selected = supported.find((locale) => locale.code === current);

    const changeLanguage = (locale: string) => {
        if (locale === current) {
            return;
        }

        router.patch(
            '/locale',
            { locale },
            {
                preserveScroll: true,
                onSuccess: () => {
                    document.documentElement.lang = locale;
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
                    aria-label="Select language"
                    title="Select language"
                >
                    <Globe2 className="size-4" />
                    <span className="hidden text-xs font-medium sm:inline">
                        {selected?.short || current.toUpperCase()}
                    </span>
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-44">
                <DropdownMenuLabel>Idioma / Language</DropdownMenuLabel>
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

import { router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useEffect, useId, useMemo, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { useIsMobile } from '@/hooks/use-mobile';

type Result = { group: string; title: string; subtitle: string; href: string };

export function GlobalSearch({ expanded = false }: { expanded?: boolean }) {
    const compact = useIsMobile(640);
    const [dialogOpen, setDialogOpen] = useState(false);
    const resultsId = useId();
    const root = useRef<HTMLDivElement>(null);
    const input = useRef<HTMLInputElement>(null);
    const [query, setQuery] = useState('');
    const [results, setResults] = useState<Result[]>([]);
    const [loading, setLoading] = useState(false);
    const [focused, setFocused] = useState(false);
    const [active, setActive] = useState(-1);

    useEffect(() => {
        const term = query.trim();

        if (term.length < 2) {
            return;
        }

        const controller = new AbortController();
        const timeout = window.setTimeout(async () => {
            setLoading(true);

            try {
                const response = await fetch(
                    `/search?q=${encodeURIComponent(term)}`,
                    {
                        signal: controller.signal,
                        headers: {
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    },
                );
                const data = await response.json();

                if (controller.signal.aborted) {
                    return;
                }

                setResults(response.ok ? data.results || [] : []);
                setActive(-1);
            } catch (error) {
                if (
                    !(
                        error instanceof DOMException &&
                        error.name === 'AbortError'
                    )
                ) {
                    setResults([]);
                }
            } finally {
                if (!controller.signal.aborted) {
                    setLoading(false);
                }
            }
        }, 180);

        return () => {
            window.clearTimeout(timeout);
            controller.abort();
        };
    }, [query]);

    useEffect(() => {
        const shortcut = (event: KeyboardEvent) => {
            if (
                (event.metaKey || event.ctrlKey) &&
                event.key.toLowerCase() === 'k'
            ) {
                event.preventDefault();
                setFocused(true);

                if (compact) {
                    setDialogOpen(true);
                } else {
                    input.current?.focus();
                }
            }
        };
        const outside = (event: MouseEvent) => {
            if (!root.current?.contains(event.target as Node)) {
                setFocused(false);
            }
        };
        window.addEventListener('keydown', shortcut);
        document.addEventListener('mousedown', outside);

        return () => {
            window.removeEventListener('keydown', shortcut);
            document.removeEventListener('mousedown', outside);
        };
    }, [compact]);

    const groups = useMemo(
        () =>
            results.reduce<Record<string, Result[]>>((all, item) => {
                (all[item.group] ||= []).push(item);

                return all;
            }, {}),
        [results],
    );
    const open = focused && query.trim().length >= 2;
    const navigate = (result: Result) => {
        setFocused(false);
        setDialogOpen(false);
        setQuery('');
        router.visit(result.href);
    };
    const changeQuery = (value: string) => {
        setQuery(value);
        setLoading(value.trim().length >= 2);
        setResults([]);
        setActive(-1);

        if (value.trim().length < 2) {
            setResults([]);
            setLoading(false);
            setActive(-1);
        }
    };
    const onKeyDown = (event: React.KeyboardEvent<HTMLInputElement>) => {
        if (event.key === 'ArrowDown') {
            event.preventDefault();
            setActive((value) => Math.min(value + 1, results.length - 1));
        }

        if (event.key === 'ArrowUp') {
            event.preventDefault();
            setActive((value) => Math.max(value - 1, -1));
        }

        if (event.key === 'Enter' && results.length) {
            event.preventDefault();
            navigate(results[active >= 0 ? active : 0]);
        }

        if (event.key === 'Escape') {
            setFocused(false);
            input.current?.blur();
            setDialogOpen(false);
        }
    };

    let index = -1;

    const content = (
        <div
            ref={root}
            className={
                expanded
                    ? 'relative w-full max-w-[502px]'
                    : 'relative w-32 sm:w-56 lg:w-80'
            }
        >
            <div className="relative">
                <Search className="pointer-events-none absolute top-1/2 left-3 z-10 size-4 -translate-y-1/2 text-muted-foreground" />
                <Input
                    role="combobox"
                    aria-expanded={open}
                    aria-controls={resultsId}
                    aria-autocomplete="list"
                    aria-activedescendant={
                        active >= 0 ? `${resultsId}-${active}` : undefined
                    }
                    ref={input}
                    type="search"
                    value={query}
                    onChange={(event) => changeQuery(event.target.value)}
                    onFocus={() => setFocused(true)}
                    onKeyDown={onKeyDown}
                    className="h-9 bg-muted/30 pr-12 pl-9"
                    placeholder="Buscar en AREVANTES…"
                    aria-label="Búsqueda global"
                    autoComplete="off"
                />
                {!compact && (
                    <kbd className="pointer-events-none absolute top-2 right-3 text-xs text-muted-foreground">
                        ⌘ K
                    </kbd>
                )}
            </div>
            {open && (
                <div
                    id={resultsId}
                    role="listbox"
                    aria-label="Resultados de búsqueda"
                    className={`${compact ? 'relative mt-3 max-h-[50dvh] w-full' : 'absolute top-[calc(100%+0.5rem)] right-0 max-h-[min(32rem,70vh)] w-[min(34rem,calc(100vw-2rem))]'} z-50 overflow-y-auto rounded-md border bg-popover p-2 text-popover-foreground shadow-sm`}
                >
                    {Object.entries(groups).map(([group, items]) => (
                        <section key={group}>
                            <h3 className="px-2 py-2 text-xs font-semibold text-muted-foreground">
                                {group}
                            </h3>
                            {items.map((item) => {
                                index += 1;
                                const itemIndex = index;

                                return (
                                    <button
                                        key={`${item.group}-${item.href}`}
                                        id={`${resultsId}-${itemIndex}`}
                                        role="option"
                                        aria-selected={active === itemIndex}
                                        type="button"
                                        onMouseDown={(event) =>
                                            event.preventDefault()
                                        }
                                        onClick={() => navigate(item)}
                                        onMouseEnter={() =>
                                            setActive(itemIndex)
                                        }
                                        className={`block w-full rounded-md px-3 py-2 text-left ${active === itemIndex ? 'bg-accent' : 'hover:bg-accent'}`}
                                    >
                                        <div className="text-sm font-medium">
                                            {item.title}
                                        </div>
                                        <div className="truncate text-xs text-muted-foreground">
                                            {item.subtitle}
                                        </div>
                                    </button>
                                );
                            })}
                        </section>
                    ))}
                    {loading && (
                        <p className="py-6 text-center text-sm text-muted-foreground">
                            Searching…
                        </p>
                    )}
                    {!loading && results.length === 0 && (
                        <p className="py-6 text-center text-sm text-muted-foreground">
                            No matching records.
                        </p>
                    )}
                </div>
            )}
        </div>
    );

    if (!compact) {
        return content;
    }

    return (
        <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
            <DialogTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    aria-label="Búsqueda global"
                >
                    <Search className="size-4" />
                </Button>
            </DialogTrigger>
            <DialogContent
                className="top-[10vh] translate-y-0 overflow-visible"
                aria-describedby={undefined}
            >
                <DialogHeader>
                    <DialogTitle>Búsqueda global</DialogTitle>
                </DialogHeader>
                {content}
            </DialogContent>
        </Dialog>
    );
}

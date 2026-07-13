import { router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { Input } from '@/components/ui/input';

type Result = { group: string; title: string; subtitle: string; href: string };

export function GlobalSearch() {
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
                input.current?.focus();
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
    }, []);

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
        setQuery('');
        router.visit(result.href);
    };
    const changeQuery = (value: string) => {
        setQuery(value);

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
        }
    };

    let index = -1;

    return (
        <div ref={root} className="relative w-32 sm:w-56 lg:w-80">
            <Search className="pointer-events-none absolute top-1/2 left-3 z-10 size-4 -translate-y-1/2 text-muted-foreground" />
            <Input
                ref={input}
                type="search"
                value={query}
                onChange={(event) => changeQuery(event.target.value)}
                onFocus={() => setFocused(true)}
                onKeyDown={onKeyDown}
                className="h-9 bg-background pr-3 pl-9"
                placeholder="Search CRM…"
                aria-label="Search CRM"
                autoComplete="off"
            />
            {open && (
                <div className="absolute top-[calc(100%+0.5rem)] right-0 z-50 max-h-[min(32rem,70vh)] w-[min(34rem,calc(100vw-2rem))] overflow-y-auto rounded-md border bg-popover p-2 text-popover-foreground shadow-lg">
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
}

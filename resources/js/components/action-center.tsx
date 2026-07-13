import { router } from '@inertiajs/react';
import { AlertCircle, Bell, CalendarClock, CircleCheck } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';

type ActionItem = {
    id: string;
    title: string;
    subtitle: string;
    href: string;
    severity: 'critical' | 'warning' | 'neutral';
    due_at: string | null;
};

type ActionGroup = { label: string; count: number; items: ActionItem[] };
type ActionData = {
    total: number;
    groups: ActionGroup[];
    generated_at: string;
};

export function ActionCenter() {
    const [open, setOpen] = useState(false);
    const [loading, setLoading] = useState(false);
    const [data, setData] = useState<ActionData>({
        total: 0,
        groups: [],
        generated_at: '',
    });

    const refresh = useCallback(async () => {
        setLoading(true);

        try {
            const response = await fetch('/action-center', {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (response.ok) {
                setData(await response.json());
            }
        } finally {
            setLoading(false);
        }
    }, []);

    useEffect(() => {
        const initial = window.setTimeout(refresh, 0);
        const interval = window.setInterval(refresh, 60_000);

        return () => {
            window.clearTimeout(initial);
            window.clearInterval(interval);
        };
    }, [refresh]);

    const visit = (href: string) => {
        setOpen(false);
        router.visit(href);
    };

    return (
        <Sheet open={open} onOpenChange={setOpen}>
            <Tooltip>
                <TooltipTrigger asChild>
                    <SheetTrigger asChild>
                        <Button
                            variant="ghost"
                            size="icon"
                            className="relative size-9"
                            aria-label="Action center"
                        >
                            <Bell className="size-4" />
                            {data.total > 0 && (
                                <span className="absolute -top-0.5 -right-0.5 flex min-h-4 min-w-4 items-center justify-center rounded-full bg-destructive px-1 text-[10px] leading-none font-semibold text-destructive-foreground">
                                    {data.total > 99 ? '99+' : data.total}
                                </span>
                            )}
                        </Button>
                    </SheetTrigger>
                </TooltipTrigger>
                <TooltipContent>Action center</TooltipContent>
            </Tooltip>
            <SheetContent className="w-full gap-0 overflow-hidden p-0 sm:max-w-md">
                <SheetHeader className="border-b px-5 py-4">
                    <SheetTitle>Action center</SheetTitle>
                    <SheetDescription>
                        {data.total
                            ? `${data.total} items require attention`
                            : 'Everything is up to date'}
                    </SheetDescription>
                </SheetHeader>

                <div className="flex-1 overflow-y-auto">
                    {loading && data.groups.length === 0 ? (
                        <div className="space-y-3 p-5">
                            {[1, 2, 3].map((item) => (
                                <div
                                    key={item}
                                    className="h-16 animate-pulse rounded-md bg-muted"
                                />
                            ))}
                        </div>
                    ) : data.groups.length === 0 ? (
                        <div className="flex h-full min-h-72 flex-col items-center justify-center px-8 text-center">
                            <CircleCheck className="mb-3 size-8 text-emerald-600 dark:text-emerald-400" />
                            <p className="font-medium">No pending actions</p>
                            <p className="mt-1 text-sm text-muted-foreground">
                                New tasks and exceptions will appear here.
                            </p>
                        </div>
                    ) : (
                        data.groups.map((group) => (
                            <section
                                key={group.label}
                                className="border-b last:border-b-0"
                            >
                                <div className="flex items-center justify-between bg-muted/40 px-5 py-2.5">
                                    <h3 className="text-xs font-semibold text-muted-foreground uppercase">
                                        {group.label}
                                    </h3>
                                    <span className="text-xs text-muted-foreground tabular-nums">
                                        {group.count}
                                    </span>
                                </div>
                                <div>
                                    {group.items.map((item) => (
                                        <button
                                            key={item.id}
                                            type="button"
                                            onClick={() => visit(item.href)}
                                            className="flex w-full gap-3 border-t px-5 py-3 text-left first:border-t-0 hover:bg-accent focus-visible:bg-accent focus-visible:outline-none"
                                        >
                                            {item.severity === 'critical' ? (
                                                <AlertCircle className="mt-0.5 size-4 shrink-0 text-destructive" />
                                            ) : (
                                                <CalendarClock
                                                    className={`mt-0.5 size-4 shrink-0 ${item.severity === 'warning' ? 'text-amber-600 dark:text-amber-400' : 'text-muted-foreground'}`}
                                                />
                                            )}
                                            <span className="min-w-0">
                                                <span className="block truncate text-sm font-medium">
                                                    {item.title}
                                                </span>
                                                <span className="mt-0.5 block truncate text-xs text-muted-foreground">
                                                    {item.subtitle}
                                                </span>
                                            </span>
                                        </button>
                                    ))}
                                    {group.count > group.items.length && (
                                        <p className="border-t px-5 py-2 text-xs text-muted-foreground">
                                            {group.count - group.items.length}{' '}
                                            more in this area
                                        </p>
                                    )}
                                </div>
                            </section>
                        ))
                    )}
                </div>
            </SheetContent>
        </Sheet>
    );
}

import { Link } from '@inertiajs/react';
import type { ComponentProps, ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

export function PageShell({ className, ...props }: ComponentProps<'div'>) {
    return (
        <div
            className={cn(
                'flex min-w-0 flex-1 flex-col gap-6 p-4 md:p-6',
                className,
            )}
            {...props}
        />
    );
}

export function PageHeader({
    title,
    description,
    actions,
    className,
}: {
    title: ReactNode;
    description?: ReactNode;
    actions?: ReactNode;
    className?: string;
}) {
    return (
        <header
            className={cn(
                'flex min-w-0 flex-col gap-4 sm:flex-row sm:items-start sm:justify-between',
                className,
            )}
        >
            <div className="min-w-0">
                <h1 className="text-xl font-semibold tracking-tight">
                    {title}
                </h1>
                {description ? (
                    <p className="mt-0.5 max-w-3xl text-sm text-muted-foreground">
                        {description}
                    </p>
                ) : null}
            </div>
            {actions ? (
                <div className="flex w-full shrink-0 flex-wrap gap-2 sm:w-auto sm:justify-end">
                    {actions}
                </div>
            ) : null}
        </header>
    );
}

export function PageTabs({ className, ...props }: ComponentProps<'nav'>) {
    return (
        <nav
            className={cn(
                '-mx-1 flex min-w-0 [scrollbar-width:thin] gap-1 overflow-x-auto border-b px-1 [&>*]:shrink-0',
                className,
            )}
            {...props}
        />
    );
}

export function TableViewport({ className, ...props }: ComponentProps<'div'>) {
    return (
        <div
            className={cn(
                'min-w-0 [scrollbar-width:thin] overflow-x-auto [&_td]:px-3 [&_td:first-child]:pl-0 [&_td:last-child]:pr-0 [&_th]:px-3 [&_th:first-child]:pl-0 [&_th:last-child]:pr-0',
                className,
            )}
            {...props}
        />
    );
}

export type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

export function PaginationBar({
    links,
    total,
    noun,
}: {
    links: PaginationLink[];
    total: number;
    noun: string;
}) {
    if (total === 0) {
        return null;
    }

    return (
        <div className="flex flex-wrap items-center justify-between gap-3 text-sm">
            <span className="text-muted-foreground">
                {total} {noun}
            </span>
            <div className="flex max-w-full gap-1 overflow-x-auto pb-1">
                {links.map((link, index) =>
                    link.url ? (
                        <Button
                            key={`${link.label}-${index}`}
                            size="sm"
                            variant={link.active ? 'default' : 'outline'}
                            asChild
                        >
                            <Link
                                href={link.url}
                                preserveScroll
                                dangerouslySetInnerHTML={{
                                    __html: link.label,
                                }}
                            />
                        </Button>
                    ) : null,
                )}
            </div>
        </div>
    );
}

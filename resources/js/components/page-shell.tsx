import { Link, usePage } from '@inertiajs/react';
import { MoreHorizontal } from 'lucide-react';
import type { ComponentProps, ReactNode } from 'react';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { ModuleNavigation } from '@/components/module-navigation';
import { useShellContext } from '@/components/shell-context';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { cn } from '@/lib/utils';
import { activeNavigation, contextualHref } from '@/lib/workspace-navigation';
import type { BreadcrumbItem } from '@/types';

export type ContentMode = 'full' | 'standard' | 'focused' | 'master-detail';

export function PageShell({
    className,
    mode = 'full',
    ...props
}: ComponentProps<'div'> & { mode?: ContentMode }) {
    return (
        <div
            data-content-mode={mode}
            className={cn('shell-content', className)}
            {...props}
        />
    );
}

export function PageActions({
    primary,
    secondary,
    children,
    className,
}: {
    primary?: ReactNode;
    secondary?: ReactNode;
    children?: ReactNode;
    className?: string;
}) {
    return (
        <div className={cn('shell-actions', className)}>
            {children}
            {secondary && (
                <>
                    <div className="hidden items-center gap-2 sm:flex">
                        {secondary}
                    </div>
                    <Sheet>
                        <SheetTrigger asChild>
                            <Button
                                variant="outline"
                                size="icon"
                                className="sm:hidden"
                                aria-label="Acciones de página"
                            >
                                <MoreHorizontal className="size-4" />
                            </Button>
                        </SheetTrigger>
                        <SheetContent
                            side="bottom"
                            aria-describedby={undefined}
                        >
                            <SheetHeader>
                                <SheetTitle>Acciones de página</SheetTitle>
                            </SheetHeader>
                            <div className="flex flex-wrap gap-2 p-4 pb-[max(16px,env(safe-area-inset-bottom))]">
                                {secondary}
                            </div>
                        </SheetContent>
                    </Sheet>
                </>
            )}
            {primary && <div className="shell-primary-action">{primary}</div>}
        </div>
    );
}

export function PageHeader({
    title,
    description,
    actions,
    primaryAction,
    secondaryActions,
    breadcrumbs,
    secondaryNavigation,
    metadata,
    className,
}: {
    title: ReactNode;
    description?: ReactNode;
    actions?: ReactNode;
    primaryAction?: ReactNode;
    secondaryActions?: ReactNode;
    breadcrumbs?: BreadcrumbItem[];
    secondaryNavigation?: ReactNode;
    metadata?: ReactNode;
    className?: string;
}) {
    const page = usePage();
    const shell = useShellContext();
    const selected = activeNavigation(page.props.auth.navigation, page.url);
    const module = page.props.auth.navigation.find((item) =>
        item.children?.some((child) => child.id === selected),
    );
    const section = module?.children?.find((child) => child.id === selected);
    const trail =
        breadcrumbs ??
        (module && section
            ? [
                  {
                      title: module.title,
                      href: contextualHref(module.href, shell.year),
                  },
                  {
                      title: section.title,
                      href: contextualHref(section.href, shell.year),
                  },
                  ...shell.breadcrumbs.filter((item) => {
                      const path =
                          typeof item.href === 'string'
                              ? new URL(item.href, 'http://localhost').pathname
                              : item.href.url;

                      return (
                          path ===
                              new URL(page.url, 'http://localhost').pathname &&
                          path !==
                              new URL(section.href, 'http://localhost')
                                  .pathname &&
                          !section.links?.some(
                              (link) =>
                                  new URL(link.href, 'http://localhost')
                                      .pathname === path,
                          )
                      );
                  }),
              ]
            : shell.breadcrumbs);

    return (
        <header className={cn('shell-page-header', className)}>
            {trail.length > 0 && <Breadcrumbs breadcrumbs={trail} />}
            <div className="shell-page-heading">
                <div className="min-w-0">
                    <h1>{title}</h1>
                    {description && (
                        <p className="shell-page-description">{description}</p>
                    )}
                    {metadata && (
                        <div className="mt-2 text-xs text-muted-foreground">
                            {metadata}
                        </div>
                    )}
                </div>
                {(actions || primaryAction || secondaryActions) && (
                    <PageActions
                        primary={primaryAction}
                        secondary={secondaryActions}
                    >
                        {actions}
                    </PageActions>
                )}
            </div>
            {secondaryNavigation === undefined ? (
                <ModuleNavigation />
            ) : (
                secondaryNavigation
            )}
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

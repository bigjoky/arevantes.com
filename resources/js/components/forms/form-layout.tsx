import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { useLocale } from '@/hooks/use-locale';
import { translate } from '@/i18n';
import { cn } from '@/lib/utils';

export function FormSection({
    title,
    description,
    children,
    className,
}: {
    title: ReactNode;
    description?: ReactNode;
    children: ReactNode;
    className?: string;
}) {
    return (
        <section
            className={cn(
                'min-w-0 space-y-4 rounded-lg border bg-card p-4 sm:p-5',
                className,
            )}
        >
            <div>
                <h2 className="text-sm font-semibold">{title}</h2>
                {description && (
                    <p className="mt-1 max-w-3xl text-xs text-muted-foreground">
                        {description}
                    </p>
                )}
            </div>
            <div className="min-w-0 space-y-4">{children}</div>
        </section>
    );
}

export function FormErrorSummary({
    errors,
}: {
    errors: Record<string, string | undefined>;
}) {
    const { locale } = useLocale();
    const messages = [...new Set(Object.values(errors).filter(Boolean))];

    if (messages.length === 0) {
        return null;
    }

    return (
        <div
            role="alert"
            className="rounded-lg border border-destructive/40 bg-destructive/5 p-4 text-sm"
        >
            <p className="font-medium text-destructive">
                {translate('Review the highlighted fields', locale)}
            </p>
            <ul className="mt-2 list-disc space-y-1 pl-5 text-muted-foreground">
                {messages.map((message) => (
                    <li key={message}>{message}</li>
                ))}
            </ul>
        </div>
    );
}

export function FormActions({
    processing,
    submitLabel,
    processingLabel,
    description,
    icon,
}: {
    processing: boolean;
    submitLabel: ReactNode;
    processingLabel?: ReactNode;
    description?: ReactNode;
    icon?: ReactNode;
}) {
    const { locale } = useLocale();

    return (
        <div
            className={cn(
                'sticky bottom-2 z-10 flex flex-col gap-3 rounded-lg border bg-background/95 p-3 shadow-lg backdrop-blur-sm sm:flex-row sm:items-center',
                description ? 'sm:justify-between' : 'sm:justify-end',
            )}
        >
            {description && (
                <p className="max-w-2xl text-sm text-muted-foreground">
                    {description}
                </p>
            )}
            <Button className="w-full sm:w-auto" disabled={processing}>
                {icon}
                {processing
                    ? (processingLabel ?? translate('Saving...', locale))
                    : submitLabel}
            </Button>
        </div>
    );
}
